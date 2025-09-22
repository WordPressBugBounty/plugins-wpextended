<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Manages user access control and capability checking
 */
class AccessManager
{
    /**
     * Check if user can access a menu item
     *
     * @param array $item Menu item
     * @param array $user_roles User roles
     * @param int $user_id User ID
     * @param array|null $parent_item Parent menu item (if this is a submenu item)
     * @return bool Can access
     */
    public function canUserAccessItem(array $item, array $user_roles, int $user_id, ?array $parent_item = null): bool
    {
        // Ensure access control fields exist
        $access_roles = isset($item['access_roles']) ? $item['access_roles'] : array();
        $access_users = isset($item['access_users']) ? $item['access_users'] : array();
        $user_access_mode = isset($item['user_access_mode']) ? $item['user_access_mode'] : 'grant';

        // Check parent capability override (similar to Admin Menu Editor Pro)
        if (!empty($parent_item)) {
            // Check if user can access parent first
            if (!$this->canUserAccessItem($parent_item, $user_roles, $user_id)) {
                return false;
            }
        }

        // First, check user-specific access if configured
        if (!empty($access_users) && is_array($access_users)) {
            $user_id_string = (string) $user_id;

            if (in_array($user_id_string, $access_users)) {
                // User is in the specific access list
                if ($user_access_mode === 'grant') {
                    // Grant mode: User has explicit access regardless of role
                    return true;
                } else {
                    // Deny mode: User is explicitly denied regardless of role
                    return false;
                }
            }
        }

        // If no roles are selected, check if this is an explicit empty setting or no configuration
        if (empty($access_roles)) {
            // Check if access_roles was explicitly set (exists in the item array)
            // vs. not being set at all (using capability fallback)
            if (array_key_exists('access_roles', $item)) {
                // Explicitly set to empty - user wants to hide this menu item
                return false;
            } else {
                // Not explicitly set - use capability fallback
                $capability = isset($item['capability']) ? $item['capability'] : 'manage_options';
                // Check if current user has the required capability
                if (current_user_can($capability)) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        // Check role-based access
        if (!empty($access_roles) && is_array($access_roles)) {
            foreach ($user_roles as $user_role) {
                if (in_array($user_role, $access_roles)) {
                    return true;
                }
            }
        }

        // If we have restrictions but user doesn't meet them, deny access
        return false;
    }

    /**
     * Get user roles choices
     *
     * @return array User roles choices
     */
    public function getUserRolesChoices(): array
    {
        $wp_roles = wp_roles();
        $choices = [];

        if ($wp_roles) {
            foreach ($wp_roles->roles as $role_key => $role_data) {
                $choices[$role_key] = $role_data['name'] ?? $role_key;
            }
        }

        return $choices;
    }

    /**
     * Get user choices
     *
     * @return array User choices
     */
    public function getUserChoices(): array
    {
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));

        $choices = array();
        foreach ($users as $user) {
            $choices[$user->ID] = $user->display_name . ' (' . $user->user_email . ')';
        }

        return $choices;
    }

    /**
     * Get menu item capability
     *
     * @param string $menu_slug Menu slug
     * @return string Capability
     */
    public function getMenuItemCapability(string $menu_slug): string
    {
        global $menu;

        if ($menu) {
            foreach ($menu as $menu_item) {
                if (!empty($menu_item[2]) && $menu_item[2] === $menu_slug) {
                    return $menu_item[1] ?? 'manage_options';
                }
            }
        }

        return 'manage_options';
    }

    /**
     * Get submenu item capability
     *
     * @param string $menu_slug Menu slug
     * @param string $parent_slug Parent slug
     * @return string Capability
     */
    public function getSubmenuItemCapability(string $menu_slug, string $parent_slug = ''): string
    {
        global $submenu;

        if ($submenu && isset($submenu[$parent_slug])) {
            foreach ($submenu[$parent_slug] as $submenu_item) {
                if (!empty($submenu_item[2]) && $submenu_item[2] === $menu_slug) {
                    return $submenu_item[1] ?? 'manage_options';
                }
            }
        }

        return 'manage_options';
    }

    /**
     * Get roles for capability
     *
     * @param string $capability Capability
     * @return array Roles
     */
    public function getRolesForCapability(string $capability): array
    {
        $wp_roles = wp_roles();
        $roles = [];

        if ($wp_roles) {
            foreach ($wp_roles->roles as $role_key => $role_data) {
                if (isset($role_data['capabilities'][$capability]) && $role_data['capabilities'][$capability]) {
                    $roles[] = $role_key;
                }
            }
        }

        return $roles;
    }
}
