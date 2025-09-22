<?php

namespace Wpextended\Modules\LimitLoginAttempts\Includes;

class RestApi
{
    /**
     * @var AttemptsHandler
     */
    private $attemptsHandler;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->attemptsHandler = new AttemptsHandler();
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes()
    {
        // Get blocked accounts
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/limit-login-attempts/blocked', [
            'methods' => 'GET',
            'callback' => [$this, 'getBlockedAccounts'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'page' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    },
                ],
                'per_page' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        if (!is_numeric($param)) {
                            return false;
                        }
                        $value = (int) $param;
                        return $value >= 1 && $value <= 100;
                    },
                ],
            ],
        ]);

        // Test failed login (for development only)
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/limit-login-attempts/test', [
            'methods' => 'POST',
            'callback' => [$this, 'testFailedLogin'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'username' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Clear all attempts (for development only)
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/limit-login-attempts/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clearAllAttempts'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * Check if user has permission to access endpoints
     *
     * @return bool
     */
    public function checkPermission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Get blocked accounts
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getBlockedAccounts($request)
    {
        // Pagination params
        $page = (int) $request->get_param('page');
        if ($page < 1) {
            $page = 1;
        }

        $per_page = (int) $request->get_param('per_page');
        if ($per_page < 1) {
            $per_page = 10;
        }
        // Cap per_page to a reasonable upper bound
        $per_page = min(100, $per_page);

        $blocked = $this->attemptsHandler->getBlockedAccounts($page, $per_page);

        // Format the response data
        $formatted_blocked = array_map(function ($item) {
            $is_currently_blocked = $item->status == 1 &&
                strtotime($item->date) + ($item->locktime * 60) > current_time('timestamp');

            $remaining_time = 0;
            if ($is_currently_blocked) {
                $remaining_time = $this->calculateRemainingTime($item->date, $item->locktime);
            }

            return [
                'id' => (int) $item->id,
                'username' => sanitize_text_field($item->username),
                'ip' => sanitize_text_field($item->ip),
                'status' => $is_currently_blocked ? 1 : 0,
                'locktime' => (int) $item->locktime,
                'locklimit' => (int) $item->locklimit,
                'date' => wp_date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($item->date)
                ),
                'remaining_time' => $remaining_time,
            ];
        }, $blocked);

        // Total count from DB
        $total = (int) $this->attemptsHandler->getBlockedAccountsTotal();
        $total_pages = (int) ceil($total / $per_page);

        $response = new \WP_REST_Response([
            'success' => true,
            'data' => $formatted_blocked,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ]);

        return $response;
    }



    /**
     * Test failed login (for development only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function testFailedLogin($request)
    {
        // Only allow in development
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Test endpoint only available in debug mode.', WP_EXTENDED_TEXT_DOMAIN),
            ], 403);
        }

        $username = $request->get_param('username') ?: 'test_user';
        $this->attemptsHandler->handleFailedLogin($username);

        return new \WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('Test failed login recorded for username: %s', WP_EXTENDED_TEXT_DOMAIN), $username),
        ]);
    }

    /**
     * Clear all attempts (for development only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function clearAllAttempts($request)
    {
        // Only allow in development
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Clear endpoint only available in debug mode.', WP_EXTENDED_TEXT_DOMAIN),
            ], 403);
        }

        $this->attemptsHandler->clearAllAttempts();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('All login attempts cleared.', WP_EXTENDED_TEXT_DOMAIN),
        ]);
    }

    /**
     * Calculate remaining lockout time
     *
     * @param string $date
     * @param int $locktime
     * @return int
     */
    private function calculateRemainingTime($date, $locktime)
    {
        $lockout_end = strtotime($date) + ($locktime * 60);
        $remaining = $lockout_end - current_time('timestamp');
        return max(0, $remaining);
    }
}
