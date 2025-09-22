<?php

namespace Wpextended\Modules\CodeSnippets\Includes;

use Wpextended\Includes\Notices;

/**
 * Executes code snippets at runtime based on their type and location.
 *
 * Handles safe mode, request-context checks, and output buffering safeguards for
 * REST/AJAX contexts. Non-PHP snippets are queued and printed on appropriate hooks.
 *
 * @since 3.1.0
 */
class SnippetExecutor
{
    private $snippetManager;
    private $safeMode;
    private $processedSnippets = [];
    private $snippetQueue = [];

    /**
     * Construct the executor and bootstrap if the current user/context permits.
     *
     * @since 3.1.0
     */
    public function __construct()
    {
        if (is_admin() && !current_user_can('manage_options')) {
            return;
        }

        $this->snippetManager = new SnippetManager();
        $this->safeMode = $this->isSafeMode();
        $this->init();
    }

    /**
     * Determine whether safe mode is currently active.
     *
     * @since 3.1.0
     * @return bool
     */
    private function isSafeMode()
    {
        if (!current_user_can('manage_options')) {
            if (isset($_COOKIE['wpextended_safe_mode']) && $_COOKIE['wpextended_safe_mode'] === '1') {
                return true;
            }
            return defined('WP_EXTENDED_SAFE_MODE') && WP_EXTENDED_SAFE_MODE;
        }

        if (isset($_GET['wpextended_safe_mode']) && $_GET['wpextended_safe_mode'] === '1') {
            setcookie('wpextended_safe_mode', '1', time() + (30 * 24 * 60 * 60), '/', '', is_ssl(), true);
            return true;
        }

        if (isset($_GET['wpextended_safe_mode']) && $_GET['wpextended_safe_mode'] === '0') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpextended_safe_mode')) {
                return defined('WP_EXTENDED_SAFE_MODE') && WP_EXTENDED_SAFE_MODE;
            }

