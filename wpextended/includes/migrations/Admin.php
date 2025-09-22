<?php

namespace Wpextended\Includes\Migrations;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Global plugin migration
 * Handles remapping of core plugin settings and data
 */
class Admin
{
    public function __construct()
    {
        $this->migrateGlobalSettings();
        $this->migrateActiveModules();
        $this->purgeLegacyGlobalOptions();
    }

    /**
     * Map legacy global options to consolidated settings under
     * the option `wpextended__global_settings` (Utils::updateSettings('global', ...)).
     * Idempotent: preserves values already present in the new structure.
     */
    private function migrateGlobalSettings(): void
    {
        $newSettings = Utils::getSettings('global');
        if (!is_array($newSettings)) {
            $newSettings = [];
        }

        // License
        $legacyLicenseKey = get_option('wpext_license_key', '');
        if (is_string($legacyLicenseKey)) {
            $legacyLicenseKey = sanitize_text_field($legacyLicenseKey);
        } else {
            $legacyLicenseKey = '';
        }

        $legacyStatus = get_option('wpext_lisence-status', '');
        $legacyStatus = is_string($legacyStatus) ? sanitize_text_field($legacyStatus) : '';

        $legacySuccess = get_option('wpext_lisence_success', '');
        if ($legacySuccess === '') {
            $legacySuccess = get_option('wpext_lisence-success', '');
        }

        // Normalize success to boolean-ish
        $successTrue = ($legacySuccess === '1' || $legacySuccess === 1 || $legacySuccess === 'true');
        $derivedStatus = $legacyStatus !== '' ? $legacyStatus : ($successTrue ? 'valid' : '');

        if (!isset($newSettings['license_key']) && $legacyLicenseKey !== '') {
            $newSettings['license_key'] = $legacyLicenseKey;
        }
        if (!isset($newSettings['license_status']) && $derivedStatus !== '') {
            $newSettings['license_status'] = $derivedStatus;
        }

        // Keep existing license_expiry if present; no reliable legacy key
        if (!array_key_exists('license_expiry', $newSettings)) {
            $newSettings['license_expiry'] = '';
        }

        // General toggles
        $legacyRemoveData = get_option('wpext_plugin_reset_action', ''); // 'true' | 'false'
        $removePluginData = false;
        if (is_string($legacyRemoveData)) {
            $removePluginData = (strtolower($legacyRemoveData) === 'true' || $legacyRemoveData === '1');
        } elseif (is_bool($legacyRemoveData)) {
            $removePluginData = $legacyRemoveData;
        } elseif (is_int($legacyRemoveData)) {
            $removePluginData = ($legacyRemoveData === 1);
        }
        if (!array_key_exists('remove_plugin_data', $newSettings)) {
            $newSettings['remove_plugin_data'] = $removePluginData;
        }

        // Submenu display and favorites
        $legacyShowSubmenu = get_option('wpext_show_plugin_menu_action', '');
        $displaySubmodules = false;
        if (is_string($legacyShowSubmenu)) {
            $displaySubmodules = (strtolower($legacyShowSubmenu) === 'true' || $legacyShowSubmenu === '1');
        } elseif (is_bool($legacyShowSubmenu)) {
            $displaySubmodules = $legacyShowSubmenu;
        } elseif (is_int($legacyShowSubmenu)) {
            $displaySubmodules = ($legacyShowSubmenu === 1);
        }
        if (!array_key_exists('display_submodules', $newSettings)) {
            $newSettings['display_submodules'] = $displaySubmodules;
        }

        // Enabled submodules
        $legacyFavorites = get_option('wpext_admin_menu_favorite', []);
        if (is_array($legacyFavorites)) {
            $mapped = [];
            // Map legacy option keys to new submodule slugs.
            $map = $this->getLegacyToModuleMap();

            foreach ($legacyFavorites as $legacyKey => $value) {
                $enabled = false;
                if (is_string($value)) {
                    $enabled = (strtolower($value) === 'true' || $value === '1');
                } elseif (is_bool($value)) {
                    $enabled = $value;
                } elseif (is_int($value)) {
                    $enabled = ($value === 1);
                }
                if (!$enabled) {
                    continue;
                }
                if (isset($map[$legacyKey])) {
                    $mapped[] = $map[$legacyKey];
                }
            }

            if (!empty($mapped) && empty($newSettings['enabled_submodules'])) {
                $newSettings['enabled_submodules'] = array_values(array_unique($mapped));
            }

            if ($displaySubmodules && empty($newSettings['enabled_submodules']) && !isset($newSettings['enable_all_submodules'])) {
                $newSettings['enable_all_submodules'] = true;
            }
        }

        // Development mode default
        if (!array_key_exists('use_development', $newSettings)) {
            $newSettings['use_development'] = false;
        }

        // Save consolidated settings
        Utils::updateSettings('global', $newSettings);
    }

