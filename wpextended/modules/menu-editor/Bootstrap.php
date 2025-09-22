<?php

namespace Wpextended\Modules\MenuEditor;

use Wpextended\Modules\BaseModule;
use Wpextended\Modules\MenuEditor\Includes\{
    MenuStructureManager,
    MenuItemFactory,
    MenuItemFinder,
    AccessManager,
    MenuProcessor,
    PageAccessManager,
    SettingsManager
};

/**
 * Menu Editor Module Bootstrap - Refactored Version
 */
class Bootstrap extends BaseModule
{
    private MenuStructureManager $structureManager;
    private MenuItemFactory $itemFactory;
    private MenuItemFinder $itemFinder;
    private AccessManager $accessManager;
    private MenuProcessor $menuProcessor;
    private PageAccessManager $pageAccessManager;
    private SettingsManager $settingsManager;

    /**
     * Initialize the module
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('menu-editor');

        // Initialize all managers
        $this->initializeManagers();
    }

    /**
     * Initialize all manager classes
     *
     * @return void
     */
    private function initializeManagers(): void
    {
        $this->itemFactory = new MenuItemFactory();
        $this->accessManager = new AccessManager();
        $this->itemFinder = new MenuItemFinder();

        $this->structureManager = new MenuStructureManager();
        $this->menuProcessor = new MenuProcessor($this->accessManager, $this->itemFinder);
        $this->pageAccessManager = new PageAccessManager($this->itemFinder, $this->accessManager);
        $this->settingsManager = new SettingsManager($this->itemFactory, $this->accessManager);
    }

    /**
     * Set up all hooks
     *
     * @return void
     */
    protected function init(): void
    {
        // Profile injection handled inside MenuProcessor::applyMenuChanges

        // Run late to ensure all menus from other plugins are registered before filtering
        add_action('admin_menu', array($this, 'applyMenuChanges'), 9999);

        // Ensure dashboard and profile pages are always accessible - run as early as possible
        add_action('admin_init', array($this, 'ensureDashboardAccess'), 1);
        add_action('admin_head', array($this, 'ensureDashboardAccess'), 1);

        // Also hook into user capability checks to ensure dashboard access
        add_filter('user_has_cap', array($this, 'ensureDashboardCapabilities'), 10, 4);

        // Hook into WordPress's admin access system
        add_action('admin_page_access_denied', array($this, 'handleAdminAccessDenied'), 1);

        add_filter('wpextended/menu-editor/menu_items/field_args', array($this, 'addFieldArgs'), 10, 2);
        add_filter('wpextended/menu-editor/settings_before_save', array($this, 'beforeSave'), 10, 3);
        add_action('admin_init', array($this, 'checkPageAccess'), 1);

        // Sync per-user capability grants at runtime so changes take effect immediately when rendering menus.
        add_action('set_current_user', array($this, 'syncUserCapabilities'), 999);
        add_action('admin_init', array($this, 'syncUserCapabilities'), 999);
    }

    /**
     * Get module settings fields
     *
     * @return array Module settings fields
     */
    public function getSettingsFields(): array
    {
        return $this->settingsManager->getSettingsFields();
    }

    /**
     * Clean menu items before saving
     *
     * @param array $data Data to clean
     * @return array Cleaned data
     */
    public function beforeSave($data)
    {
        return $this->settingsManager->beforeSave($data);
    }

    /**
     * Add field arguments for dynamic choices and default values
     *
     * @param array $args Field arguments
     * @param array $field Field data
     * @return array Field arguments
     */
    public function addFieldArgs(array $args, array $field): array
    {
        $menu_structure = $this->getMenuStructure();
        return $this->settingsManager->addFieldArgs($args, $field, $menu_structure);
    }

    /**
     * Get the current menu structure
     *
     * @return array Menu structure
     */
    public function getMenuStructure(): array
    {
        return $this->structureManager->getMenuStructure();
    }

