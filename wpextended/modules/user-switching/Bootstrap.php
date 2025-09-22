<?php

namespace Wpextended\Modules\UserSwitching;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Notices;

class Bootstrap extends BaseModule
{
    /**
     * Bootstrap constructor.
     * Calls the parent constructor with module ID 'hide-admin-bar'.
     */
    public function __construct()
    {
        parent::__construct('user-switching');
    }

    /**
     * Initialize the module
     * This runs every time WordPress loads if the module is enabled
     *
     * @return void
     */
    protected function init()
    {
        $this->registerCapabilityFilters();
        $this->registerUserActions();
        $this->registerCookieHandlers();
        $this->registerNoticesAndUI();
    }

    /**
     * Register capability-related filters
     *
     * @return void
     */
    private function registerCapabilityFilters()
    {
        add_filter('user_has_cap', array($this, 'filterRoleCap'), 10, 4);
        add_filter('map_meta_cap', array($this, 'filterRoleMetaCap'), 10, 4);
    }

    /**
     * Register user-related actions and filters
     *
     * @return void
     */
    private function registerUserActions()
    {
        add_filter('user_row_actions', array($this, 'filterUserRoleRowActions'), 10, 2);
        add_action('init', array($this, 'userRoleInit'), 20);
        add_action('admin_bar_menu', array($this, 'userActionAdminBarMenu'), 11);
    }

    /**
     * Register cookie-related handlers
     *
     * @return void
     */
    private function registerCookieHandlers()
    {
        add_action('plugins_loaded', array($this, 'actionInitialLoaded'), 1);
        add_action('wp_logout', array($this, 'clearOldUserCookie'));
        add_action('wp_login', array($this, 'clearOldUserCookie'));
    }

    /**
     * Register notices and UI elements
     *
     * @return void
     */
    private function registerNoticesAndUI()
    {
        add_action('admin_notices', array($this, 'adminNotices'), 1);
        add_action('wp_footer', array($this, 'switchFooterAction'));
        add_action('personal_options', array($this, 'showSwitchToUserOptionInProfile'), 10, 1);
        add_filter('login_message', array($this, 'filterLoginMessage'));
    }

    /**
     * Adds a 'Switch back to {previous user}' link to the account menu, and a `Switch To` link to the user edit menu.
     *
     * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
     */
    public function userActionAdminBarMenu($wp_admin_bar)
    {
        if (!is_admin_bar_showing()) {
            return;
        }

        $parent = $wp_admin_bar->get_node('user-actions') ? 'user-actions' : null;
        if (!$parent) {
            return;
        }

        $old_user = self::getOldUser();
        $current_user = wp_get_current_user();

        // If switched, show the switch back option
        if ($old_user) {
            $wp_admin_bar->add_node(
                array(
                    'parent' => $parent,
                    'id' => 'switch-back',
                    'title' => esc_html(self::userSwitchBackMessage($old_user)),
                    'href' => add_query_arg(
                        array(
                            'redirect_to' => rawurlencode(self::checkCurrentUrl()),
                        ),
                        self::moveBackUrl($old_user)
                    ),
                )
            );
        }
    }

    /**
     * Fetches the URL to redirect to for a given user (used after switching)
     *
     * @param WP_User|null $newUser New user being switched to
     * @param WP_User|null $oldUser Original user switching from
     * @return string Redirect URL
     */
    public static function usersGetRedirect($newUser = null, $oldUser = null)
    {
        $redirectTo = '';
        $requestedRedirectTo = '';
        $redirectType = null;

        if (!empty($_REQUEST['redirect_to'])) {
            // URL
            $redirectTo = self::queryArgRemove(wp_unslash($_REQUEST['redirect_to']));
            $requestedRedirectTo = wp_unslash($_REQUEST['redirect_to']);
            $redirectType = 'url';
        } elseif (!empty($_GET['redirect_to_post'])) {
            $redirectInfo = self::getPostRedirect();
            if ($redirectInfo) {
                $redirectTo = $redirectInfo['url'];
                $requestedRedirectTo = $redirectInfo['url'];
                $redirectType = 'post';
            }
        } elseif (!empty($_GET['redirect_to_term'])) {
            $redirectInfo = self::getTermRedirect();
            if ($redirectInfo) {
                $redirectTo = $redirectInfo['url'];
                $requestedRedirectTo = $redirectInfo['url'];
                $redirectType = 'term';
            }
        } elseif (!empty($_GET['redirect_to_user'])) {
            $redirectInfo = self::getUserRedirect();
            if ($redirectInfo) {
                $redirectTo = $redirectInfo['url'];
                $requestedRedirectTo = $redirectInfo['url'];
                $redirectType = 'user';
            }
        } elseif (!empty($_GET['redirect_to_comment'])) {
            $redirectInfo = self::getCommentRedirect();
            if ($redirectInfo) {
                $redirectTo = $redirectInfo['url'];
                $requestedRedirectTo = $redirectInfo['url'];
                $redirectType = $redirectInfo['type'];
            }
        }

        if (!$newUser) {
            /**
             * This filter is documented in wp-login.php
             */
            $redirectTo = apply_filters('logout_redirect', $redirectTo, $requestedRedirectTo, $oldUser);
        } else {
            /**
             * This filter is documented in wp-login.php
             */
            $redirectTo = apply_filters('login_redirect', $redirectTo, $requestedRedirectTo, $newUser);
        }

        /**
         * Filters the redirect location after a user switches to another account or switches off
         */
        $redirectTo = apply_filters('wpextended/user-switching/redirect_to', $redirectTo, $redirectType, $newUser, $oldUser);

        /**
         * Filters the final redirect URL for user switching operations.
         *
         * @param string $redirectTo The URL to redirect to
         * @param WP_User|null $newUser The user being switched to (null when switching off)
         * @param WP_User|null $oldUser The user being switched from
         * @return string The filtered redirect URL
         */
        return apply_filters('wpextended/user-switching/get_redirect', $redirectTo, $newUser, $oldUser);
    }

