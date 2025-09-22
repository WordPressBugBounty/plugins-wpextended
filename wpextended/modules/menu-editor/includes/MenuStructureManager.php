<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Manages menu structure building, merging, and management
 */
class MenuStructureManager
{
    private MenuItemFactory $itemFactory;
    private MenuItemFinder $itemFinder;
    private AccessManager $accessManager;

    public function __construct()
    {
        $this->itemFactory = new MenuItemFactory();
        $this->itemFinder = new MenuItemFinder();
        $this->accessManager = new AccessManager();
    }

    /**
     * Get the current menu structure
     * Merges saved settings with current WordPress menu structure
     *
     * @return array Menu structure
     */
    public function getMenuStructure(): array
    {
        global $menu, $submenu;

        // Get saved menu items
        $saved_items = $this->getSetting('menu_items', array());

        // If no saved items, build from WordPress structure
        if (empty($saved_items)) {
            return $this->buildFromWordPressStructure();
        }

        // Merge saved items with current WordPress structure
        $merged = $this->mergeWithWordPressStructure($saved_items);

        return $merged;
    }

    /**
     * Build menu structure from WordPress global menu arrays
     * Enhanced to store full URLs as slugs for proper identification
     *
     * @return array Menu structure
     */
    protected function buildFromWordPressStructure(): array
    {
        global $menu, $submenu;

        $structure = array();
        $all_processed_slugs = array();


        // Build submenu map for easier lookup
        $submenu_map = array();
        if ($submenu) {
            foreach ($submenu as $parent_slug => $submenu_items) {
                $submenu_map[$parent_slug] = array();
                foreach ($submenu_items as $submenu_item) {
                    if (!empty($submenu_item[2])) {
                        $submenu_map[$parent_slug][$submenu_item[2]] = $submenu_item;
                    }
                }
            }
        }

        // Process each WordPress menu item
        if ($menu) {
            foreach ($menu as $menu_item) {
                if (empty($menu_item[2])) {
                    continue;
                }

                $menu_slug = $menu_item[2];
                $menu_title = $menu_item[0];
                $menu_cap = $menu_item[1] ?? 'manage_options';

                // Ensure title is never null and always a string
                $menu_title = (string) ($menu_title ?? '');

                // Skip WP Extended modules - they are dynamically added and should render normally
                // But allow wpextended-settings to be managed
                if (strpos($menu_slug, 'wpextended-') === 0 && $menu_slug !== 'wpextended-settings') {
                    continue;
                }

                // Skip post menu item if disable-blog module is enabled
                if ($this->isDisableBlogEnabled() && $menu_slug === 'edit.php') {
                    continue;
                }

                // Store the full URL as the slug for proper identification
                $full_url = $this->getFullMenuUrl($menu_slug);

                // Use the capability directly from the menu item
                $capability = $menu_cap;

                // Set default roles based on capability - users can override this in settings
                $roles = $this->accessManager->getRolesForCapability($capability);

                $item = $this->itemFactory->createMenuItem(
                    (strpos($menu_slug, 'separator') !== false || empty($menu_title)) ? 'separator' : 'item',
                    $menu_title,
                    $full_url, // Use full URL as slug
                    $menu_title,
                    $capability,
                    $roles
                );

                // Ensure separators render visibly in the UI
                if ($item['type'] === 'separator') {
                    $item['title'] = '-- Separator --';
                    $item['default_title'] = '-- Separator --';
                }

                // Add submenu items if any
                if (isset($submenu_map[$menu_slug])) {
                    $children = array();
                    foreach ($submenu_map[$menu_slug] as $submenu_slug => $submenu_item) {
                        $submenu_title = $submenu_item[0];
                        $submenu_cap = $submenu_item[1] ?? 'manage_options';

                        // Ensure submenu title is never null and always a string
                        $submenu_title = (string) ($submenu_title ?? '');

                        // Allow WP Extended submenu items to be visible in the settings interface
                        // They should appear under their proper parent menu
                        // Only filter them out if they're being promoted to root level

                        // Log WP Extended submenu items specifically
                        if (strpos($submenu_slug, 'wpextended-') === 0) {
                        }

                        // Skip blog-related submenu items if disable-blog module is enabled
                        if ($this->isDisableBlogEnabled()) {
                            $blocked_submenu_slugs = $this->getDisableBlogBlockedSubmenuSlugsForParent($menu_slug);
                            if (in_array($submenu_slug, $blocked_submenu_slugs, true)) {
                                continue;
                            }
                        }

                        // Store the full URL for submenu items as well
                        $full_submenu_url = $this->getFullSubmenuUrl($submenu_slug, $menu_slug);

                        // Use the capability directly from the submenu item
                        // Special case: profile.php always uses 'read' capability
                        if ($submenu_slug === 'profile.php') {
                            $sub_capability = 'read';
                        } else {
                            $sub_capability = $submenu_cap;
                        }

                        // Set default roles based on capability - users can override this in settings
                        $sub_roles = $this->accessManager->getRolesForCapability($sub_capability);

                        $children[] = $this->itemFactory->createMenuItem(
                            'item',
                            $submenu_title,
                            $full_submenu_url, // Use full URL as slug
                            $submenu_title,
                            $sub_capability,
                            $sub_roles
                        );

                        $all_processed_slugs[] = $full_submenu_url;
                    }
                    $item['children'] = $children;
                }

                $structure[] = $item;
                $all_processed_slugs[] = $full_url;
            }
        }

        // Add any orphaned submenu items (submenu items without a parent in the menu)
        // These are submenu items that don't have a parent in the current WordPress menu structure
        // We promote them to parent level so they can be managed in the interface
        if ($submenu) {
            foreach ($submenu as $parent_slug => $submenu_items) {
                // Skip WP Extended parent slugs - they are dynamically added and should render normally
                // But allow wpextended-settings to be managed
                if (strpos($parent_slug, 'wpextended-') === 0 && $parent_slug !== 'wpextended-settings') {
                    continue;
                }

                // Check if this parent exists in the structure
                $parent_exists = false;
                foreach ($structure as $structure_item) {
                    if (isset($structure_item['menu_slug']) && $structure_item['menu_slug'] === $parent_slug) {
                        $parent_exists = true;
                        break;
                    }
                }

                // If parent doesn't exist, all its submenu items are orphans
                if (!$parent_exists) {
                    foreach ($submenu_items as $submenu_item) {
                        if (empty($submenu_item[2])) {
                            continue;
                        }

                        $submenu_slug = $submenu_item[2];
                        $submenu_title = (string) ($submenu_item[0] ?? '');
                        $submenu_cap = $submenu_item[1] ?? 'manage_options';

                        // Skip WP Extended submenu items when they're being promoted to root level
                        // This prevents them from appearing as orphaned root items
                        // But allow wpextended-settings to be managed
                        if (strpos($submenu_slug, 'wpextended-') === 0 && $submenu_slug !== 'wpextended-settings') {
                            continue;
                        }

                        // Store the full URL for orphan submenu items
                        $full_submenu_url = $this->getFullSubmenuUrl($submenu_slug, $parent_slug);

                        // Use the capability directly from the submenu item
                        // Special case: profile.php always uses 'read' capability
                        if ($submenu_slug === 'profile.php') {
                            $capability = 'read';
                        } else {
                            $capability = $submenu_cap;
                        }

                        // Set default roles based on capability - users can override this in settings
                        $roles = $this->accessManager->getRolesForCapability($capability);

                        $item = $this->itemFactory->createMenuItem(
                            'item',
                            $submenu_title,
                            $full_submenu_url, // Use full URL as slug
                            $submenu_title,
                            $capability,
                            $roles
                        );

    // Mark this as an orphan item for reference
                        $item['orphan_info'] = array(
                        'original_parent' => $parent_slug,
                        'was_submenu' => true
                        );

                        $structure[] = $item;
                        $all_processed_slugs[] = $full_submenu_url;
                    }
                }
            }
        }

        // Special handling: Always ensure Profile exists in the structure
        // WordPress shows it differently based on user capabilities:
        // - With list_users: Profile is under Users menu
        // - Without list_users: Profile is a top-level menu
        // We'll add it as a fallback top-level item if it doesn't exist anywhere
        $profile_exists = false;
        foreach ($structure as $item) {
            if (isset($item['menu_slug']) && $item['menu_slug'] === 'profile.php') {
                $profile_exists = true;
                break;
            }
            // Also check in children (under Users menu)
            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['menu_slug']) && $child['menu_slug'] === 'profile.php') {
                        $profile_exists = true;
                        break 2;
                    }
                }
            }
        }

        // If Profile doesn't exist anywhere, add it as a top-level menu
        // This ensures it's available in the menu editor settings
        if (!$profile_exists) {

            // Set default roles based on capability - users can override this in settings
            $roles = $this->accessManager->getRolesForCapability('read');
            $structure[] = $this->itemFactory->createMenuItem(
                'item',
                __('Profile'),
                'profile.php',
                __('Profile'),
                'read',
                $roles
            );
        }

        return $structure;
    }

    /**
     * Get the full URL for a menu item
     * Ensures proper identification of complex URLs with query parameters
     *
     * @param string $menu_slug Original menu slug
     * @return string Full URL
     */
    protected function getFullMenuUrl(string $menu_slug): string
    {
        // If it's already a full URL with query parameters, return as is
        if (strpos($menu_slug, '?') !== false) {
            return $menu_slug;
        }

        // If it's a simple slug without extension, format as admin.php?page=
        if (strpos($menu_slug, '.') === false) {
            return 'admin.php?page=' . $menu_slug;
        }

        // If it has a file extension, return as is (like edit.php, upload.php, etc.)
        return $menu_slug;
    }

    /**
     * Get the full URL for a submenu item
     * Handles the relationship between parent and child menu items
     * Preserves the parent-child relationship in the URL structure
     *
     * @param string $submenu_slug Submenu slug
     * @param string $parent_slug Parent slug
     * @return string Full URL
     */
    protected function getFullSubmenuUrl(string $submenu_slug, string $parent_slug): string
    {
        // If submenu slug already has query parameters, return as is
        if (strpos($submenu_slug, '?') !== false) {
            return $submenu_slug;
        }

        // If submenu slug has a file extension (.php), use it as-is
        if (strpos($submenu_slug, '.php') !== false) {
            return $submenu_slug;
        }

        // Handle parent-child relationships based on parent slug (only for simple slugs)
        if ($parent_slug === 'options-general.php') {
            // Settings submenu items should use options-general.php as parent
            return sprintf('options-general.php?page=%s', $submenu_slug);
        }

        return sprintf('admin.php?page=%s', $submenu_slug);
    }


    /**
     * Merge saved menu structure with current WordPress structure
     * This ensures new items are added and removed items are handled
     *
     * @param array $saved_items Saved menu items
     * @return array Merged menu structure
     */
    protected function mergeWithWordPressStructure(array $saved_items): array
    {
        global $menu, $submenu;

        // Helper to validate menu item has required fields
        $is_valid_item = function ($item) {
            return $this->itemFactory->isValidMenuItem($item);
        };

        // Filter out invalid items from saved items
        $saved_items = array_filter($saved_items, $is_valid_item);

        // Build a new merged structure
        $merged = array();
        $all_processed_slugs = array(); // Track ALL processed slugs (parent and submenu)

        // First, build a complete map of WordPress menu structure
        $wp_menu_map = array();
        $wp_submenu_map = array();

        if ($menu) {
            foreach ($menu as $menu_item) {
                if (!empty($menu_item[2])) {
                    $slug = $menu_item[2];
                    // When disable-blog is enabled, exclude Posts and its direct sub-items from the base map to avoid later promotion
                    if ($this->isDisableBlogEnabled()) {
                        if ($slug === 'edit.php') {
                            continue;
                        }
                    }
                    $wp_menu_map[$slug] = $menu_item;
                }
            }
        }

        if ($submenu) {
            foreach ($submenu as $parent_slug => $submenu_items) {
                $wp_submenu_map[$parent_slug] = array();
                foreach ($submenu_items as $submenu_item) {
                    if (!empty($submenu_item[2])) {
                        $sub_slug = $submenu_item[2];
                        if ($this->isDisableBlogEnabled()) {
                            $blocked = $this->getDisableBlogBlockedSubmenuSlugsForParent($parent_slug);
                            if (in_array($sub_slug, $blocked, true)) {
                                continue;
                            }
                        }
                        $wp_submenu_map[$parent_slug][$sub_slug] = $submenu_item;
                    }
                }
            }
        }

        // Helper to recursively merge children and track all slugs
        $merge_children = function ($parent_slug, $saved_children, $depth = 0) use (&$wp_submenu_map, &$merge_children, $saved_items, $is_valid_item, &$all_processed_slugs) {
            // Respect max_depth setting (1 level of nesting)
            $max_depth = 1;
            if ($depth >= $max_depth) {
                return array();
            }

            $children = array();

            // Filter out invalid children
            $saved_children = array_filter($saved_children, $is_valid_item);

            // Get WordPress submenu items for this parent
            $wp_submenu_items = isset($wp_submenu_map[$parent_slug]) ? $wp_submenu_map[$parent_slug] : array();

            // Process WordPress submenu items first
            foreach ($wp_submenu_items as $submenu_slug => $submenu_item) {
                $submenu_title = $submenu_item[0];
                $submenu_cap = $submenu_item[1] ?? 'manage_options';

                // Try to find in saved children by slug or default_title
                $saved = null;
                foreach ($saved_children as $child) {
                    if (
                        (isset($child['menu_slug']) && $child['menu_slug'] === $submenu_slug) ||
                        (isset($child['default_title']) && $child['default_title'] === $submenu_title)
                    ) {
                        $saved = $child;
                        break;
                    }
                }

                if ($saved) {
                    // Use saved item but ensure it has current WordPress data
                    $saved['capability'] = $saved['capability'] ?? $submenu_cap;
                    // Preserve saved access_roles; only default when key is entirely missing
                    if (!array_key_exists('access_roles', $saved)) {
                        $saved['access_roles'] = $this->accessManager->getRolesForCapability($saved['capability']);
                    } elseif (!is_array($saved['access_roles'])) {
                        // If present but malformed, normalize to empty array (explicit empty)
                        $saved['access_roles'] = array();
                    }
                    $saved['default_title'] = $saved['default_title'] ?? $submenu_title;

                    // Recursively merge children with depth tracking
                    if ($depth < $max_depth) {
                        $saved['children'] = isset($saved['children']) ? $merge_children($submenu_slug, $saved['children'], $depth + 1) : $merge_children($submenu_slug, array(), $depth + 1);
                    } else {
                        $saved['children'] = array(); // No more nesting allowed
                    }
                    $children[] = $saved;
                } else {
                    // New WordPress submenu item
                    // Set default roles based on capability - users can override this in settings
                    $roles = $this->accessManager->getRolesForCapability($submenu_cap);
                    $children[] = $this->itemFactory->createMenuItem('item', $submenu_title, $submenu_slug, $submenu_title, $submenu_cap, $roles);
                }

                // Track this slug as processed
                $all_processed_slugs[] = $submenu_slug;
            }

            // Add any saved children that are not in current WordPress submenu (e.g., moved from another parent)
            foreach ($saved_children as $child) {
                $exists = false;
                if (isset($child['menu_slug'])) {
                    foreach ($children as $c) {
                        if ($c['menu_slug'] === $child['menu_slug']) {
                            $exists = true;
                            break;
                        }
                    }
                }
                if (!$exists) {
                    // Check if this child exists in any WordPress submenu (might be moved)
                    $found_in_any_submenu = false;
                    foreach ($wp_submenu_map as $any_parent => $any_submenu_items) {
                        if (isset($any_submenu_items[$child['menu_slug']])) {
                            $found_in_any_submenu = true;
                            break;
                        }
                    }

                    if ($found_in_any_submenu) {
                        $children[] = $child;
                        // Track this slug as processed
                        if (isset($child['menu_slug'])) {
                            $all_processed_slugs[] = $child['menu_slug'];
                        }
                    } else {
                        // This is an orphan submenu item - don't add it to the settings interface
                        // but keep it in the saved data for potential future use
                    }
                }
            }

            return $children;
        };

        // Build merged parents - start with saved items that are marked as parent items
        foreach ($saved_items as $saved_item) {
            if (empty($saved_item['menu_slug'])) {
                continue;
            }

            // Ensure all required fields are present
            $saved_item = $this->itemFactory->normalizeMenuItem($saved_item);

            $menu_slug = $saved_item['menu_slug'];

            // Track this slug as processed
            $all_processed_slugs[] = $menu_slug;

            // Check if this item exists in WordPress menu (as parent)
            if (isset($wp_menu_map[$menu_slug])) {
                $wp_menu_item = $wp_menu_map[$menu_slug];

                // Use saved item but ensure it has current WordPress data
                $saved_item['capability'] = $saved_item['capability'] ?? $wp_menu_item[1] ?? 'manage_options';
                // Preserve saved access_roles; only default when key is entirely missing
                if (!array_key_exists('access_roles', $saved_item)) {
                    $saved_item['access_roles'] = $this->accessManager->getRolesForCapability($saved_item['capability']);
                } elseif (!is_array($saved_item['access_roles'])) {
                    // If present but malformed, normalize to empty array (explicit empty)
                    $saved_item['access_roles'] = array();
                }
                $saved_item['default_title'] = $saved_item['default_title'] ?? $wp_menu_item[0];

                // Merge children
                $saved_item['children'] = isset($saved_item['children']) ? $merge_children($menu_slug, $saved_item['children'], 0) : $merge_children($menu_slug, array(), 0);
                $merged[] = $saved_item;
            } else {
                // This item is not in WordPress menu - it might be a moved submenu item

                // Check if it was originally a submenu item
                $found_as_submenu = false;
                foreach ($wp_submenu_map as $parent_slug => $submenu_items) {
                    if (isset($submenu_items[$menu_slug])) {
                        $original_submenu_data = $submenu_items[$menu_slug];

                        // Use the saved item but ensure it has proper data
                        $saved_item['capability'] = $saved_item['capability'] ?? $original_submenu_data[1] ?? 'manage_options';
                        // Preserve saved access_roles; only default when key is entirely missing
                        if (!array_key_exists('access_roles', $saved_item)) {
                            $saved_item['access_roles'] = $this->accessManager->getRolesForCapability($saved_item['capability']);
                        } elseif (!is_array($saved_item['access_roles'])) {
                            // If present but malformed, normalize to empty array (explicit empty)
                            $saved_item['access_roles'] = array();
                        }
                        $saved_item['default_title'] = $saved_item['default_title'] ?? $original_submenu_data[0];
                        $saved_item['children'] = array(); // No children for moved submenu items
                        $merged[] = $saved_item;
                        $found_as_submenu = true;
                        break;
                    }
                }

                if (!$found_as_submenu) {
                    // This is an orphan item - don't add it to the settings interface
                    // but keep it in the saved data for potential future use
                    continue;
                }
            }
        }

        // Add any WordPress menu items that aren't in saved items AND haven't been processed as submenu items
        foreach ($wp_menu_map as $menu_slug => $menu_item) {
            $menu_title = $menu_item[0];

            // Skip WP Extended modules - they are dynamically added and should render normally
            // But allow wpextended-settings to be managed
            if (strpos($menu_slug, 'wpextended-') === 0 && $menu_slug !== 'wpextended-settings') {
                continue;
            }

            // Check if this item is already processed (either as parent or submenu)
            if (in_array($menu_slug, $all_processed_slugs)) {
                continue;
            }

            // If disable-blog is enabled, do not re-append Posts top-level (edit.php)
            if ($this->isDisableBlogEnabled() && $menu_slug === 'edit.php') {
                continue;
            }

            // Use the capability directly from the menu item
            $capability = $menu_item[1] ?? 'manage_options';

            // Set default roles based on capability - users can override this in settings
            $roles = $this->accessManager->getRolesForCapability($capability);

            $item = $this->itemFactory->createMenuItem(
                (strpos($menu_slug, 'separator') !== false || empty($menu_title)) ? 'separator' : 'item',
                $menu_title,
                $menu_slug,
                $menu_title,
                $capability,
                $roles
            );

            // Fix separator titles
            if ($item['type'] === 'separator') {
                $item['title'] = '-- Separator --';
                $item['default_title'] = '-- Separator --';
            }

            // Add children if any
            if (isset($wp_submenu_map[$menu_slug])) {
                $children = $merge_children($menu_slug, array(), 0);
                // When disable-blog is enabled, filter out posts-related children as a safety net
                if ($this->isDisableBlogEnabled() && $menu_slug === 'edit.php') {
                    $blocked = $this->getDisableBlogBlockedSubmenuSlugsForParent($menu_slug);
                    $children = array_values(array_filter($children, function ($child) use ($blocked) {
                        return empty($child['menu_slug']) || !in_array($child['menu_slug'], $blocked, true);
                    }));
                }
                $item['children'] = $children;
            }

            $merged[] = $item;
            $all_processed_slugs[] = $menu_slug;
        }

        // Final validation - filter out any invalid items that might have been created
        $merged = array_filter($merged, $is_valid_item);

        // Add any orphaned submenu items (submenu items without a parent in the merged array)
        // These are submenu items that don't have a parent in the current WordPress menu structure
        // We promote them to parent level so they can be managed in the interface
        $orphan_submenu_items = array();

        // Find all submenu items that don't have a parent in the merged array
        foreach ($wp_submenu_map as $parent_slug => $submenu_items) {
            // Skip WP Extended parent slugs - they are dynamically added and should render normally
            // But allow wpextended-settings to be managed
            if (strpos($parent_slug, 'wpextended-') === 0 && $parent_slug !== 'wpextended-settings') {
                continue;
            }

            // Check if this parent exists in the merged array
            $parent_exists = false;
            foreach ($merged as $merged_item) {
                if (isset($merged_item['menu_slug']) && $merged_item['menu_slug'] === $parent_slug) {
                    $parent_exists = true;
                    break;
                }
            }

            // If parent doesn't exist, all its submenu items are orphans
            if (!$parent_exists) {
                foreach ($submenu_items as $submenu_slug => $submenu_item) {
                    // Skip WP Extended submenu items when they're being promoted to root level
                    // This prevents them from appearing as orphaned root items
                    // But allow wpextended-settings to be managed
                    if (strpos($submenu_slug, 'wpextended-') === 0 && $submenu_slug !== 'wpextended-settings') {
                        continue;
                    }

                    $orphan_submenu_items[] = array(
                        'parent_slug' => $parent_slug,
                        'submenu_slug' => $submenu_slug,
                        'submenu_item' => $submenu_item
                    );
                }
            }
        }

        // Add orphan submenu items as parent items
        foreach ($orphan_submenu_items as $orphan) {
            $submenu_item = $orphan['submenu_item'];
            $submenu_slug = $orphan['submenu_slug'];
            $parent_slug = $orphan['parent_slug'];

            // Use the capability directly from the submenu item
            // Special case: profile.php always uses 'read' capability
            if ($submenu_slug === 'profile.php') {
                $capability = 'read';
            } else {
                $capability = $submenu_item[1] ?? 'manage_options';
            }
            $roles = $this->accessManager->getRolesForCapability($capability);

            $item = $this->itemFactory->createMenuItem(
                'item',
                $submenu_item[0], // title
                $submenu_slug,
                $submenu_item[0], // default_title
                $capability,
                $roles
            );

            // Mark this as an orphan item for reference
            $item['orphan_info'] = array(
                'original_parent' => $parent_slug,
                'was_submenu' => true
            );

            $merged[] = $item;
        }

        // Special handling: Always ensure Profile exists in the structure
        // Same logic as in buildFromWordPressStructure
        $profile_exists = false;
        foreach ($merged as $item) {
            if (isset($item['menu_slug']) && ($item['menu_slug'] === 'profile.php' || strpos($item['menu_slug'], 'profile.php') !== false)) {
                $profile_exists = true;
                break;
            }
            // Also check in children
            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['menu_slug']) && ($child['menu_slug'] === 'profile.php' || strpos($child['menu_slug'], 'profile.php') !== false)) {
                        $profile_exists = true;
                        break 2;
                    }
                }
            }
        }

        // If Profile doesn't exist anywhere, add it as a top-level menu
        if (!$profile_exists) {
          
            // Set default roles based on capability - users can override this in settings
            $roles = $this->accessManager->getRolesForCapability('read');
            $merged[] = $this->itemFactory->createMenuItem(
                'item',
                __('Profile'),
                'profile.php',
                __('Profile'),
                'read',
                $roles
            );
        }

        return $merged;
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    protected function getSetting(string $key, $default = null)
    {
        return \Wpextended\Includes\Utils::getSetting('menu-editor', $key, $default);
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
        // Also block Posts-related submenus under Posts parent when disable-blog is enabled
        if ($parent_slug === 'edit.php') {
            return array(
                'post-new.php',
                'edit-tags.php?taxonomy=category',
                'edit-tags.php?taxonomy=post_tag',
            );
        }
        return array();
    }
}
