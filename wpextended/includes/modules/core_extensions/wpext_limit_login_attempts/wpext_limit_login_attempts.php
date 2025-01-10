<?php

if (! defined('ABSPATH')) {
    die();
}

class Wp_Extended_Login_Attempts extends Wp_Extended
{

    const USER_LOGIN_ATTEMPT = "wpext-user-login-attempt-config";
    const WPEXT_LOGIN_ATTEMPT = "wpext_login_attempt";
    const WPEXT_LOGIN_ATTEMPT_FAILED = "wpext_login_failed";

    public function __construct()
    {
        parent::__construct();
        // Login Attempts
        add_filter('authenticate', array($this, 'wpext_check_attempted_login'), 30, 3);
        add_action('wp_login_failed', array($this, 'wpext_login_failed'), 10, 1);
        add_action('admin_menu', array($this, 'wpext_user_login_attempt'));
        add_action('admin_init', array($this, 'wpext_login_settings'));
        add_action('admin_enqueue_scripts', array($this, 'wpext_login_attempt_scripts'), 110);
        add_action('init', array($this, 'wpext_clear_db'));
        add_action('admin_init', array($this, 'wpext_clear_db'));
    }
    public static function init()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new Wp_Extended_Login_Attempts(get_called_class(), WP_EXTENDED_VERSION);
        }
        self::wpext_update_limit_option();
        self::wpext_login_settings();
        return $instance;
    } // init

    public function wpext_user_login_attempt()
    {
        $menustatus = get_option('wpext_show_plugin_menu_action');
        $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

        // Initialize flag
        $flagfavorite = false;

        //Check if the favorite admin menu settings marked
        if (!empty($wpext_admin_menu_favorite)) {
            if (array_key_exists('wpext_limit_login_attempts', $wpext_admin_menu_favorite)) {
                if ($wpext_admin_menu_favorite['wpext_limit_login_attempts'] == 'true') {
                    $flagfavorite = true;
                }
            }
        }

        if ((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite)) {
            add_submenu_page(
                'wp-extended',
                __('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
                __('Login Attempts', WP_EXTENDED_TEXT_DOMAIN),
                'manage_options',
                'wp-extended_login_attempt',
                array(get_called_class(), 'settings_admin_login_attempt'),
                null
            );
        } else {
            $capability = 'manage_options';
            $slug = 'wp-extended_login_attempt';
            $callback = [get_called_class(), 'settings_admin_login_attempt'];
            add_submenu_page('', '', '', $capability, $slug, $callback);
            add_rewrite_rule('^wp-extended_login_attempt/?', 'index.php?wp_extended_login_attempt=1', 'top');
            add_rewrite_tag('%wp_extended_login_attempt%', '([^&]+)');
        }
    }
    public static function wpext_login_attempt_scripts()
    {
        $screen = get_current_screen();
        if ($screen->base == "wp-extended_page_wp-extended_login_attempt" || $screen->base == "admin_page_wp-extended_login_attempt") {
            wp_enqueue_script('wpext_limit_login_attempts', plugin_dir_url(__FILE__) . "js/wpext_limit_login_attempts.js", array(), WP_EXTENDED_VERSION);
            wp_enqueue_script('wp-extended_jquery.dataTables.min', plugin_dir_url(__FILE__) . "js/jquery.dataTables.min.js", array(), WP_EXTENDED_VERSION);
            wp_enqueue_style('wp-extended-limit_login_attempts', plugin_dir_url(__FILE__) . "css/wpext_limit_login_attempts.css", array(), WP_EXTENDED_VERSION);
            wp_enqueue_style('wp-extended-dataTables.min', plugin_dir_url(__FILE__) . "css/jquery.dataTables.min.css", array(), WP_EXTENDED_VERSION);
            wp_enqueue_style('wp-extended-dataTables.responsive', plugin_dir_url(__FILE__) . "css/responsive.dataTables.min.css", array(), WP_EXTENDED_VERSION);
            wp_enqueue_script('wp-extended-dataTables.responsive-js', plugin_dir_url(__FILE__) . "js/dataTables.responsive.min.js", array(), WP_EXTENDED_VERSION);
        }
    }
    public static function wpext_login_settings()
    {
        register_setting(self::USER_LOGIN_ATTEMPT, self::USER_LOGIN_ATTEMPT,  array('type' => 'array'));
        require_once plugin_dir_path(__FILE__) . "wpext_login_limit_sql.php";
    }
    public function wpext_login_failed($username)
    {
        global $wpdb;
        self::wpext_check_user_login_attempt();
        add_action('login_message', array($this, 'wp_extend_login_message'), 100);
    }

    public static function settings_admin_login_attempt()
    {
        require_once plugin_dir_path(__FILE__) . "templates/wp-extend-module-layout.php";
    }

    public function wpext_check_attempted_login($user, $username, $password)
    {
        global $wpdb;
        $wp_config = get_option(self::USER_LOGIN_ATTEMPT);
        $wpext_ip = self::wpext_get_the_user_ip();

        $login_failed_option = get_option(self::USER_LOGIN_ATTEMPT, array());
        $login_failed = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT_FAILED;
        $login_attempt = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT;

        // Fix SQL injection vulnerability by using prepared statement with correct placeholder
        $log_block_locktime = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT date FROM {$login_failed} WHERE ip = %s AND status = '1' ORDER BY id DESC",
                sanitize_text_field($wpext_ip)
            )
        );

        $date_time_wp = date_i18n('Y-m-d H:i:s');
        $login_failed_option = absint(get_option('login_attempts', 3));

        // Fix SQL injection vulnerability with correct placeholder
        $login_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempt FROM {$login_attempt} WHERE ip = %s",
                sanitize_text_field($wpext_ip)
            )
        );

        if (empty($log_block_locktime)) {
            if (($login_status + 1) >= $login_failed_option) {
                self::wpext_storefailed_user();
                add_action('login_footer', array($this, 'wpext_login_footer'), 100);
                return new WP_Error('too_many_tried', sprintf(__('<strong>Sorry</strong>: You have reached the authentication limit, please try again after %1$s minutes.', WP_EXTENDED_TEXT_DOMAIN), absint(get_option('lockout_time', 30))));
            }
        } else {
            self::wpext_clear_db();
        }
        return $user;
    }

    public function wpext_storefailed_user()
    {
        global $wpdb;

        // Remove nonce check as it's not needed for login attempts
        $login_failed_option = absint(get_option('login_attempts', 3));
        $login_locktime = absint(get_option('lockout_time', 30));
        $wpext_ip = self::wpext_get_the_user_ip();

        $date = date_i18n('Y-m-d H:i:s');
        $date_formate = get_option('date_format');
        $time_formate = get_option('time_format');
        $wpdate = date($date_formate . ' ' . $time_formate, strtotime($date));
        $newdate = date_i18n('Y-m-d H:i:s', strtotime($wpdate));

        $currentDate = strtotime($newdate);
        $futureDate = $currentDate + (60 * $login_locktime);
        $formatDate = date("Y-m-d H:i:s", $futureDate);
        $login_failed = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT_FAILED;

        if (!empty($_POST['log'])) {
            // Sanitize and validate input
            $username = sanitize_user($_POST['log']);
            $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';

            // Check if IP is already blocked with correct placeholder
            $check_loged_ip = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT count(*) FROM {$login_failed} WHERE ip = %s AND status = '1'",
                    sanitize_text_field($wpext_ip)
                )
            );

            if (empty($check_loged_ip)) {
                $wpdb->insert(
                    $login_failed,
                    array(
                        'username' => $username,
                        'ip' => sanitize_text_field($wpext_ip),
                        'country' => '',
                        'redirect_to' => $redirect_to,
                        'status' => 1,
                        'locktime' => $login_locktime,
                        'locklimit' => $login_failed_option,
                        'date' => $formatDate
                    ),
                    array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
                );
            }
        }
    }

    public function wpext_get_the_user_ip()
    {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }
        return 'UNKNOWN';
    }

    public function wpext_login_footer()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                $("#user_login").attr("disabled", true);
                $("#user_pass").attr("disabled", true);
                $("#rememberme").attr("disabled", true);
                $("#wp-submit").attr("disabled", true);
            });
        </script>
        <?php }

    public function wpext_check_user_login_attempt()
    {
        global $wpdb;

        // Remove nonce check as it's not needed for login attempts
        $login_failed_option = get_option(self::USER_LOGIN_ATTEMPT);
        $wpext_ip = self::wpext_get_the_user_ip();

        if (!empty($_POST['log'])) {
            $table_name = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT;

            // Fix SQL injection vulnerability with correct placeholder
            $login_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT count(*) FROM {$table_name} WHERE ip = %s",
                    sanitize_text_field($wpext_ip)
                )
            );

            if (empty($login_status)) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'attempt' => 1,
                        'ip' => sanitize_text_field($wpext_ip),
                        'status' => 1,
                        'date' => date_i18n('Y-m-d H:i:s')
                    ),
                    array('%d', '%s', '%d', '%s')
                );
            } else {
                $attempt_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT attempt FROM {$table_name} WHERE ip = %s ORDER BY id DESC",
                        sanitize_text_field($wpext_ip)
                    )
                );
                $attempt = absint($attempt_count) + 1;

                $wpdb->update(
                    $table_name,
                    array(
                        'attempt' => $attempt,
                        'ip' => sanitize_text_field($wpext_ip)
                    ),
                    array('ip' => sanitize_text_field($wpext_ip)),
                    array('%d', '%s'),
                    array('%s')
                );
            }
        }
    }

    /*LOGIN MESSAGE*/
    public function wp_extend_login_message()
    {
        global $wpdb;
        $wp_config = get_option(self::USER_LOGIN_ATTEMPT);
        $table_name = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT;
        $wpext_ip = self::wpext_get_the_user_ip();
        $login_failed_option = absint(get_option('login_attempts', 3));

        // Fix SQL injection with prepared statement
        $login_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempt FROM {$table_name} WHERE ip = %s",
                sanitize_text_field($wpext_ip)
            )
        );

        if ($login_status <= $login_failed_option && $login_failed_option - $login_status != 0) { ?>
            <div id="login_error">
                <strong><?php esc_html_e('Login limits error:', WP_EXTENDED_TEXT_DOMAIN); ?></strong>
                <?php esc_html_e('remaining attempts:', WP_EXTENDED_TEXT_DOMAIN); ?>
                <?php echo esc_html($login_failed_option - absint($login_status)); ?>
                <?php esc_html_e('of', WP_EXTENDED_TEXT_DOMAIN); ?>
                <?php echo esc_html($login_failed_option); ?>
            </div>
<?php }
    }

    public function wpext_clear_db()
    {
        global $wpdb;
        $wpext_ip = self::wpext_get_the_user_ip();
        $table_name = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT;
        $table_failed = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT_FAILED;

        // Fix SQL injection with correct placeholder
        $log_block_locktime = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT date FROM {$table_failed} WHERE ip = %s AND status = 1",
                sanitize_text_field($wpext_ip)
            )
        );

        $date_time_wp = date_i18n('Y-m-d H:i:s');
        $date_formate = get_option('date_format');
        $time_formate = get_option('time_format');
        $wpdate = date($date_formate . ' ' . $time_formate, strtotime($date_time_wp));
        $newdate = date_i18n('Y-m-d H:i:s', strtotime($wpdate));

        // Fix SQL injection with correct placeholder
        $get_record = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT count(date) FROM {$table_name} WHERE date <= %s",
                $newdate
            )
        );

        if ($log_block_locktime >= $newdate) {
            add_action('login_footer', array($this, 'wpext_login_footer'), 100);
            return new WP_Error('too_many_tried', sprintf(__('<strong>Sorry</strong>: You have reached the authentication limit, please try again after %1$s minutes.', WP_EXTENDED_TEXT_DOMAIN), absint(get_option('lockout_time', 30))));
        } else {
            if ($wpext_ip && !empty($log_block_locktime)) {
                foreach ($get_record as $dt) {
                    $wpdb->update(
                        $table_failed,
                        array('status' => 0),
                        array('ip' => sanitize_text_field($wpext_ip)),
                        array('%d'),
                        array('%s')
                    );

                    $wpdb->delete(
                        $table_name,
                        array('date' => $newdate),
                        array('%s')
                    );
                }
            }
        }

        if (is_user_logged_in()) {
            $wpdb->delete(
                $table_name,
                array('ip' => sanitize_text_field($wpext_ip)),
                array('%s')
            );
        }
    }
    public static function wpext_update_limit_option()
    {
        $wp_config = get_option(self::USER_LOGIN_ATTEMPT);
        if (empty($wp_config['login_attempts']) && empty($wp_config['lockout_time'])) {
            update_option('login_attempts', 3);
            update_option('lockout_time', 30);
        }
    }
}
Wp_Extended_Login_Attempts::init();