            setcookie('wpextended_safe_mode', '', time() - 3600, '/', '', is_ssl(), true);
            return false;
        }

        if (isset($_COOKIE['wpextended_safe_mode']) && $_COOKIE['wpextended_safe_mode'] === '1') {
            return true;
        }

        return defined('WP_EXTENDED_SAFE_MODE') && WP_EXTENDED_SAFE_MODE;
    }

    /**
     * Initialize executor behavior for the current request.
     *
     * @since 3.1.0
     * @return void
     */
    private function init()
    {
        if (isset($_GET['wpextended_safe_mode']) && $_GET['wpextended_safe_mode'] === '0') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpextended_safe_mode')) {
                $this->displaySafeModeDisabledNotice();
            }
        }

        if ($this->safeMode) {
            $this->displaySafeModeNotice();
            return;
        }

        if ($this->shouldSkipExecution()) {
            return;
        }

        $this->loadPhpSnippets();
        $this->processNonPhpSnippets();
    }

    /**
     * Check if snippet execution should be skipped for this request.
     *
     * @since 3.1.0
     * @return bool
     */
    private function shouldSkipExecution()
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }
        return false;
    }

    /**
     * Load PHP snippets early so they can register their own hooks.
     *
     * @since 3.1.0
     * @return void
     */
    public function loadPhpSnippets()
    {
        $snippets = $this->snippetManager->getAllSnippets();
        foreach ($snippets as $snippet) {
            if (!$snippet->isEnabled()) {
                continue;
            }
            if (method_exists($snippet, 'canExecute') && !$snippet->canExecute()) {
                continue;
            }
            if ($snippet->getType() === 'php') {
                $this->includePhpSnippet($snippet);
            }
        }
    }

    /**
     * Queue non-PHP snippets for output on their appropriate hooks.
     *
     * @since 3.1.0
     * @return void
     */
    private function processNonPhpSnippets()
    {
        $snippets = $this->snippetManager->getAllSnippets();
        foreach ($snippets as $snippet) {
            if (!$snippet->isEnabled()) {
                continue;
            }
            if (method_exists($snippet, 'canExecute') && !$snippet->canExecute()) {
                continue;
            }
            $type = $snippet->getType();
            if ($type !== 'php') {
                $this->processNonPhpSnippet($snippet);
            }
        }
        $this->registerQueuedSnippetHooks();
    }

    /**
     * Detect whether the current request targets the REST API.
     *
     * @since 3.1.0
     * @return bool
     */
    private function isRestApiRequest()
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return true;
        }
        if (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Include a PHP snippet file if permitted and within allowed paths.
     *
     * @since 3.1.0
     * @param Snippet $snippet
     * @return void
     */
    private function includePhpSnippet($snippet)
    {
        $filePath = $this->getSnippetFilePath($snippet);
        if (!$filePath || !$this->isSnippetFileValid($filePath)) {
            return;
        }
        if (!$snippet->verifyIntegrity()) {
            return;
        }
        $realPath = realpath($filePath);
        $allowedDir = realpath(WP_CONTENT_DIR . '/wpextended-snippets');
        if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
            return;
        }

        $location = $snippet->getRunLocation();
        $shouldExecute = false;
        switch ($location) {
            case 'everywhere':
                $shouldExecute = true;
                break;
            case 'admin_only':
                $shouldExecute = is_admin();
                break;
            case 'frontend_only':
                $shouldExecute = !is_admin();
                break;
            default:
                $shouldExecute = true;
                break;
        }
        if (!$shouldExecute) {
            return;
        }

        try {
            if ($this->isRestApiRequest()) {
                ob_start();
            }
            include $filePath;
            if ($this->isRestApiRequest()) {
                ob_end_clean();
            }
        } catch (\Exception $e) {
            $this->logError($snippet, $e);
            if ($this->isRestApiRequest() && ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    /**
     * Extract and enqueue a non-PHP snippet for later output.
     *
     * @since 3.1.0
     * @param Snippet $snippet
     * @return void
     */
    private function processNonPhpSnippet($snippet)
    {
        $snippetId = $snippet->getId();
        if (isset($this->processedSnippets[$snippetId])) {
            return;
        }
        try {
            $filePath = $this->getSnippetFilePath($snippet);
            if (!$filePath || !file_exists($filePath) || !$this->isSnippetFileValid($filePath)) {
                throw new \Exception('Invalid snippet file');
            }
            if (!$snippet->verifyIntegrity()) {
                return;
            }
            $content = $this->getSnippetContent($filePath);
            $extractedCode = $this->extractCode($content, $snippet->getType());
            if ($extractedCode === false) {
                throw new \Exception('Failed to extract code from snippet');
            }
            $hook = $this->getHookForSnippet($snippet);
            if (!$hook) {
                return;
            }
            if (!isset($this->snippetQueue[$hook])) {
                $this->snippetQueue[$hook] = [];
            }
            $this->snippetQueue[$hook][] = [
                'snippet' => $snippet,
                'code' => $extractedCode
            ];
            $this->processedSnippets[$snippetId] = true;
        } catch (\Throwable $e) {
            $this->logError($snippet, $e);
        }
    }

    /**
     * Read the raw file contents of a snippet.
     *
     * @since 3.1.0
     * @param string $filePath
     * @return string|false
     */
    private function getSnippetContent($filePath)
    {
        return file_get_contents($filePath);
    }

    /**
     * Extract code for non-PHP snippets (strip header and wrappers).
     *
     * @since 3.1.0
     * @param string $content
     * @param string $type
     * @return string|false
     */
    private function extractCode($content, $type)
    {
        $content = trim($content);
        if (in_array($type, ['html', 'js', 'css'])) {
            $content = preg_replace('/<\?php.*?\?>\s*/s', '', $content);
        }
        switch ($type) {
            case 'js':
                if (preg_match('/<script[^>]*>(.*?)<\/script>/is', $content, $matches)) {
                    return trim($matches[1]);
                }
                return $content;
            case 'css':
                if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $content, $matches)) {
                    return trim($matches[1]);
                }
                return $content;
            case 'html':
                return $content;
            default:
                return false;
        }
    }

    /**
     * Register output hooks to print queued non-PHP snippets.
     *
     * @since 3.1.0
     * @return void
     */
    private function registerQueuedSnippetHooks()
    {
        foreach ($this->snippetQueue as $hook => $snippets) {
            add_action($hook, function () use ($hook) {
                $this->outputQueuedSnippets($hook);
            }, 10);
        }
    }

    /**
     * Output snippets queued for a specific hook.
     *
     * @since 3.1.0
     * @param string $hook
     * @return void
     */
    private function outputQueuedSnippets($hook)
    {
        if (!isset($this->snippetQueue[$hook])) {
            return;
        }
        foreach ($this->snippetQueue[$hook] as $item) {
            $snippet = $item['snippet'];
            $code = $item['code'];
            $this->outputSnippet($snippet, $code);
        }
    }

    /**
     * Echo a snippet's code wrapped appropriately for its type.
     *
     * @since 3.1.0
     * @param Snippet $snippet
     * @param string  $code
     * @return void
     */
    private function outputSnippet($snippet, $code)
    {
        $type = strtolower($snippet->getType());
        switch ($type) {
            case 'js':
                printf('<script>%s</script>', "\n" . $code . "\n");
                break;
            case 'css':
                printf('<style>%s</style>', "\n" . $code . "\n");
                break;
            case 'html':
                echo "\n" . $code . "\n";
                break;
        }
    }

    /**
     * Resolve a snippet's file path from the entity.
     *
     * @since 3.1.0
     * @param Snippet $snippet
     * @return string|null
     */
    private function getSnippetFilePath($snippet)
    {
        if (method_exists($snippet, 'getFilePath')) {
            return $snippet->getFilePath();
        }
        if (method_exists($snippet, 'getId')) {
            $snippetsDir = WP_CONTENT_DIR . '/wpextended-snippets/';
            return $snippetsDir . $snippet->getId() . '.php';
        }
        return null;
    }

    /**
     * Ensure the snippet file resides within an allowed directory.
     *
     * @since 3.1.0
     * @param string $filePath
     * @return bool
     */
    private function isSnippetFileValid($filePath)
    {
        $allowedDirs = [
            WP_CONTENT_DIR . '/wpextended-snippets/',
        ];
        $realPath = realpath($filePath);
        foreach ($allowedDirs as $dir) {
            if (strpos($realPath, realpath($dir)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map a snippet's run_location to a WordPress hook.
     *
     * @since 3.1.0
     * @param Snippet $snippet
     * @return string|null
     */
    private function getHookForSnippet($snippet)
    {
        $location = $snippet->getRunLocation();
        $locationMap = [
            'site_header' => 'wp_head',
            'site_body_open' => 'wp_body_open',
            'site_footer' => 'wp_footer',
            'frontend' => 'wp_head',
            'backend' => 'admin_head',
            'both' => is_admin() ? 'admin_head' : 'wp_head',
            'admin_header' => 'admin_head',
            'admin_footer' => 'admin_footer',
            'everywhere' => is_admin() ? 'admin_head' : 'wp_head',
            'frontend_only' => 'wp_head',
            'admin_only' => 'admin_head'
        ];
        $hook = $locationMap[$location] ?? null;
        return $hook;
    }

    /**
     * Display the safe mode notice with a link to disable.
     *
     * @since 3.1.0
     * @return void
     */
    public function displaySafeModeNotice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $current_url = remove_query_arg(['wpextended_safe_mode', '_wpnonce']);
        $disable_url = add_query_arg([
            'wpextended_safe_mode' => '0',
            '_wpnonce' => wp_create_nonce('wpextended_safe_mode')
        ], $current_url);

        Notices::add([
            'type' => 'warning',
            'message' => sprintf(
                __('WP Extended is running in safe mode. Code snippets are disabled. <a href="%s">Disable Safe Mode</a>', WP_EXTENDED_TEXT_DOMAIN),
                esc_url($disable_url)
            ),
            'dismissible' => false
        ]);
    }

    /**
     * Display a success notice after safe mode is disabled.
     *
     * @since 3.1.0
     * @return void
     */
    public function displaySafeModeDisabledNotice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        Notices::add([
            'type' => 'success',
            'message' => __('Safe mode has been disabled. Code snippets are now active.', WP_EXTENDED_TEXT_DOMAIN),
            'dismissible' => true
        ]);
    }

    /**
     * Whether safe mode is currently enabled.
     *
     * @since 3.1.0
     * @return bool
     */
    public function isSafeModeEnabled()
    {
        return $this->safeMode;
    }

    /**
     * Return internal executor statistics (useful for debugging/UI later).
     *
     * @since 3.1.0
     * @return array
     */
    public function getStatistics()
    {
        $stats = [
            'safe_mode' => $this->safeMode,
            'processed_snippets' => count($this->processedSnippets),
            'queued_hooks' => []
        ];
        foreach ($this->snippetQueue as $hook => $snippets) {
            $stats['queued_hooks'][$hook] = count($snippets);
        }
        return $stats;
    }
}
