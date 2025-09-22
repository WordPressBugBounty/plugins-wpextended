<?php

namespace Wpextended\Modules\MaintenanceMode;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

class Bootstrap extends BaseModule
{
    /**
     * Preview parameter name
     */
    const PREVIEW_PARAM = 'wpextended_maintenance_preview';

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('maintenance-mode');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_action('template_redirect', array($this, 'maybeShowMaintenancePage'));
        add_action('admin_init', array($this, 'maybeRedirectFromAdmin'));
        add_filter('wpextended/maintenance-mode/header_links', array($this, 'addPreviewLink'));
        add_action('admin_bar_menu', array($this, 'addAdminBarNotice'), 1000, 1);
        add_action('admin_head', array($this, 'enqueueAdminBarAssets'));
        add_action('wp_head', array($this, 'enqueueAdminBarAssets'));
    }

    /**
     * Check if maintenance mode should be shown
     *
     * @return void
     */
    public function maybeShowMaintenancePage()
    {
        $show = $this->shouldShowMaintenancePage();

        if ($show) {
            add_filter('show_admin_bar', '__return_false');

            $this->loadMaintenanceTemplate();
            exit;
        }
    }

    /**
     * Redirect unauthorized users from wp-admin during maintenance mode
     *
     * @return void
     */
    public function maybeRedirectFromAdmin()
    {
        // Always allow preview parameter to override everything
        if (isset($_GET[self::PREVIEW_PARAM]) && $_GET[self::PREVIEW_PARAM] === '1') {
            return;
        }

        // Don't redirect during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        // Don't redirect during cron jobs
        if (wp_doing_cron()) {
            return;
        }

        // Apply the same access control logic as frontend
        $show_maintenance = !is_user_logged_in();
        $show_maintenance = apply_filters('wpextended/maintenance-mode/show_maintenance_page', $show_maintenance);

        if ($show_maintenance) {
            // Redirect to the frontend maintenance page
            wp_redirect(home_url('/'));
            exit;
        }
    }

    /**
     * Determine if maintenance page should be shown
     *
     * @return boolean
     */
    protected function shouldShowMaintenancePage()
    {
        // Always allow preview parameter to override everything
        if (isset($_GET[self::PREVIEW_PARAM]) && $_GET[self::PREVIEW_PARAM] === '1') {
            return true;
        }

        // Don't show maintenance page for admin-ajax requests
        if (wp_doing_ajax()) {
            return false;
        }

        // Don't show maintenance page for admin requests
        if (is_admin()) {
            return false;
        }

        // Apply access control filter - pro version will handle authentication logic
        // Default for free version: hide for logged-in users
        $show = !is_user_logged_in();
        return apply_filters('wpextended/maintenance-mode/show_maintenance_page', $show);
    }

    /**
     * Get the preview URL
     *
     * @return string
     */
    public function getPreviewUrl()
    {
        return add_query_arg(self::PREVIEW_PARAM, '1', home_url('/'));
    }

    /**
     * Load the maintenance mode template
     *
     * @return void
     */
    protected function loadMaintenanceTemplate()
    {
        $data = $this->getLayoutData();

        $this->setPageHeaders();

        $layout_1 = $this->getPath("layouts/layout-1.php", true);

        // Allow pro version to handle template loading
        $layout = apply_filters('wpextended/maintenance-mode/layout', $layout_1, $data);

        // If not handled by pro version, load default layout
        if (file_exists($layout) && !empty($layout)) {
            include $layout;
            return;
        }

        include $layout_1;
    }

    /**
     * Get the header status
     *
     * @return void
     */
    public function setPageHeaders()
    {
        $headers = array(
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        );

        $page_header = apply_filters('wpextended/maintenance-mode/page_header', array('status_code' => 503, 'headers' => $headers));

        status_header($page_header['status_code']);

        foreach ($page_header['headers'] as $header => $value) {
            header(sprintf('%s: %s', $header, $value));
        }
    }

    /**
     * Get settings fields for the module
     *
     * @return array Settings
     */
    protected function getSettingsFields()
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'customisation',
            'title' => __('Customisation', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'customisation',
                'section_id'    => 'headline',
                'section_title' => __('Headline Settings', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'headline_text',
                        'type' => 'text',
                        'title' => __('Headline Text', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter the page headline in to the field provided.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN)
                    ),
                    array(
                        'id' => 'headline_colour',
                        'type' => 'color',
                        'title' => __('Headline colour', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose headline colour.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => '#1f2937',
                    ),
                ),
            ),
            array(
                'tab_id' => 'customisation',
                'section_id'    => 'body',
                'section_title' => __('Body Settings', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'body_text',
                        'type' => 'editor',
                        'title' => __('Body Text', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter the body text in to the field provided.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => __('Site will be available soon. Thank you for your patience!', WP_EXTENDED_TEXT_DOMAIN),
                        'editor_settings' => array(
                            'textarea_rows' => 5,
                            'media_buttons' => false,
                        )
                    ),
                    array(
                        'id' => 'body_colour',
                        'type' => 'color',
                        'title' => __('Body colour', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose body colour.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => '#676c76',
                    ),
                ),
            ),
            array(
                'tab_id' => 'customisation',
                'section_id'    => 'footer',
                'section_title' => __('Footer Settings', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'footer_text',
                        'type' => 'text',
                        'title' => __('Footer Text', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter the page footer text in to the field provided.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => sprintf('© %s 2025', get_bloginfo('name'))
                    ),
                    array(
                        'id' => 'footer_colour',
                        'type' => 'color',
                        'title' => __('Footer colour', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose footer colour.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => '#676c76',
                    ),
                ),
            ),
            array(
                'tab_id' => 'customisation',
                'section_id'    => 'logo',
                'section_title' => __('Logo Settings', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'enable_logo',
                        'type' => 'select',
                        'title' => 'Enable Logo',
                        'choices' => array(
                            'yes' => __('Yes', WP_EXTENDED_TEXT_DOMAIN),
                            'no' => __('No', WP_EXTENDED_TEXT_DOMAIN)
                        ),
                        'default' => 'no'
                    ),
                    array(
                        'id' => 'logo_image',
                        'type' => 'file',
                        'title' => __('Select Logo', WP_EXTENDED_TEXT_DOMAIN),
                        'mime_types' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                        'description' => __('Select a logo image for the maintenance page.', WP_EXTENDED_TEXT_DOMAIN),
                        'show_if' => array(
                            array(
                                'field' => 'enable_logo',
                                'value' => array('yes')
                            )
                        )
                    ),
                    array(
                        'id' => 'logo_width',
                        'type' => 'number',
                        'title' => __('Logo Width (px)', WP_EXTENDED_TEXT_DOMAIN),
                        'suffix' => 'px',
                        'default' => 180,
                        'show_if' => array(
                            array(
                                'field' => 'enable_logo',
                                'value' => array('yes')
                            )
                        )
                    ),
                ),
            ),
            array(
                'tab_id' => 'customisation',
                'section_id'    => 'background',
                'section_title' => __('Background Settings', WP_EXTENDED_TEXT_DOMAIN),
                'fields'        => array(
                    array(
                        'id' => 'background_colour',
                        'type' => 'color',
                        'title' => __('Background Colour', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Choose the base background colour for the maintenance page.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => '#f5f5f5'
                    ),
                    array(
                        'id' => 'enable_background_image',
                        'type' => 'toggle',
                        'title' => __('Enable Background Image', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Add an optional background image overlay.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => false
                    ),
                    array(
                        'id' => 'background_image',
                        'type' => 'file',
                        'title' => __('Background Image', WP_EXTENDED_TEXT_DOMAIN),
                        'mime_types' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                        'description' => __('Select a background image to overlay on the background colour.', WP_EXTENDED_TEXT_DOMAIN),
                        'show_if' => array(
                            array(
                                'field' => 'enable_background_image',
                                'value' => array(true)
                            )
                        )
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Get layout data for the maintenance page
     *
     * @return array
     */
    public function getLayoutData()
    {
        $settings = $this->getSettings();

        // Base data structure using existing settings
        $data = [
            // General Settings
            'layout' => array(
                'type' => 'custom',
                'custom_layout' => 'layout-1',
                'existing_page' => '',
            ),

            // Header Settings
            'header' => [
                'enabled' => !empty($this->getSetting('enable_logo')),
                'logo' => [
                    'enabled' => !empty($this->getSetting('enable_logo')),
                    'type' => 'image',
                    'image' => $this->getSetting('logo_image.0', ''),
                    'dimensions' => [
                        'width' => $this->getSetting('logo_width', '120'),
                    ],
                    'alt' => get_bloginfo('name') . ' ' . __('Logo', WP_EXTENDED_TEXT_DOMAIN),
                ],
            ],

            // Content Settings - using existing setting fields
            'content' => [
                'headline' => [
                    'text' => $this->getSetting('headline_text', __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN)),
                    'color' => $this->getSetting('headline_colour', '#1f2937'),
                ],
                'body' => [
                    'text' => $this->getSetting('body_text', __('We are currently performing scheduled maintenance to improve your experience. Please check back soon.', WP_EXTENDED_TEXT_DOMAIN)),
                    'color' => $this->getSetting('body_colour', '#676c76'),
                ],
                'footer' => [
                    'text' => $this->getSetting('footer_text', sprintf('© %s %s', get_bloginfo('name'), date('Y'))),
                    'color' => $this->getSetting('footer_colour', '#676c76'),
                ],
            ],

            // Background Settings - color always present, image optional
            'background' => [
                'color' => $this->getSetting('background_colour', '#f5f5f5'),
                'image' => (!empty($this->getSetting('enable_background_image')) && !empty($this->getSetting('background_image.0', '')))
                    ? wp_get_attachment_url($this->getSetting('background_image.0', ''))
                    : '',
                'enable_image' => !empty($this->getSetting('enable_background_image')) && !empty($this->getSetting('background_image.0', '')),
            ],

            // SEO and Meta
            'meta' => [
                'title' => sprintf('%s - %s', get_bloginfo('name'), __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN)),
                'description' => __('We are currently performing scheduled maintenance. Please check back soon.', WP_EXTENDED_TEXT_DOMAIN),
            ],
        ];

        // Add CSS file paths
        $data['css_files'] = [
            'base' => $this->getCssUrl('base'),
            'layout' => $this->getCssUrl('layout-1'),
        ];

        // Allow pro version to extend the data
        return apply_filters('wpextended/maintenance-mode/layout_data', $data);
    }

    /**
     * Get the URL for a CSS file
     *
     * @param string $filename The CSS filename
     * @return string The URL to the CSS file
     */
    public function getCssUrl($filename, $pro = false)
    {
        $path = $pro ? 'pro/assets/css/' : 'assets/css/';
        return $this->getPath(sprintf('%s%s.css', $path, $filename), false);
    }

    /**
     * Add header links
     *
     * @return array
     */
    public function addPreviewLink($links)
    {
        $links[] = array(
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" color="currentColor" fill="none"><path d="M21.544 11.045C21.848 11.4713 22 11.6845 22 12C22 12.3155 21.848 12.5287 21.544 12.955C20.1779 14.8706 16.6892 19 12 19C7.31078 19 3.8221 14.8706 2.45604 12.955C2.15201 12.5287 2 12.3155 2 12C2 11.6845 2.15201 11.4713 2.45604 11.045C3.8221 9.12944 7.31078 5 12 5C16.6892 5 20.1779 9.12944 21.544 11.045Z" stroke="currentColor" stroke-width="1.5" /><path d="M15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15C13.6569 15 15 13.6569 15 12Z" stroke="currentColor" stroke-width="1.5" /></svg>',
            'text' => __('Preview', WP_EXTENDED_TEXT_DOMAIN),
            'attributes' => array(
                'href' => $this->getPreviewUrl(),
                'target' => '_blank',
                'aria-label' => __('Open preview of maintenance mode, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
                'title' => __('Open preview of maintenance mode, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
            )
        );

        return $links;
    }

    /**
     * Determine if the admin bar notice should be shown
     *
     * @return bool
     */
    protected function shouldShowAdminBarNotice()
    {
        // Don't show if user can't manage options
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Don't show if admin bar is not showing
        if (!is_admin_bar_showing()) {
            return false;
        }

        return apply_filters('wpextended/maintenance-mode/is_active', true);
    }

    /**
     * Enqueue admin bar assets
     *
     * @return void
     */
    public function enqueueAdminBarAssets()
    {
        if (!$this->shouldShowAdminBarNotice()) {
            return;
        }

        // Inline CSS for admin bar styling
        $css = '
        #wp-admin-bar-wpext-maintenance-mode a {
            color: #fff !important;
            background: #e0281f !important;
            transition: 0.1s ease-in-out;
        }

        #wp-admin-bar-wpext-maintenance-mode a:hover,
        #wp-admin-bar-wpext-maintenance-mode a:focus {
            text-decoration: underline;
            background: #bd2b24 !important;
        }

        #wp-admin-bar-wpext-maintenance-mode:has(+ #wp-admin-bar-wpext-indexing-notice) {
            margin-right: 10px;
        }
        ';

        printf(
            '<style id="%s">
                %s
            </style>',
            'wpext-maintenance-mode-admin-bar',
            $css
        );
    }

    /**
     * Add maintenance mode notice to the admin bar
     *
     * @param \WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance
     * @return void
     */
    public function addAdminBarNotice($wp_admin_bar)
    {
        if (!$this->shouldShowAdminBarNotice()) {
            return;
        }

        $title = apply_filters(
            'wpextended/maintenance-mode/admin_bar_title',
            __('Maintenance Mode Active', WP_EXTENDED_TEXT_DOMAIN),
            $wp_admin_bar
        );

        $wp_admin_bar->add_node(array(
            'id'     => 'wpext-maintenance-mode',
            'parent' => 'top-secondary',
            'title'  => $title,
            'href'   => Utils::getModulePageLink('maintenance-mode'),
        ));
    }
}