    /**
     * Migrate legacy enabled modules from `wp-extended-modules` to the
     * consolidated modules settings `wpextended__modules_settings` (context 'modules').
     *
     * Idempotent: will only set if the new list is empty/missing.
     */
    private function migrateActiveModules(): void
    {
        // If modules already set, do nothing
        $existing = Utils::getSetting('modules', 'modules');
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $legacy = get_option('wp-extended-modules', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $map = $this->getLegacyToModuleMap();
        $enabled = [];

        foreach ($legacy as $legacyKey => $isActive) {
            // Accept 1, '1', true, 'true' as enabled; everything else is not
            $active = false;
            if (is_bool($isActive)) {
                $active = $isActive;
            } elseif (is_int($isActive)) {
                $active = ($isActive === 1);
            } elseif (is_string($isActive)) {
                $active = (strtolower(trim($isActive)) === '1' || strtolower(trim($isActive)) === 'true');
            }
            if (!$active) {
                continue;
            }
            if (isset($map[$legacyKey])) {
                $enabled[] = $map[$legacyKey];
            }
        }

        // Only save if we have enabled modules
        $enabled = array_values(array_unique(array_filter($enabled)));

        if (empty($enabled)) {
            return;
        }

        Utils::updateSetting('modules', 'modules', $enabled);
    }

    /**
     * Remove legacy global options after successful migration.
     */
    private function purgeLegacyGlobalOptions(): void
    {
        $legacyKeys = [
            // License-related (legacy/typos)
            'wpext_license_key',
            'wpext_lisence-status',
            'wpext_lisence_success',
            'wpext_lisence-success',
            'wpext_lisence_activation_time',
            'wpext_check_license_date',
            // UI toggles and favorites
            'wpext_plugin_reset_action',
            'wpext_show_plugin_menu_action',
            'wpext_admin_menu_favorite',
            'wpext_active_modules_status',
            // Legacy modules activation map
            'wp-extended-modules',
        ];

        foreach ($legacyKeys as $key) {
            delete_option($key);
        }
    }

    /**
     * Legacy slug to new module ID mapping used across migrations.
     *
     * @param string $key Optional: return only the mapped value for a specific key.
     *
     * @return array<string,string>|string
     */
    private function getLegacyToModuleMap($key = ''): array
    {
        $map = [
            // Admin tools
            'wpext_admin_columns'              => 'admin-columns',
            'wpext_admin_color_picker'         => 'admin-customiser',
            'wpext_duplicate_menu'             => 'duplicate-menu',
            'wpext_manu_visibility'            => 'menu-visibility',
            'wpext_tidy_nav'                   => 'menu-editor',
            'wpext_quick_add_post'             => 'quick-add-post',
            'wpext_disk_usage'                 => 'disk-usage-widget',
            'wpext_indexing_notice'            => 'indexing-notice',
            'wpext_quick_search'               => 'quick-search',
            'wpext_hide_admin_bar'             => 'hide-admin-bar',
            'wpext_disable_dashboard_widget'   => 'clean-dashboard',
            'wpext_user_sections'              => 'clean-profiles',

            // Content management
            'wpext_post_order'                 => 'post-type-order',
            'wpext_convert_posts_type'         => 'post-type-switcher',
            'wpext_export_posts'               => 'export-posts',
            'wpext_quick_image_replace'        => 'quick-image',
            'wpext_duplicate_post'             => 'duplicate-post',
            'wpext_disable_widgets_gutenberg'  => 'classic-widgets',
            'wpext_disable_gutenberg'          => 'classic-editor',
            'wpext_external_permalinks'        => 'external-permalinks',
            'wpext_export_users'               => 'export-users',
            'wpext_duplicator'                 => 'duplicate-post',

            // Developer tools
            'wpext_snippets'                   => 'code-snippets',
            'wpext_pixel_tag_manager'          => 'pixel-tag-manager',
            'wpext_plugin_and_theme_rollback'  => 'rollback-manager',
            'wpext_smtp_email'                 => 'smtp-email',
            'wpext_user_switching'             => 'user-switching',
            'wpext_debug'                      => 'debug-mode',

            // Security & Privacy
            'wpext_block_user_name_admin'      => 'block-usernames',
            'wpext_change_wp_admin_url'        => 'custom-login-url',
            'wpext_limit_login_attempts'       => 'limit-login-attempts',
            'wpext_hide_notices'               => 'hide-admin-notices',
            'wpext_last_login_status'          => 'user-last-login',
            'wpext_disable_user_numeration'    => 'user-enumeration',
            'wpext_disable_xml_rcp'            => 'disable-xml-rpc',
            'wpext_hide_the_wordPress_version' => 'hide-wp-version',
            'wpext_redirect-404-to- homepage'   => 'redirect-404-to-homepage',
            'wpext_obfuscate_author_slugs'     => 'hide-author-slugs',

            // Performance & Control
            'wpext_disable_blogs'              => 'disable-blog',
            'wpext_disable_comments'           => 'disable-comments',
            'wpext_disable_feeds'              => 'disable-rss-feeds',
            'wpext_autoupdate'                 => 'disable-auto-updates',
            'wpext_maintenance_mode'           => 'maintenance-mode',
            'wpext_target_blank'               => 'link-manager',

            // Media tools
            'wpext_media_replacement'          => 'media-replace',
            'wpext_media_trash'                => 'media-trash',
            'wpext_svg_file_upload'             => 'svg-upload',
            'wpext_media_video'                => 'disable-video-uploads',
        ];

        if (empty($key)) {
            return $map;
        }

        return isset($map[$key]) ? $map[$key] : '';
    }
}
