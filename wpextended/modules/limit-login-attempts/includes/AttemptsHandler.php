<?php

namespace Wpextended\Modules\LimitLoginAttempts\Includes;

use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;

class AttemptsHandler
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @var string
     */
    private $login_failed;

    /**
     * @var string
     */
    private $login_attempt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeProperties();
        $this->initDatabaseTables();
    }

    /**
     * Initialize class properties
     */
    private function initializeProperties()
    {
        global $wpdb;
        $this->login_failed = $wpdb->prefix . 'wpext_login_failed';
        $this->login_attempt = $wpdb->prefix . 'wpext_login_attempt';

        $settings = Utils::getSettings('limit-login-attempts');
        $defaults = [
            'max_attempts' => 3,
            'lockout_time' => 30,
            'notify_admin' => false,
            'block_username_attempts' => false,
        ];

        $this->settings = array_merge($defaults, $settings);
    }

    /**
     * Initialize database tables
     */
    public function initDatabaseTables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $this->createLoginFailedTable($charset_collate);
        $this->createLoginAttemptTable($charset_collate);
        $this->runMigrations();
    }

    /**
     * Create login failed table
     *
     * @param string $charset_collate
     */
    private function createLoginFailedTable($charset_collate)
    {
        global $wpdb;

        $login_failed_sql = "CREATE TABLE $this->login_failed (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `username` varchar(100) NOT NULL,
            `ip` varchar(45) NOT NULL,
            `country` varchar(60) NULL,
            `status` tinyint(1) NOT NULL DEFAULT 0,
            `locktime` int(11) NOT NULL DEFAULT 30,
            `locklimit` int(11) NOT NULL DEFAULT 3,
            `date` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY  (id),
            KEY ip (ip),
            KEY status (status)
        )$charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($login_failed_sql);
    }

    /**
     * Create login attempt table
     *
     * @param string $charset_collate
     */
    private function createLoginAttemptTable($charset_collate)
    {
        global $wpdb;

        $login_attempt_sql = "CREATE TABLE $this->login_attempt (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `attempt` int(11) NOT NULL DEFAULT 1,
            `ip` varchar(45) NOT NULL,
            `date` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY  (id),
            KEY ip (ip)
        )$charset_collate;";

        dbDelta($login_attempt_sql);
    }

    /**
     * Run database migrations
     */
    private function runMigrations()
    {
        $this->migrateLoginFailedTable();
        $this->migrateLoginAttemptTable();
    }

    /**
     * Ensure tables exist before database operations
     */
    private function ensureTablesExist()
    {
        global $wpdb;

        $failed_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->login_failed)));
        $attempt_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->login_attempt)));

        if (!$failed_exists || !$attempt_exists) {
            $this->initDatabaseTables();
        }
    }

    /**
     * Migrate login failed table if it has the old structure
     */
    private function migrateLoginFailedTable()
    {
        global $wpdb;

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->login_failed)));
        if (!$table_exists) {
            return;
        }

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->login_failed}");
        $has_redirect_to = false;

        foreach ($columns as $column) {
            if ($column->Field === 'redirect_to') {
                $has_redirect_to = true;
                break;
            }
        }

        if ($has_redirect_to) {
            $wpdb->query("ALTER TABLE {$this->login_failed} DROP COLUMN redirect_to");
        }
    }

    /**
     * Migrate login attempt table if it has the old structure
     */
    private function migrateLoginAttemptTable()
    {
        global $wpdb;

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->login_attempt)));
        if (!$table_exists) {
            return;
        }

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->login_attempt}");
        $has_status = false;

        foreach ($columns as $column) {
            if ($column->Field === 'status') {
                $has_status = true;
                break;
            }
        }

        if ($has_status) {
            $wpdb->query("ALTER TABLE {$this->login_attempt} DROP COLUMN status");
            $this->removeStatusIndex();
        }
    }

    /**
     * Remove status index if it exists
     */
    private function removeStatusIndex()
    {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->login_attempt}");
        foreach ($indexes as $index) {
            if ($index->Key_name === 'status') {
                $wpdb->query("ALTER TABLE {$this->login_attempt} DROP INDEX status");
                break;
            }
        }
    }

    /**
     * Get blocked accounts (paginated)
     *
     * @param int $page
     * @param int $per_page
     * @return array
     */
    public function getBlockedAccounts($page = 1, $per_page = 10)
    {
        global $wpdb;

        $page = (int) $page;
        $per_page = (int) $per_page;
        if ($page < 1) {
            $page = 1;
        }
        if ($per_page < 1) {
            $per_page = 10;
        }
        // Hard cap to avoid excessive loads
        if ($per_page > 100) {
            $per_page = 100;
        }

        $offset = ($page - 1) * $per_page;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->login_failed}
            ORDER BY date DESC
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get total count of blocked accounts
     *
     * @return int
     */
    public function getBlockedAccountsTotal()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->login_failed}");
        return (int) $count;
    }

    /**
     * Unblock account
     *
     * @param int $id
     * @return bool
     */
    public function unblockAccount($id)
    {
        global $wpdb;

        $account = $this->getAccountDetails($id);
        if (!$account) {
            return false;
        }

        $result = $wpdb->update(
            $this->login_failed,
            ['status' => 0],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $this->clearIpAttempts($account->ip);
        }

        return $result !== false;
    }

    /**
     * Clear login attempts for an IP
     *
     * @param string $ip
     */
    private function clearIpAttempts($ip)
    {
        global $wpdb;

        $wpdb->delete(
            $this->login_attempt,
            ['ip' => $ip],
            ['%s']
        );
    }

    /**
     * Check attempted login
     *
     * @param \WP_User|\WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return \WP_User|\WP_Error|null
     */
    public function checkAttemptedLogin($user, $username, $password)
    {
        if ($user instanceof \WP_User) {
            $this->resetAttempts($username);
            return $user;
        }

        $ip = $this->getClientIp();

        if ($this->isIpBlocked($ip)) {
            return new \WP_Error(
                'ip_blocked',
                sprintf(
                    __('Too many failed login attempts from this IP. Please try again in %d minutes.', WP_EXTENDED_TEXT_DOMAIN),
                    $this->settings['lockout_time']
                )
            );
        }

        if ($this->isUsernameBlocked($username)) {
            return new \WP_Error(
                'username_blocked',
                sprintf(
                    __('Too many failed login attempts for this username. Please try again in %d minutes.', WP_EXTENDED_TEXT_DOMAIN),
                    $this->settings['lockout_time']
                )
            );
        }

        // Check if this attempt would put the user at or over the limit
        $current_ip_attempts = $this->getCurrentAttemptCount($ip);
        $current_username_attempts = $this->getCurrentUsernameAttempts($username);

        if ($current_ip_attempts >= $this->settings['max_attempts'] || $current_username_attempts >= $this->settings['max_attempts']) {
            return new \WP_Error(
                'attempts_exceeded',
                sprintf(
                    __('Too many failed login attempts. Please try again in %d minutes.', WP_EXTENDED_TEXT_DOMAIN),
                    $this->settings['lockout_time']
                )
            );
        }

        // Check if username is blocked (integration with block-usernames module)
        if ($this->isUsernameBlockedByBlockUsernames($username)) {
            return new \WP_Error(
                'username_blocked_by_policy',
                __('This username is not allowed for login.', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        return $user;
    }

    /**
     * Handle failed login
     *
     * @param string $username
     */
    public function handleFailedLogin($username)
    {
        $this->ensureTablesExist();

        $ip = $this->getClientIp();
        $ip_attempts = $this->incrementIpAttempts($ip);
        $username_attempts = $this->getCurrentUsernameAttempts($username);

        if ($this->shouldBlockUser($ip_attempts, $username_attempts)) {
            $this->blockIp($ip, $username);
            $this->notifyAdminIfEnabled($ip, $username);
        }
    }

    /**
     * Force block the current client IP for a given username immediately.
     *
     * Used when a username is disallowed by policy so the IP is treated like a standard lockout.
     *
     * @param string $username
     * @return bool True on success or if already blocked, false on failure
     */
    public function forceBlockCurrentIpForUsername($username)
    {
        $this->ensureTablesExist();

        $ip = $this->getClientIp();
        if (empty($ip)) {
            return false;
        }

        // If already blocked within the lock window, nothing to do
        if ($this->isIpBlocked($ip)) {
            return true;
        }

        // Create a standard lockout record and notify if enabled
        $this->blockIp($ip, $username);
        $this->notifyAdminIfEnabled($ip, $username);

        return true;
    }

    /**
     * Increment IP attempts
     *
     * @param string $ip
     * @return int
     */
    private function incrementIpAttempts($ip)
    {
        global $wpdb;

        $existing_attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->login_attempt}
            WHERE ip = %s
            ORDER BY date DESC
            LIMIT 1",
            $ip
        ));

        if ($existing_attempt) {
            return $this->updateExistingAttempt($existing_attempt);
        }

        return $this->createNewAttempt($ip);
    }

    /**
     * Update existing attempt record
     *
     * @param object $existing_attempt
     * @return int
     */
    private function updateExistingAttempt($existing_attempt)
    {
        global $wpdb;

        $new_attempt_count = $existing_attempt->attempt + 1;
        $wpdb->update(
            $this->login_attempt,
            [
                'attempt' => $new_attempt_count,
                'date' => current_time('mysql'),
            ],
            ['id' => $existing_attempt->id],
            ['%d', '%s'],
            ['%d']
        );

        return $new_attempt_count;
    }

    /**
     * Create new attempt record
     *
     * @param string $ip
     * @return int
     */
    private function createNewAttempt($ip)
    {
        global $wpdb;

        $wpdb->insert(
            $this->login_attempt,
            [
                'attempt' => 1,
                'ip' => $ip,
                'date' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        return 1;
    }

    /**
     * Get current username attempts
     *
     * @param string $username
     * @return int
     */
    private function getCurrentUsernameAttempts($username)
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->login_failed}
            WHERE username = %s AND status = %d
            AND date > DATE_SUB(NOW(), INTERVAL locktime MINUTE)",
            $username,
            1
        ));
    }

    /**
     * Check if user should be blocked
     *
     * @param int $ip_attempts
     * @param int $username_attempts
     * @return bool
     */
    private function shouldBlockUser($ip_attempts, $username_attempts)
    {
        return $ip_attempts >= $this->settings['max_attempts'] ||
               $username_attempts >= $this->settings['max_attempts'];
    }

    /**
     * Block IP address
     *
     * @param string $ip
     * @param string $username
     */
    private function blockIp($ip, $username)
    {
        global $wpdb;

        $country = $this->getCountryCode($ip);

        $wpdb->insert(
            $this->login_failed,
            [
                'ip' => $ip,
                'username' => $username,
                'country' => $country,
                'status' => 1,
                'locktime' => $this->settings['lockout_time'],
                'locklimit' => $this->settings['max_attempts'],
                'date' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );
    }

    /**
     * Notify admin if enabled
     *
     * @param string $ip
     * @param string $username
     */
    private function notifyAdminIfEnabled($ip, $username)
    {
        if ($this->settings['notify_admin']) {
            $this->notifyAdmin($ip, $username);
        }
    }

    /**
     * Check if IP is blocked
     *
     * @param string $ip
     * @return bool
     */
    public function isIpBlocked($ip)
    {
        global $wpdb;

        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->login_failed}
            WHERE ip = %s AND status = %d
            AND date > DATE_SUB(NOW(), INTERVAL locktime MINUTE)",
            $ip,
            1
        ));

        return (bool) $blocked;
    }

    /**
     * Get remaining block time for an IP
     *
     * @param string $ip
     * @return int
     */
    public function getRemainingBlockTime($ip)
    {
        global $wpdb;

        $blocked_record = $wpdb->get_row($wpdb->prepare(
            "SELECT date, locktime FROM {$this->login_failed}
            WHERE ip = %s AND status = %d
            AND date > DATE_SUB(NOW(), INTERVAL locktime MINUTE)
            ORDER BY date DESC
            LIMIT 1",
            $ip,
            1
        ));

        if (!$blocked_record) {
            return 0;
        }

        $lockout_end = strtotime($blocked_record->date) + ($blocked_record->locktime * 60);
        $remaining = $lockout_end - current_time('timestamp');

        return max(0, $remaining);
    }

    /**
     * Check if username is blocked
     *
     * @param string $username
     * @return bool
     */
    private function isUsernameBlocked($username)
    {
        global $wpdb;

        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->login_failed}
            WHERE username = %s AND status = %d
            AND date > DATE_SUB(NOW(), INTERVAL locktime MINUTE)",
            $username,
            1
        ));

        return (bool) $blocked;
    }

    /**
     * Reset attempts for username and IP
     *
     * @param string $username
     */
    private function resetAttempts($username)
    {
        global $wpdb;
        $ip = $this->getClientIp();

        $this->clearIpAttempts($ip);

        $wpdb->update(
            $this->login_failed,
            ['status' => 0],
            ['username' => $username],
            ['%d'],
            ['%s']
        );
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function getClientIp()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Get country code from IP
     *
     * @param string $ip
     * @return string|null
     */
    private function getCountryCode($ip)
    {
        return null;
    }

    /**
     * Notify admin about blocked IP
     *
     * @param string $ip
     * @param string $username
     */
    private function notifyAdmin($ip, $username)
    {
        $admin_email = apply_filters('wpextended/limit-login-attempts/admin_email', get_option('admin_email'));
        $site_name = apply_filters('wpextended/limit-login-attempts/site_name', get_bloginfo('name'));

        $subject = sprintf(
            __('[%s] IP Address Blocked', WP_EXTENDED_TEXT_DOMAIN),
            $site_name
        );

        $message_lines = [
            __('An IP address has been blocked due to too many failed login attempts:', WP_EXTENDED_TEXT_DOMAIN),
            '- ' . sprintf(__('IP Address: %s', WP_EXTENDED_TEXT_DOMAIN), $ip),
            '- ' . sprintf(__('Username: %s', WP_EXTENDED_TEXT_DOMAIN), $username),
            '- ' . sprintf(__('Time: %s', WP_EXTENDED_TEXT_DOMAIN), wp_date(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp'))),
            '- ' . sprintf(__('Block Duration: %d minutes', WP_EXTENDED_TEXT_DOMAIN), $this->settings['lockout_time']),
            '',
            __('You can unblock this IP address from the WordPress admin panel.', WP_EXTENDED_TEXT_DOMAIN),
        ];

        $message = implode("\r\n", $message_lines);

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Cleanup expired attempts
     */
    public function cleanupExpiredAttempts()
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->login_attempt}
            WHERE date < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $this->settings['lockout_time']
        ));

        // Don't cleanup failed attempts - keep history of all blocked/unblocked attempts
        // The isIpBlocked method already checks for active blocks within the lockout window
    }

    /**
     * Get current attempt count for an IP
     *
     * @param string $ip
     * @return int
     */
    public function getCurrentAttemptCount($ip)
    {
        $this->ensureTablesExist();

        global $wpdb;

        // Only count attempts within the lockout window
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT attempt FROM {$this->login_attempt}
            WHERE ip = %s
            AND date > DATE_SUB(NOW(), INTERVAL %d MINUTE)
            ORDER BY date DESC
            LIMIT 1",
            $ip,
            $this->settings['lockout_time']
        ));

        return $attempt ? (int) $attempt->attempt : 0;
    }

    /**
     * Get module settings
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Clear all attempts (for testing/development)
     */
    public function clearAllAttempts()
    {
        global $wpdb;

        $wpdb->query("DELETE FROM {$this->login_attempt}");
        $wpdb->query("DELETE FROM {$this->login_failed}");
    }

    /**
     * Get account details
     *
     * @param int $id
     * @return object|null
     */
    public function getAccountDetails($id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->login_failed} WHERE id = %d",
            $id
        ));
    }

    /**
     * Check if username is blocked by block-usernames module
     *
     * @param string $username
     * @return bool
     */
    public function isUsernameBlockedByBlockUsernames($username)
    {
        // Check if block-usernames module is available and enabled
        if (!Modules::isModuleEnabled('block-usernames')) {
            return false;
        }

        // Check if the feature is enabled in settings
        if (!$this->settings['block_username_attempts']) {
            return false;
        }

        // Use the static method from block-usernames module
        return \Wpextended\Modules\BlockUsernames\Bootstrap::isUsernameBlocked($username);
    }
}
