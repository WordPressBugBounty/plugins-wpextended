<?php

namespace Wpextended\Modules\BlockUsernames;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Notices;
use Wpextended\Includes\Utils;

/**
 * BlockUsernames module Bootstrap class
 *
 * Prevents specific usernames from being registered in WordPress,
 * particularly focusing on blocking variations of "admin" to enhance security.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('block-usernames');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        // Block usernames during registration
        add_filter('registration_errors', [$this, 'blockRestrictedUsernames'], 10, 3);

        // Block usernames when creating users via wp-admin
        add_action('user_profile_update_errors', [$this, 'blockRestrictedUsernamesAdmin'], 10, 3);

        // Block usernames during multisite signup
        add_filter('wpmu_validate_user_signup', [$this, 'blockRestrictedUsernamesMultisite']);

        // Check for existing accounts with blocked usernames
        add_action('admin_notices', [$this, 'checkExistingBlockedUsernames']);
    }

    /**
     * Get the list of blocked username usernames
     *
     * @return array List of blocked usernames
     */
    public static function getBlockedUsernames(): array
    {
        $default_usernames = array(
            'admin',
            'administrator',
        );

        /**
         * Filter the list of blocked usernames.
         *
         * Allows other plugins or themes to modify the default list of blocked usernames.
         *
         * @param array $default_usernames The default list of blocked usernames.
         *
         * @return array Modified list of blocked usernames.
         */
        return apply_filters('wpextended/block-usernames/blocked_usernames', $default_usernames);
    }

    /**
     * Check if a username should be ignored
     *
     * @param string $username Username to check
     * @return bool True if the username should be ignored
     */
    public static function isUsernameIgnored(string $username): bool
    {
        $ignored_usernames = array();

        /**
         * Filter the list of ignored usernames.
         *
         * Allows other plugins or themes to specify usernames that should be ignored
         * even if they match the blocked patterns.
         *
         * @param array $ignored_usernames The list of usernames to ignore.
         * @param string $username The username being checked.
         *
         * @return array Modified list of ignored usernames.
         */
        $ignored_usernames = apply_filters('wpextended/block-usernames/ignored_usernames', $ignored_usernames, $username);

        // Normalize the input username
        $normalized_username = strtolower(trim($username));

        // Normalize all ignored usernames
        $normalized_ignored = array_map('strtolower', $ignored_usernames);

        // Check if the normalized username exists in the normalized ignored list
        return in_array($normalized_username, $normalized_ignored, true);
    }

    /**
     * Build regex pattern for blocked usernames
     *
     * @param array $usernames List of usernames to block
     * @return string Regex pattern
     */
    private static function buildRegexPattern(array $usernames): string
    {
        $escaped_patterns = array_map(function ($pattern) {
            return preg_quote(trim($pattern), '/');
        }, $usernames);

        return '/\b(' . implode('|', $escaped_patterns) . ')\b/i';
    }

    /**
     * Check if a username is blocked - regex optimized version
     *
     * @param string $username Username to check
     * @return bool True if the username is blocked
     */
    public static function isUsernameBlocked(string $username): bool
    {
        // First check if username should be ignored
        if (self::isUsernameIgnored($username)) {
            return false;
        }

        static $regex_pattern = null;

        $username = strtolower(trim($username));
        $usernames = self::getBlockedUsernames();

        if ($regex_pattern === null) {
            $regex_pattern = self::buildRegexPattern($usernames);
        }

        return preg_match($regex_pattern, $username) === 1;
    }

    /**
     * Process a batch of users for blocked usernames
     *
     * @param array $users Batch of users to process
     * @param string $regex_pattern Regex pattern to match against
     * @return array Array of users with blocked usernames
     */
    private function processUserBatch(array $users, string $regex_pattern): array
    {
        $problematic_users = [];

        foreach ($users as $user) {
            $normalized_username = strtolower(trim($user->user_login));

            // Skip ignored usernames
            if ($this->isUsernameIgnored($normalized_username)) {
                continue;
            }

            if (preg_match($regex_pattern, $normalized_username) === 1) {
                $problematic_users[] = $user;
            }
        }

        return $problematic_users;
    }

    /**
     * Find users with blocked usernames - regex optimized version
     *
     * @return array Array of users with blocked usernames
     */
    protected function findUsersWithBlockedUsernames(): array
    {
        global $wpdb;
        $problematic_users = [];
        $blocked_patterns = $this->getBlockedUsernames();

        // Validate blocked patterns
        if (!is_array($blocked_patterns) || empty($blocked_patterns)) {
            return $problematic_users;
        }

        // Process in larger batches for better performance
        $batch_size = 500;
        $offset = 0;

        // Build a cached regex pattern for efficient matching
        $regex_pattern = $this->buildRegexPattern($blocked_patterns);

        do {
            // Get a batch of users
            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, user_login FROM {$wpdb->users} ORDER BY ID LIMIT %d OFFSET %d",
                (int)$batch_size,
                (int)$offset
            ));

            if ($users) {
                $batch_problematic = $this->processUserBatch($users, $regex_pattern);
                $problematic_users = array_merge($problematic_users, $batch_problematic);
                $offset += $batch_size;
            }
        } while (!empty($users));

        return $problematic_users;
    }

    /**
     * Check for existing accounts with blocked usernames and show admin notice
     *
     * @return void
     */
    public function checkExistingBlockedUsernames(): void
    {
        // Only run for administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get blocked usernames
        $blocked_usernames = $this->getBlockedUsernames();

        // Skip if no blocked usernames defined
        if (empty($blocked_usernames)) {
            return;
        }

        // Get users with blocked usernames
        $problematic_users = $this->findUsersWithBlockedUsernames();

        // If we found any users with blocked usernames, show a notice
        if (!empty($problematic_users)) {
            $this->showBlockedUsernamesNotice($problematic_users);
        }
    }

    /**
     * Show notice about users with blocked usernames
     *
     * @param array $users Array of users with blocked usernames
     * @return void
     */
    protected function showBlockedUsernamesNotice(array $users): void
    {
        // Build the message
        $count = count($users);
        $user_list = '';

        // Limit to first 5 users for display
        $displayed_users = array_slice($users, 0, 5);

        foreach ($displayed_users as $user) {
            $user_list .= sprintf(
                '<li><strong>%s</strong></li>',
                esc_html($user->user_login)
            );
        }

        // If there are more users than we displayed
        if ($count > 5) {
            $user_list .= sprintf(
                '<li>%s</li>',
                sprintf(__('and %d more...', WP_EXTENDED_TEXT_DOMAIN), $count - 5)
            );
        }

        // Create messages as an array for better structure
        $messages = [
            sprintf(
                /* translators: %d: Number of users */
                __('WP Extended: Security Warning - Found %d existing user account(s) with potentially insecure usernames that would now be blocked by the Block Usernames module:', WP_EXTENDED_TEXT_DOMAIN),
                $count
            ),
            sprintf('<ul>%s</ul>', $user_list), // Already formatted HTML, will be preserved
            sprintf(
                /* translators: %s: Documentation link, %s: Documentation link text, %s: Documentation warning text */
                __('<a href="%s" target="_blank">%s</a> %s', WP_EXTENDED_TEXT_DOMAIN),
                Utils::generateTrackedLink('https://wpextended.com/docs/modules/block-usernames#handling-existing-users', 'block-usernames'),
                __('Read our documentation', WP_EXTENDED_TEXT_DOMAIN),
                __('to learn how to fix the usernames, including how to ignore specific usernames using filters.', WP_EXTENDED_TEXT_DOMAIN)
            )
        ];

        // Add the notice
        Notices::add(array(
            'message' => $messages,
            'type' => 'warning',
            'id' => 'wpext_existing_blocked_usernames',
            'persistent' => false,
            'dismissible' => true,
        ));
    }

    /**
     * Get the blocked username message
     *
     * @return string The blocked username message
     */
    public function getBlockedUsernameMessage()
    {
        return __('This username is not allowed for security reasons. Please choose a different username.', WP_EXTENDED_TEXT_DOMAIN);
    }

    /**
     * Block restricted usernames during registration
     *
     * @param \WP_Error $errors      WP_Error object
     * @param string    $sanitized_user_login The user's username after it has been sanitized
     * @param string    $user_email The user's email
     *
     * @return \WP_Error Modified WP_Error object
     */
    public function blockRestrictedUsernames($errors, $sanitized_user_login, $user_email)
    {
        // Check if the username is blocked
        if ($this->isUsernameBlocked($sanitized_user_login)) {
            $errors->add(
                'blocked_username',
                $this->getBlockedUsernameMessage()
            );
        }

        return $errors;
    }

    /**
     * Block restricted usernames when creating users via wp-admin
     *
     * @param \WP_Error $errors      WP_Error object
     * @param bool      $update      Whether this is an existing user being updated
     * @param \WP_User  $user        User object
     *
     * @return void
     */
    public function blockRestrictedUsernamesAdmin($errors, $update, $user): void
    {
        // Don't check during updates
        if ($update) {
            return;
        }

        // Check if the username is blocked
        if (isset($user->user_login) && $this->isUsernameBlocked($user->user_login)) {
            $errors->add(
                'blocked_username',
                $this->getBlockedUsernameMessage()
            );
        }
    }

    /**
     * Block restricted usernames during multisite signup
     *
     * @param array $result The current signup data
     * @return array Modified signup data
     */
    public function blockRestrictedUsernamesMultisite($result)
    {
        $username = $result['user_name'];

        // Check if the username is blocked
        if ($this->isUsernameBlocked($username)) {
            if (!is_wp_error($result['errors'])) {
                $result['errors'] = new \WP_Error();
            }

            $result['errors']->add(
                'blocked_username',
                $this->getBlockedUsernameMessage()
            );
        }

        return $result;
    }
}