    /**
     * Gets post redirect information if requested
     *
     * @return array|null Redirect information or null if invalid
     */
    private static function getPostRedirect()
    {
        $postId = absint($_GET['redirect_to_post']);

        if (function_exists('is_post_publicly_viewable') && is_post_publicly_viewable($postId)) {
            $link = get_permalink($postId);

            if (is_string($link)) {
                return array(
                    'url' => $link,
                    'type' => 'post'
                );
            }
        }

        return null;
    }

    /**
     * Gets term redirect information if requested
     *
     * @return array|null Redirect information or null if invalid
     */
    private static function getTermRedirect()
    {
        $term = get_term(absint($_GET['redirect_to_term']));

        if (!($term instanceof \WP_Term) || !function_exists('is_taxonomy_viewable') || !is_taxonomy_viewable($term->taxonomy)) {
            return null;
        }

        $link = get_term_link($term);

        return is_string($link) ? array('url' => $link, 'type' => 'term') : null;
    }

    /**
     * Gets user redirect information if requested
     *
     * @return array|null Redirect information or null if invalid
     */
    private static function getUserRedirect()
    {
        $user = get_userdata(absint($_GET['redirect_to_user']));

        if (!($user instanceof \WP_User)) {
            return null;
        }

        $link = get_author_posts_url($user->ID);

        if (!is_string($link)) {
            return null;
        }

        return array(
            'url' => $link,
            'type' => 'user'
        );
    }

    /**
     * Gets comment redirect information if requested
     *
     * @return array|null Redirect information or null if invalid
     */
    private static function getCommentRedirect()
    {
        $comment = get_comment(absint($_GET['redirect_to_comment']));

        if (!($comment instanceof \WP_Comment)) {
            return null;
        }

        if ('approved' === wp_get_comment_status($comment)) {
            $link = get_comment_link($comment);
            if (is_string($link)) {
                return [
                    'url' => $link,
                    'type' => 'comment',
                ];
            }
        }

        if (function_exists('is_post_publicly_viewable') && is_post_publicly_viewable((int)$comment->comment_post_ID)) {
            $link = get_permalink((int)$comment->comment_post_ID);
            if (is_string($link)) {
                return [
                    'url' => $link,
                    'type' => 'post',
                ];
            }
        }

        return null;
    }

    /**
     * Authenticates an old user by verifying the latest entry in the auth cookie
     *
     * @param  \WP_User $user A WP_User object (usually from the logged_in cookie)
     * @return bool Whether verification with the auth cookie passed
     */
    public static function authenticateOldUser($user)
    {
        $cookie = wpextended_user_switching_get_auth_cookie();
        if (empty($cookie)) {
            return false;
        }

        $scheme = self::secureAuthCookie() ? 'secure_auth' : 'auth';
        $oldUserId = wp_validate_auth_cookie(end($cookie), $scheme);

        if (!$oldUserId) {
            return false;
        }

        return ($user->ID === $oldUserId);
    }

    /**
     * Adds a 'Switch back to {user}' link to the WordPress login screen.
     *
     * @param string $message The login screen message
     * @return string Modified login screen message
     */
    public function filterLoginMessage($message)
    {
        $old_user = self::getOldUser();

        if (!($old_user instanceof \WP_User)) {
            return $message;
        }

        $url = self::moveBackUrl($old_user);

        if (!empty($_REQUEST['interim-login'])) {
            $url = add_query_arg(
                array(
                    'interim-login' => '1',
                ),
                $url
            );
        } elseif (!empty($_REQUEST['redirect_to'])) {
            $url = add_query_arg(
                array(
                    'redirect_to' => rawurlencode(wp_unslash($_REQUEST['redirect_to'])),
                ),
                $url
            );
        }

        $icon = '<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span> ';

        $url = sprintf(
            '<a href="%1$s" onclick="window.location.href=\'%1$s\';return false;">%2$s</a>',
            esc_url($url),
            esc_html(self::userSwitchBackMessage($old_user))
        );

        $message = sprintf(
            '<div class="message" id="wpextended-user-switching-switch-on">%s%s</div>',
            $icon,
            $url
        );

        /**
         * Filters the login screen message when a user is switching or has switched.
         *
         * @param string $message The login message
         * @return string The filtered login message
         */
        return apply_filters('wpextended/user-switching/login_message', $message);
    }

