<?php

namespace Wpextended\Modules\LimitLoginAttempts;

use Wpextended\Modules\BaseModule;
use Wpextended\Modules\LimitLoginAttempts\Includes\AttemptsHandler;
use Wpextended\Modules\LimitLoginAttempts\Includes\RestApi;
use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;
use Wpextended\Includes\Notices;

class Bootstrap extends BaseModule
{
    /**
     * @var AttemptsHandler
     */
    private $attemptsHandler;

    /**
     * Bootstrap constructor.
     */
    public function __construct()
    {
        parent::__construct('limit-login-attempts');
        $this->attemptsHandler = new AttemptsHandler();
    }

    /**
     * Initialize the module.
     */
    protected function init()
    {
        $this->registerHooks();
    }

    /**
     * Get the attempts handler instance
     *
     * @return AttemptsHandler
     */
    public function getAttemptsHandler()
    {
        return $this->attemptsHandler;
    }

    /**
     * Register all WordPress hooks
     */
    private function registerHooks()
    {
        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Authentication hooks
        add_filter('authenticate', [$this->attemptsHandler, 'checkAttemptedLogin'], 30, 3);
        add_action('wp_login_failed', [$this->attemptsHandler, 'handleFailedLogin'], 10, 1);
        add_action('wp_login_failed', [$this, 'handleBlockedUsername'], 5, 1);

        // Access control hooks
        add_action('login_init', [$this, 'blockLoginPageAccess'], 1);
        add_action('wp_login', [$this, 'blockLoginPageAccess'], 1);
        add_action('admin_init', [$this, 'blockAdminAccess'], 1);
        add_action('register_form', [$this, 'blockRegistrationAccess'], 1);

        // Login page display hooks
        add_filter('login_message', [$this, 'displayLoginAttemptsCount']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Maintenance hooks
        add_action('init', [$this->attemptsHandler, 'cleanupExpiredAttempts']);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes()
    {
        $restApi = new RestApi();
        $restApi->registerRoutes();
    }

    /**
     * Get module settings fields
     *
     * @return array
     */
    protected function getSettingsFields(): array
    {
        return array(
            'tabs' => $this->getSettingsTabs(),
            'sections' => $this->getSettingsSections(),
        );
    }

    /**
     * Get settings tabs configuration
     *
     * @return array
     */
    private function getSettingsTabs(): array
    {
        return array(
            array(
                'id' => 'settings',
                'title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'login_attempts',
                'title' => __('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
            )
        );
    }

    /**
     * Get settings sections configuration
     *
     * @return array
     */
    private function getSettingsSections(): array
    {
        return [
            $this->getGeneralSettingsSection(),
            $this->getLoginAttemptsSection(),
        ];
    }

    /**
     * Get general settings section
     *
     * @return array
     */
    private function getGeneralSettingsSection(): array
    {
        return array(
            'tab_id' => 'settings',
            'section_id' => 'general',
            'section_title' => __('General Settings', WP_EXTENDED_TEXT_DOMAIN),
            'section_description' => __('Configure login attempt limits and lockout settings.', WP_EXTENDED_TEXT_DOMAIN),
            'fields' => $this->getGeneralSettingsFields(),
        );
    }

    /**
     * Get general settings fields
     *
     * @return array
     */
    private function getGeneralSettingsFields(): array
    {
        return array(
            array(
                'id' => 'max_attempts',
                'type' => 'number',
                'title' => __('Maximum Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('Number of failed login attempts before blocking.', WP_EXTENDED_TEXT_DOMAIN),
                'default' => 3,
                'min' => 1,
            ),
            array(
                'id' => 'lockout_time',
                'type' => 'number',
                'title' => __('Lockout Duration', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('How long to lockout accounts after reaching maximum attempts.', WP_EXTENDED_TEXT_DOMAIN),
                'default' => 30,
                'suffix' => __('minutes', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'notify_admin',
                'type' => 'toggle',
                'title' => __('Notify Admin', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('If enabled, an email will be sent to the admin when a user is blocked.', WP_EXTENDED_TEXT_DOMAIN),
                'default' => false,
            ),
            array(
                'id' => 'block_username_attempts',
                'type' => 'toggle',
                'title' => __('Block Usernames', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('If enabled, usernames set in Block Usernames Module will be blocked immediately.', WP_EXTENDED_TEXT_DOMAIN),
                'default' => false,
            ),
        );
    }

    /**
     * Get login attempts section
     *
     * @return array
     */
    private function getLoginAttemptsSection(): array
    {
        return array(
            'tab_id' => 'login_attempts',
            'section_id' => 'blocked_attempts',
            'section_title' => __('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
            'section_description' => __('View and manage login attempts that have been blocked.', WP_EXTENDED_TEXT_DOMAIN),
            'fields' => array(
                array(
                    'id' => 'login_attempts',
                    'type' => 'table',
                    'title' => __('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
                    'table_config' => $this->getTableConfig(),
                )
            )
        );
    }

    /**
     * Get table configuration for login attempts
     *
     * @return array
     */
    private function getTableConfig(): array
    {
        return array(
            'endpoint' => rest_url(WP_EXTENDED_API_NAMESPACE . '/limit-login-attempts/blocked'),
            'columns' => $this->getTableColumns(),
            'per_page' => 10,
            'search' => true,
            'sort' => true,
            'pagination' => true,
        );
    }

    /**
     * Get table columns configuration
     *
     * @return array
     */
    public function getTableColumns(): array
    {
        $columns = array(
            array(
                'id' => 'id',
                'name' => __('ID', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
                'hidden' => true,
            ),
            array(
                'id' => 'username',
                'name' => __('Username', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
            ),
            array(
                'id' => 'ip',
                'name' => __('IP Address', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
            ),
            array(
                'id' => 'date',
                'name' => __('Date', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
            ),
            array(
                'id' => 'status',
                'name' => __('Status', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
                'formatter' => 'blockedAccountsStatusFormatter',
            ),
            array(
                'id' => 'remaining_time',
                'name' => __('Remaining Time', WP_EXTENDED_TEXT_DOMAIN),
                'sort' => true,
                'hidden' => true,
            ),
        );

        /**
         * Filter the table columns
         *
         * @param array $columns The table columns
         * @return array The filtered table columns
         */
        $columns = apply_filters('wpextended/limit-login-attempts/table_columns', $columns);

        return $columns;
    }

    /**
     * Block access to login page if IP is blocked
     */
    public function blockLoginPageAccess()
    {
        if ($this->shouldSkipBlocking()) {
            return;
        }

        $ip = $this->attemptsHandler->getClientIp();
        if (empty($ip) || !$this->attemptsHandler->isIpBlocked($ip)) {
            return;
        }

        if (!$this->isLoginPage()) {
            return;
        }

        $this->showBlockedMessage($ip);
    }

    /**
     * Check if blocking should be skipped
     *
     * @return bool
     */
    private function shouldSkipBlocking(): bool
    {
        return wp_doing_ajax() || wp_doing_cron() || is_user_logged_in();
    }

    /**
     * Check if current page is a login page (including custom login URLs)
     *
     * @return bool
     */
    private function isLoginPage(): bool
    {
        if ($this->isStandardLoginPage()) {
            return true;
        }

        return $this->isCustomLoginPage();
    }

    /**
     * Check if current page is the standard WordPress login page
     *
     * @return bool
     */
    private function isStandardLoginPage(): bool
    {
        return isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false;
    }

    /**
     * Check if current page is a custom login page
     *
     * @return bool
     */
    private function isCustomLoginPage(): bool
    {
        $custom_login_url = $this->getCustomLoginUrl();
        if (empty($custom_login_url)) {
            return false;
        }

        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = untrailingslashit($current_path);

        // Normalize both paths by removing leading slash
        $current_path = ltrim($current_path, '/');
        $custom_login_url = ltrim($custom_login_url, '/');

        return $current_path === $custom_login_url ||
               (empty($current_path) && isset($_GET[$custom_login_url]));
    }

    /**
     * Get custom login URL if set
     *
     * @return string|null
     */
    private function getCustomLoginUrl()
    {
        static $custom_login_url = null;

        if ($custom_login_url !== null) {
            return $custom_login_url;
        }

        if (!Modules::isModuleLoaded('custom-login-url')) {
            $custom_login_url = null;
            return $custom_login_url;
        }

        $custom_login_url = Utils::getSetting('custom-login-url', 'login_url', '');
        return $custom_login_url;
    }

    /**
     * Block access to admin area if IP is blocked and user is not logged in
     */
    public function blockAdminAccess()
    {
        if ($this->shouldSkipBlocking()) {
            return;
        }

        if (!$this->isAdminPage()) {
            return;
        }

        $ip = $this->attemptsHandler->getClientIp();
        if (empty($ip) || !$this->attemptsHandler->isIpBlocked($ip)) {
            return;
        }

        $this->showBlockedMessage($ip);
    }

    /**
     * Check if current page is an admin page
     *
     * @return bool
     */
    private function isAdminPage(): bool
    {
        if (is_admin()) {
            return true;
        }

        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
            return true;
        }

        global $pagenow;
        return $pagenow === 'admin-post.php';
    }

    /**
     * Block access to registration page if IP is blocked
     */
    public function blockRegistrationAccess()
    {
        if ($this->shouldSkipBlocking()) {
            return;
        }

        if (!$this->isRegistrationPage()) {
            return;
        }

        $ip = $this->attemptsHandler->getClientIp();
        if (empty($ip) || !$this->attemptsHandler->isIpBlocked($ip)) {
            return;
        }

        $this->showBlockedMessage($ip);
    }

    /**
     * Check if current page is a registration page
     *
     * @return bool
     */
    private function isRegistrationPage(): bool
    {
        if ($this->isStandardRegistrationPage()) {
            return true;
        }

        return $this->isCustomRegistrationPage();
    }

    /**
     * Check if current page is the standard WordPress registration page
     *
     * @return bool
     */
    private function isStandardRegistrationPage(): bool
    {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-register.php') !== false) {
            return true;
        }

        return isset($_GET['action']) && $_GET['action'] === 'register';
    }

    /**
     * Check if current page is a custom registration page
     *
     * @return bool
     */
    private function isCustomRegistrationPage(): bool
    {
        $custom_login_url = $this->getCustomLoginUrl();
        if (empty($custom_login_url)) {
            return false;
        }

        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = untrailingslashit($current_path);

        // Normalize both paths by removing leading slash
        $current_path = ltrim($current_path, '/');
        $custom_login_url = ltrim($custom_login_url, '/');

        $is_custom_login = $current_path === $custom_login_url ||
                          (empty($current_path) && isset($_GET[$custom_login_url]));

        return $is_custom_login && isset($_GET['action']) && $_GET['action'] === 'register';
    }

    /**
     * Display login attempts count on login form
     */
    public function displayLoginAttemptsCount()
    {
        if (is_user_logged_in()) {
            return;
        }

        $ip = $this->attemptsHandler->getClientIp();
        if (empty($ip)) {
            return;
        }

        // Early return if IP is blocked (user should see blocked message instead)
        if ($this->attemptsHandler->isIpBlocked($ip)) {
            return;
        }

        $current_attempts = $this->attemptsHandler->getCurrentAttemptCount($ip);
        $max_attempts = $this->getSetting('max_attempts', 3);
        $remaining_attempts = $max_attempts - $current_attempts;

        // Only show if there are attempts and user hasn't reached the limit
        if ($current_attempts === 0 || $current_attempts >= $max_attempts) {
            return;
        }

        $this->renderLoginAttemptsMessage($current_attempts, $remaining_attempts);
    }

    /**
     * Render login attempts message
     *
     * @param int $current_attempts
     * @param int $remaining_attempts
     */
    private function renderLoginAttemptsMessage(int $current_attempts, int $remaining_attempts)
    {
        $attempts_text = sprintf(
            _n(
                'You have %d failed login attempt.',
                'You have %d failed login attempts.',
                $current_attempts,
                WP_EXTENDED_TEXT_DOMAIN
            ),
            $current_attempts
        );

        $remaining_text = sprintf(
            _n(
                '%d attempt remaining before your IP is blocked.',
                '%d attempts remaining before your IP is blocked.',
                $remaining_attempts,
                WP_EXTENDED_TEXT_DOMAIN
            ),
            $remaining_attempts
        );

        $warning_class = $this->getWarningClass($remaining_attempts);

        printf(
            '<div class="wpext-login-attempts-notice %s">
                <div class="wpext-attempts-header">
                    <strong>%s</strong>
                </div>
                <p>%s</p>
                <p>%s</p>
            </div>',
            esc_attr($warning_class),
            esc_html__('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
            esc_html($attempts_text),
            esc_html($remaining_text)
        );
    }

    /**
     * Get warning class based on remaining attempts
     *
     * @param int $remaining_attempts
     * @return string
     */
    private function getWarningClass(int $remaining_attempts): string
    {
        if ($remaining_attempts <= 0) {
            return 'wpext-attempts-danger';
        }

        if ($remaining_attempts === 1) {
            return 'wpext-attempts-warning';
        }

        return 'wpext-attempts-info';
    }

    /**
     * Show blocked message and die
     *
     * @param string $ip
     */
    private function showBlockedMessage($ip)
    {
        if (empty($ip)) {
            return;
        }

        include $this->getPath('layouts/block-page.php', true);
    }

    /**
     * Show blocked username message and die
     *
     * @param string $username
     */
    private function showBlockedUsernameMessage($username)
    {
        if (empty($username)) {
            return;
        }

        // Set the blocked username for the template
        $GLOBALS['wpext_blocked_username'] = $username;

        include $this->getPath('layouts/block-page.php', true);
    }

    /**
     * Handle blocked username during login
     *
     * @param string $username
     */
    public function handleBlockedUsername($username)
    {
        // If the username is blocked by policy, force-lock the IP and show the standard lockout page
        if ($this->attemptsHandler->isUsernameBlockedByBlockUsernames($username)) {
            $this->attemptsHandler->forceBlockCurrentIpForUsername($username);
            $ip = $this->attemptsHandler->getClientIp();
            $this->showBlockedMessage($ip);
        }
    }

    /**
     * Enqueue admin assets for free version
     *
     * @param string $hook Current admin page
     */
    public function enqueueAdminAssets($hook)
    {
        if (!Utils::isPluginScreen($this->module_id)) {
            return;
        }

        Utils::enqueueStyle('wpext-login-attempts-admin', $this->getPath('assets/css/style.css'));
    }
}
