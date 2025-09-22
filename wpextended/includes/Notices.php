<?php

namespace Wpextended\Includes;

/**
 * Handles the creation and rendering of admin notices.
 *
 * @since 1.0.0
 * @package Wpextended
 */
class Notices
{
    /**
     * Array of notices to be displayed
     *
     * @var array
     */
    private static $notices = [];

    /**
     * Notice types and their corresponding CSS classes
     *
     * @var array
     */
    private static $types = [
        'error'   => 'notice-error',
        'warning' => 'notice-warning',
        'success' => 'notice-success',
        'info'    => 'notice-info',
    ];

    /**
     * Initialize the notices system
     */
    public static function init()
    {
        add_action('admin_notices', [__CLASS__, 'render_notices']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /**
     * Register REST API routes for notices
     */
    public static function register_rest_routes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/notices', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_notices_rest'],
                'permission_callback' => '__return_true', // Public endpoint with internal validation
                'args' => [
                    'context' => [
                        'default' => 'view',
                        'enum' => ['view', 'edit'],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($param) {
                            return in_array($param, ['view', 'edit'], true);
                        }
                    ]
                ]
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [__CLASS__, 'dismiss_notice_rest'],
                'permission_callback' => function () {
                    return current_user_can('read');
                },
                'args' => [
                    'notice_id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ($param) {
                            return !empty($param) && is_string($param);
                        }
                    ]
                ]
            ]
        ]);
    }

    /**
     * REST API endpoint for getting notices
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_notices_rest($request)
    {
        if (!self::verify_rest_nonce($request)) {
            return new \WP_Error(
                'rest_forbidden',
                esc_html__('Sorry, you are not allowed to do that.', 'wpext'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $notices = self::get_all_notices();
        $prepared_notices = array_map([__CLASS__, 'prepare_notice_for_response'], $notices);

        return rest_ensure_response($prepared_notices);
    }

    /**
     * Verify REST nonce if user is logged in
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    private static function verify_rest_nonce($request)
    {
        if (!is_user_logged_in()) {
            return true;
        }

        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Get all notices combining stored and runtime
     *
     * @return array
     */
    private static function get_all_notices()
    {
        return array_merge(self::$notices, self::get_stored_notices());
    }

    /**
     * REST API endpoint for dismissing a notice
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function dismiss_notice_rest($request)
    {
        $notice_id = $request->get_param('notice_id');
        $notices = get_option('wpextended__notices', []);

        if (!isset($notices[$notice_id])) {
            return new \WP_Error(
                'rest_notice_not_found',
                esc_html__('Notice not found.', 'wpext'),
                ['status' => 404]
            );
        }

        self::remove_stored_notice($notice_id);

        return rest_ensure_response([
            'dismissed' => true,
            'notice_id' => $notice_id
        ]);
    }

    /**
     * Check if a notice exists
     *
     * @param string $notice_id The notice ID to check
     * @return bool Whether the notice exists
     */
    public static function exists($notice_id)
    {
        if (empty($notice_id)) {
            return false;
        }

        $notice_id = sanitize_key($notice_id);

        // Check runtime notices
        foreach (self::$notices as $notice) {
            if (isset($notice['id']) && $notice['id'] === $notice_id) {
                return true;
            }
        }

        // Check stored notices
        $stored_notices = get_option('wpextended__notices', []);
        return is_array($stored_notices) && isset($stored_notices[$notice_id]);
    }

    /**
     * Delete a notice by ID
     *
     * @param string $notice_id The notice ID to remove
     * @return bool Whether the notice was removed successfully
     */
    public static function delete($notice_id)
    {
        if (empty($notice_id)) {
            return false;
        }

        if (!self::exists($notice_id)) {
            return false;
        }

        $notice_id = sanitize_key($notice_id);
        $removed = false;

        // Remove from runtime notices
        foreach (self::$notices as $key => $notice) {
            if (isset($notice['id']) && $notice['id'] === $notice_id) {
                unset(self::$notices[$key]);
                $removed = true;
                break;
            }
        }

        // Reindex array after removal
        if ($removed) {
            self::$notices = array_values(self::$notices);
        }

        // Also try to remove from stored notices
        $stored_removed = self::remove_stored_notice($notice_id);

        // Return true if notice was removed from either location
        return $removed || $stored_removed;
    }

    /**
     * Add a notice to be displayed
     *
     * @param array $args {
     *     Notice arguments.
     *     @type string|array $message     The notice message(s)
     *     @type string      $type        The notice type (error, warning, success, info)
     *     @type bool        $dismissible Whether the notice can be dismissed
     *     @type string      $id          Unique identifier for the notice
     *     @type bool        $persistent  Whether the notice should persist across page loads
     *     @type int         $expiry      Timestamp when the notice should expire
     * }
     * @return bool Whether the notice was added successfully
     */
    public static function add($args)
    {
        if (empty($args['message'])) {
            return false;
        }

        $defaults = [
            'message'     => '',
            'type'        => 'info',
            'dismissible' => true,
            'id'         => '',
            'persistent' => false,
            'expiry'     => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        // Check if notice with this ID already exists
        if (!empty($args['id']) && self::exists($args['id'])) {
            return false;
        }

        // Sanitize input
        $args['type'] = sanitize_key($args['type']);
        $args['message'] = is_array($args['message'])
            ? array_map('wp_kses_post', $args['message'])
            : wp_kses_post($args['message']);

        $notice = self::prepare_notice($args);

        if (!$notice) {
            return false;
        }

        if ($notice['persistent']) {
            return self::store_notice($notice);
        }

        self::$notices[] = $notice;
        return true;
    }

    /**
     * Prepare notice data
     *
     * @param array $args Notice arguments
     * @return array|false Notice data or false if invalid
     */
    private static function prepare_notice($args)
    {
        // Validate type
        if (!isset(self::$types[$args['type']])) {
            $args['type'] = 'info';
        }

        // Ensure messages is array and sanitized
        $messages = is_array($args['message']) ? $args['message'] : [$args['message']];
        $messages = array_filter($messages, 'strlen');

        if (empty($messages)) {
            return false;
        }

        return [
            'messages'    => $messages,
            'type'       => $args['type'],
            'dismissible' => (bool) $args['dismissible'],
            'id'         => $args['id'] ?: 'wpext_notice_' . wp_generate_password(6, false),
            'persistent' => (bool) $args['persistent'],
            'expiry'     => absint($args['expiry']),
        ];
    }

    /**
     * Get all stored notices from the database
     *
     * @return array Array of stored notices
     */
    private static function get_stored_notices()
    {
        $notices = get_option('wpextended__notices', []);
        if (!is_array($notices)) {
            return [];
        }

        $current_time = time();
        $original_count = count($notices);

        // Remove expired and validate notices
        $notices = array_filter($notices, function ($notice) use ($current_time) {
            return self::is_valid_notice($notice) &&
                (!$notice['expiry'] || $notice['expiry'] >= $current_time);
        });

        if (count($notices) !== $original_count) {
            update_option('wpextended__notices', $notices, false);
        }

        return array_values($notices);
    }

    /**
     * Validate notice structure
     *
     * @param mixed $notice
     * @return bool
     */
    private static function is_valid_notice($notice)
    {
        return is_array($notice)
            && isset($notice['messages'])
            && is_array($notice['messages'])
            && isset($notice['type'])
            && isset(self::$types[$notice['type']]);
    }

    /**
     * Store a persistent notice in the database
     *
     * @param array $notice Notice data
     * @return bool Whether the notice was stored
     */
    private static function store_notice($notice)
    {
        if (!self::is_valid_notice($notice) || empty($notice['id'])) {
            return false;
        }

        // Double check if notice already exists
        if (self::exists($notice['id'])) {
            return false;
        }

        $notices = get_option('wpextended__notices', []);
        if (!is_array($notices)) {
            $notices = [];
        }

        $notice_id = sanitize_key($notice['id']);
        $notices[$notice_id] = [
            'messages'    => array_map('wp_kses_post', $notice['messages']),
            'type'       => sanitize_key($notice['type']),
            'dismissible' => (bool) $notice['dismissible'],
            'id'         => $notice_id,
            'persistent' => (bool) $notice['persistent'],
            'expiry'     => absint($notice['expiry']),
        ];

        return update_option('wpextended__notices', $notices, false);
    }

    /**
     * Remove a stored notice from the database
     *
     * @param string $notice_id Notice identifier
     * @return bool Whether the notice was removed
     */
    private static function remove_stored_notice($notice_id)
    {
        $notice_id = sanitize_key($notice_id);
        $notices = get_option('wpextended__notices', []);

        if (!is_array($notices) || !isset($notices[$notice_id])) {
            return false;
        }

        unset($notices[$notice_id]);
        return update_option('wpextended__notices', $notices, false);
    }

    /**
     * Prepare a notice for REST API response
     *
     * @param array $notice The notice data
     * @return array Prepared notice data
     */
    private static function prepare_notice_for_response($notice)
    {
        if (!self::is_valid_notice($notice)) {
            return [];
        }

        return [
            'id' => sanitize_key($notice['id']),
            'messages' => array_map('wp_kses_post', $notice['messages']),
            'type' => sanitize_key($notice['type']),
            'dismissible' => (bool) $notice['dismissible'],
            'expiry' => absint($notice['expiry'])
        ];
    }

    /**
     * Render all queued notices
     */
    public static function render_notices()
    {
        // Get stored notices
        $stored_notices = self::get_stored_notices();

        // Combine stored and runtime notices
        $all_notices = array_merge(self::$notices, $stored_notices);

        foreach ($all_notices as $notice) {
            // Skip if notice has expired
            if ($notice['expiry'] && $notice['expiry'] < time()) {
                if ($notice['persistent']) {
                    self::remove_stored_notice($notice['id']);
                }
                continue;
            }

            $class = 'notice ' . self::$types[$notice['type']];
            if ($notice['dismissible']) {
                $class .= ' is-dismissible';
            }

            // Process each message with wpautop for proper paragraph formatting
            $messages = array_map(function ($message) {
                // First sanitize the content
                $message = wp_kses_post($message);
                // Let WordPress handle paragraph formatting intelligently
                return wpautop($message);
            }, $notice['messages']);

            printf(
                '<div class="%1$s" data-notice-id="%2$s">
                    <div class="wpext-notice-content">%3$s</div>
                </div>',
                esc_attr($class),
                esc_attr($notice['id']),
                implode('', $messages)
            );
        }

        if (!empty($all_notices)) {
            self::enqueueScripts();
        }
    }

    /**
     * Enqueue necessary scripts for notice functionality
     */
    private static function enqueueScripts()
    {
        // Only enqueue if there are dismissible notices
        $has_dismissible = false;
        foreach (self::$notices as $notice) {
            if (!empty($notice['dismissible'])) {
                $has_dismissible = true;
                break;
            }
        }

        if (!$has_dismissible) {
            return;
        }

        wp_add_inline_style('wpext-notices', '
            .wpext-notice-content {
                display: flex;
                flex-direction: column;
            }
        ');

        Utils::enqueueScript(
            'wpext-notices',
            'admin/assets/js/notices.js',
            ['wp-api'],
        );

        // Localize script with necessary data
        wp_localize_script('wpext-notices', 'wpextendedNotices', [
            'restUrl' => esc_url_raw(rest_url(sprintf('%s/notices', WP_EXTENDED_API_NAMESPACE))),
            'restNonce' => wp_create_nonce('wp_rest')
        ]);
    }

    /**
     * Handle AJAX request to dismiss a notice
     */
    public static function handle_dismiss_notice()
    {
        if (!check_ajax_referer('wpext_dismiss_notice', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $notice_id = sanitize_text_field($_POST['notice_id']);
        self::remove_stored_notice($notice_id);
        wp_send_json_success();
    }

    /**
     * Add an error notice
     *
     * @param string|array $message Notice message(s)
     * @param array $args Additional arguments
     */
    public static function error($message, $args = [])
    {
        $args['message'] = $message;
        $args['type'] = 'error';
        self::add($args);
    }

    /**
     * Add a warning notice
     *
     * @param string|array $message Notice message(s)
     * @param array $args Additional arguments
     */
    public static function warning($message, $args = [])
    {
        $args['message'] = $message;
        $args['type'] = 'warning';
        self::add($args);
    }

    /**
     * Add a success notice
     *
     * @param string|array $message Notice message(s)
     * @param array $args Additional arguments
     */
    public static function success($message, $args = [])
    {
        $args['message'] = $message;
        $args['type'] = 'success';
        self::add($args);
    }

    /**
     * Add an info notice
     *
     * @param string|array $message Notice message(s)
     * @param array $args Additional arguments
     */
    public static function info($message, $args = [])
    {
        $args['message'] = $message;
        $args['type'] = 'info';
        self::add($args);
    }
}