    /**
     * Filters a user's capabilities so they can be altered at runtime.
     *
     * @param array  $user_caps    User's capabilities
     * @param array  $required_caps Primitive capabilities required
     * @param array  $args         Arguments that accompany the requested capability check
     * @param WP_User $user        The user object
     * @return array Filtered user capabilities
     */
    public function filterRoleCap(array $user_caps, array $required_caps, array $args, $user)
    {
        if ('switch_user_role' === $args[0]) {
            if (empty($args[2])) {
                $user_caps['switch_user_role'] = false;
                return $user_caps;
            }

            if (array_key_exists('switch_users', $user_caps)) {
                $user_caps['switch_user_role'] = $user_caps['switch_users'];
                return $user_caps;
            }

            $user_caps['switch_user_role'] = (user_can($user->ID, 'edit_user', $args[2]) && ($args[2] !== $user->ID));
        } elseif ('switch_off' === $args[0]) {
            if (array_key_exists('switch_users', $user_caps)) {
                $user_caps['switch_off'] = $user_caps['switch_users'];
                return $user_caps;
            }

            $user_caps['switch_off'] = user_can($user->ID, 'edit_users');
        }

        return $user_caps;
    }

    /**
     * @return bool Whether the current user is being 'switch_remember'.
     */
    public static function switchRemember()
    {
        /**
         * This filter is documented in wp-includes/pluggable.php
         */
        $cookie_life = apply_filters('auth_cookie_expiration', 172800, get_current_user_id(), false);
        $current = wp_parse_auth_cookie('', 'logged_in');

        if (!$current) {
            return false;
        }

        // Calculate the expiration length of the current auth cookie and compare it to the default expiration.
        // If it's greater than this, then we know the user checked 'Remember Me' when they logged in.
        return (intval($current['expiration']) - time() > $cookie_life);
    }

    /**
     * Filters the required primitive capabilities for the given primitive or meta capability.
     *
     * @param array  $required_caps Required primitive capabilities for the requested capability
     * @param string $cap           Capability being checked
     * @param int    $user_id       ID of the user being checked against
     * @param array  $args          Additional arguments passed to the capability check
     * @return array Filtered required capabilities
     */
    public function filterRoleMetaCap(array $required_caps, $cap, $user_id, array $args)
    {
        if ('switch_user_role' === $cap) {
            if (empty($args[0]) || $args[0] === $user_id) {
                $required_caps[] = 'do_not_allow';
            }
        }
        return $required_caps;
    }

    /**
     * Adds a 'Switch To' link to each list of user actions on the Users screen.
     *
     * @param array   $actions Action links
     * @param WP_User $user    User object
     * @return array Modified action links
     */
    public function filterUserRoleRowActions(array $actions, $user)
    {
        $link = self::usersNeedSwitchUrl($user);

        if (!$link) {
            return $actions;
        }

        $actions['switch_user_role'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($link),
            esc_html__('Switch&nbsp;To', WP_EXTENDED_TEXT_DOMAIN)
        );

        return $actions;
    }

    /**
     * Returns the switch to or switch back URL for a given user.
     *
     * @param WP_User $user User object
     * @return string|false URL to switch to the user, or false if not available
     */
    public static function usersNeedSwitchUrl($user)
    {
        $old_user = self::getOldUser();

        if ($old_user && ($old_user->ID === $user->ID)) {
            return self::moveBackUrl($old_user);
        } elseif (current_user_can('switch_user_role', $user->ID)) {
            return self::switchToUrl($user);
        }

        return false;
    }

