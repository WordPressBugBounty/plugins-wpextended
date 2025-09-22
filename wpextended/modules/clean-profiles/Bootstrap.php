<?php

namespace Wpextended\Modules\CleanProfiles;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('clean-profiles');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_footer', array($this, 'renderStyles'));
        add_filter('wpextended/clean-profiles/sections', array($this, 'addPluginIntegrationSections'), 10, 1);
        add_filter('wpextended/clean-profiles/section_selectors', array($this, 'addPluginIntegrationSelectors'), 10, 1);
    }

    /**
     * Get module settings fields
     *
     * @return array
     */
    protected function getSettingsFields()
    {
        $settings['tabs'][] = array(
        'id' => 'settings',
        'title' => __('Clean Profiles', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
        array(
            'tab_id' => 'settings',
            'section_id'    => 'settings',
            'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
            'section_description' => __('Configure the profile sections and fields that will be hidden on the user profile page.', WP_EXTENDED_TEXT_DOMAIN),
            'section_order' => 10,
            'fields'        => array(
                array(
                    'id'          => 'sections',
                    'type'        => 'checkboxes',
                    'title'       => __('Profile Sections', WP_EXTENDED_TEXT_DOMAIN),
                    'choices'     => $this->getUserProfileSections(),
                ),
                array(
                    'id'          => 'fields',
                    'type'        => 'checkboxes',
                    'title'       => __('Profile Fields', WP_EXTENDED_TEXT_DOMAIN),
                    'choices'     => $this->getUserProfileFields(),
                ),
            ),
        ),
        );

        return $settings;
    }

    /**
     * Get user profile fields
     *
     * @return array
     */
    public function getUserProfileFields()
    {
        $fields = array(
            'user-rich-editing-wrap'           => __('Visual Editor', WP_EXTENDED_TEXT_DOMAIN),
            'user-syntax-highlighting-wrap'   => __('Syntax Highlighting', WP_EXTENDED_TEXT_DOMAIN),
            'user-admin-color-wrap'           => __('Admin Color Scheme', WP_EXTENDED_TEXT_DOMAIN),
            'user-comment-shortcuts-wrap'     => __('Keyboard Shortcuts', WP_EXTENDED_TEXT_DOMAIN),
            'user-admin-bar-front-wrap'       => __('Toolbar', WP_EXTENDED_TEXT_DOMAIN),
            'user-description-wrap'           => __('Biographical Info', WP_EXTENDED_TEXT_DOMAIN),
            'user-role-wrap'                  => __('Role', WP_EXTENDED_TEXT_DOMAIN),
            'user-email-wrap'                 => __('Email', WP_EXTENDED_TEXT_DOMAIN),
            'user-url-wrap'                   => __('Website', WP_EXTENDED_TEXT_DOMAIN),
            'user-pass1-wrap'                 => __('New Password', WP_EXTENDED_TEXT_DOMAIN),
            'user-generate-reset-link-wrap'   => __('Password Reset', WP_EXTENDED_TEXT_DOMAIN),
            'user-sessions-wrap'              => __('Sessions', WP_EXTENDED_TEXT_DOMAIN),
        );

        return apply_filters('wpextended/clean-profiles/fields', $fields);
    }

    /**
     * Get user profile sections
     *
     * @return array
     */
    public function getUserProfileSections()
    {
        $sections = array(
        'user-rich-editing-wrap'    => __('Personal Options', WP_EXTENDED_TEXT_DOMAIN),
        'user-user-login-wrap'      => __('Name', WP_EXTENDED_TEXT_DOMAIN),
        'user-email-wrap'           => __('Contact Info', WP_EXTENDED_TEXT_DOMAIN),
        'user-description-wrap'     => __('About the user', WP_EXTENDED_TEXT_DOMAIN),
        'user-pass1-wrap'           => __('Account Management', WP_EXTENDED_TEXT_DOMAIN),
        'application-passwords'     => __('Application Passwords', WP_EXTENDED_TEXT_DOMAIN),
        );

        return apply_filters('wpextended/clean-profiles/sections', $sections);
    }

    /**
     * Render styles
     *
     * @return void
     */
    public function renderStyles()
    {
        $current_screen = get_current_screen();
        $screens = ['user-edit', 'profile'];

        if (!in_array($current_screen->id, $screens)) {
            return;
        }

        if (empty($this->settings)) {
            return;
        }

        $sections = $this->getSectionSelectors($this->getSetting('sections'));
        $fields = $this->getFieldSelectors($this->getSetting('fields'));

        $selectors = array_merge($sections, $fields);

        printf(
            '<style id="wpextended-clean-profiles-styles">
                %s{
                    display: none;
                }
            </style>',
            implode(',', $selectors)
        );
    }

    /**
     * Get section selectors
     *
     * @param array $sections
     *
     * @return array
     */
    public function getSectionSelectors($sections)
    {
        if (empty($sections)) {
            return array();
        }

        $selectors = array();

        foreach ($sections as $section) {
            $selectors[] = sprintf('table.form-table:has(.%s)', $section);
            $selectors[] = sprintf('h2:has(+ .form-table .%s)', $section);

            if ($section == 'application-passwords') {
                $selectors[] = sprintf('.%s', $section);
            }
        }

        $selectors = apply_filters('wpextended/clean-profiles/section_selectors', $selectors);

        return $selectors;
    }

    /**
     * Get field selectors
     *
     * @param array $fields
     *
     * @return array
     */
    public function getFieldSelectors($fields)
    {
        if (empty($fields)) {
            return array();
        }

        $selectors = array();

        foreach ($fields as $field) {
            $selectors[] = sprintf('.%s', $field);
        }

        $selectors = apply_filters('wpextended/clean-profiles/field_selectors', $selectors);

        return $selectors;
    }

    /**
     * Add integration from other plugins
     *
     * @param array $sections
     *
     * @return array
     */
    public function addPluginIntegrationSections($sections)
    {
        /**
         * WooCommerce
         */
        if (class_exists('woocommerce')) {
            $sections['woocommerce-customer-billing'] = __('[WooCommerce] Billing Address', WP_EXTENDED_TEXT_DOMAIN);
            $sections['woocommerce-customer-shipping'] = __('[WooCommerce] Shipping Address', WP_EXTENDED_TEXT_DOMAIN);
        }

        return $sections;
    }

    /**
     * Add integration selectors
     *
     * @param array $selectors
     *
     * @return array
     */
    public function addPluginIntegrationSelectors($selectors)
    {
        $sections = $this->getSetting('sections');

        /**
         * WooCommerce
         */
        if (class_exists('woocommerce')) {
            if (in_array('woocommerce-customer-billing', $sections)) {
                $selectors[] = '#fieldset-billing';
                $selectors[] = 'h2:has(+ #fieldset-billing)';
            }

            if (in_array('woocommerce-customer-shipping', $sections)) {
                $selectors[] = '#fieldset-shipping';
                $selectors[] = 'h2:has(+ #fieldset-shipping)';
            }
        }

        return $selectors;
    }
}
