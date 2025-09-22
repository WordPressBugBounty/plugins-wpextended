<?php

namespace Wpextended\Includes;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator
{
    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate()
    {
        // Only remove data if the user opted in via global setting
        if (is_multisite()) {
            $site_ids = get_sites(array('fields' => 'ids'));
            $did_cleanup_any_site = false;

            if (is_array($site_ids)) {
                foreach ($site_ids as $site_id) {
                    switch_to_blog((int) $site_id);

                    $remove = (bool) Utils::getSetting('global', 'remove_plugin_data', false);
                    if ($remove) {
                        self::cleanupSite();
                        $did_cleanup_any_site = true;
                    }

                    restore_current_blog();
                }
            }

            if ($did_cleanup_any_site) {
                self::cleanupNetwork();
            }
        } else {
            $remove = (bool) Utils::getSetting('global', 'remove_plugin_data', false);
            if (!$remove) {
                return;
            }
            self::cleanupSite();
        }
    }

    /**
     * Cleanup data for the current site (single site or a blog in multisite).
     *
     * Deletes plugin options, transients, post meta, user meta and custom tables.
     *
     * @return void
     */
    private static function cleanupSite()
    {
        global $wpdb;

        // 1) Delete options with known plugin prefixes
        $option_prefixes = array(
            'wpextended__', // primary settings prefix
            'wpextended_',  // other internal keys
            'wpext_',       // legacy keys
        );

        foreach ($option_prefixes as $prefix) {
            $like = $wpdb->esc_like($prefix) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));

            // Remove site transients stored in options table
            $transient_like = $wpdb->esc_like('_transient_' . $prefix) . '%';
            $timeout_like = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $transient_like,
                    $timeout_like
                )
            );
        }

        // Explicit legacy key not covered by prefix sweep
        delete_option('wp-extended-modules');

        // 2) Delete post meta created by modules
        $post_meta_keys = array(
            '_wpext_menu_item_visible',
            '_links_to', // external-permalinks module
        );

        if (!empty($post_meta_keys)) {
            $placeholders = implode(',', array_fill(0, count($post_meta_keys), '%s'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)", $post_meta_keys));
        }

        // 3) Delete user meta created by modules
        $user_meta_keys = array(
            'wpext_user_last_login_status', // user-last-login module
            'wpextended_switch_user_default', // user-switching module
        );

        if (!empty($user_meta_keys)) {
            $placeholders = implode(',', array_fill(0, count($user_meta_keys), '%s'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)", $user_meta_keys));
        }

        // 4) Drop custom tables created by modules
        $custom_tables = array(
            $wpdb->prefix . 'wpext_logs',
            $wpdb->prefix . 'wpext_login_failed',
            $wpdb->prefix . 'wpext_login_attempt',
        );
        foreach ($custom_tables as $table) {
            // Validate table begins with current prefix to avoid accidental deletion
            if (strpos($table, $wpdb->prefix) === 0) {
                $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
            }
        }

        // 5) Unschedule any cron events registered by the plugin
        $hooks = array(
            'wpextended_smtp_email_purge_logs',
        );
        foreach ($hooks as $hook) {
            $next = wp_next_scheduled($hook);
            while ($next) {
                wp_unschedule_event($next, $hook);
                $next = wp_next_scheduled($hook);
            }
        }
    }

    /**
     * Cleanup network-level transients and options for multisite installs.
     *
     * @return void
     */
    private static function cleanupNetwork()
    {
        global $wpdb;

        $prefixes = array('wpextended__', 'wpextended_', 'wpext_');
        foreach ($prefixes as $prefix) {
            $site_transient_like = $wpdb->esc_like('_site_transient_' . $prefix) . '%';
            $site_timeout_like = $wpdb->esc_like('_site_transient_timeout_' . $prefix) . '%';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                    $site_transient_like,
                    $site_timeout_like
                )
            );

            // Remove any network options with our prefixes (defensive; none expected)
            $network_like = $wpdb->esc_like($prefix) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $network_like));
        }
    }
}