    /**
     * Returns the nonce-secured URL needed to switch to a given user ID.
     *
     * @param WP_User $user User to switch to
     * @return string URL to switch to the user
     */
    public static function switchToUrl($user)
    {
        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'switch_user_role',
                    'user_id' => $user->ID,
                    'nr' => 1,
                ),
                wp_login_url()
            ),
            "switch_user_role_{$user->ID}"
        );

        /**
         * Filters the URL for switching to a user.
         *
         * @param string $url The URL for switching to the user
         * @param WP_User $user The user being switched to
         * @return string The filtered URL
         */
        return apply_filters('wpextended/user-switching/switch_to_url', $url, $user);
    }

    /**
     * Returns the nonce-secured URL needed to switch back to the originating user.
     *
     * @param WP_User $user User to switch back to
     * @return string URL to switch back to the user
     */
    public static function moveBackUrl($user)
    {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'switch_to_olduser',
                    'nr' => 1,
                ),
                wp_login_url()
            ),
            "switch_to_olduser_{$user->ID}"
        );
    }

    /**
     * Returns the nonce-secured URL needed to temporarily log out the current user.
     *
     * @param WP_User $user User to temporarily log out
     * @return string URL to temporarily log out the user
     */
    public static function switchOffUrl($user)
    {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'switch_off',
                    'nr' => 1,
                ),
                wp_login_url()
            ),
            "switch_off_{$user->ID}"
        );
    }

    /**
     * Returns the message shown to the user when they've switched to a user.
     *
     * @param WP_User $user User that was switched to
     * @return string Message to display
     */
    public static function switchedToMessage($user)
    {
        $message = sprintf(
            /* Translators: 1: user display name; 2: username; */
            __('Switched to %1$s (%2$s).', WP_EXTENDED_TEXT_DOMAIN),
            $user->display_name,
            $user->user_login
        );

        // Removes the user login from this message without invalidating existing translations
        return str_replace(
            sprintf(
                ' (%s)',
                $user->user_login
            ),
            '',
            $message
        );
    }

    /**
     * Returns the message shown to the user for the link to switch back to their original user.
     *
     * @param WP_User $user User to switch back to
     * @return string Message to display
     */
    public static function userSwitchBackMessage($user)
    {
        $message = sprintf(
            /* Translators: 1: user display name */
            __('Switch back to %1$s', WP_EXTENDED_TEXT_DOMAIN),
            $user->display_name,
        );

        return $message;
    }

    /**
     * Returns the message shown to the user when they've switched back to their original user.
     *
     * @param WP_User $user User that was switched back to
     * @return string Message to display
     */
    public static function userSwitchedBackMessage($user)
    {
        $message = sprintf(
            /* Translators: 1: user display name */
            __('Switched back to %1$s.', WP_EXTENDED_TEXT_DOMAIN),
            $user->display_name,
        );

        return $message;
    }

    /**
     * Returns the current URL.
     *
     * @return string The current URL.
     */
    public static function checkCurrentUrl()
    {
        return (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Removes a list of common confirmation-style query args from a URL.
     *
     * @param  string $url A URL.
     * @return string The URL with query args removed.
     */
    public static function queryArgRemove($url)
    {
        if (function_exists('wp_removable_query_args')) {
            $url = remove_query_arg(wp_removable_query_args(), $url);
        }
        return $url;
    }

    /**
     * Returns whether User Switching's equivalent of the 'auth' cookie should be secure.
     *
     * @return bool Whether the auth cookie should be secure
     */
    public static function secureAuthCookie()
    {
        return (is_ssl() && ('https' === parse_url(wp_login_url(), PHP_URL_SCHEME)));
    }

    /**
     * Returns whether User Switching's equivalent of the 'logged_in' cookie should be secure.
     *
     * This is used to set the 'secure' flag on the old user cookie, for enhanced security.
     *
     * @return bool Whether the cookie should be secure
     */
    public static function secureOlduserCookie()
    {
        return (is_ssl() && ('https' === parse_url(home_url(), PHP_URL_SCHEME)));
    }

    /**
     * Validates the old user cookie and returns its user data.
     *
     * @return false|WP_User False if there's no old user cookie or it's invalid, WP_User object if it's present and valid.
     */
    public static function getOldUser()
    {
        $cookie = wpextended_user_switching_get_olduser_cookie();

        if (empty($cookie)) {
            return false;
        }

        $old_user_id = wp_validate_auth_cookie($cookie, 'logged_in');
        if (!$old_user_id) {
            return false;
        }

        $old_user = get_userdata($old_user_id);

        /**
         * Filters the old user object retrieved from the cookie.
         *
         * @param WP_User|false $old_user The old user object
         * @return WP_User|false The filtered old user object
         */
        return apply_filters('wpextended/user-switching/current_old_user', $old_user);
    }

    /**
     * Defines the names of the cookies used by User Switching.
     */
    public function actionInitialLoaded()
    {
        // User Switching's auth_cookie
        if (!defined('WPEXT_SWITCHING_COOKIE')) {
            define('WPEXT_SWITCHING_COOKIE', 'wpext_user_role_' . COOKIEHASH);
        }

        // User Switching's wpext_secure_auth_cookie
        if (!defined('WPEXT_ROLE_SECURE_COOKIE')) {
            define('WPEXT_ROLE_SECURE_COOKIE', 'wpext_user_role_secure_' . COOKIEHASH);
        }

        // User Switching's logged_in_cookie
        if (!defined('WPEXT_SWITCHING_PREVUSER_COOKIE')) {
            define('WPEXT_SWITCHING_PREVUSER_COOKIE', 'wpext_user_role_olduser_' . COOKIEHASH);
        }
    }

    /**
     * Loads localisation files and routes actions depending on the 'action' query var.
     */
    public function userRoleInit()
    {
        if (!isset($_REQUEST['action'])) {
            return;
        }

        $current_user = (is_user_logged_in()) ? wp_get_current_user() : null;

        switch ($_REQUEST['action']) {
            // We're attempting to switch to another user:
            case 'switch_user_role':
                if (isset($_REQUEST['user_id'])) {
                    $user_id = absint($_REQUEST['user_id']);
                } else {
                    $user_id = 0;
                }

                // Check authentication:
                if (!current_user_can('switch_user_role', $user_id)) {
                    wp_die(esc_html__('Could not switch users.', WP_EXTENDED_TEXT_DOMAIN), 403);
                }

                // Check intent:
                check_admin_referer("switch_user_role_{$user_id}");

                // Switch user:
                $user = wpextended_user_switching_switch_to_user($user_id, self::switchRemember());
                if (!$user) {
                    wp_die(esc_html__('Could not switch users.', WP_EXTENDED_TEXT_DOMAIN), 404);
                }

                $redirect_to = self::usersGetRedirect($user, $current_user);

                // Redirect to the dashboard or the home URL depending on capabilities:
                $args = [
                    'user_switched' => 'true',
                ];

                if ($redirect_to) {
                    wp_safe_redirect(add_query_arg($args, $redirect_to), 302);
                } elseif (!current_user_can('read')) {
                    wp_safe_redirect(add_query_arg($args, home_url()), 302);
                } else {
                    wp_safe_redirect(add_query_arg($args, admin_url()), 302);
                }
                exit;

                // We're attempting to switch back to the originating user:
            case 'switch_to_olduser':
                // Fetch the originating user data:
                $old_user = self::getOldUser();
                if (!$old_user) {
                    wp_die(esc_html__('Could not switch users.', WP_EXTENDED_TEXT_DOMAIN), 400);
                }

                // Check authentication:
                if (!self::authenticateOldUser($old_user)) {
                    wp_die(esc_html__('Could not switch users.', WP_EXTENDED_TEXT_DOMAIN), 403);
                }

                // Check intent:
                check_admin_referer("switch_to_olduser_{$old_user->ID}");

                // Switch user:
                if (!wpextended_user_switching_switch_to_user($old_user->ID, self::switchRemember(), false)) {
                    wp_die(esc_html__('Could not switch users.', WP_EXTENDED_TEXT_DOMAIN), 404);
                }

                if (!empty($_REQUEST['interim-login']) && function_exists('login_header')) {
                    $GLOBALS['interim_login'] = 'success';
                    login_header('', '');
                    exit;
                }

                $redirect_to = self::usersGetRedirect($old_user, $current_user);
                $args = [
                    'user_switched' => 'true',
                    'switched_back' => 'true',
                ];

                if ($redirect_to) {
                    wp_safe_redirect(add_query_arg($args, $redirect_to), 302);
                } else {
                    wp_safe_redirect(add_query_arg($args, admin_url('users.php')), 302);
                }
                exit;

                // We're attempting to temporarily log out the current user:
            case 'switch_off':
                // Check authentication:
                if (!$current_user || !current_user_can('switch_off')) {
                    wp_die(esc_html__('Could not temporarily log out.', WP_EXTENDED_TEXT_DOMAIN), 403);
                }

                // Check intent:
                check_admin_referer("switch_off_{$current_user->ID}");

                // Switch off:
                $success = wpextended_user_switching_switch_off_user();

                /**
                 * Filters the result of the switch off operation.
                 *
                 * @param bool $success Whether the switch off operation was successful
                 * @return bool Whether the operation should proceed
                 */
                $success = apply_filters('wpextended/user-switching/do_switch_off', $success);

                if (!$success) {
                    wp_die(esc_html__('Could not temporarily log out.', WP_EXTENDED_TEXT_DOMAIN), 403);
                }

                $redirect_to = self::usersGetRedirect(null, $current_user);
                $args = [
                    'switched_off' => 'true',
                ];

                if ($redirect_to) {
                    wp_safe_redirect(add_query_arg($args, $redirect_to), 302);
                } else {
                    wp_safe_redirect(add_query_arg($args, home_url()), 302);
                }
                exit;
        }
    }

    /**
     * Displays the 'Switched to {user}' and 'Switch back to {user}' messages in the admin area.
     */
    public function adminNotices()
    {
        $user = wp_get_current_user();
        $old_user = self::getOldUser();

        if ($old_user) {
            $message = '';
            $just_switched = isset($_GET['user_switched']);

            if ($just_switched) {
                $message = esc_html(self::switchedToMessage($user));
            }

            $switch_back_url = add_query_arg(
                array(
                    'redirect_to' => rawurlencode(self::checkCurrentUrl()),
                ),
                self::moveBackUrl($old_user)
            );

            // Combine both messages into a single notice
            $combined_message = sprintf(
                '<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span> %s <a href="%s">%s</a>.',
                $message ? $message . ' ' : '',
                esc_url($switch_back_url),
                esc_html(self::userSwitchBackMessage($old_user))
            );

            Notices::add(array(
                'message' => $combined_message,
                'type' => 'success',
                'id' => 'user_switch_notice',
                'persistent' => false,
                'dismissible' => true
            ));
        } elseif (isset($_GET['user_switched'])) {
            // Just one notice for users who have been switched or switched back
            $message = isset($_GET['switched_back'])
                ? esc_html(self::userSwitchedBackMessage($user))
                : esc_html(self::switchedToMessage($user));

            Notices::add(array(
                'message' => $message,
                'type' => 'success',
                'id' => 'user_switch_notice',
                'persistent' => false,
                'dismissible' => true
            ));
        }
    }

    /**
     * Displays the 'Switch back to {user}' message in the footer if the user has switched.
     */
    public function switchFooterAction()
    {
        // Check if there is an old user to switch back to
        $old_user = self::getOldUser();
        if (!$old_user) {
            return;
        }

        // Build the URL to switch back
        $url = add_query_arg(
            array(
                'redirect_to' => rawurlencode(self::checkCurrentUrl()),
            ),
            self::moveBackUrl($old_user)
        );

        // Display the message in the footer
        printf(
            '<p id="wpextended-user-switching-switch" style="position:fixed;bottom:40px;padding:0;margin:0;right:10px;font-size:13px;z-index:99999; background-color: #fff; padding: 10px !important;"><a href="%s" style="color: #0073AA; font-weight:600">%s</a></p>',
            esc_url($url),
            esc_html(self::userSwitchBackMessage($old_user))
        );
    }

    /**
     * Clears the cookies containing the originating user.
     *
     * If $clear_all is true, all cookies are cleared.
     * Otherwise, pops the latest item off the end if there's more than one.
     *
     * @param bool $clear_all Whether to clear all cookies or just the latest
     */
    public static function clearOldUserCookie($clear_all = true)
    {
        $auth_cookie = wpextended_user_switching_get_auth_cookie();

        if (!empty($auth_cookie)) {
            array_pop($auth_cookie);
        }

        if ($clear_all || empty($auth_cookie)) {
            /**
             * Fires just before the user switching cookies are cleared.
             */
            do_action('wpextended/user-switching/clear_olduser_cookie');

            /**
             * Filter whether to send authentication cookies to the client.
             *
             * @param bool $send_cookies Whether to send the auth cookies
             * @return bool
             */
            if (!apply_filters('wpextended/user-switching/send_auth_cookies', true)) {
                return;
            }

            $expire = time() - 31536000;
            setcookie('WPEXT_SWITCHING_COOKIE', ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN);
            setcookie('WPEXT_ROLE_SECURE_COOKIE', ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN);
            setcookie('WPEXT_SWITCHING_PREVUSER_COOKIE', ' ', $expire, COOKIEPATH, COOKIE_DOMAIN);
            return;
        }

        $scheme = self::secureAuthCookie() ? 'secure_auth' : 'auth';
        $old_cookie = end($auth_cookie);
        $old_user_id = wp_validate_auth_cookie($old_cookie, $scheme);

        if (!$old_user_id) {
            return;
        }

        $parts = wp_parse_auth_cookie($old_cookie, $scheme);
        if (false === $parts) {
            return;
        }

        wpextended_user_switching_set_olduser_cookie($old_user_id, true, $parts['token']);
    }

    /**
     * Adds a 'Switch To' button in the personal options section of the user edit screen.
     *
     * @param WP_User $user The user being edited
     * @return void
     */
    public function showSwitchToUserOptionInProfile($user)
    {
        // Current user is looking at their own profile
        if (get_current_user_id() === $user->ID) {
            return;
        }

        // Don't show the option if the current user can't switch to this user
        if (!current_user_can('switch_user_role', $user->ID)) {
            return;
        }

        $switch_url = self::usersNeedSwitchUrl($user);
        if (!$switch_url) {
            return;
        }
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('User Switching', WP_EXTENDED_TEXT_DOMAIN); ?></th>
            <td>
                <a href="<?php echo esc_url($switch_url); ?>" class="button button-secondary">
                    <?php esc_html_e('Switch to this user', WP_EXTENDED_TEXT_DOMAIN); ?>
                </a>
                <p class="description">
                    <?php esc_html_e('Instantly switch to this user account.', WP_EXTENDED_TEXT_DOMAIN); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Gets all users that the current user can switch to
     *
     * @return array Array of WP_User objects
     */
    public static function getUsersAvailableToSwitchTo()
    {
        $current_user_id = get_current_user_id();

        // Determine if user can list other users
        if (!current_user_can('list_users')) {
            return array();
        }

        // Query for users that the current user can switch to
        $args = array(
            'exclude' => array($current_user_id),
            'fields' => array('ID', 'user_login', 'display_name', 'user_email'),
        );

        // If the user doesn't have the 'edit_users' capability, we need to get creative
        if (!current_user_can('edit_users')) {
            return array();
        }

        $users = get_users($args);

        // Filter out users that the current user can't switch to
        $users_can_switch_to = array();
        foreach ($users as $user) {
            if (current_user_can('switch_user_role', $user->ID)) {
                $users_can_switch_to[] = $user;
            }
        }

        return $users_can_switch_to;
    }

    /**
     * Handler for the 'wpextended/user-switching/do_switch_off' filter.
     * Performs the temporary logout operation.
     *
     * @return bool Whether the user was switched off successfully
     */
    public function doSwitchOff()
    {
        return wpextended_user_switching_switch_off_user();
    }

    /**
     * Handler for the 'wpextended/user-switching/current_old_user' filter.
     * Returns the old user object if present.
     *
     * @return false|\WP_User The old user object or false if not present
     */
    public function getCurrentOldUser()
    {
        return self::getOldUser();
    }

    /**
     * Handler for the 'wpextended/user-switching/get-redirect' filter.
     * Returns the redirect URL for after switching.
     *
     * @param string    $redirect_to Default redirect URL
     * @param \WP_User  $new_user    The user being switched to (or null when switching off)
     * @param \WP_User  $old_user    The user being switched from
     * @return string The URL to redirect to
     */
    public function getRedirectUrl($redirect_to, $new_user, $old_user)
    {
        return self::usersGetRedirect($new_user, $old_user);
    }

    /**
     * Handler for the 'wpextended/user-switching/switch-to-url' filter.
     * Generates a URL for switching to a user.
     *
     * @param string   $url  Default URL (empty string)
     * @param \WP_User $user The user to switch to
     * @return string The URL for switching to the specified user
     */
    public function getSwitchToUrl($url, $user)
    {
        return self::switchToUrl($user);
    }
}

/**
 * Gets the value of the cookie containing the originating user.
 *
 * @return string|false The cookie value or false if not set
 */
function wpextended_user_switching_get_olduser_cookie()
{
    if (!isset($_COOKIE['WPEXT_SWITCHING_PREVUSER_COOKIE'])) {
        return false;
    }

    return wp_unslash($_COOKIE['WPEXT_SWITCHING_PREVUSER_COOKIE']);
}

/**
 * Gets the value of the auth cookie containing the list of originating users.
 *
 * @return array<int,string> Array of originating user authentication cookie values. Empty array if there are none.
 */
function wpextended_user_switching_get_auth_cookie()
{
    $auth_cookie_name = \Wpextended\Modules\UserSwitching\Bootstrap::secureAuthCookie()
        ? 'WPEXT_ROLE_SECURE_COOKIE'
        : 'WPEXT_SWITCHING_COOKIE';

    if (isset($_COOKIE[$auth_cookie_name]) && is_string($_COOKIE[$auth_cookie_name])) {
        $cookie = json_decode(wp_unslash($_COOKIE[$auth_cookie_name]));
    }

    if (!isset($cookie) || !is_array($cookie)) {
        $cookie = array();
    }

    return $cookie;
}

/**
 * Switches the current logged in user to the specified user.
 *
 * @param int  $user_id         The ID of the user to switch to
 * @param bool $switch_remember Whether to remember the user login
 * @param bool $set_old_user    Whether to set the old user cookie
 * @return \WP_User|false The switched user object on success, false on failure
 */
function wpextended_user_switching_switch_to_user($user_id, $switch_remember = false, $set_old_user = true)
{
    $user = get_userdata($user_id);

    if (!$user) {
        return false;
    }

    $old_user_id = is_user_logged_in() ? get_current_user_id() : false;
    $old_token = function_exists('wp_get_session_token') ? wp_get_session_token() : '';
    $auth_cookies = wpextended_user_switching_get_auth_cookie();
    $auth_cookie = end($auth_cookies);
    $cookie_parts = $auth_cookie ? wp_parse_auth_cookie($auth_cookie) : false;

    if ($set_old_user && $old_user_id) {
        // Switching to another user
        $new_token = '';
        wpextended_user_switching_set_olduser_cookie($old_user_id, false, $old_token);
    } else {
        // Switching back, either after being switched off or after being switched to another user
        $new_token = ($cookie_parts && isset($cookie_parts['token'])) ? $cookie_parts['token'] : '';
        \Wpextended\Modules\UserSwitching\Bootstrap::clearOldUserCookie(false);
    }

    /**
     * Attach the original user ID and session token to the new session
     */
    $session_filter = function (array $session, $user_id) use ($old_user_id, $old_token) {
        $session['switched_from_id'] = $old_user_id;
        $session['switched_from_session'] = $old_token;
        return $session;
    };

    add_filter('wpextended/user-switching/attach_session_information', $session_filter, 99, 2);

    wp_clear_auth_cookie();
    wp_set_auth_cookie($user_id, $switch_remember, '', $new_token);
    wp_set_current_user($user_id);

    remove_filter('wpextended/user-switching/attach_session_information', $session_filter, 99);

    if ($set_old_user && $old_user_id) {
        /**
         * Fires when a user switches to another user account.
         *
         * @param int    $user_id    The ID of the user being switched to
         * @param int    $old_user_id The ID of the user being switched from
         * @param string $new_token  The token of the new user session
         * @param string $old_token  The token of the old user session
         */
        do_action('wpextended/user-switching/switch_to_user', $user_id, $old_user_id, $new_token, $old_token);
    } else {
        /**
         * Fires when a user switches back to their originating account.
         *
         * @param int    $user_id    The ID of the user being switched to
         * @param int    $old_user_id The ID of the user being switched from
         * @param string $new_token  The token of the new user session
         * @param string $old_token  The token of the old user session
         */
        do_action('wpextended/user-switching/switch_back_user', $user_id, $old_user_id, $new_token, $old_token);
    }

    if ($old_token && $old_user_id && !$set_old_user) {
        // When switching back, destroy the session for the old user
        $manager = \WP_Session_Tokens::get_instance($old_user_id);
        $manager->destroy($old_token);
    }

    return $user;
}

/**
 * Switches off the current logged in user.
 *
 * This logs the current user out while retaining a cookie allowing them to log
 * straight back in using the 'Switch back to {user}' system.
 *
 * @return bool True on success, false on failure.
 */
function wpextended_user_switching_switch_off_user(): bool
{
    $old_user_id = get_current_user_id();

    if (!$old_user_id) {
        return false;
    }

    $old_token = wp_get_session_token();

    wpextended_user_switching_set_olduser_cookie($old_user_id, false, $old_token);
    wp_clear_auth_cookie();
    wp_set_current_user(0);

    /**
     * Fires when a user switches off.
     *
     * @param int    $old_user_id The ID of the user switching off.
     * @param string $old_token   The token of the session of the user switching off.
     */
    do_action('wpextended/user-switching/switch_off_user', $old_user_id, $old_token);

    return true;
}

/**
 * Sets authorisation cookies containing the originating user information.
 *
 * @param int    $old_user_id The ID of the old user
 * @param bool   $pop         Whether to pop the auth cookie stack
 * @param string $token       The auth token
 */
function wpextended_user_switching_set_olduser_cookie($old_user_id, $pop = false, $token = '')
{
    $secure_auth_cookie = \Wpextended\Modules\UserSwitching\Bootstrap::secureAuthCookie();
    $secure_olduser_cookie = \Wpextended\Modules\UserSwitching\Bootstrap::secureOlduserCookie();
    $expiration = time() + 172800; // 48 hours
    $auth_cookie = wpextended_user_switching_get_auth_cookie();
    $olduser_cookie = wp_generate_auth_cookie($old_user_id, $expiration, 'logged_in', $token);

    $auth_cookie_name = $secure_auth_cookie ? 'WPEXT_ROLE_SECURE_COOKIE' : 'WPEXT_SWITCHING_COOKIE';
    $scheme = $secure_auth_cookie ? 'secure_auth' : 'auth';

    if ($pop) {
        array_pop($auth_cookie);
    } else {
        array_push($auth_cookie, wp_generate_auth_cookie($old_user_id, $expiration, $scheme, $token));
    }

    $auth_cookie = json_encode($auth_cookie);

    if (false === $auth_cookie) {
        return;
    }

    /**
     * Fires immediately before the User Switching authentication cookie is set.
     *
     * @param string $auth_cookie The auth cookie value
     * @param int    $expiration  The cookie expiration timestamp
     * @param int    $old_user_id The ID of the old user
     * @param string $scheme      The auth scheme
     * @param string $token       The auth token
     */
    do_action('wpextended/user-switching/set_auth_cookie', $auth_cookie, $expiration, $old_user_id, $scheme, $token);

    $scheme = 'logged_in';

    /**
     * Fires immediately before the User Switching old user cookie is set.
     *
     * @param string $olduser_cookie The old user cookie value
     * @param int    $expiration     The cookie expiration timestamp
     * @param int    $old_user_id    The ID of the old user
     * @param string $scheme         The auth scheme
     * @param string $token          The auth token
     */
    do_action('wpextended/user-switching/set_olduser_cookie', $olduser_cookie, $expiration, $old_user_id, $scheme, $token);

    /**
     * Allows preventing auth cookies from actually being sent to the client.
     *
     * @param bool $send_cookies Whether to send the auth cookies
     * @return bool
     */
    if (!apply_filters('wpextended/user-switching/send_auth_cookies', true)) {
        return;
    }

    setcookie($auth_cookie_name, $auth_cookie, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_auth_cookie, true);
    setcookie('WPEXT_SWITCHING_PREVUSER_COOKIE', $olduser_cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure_olduser_cookie, true);
}
