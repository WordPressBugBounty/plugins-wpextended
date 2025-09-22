<?php

namespace Wpextended\Modules\DuplicateMenu;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * DuplicateMenu module Bootstrap class.
 * Handles menu duplication functionality in WordPress admin.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('duplicate-menu');
    }

    /**
     * Initialize the module.
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_filter(sprintf('wpextended/%s/show_save_changes_button', $this->module_id), '__return_false');

        // Register REST endpoint
        add_action('rest_api_init', array($this, 'registerRestRoutes'));
    }

    /**
     * Register REST API routes for menu duplication.
     * Sets up the endpoint, callback, and argument validation.
     *
     * @return void
     */
    public function registerRestRoutes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/duplicate-menu', array(
            'methods' => 'POST',
            'callback' => array($this, 'handleDuplicateMenu'),
            'permission_callback' => array($this, 'checkPermission'),
            'args' => array(
                'menu_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'menu_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Check if user has permission to duplicate menus.
     *
     * @return bool Whether the current user can edit theme options
     */
    public function checkPermission()
    {
        return current_user_can('edit_theme_options');
    }

    /**
     * Handle menu duplication REST request.
     *
     * @param WP_REST_Request $request The REST request object
     *
     * @return WP_REST_Response|WP_Error Response object on success, error object on failure
     */
    public function handleDuplicateMenu(\WP_REST_Request $request)
    {
        $menu_id = $request->get_param('menu_id');
        $menu_name = $request->get_param('menu_name');

        // Duplicate the menu
        $new_menu_id = $this->duplicateMenu($menu_id, $menu_name);

        if (!$new_menu_id) {
            return new \WP_Error(
                'duplication_failed',
                __('Failed to duplicate menu. The menu name may already exist.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 400)
            );
        }

        return new \WP_REST_Response(array(
            'success' => true,
            'message' => __('Menu duplicated successfully.', WP_EXTENDED_TEXT_DOMAIN),
            'menu_id' => $new_menu_id,
            'menu_name' => $menu_name,
        ), 200);
    }

    /**
     * Enqueue module assets.
     * Loads CSS and JavaScript files for the module.
     */
    public function enqueueAssets()
    {
        if (!Utils::isPluginScreen($this->module_id)) {
            return;
        }

        Utils::enqueueStyle(
            'wpext-duplicate-menu',
            $this->getPath('assets/css/style.css'),
        );

        Utils::enqueueScript(
            'wpext-duplicate-menu',
            $this->getPath('assets/js/script.js'),
            array('jquery'),
        );

        // Localize script with endpoint URL and nonce
        wp_localize_script('wpext-duplicate-menu', 'wpextDuplicateMenu', array(
            'restUrl' => rest_url(WP_EXTENDED_API_NAMESPACE . '/duplicate-menu'),
            'siteUrl' => site_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n' => array(
                'selected_menu' => __('Please select a menu', WP_EXTENDED_TEXT_DOMAIN),
                'menu_name' => __('Please enter a name for the menu', WP_EXTENDED_TEXT_DOMAIN),
                'view_menu' => __('View Menu', WP_EXTENDED_TEXT_DOMAIN),
            ),
        ));
    }

    /**
     * Get module settings fields configuration.
     *
     * @return array Array of settings field configurations
     */
    public function getSettingsFields()
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Duplicate Menu', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id'    => 'settings',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => '',
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'          => 'selected_menu',
                        'type'        => 'select',
                        'title'       => __('Select Menu', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose a menu to display', WP_EXTENDED_TEXT_DOMAIN),
                        'choices'     => wp_list_pluck(wp_get_nav_menus(), 'name', 'term_id'),
                        'required'    => true,
                    ),
                    array(
                        'id' => 'menu_name',
                        'type' => 'text',
                        'title' => __('Menu Name', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter a name for the new menu', WP_EXTENDED_TEXT_DOMAIN),
                        'required' => true,
                    ),
                    array(
                        'id' => 'duplicate_menu',
                        'type' => 'button',
                        'title' => __('Duplicate Menu', WP_EXTENDED_TEXT_DOMAIN),
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Get module dependencies.
     * Checks if theme supports menus and if any menus exist.
     *
     * @return array Array of dependency errors or empty array if all dependencies are met
     */
    public function dependencies()
    {
        $errors = array();

        // Check if theme supports menus
        if (!current_theme_supports('menus')) {
            $errors[] = array(
                'type'    => 'error',
                'message' => __('This module requires theme support for menus.', WP_EXTENDED_TEXT_DOMAIN),
            );
        }

        // Check if any menus exist
        $menus = wp_get_nav_menus();

        if (empty($menus)) {
            $errors[] = array(
                'type'    => 'error',
                'message' => sprintf(
                    __('No menus have been created yet. Please <a href="%s">create a menu</a>.', WP_EXTENDED_TEXT_DOMAIN),
                    admin_url('nav-menus.php')
                ),
            );
        }

        return $errors;
    }

    /**
     * Duplicate a menu.
     *
     * @param int|null $id ID of the menu to duplicate
     * @param string|null $name Name for the new menu
     *
     * @return int|false The new menu ID on success, false on failure
     */
    public function duplicateMenu($id = null, $name = null)
    {
        if (empty($id) || empty($name)) {
            return false;
        }

        $id = intval($id);
        $name = sanitize_text_field($name);

        if ($this->menuNameExists($name)) {
            return false;
        }

        $new_menu_id = $this->createDuplicateMenu($name);
        if (!$new_menu_id) {
            return false;
        }

        $this->duplicateMenuItems($id, $new_menu_id);

        return $new_menu_id;
    }

    /**
     * Check if a menu name already exists.
     *
     * @param string $name Menu name to check
     *
     * @return bool True if menu name exists, false otherwise
     */
    private function menuNameExists($name)
    {
        $menus = wp_get_nav_menus();
        if (empty($menus)) {
            return false;
        }

        $name_slug = sanitize_title($name);
        foreach ($menus as $menu) {
            if ($name_slug === $menu->slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new menu.
     *
     * @param string $name Name for the new menu
     *
     * @return int|false The new menu ID on success, false on failure
     */
    private function createDuplicateMenu($name)
    {
        return wp_create_nav_menu($name);
    }

    /**
     * Duplicate menu items from one menu to another.
     *
     * @param int $source_id Source menu ID
     * @param int $new_menu_id New menu ID
     *
     * @return void
     */
    private function duplicateMenuItems($source_id, $new_menu_id)
    {
        $source_items = wp_get_nav_menu_items($source_id);
        if (empty($source_items)) {
            return;
        }

        // Map original IDs to new IDs for parent relationships
        $id_mappings = array();

        foreach ($source_items as $index => $menu_item) {
            $args = array(
                'menu-item-db-id' => $menu_item->db_id,
                'menu-item-object-id' => $menu_item->object_id,
                'menu-item-object' => $menu_item->object,
                'menu-item-position' => $index + 1,
                'menu-item-type' => $menu_item->type,
                'menu-item-title' => $menu_item->title,
                'menu-item-url' => $menu_item->url,
                'menu-item-description' => $menu_item->description,
                'menu-item-attr-title' => $menu_item->attr_title,
                'menu-item-target' => $menu_item->target,
                'menu-item-classes' => implode(' ', $menu_item->classes),
                'menu-item-xfn' => $menu_item->xfn,
                'menu-item-status' => $menu_item->post_status
            );

            $new_item_id = wp_update_nav_menu_item($new_menu_id, 0, $args);
            $id_mappings[$menu_item->db_id] = $new_item_id;

            if ($menu_item->menu_item_parent) {
                $args['menu-item-parent-id'] = $id_mappings[$menu_item->menu_item_parent];
                wp_update_nav_menu_item($new_menu_id, $new_item_id, $args);
            }

            do_action('wpextended/duplicate-menu/duplicate_menu_item', $menu_item, $args);
        }
    }
}