    /**
     * Ensure dashboard and profile pages are always accessible
     * This runs early in admin_init to override any access restrictions
     *
     * @return void
     */
    public function ensureDashboardAccess(): void
    {
        // Only run in admin area
        if (!is_admin()) {
            return;
        }

        // Get current page information
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $current_file = basename($_SERVER['PHP_SELF']);
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';



        // Check if this is a dashboard or profile access attempt
        $is_dashboard_access = (
            $current_file === 'index.php' ||
            $current_file === 'profile.php' ||
            $current_page === 'index.php' ||
            $current_page === 'profile.php' ||
            strpos($request_uri, '/wp-admin/index.php') !== false ||
            strpos($request_uri, '/wp-admin/profile.php') !== false ||
            strpos($request_uri, 'page=index.php') !== false ||
            strpos($request_uri, 'page=profile.php') !== false
        );

        if ($is_dashboard_access) {
            // Ensure current user has read capability
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID) {
                if (!$current_user->has_cap('read')) {
                    $current_user->add_cap('read');
                }

                // Also ensure they have manage_options for admin access
                if (!$current_user->has_cap('manage_options')) {
                    $current_user->add_cap('manage_options');
                }
            }

            // Remove any access restrictions for these pages
            remove_action('admin_init', array($this, 'checkPageAccess'));
        }
    }

    /**
     * Ensure dashboard capabilities are always granted
     * This filter runs when WordPress checks user capabilities
     *
     * @param array $allcaps Array of key/value pairs where keys represent a capability name and boolean values
     * @param array $caps Required primitive capabilities for the requested capability
     * @param array $args Arguments that accompany the requested capability check
     * @param WP_User $user The user object
     * @return array Modified capabilities array
     */
    public function ensureDashboardCapabilities($allcaps, $caps, $args, $user): array
    {
        // Only run in admin area
        if (!is_admin()) {
            return $allcaps;
        }

        // Get current page information
        $current_file = basename($_SERVER['PHP_SELF']);
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if this is a dashboard or profile access attempt
        $is_dashboard_access = (
            $current_file === 'index.php' ||
            $current_file === 'profile.php' ||
            strpos($request_uri, '/wp-admin/index.php') !== false ||
            strpos($request_uri, '/wp-admin/profile.php') !== false
        );

        if ($is_dashboard_access) {
            // Always grant read and manage_options capabilities for dashboard access
            $allcaps['read'] = true;
            $allcaps['manage_options'] = true;
            $allcaps['edit_posts'] = true;
            $allcaps['edit_pages'] = true;

            // No debug logging
        }

        return $allcaps;
    }

    /**
     * Handle admin access denied for dashboard/profile pages
     *
     * @return void
     */
    public function handleAdminAccessDenied(): void
    {
        // Get current page information
        $current_file = basename($_SERVER['PHP_SELF']);
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if this is a dashboard or profile access attempt
        $is_dashboard_access = (
            $current_file === 'index.php' ||
            $current_file === 'profile.php' ||
            strpos($request_uri, '/wp-admin/index.php') !== false ||
            strpos($request_uri, '/wp-admin/profile.php') !== false
        );

        if ($is_dashboard_access) {
            // No debug logging

            // Redirect to dashboard instead of showing access denied
            wp_redirect(admin_url('index.php'));
            exit;
        }
    }

    /**
     * Apply menu changes to WordPress admin menu
     *
     * @return void
     */
    public function applyMenuChanges(): void
    {
        $menu_items = $this->getSetting('menu_items', array());
        $this->menuProcessor->applyMenuChanges($menu_items);
    }

    /**
     * Check page access and redirect unauthorized users
     *
     * @return void
     */
    public function checkPageAccess(): void
    {
        // Skip page access checks for dashboard and profile pages
        $current_file = basename($_SERVER['PHP_SELF']);
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if this is a dashboard or profile access attempt
        $is_dashboard_access = (
            $current_file === 'index.php' ||
            $current_file === 'profile.php' ||
            strpos($request_uri, '/wp-admin/index.php') !== false ||
            strpos($request_uri, '/wp-admin/profile.php') !== false
        );

        if ($is_dashboard_access) {
            // No debug logging
            return;
        }

        $menu_items = $this->getSetting('menu_items', array());
        $this->pageAccessManager->checkPageAccess($menu_items);
    }


    /**
     * Grant temporary capabilities based on user-specific menu access
     *
     * @param array $allcaps All capabilities the user has
     * @param array $caps Required capabilities being checked
     * @param array $args Additional arguments
     * @param \WP_User $user The user object
     * @return array Modified capabilities
     */
    public function grantTemporaryCapabilities(array $allcaps, array $caps, array $args, \WP_User $user): array
    {
        // Only process in admin area
        if (!is_admin()) {
            return $allcaps;
        }

        // Get menu items configuration
        $menu_items = $this->getSetting('menu_items', array());
        if (empty($menu_items)) {
            return $allcaps;
        }

        // Check each required capability
        foreach ($caps as $cap) {
            // Skip the special 'do_not_allow' capability (used for separators)
            if ($cap === 'do_not_allow') {
                continue;
            }

            // Skip if user already has this capability
            if (!empty($allcaps[$cap])) {
                continue;
            }

            // Check if this capability is needed for any menu item the user has explicit access to
            if ($this->shouldGrantCapability($cap, $user->ID, $menu_items)) {
                $allcaps[$cap] = true;
            }
        }

        return $allcaps;
    }

    /**
     * Check if a capability should be granted based on menu configuration
     *
     * @param string $capability The capability being checked
     * @param int $user_id The user ID
     * @param array $menu_items Menu items configuration
     * @return bool Whether to grant the capability
     */
    protected function shouldGrantCapability(string $capability, int $user_id, array $menu_items): bool
    {
        // Recursively check all menu items
        foreach ($menu_items as $item) {
            // Check if this item uses the capability
            if (isset($item['capability']) && $item['capability'] === $capability) {
                // Check if user has explicit access
                if ($this->userHasExplicitAccess($item, $user_id)) {
                    return true;
                }
            }

            // Check children recursively
            if (!empty($item['children']) && is_array($item['children'])) {
                if ($this->shouldGrantCapability($capability, $user_id, $item['children'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user has explicit access to a menu item
     *
     * @param array $item Menu item
     * @param int $user_id User ID
     * @return bool Whether user has explicit access
     */
    protected function userHasExplicitAccess(array $item, int $user_id): bool
    {
        $access_users = isset($item['access_users']) ? $item['access_users'] : array();
        $user_access_mode = isset($item['user_access_mode']) ? $item['user_access_mode'] : 'grant';

        if (!empty($access_users) && is_array($access_users)) {
            $user_id_string = (string) $user_id;

            if (in_array($user_id_string, $access_users) && $user_access_mode === 'grant') {
                return true;
            }
        }

        return false;
    }

    /**
     * Log menu structure for debugging
     *
     * @param array $menu_items Menu items
     * @param int $depth Current depth for indentation
     * @return void
     */
    protected function logMenuStructure(array $menu_items, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);

        foreach ($menu_items as $item) {
            if (!isset($item['menu_slug'])) {
                continue;
            }

            $title = $item['title'] ?? $item['default_title'] ?? 'Unknown';
            $capability = $item['capability'] ?? 'not set';
            $access_users = isset($item['access_users']) && is_array($item['access_users']) ? $item['access_users'] : array();
            $user_access_mode = $item['user_access_mode'] ?? 'grant';

            $log_message = $indent . '- ' . $title . ' (' . $item['menu_slug'] . ')';
            $log_message .= ' | Cap: ' . $capability;

            if (!empty($access_users)) {
                $log_message .= ' | Users (' . $user_access_mode . '): ' . implode(',', $access_users);
            }

            // Log children
            if (!empty($item['children']) && is_array($item['children'])) {
                $this->logMenuStructure($item['children'], $depth + 1);
            }
        }
    }

    /**
     * At runtime, grant/revoke user-specific capabilities for current user only.
     * No extra options are stored; this derives solely from saved menu_items.
     */
    public function syncUserCapabilities(): void
    {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }
        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) {
            return;
        }

        // Load configured menu items once.
        $menu_items = $this->getSetting('menu_items', array());
        if (empty($menu_items) || !is_array($menu_items)) {
            return;
        }

        // Compute desired caps for this user from settings.
        $desired_grants = $this->buildUserCapabilityGrants($menu_items);
        $target_caps = isset($desired_grants[$current_user->ID]) ? $desired_grants[$current_user->ID] : array();
        $managed_caps = $this->buildManagedCapabilities($menu_items);

        // Add desired caps.
        foreach ($target_caps as $cap) {
            if (empty($current_user->caps[$cap])) {
                $current_user->add_cap($cap);
            }
        }

        // Remove only managed caps that are not desired now.
        if (!empty($current_user->caps) && is_array($current_user->caps)) {
            foreach (array_keys($current_user->caps) as $ucap) {
                if (in_array($ucap, $managed_caps, true) && !in_array($ucap, $target_caps, true)) {
                    $current_user->remove_cap($ucap);
                }
            }
        }
    }

    /**
     * Persist real capability grants to users based on settings.
     * Ensures menus can load while visibility is still controlled by our access rules.
     *
     * @param mixed $old_value Previous option value
     * @param mixed $value New option value
     * @return void
     */
    public function handleCapabilityGrants($old_value, $value): void
    {
        // Expect an array with 'menu_items'. Fail-safe if not structured.
        $menu_items = array();
        if (is_array($value) && isset($value['menu_items']) && is_array($value['menu_items'])) {
            $menu_items = $value['menu_items'];
        }

        // Build desired grants: user_id => [cap, ...]
        $desired_grants = $this->buildUserCapabilityGrants($menu_items);
        // Build the set of capabilities that our module manages (from current settings only)
        $managed_caps = $this->buildManagedCapabilities($menu_items);
        // Build affected users from settings (union of keys and access_users)
        $affected_user_ids = array_keys($desired_grants);
        $collect_user_ids = function ($nodes) use (&$collect_user_ids) {
            $ids = array();
            foreach ($nodes as $n) {
                if (!is_array($n)) {
                    continue;
                }
                if (!empty($n['access_users']) && is_array($n['access_users'])) {
                    foreach ($n['access_users'] as $u) {
                        $ids[] = intval($u);
                    }
                }
                if (!empty($n['children']) && is_array($n['children'])) {
                    $ids = array_merge($ids, $collect_user_ids($n['children']));
                }
            }
            return $ids;
        };
        $affected_user_ids = array_unique(array_merge($affected_user_ids, $collect_user_ids($menu_items)));

        foreach ($affected_user_ids as $user_id) {
            $user_id = intval($user_id);
            if ($user_id <= 0) {
                continue;
            }
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                continue;
            }

            $target_caps = isset($desired_grants[$user_id]) ? $desired_grants[$user_id] : array();

            // Add desired user caps
            foreach ($target_caps as $cap) {
                $user->add_cap($cap);
            }

            // Remove only user-specific caps that are within our managed set and not desired now
            if (!empty($user->caps) && is_array($user->caps)) {
                foreach (array_keys($user->caps) as $ucap) {
                    if (in_array($ucap, $managed_caps, true) && !in_array($ucap, $target_caps, true)) {
                        $user->remove_cap($ucap);
                    }
                }
            }
        }
    }

    /**
     * Recursively compute user capability grants from menu items.
     * Only per-user explicit grants (user_access_mode === 'grant') are considered.
     * Separators and invalid caps are ignored.
     *
     * @param array $items
     * @return array user_id(int) => array of capabilities(string)
     */
    protected function buildUserCapabilityGrants(array $items): array
    {
        $grants = array();

        $walk = function ($nodeList) use (&$walk, &$grants) {
            foreach ($nodeList as $node) {
                if (!is_array($node)) {
                    continue;
                }

                $type = isset($node['type']) ? $node['type'] : 'item';
                if ($type === 'separator') {
                    // Skip separators
                    if (!empty($node['children']) && is_array($node['children'])) {
                        $walk($node['children']);
                    }
                    continue;
                }

                $cap = isset($node['capability']) ? $node['capability'] : '';
                if (!is_string($cap) || $cap === '' || $cap === 'do_not_allow') {
                    // Ignore invalid/sentinel caps
                    $cap = '';
                }

                $mode = isset($node['user_access_mode']) ? $node['user_access_mode'] : 'grant';
                $access_users = isset($node['access_users']) && is_array($node['access_users']) ? $node['access_users'] : array();

                if ($cap !== '' && $mode === 'grant' && !empty($access_users)) {
                    foreach ($access_users as $uid_str) {
                        $uid = intval($uid_str);
                        if ($uid <= 0) {
                            continue;
                        }
                        if (!isset($grants[$uid])) {
                            $grants[$uid] = array();
                        }
                        if (!in_array($cap, $grants[$uid], true)) {
                            $grants[$uid][] = $cap;
                        }
                    }
                }

                if (!empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children']);
                }
            }
        };

        $walk($items);
        return $grants;
    }

    /**
     * Build the set of capabilities that this module manages based on current settings.
     * Used to constrain removals to only caps we introduced, without storing extra options.
     *
     * @param array $items
     * @return array
     */
    protected function buildManagedCapabilities(array $items): array
    {
        $caps = array();
        $walk = function ($nodes) use (&$walk, &$caps) {
            foreach ($nodes as $n) {
                if (!is_array($n)) {
                    continue;
                }
                $type = isset($n['type']) ? $n['type'] : 'item';
                if ($type !== 'separator' && !empty($n['capability']) && is_string($n['capability']) && $n['capability'] !== 'do_not_allow') {
                    $caps[] = $n['capability'];
                }
                if (!empty($n['children']) && is_array($n['children'])) {
                    $walk($n['children']);
                }
            }
        };
        $walk($items);
        return array_values(array_unique($caps));
    }
}
