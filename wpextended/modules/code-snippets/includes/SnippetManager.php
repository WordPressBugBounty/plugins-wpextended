<?php

namespace Wpextended\Modules\CodeSnippets\Includes;

use Wpextended\Modules\CodeSnippets\Includes\PhpValidator;

/**
 * Manages Code Snippet persistence, validation, and discovery.
 *
 * Responsible for creating, updating, deleting and reading snippet files from
 * the secure `wp-content/wpextended-snippets` directory, including integrity
 * verification and safe path handling.
 *
 * @since 3.1.0
 */
class SnippetManager
{
    /**
     * @var string
     */
    private $snippetsDir;

    /**
     * @var string
     */
    private $securityHash;

    /**
     * @var array
     */
    private $hookMap = [
        'css' => [
            'wp_head' => 'wp_head',
            'admin_head' => 'admin_head',
            'frontend' => 'wp_head',
            'backend' => 'admin_head',
            'both' => 'wp_head'
        ],
        'php' => [
            'init' => 'init',
            'admin_init' => 'admin_init',
            'everywhere' => 'init',
            'admin_only' => 'admin_init',
            'frontend_only' => 'init'
        ],
        'html' => [
            'wp_head' => 'wp_head',
            'wp_body_open' => 'wp_body_open',
            'wp_footer' => 'wp_footer',
            'site_header' => 'wp_head',
            'site_body_open' => 'wp_body_open',
            'site_footer' => 'wp_footer',
            'before_content' => 'the_content',
            'after_content' => 'the_content'
        ],
        'js' => [
            'wp_head' => 'wp_head',
            'wp_footer' => 'wp_footer',
            'admin_head' => 'admin_head',
            'admin_footer' => 'admin_footer',
            'site_header' => 'wp_head',
            'site_footer' => 'wp_footer',
            'admin_header' => 'admin_head',
            'admin_footer' => 'admin_footer'
        ]
    ];

    /**
     * Construct the SnippetManager and bootstrap the snippets directory.
     *
     * @since 3.1.0
     */
    public function __construct()
    {
        $this->snippetsDir = WP_CONTENT_DIR . '/wpextended-snippets';
        $this->securityHash = $this->generateSecurityHash();
        $this->initializeDirectory();
    }

    /**
     * Generate a security hash for file integrity verification
     *
     * @return string
     */
    /**
     * Generate a site-scoped security hash used during integrity operations.
     *
     * @since 3.1.0
     * @return string SHA-256 hash string
     */
    private function generateSecurityHash()
    {
        $siteUrl = get_site_url();
        $uploadDir = wp_upload_dir();

        // Use wp_salt if available, otherwise use a fallback
        if (function_exists('wp_salt')) {
            $secret = wp_salt('auth');
        } else {
            // Fallback for when wp_salt is not available
            $secret = defined('AUTH_KEY') ? AUTH_KEY : 'wpextended_fallback_key';
        }

        return hash('sha256', $siteUrl . $uploadDir['basedir'] . $secret . 'wpextended_snippets');
    }

    /**
     * Verify file integrity
     *
     * @param string $filePath
     * @return bool
     */
    /**
     * Verify a snippet file's integrity prior to loading.
     *
     * @since 3.1.0
     * @param string $filePath Absolute file path to validate.
     * @return bool True when the file appears valid and safe to load.
     */
    private function verifyFileIntegrity($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Check for docblock header
        if (strpos($content, '/**') === false) {
            return false;
        }

        // Verify file is within allowed directory
        if (!$this->isPathSecure($filePath)) {
            return false;
        }

        // Parse headers to get snippet data
        $headers = $this->parseHeaders($content);
        if (!$headers) {
            return false;
        }

        // Extract ID from filename
        $filename = basename($filePath, '.php');
        $parts = explode('-', $filename, 2);
        $id = $parts[0];

        // Extract code
        $code = $this->extractCode($content, $headers['type'] ?? 'php');
        if (empty($code)) {
            return false;
        }

        // Create temporary snippet object to verify integrity
        $tempSnippet = new Snippet(
            $id,
            $headers['name'] ?? '',
            $headers['type'] ?? 'php',
            $code,
            $headers['enabled'] ?? false,
            $filePath,
            $headers['description'] ?? '',
            $headers['run_location'] ?? $this->getDefaultRunLocation($headers['type'] ?? 'php'),
            $headers['priority'] ?? 10
        );

        // Verify the hash
        if (!$tempSnippet->verifyIntegrity()) {
            // Allow loading but mark as having integrity issues
            $tempSnippet->setIntegrityIssue(true);
            return true;
        }

        return true;
    }

