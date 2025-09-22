<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Handles finding menu items by various criteria
 */
class MenuItemFinder
{
    /**
     * Find menu item by slug
     * Optimized to reduce recursion and improve performance
     * Enhanced to handle full URLs as slugs
     *
     * @param array $menu_items Menu items
     * @param string $slug Menu slug (may be full URL)
     * @return array|null Menu item
     */
    public function findMenuItemBySlug(array $menu_items, string $slug): ?array
    {
        foreach ($menu_items as $item) {
            if (isset($item['menu_slug'])) {
                // Direct match first (most common case)
                if ($item['menu_slug'] === $slug) {
                    return $item;
                }

                // Handle full URL matching for complex URLs
                if ($this->urlsMatch($item['menu_slug'], $slug)) {
                    return $item;
                }
            }

            // Search recursively in children (only if we have children)
            if (!empty($item['children']) && is_array($item['children'])) {
                $found = $this->findMenuItemBySlug($item['children'], $slug);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Find submenu item by slug
     * Optimized to reduce recursion and improve performance
     * Enhanced to handle full URLs as slugs
     *
     * @param array $menu_items Menu items
     * @param string $slug Menu slug (may be full URL)
     * @return array|null Menu item
     */
    public function findSubmenuItemBySlug(array $menu_items, string $slug): ?array
    {
        foreach ($menu_items as $item) {
            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['menu_slug'])) {
                        // Direct match first (most common case)
                        if ($child['menu_slug'] === $slug) {
                            return $child;
                        }

                        // Handle full URL matching for complex URLs
                        if ($this->urlsMatch($child['menu_slug'], $slug)) {
                            return $child;
                        }
                    }

                    // Check nested children (only if we have children)
                    if (!empty($child['children']) && is_array($child['children'])) {
                        $found = $this->findSubmenuItemBySlug(array($child), $slug);
                        if ($found) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find parent of submenu item
     * Optimized to reduce recursion and improve performance
     * Enhanced to handle full URLs as slugs
     *
     * @param array $menu_items Menu items
     * @param string $submenu_slug Submenu slug (may be full URL)
     * @return array|null Parent menu item
     */
    public function findParentOfSubmenuItem(array $menu_items, string $submenu_slug): ?array
    {
        foreach ($menu_items as $item) {
            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['menu_slug'])) {
                        // Direct match first (most common case)
                        if ($child['menu_slug'] === $submenu_slug) {
                            return $item;
                        }

                        // Handle full URL matching for complex URLs
                        if ($this->urlsMatch($child['menu_slug'], $submenu_slug)) {
                            return $item;
                        }
                    }

                    // Check nested children (only if we have children)
                    if (!empty($child['children']) && is_array($child['children'])) {
                        $found = $this->findParentOfSubmenuItem(array($child), $submenu_slug);
                        if ($found) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Recursively search for a menu item by slug or default_title anywhere in the menu tree
     *
     * @param array $items Menu items
     * @param string $menu_slug
     * @param string|null $default_title
     * @return array|null
     */
    public function findMenuItemAnywhere(array $items, string $menu_slug, ?string $default_title = null): ?array
    {
        foreach ($items as $item) {
            if (
                (isset($item['menu_slug']) && $item['menu_slug'] === $menu_slug) ||
                (!empty($default_title) && isset($item['default_title']) && $item['default_title'] === $default_title)
            ) {
                return $item;
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $found = $this->findMenuItemAnywhere($item['children'], $menu_slug, $default_title);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Check if two URLs match (for moved menu items)
     * Enhanced to handle more URL patterns and improve matching
     *
     * @param string $url1 First URL
     * @param string $url2 Second URL
     * @return bool True if URLs match
     */
    public function urlsMatch(string $url1, string $url2): bool
    {
        // Direct match first
        if ($url1 === $url2) {
            return true;
        }

        // Normalize URLs for comparison
        $normalized1 = $this->normalizeUrl($url1);
        $normalized2 = $this->normalizeUrl($url2);

        if ($normalized1 === $normalized2) {
            return true;
        }

        // Handle admin.php?page= variations
        if (strpos($url1, 'admin.php?page=') === 0 && strpos($url2, 'admin.php?page=') === 0) {
            $page1 = substr($url1, 14); // Remove 'admin.php?page='
            $page2 = substr($url2, 14);
            if ($page1 === $page2) {
                return true;
            }
        }

        // Handle simple slug vs admin.php?page=slug
        if (strpos($url1, 'admin.php?page=') === 0) {
            $page1 = substr($url1, 14);
            if ($page1 === $url2) {
                return true;
            }
        }

        if (strpos($url2, 'admin.php?page=') === 0) {
            $page2 = substr($url2, 14);
            if ($page2 === $url1) {
                return true;
            }
        }

        // Handle post type variations
        if (strpos($url1, 'edit.php?post_type=') === 0 && strpos($url2, 'edit.php?post_type=') === 0) {
            $post_type1 = substr($url1, 19); // Remove 'edit.php?post_type='
            $post_type2 = substr($url2, 19);
            if ($post_type1 === $post_type2) {
                return true;
            }
        }

        // Handle taxonomy variations
        if (strpos($url1, 'edit-tags.php?taxonomy=') === 0 && strpos($url2, 'edit-tags.php?taxonomy=') === 0) {
            $taxonomy1 = substr($url1, 23); // Remove 'edit-tags.php?taxonomy='
            $taxonomy2 = substr($url2, 23);
            if ($taxonomy1 === $taxonomy2) {
                return true;
            }
        }

        // Handle options-general.php?page= variations
        if (strpos($url1, 'options-general.php?page=') === 0 && strpos($url2, 'options-general.php?page=') === 0) {
            $page1 = substr($url1, 25); // Remove 'options-general.php?page='
            $page2 = substr($url2, 25);
            if ($page1 === $page2) {
                return true;
            }
        }

        // Handle tools.php?page= variations
        if (strpos($url1, 'tools.php?page=') === 0 && strpos($url2, 'tools.php?page=') === 0) {
            $page1 = substr($url1, 14); // Remove 'tools.php?page='
            $page2 = substr($url2, 14);
            if ($page1 === $page2) {
                return true;
            }
        }

        // Handle index.php with page parameter
        if ($url1 === 'index.php' && strpos($url2, 'admin.php?page=') === 0) {
            $page = substr($url2, 14);
            if (isset($_GET['page']) && $_GET['page'] === $page) {
                return true;
            }
        }

        if ($url2 === 'index.php' && strpos($url1, 'admin.php?page=') === 0) {
            $page = substr($url1, 14);
            if (isset($_GET['page']) && $_GET['page'] === $page) {
                return true;
            }
        }

        // Handle absolute vs relative URL variations
        if (strpos($url1, '://') !== false && strpos($url2, '://') === false) {
            $relative2 = $this->makeRelative($url2);
            if ($url1 === $relative2) {
                return true;
            }
        }

        if (strpos($url2, '://') !== false && strpos($url1, '://') === false) {
            $relative1 = $this->makeRelative($url1);
            if ($url2 === $relative1) {
                return true;
            }
        }

        // Handle full admin URL vs simple slug
        if (strpos($url1, admin_url()) === 0) {
            $relative1 = $this->makeRelative($url1);
            if ($relative1 === $url2) {
                return true;
            }
        }

        if (strpos($url2, admin_url()) === 0) {
            $relative2 = $this->makeRelative($url2);
            if ($relative2 === $url1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize URL for comparison
     *
     * @param string $url URL to normalize
     * @return string Normalized URL
     */
    protected function normalizeUrl(string $url): string
    {
        // Remove trailing slashes
        $url = rtrim($url, '/');

        // Remove admin_url prefix if present
        $admin_url = admin_url();
        if (strpos($url, $admin_url) === 0) {
            $url = substr($url, strlen($admin_url));
        }

        // Remove leading slash
        $url = ltrim($url, '/');

        return $url;
    }

    /**
     * Make URL relative by removing admin_url prefix
     *
     * @param string $url URL to make relative
     * @return string Relative URL
     */
    protected function makeRelative(string $url): string
    {
        $admin_url = admin_url();
        if (strpos($url, $admin_url) === 0) {
            return substr($url, strlen($admin_url));
        }
        return $url;
    }
}
