<?php

namespace Wpextended\Modules\UserLastLogin;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * User meta key for storing login timestamp
     */
    private const USER_LOGIN_META_KEY = 'wpext_user_last_login_status';

    /**
     * Bootstrap constructor.
     */
    public function __construct()
    {
        parent::__construct('user-last-login');
    }

    /**
     * Initialize the module.
     * This function runs every time WordPress loads if the module is enabled.
     */
    protected function init()
    {
        add_action('wp_login', [$this, 'userLogin']);
        add_filter('manage_users_columns', [$this, 'registerColumn']);
        add_filter('manage_users_custom_column', [$this, 'renderColumn'], 10, 3);
    }

    /**
     * Get module settings fields
     *
     * @param array $settings The settings array
     * @return array The settings array
     */
    protected function getSettingsFields(): array
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('User Last Login', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id'    => 'last_login',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Select the format of the last login timestamp.', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'format',
                        'type' => 'select',
                        'title' => __('Format', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => 'date_time',
                        'choices' => array(
                            'date_time' => __('WordPress Date/Time', WP_EXTENDED_TEXT_DOMAIN),
                            'date' => __('WordPress Date', WP_EXTENDED_TEXT_DOMAIN),
                            'relative' => __('Relative Time', WP_EXTENDED_TEXT_DOMAIN),
                            'custom' => __('Custom', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'conditional_desc' => [
                            'date_time' => sprintf(__('Example: %s', WP_EXTENDED_TEXT_DOMAIN), wp_date(get_option('date_format') . ' ' . get_option('time_format'))),
                            'date' => sprintf(__('Example: %s', WP_EXTENDED_TEXT_DOMAIN), wp_date(get_option('date_format'))),
                            'relative' => sprintf(__('Example: %s ago', WP_EXTENDED_TEXT_DOMAIN), human_time_diff(time() - WEEK_IN_SECONDS, time())),
                            'custom' => sprintf(__('Example: %s', WP_EXTENDED_TEXT_DOMAIN), wp_date('Y-m-d H:i:s')),
                        ],
                    ),
                    array(
                        'id' => 'custom_format',
                        'type' => 'text',
                        'title' => __('Custom Format', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => sprintf(
                            /* translators: %s: WordPress Date/Time Formatting documentation URL */
                            __('Enter a supported <a href="%s" target="_blank" aria-label="%s">WordPress date/time format</a>.', WP_EXTENDED_TEXT_DOMAIN),
                            'https://wordpress.org/documentation/article/customize-date-and-time-format/',
                            __('WordPress Date/Time Formatting documentation, open in new tab', WP_EXTENDED_TEXT_DOMAIN)
                        ),
                        'placeholder' => 'Y-m-d H:i:s',
                        'show_if' => array(
                            array(
                                'field' => 'format',
                                'value' => 'custom',
                                'compare' => '==',
                            ),
                        ),
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Log user login time
     *
     * @param string $user_login Username of the user logging in
     * @return void
     */
    public function userLogin($user_login)
    {
        $user = get_user_by('login', $user_login);

        if (!$user) {
            return;
        }

        update_user_meta($user->ID, self::USER_LOGIN_META_KEY, time());
    }

    /**
     * Register custom columns
     *
     * @param array $columns Array of column names
     * @return array Modified columns array
     */
    public function registerColumn($columns)
    {
        $columns['wpextended_login'] = __('Last Login', WP_EXTENDED_TEXT_DOMAIN);
        return $columns;
    }

    /**
     * Render column content
     *
     * @param string $output      Custom column output
     * @param string $column_name Column name
     * @param int    $user_id     User ID
     * @return string Modified output
     */
    public function renderColumn($output, $column_name, $user_id)
    {
        if ('wpextended_login' !== $column_name) {
            return $output;
        }

        $login_time = get_user_meta($user_id, self::USER_LOGIN_META_KEY, true);

        if (empty($login_time)) {
            return __('Never', WP_EXTENDED_TEXT_DOMAIN);
        }

        return $this->formatLoginTime($login_time);
    }

    /**
     * Format login time
     *
     * @param int $login_time The login time
     * @return string The formatted login time
     */
    public function formatLoginTime($login_time)
    {
        $format = $this->getSetting('format');

        if (empty($format)) {
            return __('Never', WP_EXTENDED_TEXT_DOMAIN);
        }

        /**
         * Date/Time (WordPress date format)
         */
        if ($format === 'date_time') {
            return sprintf(
                '%s %s',
                wp_date(get_option('date_format'), (int) $login_time),
                wp_date(get_option('time_format'), (int) $login_time)
            );
        }

        /**
         * Date only (WordPress date format)
         */
        if ($format === 'date') {
            return wp_date(get_option('date_format'), (int) $login_time);
        }

        /**
         * Relative time (e.g. "1 hour ago")
         */
        if ($format === 'relative') {
            return $this->formatRelativeTime($login_time);
        }

        /**
         * Custom format
         */
        if ($format === 'custom' && !empty($this->getSetting('custom_format'))) {
            $custom_format = $this->getSetting('custom_format');
            $test_date     = wp_date($custom_format, time());

            if ($test_date === false) {
                return wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $login_time);
            }

            return wp_date($custom_format, (int) $login_time);
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $login_time);
    }

    /**
     * Format relative time
     *
     * @param int $datetime The datetime to format
     * @return string The formatted datetime
     */
    public function formatRelativeTime($datetime)
    {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $now       = current_time('timestamp');
        $is_future = $timestamp > $now;
        $diff      = abs($now - $timestamp);
        $max_units = 2;

        if ($diff < MINUTE_IN_SECONDS) {
            return __('Just now', WP_EXTENDED_TEXT_DOMAIN);
        }

        $units = [
            'year'   => YEAR_IN_SECONDS,
            'month'  => MONTH_IN_SECONDS,
            'week'   => WEEK_IN_SECONDS,
            'day'    => DAY_IN_SECONDS,
            'hour'   => HOUR_IN_SECONDS,
            'minute' => MINUTE_IN_SECONDS,
            'second' => 1,
        ];

        $result = [];

        foreach ($units as $name => $seconds) {
            if ($diff >= $seconds) {
                $value = floor($diff / $seconds);
                $diff -= $value * $seconds;

                $result[] = sprintf(
                    _n('%d %s', '%d %ss', $value, WP_EXTENDED_TEXT_DOMAIN),
                    $value,
                    __($name, WP_EXTENDED_TEXT_DOMAIN)
                );
            }

            if (count($result) >= $max_units) {
                break;
            }
        }

        return sprintf(
            $is_future ? __('in %s', WP_EXTENDED_TEXT_DOMAIN) : __('%s ago', WP_EXTENDED_TEXT_DOMAIN),
            implode(' ', $result)
        );
    }
}