    /**
     * Check if file path is secure
     *
     * @param string $filePath
     * @return bool
     */
    /**
     * Ensure a file path resolves within the allowed snippets directory.
     *
     * @since 3.1.0
     * @param string $filePath File path to test.
     * @return bool Whether the path is secure.
     */
    private function isPathSecure($filePath)
    {
        // Get the directory path of the file
        $dirPath = dirname($filePath);
        $realDirPath = realpath($dirPath);
        $allowedDir = realpath($this->snippetsDir);

        if ($realDirPath === false || $allowedDir === false) {
            return false;
        }

        // Ensure the directory is within the snippets directory
        $isSecure = strpos($realDirPath, $allowedDir) === 0;

        return $isSecure;
    }

    /**
     * Validate and sanitize file path
     *
     * @param string|int $id
     * @param string $name
     * @return string|false
     */
    /**
     * Build a safe snippet file path from an id and name.
     *
     * @since 3.1.0
     * @param string|int $id   Snippet id.
     * @param string     $name Snippet name.
     * @return string|false Absolute file path or false on failure.
     */
    private function generateSecureFilePath($id, $name)
    {
        // Convert ID to string and validate format (alphanumeric and hyphens only)
        $idString = (string) $id;
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $idString)) {
            return false;
        }

        // Sanitize name for file system
        $sanitizedName = sanitize_file_name($name);
        if (empty($sanitizedName)) {
            $sanitizedName = 'snippet';
        }

        // Limit name length to prevent path issues
        $sanitizedName = substr($sanitizedName, 0, 50);

        $filePath = $this->snippetsDir . DIRECTORY_SEPARATOR . $idString . '-' . $sanitizedName . '.php';

        // Final security check
        if (!$this->isPathSecure($filePath)) {
            return false;
        }

        return $filePath;
    }

    /**
     * Initialize the snippets directory
     */
    /**
     * Create the snippets directory and guard files if they do not exist.
     *
     * @since 3.1.0
     * @return void
     */
    private function initializeDirectory()
    {
        if (!file_exists($this->snippetsDir)) {
            $created = wp_mkdir_p($this->snippetsDir);
            if (!$created) {
                return;
            }
        }

        if (!is_writable($this->snippetsDir)) {
            chmod($this->snippetsDir, 0755);
            if (!is_writable($this->snippetsDir)) {
                return;
            }
        }

        $this->createSecurityFile();
    }

    /**
     * Create security index.php file
     */
    /**
     * Create an index guard file to prevent direct listing.
     *
     * @since 3.1.0
     * @return void
     */
    private function createSecurityFile()
    {
        $indexFile = $this->snippetsDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<?php // Silence is golden');
        }
    }

    /**
     * Validate snippet data
     *
     * @param array $data
     * @return array{valid: bool, message: string, sanitized_data: array}
     */
    /**
     * Validate and sanitize incoming snippet payload.
     *
     * @since 3.1.0
     * @param array $data Raw payload.
     * @return array{valid:bool,message:string,sanitized_data:array} Result structure.
     */
    private function validateAndSanitizeData($data)
    {
        if (!is_array($data)) {
            return [
                'valid' => false,
                'message' => esc_html__('Invalid data format', WP_EXTENDED_TEXT_DOMAIN),
                'sanitized_data' => []
            ];
        }

        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        if (empty($name)) {
            return [
                'valid' => false,
                'message' => esc_html__('Snippet name is required', WP_EXTENDED_TEXT_DOMAIN),
                'sanitized_data' => []
            ];
        }

        $code = isset($data['code']) ? $data['code'] : '';
        if (empty($code)) {
            return [
                'valid' => false,
                'message' => esc_html__('Snippet code is required', WP_EXTENDED_TEXT_DOMAIN),
                'sanitized_data' => []
            ];
        }

        $type = isset($data['type']) ? sanitize_key($data['type']) : 'php';

        // Validate code based on type
        if ($type === 'php') {
            $validation = $this->validatePhpCode($code);
            if (!$validation['valid']) {
                return [
                    'valid' => false,
                    'message' => $validation['message'],
                    'sanitized_data' => []
                ];
            }
        }

        $sanitized = [
            'name' => $name,
            'type' => $type,
            'code' => $code,
            'enabled' => isset($data['enabled']) ? (bool) $data['enabled'] : false,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'run_location' => isset($data['run_location']) ? sanitize_key($data['run_location']) : $this->getDefaultRunLocation($type),
            'priority' => isset($data['priority']) ? (int) $data['priority'] : 10
        ];

        return [
            'valid' => true,
            'message' => '',
            'sanitized_data' => $sanitized
        ];
    }

    /**
     * Validate PHP code using PhpValidator
     *
     * @param string $code
     * @return array{valid: bool, message: string}
     */
    /**
     * Validate PHP snippet code using PhpValidator.
     *
     * @since 3.1.0
     * @param string $code PHP code to validate.
     * @return array{valid:bool,message:string}
     */
    private function validatePhpCode($code)
    {
        try {
            // First, do a basic syntax check for common errors
            $basicCheck = $this->basicPhpSyntaxCheck($code);
            if (!$basicCheck['valid']) {
                return $basicCheck;
            }

            // Check if PhpValidator class exists
            if (!class_exists('Wpextended\\Modules\\CodeSnippets\\Includes\\PhpValidator')) {
                return [
                    'valid' => false,
                    'message' => 'PhpValidator class not found'
                ];
            }

            $validator = new PhpValidator($code);

            // Check syntax errors
            $result = $validator->validate();
            if (is_wp_error($result)) {
                return [
                    'valid' => false,
                    'message' => $result->get_error_message()
                ];
            }

            // Check runtime errors
            $result = $validator->checkRunTimeError();
            if (is_wp_error($result)) {
                return [
                    'valid' => false,
                    'message' => $result->get_error_message()
                ];
            }

            return [
                'valid' => true,
                'message' => __('PHP code is valid', WP_EXTENDED_TEXT_DOMAIN)
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => sprintf(__('PHP validation error: %s', WP_EXTENDED_TEXT_DOMAIN), $e->getMessage())
            ];
        }
    }

    /**
     * Basic PHP syntax check for common errors
     *
     * @param string $code
     * @return array{valid: bool, message: string}
     */
    /**
     * Perform a quick token-based syntax sanity check for common mistakes.
     *
     * @since 3.1.0
     * @param string $code Code to check.
     * @return array{valid:bool,message:string}
     */
    private function basicPhpSyntaxCheck($code)
    {
        // Add PHP tags if not present for token_get_all
        if (strpos($code, '<?php') === false) {
            $code = '<?php ' . $code;
        }

        try {
            // Use token_get_all to check for parse errors
            $tokens = token_get_all($code);

            // Check for missing semicolons after common statements
            foreach ($tokens as $i => $token) {
                if (is_array($token) && $token[0] === T_STRING) {
                    $statement = strtolower($token[1]);
                    if (in_array($statement, ['echo', 'print', 'return', 'break', 'continue'])) {
                        // Look for semicolon after this statement
                        $foundSemicolon = false;
                        for ($j = $i + 1; $j < count($tokens); $j++) {
                            if (is_string($tokens[$j]) && $tokens[$j] === ';') {
                                $foundSemicolon = true;
                                break;
                            }
                            // Stop looking if we hit a new statement or block
                            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_IF, T_FOR, T_WHILE, T_FOREACH])) {
                                break;
                            }
                        }
                        if (!$foundSemicolon) {
                            return [
                                'valid' => false,
                                'message' => sprintf(__('Missing semicolon after %s statement', WP_EXTENDED_TEXT_DOMAIN), $statement)
                            ];
                        }
                    }
                }
            }

            return [
                'valid' => true,
                'message' => __('Basic syntax check passed', WP_EXTENDED_TEXT_DOMAIN)
            ];
        } catch (\ParseError $e) {
            return [
                'valid' => false,
                'message' => sprintf(__('PHP syntax error: %s', WP_EXTENDED_TEXT_DOMAIN), $e->getMessage())
            ];
        }
    }

    /**
     * Get default run location for a snippet type
     *
     * @param string $type
     * @return string
     */
    /**
     * Get default run location for a given snippet type.
     *
     * @since 3.1.0
     * @param string $type Snippet type.
     * @return string Default location key.
     */
    private function getDefaultRunLocation($type)
    {
        $defaults = [
            'php' => 'init',
            'js' => 'wp_footer',
            'css' => 'wp_head',
            'html' => 'wp_head'
        ];

        return $defaults[$type] ?? 'init';
    }

    /**
     * Convert user-friendly location to WordPress hook
     *
     * @param string $location
     * @param string $type
     * @return string
     */
    public function convertLocationToHook($location, $type)
    {
        return $this->hookMap[$type][$location] ?? $location;
    }

    /**
     * Get all available run locations for a snippet type
     *
     * @param string $type
     * @return array
     */
    public function getAvailableRunLocations($type)
    {
        return array_keys($this->hookMap[$type] ?? []);
    }

    /**
     * Get all snippets
     *
     * @return array
     */
    public function getAllSnippets()
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return [];
        }

        $snippets = [];
        $files = glob($this->snippetsDir . '/*.php');

        if (!$files) {
            return $snippets;
        }

        foreach ($files as $file) {
            $snippet = $this->loadSnippetFromFile($file);
            if ($snippet) {
                $snippets[] = $snippet;
            }
        }

        return $snippets;
    }

    /**
     * Get a specific snippet by ID
     *
     * @param string $id
     * @return Snippet|null
     */
    public function getSnippet($id)
    {
        $id = sanitize_key($id);
        $files = glob($this->snippetsDir . '/' . $id . '-*.php');

        if (empty($files)) {
            return null;
        }

        return $this->loadSnippetFromFile($files[0]);
    }

    /**
     * Get all snippets for admin interface (with capability check)
     *
     * @return array
     */
    public function getAdminSnippets()
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return [];
        }

        return $this->getAllSnippets();
    }

    /**
     * Get a specific snippet by ID for admin interface (with capability check)
     *
     * @param string $id
     * @return Snippet|null
     */
    public function getAdminSnippet($id)
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return null;
        }

        return $this->getSnippet($id);
    }

    /**
     * Check if snippet name exists (excluding current snippet)
     *
     * @param string $name
     * @param string|null $excludeId
     * @return bool
     */
    public function snippetNameExists($name, $excludeId = null)
    {
        $sanitizedName = sanitize_title($name);
        $files = glob($this->snippetsDir . '/*-' . $sanitizedName . '.php');

        if (empty($files)) {
            return false;
        }

        // If excluding an ID, check if any other snippets have this name
        if ($excludeId !== null) {
            foreach ($files as $file) {
                $filename = basename($file, '.php');
                $parts = explode('-', $filename, 2);
                if ($parts[0] !== $excludeId) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Get the next available snippet ID
     *
     * @return int
     */
    /**
     * Compute the next sequential snippet id by scanning existing files.
     *
     * @since 3.1.0
     * @return int Next id.
     */
    private function getNextSnippetId()
    {
        $files = glob($this->snippetsDir . '/*.php');
        if (!is_array($files)) {
            return 1;
        }

        $maxId = 0;
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (preg_match('/^(\d+)-/', $filename, $matches)) {
                $id = (int)$matches[1];
                $maxId = max($maxId, $id);
            }
        }

        return $maxId + 1;
    }

    /**
     * Generate secure file path
     *
     * @param string $id
     * @param string $name
     * @return string|false
     */
    /**
     * Wrapper for secure file path generation.
     *
     * @since 3.1.0
     * @param string $id   Snippet id.
     * @param string $name Snippet name.
     * @return string|false
     */
    private function generateFilePath($id, $name)
    {
        return $this->generateSecureFilePath($id, $name);
    }

    /**
     * Create a new snippet
     *
     * @param array $data
     * @return array{success: bool, message: string, data: ?array}
     */
    public function createSnippet($data)
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => esc_html__('You do not have sufficient permissions to create snippets.', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Validate directory
        if (!is_writable($this->snippetsDir)) {
            return [
                'success' => false,
                'message' => esc_html__('Snippets directory is not writable', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Validate and sanitize data
        $validation = $this->validateAndSanitizeData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'data' => null
            ];
        }

        $sanitizedData = $validation['sanitized_data'];

        // Check for duplicate names
        if ($this->snippetNameExists($sanitizedData['name'])) {
            return [
                'success' => false,
                'message' => esc_html__('A snippet with this name already exists', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Generate ID and secure file path
        $id = $this->getNextSnippetId();

        $filePath = $this->generateFilePath($id, $sanitizedData['name']);

        if ($filePath === false) {
            return [
                'success' => false,
                'message' => esc_html__('Failed to generate secure file path', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Create snippet
        $snippet = new Snippet(
            $id,
            $sanitizedData['name'],
            $sanitizedData['type'],
            $sanitizedData['code'],
            $sanitizedData['enabled'],
            $filePath,
            $sanitizedData['description'],
            $sanitizedData['run_location'],
            $sanitizedData['priority']
        );

        // Save snippet
        if ($snippet->save()) {
            return [
                'success' => true,
                'message' => esc_html__('Snippet created successfully', WP_EXTENDED_TEXT_DOMAIN),
                'data' => ['id' => $id, 'snippet' => $snippet]
            ];
        }

        return [
            'success' => false,
            'message' => esc_html__('Failed to create snippet file', WP_EXTENDED_TEXT_DOMAIN),
            'data' => null
        ];
    }

    /**
     * Update an existing snippet
     *
     * @param string $id
     * @param array $data
     * @return array{success: bool, message: string, data: ?array}
     */
    public function updateSnippet($id, $data)
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => esc_html__('You do not have sufficient permissions to update snippets.', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        $existingSnippet = $this->getAdminSnippet($id);
        if (!$existingSnippet) {
            return [
                'success' => false,
                'message' => esc_html__('Snippet not found', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Validate and sanitize data
        $validation = $this->validateAndSanitizeData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'data' => null
            ];
        }

        $sanitizedData = $validation['sanitized_data'];

        // Check for duplicate names (excluding current snippet)
        if ($this->snippetNameExists($sanitizedData['name'], $id)) {
            return [
                'success' => false,
                'message' => esc_html__('A snippet with this name already exists', WP_EXTENDED_TEXT_DOMAIN),
                'data' => null
            ];
        }

        // Create updated snippet with new data
        $snippet = new Snippet(
            $id,
            $sanitizedData['name'],
            $sanitizedData['type'],
            $sanitizedData['code'],
            $sanitizedData['enabled'],
            $existingSnippet->getFilePath(),
            $sanitizedData['description'],
            $sanitizedData['run_location'],
            $sanitizedData['priority']
        );

        // Save snippet
        if ($snippet->save()) {
            return [
                'success' => true,
                'message' => esc_html__('Snippet updated successfully', WP_EXTENDED_TEXT_DOMAIN),
                'data' => ['id' => $id, 'snippet' => $snippet]
            ];
        }

        return [
            'success' => false,
            'message' => esc_html__('Failed to update snippet file', WP_EXTENDED_TEXT_DOMAIN),
            'data' => null
        ];
    }

    /**
     * Set the enabled status of a snippet
     *
     * @param string $id
     * @param bool $enabled
     * @return bool
     */
    public function setSnippetEnabled($id, $enabled)
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return false;
        }

        $snippet = $this->getAdminSnippet($id);
        if (!$snippet) {
            return false;
        }

        // Create updated snippet with the specified enabled status
        $updatedSnippet = new Snippet(
            $id,
            $snippet->getName(),
            $snippet->getType(),
            $snippet->getCode(),
            $enabled,
            $snippet->getFilePath(),
            $snippet->getDescription(),
            $snippet->getRunLocation(),
            $snippet->getPriority()
        );

        // Save the updated snippet
        return $updatedSnippet->save();
    }

    /**
     * Delete a snippet
     *
     * @param string $id
     * @return array{success: bool, message: string}
     */
    public function deleteSnippet($id)
    {
        // Security check: only allow users with manage_options capability
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => esc_html__('You do not have sufficient permissions to delete snippets.', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        $snippet = $this->getAdminSnippet($id);
        if (!$snippet) {
            return [
                'success' => false,
                'message' => esc_html__('Snippet not found', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        if ($snippet->delete()) {
            return [
                'success' => true,
                'message' => esc_html__('Snippet deleted successfully', WP_EXTENDED_TEXT_DOMAIN)
            ];
        }

        return [
            'success' => false,
            'message' => esc_html__('Failed to delete snippet file', WP_EXTENDED_TEXT_DOMAIN)
        ];
    }

    /**
     * Load a snippet from file
     *
     * @param string $file
     * @return Snippet|null
     */
    /**
     * Hydrate a Snippet entity from a snippet file on disk.
     *
     * @since 3.1.0
     * @param string $file Absolute path to snippet file.
     * @return Snippet|null
     */
    private function loadSnippetFromFile($file)
    {
        // Security check: verify file path is secure
        if (!$this->isPathSecure($file)) {
            return null;
        }

        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        // Verify file integrity
        if (!$this->verifyFileIntegrity($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $headers = $this->parseHeaders($content);
        if (!$headers) {
            return null;
        }

        // Extract ID from filename and validate
        $filename = basename($file, '.php');
        $parts = explode('-', $filename, 2);
        $id = $parts[0];

        // Validate ID format
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $id)) {
            return null;
        }

        $code = $this->extractCode($content, $headers['type'] ?? 'php');
        if (empty($code)) {
            return null;
        }

        return new Snippet(
            $id,
            $headers['name'] ?? '',
            $headers['type'] ?? 'php',
            $code,
            $headers['enabled'] ?? false,
            $file,
            $headers['description'] ?? '',
            $headers['run_location'] ?? $this->getDefaultRunLocation($headers['type'] ?? 'php'),
            $headers['priority'] ?? 10
        );
    }

    /**
     * Parse snippet headers
     *
     * @param string $content
     * @return array|null
     */
    /**
     * Parse snippet docblock headers from raw file contents.
     *
     * @since 3.1.0
     * @param string $content Raw file contents.
     * @return array|null Header map or null on failure.
     */
    private function parseHeaders($content)
    {
        $headers = [];

        // Find the docblock section (between /** and */)
        $docStart = strpos($content, '/**');
        $docEnd = strpos($content, '*/');

        if ($docStart === false || $docEnd === false || $docStart >= $docEnd) {
            return null;
        }

        // Extract the docblock content
        $docblockContent = substr($content, $docStart, $docEnd - $docStart);

        // Split into lines and process each line individually
        $lines = explode("\n", $docblockContent);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comment markers
            if (empty($line) || $line === '/**' || $line === '*/') {
                continue;
            }

            // Match header pattern: * @key: value
            if (preg_match('/^\s*\*\s*@(\w+):\s*(.*)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                switch ($key) {
                    case 'name':
                    case 'description':
                    case 'created_by':
                    case 'created_at':
                    case 'updated_at':
                    case 'updated_by':
                        $headers[$key] = sanitize_text_field($value);
                        break;
                    case 'type':
                    case 'run_location':
                        $headers[$key] = sanitize_key($value);
                        break;
                    case 'enabled':
                    case 'is_valid':
                        $headers[$key] = strtolower($value) === 'true';
                        break;
                    case 'priority':
                        $headers[$key] = (int) $value;
                        break;
                }
            }
        }

        return $headers;
    }

    /**
     * Extract code from snippet content
     *
     * @param string $content
     * @param string $type
     * @return string
     */
    /**
     * Extract user code from a snippet file, removing wrapper tags as needed.
     *
     * @since 3.1.0
     * @param string $content Raw file contents.
     * @param string $type    Snippet type.
     * @return string Extracted code (may be empty string).
     */
    private function extractCode($content, $type)
    {
        // Find the end of the docblock
        $docEnd = strpos($content, '*/');
        if ($docEnd === false) {
            return '';
        }

        // Get content after docblock end
        $code = substr($content, $docEnd + strlen('*/'));
        $code = trim($code);

        // For HTML, CSS, and JS, we need to find the closing ? > and get everything after it
        if (in_array($type, ['html', 'css', 'js'])) {
            $closingTagPos = strpos($code, '?>');
            if ($closingTagPos !== false) {
                $code = substr($code, $closingTagPos + strlen('?>'));
                $code = trim($code);
            }
        }

        // Remove PHP opening tag and ABSPATH check (common for all types)
        $code = $this->removePhpHeader($code, $type);

        // Remove type-specific wrappers
        switch ($type) {
            case 'js':
                $code = $this->removeHtmlWrapper($code, 'script');
                break;
            case 'css':
                $code = $this->removeHtmlWrapper($code, 'style');
                break;
            case 'html':
                // HTML needs no additional unwrapping
                break;
            case 'php':
                // PHP needs no additional unwrapping
                break;
        }

        return trim($code);
    }

    /**
     * Remove PHP header and ABSPATH check
     *
     * @param string $code
     * @param string $type
     * @return string
     */
    /**
     * Strip the generated PHP header from snippet files.
     *
     * @since 3.1.0
     * @param string $code Source content.
     * @param string $type Snippet type.
     * @return string Cleaned content.
     */
    private function removePhpHeader($code, $type = 'php')
    {
        // For HTML, CSS, and JS, we want to keep the closing tag
        if (in_array($type, ['html', 'css', 'js'])) {
            $phpHeaderPattern = '/^\s*<\?php\s*if\s*\(!defined\(\'ABSPATH\'\)\)\s*\{\s*return;\s*\}\s*/i';
        } else {
            // For PHP snippets, remove everything including closing tag
            $phpHeaderPattern = '/^\s*<\?php\s*if\s*\(!defined\(\'ABSPATH\'\)\)\s*\{\s*return;\s*\}\s*\?>\s*/i';
        }

        return preg_replace($phpHeaderPattern, '', $code);
    }

    /**
     * Remove HTML wrapper tags
     *
     * @param string $code
     * @param string $tagName
     * @return string
     */
    /**
     * Strip HTML wrapper tags (script/style) from extracted content.
     *
     * @since 3.1.0
     * @param string $code    Source content.
     * @param string $tagName Tag name to remove.
     * @return string Cleaned content.
     */
    private function removeHtmlWrapper($code, $tagName)
    {
        // Remove opening tag (optionally preceded by if present)
        $openingPattern = '/^\s*(?:\?>\s*)?<' . preg_quote($tagName, '/') . '[^>]*>\s*/i';
        $code = preg_replace($openingPattern, '', $code);

        // Remove closing tag
        $closingPattern = '/\s*<\/' . preg_quote($tagName, '/') . '>\s*$/i';
        return preg_replace($closingPattern, '', $code);
    }
}
