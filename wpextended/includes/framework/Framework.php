<?php

/**
 * WP Extended Settings Framework
 *
 * @version 1.0.0
 *
 * @package Wpextended
 */

namespace Wpextended\Includes\Framework;

use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;

if (! class_exists('Framework')) {
    /**
     * WpextendedFramework class
     */
    class Framework
    {
        private static $instances = [];

        /**
         * Settings wrapper.
         *
         * @var array
         */
        private $settings_wrapper;

        /**
         * Settings.
         *
         * @var array
         */
        private $settings;

        /**
         * Tabs.
         *
         * @var array
         */
        private $tabs;

        /**
         * Option group.
         *
         * @var string
         */
        private $option_group;

        /**
         * Settings page.
         *
         * @var array
         */
        public $settings_page = array();

        /**
         * Whether to always show the sidebar.
         *
         * @var bool
         */
        private $always_show_sidebar = false;

        /**
         * Options path.
         *
         * @var string
         */
        private $options_path;

        /**
         * Options URL.
         *
         * @var string
         */
        private $options_url;

        /**
         * Setting defaults.
         *
         * @var array
         */
        protected $setting_defaults = array(
            'id'           => 'default_field',
            'title'        => 'Default Field',
            'description'  => '',
            'std'          => '',
            'type'         => 'text',
            'placeholder'  => '',
            'choices'      => array(),
            'class'        => '',
            'subfields'    => array(),
            'autocomplete' => '',
            'attributes'   => array(),
            'custom_args'  => array(),
            'required'     => false,  // Add required field support
        );

        /**
         * Framework constructor.
         *
         * @param null|string $settings_file Path to a settings file, or null if you pass the option_group manually and construct your settings with a filter.
         * @param bool|string $option_group  Option group name, usually a short slug.
         */
        public function __construct($option_group = false, array $settings = [])
        {
            $this->option_group = $option_group;
            $this->settings = $settings;
            $this->always_show_sidebar = apply_filters(sprintf('wpextended/%s/always_show_sidebar', $this->option_group), false);

            $this->initialize_paths();
            $this->construct_settings();
            $this->setup_admin_hooks();
        }

        public static function getInstance($option_group, array $settings = [])
        {
            if (!isset(self::$instances[$option_group])) {
                self::$instances[$option_group] = new self($option_group, $settings);
            } else {
            }
            return self::$instances[$option_group];
        }

        /**
         * Generate option group from settings file name.
         *
         * @param string $settings_file Path to settings file.
         * @return string Generated option group name.
         *
         * @refactored = true
         */
        public function generate_option_group_from_file($settings_file)
        {
            return preg_replace('/[^a-z0-9]+/i', '', basename($settings_file, '.php'));
        }

        /**
         * Initialize plugin paths.
         *
         * @refactored = true
         */
        public function initialize_paths()
        {
            $this->options_path = plugin_dir_path(__FILE__);
            $this->options_url  = WP_EXTENDED_URL . 'includes/framework';
        }

        /**
         * Set up admin hooks.
         *
         * @refactored = true
         */
        public function setup_admin_hooks()
        {
            if (! is_admin()) {
                return;
            }

            add_action('admin_init', array($this, 'admin_init'));
            add_action(sprintf('wpextended/%s/do_settings_sections', $this->option_group), array($this, 'do_tabless_settings_sections'), 10);

            // Move page-specific hooks to admin_menu with later priority to ensure it runs after menu registration
            add_action('admin_menu', array($this, 'setup_page_specific_hooks'), 999);

            $this->setup_tab_hooks();
            $this->setup_ajax_hooks();
        }

        /**
         * Set up page-specific hooks.
         *
         * @refactored = true
         */
        public function setup_page_specific_hooks()
        {
            if (! $this->is_settings_page()) {
                return;
            }

            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('admin_body_class', array($this, 'admin_body_class'));
        }

        /**
         * Check if current page is the settings page.
         *
         * @return bool Whether the current page is the settings page.
         *
         * @refactored = true
         */
        public function is_settings_page()
        {
            if (!isset($this->settings_page['slug'])) {
                return false;
            }

            $current_page = filter_input(INPUT_GET, 'page');
            return !empty($current_page) && $current_page === $this->settings_page['slug'];
        }

        public function admin_body_class($classes)
        {
            $is_settings_page = $this->is_settings_page();
            if ($is_settings_page) {
                $classes .= ' wpextended-settings-page';
            }

            return apply_filters(sprintf('wpextended/%s/admin_body_class', $this->option_group), $classes, $is_settings_page);
        }

        /**
         * Set up tab-related hooks.
         *
         * @refactored = true
         */
        public function setup_tab_hooks()
        {
            add_action(sprintf('wpextended/%s/before_settings', $this->option_group), array($this, 'render_sidebar_tab_links_wrapper'));

            if ($this->has_tabs()) {
                remove_action(sprintf('wpextended/%s/do_settings_sections', $this->option_group), array($this, 'do_tabless_settings_sections'), 10);
                add_action(sprintf('wpextended/%s/do_settings_sections', $this->option_group), array($this, 'do_tabbed_settings_sections'), 10);
            }

            add_filter(sprintf('wpextended/%s/sidebar_wrapper_start', $this->option_group), array($this, 'sidebar_wrapper_start'), 10, 1);
            add_filter(sprintf('wpextended/%s/sidebar_wrapper_end', $this->option_group), array($this, 'sidebar_wrapper_end'), 10, 1);
        }

        /**
         * Set up AJAX hooks.
         *
         * @refactored = true
         */
        public function setup_ajax_hooks()
        {
            add_action('wp_ajax_wpext_export_settings', array($this, 'export_settings'));
            add_action('wp_ajax_wpext_import_settings', array($this, 'import_settings'));
        }

        /**
         * Construct settings.
         *
         * @refactored = true
         */
        public function construct_settings()
        {
            $this->settings_wrapper = $this->get_registered_settings();

            if (! is_array($this->settings_wrapper)) {
                return;
            }

            $this->extract_tabs_and_settings();
            $this->setup_settings_page();
        }

        /**
         * Get registered settings.
         *
         * @return array Registered settings.
         *
         * @refactored = true
         */
        private function get_registered_settings()
        {
            return apply_filters(sprintf('wpextended/%s/register_settings', $this->option_group), $this->settings);
        }

        /**
         * Extract tabs and settings from settings wrapper.
         *
         * @refactored = true
         */
        private function extract_tabs_and_settings()
        {
            if (isset($this->settings_wrapper['sections'])) {
                $tabs = isset($this->settings_wrapper['tabs']) ? $this->settings_wrapper['tabs'] : array();

                // remove duplicate tabs by id
                $unique_tabs = array();

                foreach ($tabs as $tab) {
                    if (isset($tab['id']) && !isset($unique_tabs[$tab['id']])) {
                        $unique_tabs[$tab['id']] = $tab;
                    }
                }

                $this->tabs = $unique_tabs;

                $this->settings = $this->settings_wrapper['sections'];
            } else {
                $this->settings = $this->settings_wrapper;
            }
        }

        /**
         * Set up settings page.
         *
         * @refactored = true
         */
        private function setup_settings_page()
        {
            $this->settings_page = [
                'slug' => sprintf(
                    'wpextended-%s-settings',
                    str_replace('_', '-', $this->option_group)
                ),
                'title' => '',  // Will be set by add_settings_page
                'capability' => 'manage_options',  // Default capability
            ];
        }

        /**
         * Get the option group for this instance.
         *
         * @return string The option group.
         *
         * @refactored = true
         */
        public function get_option_group()
        {
            return $this->option_group;
        }

        /**
         * Get the option name for settings
         *
         * @return string
         */
        private function getOptionName()
        {
            return Utils::getOptionName($this->option_group);
        }

        /**
         * Register internal WordPress settings.
         *
         * @refactored = true
         */
        public function admin_init()
        {
            register_setting(
                $this->option_group,
                Utils::getOptionName($this->option_group),
                array($this, 'settings_validate')
            );
            $this->process_settings();
        }

        /**
         * Get settings for this framework instance
         *
         * @return array
         */
        private function get_instance_settings()
        {
            return Utils::getSettings($this->option_group);
        }

        /**
         * Get a specific setting
         *
         * @param string $field_id Field ID
         * @param mixed $default Default value
         * @return mixed
         */
        public function getSetting($field_id, $default = null)
        {
            return Utils::getSetting($this->option_group, $field_id, $default);
        }

        /**
         * Update settings
         *
         * @param array $settings Settings to update
         * @return bool
         */
        public function updateSettings($settings)
        {
            return Utils::updateSettings($this->option_group, $settings);
        }

        /**
         * Update a specific setting
         *
         * @param string $field_id Field ID
         * @param mixed $value Value to set
         * @return bool
         */
        public function updateSetting($field_id, $value)
        {
            return Utils::updateSetting($this->option_group, $field_id, $value);
        }

        /**
         * Delete settings
         *
         * @return bool
         */
        public function deleteSettings()
        {
            return Utils::deleteSettings($this->option_group);
        }

        /**
         * Delete a specific setting
         *
         * @param string $field_id Field ID
         * @return bool
         */
        public function deleteSetting($field_id)
        {
            return Utils::deleteSetting($this->option_group, $field_id);
        }

        /**
         * Add settings page.
         *
         * @param array $args Settings page arguments.
         *
         * @refactored = true
         */
        public function add_settings_page($args)
        {

            $defaults = array(
                'parent_slug' => null,
                'page_slug'   => '',
                'page_title'  => '',
                'menu_title'  => '',
                'capability'  => 'manage_options',
            );

            $args = wp_parse_args($args, $defaults);

            // Update settings page properties
            $this->settings_page['title'] = $args['page_title'];
            $this->settings_page['capability'] = $args['capability'];

            // Override the default slug if a custom one is provided
            if (!empty($args['page_slug'])) {
                $this->settings_page['slug'] = $args['page_slug'];
            }

            if ($args['parent_slug'] == null) {
                $this->add_menu_page($args);
            } else {
                $this->add_submenu_page($args);
            }
        }

        /**
         * Add submenu page.
         *
         * @param array $args Submenu page arguments.
         *
         * @refactored = true
         */
        private function add_submenu_page($args)
        {
            // Ensure page title is never null to prevent strip_tags() deprecation warnings
            $page_title = $this->settings_page['title'] ?? $args['menu_title'] ?? 'Admin Page';

            add_submenu_page(
                $args['parent_slug'],
                $page_title,
                $args['menu_title'],
                $args['capability'],
                $this->settings_page['slug'],
                array($this, 'settings_page_content')
            );
        }

        /**
         * Add menu page.
         *
         * @param array $args Menu page arguments.
         *
         * @refactored = true
         */
        private function add_menu_page($args)
        {
            // Ensure page title is never null to prevent strip_tags() deprecation warnings
            $page_title = $this->settings_page['title'] ?? $args['menu_title'] ?? 'Admin Page';

            add_menu_page(
                $page_title,
                $args['menu_title'],
                $args['capability'],
                $this->settings_page['slug'],  // Use the stored settings page slug
                array($this, 'settings_page_content'),
                $this->get_menu_icon_url(),
                $this->get_menu_position()
            );
        }

        /**
         * Get menu icon URL.
         *
         * @return string Menu icon URL.
         *
         * @refactored = true
         */
        private function get_menu_icon_url()
        {
            return apply_filters(sprintf('wpextended/%s/menu_icon_url', $this->option_group), '');
        }

        /**
         * Get menu position.
         *
         * @return int|null Menu position.
         *
         * @refactored = true
         */
        private function get_menu_position()
        {
            return apply_filters(sprintf('wpextended/%s/menu_position', $this->option_group), null);
        }

        /**
         * Render the settings page content.
         *
         * @refactored = true
         */
        public function settings_page_content()
        {

            if (! $this->user_can_view_settings_page()) {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wpext'));
            }

            $this->render_settings_page();
        }

        /**
         * Check if the current user can view the settings page.
         *
         * @return bool
         *
         * @refactored = true
         */
        private function user_can_view_settings_page()
        {
            return current_user_can($this->settings_page['capability']);
        }

        /**
         * Render the settings page.
         *
         * @refactored = true
         */
        private function render_settings_page()
        {
            ?>
            <div class="wpext-settings wpext-settings--<?php echo esc_attr($this->option_group); ?>">
                <?php $this->render_notices_placeholder(); ?>
                <?php $this->render_settings_content(); ?>
            </div>
            <?php
        }

        /**
         * Render the settings header.
         *
         * @refactored = true
         */
        public function render_settings_header()
        {
            ?>
            <?php do_action(sprintf('wpextended/%s/before_header', $this->option_group)); ?>
            <div class="wpext-settings__header">
                <div class="wpext-settings__header-inner">
                    <?php do_action(sprintf('wpextended/%s/before_title', $this->option_group)); ?>
                    <p>
                        <?php echo esc_html__('WP Extended', WP_EXTENDED_TEXT_DOMAIN); ?>
                        <?php if (WP_EXTENDED_PRO) { ?>
                            <span class="wpext-settings__header-pro">Pro</span>
                        <?php } ?>
                    </p>
                    <?php do_action(sprintf('wpextended/%s/after_title', $this->option_group)); ?>
                </div>
            </div>
            <?php do_action(sprintf('wpextended/%s/after_header', $this->option_group)); ?>
            <?php
        }

        /**
         * Get the settings title.
         *
         * @return string
         *
         * @refactored = true
         */
        private function get_settings_title()
        {
            return apply_filters(sprintf('wpextended/%s/title', $this->option_group), $this->settings_page['title']);
        }

        /**
         * Get allowed HTML tags for wp_kses.
         *
         * @return array
         *
         * @refactored = true
         */
        private function get_allowed_html_tags()
        {
            global $allowedposttags;
            $allowed_tags = $allowedposttags;
            $allowed_tags['data'] = array();
            return $allowed_tags;
        }

        /**
         * Render the settings content.
         *
         * @refactored = true
         */
        private function render_settings_content()
        {
            ?>
            <div class="wpext-settings__content">
                <?php
                $this->render_settings_form();
                ?>
            </div>
            <?php
        }

        /**
         * Render a placeholder for notices.
         *
         * @refactored = true
         */
        private function render_notices_placeholder()
        {
            ?>
            <div class="wpext-notices">
                <h2 class="screen-reader-text"><?php echo esc_html($this->get_settings_title()); ?></h2>
                <?php
                // Display WordPress admin notices
                do_action('admin_notices');
                // Display settings-specific errors/notices
                settings_errors();
                ?>
            </div>
            <?php
        }

        /**
         * Render the settings form.
         *
         * @refactored = true
         */
        public function render_settings_form()
        {
            do_action(sprintf('wpextended/%s/before_settings', $this->option_group));
            ?>
            <div class="wpext-settings__form wpext-settings__form--has-sidebar">
                <div class="wpext-settings__form-header">
                    <div class="wpext-settings__form-header-row">
                        <h1 class="wpext-settings__title <?php echo !$this->get_settings_title() ? 'screen-reader-text' : ''; ?>">
                            <?php echo esc_html($this->settings_page['title']); ?>
                        </h1>
                        <?php do_action(sprintf('wpextended/%s/settings_form_header_inner', $this->option_group)); ?>
                    </div>
                    <?php do_action(sprintf('wpextended/%s/after_settings_form_header', $this->option_group)); ?>
                </div>
                <?php
                do_action(sprintf('wpextended/%s/before_settings_form', $this->option_group));

                if (!apply_filters(sprintf('wpextended/%s/hide_settings_form', $this->option_group), false)) :
                    ?>
                    <form action="options.php" method="post" novalidate enctype="multipart/form-data" style="display: none;">
                        <?php
                        $this->render_settings_fields();
                        $this->render_settings_sections();
                        $this->render_submit_button();
                        ?>
                    </form>
                    <?php
                endif;

                do_action(sprintf('wpextended/%s/after_settings_form', $this->option_group));
                ?>
            </div>
            <?php
            do_action(sprintf('wpextended/%s/after_settings', $this->option_group));
        }

        /**
         * Render settings fields.
         *
         * @refactored = true
         */
        private function render_settings_fields()
        {
            do_action(sprintf('wpextended/%s/before_settings_fields', $this->option_group));
            settings_fields($this->option_group);
        }

        /**
         * Render settings sections.
         *
         * @refactored = true
         */
        private function render_settings_sections()
        {
            do_action(sprintf('wpextended/%s/do_settings_sections', $this->option_group));
        }

        /**
         * Custom implementation of do_settings_sections
         *
         * @param string $page The slug name of the page whose settings sections you want to output
         */
        public function do_settings_sections($page)
        {
            global $wp_settings_sections, $wp_settings_fields;

            if (!isset($wp_settings_sections[$page])) {
                return;
            }

            foreach ((array) $wp_settings_sections[$page] as $section) {
                echo '<div class="wpext-section">';

                if ($section['title']) {
                    echo "<h2 class='wpext-section-title'>{$section['title']}</h2>";
                }

                if ($section['callback']) {
                    call_user_func($section['callback'], $section);
                }

                if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                    continue;
                }

                echo '<div class="wpext-fields">';
                $this->do_settings_fields($page, $section['id']);
                echo '</div>';

                echo '</div>';
            }
        }

        public function do_settings_fields($page, $section)
        {
            global $wp_settings_fields;

            if (! isset($wp_settings_fields[$page][$section])) {
                return;
            }

            foreach ((array) $wp_settings_fields[$page][$section] as $field) {
                $field_type = isset($field['args']['field']['type']) ? $field['args']['field']['type'] : '';

                $field_classes = ['wpext-field', sprintf('wpext-field--%s', $field_type)];
                if (!empty($field['args']['class'])) {
                    $field_classes[] = $field['args']['class'];
                }

                $field_attributes = [
                    'class' => implode(' ', $field_classes),
                ];

                if (isset($field['args']['field']['slide'])) {
                    $field_attributes['data-slide'] = $field['args']['field']['slide']
                        ? 'true'
                        : 'false';
                }

                $field_attributes = $this->generate_html_atts($field_attributes);

                ob_start();
                call_user_func($field['callback'], $field['args']);
                $field_callback = ob_get_clean();

                if ($field_type === 'custom' || $field_type === 'modules' || $field_type === 'table') {
                    printf(
                        '<div %s>%s</div>',
                        $field_attributes,
                        $field_callback
                    );
                    continue;
                }

                if ($field_type === 'button') {
                    printf(
                        '<div %s>
                            <div class="wpext-label"></div>
                            <div class="wpext-input">%s</div>
                        </div>',
                        $field_attributes,
                        $field_callback
                    );
                    continue;
                }

                // Get the field ID for the label's for attribute
                $field_id = isset($field['args']['field']['id']) ? $field['args']['field']['id'] : '';

                printf(
                    '<div %s>
                        <div class="wpext-label">
                            <label for="%s">%s</label>
                        </div>
                        <div class="wpext-input">%s</div>
                    </div>',
                    $field_attributes,
                    esc_attr($field_id),
                    $field['title'],
                    $field_callback
                );
            }
        }

        /**
         * Render submit button.
         *
         * @refactored = true
         */
        private function render_submit_button()
        {
            if ($this->show_save_changes_button()) {
                ?>
                <div class="wpext__save-tray">
                    <p><?php esc_html_e('You have unsaved changes.', WP_EXTENDED_TEXT_DOMAIN); ?></p>
                    <div class="wpext__save-tray-buttons">
                        <button class="wpext-button--alt wpext__save-tray-button--cancel">Cancel</button>
                        <input type="submit" class="wpext-button--primary wpext__save-tray-button--save" value="<?php esc_attr_e('Save Changes'); ?>" />
                    </div>
                </div>
                <?php
            }
        }

        /**
         * Check if the save changes button should be shown.
         *
         * @return bool
         *
         * @refactored = true
         */
        private function show_save_changes_button()
        {
            return apply_filters(sprintf('wpextended/%s/show_save_changes_button', $this->option_group), true);
        }

        /**
         * Render tabless settings sections.
         *
         * @refactored = true
         */
        public function do_tabless_settings_sections()
        {
            ?>
            <div class="wpext-tabless">
                <?php $this->do_settings_sections($this->option_group); ?>
            </div>
            <?php
        }

        /**
         * Render tabbed settings sections.
         */
        public function do_tabbed_settings_sections()
        {
            $output = '';
            foreach ($this->tabs as $tab_data) {
                $output .= $this->render_tab_section($tab_data);
            }
            echo $output;
        }

        /**
         * Render a single tab section.
         *
         * @param array $tab_data Tab data.
         */
        private function render_tab_section($tab_data)
        {
            $tab_id = esc_attr($tab_data['id']);
            $style = '';

            // Add initial display:none if show_if conditions exist
            if (isset($tab_data['show_if'])) {
                $style = ' style="display: none;"';
            }
            ?>
            <div id="tab-<?php echo $tab_id; ?>" class="wpext-tab wpext-tab--<?php echo $tab_id; ?>" <?php echo $style; ?>>
                <?php
                echo '<div class="postbox">';
                $this->do_settings_sections(sprintf('%s_%s', $this->option_group, $tab_id));
                echo '</div>';
                ?>
            </div>
            <?php
        }

        /**
         * Check if tab links should be shown.
         *
         * @return bool
         *
         * @refactored = true
         */
        private function should_show_tab_links()
        {
            return apply_filters(sprintf('wpextended/%s/show_tab_links', $this->option_group), true);
        }


        /**
         * Render sidebar tab links wrapper.
         */
        public function render_sidebar_tab_links_wrapper()
        {
            if (!$this->should_show_tab_links()) {
                return;
            }

            // Allow filtering before sidebar - both generic and option group specific
            do_action('wpextended/before_sidebar');
            do_action(sprintf('wpextended/%s/before_sidebar', $this->option_group));

            $output = '<div class="wpext-sidebar">';

            $output .= '<div class="wpext-sidebar__inner">';

            // Allow filtering sidebar wrapper start - both generic and option group specific
            $output = apply_filters(sprintf('wpextended/%s/sidebar_wrapper_start', $this->option_group), $output);

            $output .= '<ul class="wpext-nav" data-type="tabs">';
            $active_tab = true;

            // Get array of tab links that can be filtered
            $tab_links = array();

            // Add core tab links
            foreach ($this->tabs as $tab_data) {
                if (! $this->tab_has_settings($tab_data['id'])) {
                    continue;
                }

                $classes = [];
                if ($active_tab) {
                    $classes[] = 'wpext-nav__item--active';
                }
                if (isset($tab_data['class'])) {
                    $classes[] = $tab_data['class'];
                }

                // Add show/hide classes if conditions exist
                if (isset($tab_data['show_if'])) {
                    $classes[] = self::add_show_hide_classes($tab_data, 'show_if');
                }
                if (isset($tab_data['hide_if'])) {
                    $classes[] = self::add_show_hide_classes($tab_data, 'hide_if');
                }

                $tab_links[] = array(
                    'href' => '#tab-' . esc_attr($tab_data['id']),
                    'title' => wp_kses_post($tab_data['title']),
                    'class' => implode(' ', array_filter($classes))
                );
                $active_tab = false;
            }

            $tab_links = apply_filters(sprintf('wpextended/%s/tab_links', $this->option_group), $tab_links);

            // Render each tab link
            foreach ($tab_links as $link) {
                $classes = array('wpext-nav__item');
                if (!empty($link['class'])) {
                    $classes[] = $link['class'];
                }
                if (!empty($link['custom_class'])) {
                    $classes[] = $link['custom_class'];
                }

                $li_atts = array(
                    'class' => implode(' ', array_filter($classes))
                );

                // Add inline style if the tab has show/hide conditions
                if (strpos($link['class'], 'show-if') !== false || strpos($link['class'], 'hide-if') !== false) {
                    $li_atts['style'] = 'display: none;';
                }

                $a_atts = array(
                    'class' => 'wpext-nav__item-link',
                    'href' => $link['href']
                );

                $output .= sprintf(
                    '<li %s><a %s>%s</a></li>',
                    $this->generate_html_atts($li_atts),
                    $this->generate_html_atts($a_atts),
                    wp_kses_post($link['title'])
                );
            }

            $output .= '</ul>';

            // Allow filtering sidebar wrapper end - both generic and option group specific
            $output = apply_filters(sprintf('wpextended/%s/sidebar_wrapper_end', $this->option_group), $output);

            $output .= '</div>';

            $output .= sprintf(
                '<a class="wpext-sidebar__footer" href="%s" target="_blank" aria-label="%s">%s</a>',
                Utils::generateTrackedLink('https://wpextended.io/changelog/', 'changelog'),
                sprintf(
                    /* translators: %s: version number */
                    esc_attr__('View changelog for version %s, open in new tab', WP_EXTENDED_TEXT_DOMAIN),
                    WP_EXTENDED_VERSION
                ),
                /* translators: %s: version number */
                sprintf(
                    /* translators: %s: version number */
                    esc_html__('Version %s', WP_EXTENDED_TEXT_DOMAIN),
                    WP_EXTENDED_VERSION
                )
            );

            $output .= '</div>';

            echo $output;

            // Allow filtering after sidebar - both generic and option group specific
            do_action('wpextended/after_sidebar');
            do_action(sprintf('wpextended/%s/after_sidebar', $this->option_group));
        }

        public function sidebar_wrapper_start($output)
        {
            $label = WP_EXTENDED_PRO ? sprintf(
                /* translators: %s: PRO label */
                esc_html__('WP Extended %s', WP_EXTENDED_TEXT_DOMAIN),
                '<span class="wpext-sidebar__pro-label">' . esc_html__('PRO', WP_EXTENDED_TEXT_DOMAIN) . '</span>'
            ) : esc_html__('WP Extended', WP_EXTENDED_TEXT_DOMAIN);

            $icon = WP_EXTENDED_URL . 'admin/assets/icons/logo.svg';

            return $output . sprintf(
                '<div class="wpext-sidebar__title">
                    <a href="/wp-admin/admin.php?page=wpextended">
                        <img src="%1$s" />
                        %2$s
                    </a>
                </div>',
                $icon,
                $label
            );
        }

        public function sidebar_wrapper_end($output)
        {
            $links = array(
                array(
                    'title' => 'Modules',
                    'href' => WP_EXTENDED_SITE_URL . '/wp-admin/admin.php?page=wpextended',
                    'icon' => sprintf('%s/assets/icons/modules.svg', $this->options_url),
                ),
                array(
                    'title' => 'Settings',
                    'href' => admin_url('admin.php?page=wpextended-settings'),
                    'icon' => sprintf('%s/assets/icons/settings.svg', $this->options_url),
                ),
            );

            $support_link = 'https://wordpress.org/support/plugin/wpextended/#new-topic-0';

            if (WP_EXTENDED_PRO) {
                $support_link = Utils::generateTrackedLink('https://wpextended.io/my-account/support-portal/', 'support');
            }

            $links[] = array(
                'title' => 'Support',
                'href' => $support_link,
                'icon' => sprintf('%s/assets/icons/support.svg', $this->options_url),
                'attributes' => array('target' => '_blank', 'aria-label' => 'Support, open in new tab')
            );

            if (!WP_EXTENDED_PRO) {
                $arrow = '<svg class="wpext-settings__links-item-icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" color="currentColor" fill="none"><path d="M16.5 7.5L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M8 6.18791C8 6.18791 16.0479 5.50949 17.2692 6.73079C18.4906 7.95209 17.812 16 17.812 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';

                $links[] = array(
                    'title' => sprintf(
                        /* translators: %s: arrow icon */
                        esc_html__('Upgrade to PRO %s', WP_EXTENDED_TEXT_DOMAIN),
                        $arrow
                    ),
                    'href' => Utils::generateTrackedLink('https://wpextended.io/pricing/', 'upgrade'),
                    'attributes' => array('target' => '_blank', 'class' => 'wpext-settings__links-item-link--upgrade'),
                );
            }

            $output .= '<ul class="wpext-settings__links">';

            foreach ($links as $link) {
                if (!isset($link['title'])) {
                    continue;
                }

                $icon = '';

                if (isset($link['icon'])) {
                    $icon = sprintf('<img class="wpext-settings__links-item-icon" aria-hidden="true" src="%s" />', $link['icon']);
                }

                $output .= sprintf(
                    '<li class="wpext-settings__links-item">
                        <a href="%s" %s>
                            %s
                            %s
                        </a>
                    </li>',
                    $link['href'],
                    isset($link['attributes']) ? $this->generate_html_atts($link['attributes']) : '',
                    $icon,
                    $link['title']
                );
            }
            $output .= '</ul>';

            return $output;
        }

        /**
         * Displays any errors from the WordPress settings API
         *
         * @refactored = true
         */
        public function admin_notices()
        {
            // This is now just a hook for standard WordPress admin notices
            // settings_errors() is called separately in render_notices_placeholder
        }

        /**
         * Enqueue scripts and styles
         *
         * @refactored = true
         */
        public function admin_enqueue_scripts()
        {
            $this->register_scripts();
            $this->enqueue_scripts();
            $this->localize_script();

            $this->register_styles();
            $this->enqueue_styles();

            $this->dequeue_global_styles();
        }

        /**
         * Register scripts
         *
         * @refactored = true
         */
        private function register_scripts()
        {
            $this->register_timepicker_script();
            $this->register_wpext_script();

            // Register jQuery UI Sortable
            wp_enqueue_script('jquery-ui-sortable');
            $this->register_select2_script();

            // Register jQuery UI Sortable
            wp_enqueue_script('jquery-ui-sortable');
            $this->register_coloris_script();
        }

        /**
         * Register jQuery UI Timepicker script
         *
         * @refactored = true
         */
        private function register_timepicker_script()
        {
            Utils::registerScript(
                'wpext-timepicker',
                $this->get_path('assets/lib/jquery-timepicker/jquery.timepicker.min.js'),
                array('jquery', 'jquery-ui-core'),
                true
            );
        }

        /**
         * Register Coloris script
         */
        private function register_coloris_script()
        {
            Utils::registerScript('wpext-coloris', $this->get_path('assets/lib/coloris/coloris.min.js'));
        }

        /**
         * Check if any fields use the code_editor type
         *
         * @return bool
         */
        private function has_code_editor_fields()
        {
            $all_fields = $this->get_all_fields();

            foreach ($all_fields as $field) {
                if (isset($field['type']) && $field['type'] === 'code_editor') {
                    return true;
                }
            }

            return false;
        }

        /**
         * Register WPEFR main script
         *
         * @refactored = true
         */
        private function register_wpext_script()
        {
            $dependencies = array('jquery', 'wpext-notify');

            Utils::registerScript(
                'wpext-framework',
                $this->get_path('assets/js/main.js'),
                $dependencies,
                true
            );

            wp_localize_script(
                'wpext-framework',
                'wpext_framework',
                array(
                    'save_tray_exclusions' => apply_filters(sprintf('wpextended/%s/save_tray_exclusions', $this->option_group), []),
                    'rest_url' => rest_url(WP_EXTENDED_API_NAMESPACE),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'i18n' => array(
                        'success' => esc_html__('Operation completed successfully', 'wpext'),
                        'error' => esc_html__('An error occurred', 'wpext'),
                        'processing' => esc_html__('Processing...', 'wpext'),
                        'confirm_delete' => esc_html__('Are you sure you want to delete this item?', 'wpext'),
                        'confirm_reset' => esc_html__('Are you sure you want to reset all settings? This action cannot be undone.', 'wpext'),
                    )
                )
            );
        }

        /**
         * Register Select2 script
         */
        private function register_select2_script()
        {
            Utils::registerScript(
                'wpext-select2',
                $this->get_path('assets/lib/select2/select2.min.js'),
                array('jquery'),
            );
        }

        /**
         * Enqueue scripts
         *
         * @refactored = true
         */
        private function enqueue_scripts()
        {
            Utils::enqueueScript('jquery');
            Utils::enqueueScript('wpext-timepicker');
            Utils::enqueueScript('wpext-select2');
            Utils::enqueueNotify();

            // Enqueue WordPress media library for file uploads
            wp_enqueue_media();

            Utils::enqueueScript('wpext-framework');
        }

        /**
         * Localize WPEFR script
         *
         * @refactored = true
         */
        private function localize_script()
        {
            $data = array(
                'select_file' => esc_html__('Please select a file to import', 'wpext'),
                'invalid_file' => esc_html__('Invalid file', 'wpext'),
                'something_went_wrong' => esc_html__('Something went wrong', 'wpext'),
            );
            wp_localize_script('wpext', 'wpextended_vars', $data);
        }

        /**
         * Register styles
         *
         * @refactored = true
         */
        private function register_styles()
        {
            $this->register_timepicker_style();
            $this->register_wpext_style();
            $this->register_select2_style();
            $this->register_coloris_style();
        }

        /**
         * Register jQuery UI Timepicker style
         *
         * @refactored = true
         */
        private function register_timepicker_style()
        {
            Utils::registerStyle(
                'wpext-timepicker',
                $this->get_path('assets/lib/jquery-timepicker/jquery.timepicker.min.css'),
            );
        }

        /**
         * Register WPEFR main style
         *
         * @refactored = true
         */
        private function register_wpext_style()
        {
            Utils::registerStyle(
                'wpext-framework',
                $this->get_path('assets/css/main.css'),
            );
        }

        /**
         * Register Select2 style
         */
        private function register_select2_style()
        {
            Utils::registerStyle(
                'wpext-select2',
                $this->get_path('assets/lib/select2/select2.min.css'),
            );
        }

        /**
         * Register Coloris style
         */
        private function register_coloris_style()
        {
            Utils::registerStyle('wpext-coloris', $this->get_path('assets/lib/coloris/coloris.min.css'));
        }

        /**
         * Enqueue styles
         *
         * @refactored = true
         */
        private function enqueue_styles()
        {
            Utils::enqueueStyle('wpext-timepicker');
            Utils::enqueueStyle('wpext-select2');
            Utils::enqueueStyle('wpext-framework');
        }

        /**
         * Dequeue global styles
         *
         * @refactored = true
         */
        private function dequeue_global_styles()
        {
            wp_dequeue_style('global-styles');
        }

        /**
         * Validates and sanitizes settings input.
         *
         * @param mixed $input Input data.
         *
         * @return array
         */
        public function settings_validate($input)
        {
            // Get current settings
            $current_settings = Utils::getSettings($this->option_group);

            // Ensure current_settings is an array
            if (!is_array($current_settings)) {
                $current_settings = [];
            }

            // Initialize validations array
            $validations = [];

            // Get all fields from settings
            $fields = $this->get_all_fields();

            // Apply default values fallback for missing fields
            $input = $this->apply_default_values_fallback($input, $fields);

            $validations = $this->default_validations($fields, $input);

            /**
             * Filter: Add custom validation rules for settings.
             *
             * @param array $validations Array of validation errors
             * @param array $input The input data to validate
             * @return array Array of validation errors
             */
            $validations = apply_filters(sprintf('wpextended/%s/settings_validate', $this->option_group), $validations, $input);

            // Process validations
            if (!empty($validations)) {
                foreach ($validations as $validation) {
                    if (!isset($validation['code']) || !isset($validation['message'])) {
                        continue;
                    }

                    $this->add_settings_error(
                        $validation['code'],
                        $validation['message'],
                        $validation['type'] ?? 'error'
                    );

                    // Revert the field value to original if validation failed
                    if (isset($validation['field'])) {
                        $input[$validation['field']] = $current_settings[$validation['field']] ?? null;
                    }
                }
            }

            // Only sanitize fields that were actually submitted
            $sanitized_input = $this->default_sanitization($fields, $input);

            // Merge the sanitized input with current settings
            $final_settings = $this->settings_update_recursive($current_settings, $sanitized_input, $fields);

            /**
             * Filter: Modify settings data before saving.
             *
             * @param array $final_settings The validated and merged input data
             * @param array $current_settings The original settings before update
             * @param array $sanitized_input The sanitized input data
             * @return array The modified input data
             */
            return apply_filters(
                sprintf('wpextended/%s/settings_before_save', $this->option_group),
                $final_settings,
                $current_settings,
                $sanitized_input
            );
        }

        /**
         * Apply default values fallback for missing fields
         *
         * @param array $input Input data
         * @param array $fields All field configurations
         * @return array Input data with defaults applied
         */
        private function apply_default_values_fallback($input, $fields)
        {
            foreach ($fields as $field) {
                $field_id = $field['id'];

                // Skip if field already has a value
                if (isset($input[$field_id])) {
                    continue;
                }

                // Apply default value if set
                if (isset($field['default'])) {
                    $input[$field_id] = $field['default'];
                }

                // Handle group fields with subfields
                if ($field['type'] === 'group' && !empty($field['subfields'])) {
                    $input[$field_id] = $this->apply_group_defaults_fallback($input[$field_id] ?? [], $field);
                }
            }

            return $input;
        }

        /**
         * Apply default values for group subfields
         *
         * @param array $group_value Current group value
         * @param array $field Group field configuration
         * @return array Group value with defaults applied
         */
        private function apply_group_defaults_fallback($group_value, $field)
        {
            // If group value is empty but default exists, use default
            if (empty($group_value) && isset($field['default']) && is_array($field['default'])) {
                $group_value = $field['default'];
            }

            // Process each group item
            if (is_array($group_value)) {
                foreach ($group_value as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    // Apply defaults for each subfield
                    foreach ($field['subfields'] as $subfield) {
                        $subfield_id = $subfield['id'];

                        // Skip if subfield already has a value
                        if (isset($item[$subfield_id])) {
                            continue;
                        }

                        // Apply default value if set
                        if (isset($subfield['default'])) {
                            $group_value[$index][$subfield_id] = $subfield['default'];
                        }
                    }
                }
            }

            return $group_value;
        }

        public function default_validations($fields, $input)
        {
            $validations = [];

            foreach ($fields as $field) {
                $field_id = $field['id'];

                // Skip validation if field wasn't submitted (might be conditionally hidden)
                if (!isset($input[$field_id])) {
                    continue;
                }

                $field_value = $input[$field_id];

                // Only validate required fields that were actually submitted
                if (empty($field_value) && !empty($field['required'])) {
                    $validations[] = [
                        'field' => $field_id,
                        'code' => 'required_field',
                        'message' => sprintf(__('The field "%s" is required.', 'wpext'), $field['title'] ?? $field_id),
                        'type' => 'error'
                    ];
                    continue;
                }

                $field_validations = $this->validate_field($field, $field_value);

                if (!empty($field_validations)) {
                    $validations = array_merge($validations, $field_validations);
                }
            }

            return $validations;
        }

        public function default_sanitization($fields, $input)
        {
            foreach ($fields as $field) {
                $field_id = $field['id'];
                $field_value = isset($input[$field_id]) ? $input[$field_id] : null;
                $input[$field_id] = $this->sanitize_field($field, $field_value);
            }
            return $input;
        }

        /**
         * Validate a field based on its type
         *
         * @param array $field Field configuration
         * @param mixed $value Field value
         * @return array Array of validation errors
         */
        private function validate_field($field, $value)
        {
            $validations = [];
            $field_id = $field['id'];

            // Check required fields first
            if (!empty($field['required']) && empty($value)) {
                $validations[] = [
                    'field' => $field_id,
                    'code' => 'required_field',
                    'message' => sprintf(__('"%s" is required.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                    'type' => 'error'
                ];
                // No need to continue if required value is missing
                return $validations;
            }

            // Skip other validations if value is empty and not required
            if (empty($value)) {
                return [];
            }

            switch ($field['type']) {
                case 'email':
                    if (!is_email($value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_email',
                            'message' => sprintf(__('"%s" is not a valid email address.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    }
                    break;

                case 'url':
                    if (!wp_http_validate_url($value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_url',
                            'message' => sprintf(__('"%s" is not a valid URL.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    }
                    break;

                case 'file':
                    // Convert value to attachment IDs array
                    $attachment_ids = array();
                    if (is_array($value)) {
                        $attachment_ids = array_filter(array_map('absint', $value));
                    } elseif (is_numeric($value)) {
                        $attachment_ids = array(absint($value));
                    } elseif (is_string($value) && !empty($value)) {
                        $attachment_id = attachment_url_to_postid($value);
                        if ($attachment_id) {
                            $attachment_ids = array($attachment_id);
                        }
                    }

                    // Validate each attachment ID
                    foreach ($attachment_ids as $attachment_id) {
                        if (!is_numeric($attachment_id) || $attachment_id <= 0) {
                            $validations[] = [
                                'field' => $field_id,
                                'code' => 'invalid_file_id',
                                'message' => sprintf(__('"%s" contains an invalid attachment ID.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                                'type' => 'error'
                            ];
                            continue;
                        }

                        // Check if attachment exists
                        $attachment = get_post(absint($attachment_id));
                        if (!$attachment || $attachment->post_type !== 'attachment') {
                            $validations[] = [
                                'field' => $field_id,
                                'code' => 'attachment_not_found',
                                'message' => sprintf(__('"%s" references a file that no longer exists.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                                'type' => 'error'
                            ];
                            continue;
                        }

                        // Validate MIME type if specified (check both 'mime_types' and 'mime_type' for compatibility)
                        $mime_types = !empty($field['mime_types']) ? $field['mime_types'] : (!empty($field['mime_type']) ? $field['mime_type'] : array());
                        if (!empty($mime_types) && is_array($mime_types)) {
                            $mime_validation = $this->validate_file_mime_type($attachment_id, $mime_types, $field['title']);
                            if ($mime_validation) {
                                $validations[] = $mime_validation;
                            }
                        }
                    }
                    break;

                case 'number':
                case 'range':
                    if (!is_numeric($value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_number',
                            'message' => sprintf(__('"%s" must be a number.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    } else {
                        if (isset($field['min']) && $value < $field['min']) {
                            $validations[] = [
                                'field' => $field_id,
                                'code' => 'number_too_small',
                                'message' => sprintf(__('"%s" must be at least %s.', WP_EXTENDED_TEXT_DOMAIN), $field['title'], $field['min']),
                                'type' => 'error'
                            ];
                        }
                        if (isset($field['max']) && $value > $field['max']) {
                            $validations[] = [
                                'field' => $field_id,
                                'code' => 'number_too_large',
                                'message' => sprintf(__('"%s" must not exceed %s.', WP_EXTENDED_TEXT_DOMAIN), $field['title'], $field['max']),
                                'type' => 'error'
                            ];
                        }
                    }
                    break;

                case 'color':
                    if (!preg_match('/^#[a-f0-9]{6}$/i', $value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_color',
                            'message' => sprintf(__('"%s" must be a valid hex color code.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    }
                    break;

                case 'select':
                    // If allow_custom is set and true, skip validation
                    if (!empty($field['allow_custom']) && $field['allow_custom']) {
                        break;
                    }

                    // Convert single value to array for consistent handling
                    $values = is_array($value) ? $value : [$value];

                    // Skip validation if value is empty
                    if (empty($values)) {
                        break;
                    }

                    // Check if multiple selections are allowed
                    if ((!isset($field['multiple']) || !$field['multiple']) && count($values) > 1) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'multiple_not_allowed',
                            'message' => sprintf(__('Multiple selections are not allowed for "%s".', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    }

                    // Validate each selected value against available choices
                    if (!empty($field['choices'])) {
                        // Flatten choices for validation (handle grouped choices)
                        $flattened_choices = [];
                        foreach ($field['choices'] as $key => $value) {
                            if (is_array($value)) {
                                // This is a group, add all group values
                                foreach ($value as $group_key => $group_value) {
                                    $flattened_choices[$group_key] = $group_value;
                                }
                            } else {
                                // This is a direct choice
                                $flattened_choices[$key] = $value;
                            }
                        }

                        foreach ($values as $selected) {
                            if (!isset($flattened_choices[$selected])) {
                                $validations[] = [
                                    'field' => $field_id,
                                    'code' => 'invalid_choice',
                                    'message' => sprintf(__('Invalid selection "%s" for "%s".', WP_EXTENDED_TEXT_DOMAIN), $selected, $field['title']),
                                    'type' => 'error'
                                ];
                            }
                        }
                    }
                    break;

                case 'radio':
                    if (!is_scalar($value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_type',
                            'message' => sprintf(__('"%s" must be a single value.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    } elseif (!empty($field['choices']) && !isset($field['choices'][$value])) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_choice',
                            'message' => sprintf(__('Invalid selection "%s" for "%s".', WP_EXTENDED_TEXT_DOMAIN), $value, $field['title']),
                            'type' => 'error'
                        ];
                    }
                    break;

                case 'checkboxes':
                case 'image_checkboxes':
                    if (!is_array($value)) {
                        $validations[] = [
                            'field' => $field_id,
                            'code' => 'invalid_type',
                            'message' => sprintf(__('"%s" must be an array of selections.', WP_EXTENDED_TEXT_DOMAIN), $field['title']),
                            'type' => 'error'
                        ];
                    } elseif (!empty($field['choices'])) {
                        foreach ($value as $selected) {
                            if (!isset($field['choices'][$selected])) {
                                $validations[] = [
                                    'field' => $field_id,
                                    'code' => 'invalid_choice',
                                    'message' => sprintf(__('Invalid selection "%s" for "%s".', WP_EXTENDED_TEXT_DOMAIN), $selected, $field['title']),
                                    'type' => 'error'
                                ];
                            }
                        }
                    }
                    break;

                case 'group':
                    if (is_array($value) && !empty($field['subfields'])) {
                        foreach ($value as $group_index => $group_item) {
                            foreach ($field['subfields'] as $subfield) {
                                $subfield_id = $subfield['id'];
                                if (isset($group_item[$subfield_id])) {
                                    $sub_value = $group_item[$subfield_id];
                                    $sub_errors = $this->validate_field($subfield, $sub_value);
                                    foreach ($sub_errors as $error) {
                                        $error['field'] = $field_id . '[' . $group_index . '][' . $subfield_id . ']';
                                        $validations[] = $error;
                                    }
                                }
                            }
                        }
                    }
                    break;

                default:
                    // No specific validation rules for unknown types
                    break;
            }

            return $validations;
        }

        /**
         * Validate file MIME type against allowed types
         *
         * @param int $attachment_id WordPress attachment ID
         * @param array $allowed_mime_types Array of allowed MIME types
         * @param string $field_title Field title for error messages
         * @return array|null Validation error array or null if valid
         */
        private function validate_file_mime_type($attachment_id, $allowed_mime_types, $field_title)
        {
            // Filter out empty MIME types
            $allowed_mime_types = array_filter($allowed_mime_types, function ($type) {
                return !empty(trim($type));
            });

            if (empty($allowed_mime_types)) {
                return null; // No restrictions
            }

            // Get attachment MIME type
            $file_mime_type = get_post_mime_type($attachment_id);
            if (!$file_mime_type) {
                return [
                    'field' => '',
                    'code' => 'file_not_found',
                    'message' => sprintf(__('File not found for "%s".', WP_EXTENDED_TEXT_DOMAIN), $field_title),
                    'type' => 'error'
                ];
            }

            // Check if MIME type is allowed
            $is_valid = $this->is_mime_type_allowed($file_mime_type, $allowed_mime_types);

            if (!$is_valid) {
                return [
                    'field' => '',
                    'code' => 'invalid_mime_type',
                    'message' => sprintf(
                        __('Invalid file type for "%s". Allowed types: %s. Selected file type: %s', WP_EXTENDED_TEXT_DOMAIN),
                        $field_title,
                        implode(', ', $allowed_mime_types),
                        $file_mime_type
                    ),
                    'type' => 'error'
                ];
            }

            return null; // Valid
        }

        /**
         * Check if a MIME type is allowed
         *
         * @param string $mime_type The MIME type to check
         * @param array $allowed_types Array of allowed MIME types
         * @return bool Whether the MIME type is allowed
         */
        private function is_mime_type_allowed($mime_type, $allowed_types)
        {
            foreach ($allowed_types as $allowed_type) {
                // Exact match
                if ($mime_type === $allowed_type) {
                    return true;
                }

                // Wildcard match (e.g., "image/*")
                if (strpos($allowed_type, '/*') !== false) {
                    $category = str_replace('/*', '', $allowed_type);
                    if (strpos($mime_type, $category . '/') === 0) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Recursively update settings
         *
         * @param array $original Original settings
         * @param array $updated Updated settings
         * @param array $fields Optional field configurations to help identify field types
         * @return array Updated settings
         */
        public function settings_update_recursive($original, $updated, $fields = null)
        {

            $field_types = array();

            if ($fields) {
                foreach ($fields as $field) {
                    $field_types[$field['id']] = $field['type'];
                }
            }

            foreach ($updated as $key => $value) {
                // If the key does not exist in original, just replace/add the value
                if (!isset($original[$key])) {
                    $original[$key] = $value;
                    continue;
                }

                if (isset($field_types[$key]) && $field_types[$key] === 'group') {
                    $original[$key] = $value;
                    continue;
                }

                // If both values are arrays
                if (is_array($original[$key]) && is_array($value)) {
                    // If array has numeric keys (like your post-order example) or is a group field,
                    // replace the entire array instead of merging
                    if (array_keys($original[$key]) === range(0, count($original[$key]) - 1)) {
                        $original[$key] = $value;
                        continue;
                    }

                    // Otherwise, recursively update the nested array
                    $original[$key] = $this->settings_update_recursive($original[$key], $value, $fields);
                    continue;
                }

                // For non-array values, just replace/add the value
                $original[$key] = $value;
            }

            return $original;
        }

        /**
         * Sanitize a field based on its type
         *
         * @param array $field Field configuration
         * @param mixed $value Field value
         * @return mixed Sanitized value
         */
        public function sanitize_field($field, $value)
        {
            if ($value === null || empty($value)) {
                return '';
            }

            switch ($field['type']) {
                case 'email':
                    return sanitize_email($value);

                case 'url':
                    return esc_url_raw($value);

                case 'file':
                    if (is_array($value)) {
                        // Array format - sanitize each ID
                        return array_filter(array_map('absint', $value));
                    } elseif (is_numeric($value)) {
                        // Single attachment ID - convert to array
                        return array(absint($value));
                    } elseif (is_string($value) && !empty($value)) {
                        // URL string format - try to convert to attachment ID array
                        $attachment_id = attachment_url_to_postid($value);
                        return $attachment_id ? array(absint($attachment_id)) : array();
                    }
                    return array();

                case 'number':
                case 'range':
                    return is_numeric($value) ? floatval($value) : 0;

                case 'textarea':
                    return sanitize_textarea_field($value);

                case 'code_editor':
                case 'editor':
                    return wp_kses_post($value);

                case 'color':
                    return sanitize_hex_color($value);

                case 'checkbox':
                case 'toggle':
                    return (bool) $value;

                case 'password':
                    return sanitize_text_field($value);

                case 'checkboxes':
                case 'image_checkboxes':
                    return is_array($value) ? array_map('sanitize_text_field', $value) : [];

                case 'select':
                    if (isset($field['allow_custom']) && $field['allow_custom']) {
                        if (is_array($value)) {
                            $sanitized = array_map('sanitize_text_field', $value);
                            return !empty($field['multiple']) ? $sanitized : reset($sanitized);
                        } else {
                            return sanitize_text_field($value);
                        }
                    }

                    if (!empty($field['choices']) && is_array($field['choices'])) {
                        if (is_array($value)) {
                            $sanitized = [];

                            // If multiple is not set or false, only allow one
                            if (empty($field['multiple'])) {
                                $value = [reset($value)];
                            }

                            foreach ($value as $selected) {
                                if (isset($field['choices'][$selected])) {
                                    $sanitized[] = sanitize_text_field($selected);
                                }
                            }

                            return $field['multiple'] ? $sanitized : reset($sanitized);
                        } else {
                            return isset($field['choices'][$value]) ? sanitize_text_field($value) : '';
                        }
                    }
                    return isset($field['multiple']) && $field['multiple'] ? [] : '';

                case 'radio':
                    if (!empty($field['choices']) && is_scalar($value)) {
                        return isset($field['choices'][$value]) ? sanitize_text_field($value) : '';
                    }
                    return '';

                case 'group':
                    if (is_array($value) && !empty($field['subfields'])) {
                        foreach ($value as $group_index => $group_item) {
                            foreach ($field['subfields'] as $subfield) {
                                $subfield_id = $subfield['id'];
                                if (isset($group_item[$subfield_id])) {
                                    $value[$group_index][$subfield_id] = $this->sanitize_field(
                                        $subfield,
                                        $group_item[$subfield_id]
                                    );
                                }
                            }
                        }
                    }
                    return $value;

                case 'html':
                    $allowed_html = wp_kses_allowed_html('post');
                    return wp_kses($value, $allowed_html);

                case 'date':
                case 'time':
                    return sanitize_text_field($value);
                case 'text':
                    if (isset($field['allow_html']) && $field['allow_html']) {
                        return wp_kses_post($value);
                    }
                    return sanitize_text_field($value);
                default:
                    return sanitize_text_field($value);
            }
        }

        /**
         * Get all fields from all sections
         *
         * @return array Array of all fields
         */
        private function get_all_fields()
        {
            $fields = [];

            if (empty($this->settings)) {
                return $fields;
            }

            foreach ($this->settings as $section) {
                if (!empty($section['fields']) && is_array($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        $fields_args = wp_parse_args($field, $this->setting_defaults);
                        $fields_args = apply_filters(sprintf('wpextended/%s/field_args', $this->option_group), $fields_args, $field);
                        $fields_args = apply_filters(sprintf('wpextended/%s/%s/field_args', $this->option_group, $field['id']), $fields_args, $field);

                        $fields[] = $fields_args;
                    }
                }
            }


            return $fields;
        }

        /**
         * Helper method to add a settings error.
         *
         * @param string $code Slug-name to identify the error. Used as part of 'id' attribute in HTML output.
         * @param string $message Message text to display to the user.
         * @param string $type Optional. Message type, controls HTML class. Accepts 'error', 'success', 'warning', 'info'.
         * @return void
         */
        public function add_settings_error($code, $message, $type = 'error')
        {
            add_settings_error(
                sprintf('%s_settings', $this->option_group),
                $code,
                $message,
                $type
            );
        }

        /**
         * Displays the "section_description" if specified in $this->settings
         *
         * @param array $args callback args from add_settings_section().
         */
        public function section_intro($args)
        {
            if (empty($this->settings)) {
                return;
            }

            $section = $this->find_section($args['id']);
            if (!$section) {
                return;
            }

            $this->render_section_classes($section);
            $this->render_section_description($section);
        }

        /**
         * Find the section with the given ID
         *
         * @param string $section_id
         * @return array|null
         */
        private function find_section($section_id)
        {
            foreach ($this->settings as $section) {
                if ($section['section_id'] === $section_id) {
                    return $section;
                }
            }
            return null;
        }

        /**
         * Render the section classes
         *
         * @param array $section
         */
        private function render_section_classes($section)
        {
            $render_class = self::add_show_hide_classes($section);
            if ($render_class) {
                printf('<span class="%s"></span>', esc_attr($render_class));
            }
        }

        /**
         * Render the section description
         *
         * @param array $section
         */
        private function render_section_description($section)
        {
            if (!isset($section['section_description']) || !$section['section_description']) {
                return;
            }

            printf(
                '<div class="wpext-section-description wpext-section-description--%s">
                    %s
                </div>',
                esc_attr($section['section_id']),
                wp_kses_post($section['section_description'])
            );
        }

        /**
         * Processes $this->settings and adds the sections
         * and fields via the WordPress settings API
         *
         * @refactored = true
         */
        private function process_settings()
        {
            if (empty($this->settings)) {
                return;
            }

            usort($this->settings, array($this, 'sort_array'));

            foreach ($this->settings as $section) {
                $this->process_section($section);
            }
        }

        /**
         * Process a single section
         *
         * @param array $section Section data
         *
         * @refactored = true
         */
        private function process_section($section)
        {
            if (!$this->is_valid_section($section)) {
                return;
            }

            $page_name = $this->get_page_name($section);
            $this->add_section($section, $page_name);
            $this->process_fields($section, $page_name);
        }

        /**
         * Check if a section is valid
         *
         * @param array $section Section data
         * @return bool
         *
         * @refactored = true
         */
        private function is_valid_section($section)
        {
            return
                isset($section['section_id']) &&
                $section['section_id'] &&
                isset($section['section_title']);
        }

        /**
         * Get the page name for a section
         *
         * @param array $section Section data
         * @return string
         *
         * @refactored = true
         */
        private function get_page_name($section)
        {
            return $this->has_tabs()
                ? sprintf('%s_%s', $this->option_group, $section['tab_id'])
                : $this->option_group;
        }

        /**
         * Add a section to the settings
         *
         * @param array $section Section data
         * @param string $page_name Page name
         *
         * @refactored = true
         */
        private function add_section($section, $page_name)
        {
            add_settings_section(
                $section['section_id'],
                $section['section_title'],
                array($this, 'section_intro'),
                $page_name
            );
        }

        /**
         * Process fields for a section
         *
         * @param array $section Section data
         * @param string $page_name Page name
         *
         * @refactored = true
         */
        private function process_fields($section, $page_name)
        {
            if (
                !isset($section['fields']) ||
                !is_array($section['fields']) ||
                empty($section['fields'])
            ) {
                return;
            }

            foreach ($section['fields'] as $field) {
                $this->process_field($field, $section, $page_name);
            }
        }

        /**
         * Process a single field
         *
         * @param array $field Field data
         * @param array $section Section data
         * @param string $page_name Page name
         *
         * @refactored = true
         */
        private function process_field($field, $section, $page_name)
        {
            if (!$this->is_valid_field($field)) {
                return;
            }

            $title = $this->generate_field_title($field);
            $this->add_settings_field($field, $title, $section, $page_name);
        }

        /**
         * Check if a field is valid
         *
         * @param array $field Field data
         * @return bool
         *
         * @refactored = true
         */
        private function is_valid_field($field)
        {
            $has_id = isset($field['id']) && $field['id'];
            $has_title = (isset($field['title']) && $field['title']) || $field['type'] === 'custom';
            return $has_id && $has_title;
        }

        /**
         * Generate the field title
         *
         * @param array $field Field arguments.
         * @return string
         *
         * @refactored = true
         */
        private function generate_field_title($field)
        {
            if (!isset($field['title']) || !$field['title']) {
                return '';
            }

            $tooltip = $this->generate_field_tooltip($field);
            $required = !empty($field['required']) ? ' <span class="wpext-required">*</span>' : '';

            $title = sprintf(
                '<span class="wpext-label">%s%s %s</span>',
                $field['title'],
                $required,
                $tooltip
            );

            if (!empty($field['subtitle'])) {
                $title .= sprintf(
                    '<span class="wpext-subtitle">%s</span>',
                    $field['subtitle']
                );
            }

            return $title;
        }

        /**
         * Generate the field tooltip
         *
         * @param array $field Field data
         * @return string
         *
         * @refactored = true
         */
        private function generate_field_tooltip($field)
        {
            if (!isset($field['link']) || !is_array($field['link'])) {
                return '';
            }

            $link = $this->generate_field_link($field['link']);

            if ($link && 'tooltip' === $field['link']['type']) {
                return $link;
            } elseif ($link) {
                $field['subtitle'] .= empty($field['subtitle'])
                    ? $link
                    : sprintf('<br/><br/>%s', $link);
            }

            return '';
        }

        /**
         * Generate the field link
         *
         * @param array $link_data Link data
         * @return string
         *
         * @refactored = true
         */
        private function generate_field_link($link_data)
        {
            $link_url = isset($link_data['url']) ? esc_html($link_data['url']) : '';
            $link_text = isset($link_data['text']) ? esc_html($link_data['text']) : esc_html__('Learn More', 'wpext');
            $link_external = isset($link_data['external']) ? (bool) $link_data['external'] : true;
            $link_type = isset($link_data['type']) ? esc_attr($link_data['type']) : 'tooltip';
            $link_target = $link_external ? ' target="_blank"' : '';

            if ('tooltip' === $link_type) {
                $link_text = sprintf(
                    '<i class="dashicons dashicons-info wpext-link-icon" title="%s">
                        <span class="screen-reader-text">%s</span>
                    </i>',
                    $link_text,
                    $link_text
                );
            }

            return $link_url
                ? sprintf(
                    '<a class="wpext-label__link" href="%s"%s>
                    %s
                </a>',
                    $link_url,
                    $link_target,
                    $link_text
                )
                : '';
        }

        /**
         * Add a settings field
         *
         * @param array $field Field data
         * @param string $title Field title
         * @param array $section Section data
         * @param string $page_name Page name
         *
         * @refactored = true
         */
        private function add_settings_field($field, $title, $section, $page_name)
        {
            add_settings_field(
                $field['id'],
                $title,
                array($this, 'generate_setting'),
                $page_name,
                $section['section_id'],
                array(
                    'section' => $section,
                    'field' => $field,
                )
            );
        }

        /**
         * Generates the HTML output of the settings fields
         *
         * @param array $args callback args from add_settings_field().
         *
         * @refactored = true
         */
        public function generate_setting($args)
        {
            $section = $args['section'];
            $field = $args['field'];

            $this->apply_setting_defaults();
            $args = $this->prepare_field_args($section, $field);

            $this->execute_before_field_actions($args['id']);

            echo $this->do_field_method($args);

            $this->execute_after_field_actions($args['id']);
        }

        /**
         * Apply setting defaults filter
         *
         * @refactored = true
         */
        private function apply_setting_defaults()
        {
            $filter_name = sprintf('wpextended/%s/defaults', $this->option_group);
            $this->setting_defaults = apply_filters($filter_name, $this->setting_defaults);
        }

        /**
         * Prepare field arguments
         *
         * @param array $section Section data
         * @param array $field Field data
         * @return array Prepared field arguments
         *
         * @refactored = true
         */
        private function prepare_field_args($section, $field)
        {
            $args = wp_parse_args($field, $this->setting_defaults);
            $options = get_option($this->option_group . '_settings');

            $field_name = isset($args['name']) ? $args['name'] : $args['id'];
            $args['name'] = $this->generate_field_name($field_name);

            // Get the value, using default if no value is set
            $args['value'] = $this->get_field_value($field_name, $args, $options);

            $args['class'] .= self::add_show_hide_classes($args);

            $args = apply_filters(sprintf('wpextended/%s/field_args', $this->option_group), $args, $field);
            $args = apply_filters(sprintf('wpextended/%s/%s/field_args', $this->option_group, $field['id']), $args, $field);

            return $args;
        }

        /**
         * Execute actions before field rendering
         *
         * @param string $field_id Field ID
         *
         * @refactored = true
         */
        private function execute_before_field_actions($field_id)
        {
            do_action(sprintf('wpextended/%s/before_field', $this->option_group));
            do_action(sprintf('wpextended/%s/before_field_%s', $this->option_group, $field_id));
        }

        /**
         * Execute actions after field rendering
         *
         * @param string $field_id Field ID
         *
         * @refactored = true
         */
        private function execute_after_field_actions($field_id)
        {
            do_action(sprintf('wpextended/%s/after_field', $this->option_group));
            do_action(sprintf('wpextended/%s/after_field_%s', $this->option_group, $field_id));
        }

        /**
         * Do field method, if it exists
         *
         * @param array $field Field arguments.
         * @return string
         *
         * @refactored = true
         */
        public function do_field_method($field)
        {
            $generate_field_method = sprintf('generate_%s_field', $field['type']);

            if (method_exists($this, $generate_field_method)) {
                return $this->$generate_field_method($field);
            }

            return '';
        }

        /**
         * Generate: Text field
         *
         * @param array $args Field arguments.
         * @return string
         *
         * @refactored = true
         */
        public function generate_text_field($args)
        {
            $defaults = array(
                'type' => 'text',
                'prefix' => '',
                'suffix' => '',
                'placeholder' => '',
                'default' => '',
                'allow_html' => false
            );

            $args = wp_parse_args($args, $defaults);
            $args['value'] = !empty($args['value']) ? esc_attr(stripslashes($args['value'])) : $args['default'];

            $field_attributes = $this->build_field_attributes($args, array(
                'type' => $args['type'],
                'value' => $args['value'],
            ));

            $prefix = $args['prefix'] ? sprintf('<span class="wpext-field__prefix">%s</span>', $args['prefix']) : '';
            $suffix = $args['suffix'] ? sprintf('<span class="wpext-field__suffix">%s</span>', $args['suffix']) : '';

            $output = sprintf(
                '<div class="wpext-field-group">%s<input %s />%s</div>',
                $prefix,
                $this->generate_html_atts($field_attributes),
                $suffix
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Email field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_email_field($args)
        {
            $args['type'] = 'email';
            return $this->generate_text_field($args);
        }

        /**
         * Generate: URL field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_url_field($args)
        {
            $args['type'] = 'url';
            return $this->generate_text_field($args);
        }

        /**
         * Generate: Password field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_password_field($args)
        {
            $args['type'] = 'password';
            return $this->generate_text_field($args);
        }

        /**
         * Generate: Hidden field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_hidden_field($args)
        {
            $args['value'] = esc_attr(stripslashes($args['value']));
            $field_attributes = $this->build_field_attributes($args, array('type' => 'hidden'));

            return sprintf(
                '<input %s />',
                $this->generate_html_atts($field_attributes)
            );
        }

        /**
         * Generate: Number field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_number_field($args)
        {
            // Ensure the value is treated as a number
            $value = isset($args['value']) ? floatval($args['value']) : '';

            $field_attributes = $this->build_field_attributes($args, array(
                'type' => 'number',
                'value' => $value,
                'step' => 'any'
            ));

            $output = sprintf(
                '<input %s />',
                $this->generate_html_atts($field_attributes)
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Range field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_range_field($args)
        {
            // Ensure the value is treated as a number
            $value = isset($args['value']) ? floatval($args['value']) : '';
            $min = isset($args['min']) ? $args['min'] : '';
            $max = isset($args['max']) ? $args['max'] : '';
            $step = isset($args['step']) ? $args['step'] : '';
            $prefix = isset($args['prefix']) ? $args['prefix'] : '';
            $suffix = isset($args['suffix']) ? $args['suffix'] : '';

            $field_attributes = $this->build_field_attributes($args, array(
                'type' => 'range',
                'class' => 'wpext-range-field',
                'value' => $value,
                'step' => $step,
                'min' => $min,
                'max' => $max,
                'data-prefix' => $prefix,
                'data-suffix' => $suffix,
            ));

            $output = sprintf(
                '<input %s />%s<span class="wpext-range-value">%s</span>%s',
                $this->generate_html_atts($field_attributes),
                $prefix ? sprintf('<span class="wpext-range-prefix">%s</span>', $prefix) : '',
                $value,
                $suffix ? sprintf('<span class="wpext-range-suffix">%s</span>', $suffix) : ''
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Time field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_time_field($args)
        {
            $args['value'] = esc_attr(stripslashes($args['value']));
            $timepicker = !empty($args['timepicker'])
                ? htmlentities(wp_json_encode($args['timepicker']))
                : null;

            $field_attributes = $this->build_field_attributes($args, [
                'type' => 'text',
                'class' => 'timepicker regular-text ' . $args['class'],
                'data-timepicker' => $timepicker,
            ]);

            $output = sprintf(
                '<input %s />',
                $this->generate_html_atts($field_attributes)
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Date field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_date_field($args)
        {
            $args['value'] = esc_attr(stripslashes($args['value']));
            $datepicker = !empty($args['datepicker']) ? htmlentities(wp_json_encode($args['datepicker'])) : null;

            $field_attributes = $this->build_field_attributes($args, [
                'type' => 'text',
                'class' => 'datepicker regular-text ' . $args['class'],
                'data-datepicker' => $datepicker,
            ]);

            $output = sprintf(
                '<input %s />',
                $this->generate_html_atts($field_attributes)
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate Export Field.
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_export_field($args)
        {
            $args['value'] = empty($args['value'])
                ? esc_html__('Export Settings', 'wpext')
                : esc_attr(stripslashes($args['value']));

            $export_url = add_query_arg(
                array(
                    'action' => 'wpextended/export_settings',
                    '_wpnonce' => wp_create_nonce('wpextended/export_settings'),
                    'option_group' => $this->option_group
                ),
                admin_url('admin-ajax.php')
            );

            $link_attributes = array(
                'href' => $export_url,
                'class' => 'button',
                'name' => $args['name'],
                'id' => $args['id'],
                'target' => '_blank'
            );

            $output = sprintf(
                '<a %s>%s</a>',
                $this->generate_html_atts($link_attributes),
                esc_html($args['value'])
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate Import Field.
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_import_field($args)
        {
            $args['value'] = empty($args['value'])
                ? esc_html__('Import Settings', 'wpext')
                : esc_attr(stripslashes($args['value']));

            $file_input_attrs = $this->generate_html_atts([
                'type' => 'file',
                'name' => 'wpext-import-field',
                'class' => 'wpext-import__file_field',
                'id' => $args['id'],
                'accept' => '.json'
            ]);

            $button_attrs = $this->generate_html_atts([
                'type' => 'button',
                'name' => 'wpextended/import_button',
                'class' => 'button wpext-import__button',
                'id' => $args['id']
            ]);

            $nonce_input_attrs = $this->generate_html_atts([
                'type' => 'hidden',
                'class' => 'wpextended/import_nonce',
                'value' => wp_create_nonce('wpextended/import_settings')
            ]);

            $option_group_input_attrs = $this->generate_html_atts([
                'type' => 'hidden',
                'class' => 'wpextended/import_option_group',
                'value' => $this->option_group
            ]);

            $output = sprintf(
                '<div class="wpext-import">
					<div class="wpext-import__false_btn">
						<input %s/>
						<button %s>%s</button>
						<input %s>
						<input %s>
					</div>
					<span class="spinner"></span>
				</div>',
                $file_input_attrs,
                $button_attrs,
                esc_html($args['value']),
                $nonce_input_attrs,
                $option_group_input_attrs
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Group field
         */
        public function generate_group_field($args)
        {
            // Set default options
            $args = wp_parse_args($args, array(
                'id' => '',
                'title' => '',
                'description' => '',
                'sortable' => ['nesting' => false, 'max_depth' => 1, 'current_depth' => false],
                'collapsible' => false,
                'collapsed' => true,
                'title_template' => 'Item {#}',
                'fields' => array(),
                'actions' => array('add', 'remove'),
            ));

            $is_sortable = !empty($args['sortable']) && $args['sortable'] !== false;
            $group_id = esc_attr(str_replace('_', '-', $args['id']));

            // Generate template for new items
            $template = $this->generate_group_row_template($args, true, 0);
            echo sprintf('<script type="text/template" id="template-group-%s">%s</script>', $group_id, $template);

            // Build group classes
            $group_classes = array_filter([
                'wpext-group',
                isset($args['sortable']) && $args['sortable'] ? 'wpext-group--sortable' : '',
                isset($args['collapsible']) && $args['collapsible'] ? 'wpext-group--collapsible' : '',
                isset($args['collapsed']) && $args['collapsed'] ? 'wpext-group--collapsed' : '',
            ]);


            if (isset($args['show_if'])) {
                $group_classes[] = self::add_show_hide_classes($args, 'show_if');
            }
            if (isset($args['hide_if'])) {
                $group_classes[] = self::add_show_hide_classes($args, 'hide_if');
            }

            // Build complete group field
            $output = sprintf(
                '<div id="group-%1$s" class="%2$s" %3$s>
                    <ul class="wpext-group__items">',
                $group_id,
                implode(' ', $group_classes),
                $this->generate_html_atts([
                    'data-group-id' => $group_id,
                    'data-title-template' => esc_attr($args['title_template']),
                    'data-nesting' => isset($args['sortable']['nesting']) && $args['sortable']['nesting'] ? 'true' : 'false',
                    'data-max-depth' => isset($args['sortable']['max_depth']) && $args['sortable']['max_depth'] ? $args['sortable']['max_depth'] : '',
                    'data-sortable' => $is_sortable ? 'true' : 'false',
                    'data-current-depth' => isset($args['sortable']['current_depth']) && $args['sortable']['current_depth'] ? 'true' : 'false',
                    'data-group-key' => $args['id'],
                ]),
            );

            // Get existing items
            $items = '';
            $saved_groups = $args['value'];

            if (!empty($saved_groups) && is_array($saved_groups)) {
                $items .= $this->generate_nested_items($args, $saved_groups);
            } elseif (!empty($args['default']) && is_array($args['default'])) {
                // Use default value if no saved groups exist
                $items .= $this->generate_nested_items($args, $args['default']);
            } else {
                // Add one empty row by default
                $items .= $this->generate_group_row_template($args, false, 0);
            }

            $output .= $items;
            $output .= '</ul>'; // Close wpext-group__items
            // Enforce static titles for separators at runtime (handles drag/refresh)
            $output .= sprintf(
                '<script>(function(){
                    var groupEl = document.getElementById("group-%1$s");
                    if(!groupEl) return;
                    function fixSeparatorTitles(){
                        var nodes = groupEl.querySelectorAll(".wpext-group__item--separator .wpext-group__item-title");
                        nodes.forEach(function(el){
                            if(el && el.textContent.trim() !== "-- Separator --"){
                                el.textContent = "-- Separator --";
                            }
                        });
                    }
                    fixSeparatorTitles();
                    try{
                        var mo = new MutationObserver(function(){ fixSeparatorTitles(); });
                        mo.observe(groupEl, {subtree:true, childList:true, characterData:true});
                    }catch(e){}
                })();</script>',
                $group_id
            );
            $output .= '</div>'; // Close wpext-group

            echo $output;
            $this->generate_description($args);
        }

        /**
         * Generate action buttons for group items
         *
         * @param array $args Group field arguments
         * @param string $group_id Group ID for template reference
         * @return string HTML for action buttons
         */
        private function generate_group_action_buttons($args, $group_id = '')
        {
            $output = '';
            $actions = is_array($args['actions']) ? $args['actions'] : array();

            // Add toggle button if collapsible
            if ($args['collapsible']) {
                $output .= sprintf(
                    '<button type="button" class="wpext-group__item-toggle" aria-expanded="%s" title="%s" aria-label="%s">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>',
                    $args['collapsed'] ? 'false' : 'true',
                    esc_html__('Toggle Item', WP_EXTENDED_TEXT_DOMAIN),
                    esc_html__('Toggle Item', WP_EXTENDED_TEXT_DOMAIN)
                );
            }

            // Add action buttons based on actions array
            foreach ($actions as $action) {
                switch ($action) {
                    case 'add':
                        $output .= sprintf(
                            '<button type="button" class="wpext-group__row-add" data-template="template-group-%1$s" title="%s" aria-label="%s">
                                <span class="dashicons dashicons-plus-alt2"></span>
                            </button>',
                            $group_id ?: esc_attr(str_replace('_', '-', $args['id'])),
                            esc_html__('Add Item', WP_EXTENDED_TEXT_DOMAIN),
                            esc_html__('Add Item', WP_EXTENDED_TEXT_DOMAIN)
                        );
                        break;

                    case 'remove':
                        $output .= sprintf(
                            '<button type="button" class="wpext-group__row-remove" title="%s" aria-label="%s">
                                <span class="dashicons dashicons-trash"></span>
                            </button>',
                            esc_html__('Remove', WP_EXTENDED_TEXT_DOMAIN),
                            esc_html__('Remove Item', WP_EXTENDED_TEXT_DOMAIN)
                        );
                        break;

                    // Add more action types here as needed
                    default:
                        // Skip unknown actions
                        break;
                }
            }

            return $output;
        }

        /**
         * Generate nested items recursively
         */
        private function generate_nested_items($args, $items, $parentPath = '')
        {
            $output = '';
            foreach ($items as $index => $item) {
                $currentPath = $parentPath ? $parentPath . '[children][' . $index . ']' : '[' . $index . ']';

                // Start the list item with optional separator class
                $is_separator = isset($item['type']) && $item['type'] === 'separator';
                $li_extra = $is_separator ? ' data-title-template="-- Separator --" data-title="-- Separator --"' : '';
                $output .= '<li class="wpext-group__item' . ($args['collapsed'] ? ' wpext-group__item--collapsed' : '') . ($is_separator ? ' wpext-group__item--separator' : '') . '"' . $li_extra . '>';

                // Add the item wrapper
                $output .= '<div class="wpext-group__item-wrapper">';

                // Add the header
                $output .= '<div class="wpext-group__item-header">';
                if ($args['sortable']) {
                    $output .= '<div class="wpext-group__item-drag"><span class="dashicons dashicons-menu"></span></div>';
                }
                // Title: fixed label for separators, template for others
                $item_title = $is_separator ? '-- Separator --' : $this->process_title_template($args['title_template'], $item, $index);
                $output .= '<div class="wpext-group__item-title">' . $item_title . '</div>';
                $output .= '<div class="wpext-group__item-actions">';

                if ($is_separator) {
                    // For separators: no toggle and no buttons
                } else {
                    // Default actions with toggle, add, remove
                    $output .= $this->generate_group_action_buttons($args, esc_attr(str_replace('_', '-', $args['id'])));
                }

                $output .= '</div>'; // Close actions
                $output .= '</div>'; // Close header

                // Inject hidden inputs for separators so title templating stays consistent
                if ($is_separator) {
                    $hidden_title_name = sprintf('%s%s[%s]', $args['name'], $currentPath, 'title');
                    $hidden_type_name  = sprintf('%s%s[%s]', $args['name'], $currentPath, 'type');
                    // Provide a hidden content container so JS that searches within content can find fields
                    $output .= '<div class="wpext-group__item-content" style="display:none">';
                    $output .= '<div class="wpext-group__field-wrapper">';
                    $output .= sprintf(
                        '<input type="text" name="%1$s" value="-- Separator --" style="display:none" />
                         <input type="hidden" name="%2$s" value="separator" />',
                        esc_attr($hidden_title_name),
                        esc_attr($hidden_type_name)
                    );
                    $output .= '</div>'; // Close field wrapper
                    $output .= '</div>'; // Close content
                }

                if (!$is_separator) {
                    // Add the content only for non-separators
                    $output .= sprintf(
                        '<div class="wpext-group__item-content"%s>',
                        $args['collapsed'] ? ' aria-hidden="true"' : ''
                    );

                    $output .= '<div class="wpext-group__field-wrapper">';

                    // Add the fields
                    $fields = !empty($args['subfields']) ? $args['subfields'] : (!empty($args['fields']) ? $args['fields'] : []);
                    if (!empty($fields)) {
                        foreach ($fields as $field) {
                            $field = wp_parse_args($field, array(
                                'id' => '',
                                'type' => 'text',
                                'title' => '',
                                'name' => '',
                                'class' => '',
                                'placeholder' => '',
                                'value' => '',
                                'description' => '',
                            ));

                            $field_id = $field['id'];
                            $field['name'] = sprintf('%s%s[%s]', $args['name'], $currentPath, $field_id);
                            $field['id'] = sprintf('%s_%d_%s', $args['id'], $index, $field_id);
                            // Provide a stable, non-changing key for selectors/classes
                            $field['key'] = $field_id;
                            $field['value'] = isset($item[$field_id]) ? $item[$field_id] : '';

                            // Add conditional classes
                            $conditional_class = '';
                            if (isset($field['show_if'])) {
                                $conditional_class .= self::add_show_hide_classes($field, 'show_if');
                            }
                            if (isset($field['hide_if'])) {
                                $conditional_class .= self::add_show_hide_classes($field, 'hide_if');
                            }

                            $output .= sprintf(
                                '<div class="wpext-field wpext-field--%1$s%2$s">
                                    <div class="wpext-label">
                                        <label for="%3$s"><span class="wpext-label">%4$s</span></label>
                                    </div>
                                    <div class="wpext-input">
                                        %5$s
                                    </div>
                                </div>',
                                esc_attr($field['type']),
                                $conditional_class,
                                esc_attr($field['id']),
                                esc_html($field['title']),
                                $this->do_field_method($field)
                            );
                        }
                    }

                    $output .= '</div>'; // Close field wrapper
                    $output .= '</div>'; // Close content
                }
                $output .= '</div>'; // Close item wrapper

                // If this item has children, add them in a nested list
                if (isset($item['children']) && is_array($item['children'])) {
                    $output .= '<ul class="wpext-group__items">';
                    $output .= $this->generate_nested_items($args, $item['children'], $currentPath);
                    $output .= '</ul>';
                }

                $output .= '</li>'; // Close list item
            }
            return $output;
        }

        /**
         * Generate: Group row template
         */
        public function generate_group_row_template($args, $blank = false, $row = 0)
        {
            $group_id = esc_attr(str_replace('_', '-', $args['id']));

            // Start the template
            $output = '<li class="wpext-group__item' . ($blank ? ' template-group-' . $group_id : '') . ($args['collapsed'] ? ' wpext-group__item--collapsed' : '') . '"' .
                ($blank ? ' style="display:none;" id="template-group-' . $group_id . '"' : '') . ' data-row="' . $row . '">';

            // Add the item wrapper
            $output .= '<div class="wpext-group__item-wrapper">';

            // Add the header
            $output .= '<div class="wpext-group__item-header">';
            if ($args['sortable']) {
                $output .= '<div class="wpext-group__item-drag"><span class="dashicons dashicons-menu"></span></div>';
            }
            $output .= '<div class="wpext-group__item-title">' . $this->process_title_template($args['title_template'], array(), $row) . '</div>';
            $output .= '<div class="wpext-group__item-actions">';

            // Generate action buttons using the new method
            $output .= $this->generate_group_action_buttons($args, $group_id);

            $output .= '</div>'; // Close actions
            $output .= '</div>'; // Close header

            // Add the content
            $output .= sprintf(
                '<div class="wpext-group__item-content"%s>',
                $args['collapsed'] ? ' aria-hidden="true"' : ''
            );

            $output .= '<div class="wpext-group__field-wrapper">';

            // Add the fields
            $fields = !empty($args['subfields']) ? $args['subfields'] : (!empty($args['fields']) ? $args['fields'] : []);
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    $field = wp_parse_args($field, array(
                        'id' => '',
                        'type' => 'text',
                        'title' => '',
                        'name' => '',
                        'class' => '',
                        'placeholder' => '',
                        'value' => '',
                        'description' => '',
                    ));

                    $field_id = $field['id'];
                    $field['name'] = sprintf('%s[%d][%s]', $args['name'], $row, $field_id);
                    $field['id'] = sprintf('%s_%d_%s', $args['id'], $row, $field_id);
                    // Provide a stable, non-changing key for selectors/classes
                    $field['key'] = $field_id;
                    $field['value'] = '';

                    // Add conditional classes
                    $conditional_class = '';
                    if (isset($field['show_if'])) {
                        $conditional_class .= self::add_show_hide_classes($field, 'show_if');
                    }
                    if (isset($field['hide_if'])) {
                        $conditional_class .= self::add_show_hide_classes($field, 'hide_if');
                    }

                    $output .= sprintf(
                        '<div class="wpext-field wpext-field--%1$s%2$s">
                            <div class="wpext-label">
                                <label for="%3$s"><span class="wpext-label">%4$s</span></label>
                            </div>
                            <div class="wpext-input">
                                %5$s
                            </div>
                        </div>',
                        esc_attr($field['type']),
                        $conditional_class,
                        esc_attr($field['id']),
                        esc_html($field['title']),
                        $this->do_field_method($field)
                    );
                }
            }

            $output .= '</div>'; // Close field wrapper
            $output .= '</div>'; // Close content
            $output .= '</div>'; // Close item wrapper
            $output .= '</li>'; // Close list item

            return $output;
        }


        /**
         * Generate Image Checkboxes.
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_image_checkboxes_field($args)
        {
            $output = sprintf('<input type="hidden" name="%s" value="0" />', esc_attr($args['name']));
            $output .= sprintf(
                '<ul %s>',
                $this->generate_html_atts(array(
                    'class' => 'wpext-visual-field wpext-visual-field--image-checkboxes wpext-visual-field--grid wpext-visual-field--cols',
                    'style' => $args['width'] ? sprintf('--_width: %s;', esc_attr($args['width'])) : ''
                ))
            );

            foreach ($args['choices'] as $value => $choice) {
                $field_id = sprintf('%s_%s', $args['id'], $value);
                $is_checked = is_array($args['value']) && in_array($value, $args['value'], true);
                $checked_class = $is_checked ? 'wpext-visual-field__item--checked' : '';

                $output .= $this->generate_image_field_item(
                    $checked_class,
                    $choice['image'],
                    $args['name'] . '[]',
                    $field_id,
                    $value,
                    $args['class'],
                    checked(true, $is_checked, false),
                    $choice['text'],
                    'checkbox'
                );
            }

            $output .= '</ul>';
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Image Radio field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_image_radio_field($args)
        {
            $count = count($args['choices']);

            $output = sprintf(
                '<ul %s>',
                $this->generate_html_atts(array(
                    'class' => sprintf('wpext-visual-field wpext-visual-field--image-radio wpext-visual-field--grid wpext-visual-field--col-%s', esc_attr($count)),
                    'style' => $args['width'] ? sprintf('--_width: %s;', esc_attr($args['width'])) : ''
                ))
            );

            foreach ($args['choices'] as $value => $choice) {
                $field_id = sprintf('%s_%s', $args['id'], $value);
                $checked = $value === $args['value'] ? 'checked="checked"' : '';
                $checked_class = $checked ? 'wpext-visual-field__item--checked' : '';

                $output .= $this->generate_image_field_item(
                    $checked_class,
                    $choice['image'],
                    $args['name'],
                    $field_id,
                    $value,
                    $args['class'],
                    $checked,
                    $choice['text'],
                    'radio'
                );
            }

            $output .= '</ul>';
            return $output . $this->generate_description($args);
        }

        /**
         * Generate an image field item (checkbox or radio).
         *
         * @param string $checked_class CSS class for checked state.
         * @param string $image_src Image source URL.
         * @param string $name Field name.
         * @param string $id Field ID.
         * @param string $value Field value.
         * @param string $class CSS class.
         * @param string $checked Checked attribute.
         * @param string $text Label text.
         * @param string $type Input type (checkbox or radio).
         * @return string
         */
        private function generate_image_field_item($checked_class, $image_src, $name, $id, $value, $class, $checked, $text, $type)
        {
            return sprintf(
                '<li class="wpext-visual-field__item %s">
                    <label>
                        <div class="wpext-visual-field-image-radio__img_wrap">
                            <img src="%s">
                        </div>
                        <div class="wpext-visual-field__item-footer">
                            <input type="%s" name="%s" id="%s" value="%s" class="%s" %s>
                            <span class="wpext-visual-field__item-text">%s</span>
                        </div>
                    </label>
                </li>',
                esc_attr($checked_class),
                esc_url($image_src),
                esc_attr($type),
                esc_attr($name),
                esc_attr($id),
                esc_attr($value),
                esc_attr($class),
                $checked,
                esc_html($text)
            );
        }

        /**
         * Generate: Select field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_select_field($args)
        {
            $is_multiple = isset($args['multiple']) && filter_var($args['multiple'], FILTER_VALIDATE_BOOLEAN);
            $name = $is_multiple ? $args['name'] . '[]' : $args['name'];
            $values = array_map('strval', (array) $args['value']);

            // Check if Select2 is enabled and get config
            $select2_config = isset($args['select2']) ? $args['select2'] : false;

            // Default Select2 config for multiple selects
            if ($is_multiple && $select2_config) {
                $select2_config = array_merge([
                    'width' => '100%',
                    'placeholder' => $args['placeholder'] ?? __('Select options...', WP_EXTENDED_TEXT_DOMAIN),
                ], is_array($select2_config) ? $select2_config : []);
            }

            $select_attributes = $this->build_field_attributes($args, [
                'name' => $name,
                'multiple' => $is_multiple ? 'true' : null,
                'class' => $select2_config ? 'wpext-select2' : '',
                'data-select2-config' => $select2_config ? wp_json_encode($select2_config) : '',
            ]);

            $options = $this->generate_select_options($args['choices'], $values);

            $output = sprintf(
                '<select %s>%s</select>',
                $this->generate_html_atts($select_attributes),
                $options
            );

            // Enqueue Select2 assets if enabled
            if ($select2_config) {
                Utils::enqueueScript('wpext-select2');
                Utils::enqueueStyle('wpext-select2');
            }

            return $output . $this->generate_description($args);
        }

        /**
         * Generate select options
         *
         * @param array $choices Available choices
         * @param array $values Selected values
         * @return string
         */
        private function generate_select_options($choices, $values)
        {
            $options = [];

            foreach ($choices as $value => $text) {
                if (is_array($text)) {
                    $options[] = $this->generate_option_group($value, $text, $values);
                } else {
                    $options[] = $this->generate_option($value, $text, $values);
                }
            }

            return implode('', $options);
        }

        /**
         * Generate option group
         *
         * @param string $label Group label
         * @param array $group_choices Group choices
         * @param array $values Selected values
         * @return string
         */
        private function generate_option_group($label, $group_choices, $values)
        {
            $group_options = array_map(
                function ($value, $text) use ($values) {
                    return $this->generate_option($value, $text, $values);
                },
                array_keys($group_choices),
                $group_choices
            );

            return sprintf(
                '<optgroup label="%s">%s</optgroup>',
                esc_attr($label),
                implode('', $group_options)
            );
        }

        /**
         * Generate a single option for a select field.
         *
         * @param string $value Option value.
         * @param string $text Option text.
         * @param array $selected_values Currently selected values.
         * @return string
         */
        private function generate_option($value, $text, $selected_values)
        {
            $selected = in_array((string) $value, $selected_values, true)
                ? ' selected="selected"'
                : '';

            return sprintf(
                '<option value="%s"%s>
                    %s
                </option>',
                esc_attr($value),
                $selected,
                esc_html($text)
            );
        }


        /**
         * Generate: Textarea field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_textarea_field($args)
        {
            $rows = !empty($args['attributes']['rows']) ? absint($args['attributes']['rows']) : 5;
            $cols = !empty($args['attributes']['cols']) ? absint($args['attributes']['cols']) : 55;

            $field_attributes = $this->build_field_attributes($args, [
                'rows' => $rows,
                'cols' => $cols,
            ]);

            $output = sprintf(
                '<textarea %s>%s</textarea>',
                $this->generate_html_atts($field_attributes),
                esc_textarea($args['value'])
            );
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Radio field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_radio_field($args)
        {
            $layout = isset($args['layout']) ? $args['layout'] : 'standard';
            $layout_class = 'wpext-field-radio-items';
            $style = '';

            if ($layout === 'inline') {
                $layout_class .= ' wpext-field-radio-items--inline';
            } elseif ($layout === 'columns') {
                $layout_class .= ' wpext-field-radio-items--columns';
                if (isset($args['column_width'])) {
                    $min_width = is_numeric($args['column_width']) ? $args['column_width'] . 'px' : $args['column_width'];
                    $style = sprintf(' style="--_column_width: %s;"', $min_width);
                }
            }

            $radios = array();

            foreach ($args['choices'] as $value => $text) {
                $field_id = sprintf('%s_%s', $args['id'], $value);
                $checked = $value === $args['value'] ? 'checked' : null;

                $radio_attributes = $this->build_field_attributes($args, [
                    'id' => $field_id,
                    'value' => $value,
                    'type' => 'radio',
                    'checked' => $checked,
                ]);

                $radios[] = sprintf(
                    '<div class="wpext-field-radio-item">
                        <input %s>
                        <label for="%s">%s</label>
                    </div>',
                    $this->generate_html_atts($radio_attributes),
                    esc_attr($field_id),
                    esc_html($text)
                );
            }

            $output = sprintf(
                '<div class="wpext-field-radio">
                    <div class="%s"%s>%s</div>
                </div>',
                esc_attr($layout_class),
                $style,
                implode('', $radios)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Checkbox field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_checkbox_field($args)
        {
            $hidden_field = sprintf(
                '<input type="hidden" name="%s" value="0" />',
                esc_attr($args['name'])
            );

            $checked = $args['value'] ? 'checked' : null;

            $checkbox_attributes = $this->build_field_attributes($args, [
                'type' => 'checkbox',
                'value' => '1',
                'checked' => $checked
            ]);

            $output = sprintf(
                '%s<div class="wpext-field-checkbox">
                    <input %s>
                    <label for="%s">%s</label>
                </div>',
                $hidden_field,
                $this->generate_html_atts($checkbox_attributes),
                esc_attr($args['id']),
                $this->generate_description($args)
            );

            return $output;
        }

        /**
         * Generate: Toggle field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_toggle_field($args)
        {
            $hidden_field = sprintf(
                '<input type="hidden" name="%s" value="0" />',
                esc_attr($args['name'])
            );

            $checked = $args['value'] ? 'checked' : null;

            $checkbox_attributes = $this->build_field_attributes($args, [
                'type' => 'checkbox',
                'value' => '1',
                'checked' => $checked,
                'class' => 'wpext-module__checkbox'
            ]);

            $checkbox_attributes['class'] .= sprintf(' wpext-field-checkbox__%s', sanitize_title($args['title']));

            $output = sprintf(
                '%s<div class="wpext-module__toggle">
                    <input %s>
                    <label for="%s"><span class="screen-reader-text">%s</span></label>
                </div>%s',
                $hidden_field,
                $this->generate_html_atts($checkbox_attributes),
                esc_attr($args['id']),
                esc_html($args['title']),
                $this->generate_description($args)
            );

            return $output;
        }

        /**
         * Generate: Checkboxes field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_checkboxes_field($args)
        {
            $layout = isset($args['layout']) ? $args['layout'] : 'standard';
            $layout_class = 'wpext-field-checkbox-items';
            $style = '';

            if ($layout === 'inline') {
                $layout_class .= ' wpext-field-checkbox-items--inline';
            } elseif ($layout === 'columns') {
                $layout_class .= ' wpext-field-checkbox-items--columns';
                if (isset($args['min_width'])) {
                    $min_width = is_numeric($args['min_width']) ? $args['min_width'] . 'px' : $args['min_width'];
                    $style = sprintf(' style="grid-template-columns: repeat(auto-fill, minmax(min(%s, 100%%), 1fr));"', $min_width);
                }
            }

            $hidden_field = sprintf(
                '<input type="hidden" name="%s" value="0" />',
                esc_attr($args['name'])
            );

            $checkboxes = array();

            $values = array_map('strval', (array) $args['value']);

            foreach ($args['choices'] as $value => $text) {
                $field_id = sprintf('%s_%s', $args['id'], $value);

                $checkbox_attributes = $this->build_field_attributes($args, [
                    'id' => $field_id,
                    'name' => $args['name'] . '[]',
                    'value' => $value,
                    'type' => 'checkbox',
                    'checked' => in_array((string) $value, $values, true) ? 'checked' : null,
                ]);

                $checkboxes[] = sprintf(
                    '<div class="wpext-field-checkbox-item">
                        <input %s>
                        <label for="%s">%s</label>
                    </div>',
                    $this->generate_html_atts($checkbox_attributes),
                    esc_attr($field_id),
                    esc_html($text)
                );
            }

            $output = sprintf(
                '%s
                <div class="wpext-field-checkbox">
                    <div class="%s"%s>%s</div>
                </div>',
                $hidden_field,
                esc_attr($layout_class),
                $style,
                implode('', $checkboxes)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Color field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_color_field($args)
        {
            $value = esc_attr(stripslashes($args['value']));

            Utils::enqueueScript('wpext-coloris');
            Utils::enqueueStyle('wpext-coloris');

            $input_attributes = $this->build_field_attributes($args, [
                'type' => 'text',
                'value' => $value,
                'data-coloris' => '', // Add Coloris data attribute
                'class' => isset($args['class']) ? $args['class'] : ''
            ]);

            $output = sprintf(
                '<div class="wpext-color-field">
                    <input %s>
                </div>',
                $this->generate_html_atts($input_attributes)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: File field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_file_field($args)
        {
            $defaults = array(
                'multiple' => false,
                'mime_types' => array(),
                'placeholder' => __('No file selected', WP_EXTENDED_TEXT_DOMAIN),
                'use_media_library' => true,
            );

            $args = wp_parse_args($args, $defaults);

            // Render a plain file input when requested (no Media Library)
            if (isset($args['use_media_library']) && $args['use_media_library'] === false) {
                return $this->generate_plain_file_input($args) . $this->generate_description($args);
            }

            // Ensure value is an array of attachment IDs
            $attachment_ids = $this->normalize_file_value($args['value']);

            // Generate the field HTML
            $field_html = $this->generate_file_field_html($args, $attachment_ids);

            return $field_html . $this->generate_description($args);
        }

        /**
         * Generate the HTML for the file field
         *
         * @param array $args Field arguments
         * @param array $attachment_ids Array of attachment IDs
         * @return string
         */
        private function generate_file_field_html($args, $attachment_ids)
        {
            $upload_button_id = 'upload_image_button_' . $args['id'];
            $preview_id = 'image_preview_' . $args['id'];
            $allow_multiple = $args['multiple'];

            // Prepare MIME types for JavaScript
            $mime_types_data = $this->prepare_mime_types_data($args);

            // Build container attributes
            $container_attributes = array(
                'class' => 'wpext-file-field ' . $args['class']
            );

            // Build button attributes
            $button_attributes = array(
                'type' => 'button',
                'id' => $upload_button_id,
                'class' => 'wpext-button--alt wpext-file-upload-button',
                'data-attachment-input' => $args['id'],
                'data-preview-element' => $preview_id,
                'data-upload-title' => __('Select a file to upload', WP_EXTENDED_TEXT_DOMAIN),
                'data-upload-button-text' => __('Use this file', WP_EXTENDED_TEXT_DOMAIN),
                'data-multiple' => $allow_multiple ? 'true' : 'false'
            );

            // Add MIME types data if available
            if (!empty($mime_types_data)) {
                $button_attributes['data-mime-types'] = wp_json_encode($mime_types_data);
            }

            // Build preview container attributes
            $preview_attributes = array(
                'class' => 'wpext-file-preview',
                'id' => $preview_id
            );

            ob_start(); ?>

            <div <?php echo $this->generate_html_atts($container_attributes); ?>>
                <div class="wpext-file-controls">
                    <button <?php echo $this->generate_html_atts($button_attributes); ?>>
                        <?php esc_html_e('Choose File', WP_EXTENDED_TEXT_DOMAIN); ?>
                    </button>
                </div>

                <!-- Preview container - JavaScript will populate this -->
                <div <?php echo $this->generate_html_atts($preview_attributes); ?>></div>

                <!-- Hidden fields for attachment IDs -->
                <?php echo $this->generate_attachment_inputs($args, $attachment_ids, $allow_multiple); ?>
            </div>

            <?php
            return ob_get_clean();
        }

        /**
         * Generate a plain <input type="file"> (no Media Library frame)
         *
         * @param array $args Field arguments
         * @return string
         */
        private function generate_plain_file_input($args)
        {
            $accept = '';
            if (!empty($args['mime_types']) && is_array($args['mime_types'])) {
                $accept = implode(',', array_map('esc_attr', $args['mime_types']));
            }

            $input_attributes = array(
                'type' => 'file',
                'id' => $args['id'],
                'class' => 'wpext-file-input ' . $args['class'],
            );

            if (!empty($accept)) {
                $input_attributes['accept'] = $accept;
            }

            if (!empty($args['multiple'])) {
                $input_attributes['multiple'] = 'multiple';
            }

            // Note: We intentionally do not set a name attribute pointing to the settings option
            // because plain file uploads are expected to be handled via JS (e.g., REST) rather than
            // options.php form submission (which lacks multipart support in this framework form).

            return sprintf(
                '<div class="wpext-file-field wpext-file-field--plain"><input %s /></div>',
                $this->generate_html_atts($input_attributes)
            );
        }

        /**
         * Prepare MIME types data for JavaScript
         *
         * @param array $args Field arguments
         * @return array
         */
        private function prepare_mime_types_data($args)
        {
            $mime_types = !empty($args['mime_types']) ? $args['mime_types'] : (!empty($args['mime_type']) ? $args['mime_type'] : array());

            if (empty($mime_types) || !is_array($mime_types)) {
                return array();
            }

            $filtered_mime_types = array_filter($mime_types, function ($type) {
                return !empty(trim($type));
            });

            return array_values($filtered_mime_types);
        }

        /**
         * Generate attachment input fields
         *
         * @param array $args Field arguments
         * @param array $attachment_ids Array of attachment IDs
         * @param bool $allow_multiple Whether multiple files are allowed
         * @return string
         */
        private function generate_attachment_inputs($args, $attachment_ids, $allow_multiple)
        {
            $inputs = '';

            if ($allow_multiple) {
                // Multiple files: create array inputs for existing IDs
                foreach ($attachment_ids as $attachment_id) {
                    $input_attributes = array(
                        'type' => 'hidden',
                        'name' => $args['name'] . '[]',
                        'class' => 'wpext-attachment-id',
                        'value' => $attachment_id
                    );
                    $inputs .= '<input ' . $this->generate_html_atts($input_attributes) . ' />';
                }

                // Template for new attachments
                $template_attributes = array(
                    'type' => 'hidden',
                    'name' => $args['name'] . '[]',
                    'id' => $args['id'],
                    'class' => 'wpext-attachment-id wpext-attachment-template',
                    'value' => '',
                    'style' => 'display: none;'
                );
                $inputs .= '<input ' . $this->generate_html_atts($template_attributes) . ' />';
            } else {
                // Single file: single hidden input
                $single_attributes = array(
                    'type' => 'hidden',
                    'name' => $args['name'] . '[]',
                    'id' => $args['id'],
                    'class' => 'wpext-attachment-id',
                    'value' => !empty($attachment_ids) ? $attachment_ids[0] : ''
                );
                $inputs .= '<input ' . $this->generate_html_atts($single_attributes) . ' />';
            }

            return $inputs;
        }

        /**
         * Normalize file field value to array of attachment IDs
         *
         * @param mixed $value The file field value
         * @return array Array of attachment IDs
         */
        private function normalize_file_value($value)
        {
            if (empty($value)) {
                return array();
            }

            // Handle array format (new format - array of attachment IDs)
            if (is_array($value)) {
                return array_filter(array_map('absint', $value));
            }

            // Handle single attachment ID
            if (is_numeric($value)) {
                return array(absint($value));
            }

            // Handle string format (old format for backward compatibility)
            if (is_string($value)) {
                // Try to convert URL to attachment ID
                $attachment_id = attachment_url_to_postid($value);
                if ($attachment_id) {
                    return array(absint($attachment_id));
                }
                // If URL conversion fails, return empty array
                return array();
            }

            return array();
        }

        /**
         * Generate: Editor field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_editor_field($args)
        {
            $settings = isset($args['editor_settings']) && is_array($args['editor_settings'])
                ? $args['editor_settings']
                : array();

            $settings['textarea_name'] = $args['name'];

            ob_start();
            wp_editor($args['value'], $args['id'], $settings);
            $editor = ob_get_clean();

            return $editor . $this->generate_description($args);
        }

        /**
         * Generate: Code editor field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_code_editor_field($args)
        {
            $field_attributes = $this->build_field_attributes($args, [
                'rows' => 5,
                'cols' => 55,
            ]);

            // Ensure mimetype is set, default to 'text/plain' if not provided
            $mimetype = isset($args['mimetype']) && !empty($args['mimetype']) ? $args['mimetype'] : 'text/plain';

            $default_settings = array(
                'codemirror' => array(
                    'mode' => esc_attr($mimetype),
                    'lineNumbers' => true,
                    'lineWrapping' => true,
                    'indentUnit' => 4,
                    'tabSize' => 4,
                    'indentWithTabs' => false,
                    'autoRefresh' => true,
                )
            );


            // Try to get code editor settings
            $settings = wp_enqueue_code_editor(
                array(
                    'type' => esc_attr($mimetype),
                    'codemirror' => array_merge($default_settings['codemirror'], $args['editor_settings'] ?? array())
                )
            );

            // Add settings as data attribute
            $json_settings = wp_json_encode($settings);

            $field_attributes['data-code-editor-settings'] = htmlspecialchars($json_settings, ENT_QUOTES, 'UTF-8');
            $field_attributes['data-mimetype'] = esc_attr($mimetype);

            $output = sprintf(
                '<textarea %s>%s</textarea>',
                $this->generate_html_atts($field_attributes),
                esc_textarea($args['value'])
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Custom field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_custom_field($args)
        {
            if (isset($args['callback']) && is_callable($args['callback'])) {
                $func_args = isset($args['args']) && is_array($args['args']) ? $args['args'] : [];
                ob_start();
                call_user_func_array($args['callback'], $func_args);
                return ob_get_clean();
            }

            $default = isset($args['default']) ? $args['default'] : '';
            $output = isset($args['callback']) ? $args['callback'] : $default;
            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Button field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_button_field($args)
        {
            $defaults = array(
                'title' => '',
                'class' => '',
                'attributes' => array(),
                'loader' => false,
                'style' => 'primary',
                'type' => 'button',
            );

            $args = wp_parse_args($args, $defaults);

            if ($args['style']) {
                $args['class'] .= ' wpext-button--' . $args['style'];
            }


            $attributes = array(
                'class' => $args['class'],
                'id' => $args['id'],
                'name' => $args['name'],
                'data-loading' => 'false',
            );

            $title = $args['loader']
                ? sprintf('<span class="wpext-button__text">%s</span> <span class="loader"></span>', esc_html($args['title']))
                : esc_html($args['title']);

            $tag = esc_attr($args['tag'] ?? 'button');

            // Merge classes if both exist
            if (isset($args['attributes']['class']) && isset($attributes['class'])) {
                $args['attributes']['class'] = $attributes['class'] . ' ' . $args['attributes']['class'];
                unset($attributes['class']);
            }

            $output = sprintf(
                '<%s %s>%s</%s>',
                $tag,
                $this->generate_html_atts(array_merge($attributes, $args['attributes'])),
                $title,
                $tag
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Multiple Buttons field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_buttons_field($args)
        {
            $defaults = array(
                'buttons' => array(),
                'wrapper_class' => 'wpext-buttons-group',
                'button_spacing' => '10px',
                'alignment' => 'left', // left, center, right
            );

            $args = wp_parse_args($args, $defaults);

            if (empty($args['buttons'])) {
                return $this->generate_description($args);
            }

            $buttons_html = array();

            foreach ($args['buttons'] as $index => $button) {
                // Make a copy of the button settings to prevent modifying the original
                $button_args = $button;

                // Ensure each button has a unique ID if one is not provided
                if (empty($button_args['id'])) {
                    $button_args['id'] = $args['id'] . '_button_' . $index;
                }

                // Generate a name for the button if one is not provided
                if (empty($button_args['name'])) {
                    $button_args['name'] = $args['name'] . '[' . $index . ']';
                }

                // Generate the button HTML without the description
                $button_html = $this->generate_button_field($button_args);

                // Remove any description from the returned HTML
                $button_html = preg_replace('/<div class="wpext-field__description.*?<\/div>/s', '', $button_html);

                $buttons_html[] = $button_html;
            }

            // Build CSS classes for the buttons container
            $container_classes = array(
                $args['wrapper_class'],
                'wpext-buttons-flex',
                'wpext-buttons-align-' . esc_attr($args['alignment'])
            );

            // Combine all buttons into a group with CSS variables for spacing
            $output = sprintf(
                '<div class="%s" style="--button-spacing: %s">%s</div>',
                esc_attr(implode(' ', $container_classes)),
                esc_attr($args['button_spacing']),
                implode('', $buttons_html)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Multi Inputs field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_multiinputs_field($args)
        {
            $field_titles = array_keys($args['default']);
            $values = array_values($args['value']);

            $fields = [];
            foreach ($values as $i => $value) {
                $field_id = sprintf('%s_%s', $args['id'], $i);
                $value = esc_attr(stripslashes($value));

                $field_attributes = $this->build_field_attributes($args, [
                    'type' => 'text',
                    'name' => $args['name'] . '[]',
                    'id' => $field_id,
                    'value' => $value,
                    'class' => 'regular-text ' . $args['class'],
                ]);

                $fields[] = sprintf(
                    '<div class="wpext-multifields__field">
                        <input %s />
                        <br><span>%s</span>
                    </div>',
                    $this->generate_html_atts($field_attributes),
                    esc_html($field_titles[$i])
                );
            }

            $output = sprintf(
                '<div class="wpext-multifields">
                    %s
                </div>',
                implode('', $fields)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Column field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_column_field($args)
        {
            $columns = isset($args['columns']) ? intval($args['columns']) : 2;
            $column_width = 100 / $columns;

            $column_items = [];

            if (!empty($args['fields'])) {
                foreach ($args['fields'] as $field) {
                    $field = wp_parse_args($field, $this->setting_defaults);

                    // Set the correct name attribute
                    $field['name'] = $this->generate_field_name($field['id']);

                    $field['value'] = $this->get_field_value($field['id'], $field, get_option($this->option_group . '_settings'));

                    $field_output = $this->do_field_method($field);

                    $column_items[] = sprintf(
                        '<div class="wpext-column">
                            <div class="wpext-column-field-wrapper wpext-column-field-wrapper--%2$s">
                                <label for="%3$s" class="wpext-column-field-label">%4$s</label>
                                %5$s
                            </div>
                        </div>',
                        $column_width,
                        esc_attr($field['type']),
                        esc_attr($field['id']),
                        esc_html($field['title']),
                        $field_output
                    );
                }
            }

            $output = sprintf(
                '<div class="wpext-columns wpext-columns-%d">
                    %s
                </div>',
                $columns,
                implode('', $column_items)
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Table field using GridJS
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_table_field($args)
        {
            // Ensure required JS/CSS is loaded
            Utils::enqueueStyle('wpext-gridjs', $this->get_path('assets/lib/gridjs/gridjs.min.css'));
            Utils::enqueueScript('wpext-gridjs', $this->get_path('assets/lib/gridjs/gridjs.min.js'), [], null, true);

            // Generate unique ID for this table instance
            $table_id = sprintf('table_%s', $args['id']);

            // Default table configuration
            $default_config = [
                'columns' => [],
                'endpoint' => '',
                'per_page' => 10,
                'search' => true,
                'sort' => true,
                'className' => [
                    'table' => 'wpext-table',
                    'thead' => 'wpext-table-head',
                    'tbody' => 'wpext-table-body',
                    'th' => 'wpext-table-header',
                    'td' => 'wpext-table-cell',
                    'search' => 'wpext-table-search',
                    'pagination' => 'wpext-table-pagination'
                ]
            ];

            // Merge user config with defaults
            $config = wp_parse_args(
                $args['table_config'] ?? [],
                $default_config
            );

            // Allow filtering of table configuration
            $config = apply_filters(
                sprintf('wpextended/%s/table_config', $this->option_group),
                $config,
                $args
            );

            // Start output buffer for table container
            $output = sprintf(
                '<div id="%s" class="wpext-table-container" data-config="%s"></div>',
                esc_attr($table_id),
                esc_attr(wp_json_encode($config))
            );

            return $output . $this->generate_description($args);
        }

        /**
         * Generate: Module field
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_modules_field($args)
        {
            if (empty($args['modules']) || !is_array($args['modules'])) {
                return '';
            }

            $output = '<div class="wpext-modules wpext-modules--grid">';

            foreach ($args['modules'] as $module) {
                $module_id = esc_attr($module['id']);
                $is_checked = is_array($args['value']) && in_array($module_id, $args['value'], true);
                $is_pro = !empty($module['pro']);
                $hasSettings = Modules::hasSettings($module_id);
                $group = isset($module['group']) ? $module['group'] : 'Other';
                $status = isset($module['status']) ? $module['status'] : '';

                // Build module wrapper attributes
                $module_attributes = [
                    'class' => [
                        'wpext-module',
                        $is_pro ? 'wpext-module--pro' : '',
                        $hasSettings ? 'wpext-module--has-settings' : '',
                        $is_checked ? 'wpext-module--active' : ''
                    ],
                    'data-group' => $group,
                    'data-module-id' => $module_id
                ];

                $title = $is_pro
                    ? sprintf(
                        '%s <span class="wpext-module__pro-badge">%s</span>',
                        esc_html(trim($module['name'])),
                        esc_html__('Pro', WP_EXTENDED_TEXT_DOMAIN)
                    )
                    : esc_html(trim($module['name']));

                // Build module content
                $module_content = sprintf(
                    '<div class="wpext-module__content">
                        <div class="wpext-module__header">
                            <h3 class="wpext-module__title">%s</h3>
                            %s
                            %s
                        </div>
                        <p class="wpext-module__description">%s</p>
                        %s
                    </div>',
                    $title,
                    $this->generate_module_badge($status),
                    $this->generate_module_docs_link($module),
                    esc_html($module['description']),
                    $this->generate_module_list_docs_link($module)
                );

                // Build checkbox attributes
                $checkbox_attributes = [
                    'type' => 'checkbox',
                    'name' => $args['name'] . '[]',
                    'id' => 'module-' . $module_id,
                    'value' => $module_id,
                    'class' => 'wpext-module__checkbox',
                    'checked' => $is_checked,
                    'disabled' => ($is_pro && !WP_EXTENDED_PRO),
                    'aria-label' => sprintf(esc_attr__('Enable %s module', WP_EXTENDED_TEXT_DOMAIN), $module['name'])
                ];

                // Build module actions
                $module_actions = sprintf(
                    '<div class="wpext-module__actions">
                        %s
                        <div class="wpext-module__toggle">
                            <input %s>
                            <label for="module-%s">
                                <span class="screen-reader-text">%s</span>
                            </label>
                        </div>
                    </div>',
                    $this->generate_module_settings_link($module_id, $hasSettings, $is_pro),
                    $this->generate_html_atts($checkbox_attributes),
                    esc_attr($module_id),
                    sprintf(esc_html__('Enable %s module', WP_EXTENDED_TEXT_DOMAIN), $module['name'])
                );

                // Build complete module
                $output .= sprintf(
                    '<div %s>%s%s</div>',
                    $this->generate_html_atts($module_attributes),
                    $module_content,
                    $module_actions
                );
            }

            $output .= '</div>';

            return $output . $this->generate_description($args);
        }

        /**
         * Generate module badge HTML
         *
         * @param string $status Module status text
         * @return string HTML for the badge
         */
        private function generate_module_badge($status)
        {
            if (empty($status)) {
                return '';
            }

            return sprintf(
                '<span class="wpext-module__badge">%s</span>',
                esc_html($status)
            );
        }

        /**
         * Generate module documentation link HTML for grid view
         *
         * @param array $module Module data
         * @return string HTML for the documentation link
         */
        private function generate_module_docs_link($module)
        {
            if (empty($module['documentation_link'])) {
                return '';
            }

            return sprintf(
                '<a href="%1$s" class="wpext-module__docs-link" target="_blank" aria-label="%2$s" title="%2$s">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                    </svg>
                </a>',
                Utils::generateTrackedLink($module['documentation_link'], 'modules'),
                sprintf(esc_attr__('Read documentation for %s, opens in new tab', WP_EXTENDED_TEXT_DOMAIN), $module['name'])
            );
        }

        /**
         * Generate module documentation link HTML for list view
         *
         * @param array $module Module data
         * @return string HTML for the documentation link
         */
        private function generate_module_list_docs_link($module)
        {
            if (empty($module['documentation_link'])) {
                return '';
            }

            return sprintf(
                '<a href="%s" class="wpext-module__list-docs-link" target="_blank" aria-label="%s">
                    %s
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" color="currentColor" fill="none" aria-hidden="true">
                        <path d="M16.5 7.5L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M8 6.18791C8 6.18791 16.0479 5.50949 17.2692 6.73079C18.4906 7.95209 17.812 16 17.812 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>',
                Utils::generateTrackedLink($module['documentation_link'], 'modules'),
                sprintf(esc_attr__('Read documentation for %s, opens in new tab', WP_EXTENDED_TEXT_DOMAIN), $module['name']),
                esc_html__('Read Documentation', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        /**
         * Generate module settings link HTML
         *
         * @param string $module_id Module ID
         * @param bool $hasSettings Whether module has settings
         * @param bool $is_pro Whether module is pro
         * @return string HTML for the settings link
         */
        private function generate_module_settings_link($module_id, $hasSettings, $is_pro)
        {
            if (!$hasSettings) {
                return '';
            }

            if ($is_pro && !WP_EXTENDED_PRO) {
                return '';
            }

            $module = Modules::findModule($module_id);
            $module_page_link = Utils::getModulePageLink($module_id);

            if (empty($module_page_link)) {
                return '';
            }

            return sprintf(
                '<a href="%s" class="wpext-module__settings wpext-module__settings-link" aria-label="%s">%s</a>',
                esc_url($module_page_link),
                sprintf(esc_attr__('Configure %s module', WP_EXTENDED_TEXT_DOMAIN), $module['name']),
                esc_html__('Configure', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        /**
         * Generate: Description
         *
         * @param array $args Field arguments.
         * @return string
         */
        public function generate_description($args)
        {
            if (empty($args['description']) && empty($args['conditional_desc'])) {
                return '';
            }

            $descriptions = $this->prepare_descriptions($args);
            return $this->build_description_html($descriptions);
        }

        /**
         * Prepare descriptions array
         *
         * @param array $args Field arguments.
         * @return array
         */
        private function prepare_descriptions($args)
        {
            $descriptions = [];
            $base_classes = 'wpext-description';

            if (!empty($args['description'])) {
                $descriptions[] = $this->prepare_main_description($args, $base_classes);
            }

            if ($this->has_conditional_descriptions($args)) {
                $descriptions = array_merge(
                    $descriptions,
                    $this->prepare_conditional_descriptions($args, $base_classes)
                );
            }

            return $descriptions;
        }

        /**
         * Prepare main description
         *
         * @param array $args Field arguments.
         * @param string $base_classes Base CSS classes.
         * @return array
         */
        private function prepare_main_description($args, $base_classes)
        {
            return [
                'classes' => $base_classes,
                'description' => $args['description'],
            ];
        }

        /**
         * Check if field has conditional descriptions
         *
         * @param array $args Field arguments.
         * @return bool
         */
        private function has_conditional_descriptions($args)
        {
            return 'select' === $args['type']
                && !empty($args['conditional_desc'])
                && is_array($args['conditional_desc']);
        }

        /**
         * Prepare conditional descriptions
         *
         * @param array $args Field arguments.
         * @param string $base_classes Base CSS classes.
         * @return array
         */
        private function prepare_conditional_descriptions($args, $base_classes)
        {
            $conditional_descriptions = [];

            foreach ($args['conditional_desc'] as $value => $description) {
                if ($description) {
                    $conditional_descriptions[] = [
                        'classes' => $this->get_conditional_classes($base_classes, $args, $value),
                        'value' => $value,
                        'description' => $description,
                    ];
                }
            }

            return $conditional_descriptions;
        }

        /**
         * Get conditional description classes
         *
         * @param string $base_classes Base CSS classes.
         * @param array $args Field arguments.
         * @param mixed $value Conditional value.
         * @return string
         */
        private function get_conditional_classes($base_classes, $args, $value)
        {
            $classes = $base_classes;
            if ($args['value'] !== $value) {
                $classes .= ' wpext-hide-description';
            }
            return $classes;
        }

        /**
         * Get description value
         *
         * @param mixed $value Field value.
         * @return string
         */
        private function get_description_value($value)
        {
            return is_array($value) ? serialize($value) : $value;
        }

        /**
         * Build description HTML
         *
         * @param array $descriptions Prepared descriptions.
         * @return string
         */
        private function build_description_html($descriptions)
        {
            $output = '';
            foreach ($descriptions as $description_data) {
                $attributes = $this->build_description_attributes($description_data);
                $output .= sprintf(
                    '<span %s>%s</span>',
                    $this->generate_html_atts($attributes),
                    wp_kses_post($description_data['description'])
                );
            }
            return $output;
        }

        /**
         * Build description attributes
         *
         * @param array $description_data Description data.
         * @return array
         */
        private function build_description_attributes($description_data)
        {
            $attributes = array();

            if (isset($description_data['classes']) && $description_data['classes']) {
                $attributes['class'] = $description_data['classes'];
            }

            if (isset($description_data['value']) && $description_data['value']) {
                $attributes['data-value'] = $description_data['value'];
            }

            return $attributes;
        }

        /**
         * Process fields within a section
         *
         * @param array $section
         * @param array $saved_settings
         * @param array $settings
         */
        private function process_section_fields($section, $saved_settings, &$settings)
        {
            foreach ($section['fields'] as $field) {
                $this->normalize_field_default($field);
                $setting_key = $this->get_setting_key($section, $field);
                $settings[$setting_key] = $this->get_field_value($setting_key, $field, $saved_settings);
            }
        }

        /**
         * Normalize field default value
         *
         * @param array $field
         */
        private function normalize_field_default(&$field)
        {
            if (!empty($field['default']) && is_array($field['default'])) {
                $field['default'] = array_values($field['default']);
            }
        }

        /**
         * Get the setting key for a field
         *
         * @param array $section
         * @param array $field
         * @return string
         */
        private function get_setting_key($section, $field)
        {
            if (!empty($field['name'])) {
                return $field['name'];
            }

            return $this->has_tabs()
                ? sprintf('%s_%s_%s', $section['tab_id'], $section['section_id'], $field['id'])
                : sprintf('%s_%s', $section['section_id'], $field['id']);
        }

        /**
         * Get the value for a field
         *
         * @param string $setting_key
         * @param array $field
         * @param array $saved_settings
         * @return mixed
         */
        private function get_field_value($setting_key, $field, $saved_settings)
        {
            $value = Utils::getSetting($this->option_group, $setting_key, $field['default'] ?? '');

            return apply_filters(sprintf('wpextended/%s/%s/value', $this->option_group, $setting_key), $value, $setting_key, $field);
        }

        /**
         * Handle export settings action.
         */
        public static function export_settings()
        {
            $_wpnonce     = filter_input(INPUT_GET, '_wpnonce');
            $option_group = filter_input(INPUT_GET, 'option_group');

            if (empty($_wpnonce) || ! wp_verify_nonce($_wpnonce, 'wpextended/export_settings')) {
                wp_die(esc_html__('Action failed.', 'wpext'));
            }

            if (empty($option_group)) {
                wp_die(esc_html__('No option group specified.', 'wpext'));
            }

            $options = Utils::getSettings($option_group);

            header('Content-Disposition: attachment; filename=wpext-settings-' . $option_group . '.json');

            wp_send_json($options);
        }

        /**
         * Import settings.
         */
        public function import_settings()
        {
            $_wpnonce     = filter_input(INPUT_POST, '_wpnonce');
            $option_group = filter_input(INPUT_POST, 'option_group');
            $settings     = filter_input(INPUT_POST, 'settings');

            if ($option_group !== $this->option_group) {
                return;
            }

            // verify nonce.
            if (empty($_wpnonce) || ! wp_verify_nonce($_wpnonce, 'wpextended/import_settings')) {
                wp_send_json_error();
            }

            // check if $settings is a valid json.
            if (! is_string($settings) || ! is_array(json_decode($settings, true))) {
                wp_send_json_error();
            }

            $settings_data = json_decode($settings, true);
            Utils::updateSettings($option_group, $settings_data);

            wp_send_json_success();
        }

        /**
         * Usort callback. Sorts $this->settings by "section_order"
         *
         * @param array $a Sortable Array.
         * @param array $b Sortable Array.
         *
         * @return int
         */
        public function sort_array($a, $b)
        {
            if (! isset($a['section_order'])) {
                return 0;
            }

            return ($a['section_order'] > $b['section_order']) ? 1 : -1;
        }

        /**
         * Generates the field name
         *
         * @param string $id Field ID.
         *
         * @return string
         */
        public function generate_field_name($id)
        {
            return sprintf('%s[%s]', Utils::getOptionName($this->option_group), $id);
        }

        /**
         * Check if this tab has settings
         *
         * @param string $tab_id Tab ID.
         *
         * @return bool
         */
        public function tab_has_settings($tab_id)
        {
            if (empty($this->settings)) {
                return false;
            }

            foreach ($this->settings as $settings_section) {
                if ($tab_id === $settings_section['tab_id']) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Check if this settings instance has tabs
         *
         * @return bool
         */
        public function has_tabs()
        {
            return ! empty($this->tabs);
        }

        /**
         * Add Show Hide Classes
         *
         * @param array  $args Field arguments.
         * @param string $type Type of condition (show_if or hide_if).
         *
         * @return string
         */
        public static function add_show_hide_classes($args, $type = 'show_if')
        {
            $class = '';
            $slug  = ' ' . str_replace('_', '-', $type);

            if (! isset($args[$type]) || ! is_array($args[$type])) {
                return $class;
            }

            $class .= $slug;

            foreach ($args[$type] as $condition) {
                $class .= self::process_condition($condition, $slug);
            }

            // Run the function again with hide_if if it's not already processing hide_if
            if ('hide_if' !== $type) {
                $class .= self::add_show_hide_classes($args, 'hide_if');
            }

            return $class;
        }

        /**
         * Process a single condition for show/hide classes
         *
         * @param array  $condition Condition array.
         * @param string $slug      Class slug.
         *
         * @return string
         */
        private static function process_condition($condition, $slug)
        {
            $class = '';

            if (isset($condition['field']) && isset($condition['value'])) {
                $class .= self::process_single_condition($condition, $slug);
            } else {
                $class .= self::process_multiple_conditions($condition, $slug);
            }

            return $class;
        }

        /**
         * Process a single condition
         *
         * @param array  $condition Condition array.
         * @param string $slug      Class slug.
         *
         * @return string
         */
        private static function process_single_condition($condition, $slug)
        {
            if (!isset($condition['field'])) {
                return '';
            }

            if (!isset($condition['operator'])) {
                $condition['operator'] = '===';
            }

            // If no value is set, use 'empty'
            if (!isset($condition['value'])) {
                $condition['value'] = 'empty';
            }

            // Cast value to array if int or string
            $values = is_array($condition['value']) ? $condition['value'] : array($condition['value']);
            $value_string = implode('||', $values);
            return sprintf('%s--%s%s%s', $slug, $condition['field'], $condition['operator'], $value_string);
        }

        /**
         * Process multiple conditions
         *
         * @param array  $condition Condition array.
         * @param string $slug      Class slug.
         *
         * @return string
         */
        private static function process_multiple_conditions($condition, $slug)
        {
            $and_conditions = array();

            foreach ($condition as $and_condition) {
                if (!isset($and_condition['field'])) {
                    continue;
                }

                if (!isset($and_condition['operator'])) {
                    $and_condition['operator'] = '===';
                }

                // If no value is set, use 'empty'
                if (!isset($and_condition['value'])) {
                    $and_condition['value'] = 'empty';
                }

                // Cast value to array if int or string
                $values = is_array($and_condition['value']) ? $and_condition['value'] : array($and_condition['value']);
                $value_string = implode('||', $values);
                $and_conditions[] = sprintf('%s%s%s', $and_condition['field'], $and_condition['operator'], $value_string);
            }

            if (empty($and_conditions)) {
                return '';
            }

            return sprintf('%s--%s', $slug, implode('&&', $and_conditions));
        }

        /**
         * Build field attributes
         *
         * @param array $args Field arguments.
         * @param array $extra_attributes Extra attributes to merge.
         * @return array
         */
        private function build_field_attributes($args, $extra_attributes = array())
        {
            // Ensure attributes key exists
            $args['attributes'] = isset($args['attributes']) ? $args['attributes'] : array();

            // Get all classes and merge them
            $classes = array();

            if (isset($args['class'])) {
                $classes = array_merge($classes, explode(' ', $args['class']));
            }

            if (isset($extra_attributes['class'])) {
                $classes = array_merge($classes, explode(' ', $extra_attributes['class']));
            }

            if (isset($args['attributes']['class'])) {
                $classes = array_merge($classes, explode(' ', $args['attributes']['class']));
            }

            // Remove duplicate classes and empty values
            $classes = array_filter(array_unique($classes));
            $classes = implode(' ', $classes);

            $default_attributes = array(
                'name' => $args['name'],
                'id' => $args['id'],
                'class' => $classes,
                'placeholder' => $args['placeholder'],
                'required' => !empty($args['required']),
            );

            // Append stable key/title classes for easier targeting (if provided)
            if (!empty($args['key'])) {
                $default_attributes['class'] = trim($default_attributes['class'] . ' ' . sprintf('wpext-field-key__%s', sanitize_title($args['key'])));
            }
            if (!empty($args['title']) && is_string($args['title'])) {
                $default_attributes['class'] = trim($default_attributes['class'] . ' ' . sprintf('wpext-field-title__%s', sanitize_title($args['title'])));
            }

            // Remove class from extra_attributes and args['attributes'] since we've already handled it
            unset($extra_attributes['class']);
            unset($args['attributes']['class']);

            // Merge extra attributes with defaults, allowing extra_attributes to override
            $attributes = array_merge($default_attributes, $extra_attributes);

            // Merge with any custom attributes from args
            return array_merge($attributes, $args['attributes']);
        }

        /**
         * Generate HTML attributes from an array or string.
         *
         * @param array|string $attributes An array of attributes or a string of HTML attributes.
         * @return string Sanitized string of HTML attributes.
         */
        public static function generate_html_atts($attributes)
        {
            if (empty($attributes)) {
                return '';
            }

            if (is_string($attributes)) {
                $attributes = wp_kses_hair($attributes, wp_allowed_protocols());
            }

            $html_atts = array();

            foreach ((array) $attributes as $key => $value) {
                $attr = self::process_attribute($key, $value);
                if ($attr !== '') {
                    $html_atts[] = $attr;
                }
            }

            return implode(' ', $html_atts);
        }

        /**
         * Process a single attribute.
         *
         * @param string $key The attribute key.
         * @param mixed $value The attribute value.
         * @return string The processed attribute or an empty string.
         */
        private static function process_attribute($key, $value)
        {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            if (!is_scalar($value)) {
                return '';
            }

            if (is_bool($value)) {
                return $value ? esc_html($key) : '';
            }

            $value = (string)$value;


            // Trim specific attributes where leading/trailing whitespace is typically not significant
            $trim_attributes = ['class', 'id', 'name', 'type', 'value', 'placeholder'];
            if (in_array($key, $trim_attributes, true)) {
                $value = trim($value);
            }

            if ($value === '') {
                return '';
            }

            return sprintf('%s="%s"', esc_html($key), esc_attr($value));
        }

        /**
         * Get a setting from an option group
         *
         * @param string $option_group Option group.
         * @param string $section_id   May also be prefixed with tab ID.
         * @param string $field_id     Field ID.
         *
         * @return mixed
         */
        public function getSettings($option_group = '', $field_id = '')
        {
            if ($option_group === '') {
                $option_group = $this->option_group;
            }

            $settings = get_option($option_group . '_settings');
            if (!$field_id) {
                return $settings;
            }

            if (isset($settings[$field_id])) {
                return $settings[$field_id];
            }

            return '';
        }

        /**
         * Check if the sidebar should be shown.
         *
         * @return bool Whether the sidebar should be shown.
         */
        private function should_show_sidebar()
        {
            return $this->has_tabs() || apply_filters(sprintf('wpextended/%s/always_show_sidebar', $this->option_group), false);
        }

        public function get_path($path = '')
        {
            return 'includes/framework/' . ltrim($path, '/');
        }

        /**
         * Process title template with subfield values
         *
         * @param string $template Title template string
         * @param array $item Item data
         * @param int $index Item index
         * @return string Processed title
         */
        private function process_title_template($template, $item, $index)
        {
            // Replace {#} with index number
            $title = str_replace('{#}', ($index + 1), $template);



            // Replace subfield placeholders with actual values
            if (is_array($item)) {
                foreach ($item as $field_id => $value) {
                    $placeholder = '{' . $field_id . '}';
                    if (strpos($title, $placeholder) !== false) {
                        $title = str_replace($placeholder, $value, $title);
                    }
                }
            }

            return $title;
        }
    }
}
