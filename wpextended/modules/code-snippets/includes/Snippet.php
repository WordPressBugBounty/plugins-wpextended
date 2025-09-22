<?php

namespace Wpextended\Modules\CodeSnippets\Includes;

/**
 * Represents a single Code Snippet and provides persistence helpers.
 *
 * @since 3.1.0
 */
class Snippet
{
    private $id;
    private $name;
    private $type;
    private $code;
    private $enabled;
    private $filePath;
    private $description;
    private $runLocation;
    private $priority;
    private $isValid;
    private $hasIntegrityIssue;
    private $metadata;

    /**
     * @since 3.1.0
     *
     * @param string       $id
     * @param string       $name
     * @param string       $type        php|js|css|html
     * @param string       $code
     * @param bool         $enabled
     * @param string       $filePath    Absolute path to snippet file
     * @param string       $description Optional description
     * @param string       $runLocation Optional run location key
     * @param int          $priority    Optional priority for output hooks
     */
    public function __construct($id, $name, $type, $code, $enabled, $filePath, $description = '', $runLocation = '', $priority = 10)
    {
        $this->id = sanitize_key($id);
        $this->name = sanitize_text_field($name);
        $this->type = sanitize_key($type);
        $this->code = $code;
        $this->enabled = (bool) $enabled;
        $this->filePath = $filePath;
        $this->description = sanitize_textarea_field($description);
        $this->runLocation = sanitize_key($runLocation);
        $this->priority = (int) $priority;
        $this->hasIntegrityIssue = false;

        $this->metadata = $this->initializeMetadata();

        $validation = $this->validate();
        $this->isValid = $validation['valid'];
    }

    /**
     * Initialize metadata for created/updated fields.
     *
     * @since 3.1.0
     * @return array
     */
    private function initializeMetadata()
    {
        $user = wp_get_current_user();
        $now = current_time('mysql');

        return [
            'created_by' => $user->user_login,
            'created_at' => $now,
            'updated_at' => $now,
            'updated_by' => $user->user_login,
        ];
    }

    /**
     * Update metadata for last-modified values.
     *
     * @since 3.1.0
     * @return void
     */
    private function updateMetadata()
    {
        $user = wp_get_current_user();
        $this->metadata['updated_at'] = current_time('mysql');
        $this->metadata['updated_by'] = $user->user_login;
    }

