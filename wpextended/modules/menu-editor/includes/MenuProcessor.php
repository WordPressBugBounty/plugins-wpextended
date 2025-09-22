<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Handles menu processing and submenu management
 */
class MenuProcessor
{
    private AccessManager $accessManager;
    private MenuItemFinder $itemFinder;

    public function __construct(AccessManager $accessManager, MenuItemFinder $itemFinder)
    {
        $this->accessManager = $accessManager;
        $this->itemFinder = $itemFinder;
    }

    /**
     * Apply menu changes to WordPress admin menu
     * Enhanced to handle full URLs as slugs for proper identification
     *
     * @param array $menu_items Menu items from settings
     * @return void
     */
    public function applyMenuChanges(array $menu_items): void
    {
        global $menu, $submenu;


        // Ensure Dashboard (index.php) is always present
        // This is a critical WordPress admin menu item that should never be missing
        $dashboard_exists = false;
        if (is_array($menu)) {
            foreach ($menu as $menu_item) {
                if (!empty($menu_item[2]) && $menu_item[2] === 'index.php') {
                    $dashboard_exists = true;
                    break;
                }
            }
        }

        if (!$dashboard_exists) {
            // Add the dashboard menu item if it's missing
            // This ensures the dashboard is always available
            add_menu_page(
                __('Dashboard'),
                __('Dashboard'),
                'read',
                'index.php',
                '',
                'dashicons-dashboard',
                2
            );

        }

        // Ensure Profile menu exists for users without list_users capability
        // Do this here so menu shaping stays centralized and runs even when settings are empty
        if (!current_user_can('list_users')) {
            $profile_exists = false;
            if (is_array($menu)) {
                foreach ($menu as $menu_item) {
                    if (!empty($menu_item[2]) && $menu_item[2] === 'profile.php') {
                        $profile_exists = true;
                        break;
                    }
                }
            }

            if (!$profile_exists) {
                // If user saved a custom title for Profile, respect it
                $menu_title = __('Profile');
                $page_title = $menu_title;
                if (!empty($menu_items)) {
                    $profile_item = $this->itemFinder->findMenuItemBySlug($menu_items, 'profile.php');
                    if (!empty($profile_item)) {
                        $menu_title = (!empty($profile_item['title'])) ? $profile_item['title'] : $menu_title;
                        $page_title = (!empty($profile_item['default_title'])) ? $profile_item['default_title'] : $menu_title;
                    }
                }

                // Add as top-level menu with read capability via WordPress API to ensure proper structure
                add_menu_page(
                    $page_title,
                    $menu_title,
                    'read',
                    'profile.php',
                    '',
                    'dashicons-admin-users',
                    70
                );
            }
        }

        if (empty($menu_items)) {
            return;
        }

        // Defensive: if Disable Blog is enabled, prune Posts-related orphans from incoming $menu_items
        if ($this->isDisableBlogEnabled()) {
            $blockedSlugs = array(
                'edit.php',
                'post-new.php',
                'edit-tags.php?taxonomy=category',
                'edit-tags.php?taxonomy=post_tag',
            );

            $pruneFn = function ($items) use (&$pruneFn, $blockedSlugs) {
                $filtered = array();
                foreach ($items as $item) {
                    $slug = isset($item['menu_slug']) ? $item['menu_slug'] : '';
                    // Skip any blocked slug
                    if ($slug && in_array($slug, $blockedSlugs, true)) {
                        continue;
                    }
                    // Recurse into children if present
                    if (!empty($item['children']) && is_array($item['children'])) {
                        $item['children'] = $pruneFn($item['children']);
                    }
                    $filtered[] = $item;
                }
                return $filtered;
            };

            $menu_items = $pruneFn($menu_items);
        }

        // If Disable Blog module is enabled, proactively remove its targeted items
        if ($this->isDisableBlogEnabled()) {
            // Remove Posts top-level menu
            foreach ($menu as $key => $menu_item) {
                if (!empty($menu_item[2]) && $menu_item[2] === 'edit.php') {
                    unset($menu[$key]);
                }
            }

            // Remove specific blog-related submenus from Settings and Tools
            if (isset($submenu['options-general.php']) && is_array($submenu['options-general.php'])) {
                $submenu['options-general.php'] = array_values(array_filter($submenu['options-general.php'], function ($item) {
                    if (empty($item[2])) {
                        return true;
                    }
                    if (in_array($item[2], array('options-writing.php', 'options-discussion.php'), true)) {
                        return false;
                    }
                    return !in_array($item[2], array('options-writing.php', 'options-discussion.php'), true);
                }));
            }

            if (isset($submenu['tools.php']) && is_array($submenu['tools.php'])) {
                $submenu['tools.php'] = array_values(array_filter($submenu['tools.php'], function ($item) {
                    if (empty($item[2])) {
                        return true;
                    }
                    if ($item[2] === 'tools.php') {
                        return false;
                    }
                    return $item[2] !== 'tools.php';
                }));
            }
        }

        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        $user_id = $current_user->ID;

        // Process main menu items
        foreach ($menu as $key => $menu_item) {
            if (empty($menu_item[2])) {
                continue;
            }

            $menu_slug = $menu_item[2];

            if ($this->isDisableBlogEnabled() && $menu_slug === 'edit.php') {
                // Defensive: if it somehow survived earlier pruning, drop it now
                unset($menu[$key]);
                continue;
            }

            // Note: We don't filter out WP Extended modules in the actual menu rendering
            // They should appear normally in the admin menu
            // Filtering only happens in the settings interface

            // Try to find by original slug first, then try full URL
            $custom_item = $this->itemFinder->findMenuItemBySlug($menu_items, $menu_slug);
            if (!$custom_item) {
                $full_url = $this->getFullMenuUrl($menu_slug);
                $custom_item = $this->itemFinder->findMenuItemBySlug($menu_items, $full_url);
            }

            if (!$custom_item) {
                // Special case: Always allow Profile to be displayed for users without list_users
                // For these users, WordPress adds Profile as a top-level menu
                if ($menu_slug === 'profile.php' && !current_user_can('list_users')) {
                    // Create a default configuration for Profile
                    // Get all roles to make it visible to everyone
                    $all_roles = array_keys(wp_roles()->roles);
                    $custom_item = array(
                        'type' => 'item',
                        'menu_slug' => 'profile.php',
                        'title' => '',  // Use WordPress default title
                        'capability' => 'read',
                        'access_roles' => $all_roles // Visible to all roles
                    );
                } else {
                    // If no custom configuration exists, create a default one to preserve the item
                    // This ensures all WordPress menu items are preserved unless explicitly hidden
                    // IMPORTANT: Don't set access_roles at all - let AccessManager use capability fallback
                    $custom_item = array(
                        'type' => 'item',
                        'menu_slug' => $menu_slug,
                        'title' => '',  // Use WordPress default title
                        'capability' => $menu_item[1] ?? 'manage_options'
                        // Note: access_roles is intentionally NOT set - this allows AccessManager to use capability fallback
                    );
                }
            }

            // Handle separators
            if ($custom_item['type'] === 'separator') {
                // Check if current user can see this separator
                if (!$this->accessManager->canUserAccessItem($custom_item, $user_roles, $user_id)) {
                    unset($menu[$key]);
                    continue;
                }
            } else {
                // Handle regular menu items
                // Special case: if Profile exists but saved config left access_roles empty, allow for all roles
                if ($menu_slug === 'profile.php' && !current_user_can('list_users')) {
                    if (empty($custom_item['access_roles']) || !is_array($custom_item['access_roles'])) {
                        $custom_item['access_roles'] = array_keys(wp_roles()->roles);
                    }
                }
                // Check if current user can see this menu
                // Special case: Dashboard and Profile should always be accessible to logged-in users
                if ($menu_slug === 'index.php' || $menu_slug === 'profile.php') {
                    // Dashboard and Profile are always accessible to logged-in users
                    // No debug logging
                } elseif (!$this->accessManager->canUserAccessItem($custom_item, $user_roles, $user_id)) {
                    // No debug logging
                    unset($menu[$key]);
                    continue;
                }

                // Apply custom title if set
                if (!empty($custom_item['title'])) {
                    $menu[$key][0] = $custom_item['title'];
                }
            }
        }

        // Process submenus strictly based on saved configuration
        $this->processSubmenuItems($menu_items, $user_roles, $user_id);

        // Special handling for dashboard and home - ensure they are always accessible and have submenu items
        $this->ensureDashboardAndHomeAccess();

        // Reorder menu based on saved configuration
        $this->reorderMenuFromSavedConfig($menu_items);
    }

