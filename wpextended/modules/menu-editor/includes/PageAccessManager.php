<?php

namespace Wpextended\Modules\MenuEditor\Includes;

/**
 * Manages page access checking and redirection
 */
class PageAccessManager
{
    private MenuItemFinder $itemFinder;
    private AccessManager $accessManager;

    public function __construct(MenuItemFinder $itemFinder, AccessManager $accessManager)
    {
        $this->itemFinder = $itemFinder;
        $this->accessManager = $accessManager;
    }

    /**
     * Check page access and redirect unauthorized users
     *
     * @param array $menu_items Menu items configuration
     * @return void
     */
    public function checkPageAccess(array $menu_items): void
    {
        // Only check on admin pages
        if (!is_admin()) {
            return;
        }

        // Get current page
        $current_page = $this->getCurrentAdminPage();

        if (!$current_page) {
            return;
        }

        if (empty($menu_items)) {
            return;
        }

        // Find the menu item for this page (check both main menu and submenu)
        $menu_item = $this->itemFinder->findMenuItemBySlug($menu_items, $current_page);
        $parent_item = null;

        if (!$menu_item) {
            // If not found in main menu, check if it's a submenu page
            $menu_item = $this->itemFinder->findSubmenuItemBySlug($menu_items, $current_page);

            // If found as submenu item, find its parent for capability override
            if ($menu_item) {
                $parent_item = $this->itemFinder->findParentOfSubmenuItem($menu_items, $current_page);
            }
        }

        if (!$menu_item) {
            return;
        }

        // Check if user has access (pass parent for capability override)
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        $user_id = $current_user->ID;

        if (!$this->accessManager->canUserAccessItem($menu_item, $user_roles, $user_id, $parent_item)) {
            // User doesn't have access, redirect them
            $this->redirectUnauthorizedUser();
        }
    }

    /**
     * Get current admin page slug
     * Enhanced to handle moved menu items and better URL matching
     *
     * @return string|null Current page slug or null
     */
    protected function getCurrentAdminPage(): ?string
    {
        // Get the current page from various sources
        $page = null;

        // Check for page parameter (most common for custom admin pages)
        if (isset($_GET['page'])) {
            $page = sanitize_text_field($_GET['page']);
        }

        // Check for post_type parameter (for post type pages)
        if (!$page && isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
            if (isset($_GET['action']) && $_GET['action'] === 'edit') {
                $page = 'post.php';
            } else {
                $page = 'edit.php?post_type=' . $post_type;
            }
        }

        // Check for taxonomy pages
        if (!$page && isset($_GET['taxonomy'])) {
            $taxonomy = sanitize_text_field($_GET['taxonomy']);
            $page = 'edit-tags.php?taxonomy=' . $taxonomy;
        }

        // Check for specific admin pages using pagenow
        if (!$page) {
            $pagenow = $GLOBALS['pagenow'] ?? '';
            if ($pagenow) {
                $page = $pagenow;

                // Add query parameters for specific pages
                if ($pagenow === 'edit.php' && isset($_GET['post_type'])) {
                    $page .= '?post_type=' . sanitize_text_field($_GET['post_type']);
                } elseif ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy'])) {
                    $page .= '?taxonomy=' . sanitize_text_field($_GET['taxonomy']);
                } elseif ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
                    // Handle post editing pages
                    if (isset($_GET['post_type'])) {
                        $page .= '?post_type=' . sanitize_text_field($_GET['post_type']);
                    }
                }
            }
        }

        // Check for current screen as fallback
        if (!$page) {
            $current_screen = get_current_screen();
            if ($current_screen && $current_screen->id) {
                $page = $current_screen->id;
            }
        }

        // Enhanced URL matching for moved menu items
        if ($page) {
            $page = $this->normalizePageUrl($page);
        }

        return $page;
    }

    /**
     * Normalize page URL for better matching of moved menu items
     * Similar to Admin Menu Editor Pro's URL normalization
     *
     * @param string $page Page URL/slug
     * @return string Normalized page URL
     */
    protected function normalizePageUrl(string $page): string
    {
        // Handle absolute URLs by extracting the relevant part
        if (strpos($page, admin_url()) === 0) {
            $page = str_replace(admin_url(), '', $page);
        }

        // Remove leading slash
        $page = ltrim($page, '/');

        // Handle index.php with page parameter
        if ($page === 'index.php' && isset($_GET['page'])) {
            $page = 'admin.php?page=' . sanitize_text_field($_GET['page']);
        }

        // Normalize common patterns
        $normalizations = array(
            'admin.php?page=' => 'admin.php?page=',
            'edit.php?post_type=' => 'edit.php?post_type=',
            'edit-tags.php?taxonomy=' => 'edit-tags.php?taxonomy=',
        );

        foreach ($normalizations as $pattern => $replacement) {
            if (strpos($page, $pattern) === 0) {
                $page = $replacement . substr($page, strlen($pattern));
                break;
            }
        }

        return $page;
    }

    /**
     * Redirect unauthorized user
     *
     * @return void
     */
    protected function redirectUnauthorizedUser(): void
    {
        // Set error message
        wp_die(
            __('You do not have permission to access this page.', WP_EXTENDED_TEXT_DOMAIN),
            __('Access Denied', WP_EXTENDED_TEXT_DOMAIN),
            array(
                'response' => 403,
                'back_link' => true,
            )
        );
    }
}