    /**
     * Validate snippet content according to its type.
     *
     * @since 3.1.0
     * @return array{valid:bool,message:string}
     */
    public function validate()
    {
        if (empty($this->code)) {
            return [
                'valid' => false,
                'message' => __('Snippet code cannot be empty', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        switch ($this->type) {
            case 'php':
                return $this->validatePhpCode();
            case 'js':
                return $this->validateJsCode();
            case 'css':
                return $this->validateCssCode();
            case 'html':
                return $this->validateHtmlCode();
            default:
                return [
                    'valid' => false,
                    'message' => __('Invalid snippet type', WP_EXTENDED_TEXT_DOMAIN)
                ];
        }
    }

    /**
     * Validate PHP snippet code.
     *
     * @since 3.1.0
     * @return array{valid:bool,message:string}
     */
    private function validatePhpCode()
    {
        $validator = new PhpValidator($this->code);

        $result = $validator->validate();
        if (is_wp_error($result)) {
            return [
                'valid' => false,
                'message' => $result->get_error_message()
            ];
        }

        $result = $validator->checkRunTimeError();
        if (is_wp_error($result)) {
            return [
                'valid' => false,
                'message' => $result->get_error_message()
            ];
        }

        return [
            'valid' => true,
            'message' => __('PHP code is valid', WP_EXTENDED_TEXT_DOMAIN),
            'data' => $result
        ];
    }

    /**
     * Validate JavaScript snippet code.
     *
     * @since 3.1.0
     * @return array{valid:bool,message:string}
     */
    private function validateJsCode()
    {
        if (preg_match('/<\?php|\?>|<\?/', $this->code)) {
            return [
                'valid' => false,
                'message' => __('JavaScript code contains PHP tags', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        $brackets = substr_count($this->code, '{') - substr_count($this->code, '}');
        if ($brackets !== 0) {
            return [
                'valid' => false,
                'message' => __('JavaScript code has unmatched braces', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        return [
            'valid' => true,
            'message' => __('JavaScript code is valid', WP_EXTENDED_TEXT_DOMAIN)
        ];
    }

    /**
     * Validate CSS snippet code.
     *
     * @since 3.1.0
     * @return array{valid:bool,message:string}
     */
    private function validateCssCode()
    {
        if (preg_match('/<\?php|\?>|<\?/', $this->code)) {
            return [
                'valid' => false,
                'message' => __('CSS code contains PHP tags', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        $brackets = substr_count($this->code, '{') - substr_count($this->code, '}');
        if ($brackets !== 0) {
            return [
                'valid' => false,
                'message' => __('CSS code has unmatched braces', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        return [
            'valid' => true,
            'message' => __('CSS code is valid', WP_EXTENDED_TEXT_DOMAIN)
        ];
    }

    /**
     * Validate HTML snippet code.
     *
     * @since 3.1.0
     * @return array{valid:bool,message:string}
     */
    private function validateHtmlCode()
    {
        return [
            'valid' => true,
            'message' => __('HTML code is valid', WP_EXTENDED_TEXT_DOMAIN)
        ];
    }

    /**
     * Generate the snippet file header (docblock) with metadata and integrity hash.
     *
     * @since 3.1.0
     * @return string
     */
    private function generateHeader()
    {
        $this->updateMetadata();

        $header = "<?php\n";
        $header .= "if (!defined('ABSPATH')) { return; }\n\n";
        $header .= "/**\n";
        $header .= " * WP Extended Code Snippet\n";
        $header .= " * \n";
        $header .= " * @name: " . esc_html($this->name) . "\n";
        $header .= " * @type: " . esc_html($this->type) . "\n";
        $header .= " * @enabled: " . ($this->enabled ? 'true' : 'false') . "\n";
        $header .= " * @description: " . esc_html($this->description) . "\n";
        $header .= " * @run_location: " . esc_html($this->runLocation) . "\n";
        $header .= " * @priority: " . (int) $this->priority . "\n";
        $header .= " * @created_by: " . esc_html($this->metadata['created_by']) . "\n";
        $header .= " * @created_at: " . esc_html($this->metadata['created_at']) . "\n";
        $header .= " * @updated_by: " . esc_html($this->metadata['updated_by']) . "\n";
        $header .= " * @updated_at: " . esc_html($this->metadata['updated_at']) . "\n";
        $header .= " * @security_hash: " . $this->generateSecurityHash() . "\n";
        $header .= " */\n\n";

        return $header;
    }

    /**
     * Compute a stable integrity hash of the snippet's code.
     *
     * @since 3.1.0
     * @return string
     */
    private function generateSecurityHash()
    {
        $secret = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'wpextended_fallback_key');
        $hash = hash('sha256', $this->code . $secret . 'wpextended_snippet_integrity');
        return $hash;
    }

    /**
     * Verify the saved snippet file still matches the expected integrity hash.
     *
     * @since 3.1.0
     * @return bool
     */
    public function verifyIntegrity()
    {
        if (!file_exists($this->filePath)) {
            return false;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return false;
        }

        if (strpos($content, '/**') === false) {
            return false;
        }

        if (preg_match('/@security_hash: ([a-f0-9]{64})/', $content, $matches)) {
            $storedHash = $matches[1];
            $currentHash = $this->generateSecurityHash();
            return hash_equals($storedHash, $currentHash);
        }

        return false;
    }

    /**
     * Wrap the snippet code with the required tags for non-PHP types.
     *
     * @since 3.1.0
     * @return string
     */
    private function wrapCode()
    {
        switch ($this->type) {
            case 'js':
                return "?>\n<script>\n" . $this->code . "\n</script>";
            case 'css':
                return "?>\n<style>\n" . $this->code . "\n</style>";
            case 'html':
                return "?>\n" . $this->code;
            case 'php':
            default:
                return $this->code;
        }
    }

    /**
     * Persist the snippet to its file on disk (atomic write + integrity verification).
     *
     * @since 3.1.0
     * @return bool
     */
    public function save()
    {
        $realPath = realpath(dirname($this->filePath));
        $allowedDir = realpath(WP_CONTENT_DIR . '/wpextended-snippets');

        if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
            return false;
        }

        $dir = dirname($this->filePath);
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return false;
            }
        }

        if (!is_writable($dir)) {
            return false;
        }

        $content = $this->generateHeader();
        $content .= $this->wrapCode();

        $tempFile = $this->filePath . '.tmp';
        $bytesWritten = file_put_contents($tempFile, $content, LOCK_EX);

        if ($bytesWritten === false) {
            return false;
        }

        $writtenContent = file_get_contents($tempFile);
        if ($writtenContent !== $content) {
            unlink($tempFile);
            return false;
        }

        if (!rename($tempFile, $this->filePath)) {
            unlink($tempFile);
            return false;
        }

        chmod($this->filePath, 0644);

        if (!$this->verifyIntegrity()) {
            unlink($this->filePath);
            return false;
        }

        return true;
    }

    /**
     * Delete the snippet file.
     *
     * @since 3.1.0
     * @return bool
     */
    public function delete()
    {
        if (file_exists($this->filePath)) {
            return unlink($this->filePath);
        }
        return false;
    }

    /**
     * Export the snippet as a raw array structure.
     *
     * @since 3.1.0
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'code' => $this->code,
            'enabled' => $this->enabled,
            'description' => $this->description,
            'run_location' => $this->runLocation,
            'priority' => $this->priority,
            'is_valid' => $this->isValid,
            'file_path' => $this->filePath,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Export the snippet as an API payload structure.
     *
     * @since 3.1.0
     * @return array
     */
    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'enabled' => $this->enabled,
            'description' => $this->description,
            'run_location' => $this->runLocation,
            'priority' => $this->priority,
            'is_valid' => $this->isValid,
            'created_at' => $this->metadata['created_at'] ?? '',
            'updated_at' => $this->metadata['updated_at'] ?? '',
            'created_by' => $this->metadata['created_by'] ?? '',
            'updated_by' => $this->metadata['updated_by'] ?? ''
        ];
    }

    // Getters
    /** @since 3.1.0 */
    public function getId()
    {
        return $this->id;
    }

    /** @since 3.1.0 */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @since 3.1.0
     * @param bool $format When true returns uppercased type.
     */
    public function getType($format = false)
    {
        return $format ? strtoupper($this->type) : $this->type;
    }

    /** @since 3.1.0 */
    public function getCode()
    {
        return $this->code;
    }

    /** @since 3.1.0 */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /** @since 3.1.0 */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /** @since 3.1.0 */
    public function getDescription()
    {
        return $this->description;
    }

    /** @since 3.1.0 */
    public function getRunLocation()
    {
        return $this->runLocation;
    }

    /** @since 3.1.0 */
    public function getPriority()
    {
        return $this->priority;
    }

    /** @since 3.1.0 */
    public function isValid()
    {
        return $this->isValid;
    }

    /** @since 3.1.0 */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /** @since 3.1.0 */
    public function hasIntegrityIssue()
    {
        return $this->hasIntegrityIssue;
    }

    /** @since 3.1.0 */
    public function setIntegrityIssue($hasIssue)
    {
        $this->hasIntegrityIssue = (bool) $hasIssue;
    }

    // Setters
    /** @since 3.1.0 */
    public function setName($name)
    {
        $this->name = sanitize_text_field($name);
        $this->updateValidation();
    }

    /** @since 3.1.0 */
    public function setType($type)
    {
        $this->type = sanitize_key($type);
        $this->updateValidation();
    }

    /** @since 3.1.0 */
    public function setCode($code)
    {
        $this->code = $code;
        $this->updateValidation();
    }

    /** @since 3.1.0 */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool) $enabled;
    }

    /** @since 3.1.0 */
    public function setDescription($description)
    {
        $this->description = sanitize_textarea_field($description);
    }

    /** @since 3.1.0 */
    public function setRunLocation($runLocation)
    {
        $this->runLocation = sanitize_key($runLocation);
    }

    /** @since 3.1.0 */
    public function setPriority($priority)
    {
        $this->priority = (int) $priority;
    }

    /**
     * Refresh the validity flag based on the latest fields.
     *
     * @since 3.1.0
     * @return void
     */
    private function updateValidation()
    {
        $validation = $this->validate();
        $this->isValid = $validation['valid'];
    }

    /**
     * Check whether the snippet should be executed.
     *
     * @since 3.1.0
     * @return bool
     */
    public function canExecute()
    {
        return $this->enabled && $this->isValid && !empty($this->code) && !$this->hasIntegrityIssue;
    }

    /**
     * Get the snippet file size.
     *
     * @since 3.1.0
     * @return int|false
     */
    public function getFileSize()
    {
        if (file_exists($this->filePath)) {
            return filesize($this->filePath);
        }
        return false;
    }

    /**
     * Get the snippet file last modified timestamp.
     *
     * @since 3.1.0
     * @return int|false
     */
    public function getLastModified()
    {
        if (file_exists($this->filePath)) {
            return filemtime($this->filePath);
        }
        return false;
    }
}