    /**
     * Process submenu items recursively and rebuild submenu order/titles
     * Enhanced to handle full URLs as slugs
     *
     * @param array $menu_items Menu items
     * @param array $user_roles User roles
     * @param int $user_id User ID
     * @param int $depth Current depth
     * @return void
     */
    public function processSubmenuItems(array $menu_items, array $user_roles, int $user_id, int $depth = 0): void
    {
        global $submenu;

        // Respect max_depth setting (1 level of nesting)
        $max_depth = 1;
        if ($depth >= $max_depth) {
            return;
        }

        foreach ($menu_items as $menu_item) {
            if (empty($menu_item['menu_slug'])) {
                continue;
            }

            $parent_slug = $menu_item['menu_slug'];
            // Resolve to the actual existing parent key in $submenu to avoid mismatches
            $parent_key = $this->resolveSubmenuParentKey($parent_slug);
            $new_submenu = array();

            // If this menu item has custom children configuration, process them
            if (!empty($menu_item['children'])) {
                foreach ($menu_item['children'] as $child) {
                    if (empty($child['menu_slug'])) {
                        continue;
                    }

                    // Note: We don't filter out WP Extended submenu items in the actual menu rendering
                    // They should appear normally in the admin menu
                    // Filtering only happens in the settings interface

                    // Respect Disable Blog removals for submenu rendering as well
                    if ($this->isDisableBlogEnabled()) {
                        $blocked = $this->getDisableBlogBlockedSubmenuSlugsForParent($parent_slug);
                        if (in_array($child['menu_slug'], $blocked, true)) {
                            continue;
                        }
                    }

                    // Check if current user can see this submenu (pass parent for capability override)
                    if (!$this->accessManager->canUserAccessItem($child, $user_roles, $user_id, $menu_item)) {
                        continue;
                    }

                    // Handle separators in submenus
                    if ($child['type'] === 'separator') {
                        // Create a special submenu item that looks like a separator
                        $separator_slug = 'separator-' . uniqid();
                        $new_submenu_item = array(
                            '---', // Title (will be styled as separator)
                            'read', // Capability (lowest level)
                            $separator_slug, // Unique slug
                            '---', // Page title
                            'separator', // Classes
                        );
                        $new_submenu[] = $new_submenu_item;
                        continue;
                    }

                    // Find the original submenu item (for URL/capability)
                    // Try to find by full URL first, then fallback to original slug
                    $original = null;
                    $child_slug = $child['menu_slug'];

                    if (isset($submenu[$parent_key])) {
                        foreach ($submenu[$parent_key] as $submenu_item) {
                            if (!empty($submenu_item[2])) {
                                // Direct match first
                                if ($submenu_item[2] === $child_slug) {
                                    $original = $submenu_item;
                                    break;
                                }

                                // URL matching for complex URLs
                                if ($this->itemFinder->urlsMatch($submenu_item[2], $child_slug)) {
                                    $original = $submenu_item;
                                    break;
                                }
                            }
                        }
                    }

                    // Build submenu item, using saved title and settings, but fallback to original for URL/capability
                    $submenu_title = !empty($child['title']) ? $child['title'] : ($original[0] ?? $child['menu_slug']);
                    $submenu_capability = $child['capability'] ?? ($original[1] ?? 'manage_options');

                    // Use the original URL if available, otherwise use the stored slug
                    $submenu_url = $original[2] ?? $child_slug;

                    // Build the complete submenu array with all required elements
                    $new_submenu_item = array(
                        $submenu_title, // [0] Title (can be HTML)
                        $submenu_capability, // [1] Capability
                        $submenu_url, // [2] Slug/URL
                    );

                    // Add optional elements if they exist in the original
                    if ($original && isset($original[3])) {
                        $new_submenu_item[3] = $original[3]; // Page title
                    }
                    if ($original && isset($original[4])) {
                        $new_submenu_item[4] = $original[4]; // Classes
                    }
                    if ($original && isset($original[5])) {
                        $new_submenu_item[5] = $original[5]; // Hook name
                    }

                    $new_submenu[] = $new_submenu_item;
                }

                // Replace the submenu for this parent with the new ordered/customized array
                // Always replace, even if empty, to ensure restricted items are hidden
                $submenu[$parent_key] = $new_submenu;

                // Process nested children at the next depth level
                // Only process if we haven't reached max depth
                if ($depth < $max_depth) {
                    foreach ($menu_item['children'] as $child) {
                        if (!empty($child['children'])) {
                            $this->processSubmenuItems(array($child), $user_roles, $user_id, $depth + 1);
                        }
                    }
                }
            } else {
                // No custom children configuration - preserve original WordPress submenu items
                // This ensures that menu items like dashboard keep their original submenu items
                if (isset($submenu[$parent_key]) && is_array($submenu[$parent_key])) {
                    // Keep the original submenu items as they are
                    // No processing needed - just preserve them
                    // No debug logging
                }
            }
        }
    }

