<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Factory for creating and normalizing menu items
 */
class MenuItemFactory
{
    /**
     * Create a menu item with proper structure
     *
     * @param string $type Item type
     * @param string $title Item title
     * @param string $menu_slug Menu slug
     * @param string $default_title Default title
     * @param string $capability Required capability
     * @param array $roles Roles with capability
     * @return array Menu item
     */
    public function createMenuItem(string $type, string $title, string $menu_slug, string $default_title, string $capability, array $roles): array
    {
        // Ensure titles are never null and always strings
        $title = $title ?? '';
        $default_title = $default_title ?? '';

        // Convert to string if not already
        $title = (string) $title;
        $default_title = (string) $default_title;

        // Fix separator titles
        if ($type === 'separator' || empty($title)) {
            $title = '-- Separator --';
            $default_title = '-- Separator --';
        }

        return array(
            'type' => $type,
            'title' => $title,
            'default_title' => $default_title,
            'menu_slug' => $menu_slug,
            'capability' => $capability,
            'access_roles' => is_array($roles) ? $roles : array(),
            'access_users' => array(),
            'user_access_mode' => 'grant',
            'children' => array(),
        );
    }

    /**
     * Normalize menu item data
     *
     * @param array $item Menu item data
     * @return array Normalized menu item
     */
    public function normalizeMenuItem(array $item): array
    {
        // Ensure required fields
        $item['type'] = $item['type'] ?? 'item';
        $item['title'] = $item['title'] ?? '';
        $item['default_title'] = $item['default_title'] ?? $item['title'];
        $item['capability'] = $item['capability'] ?? 'manage_options';
        $item['access_roles'] = isset($item['access_roles']) && is_array($item['access_roles']) ? $item['access_roles'] : array();
        $item['access_users'] = isset($item['access_users']) && is_array($item['access_users']) ? $item['access_users'] : array();
        $item['user_access_mode'] = $item['user_access_mode'] ?? 'grant';
        $item['children'] = is_array($item['children'] ?? array()) ? $item['children'] : array();

        // Ensure titles are never null and always strings
        $item['title'] = (string) ($item['title'] ?? '');
        $item['default_title'] = (string) ($item['default_title'] ?? '');

        return $item;
    }

    /**
     * Validate menu item has required fields
     *
     * @param array $item Menu item
     * @return bool Is valid
     */
    public function isValidMenuItem(array $item): bool
    {
        $normalized = $this->normalizeMenuItem($item);
        // Allow empty custom title (means use WordPress default). Only require type and menu_slug.
        return !empty($normalized['type']) && !empty($normalized['menu_slug']);
    }
}
