<?php

namespace Wpextended\Modules\HideAdminBar;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * Hide Admin Bar module
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('hide-admin-bar');
    }

    /**
     * Initialize the module
     * @return void
     */
    protected function init(): void
    {
        add_action('init', [$this, 'hideAdminBar'], 99999);
    }

    /**
     * Define the settings fields for the module.
     *
     * @return array Settings field configuration
     */
    protected function getSettingsFields(): array
    {
        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Hide Admin Bar', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id'    => 'general',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'          => 'roles',
                        'type'        => 'checkboxes',
                        'title'       => __('User Roles', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the user roles to hide the admin bar for.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices'     => $this->getUserRoles(),
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Get all available WordPress user roles.
     *
     * Retrieves a list of all registered user roles in WordPress
     * and formats them for use in the settings field.
     *
     * @return array Array of role_id => role_name pairs
     */
    protected function getUserRoles(): array
    {
        $roles = [];
        $roles = wp_roles()->get_names();

        foreach ($roles as $role_id => $role_name) {
            $roles[$role_id] = $role_name;
        }

        unset($roles['administrator']);

        return apply_filters('wpextended/hide-admin-bar/user_roles', $roles);
    }

    /**
     * Hide the admin bar for selected user roles.
     *
     * Checks the current user's roles against the selected roles in settings
     * and hides the admin bar if there's a match.
     *
     * @return void
     */
    public function hideAdminBar(): void
    {
        // Get current user's roles
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        // Get module settings
        $roles = $this->getSetting('roles', array());

        // If no roles are selected in settings, return early
        if (empty($roles)) {
            return;
        }

        // Check if any of the user's roles match the selected roles to hide admin bar
        if (array_intersect($roles, $user_roles)) {
            add_filter('show_admin_bar', '__return_false');
        }
    }
}