    /**
     * Resolve a provided parent slug to the actual key used in the global $submenu array.
     * This ensures submenu items attach to the correct top-level regardless of saved slug format or order.
     */
    protected function resolveSubmenuParentKey(string $parent_slug): string
    {
        global $submenu;
        // Exact match fast-path
        if (isset($submenu[$parent_slug])) {
            return $parent_slug;
        }
        // Try equivalence across existing keys
        if (is_array($submenu)) {
            foreach (array_keys($submenu) as $key) {
                // Exact match covered above; use URL equivalence helper
                if ($this->itemFinder->urlsMatch($key, $parent_slug) || $this->itemFinder->urlsMatch($parent_slug, $key)) {
                    return $key;
                }
                // Additionally, map admin.php?page=slug ↔ slug
                if (strpos($key, 'admin.php?page=') === 0) {
                    $page = substr($key, 14);
                    if ($page === $parent_slug) {
                        return $key;
                    }
                } elseif (strpos($parent_slug, 'admin.php?page=') === 0) {
                    $page = substr($parent_slug, 14);
                    if ($page === $key) {
                        return $key;
                    }
                }
            }
        }
        // Fallback to the provided slug
        return $parent_slug;
    }

    /**
     * Ensure dashboard and home menu items are always accessible and have their submenu items
     * These menu items should be accessible to all users regardless of role settings
     */
    private function ensureDashboardAndHomeAccess(): void
    {
        global $menu, $submenu;

        // List of menu items that should always be accessible to all users
        $always_accessible = array('index.php', 'profile.php');

        foreach ($always_accessible as $menu_slug) {
            // Check if the menu item exists in the main menu
            $menu_exists = false;
            foreach ($menu as $menu_item) {
                if (!empty($menu_item[2]) && $menu_item[2] === $menu_slug) {
                    $menu_exists = true;
                    break;
                }
            }
        }

        // Ensure current user has the 'read' capability for dashboard access
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            if (!$current_user->has_cap('read')) {
                $current_user->add_cap('read');
            }
        }
    }

    /**
     * Reorder menu based on saved configuration
     * Enhanced to handle full URLs as slugs
     *
     * @param array $menu_items Menu items
     * @return void
     */
    public function reorderMenu(array $menu_items): void
    {
        global $menu;

        $new_menu = array();
        $position = 5; // Start at position 5

        // Create a map of original menu positions with multiple lookup keys
        $original_positions = array();
        $original_lookup = array(); // For reverse lookup by various URL formats

        foreach ($menu as $key => $menu_item) {
            if (!empty($menu_item[2])) {
                $slug = $menu_item[2];
                $original_positions[$slug] = $key;

                // Create multiple lookup keys for the same menu item
                $original_lookup[$slug] = $key;

                // Add lookup for admin.php?page= format
                if (strpos($slug, 'admin.php?page=') === 0) {
                    $page = substr($slug, 14);
                    $original_lookup[$page] = $key;
                    $original_lookup['admin.php?page=' . $page] = $key;
                } else {
                    // Add lookup for admin.php?page= format
                    $original_lookup['admin.php?page=' . $slug] = $key;
                }

                // Add lookup for full admin URL
                $original_lookup[admin_url($slug)] = $key;
            }
        }

        // First pass: Add items in the order they appear in settings
        foreach ($menu_items as $custom_item) {
            // Handle separators explicitly: inject a visual separator row
            if (!empty($custom_item['type']) && $custom_item['type'] === 'separator') {
                $separator_slug = !empty($custom_item['menu_slug']) ? $custom_item['menu_slug'] : ('separator-' . uniqid());
                $new_menu[$position] = array(
                    '',          // [0] Menu title (empty for separator)
                    'read',      // [1] Capability
                    $separator_slug, // [2] Slug
                    '',          // [3] Page title
                    'wp-menu-separator' // [4] CSS class for separators
                );
                $position += 5;
                continue;
            }

            if (empty($custom_item['menu_slug'])) {
                continue;
            }

            $menu_slug = $custom_item['menu_slug'];

            // Note: We don't filter out WP Extended modules in the actual menu rendering
            // They should appear normally in the admin menu
            // Filtering only happens in the settings interface

            $found_key = null;

            // Try multiple lookup strategies
            if (isset($original_lookup[$menu_slug])) {
                $found_key = $original_lookup[$menu_slug];
            } else {
                // Try URL matching for complex URLs
                foreach ($original_lookup as $original_slug => $key) {
                    if ($this->itemFinder->urlsMatch($original_slug, $menu_slug)) {
                        $found_key = $key;
                        break;
                    }
                }
            }

            // Find the original menu item
            if ($found_key !== null && isset($menu[$found_key])) {
                $new_menu[$position] = $menu[$found_key];
                unset($menu[$found_key]); // Remove from original array
                $position += 5;
            } 
        }

        // Build a set of slugs already used (including children) to avoid duplicates
        $used_slugs = array();
        foreach ($new_menu as $nm) {
            if (!empty($nm[2])) {
                $used_slugs[] = $nm[2];
            }
        }
        $collect_child_slugs = function ($items) use (&$collect_child_slugs) {
            $slugs = array();
            foreach ($items as $it) {
                if (!empty($it['menu_slug'])) {
                    $slugs[] = $it['menu_slug'];
                }
                if (!empty($it['children']) && is_array($it['children'])) {
                    $slugs = array_merge($slugs, $collect_child_slugs($it['children']));
                }
            }
            return $slugs;
        };
        $used_slugs = array_merge($used_slugs, $collect_child_slugs($menu_items));

        // Normalize and uniquify used slugs
        $used_slugs = array_values(array_unique($used_slugs));

        // Helper to test URL equivalence against any used slug
        $is_slug_used = function ($candidate) use ($used_slugs) {
            foreach ($used_slugs as $used) {
                // Exact match fast path
                if ($candidate === $used) {
                    return true;
                }
                // URL equivalence via itemFinder comparison
                if ($this->itemFinder->urlsMatch($used, $candidate)) {
                    return true;
                }
            }
            return false;
        };

        // Second pass: Add any remaining items that weren't in settings, skipping duplicates
        foreach ($menu as $key => $menu_item) {
            if (empty($menu_item[2])) {
                continue;
            }
            $slug = $menu_item[2];

            if ($is_slug_used($slug)) {
                continue;
            }

            $new_menu[$position] = $menu_item;
            $position += 5;
        }

        // Ensure Profile appears as a top-level menu before Tools when the user lacks list_users
        if (!current_user_can('list_users')) {
            $profile_key = null;
            $tools_key = null;
            $separator_before_tools_key = null;
            // Determine existing keys
            foreach ($new_menu as $k => $m) {
                if (!empty($m[2]) && $m[2] === 'profile.php') {
                    $profile_key = $k;
                }
                if (!empty($m[2]) && $m[2] === 'tools.php' && ($tools_key === null)) {
                    $tools_key = $k;
                }
            }
            // Find a separator immediately before Tools
            if ($tools_key !== null) {
                $keys = array_keys($new_menu);
                sort($keys, SORT_NUMERIC);
                $prev = null;
                foreach ($keys as $k) {
                    if ($k == $tools_key) {
                        if (($prev !== null) && isset($new_menu[$prev]) && isset($new_menu[$prev][4]) && ($new_menu[$prev][4] === 'wp-menu-separator')) {
                            $separator_before_tools_key = $prev;
                        }
                        break;
                    }
                    $prev = $k;
                }
            }

            // Labels for Profile
            $menu_title = __('Profile');
            $page_title = $menu_title;
            $profile_item = $this->itemFinder->findMenuItemBySlug($menu_items, 'profile.php');
            if (!empty($profile_item)) {
                $menu_title = (!empty($profile_item['title'])) ? $profile_item['title'] : $menu_title;
                $page_title = (!empty($profile_item['default_title'])) ? $profile_item['default_title'] : $menu_title;
            }

            // Compute insert key
            $insert_key = null;
            if ($tools_key !== null) {
                // Prefer replacing separator before Tools
                if ($separator_before_tools_key !== null) {
                    $insert_key = $separator_before_tools_key;
                    unset($new_menu[$separator_before_tools_key]);
                } else {
                    $insert_key = $tools_key - 1;
                    while (isset($new_menu[$insert_key]) && $insert_key > 0) {
                        $insert_key--;
                    }
                }
            } else {
                // No Tools present; choose a reasonable slot near Users area
                $insert_key = 69;
                while (isset($new_menu[$insert_key]) && $insert_key > 0) {
                    $insert_key--;
                }
            }

            // Move existing Profile or insert a new one
            if ($profile_key !== null) {
                if (($tools_key !== null) && ($profile_key > $tools_key)) {
                    $profile_entry = $new_menu[$profile_key];
                    unset($new_menu[$profile_key]);
                    $new_menu[$insert_key] = $profile_entry;
                    ksort($new_menu, SORT_NUMERIC);
                }
            } else {
                $new_menu[$insert_key] = array(
                    $menu_title,             // [0] Title
                    'read',                  // [1] Capability
                    'profile.php',            // [2] Slug
                    $page_title,             // [3] Page title
                    'menu-top',              // [4] Classes
                    'menu-users',            // [5] Hookname
                    'dashicons-admin-users'  // [6] Icon
                );
                ksort($new_menu, SORT_NUMERIC);
            }
        }

        // Replace the global menu array
        $menu = $new_menu;
    }

    /**
     * Reorder menu based on saved configuration - new approach
     * Render all menu items directly from saved configuration, add missing items at the end
     *
     * @param array $menu_items Menu items
     * @return void
     */
    public function reorderMenuFromSavedConfig(array $menu_items): void
    {
        global $menu;

        if (empty($menu_items) || !is_array($menu_items)) {
            return;
        }

        $new_menu = array();
        $position = 5; // Start at position 5
        $used_slugs = array(); // Track which slugs we've already used

        // Create a lookup table for original WordPress menu items
        $original_lookup = array();
        foreach ($menu as $key => $menu_item) {
            if (!empty($menu_item[2])) {
                $slug = $menu_item[2];
                $original_lookup[$slug] = $menu_item;

                // Add lookup for admin.php?page= format
                if (strpos($slug, 'admin.php?page=') === 0) {
                    $page = substr($slug, 14);
                    $original_lookup[$page] = $menu_item;
                } else {
                    // Add lookup for admin.php?page= format
                    $original_lookup['admin.php?page=' . $slug] = $menu_item;
                }
            }
        }

        // First pass: Render all menu items from saved configuration in exact order
        foreach ($menu_items as $custom_item) {
            // Handle separators
            if (!empty($custom_item['type']) && $custom_item['type'] === 'separator') {
                $separator_slug = !empty($custom_item['menu_slug']) ? $custom_item['menu_slug'] : ('separator-' . uniqid());
                $new_menu[$position] = array(
                    '',          // [0] Menu title (empty for separator)
                    'read',      // [1] Capability
                    $separator_slug, // [2] Slug
                    '',          // [3] Page title
                    'wp-menu-separator' // [4] CSS class for separators
                );
                $position += 5;
                continue;
            }

            if (empty($custom_item['menu_slug'])) {
                continue;
            }

            $menu_slug = $custom_item['menu_slug'];
            $original_item = null;

            // Find the original WordPress menu item
            if (isset($original_lookup[$menu_slug])) {
                $original_item = $original_lookup[$menu_slug];
            } else {
                // Try URL matching for complex URLs
                foreach ($original_lookup as $original_slug => $item) {
                    if ($this->itemFinder->urlsMatch($original_slug, $menu_slug)) {
                        $original_item = $item;
                        break;
                    }
                }
            }

            if ($original_item) {
                // Use the original WordPress menu item
                $new_menu[$position] = $original_item;

                // Track both the saved config slug and the original WordPress slug
                $used_slugs[] = $menu_slug;
                $used_slugs[] = $original_item[2]; // The actual WordPress slug
                $position += 5;

            }
        }

        // Second pass: Add any remaining WordPress menu items that weren't in the saved configuration
        foreach ($menu as $orig_item) {
            if (empty($orig_item[2])) {
                continue;
            }
            $slug = $orig_item[2];

            // Skip WordPress core separators and pseudo-separators
            if (strpos($slug, 'separator') === 0) {
                continue;
            }

            // Skip if we already used this item (exact or URL-equivalent)
            $already_used = in_array($slug, $used_slugs, true);
            if (!$already_used) {
                foreach ($used_slugs as $used_slug) {
                    if ($this->itemFinder->urlsMatch($used_slug, $slug)) {
                        $already_used = true;
                        break;
                    }
                }
            }

            if ($already_used) {
                continue;

            }

            $new_menu[$position] = $orig_item;
            $position += 5;

        }

        // Replace the global menu array
        $menu = $new_menu;

    }

    /**
     * Format menu URL based on slug
     * If slug has no extension, default to admin.php?page=[slug]
     * Handles moved menu items by converting relative URLs to absolute ones
     *
     * @param string $slug Menu slug
     * @param string $parent_slug Parent slug (for moved items)
     * @return string Formatted URL
     */
    protected function formatMenuUrl(string $slug, string $parent_slug = ''): string
    {
        // Don't format certain special pages
        $skip_formatting = array(
            'index.php', // Dashboard
            'upload.php', // Media
            'edit.php', // Posts
            'edit.php?post_type=page', // Pages
            'edit.php?post_type=post', // Posts
            'edit-tags.php', // Categories/Tags
            'users.php', // Users
            'options-general.php', // Settings
            'themes.php', // Appearance
            'plugins.php', // Plugins
            'tools.php', // Tools
        );

        // Skip if slug starts with wpextended-
        if (strpos($slug, 'wpextended-') === 0) {
            return $slug;
        }

        if (in_array($slug, $skip_formatting)) {
            return $slug;
        }

        // Handle moved menu items - convert relative URLs to absolute ones
        // This prevents problems with WordPress incorrectly converting URLs when items are moved
        if (strpos($slug, '://') === false && substr($slug, 0, 1) != '/' && strpos($slug, '?') !== false) {
            // Check if this is a relative URL that needs to be made absolute
            $itemFile = $this->removeQueryFrom($slug);
            $shouldMakeAbsolute = ($itemFile == 'index.php' && strpos($slug, '?') !== false);

            if ($shouldMakeAbsolute) {
                return admin_url($slug);
            }
        }

        // If slug has a file extension (like .php), use it as is
        if (strpos($slug, '.') !== false) {
            return $slug;
        }

        // If slug contains query parameters, don't format it
        if (strpos($slug, '?') !== false) {
            return $slug;
        }

        // Otherwise, format as admin.php?page=[slug]
        return 'admin.php?page=' . $slug;
    }

    /**
     * Remove query string from URL (helper for formatMenuUrl)
     *
     * @param string $url URL
     * @return string URL without query string
     */
    protected function removeQueryFrom(string $url): string
    {
        $queryPos = strpos($url, '?');
        if ($queryPos !== false) {
            return substr($url, 0, $queryPos);
        }
        return $url;
    }

    /**
     * Get the full URL for a menu slug, including query parameters.
     * This is useful for finding items that might be moved or have query parameters.
     *
     * @param string $slug The menu slug (which might be a full URL)
     * @return string The full URL including query parameters.
     */
    protected function getFullMenuUrl(string $slug): string
    {
        // If it's already a full URL, return it
        if (strpos($slug, '://') !== false) {
            return $slug;
        }

        // If it's a relative URL, make it absolute
        if (substr($slug, 0, 1) != '/' && strpos($slug, '?') !== false) {
            return admin_url($slug);
        }

        // Otherwise, return it as is, as it's likely a page slug
        return $slug;
    }

    /**
     * Check if Disable Blog module is enabled
     *
     * @return bool
     */
    protected function isDisableBlogEnabled(): bool
    {
        return \Wpextended\Includes\Modules::isModuleEnabled('disable-blog');
    }

    /**
     * Get blocked submenu slugs for a given parent when Disable Blog is enabled
     * Mirrors removeBlogSidebarMenu from the Disable Blog module
     *
     * @param string $parent_slug
     * @return array
     */
    protected function getDisableBlogBlockedSubmenuSlugsForParent(string $parent_slug): array
    {
        if ($parent_slug === 'options-general.php') {
            return array('options-writing.php', 'options-discussion.php');
        }
        if ($parent_slug === 'tools.php') {
            return array('tools.php');
        }
        return array();
    }
}
