<?php

namespace Wpextended\Modules\RollbackManager;

use Theme_Upgrader;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme Rollback Upgrader
 *
 * Extends the WordPress Core Theme_Upgrader to provide rollback functionality.
 *
 * @since 1.0.0
 */
class ThemeUpdater extends Theme_Upgrader
{
    /**
     * Roll back a theme to a specific version
     *
     * @param string $theme_slug The theme slug
     * @param string $version The version to roll back to
     * @param array  $args Additional arguments
     *
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function rollback($theme_slug, $version, $args = [])
    {
        $defaults = array(
            'clear_update_cache' => true,
        );
        $args = wp_parse_args($args, $defaults);

        $this->init();
        $this->upgrade_strings();

        // Check if theme exists
        $theme = wp_get_theme($theme_slug);
        if (!$theme->exists()) {
            return new \WP_Error('theme_not_found', __('The theme does not exist.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Check if version is valid
        if (empty($version)) {
            return new \WP_Error('invalid_version', __('Invalid version specified.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Build download URL for the specific version
        $download_url = sprintf("https://downloads.wordpress.org/theme/%s.%s.zip", $theme_slug, $version);

        // Check if theme is active
        $is_active_theme = $theme->get_stylesheet() === get_stylesheet();
        $is_parent_theme = $theme->get_stylesheet() === get_template();

        // Run the upgrade/rollback process
        $result = $this->run(array(
            'package'           => $download_url,
            'destination'       => get_theme_root(),
            'clear_destination' => true,
            'clear_working'     => true,
            'hook_extra'        => array(
                'theme'  => $theme_slug,
                'type'   => 'theme',
                'action' => 'update',
                'bulk'   => false,
            ),
        ));

        // Check for errors
        if (!$result || is_wp_error($result)) {
            return $result;
        }

        // Force refresh of theme update information
        if ($args['clear_update_cache']) {
            wp_clean_themes_cache();
        }

        // If this was the active theme, we need to trigger a redirect to the customizer
        if ($is_active_theme || $is_parent_theme) {
            $this->skin->feedback(__('The active theme was successfully rolled back. Redirecting to the Themes page...', WP_EXTENDED_TEXT_DOMAIN));
        }

        return true;
    }

    /**
     * Custom hook for theme deletion during rollback
     *
     * @param bool   $removed Whether the destination was cleared
     * @param string $local_destination The local destination directory
     * @param string $remote_destination The remote destination directory
     * @param array  $theme The theme information
     * @return bool
     */
    public function deleteOldTheme($removed, $local_destination, $remote_destination, $theme)
    {
        global $wp_filesystem;

        // Theme directory
        $themes_dir = trailingslashit($wp_filesystem->wp_themes_dir());
        $theme_dir = trailingslashit($themes_dir . $theme['theme']);

        // If we get an error while trying to delete the old theme, continue anyway
        if (!$wp_filesystem->exists($theme_dir)) {
            return $removed;
        }

        // Delete the directory
        $removed = $wp_filesystem->delete($theme_dir, true);

        return $removed;
    }
}
