<?php

namespace Wpextended\Modules\RollbackManager;

use Plugin_Upgrader;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Rollback Upgrader
 *
 * Extends the WordPress Core Plugin_Upgrader to provide rollback functionality.
 *
 * @since 1.0.0
 */
class PluginUpdater extends Plugin_Upgrader
{
    /**
     * Roll back a plugin to a specific version
     *
     * @param string $file The plugin file (e.g. plugin-slug/plugin-slug.php)
     * @param string $slug The plugin slug (directory name)
     * @param string $version The version to roll back to
     * @param array  $args Additional arguments
     *
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function rollback($file, $slug, $version, $args = array())
    {
        $defaults = array(
            'clear_update_cache' => true,
        );
        $args = wp_parse_args($args, $defaults);

        $this->init();
        $this->upgrade_strings();

        // Check if plugin exists
        if (!file_exists(WP_PLUGIN_DIR . '/' . $file)) {
            return new \WP_Error('plugin_not_found', __('The plugin does not exist.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Check if version is valid
        if (empty($version)) {
            return new \WP_Error('invalid_version', __('Invalid version specified.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Get the download URL for the version
        $download_url = $this->getDownloadUrl($slug, $version);

        if (!$download_url) {
            return new \WP_Error(
                'download_url_not_found',
                __('Download URL not found for the specified version.', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        // Check if plugin is active
        $is_plugin_active = is_plugin_active($file);

        // Add filters for the rollback process
        add_filter('upgrader_pre_install', array($this, 'deactivate_plugin_before_upgrade'), 10, 2);
        add_filter('upgrader_pre_install', array($this, 'active_before'), 10, 2);
        add_filter('upgrader_clear_destination', array($this, 'delete_old_plugin'), 10, 4);
        add_filter('upgrader_post_install', array($this, 'active_after'), 10, 2);

        // Run the upgrade/rollback process
        $result = $this->run(array(
            'package'           => $download_url,
            'destination'       => WP_PLUGIN_DIR,
            'clear_destination' => true,
            'clear_working'     => true,
            'hook_extra'        => array(
                'plugin' => $file,
                'type'   => 'plugin',
                'action' => 'update',
                'bulk'   => false,
            ),
        ));

        // Remove filters after process
        remove_action('upgrader_process_complete', 'wp_clean_plugins_cache', 9);
        remove_filter('upgrader_pre_install', array($this, 'deactivate_plugin_before_upgrade'));
        remove_filter('upgrader_pre_install', array($this, 'active_before'));
        remove_filter('upgrader_clear_destination', array($this, 'delete_old_plugin'));
        remove_filter('upgrader_post_install', array($this, 'active_after'));

        // Check for errors
        if (!$result || is_wp_error($result)) {
            return $result;
        }

        // Reactivate plugin if it was active before
        if ($is_plugin_active) {
            $this->reactivatePluginAfterUpgrade($file);
        }

        // Force refresh of plugin update information
        if ($args['clear_update_cache']) {
            wp_clean_plugins_cache();
        }

        return true;
    }

    /**
     * Get the download URL for a plugin version
     *
     * @param string $slug The plugin slug
     * @param string $version The version to get the download URL for
     *
     * @return string|false The download URL or false if not found
     */
    private function getDownloadUrl($slug, $version)
    {
        $api_url = 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['versions'][$version])) {
            return false;
        }

        return $data['versions'][$version];
    }

    /**
     * Reactivate plugin after upgrade
     *
     * @param string $file The plugin file (e.g. plugin-slug/plugin-slug.php)
     *
     * @return bool
     */
    private function reactivatePluginAfterUpgrade($file)
    {
        activate_plugin($file, '', false, true);
    }

    /**
     * Deactivate plugin before upgrade
     *
     * @param bool  $return
     * @param array $plugin_data
     * @return bool
     */
    public function deactivatePluginBeforeUpgrade($return, $plugin_data)
    {
        if (isset($plugin_data['plugin']) && is_plugin_active($plugin_data['plugin'])) {
            deactivate_plugins($plugin_data['plugin'], true);
        }
        return $return;
    }

    /**
     * Log before activation status
     *
     * @param bool  $return
     * @param array $plugin_data
     * @return bool
     */
    public function activeBefore($return, $plugin_data)
    {
        $this->active_before = is_plugin_active($plugin_data['plugin']);
        return $return;
    }

    /**
     * Restore activation status
     *
     * @param bool  $return
     * @param array $plugin_data
     * @return bool
     */
    public function activeAfter($return, $plugin_data)
    {
        if ($this->active_before && !is_wp_error($return) && $plugin_data['plugin']) {
            activate_plugin($plugin_data['plugin']);
        }
        return $return;
    }
}
