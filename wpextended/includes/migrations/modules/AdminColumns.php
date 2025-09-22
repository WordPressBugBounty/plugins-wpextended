<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Admin Columns settings to new consolidated module settings.
 *
 * Legacy per-target options:
 * - wpext_admin_column_fields_{postType}
 * - wpext_admin_column_fields_users
 * - wpext_admin_column_fields_comments
 * - wpext_admin_column_fields_media
 * - (optional) wpext-admin-column (general)
 *
 * New settings (stored via Utils::updateSettings('admin-columns', ...)):
 * - {postType}_columns: array of items
 * - users_columns: array of items
 * - attachment_columns: array of items
 * - comments_columns: array of items
 */
class AdminColumns
{
    /**
     * Run the module migration and clean up legacy options.
     */
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    /**
     * Perform migration from legacy options to new module settings.
     */
    public function migrate(): void
    {
        // Skip if already migrated
        $existing = Utils::getSettings('admin-columns');
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $settings = array();

        // Public post types
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            $legacy_key = 'wpext_admin_column_fields_' . $post_type;
            $legacy = get_option($legacy_key);
            if (is_array($legacy) && !empty($legacy)) {
                $settings[$post_type . '_columns'] = $this->transformLegacy($legacy, $post_type);
            }
        }

        // Users
        $legacy_users = get_option('wpext_admin_column_fields_users');
        if (is_array($legacy_users) && !empty($legacy_users)) {
            $settings['users_columns'] = $this->transformLegacy($legacy_users, 'users');
        }

        // Comments
        $legacy_comments = get_option('wpext_admin_column_fields_comments');
        if (is_array($legacy_comments) && !empty($legacy_comments)) {
            $settings['comments_columns'] = $this->transformLegacy($legacy_comments, 'comments');
        }

        // Media → attachment
        $legacy_media = get_option('wpext_admin_column_fields_media');
        if (is_array($legacy_media) && !empty($legacy_media)) {
            $settings['attachment_columns'] = $this->transformLegacy($legacy_media, 'attachment');
        }

        if (!empty($settings)) {
            Utils::updateSettings('admin-columns', $settings);
        }
    }

    /**
     * Cleanup legacy options after successful migration.
     */
    public function cleanup(): void
    {
        // Remove generic legacy option if present
        delete_option('wpext-admin-column');

        // Remove per-target legacy options
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            delete_option('wpext_admin_column_fields_' . $post_type);
        }
        delete_option('wpext_admin_column_fields_users');
        delete_option('wpext_admin_column_fields_comments');
        delete_option('wpext_admin_column_fields_media');
    }

    /**
     * Transform legacy array into new columns array shape.
     *
     * Legacy item example:
     *   {
     *     fieldsname: string,
     *     title: string,
     *     inputValue: string,
     *     isChecked: 0|1,
     *     selectddata2: string
     *   }
     */
    private function transformLegacy(array $legacy, string $content_type): array
    {
        $result = array();

        foreach ($legacy as $legacy_key => $legacy_item) {
            if (!is_array($legacy_item)) {
                continue;
            }

            $column_label = isset($legacy_item['fieldsname']) && is_string($legacy_item['fieldsname'])
                ? $legacy_item['fieldsname']
                : (is_string($legacy_key) ? $legacy_key : '');
            if ($column_label === '' || $column_label === 'cb') {
                continue;
            }

            $column_title = '';
            if (!empty($legacy_item['title']) && is_string($legacy_item['title'])) {
                $column_title = $legacy_item['title'];
            } elseif (!empty($legacy_item['inputValue']) && is_string($legacy_item['inputValue'])) {
                $column_title = $legacy_item['inputValue'];
            } else {
                $column_title = $column_label;
            }

            // In legacy, isChecked represented the "Disable" checkbox state.
            // So isChecked=1 => disabled, isChecked=0 => enabled.
            $is_checked = isset($legacy_item['isChecked']) ? (int) $legacy_item['isChecked'] : 0;

            $result[] = array(
                'column_label' => (string) $column_label,
                'column_title' => (string) $column_title,
                'disable_column' => $is_checked ? true : false,
            );
        }

        // Enforce primary column
        $primary = $content_type === 'users' ? 'username' : 'title';
        foreach ($result as &$item) {
            if (($item['column_label'] ?? '') === $primary) {
                $item['disable_column'] = false;
            }
        }
        unset($item);

        usort($result, function ($a, $b) use ($primary) {
            if (($a['column_label'] ?? '') === $primary) {
                return -1;
            }
            if (($b['column_label'] ?? '') === $primary) {
                return 1;
            }
            return 0;
        });

        return $result;
    }
}
