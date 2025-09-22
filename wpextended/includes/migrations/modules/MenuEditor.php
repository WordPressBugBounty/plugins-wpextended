<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Menu Editor settings to new consolidated module settings.
 *
 * Legacy options:
 * - wpextended_menu-editor (may contain old structure)
 *
 * New settings (stored via Utils::updateSettings('menu-editor', ...)):
 * - menu_items: array of menu item objects with standardized structure
 */
class MenuEditor
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
        $existing = Utils::getSettings('menu-editor');
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $settings = array();

        // Check for legacy menu editor option
        $legacy_option = get_option('wpextended_menu-editor');
        if (is_array($legacy_option) && !empty($legacy_option)) {
            $settings = $this->transformLegacy($legacy_option);
        }

        // If no legacy data found, try to migrate from old structure
        if (empty($settings)) {
            $settings = $this->migrateFromOldStructure();
        }

        // If still no data found, initialize with empty structure
        if (empty($settings)) {
            $settings = array(
                'menu_items' => array()
            );
        }

        if (!empty($settings)) {
            Utils::updateSettings('menu-editor', $settings);
        }
    }

    /**
     * Cleanup legacy options after successful migration.
     */
    public function cleanup(): void
    {
        // Remove legacy option if it exists
        delete_option('wpextended_menu-editor');

        // Remove old structure options
        delete_option('wpext-user-tidy-nav');
        delete_option('wpext-user-tidy-nav-store-user-id');
        delete_option('wpext-user-tidy-nav-store-user-role');
        delete_option('wpext_seprate_superadmin');
        delete_option('wpext-hide-menu-main');

        // Remove dynamic role/user options
        $this->cleanupDynamicOptions();
    }

    /**
     * Transform legacy array into new menu items array shape.
     *
     * Legacy item example (if any):
     *   {
     *     title: string,
     *     menu_slug: string,
     *     capability: string,
     *     access_roles: array,
     *     access_users: array,
     *     user_access_mode: string,
     *     children: array
     *   }
     */
    private function transformLegacy(array $legacy): array
    {
        $result = array();

        // If legacy has menu_items key, use it directly
        if (isset($legacy['menu_items']) && is_array($legacy['menu_items'])) {
            $result['menu_items'] = $this->transformMenuItems($legacy['menu_items']);
        } else {
            // If legacy is the menu_items array itself
            $result['menu_items'] = $this->transformMenuItems($legacy);
        }

        return $result;
    }

    /**
     * Transform menu items array to ensure proper structure.
     */
    private function transformMenuItems(array $items): array
    {
        $result = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $transformed_item = array(
                'type' => $this->getItemType($item),
                'title' => $this->getStringValue($item, 'title', ''),
                'default_title' => $this->getStringValue($item, 'default_title', $this->getStringValue($item, 'title', '')),
                'menu_slug' => $this->getStringValue($item, 'menu_slug', ''),
                'capability' => $this->getStringValue($item, 'capability', 'read'),
                'access_roles' => $this->getArrayValue($item, 'access_roles', array()),
                'access_users' => $this->getArrayValue($item, 'access_users', array()),
                'user_access_mode' => $this->getStringValue($item, 'user_access_mode', 'grant'),
                'children' => array()
            );

            // Transform children if they exist
            if (isset($item['children']) && is_array($item['children'])) {
                $transformed_item['children'] = $this->transformMenuItems($item['children']);
            }

            $result[] = $transformed_item;
        }

        return $result;
    }

    /**
     * Determine item type based on content.
     */
    private function getItemType(array $item): string
    {
        $menu_slug = $this->getStringValue($item, 'menu_slug', '');

        // Check if it's a separator
        if (
            strpos($menu_slug, 'separator-') === 0 ||
            $this->getStringValue($item, 'title', '') === '-- Separator --'
        ) {
            return 'separator';
        }

        return 'item';
    }

    /**
     * Safely get string value from array.
     */
    private function getStringValue(array $item, string $key, string $default = ''): string
    {
        if (!isset($item[$key])) {
            return $default;
        }

        $value = $item[$key];
        return is_string($value) ? $value : $default;
    }

    /**
     * Safely get array value from array.
     */
    private function getArrayValue(array $item, string $key, array $default = array()): array
    {
        if (!isset($item[$key])) {
            return $default;
        }

        $value = $item[$key];
        return is_array($value) ? $value : $default;
    }

    /**
     * Migrate from the old data structure to new format.
     */
    private function migrateFromOldStructure(): array
    {
        $menu_items = array();

        // Get all users that have custom menu configurations
        $users = $this->getConfiguredUsers();

        // Process user-specific configurations
        foreach ($users as $user) {
            $user_items = $this->migrateUserConfiguration($user);
            $menu_items = $this->mergeMenuItems($menu_items, $user_items, $user);
        }

        return array(
            'menu_items' => $menu_items
        );
    }

    /**
     * Get all users that have custom menu configurations.
     */
    private function getConfiguredUsers(): array
    {
        $users = array();

        // Check for user ID store
        $user_id = get_option('wpext-user-tidy-nav-store-user-id');
        if ($user_id) {
            $users[] = $user_id;
        }

        // Get users from tidy nav data
        $tidy_nav = get_option('wpext-user-tidy-nav');
        if (is_array($tidy_nav)) {
            foreach (array_keys($tidy_nav) as $username) {
                if (!in_array($username, $users)) {
                    $users[] = $username;
                }
            }
        }

        return $users;
    }

    /**
     * Migrate user configuration to new format.
     *
     * Old format: {"johndoe":["fluent_forms","edit.php?post_type=movie","themes.php"]}
     * New format: Each menu item gets access_users with the user, and user_access_mode = 'deny'
     */
    private function migrateUserConfiguration(string $user): array
    {
        $menu_items = array();

        // Get user-specific menu items from tidy nav
        $tidy_nav = get_option('wpext-user-tidy-nav');
        if (is_array($tidy_nav) && isset($tidy_nav[$user])) {
            $user_menu_slugs = $tidy_nav[$user];

            foreach ($user_menu_slugs as $menu_slug) {
                // Get the user ID from username
                $user_obj = get_user_by('login', $user);
                $user_id = $user_obj ? (string) $user_obj->ID : $user;

                $menu_item = array(
                    'type' => 'item',
                    'title' => '',
                    'default_title' => '',
                    'menu_slug' => $menu_slug,
                    'capability' => $this->getDefaultCapability($menu_slug),
                    'access_roles' => array(),
                    'access_users' => array($user_id),
                    'user_access_mode' => 'deny', // Old system was hiding items, so deny access
                    'children' => array()
                );
                $menu_items[] = $menu_item;
            }
        }

        return $menu_items;
    }

    /**
     * Get default capability for a menu slug.
     */
    private function getDefaultCapability(string $menu_slug): string
    {
        // Common menu slug to capability mappings
        $capability_map = array(
            'themes.php' => 'switch_themes',
            'plugins.php' => 'activate_plugins',
            'users.php' => 'list_users',
            'tools.php' => 'edit_posts',
            'options-general.php' => 'manage_options',
            'edit.php' => 'edit_posts',
            'upload.php' => 'upload_files',
            'edit-comments.php' => 'moderate_comments',
            'edit.php?post_type=page' => 'edit_pages',
            'profile.php' => 'read',
        );

        // Check for post type specific capabilities
        if (strpos($menu_slug, 'edit.php?post_type=') === 0) {
            $post_type = str_replace('edit.php?post_type=', '', $menu_slug);
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj && isset($post_type_obj->cap->edit_posts)) {
                return $post_type_obj->cap->edit_posts;
            }
        }

        // Check for plugin-specific menu items
        if (strpos($menu_slug, 'admin.php?page=') === 0) {
            return 'manage_options'; // Default for plugin pages
        }

        return $capability_map[$menu_slug] ?? 'read';
    }

    /**
     * Merge menu items, handling conflicts between users.
     */
    private function mergeMenuItems(array $existing, array $new, string $user): array
    {
        foreach ($new as $new_item) {
            $found = false;

            // Check if item already exists
            foreach ($existing as &$existing_item) {
                if ($existing_item['menu_slug'] === $new_item['menu_slug']) {
                    // Merge access settings - add user to access_users if not already there
                    if (!in_array($user, $existing_item['access_users'])) {
                        $existing_item['access_users'][] = $user;
                    }
                    $found = true;
                    break;
                }
            }

            // If not found, add new item
            if (!$found) {
                $existing[] = $new_item;
            }
        }

        return $existing;
    }

    /**
     * Clean up dynamic options (user specific).
     */
    private function cleanupDynamicOptions(): void
    {
        global $wpdb;

        // Remove user-specific options
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpext_user_%_user_label'"
        );

        // Remove role-specific options if they exist
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpext_user_role_selected_order%'"
        );
    }
}
