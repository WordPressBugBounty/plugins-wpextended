<?php

namespace Wpextended\Admin;

use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;
use Wpextended\Includes\Notices;

/**
 * Modules page controller for WP Extended plugin.
 */
class ModulesPage
{
    /**
     * Framework instance.
     *
     * @var object
     */
    protected $framework;

    /**
     * Option group name.
     *
     * @var string
     */
    protected $option_group;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->option_group = 'modules';
        $this->init();
    }

    /**
     * Initialize the module page.
     *
     * @return void
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'registerSettingsPage'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action(sprintf('wpextended/%s/before_settings_form', $this->option_group), array($this, 'renderModuleFilters'));
        add_filter(sprintf('wpextended/%s/sidebar_wrapper_end', $this->option_group), array($this, 'renderGroupLinks'));
        add_filter(sprintf('wpextended/%s/show_save_changes_button', $this->option_group), '__return_false');
        add_filter(sprintf('wpextended/%s/menu_icon_url', $this->option_group), array($this, 'renderMenuIcon'));
        $this->framework = Utils::initializeFramework($this->option_group, $this->registerSettings());
    }

    /**
     * Register the modules page in the admin menu.
     *
     * @return void
     */
    public function registerSettingsPage()
    {
        $this->framework->add_settings_page(
            array(
                'parent_slug' => '',
                'page_slug' => 'wpextended',
                'page_title'  => esc_html__('WP Extended', WP_EXTENDED_TEXT_DOMAIN),
                'menu_title'  => esc_html__('WP Extended', WP_EXTENDED_TEXT_DOMAIN),
                'capability'  => 'manage_options',
            )
        );
        $this->framework->add_settings_page(
            array(
                'parent_slug' => 'wpextended',
                'page_slug' => 'wpextended',
                'page_title'  => esc_html__('Modules', WP_EXTENDED_TEXT_DOMAIN),
                'menu_title'  => esc_html__('Modules', WP_EXTENDED_TEXT_DOMAIN),
                'capability'  => 'manage_options',
            )
        );
    }

    /**
     * Render the menu icon for the admin page.
     *
     * @return string Base64 encoded SVG icon
     */
    public function renderMenuIcon()
    {
        $svg = file_get_contents(WP_EXTENDED_URL . 'admin/assets/icons/logo.svg');
        return sprintf('data:image/svg+xml;base64,%s', base64_encode($svg));
    }

    /**
     * Register all settings for the modules page.
     *
     * @return array Settings configuration
     */
    public function registerSettings()
    {
        $settings = array(
            'tabs' => $this->getTabs(),
            'sections' => array_merge(
                $this->getSettingsSections(),
            ),
        );

        return $settings;
    }

    /**
     * Get tab definitions for the modules page.
     *
     * @return array Tabs configuration
     */
    private function getTabs()
    {
        return array(
            array(
                'id' => 'modules',
                'title' => esc_html__('Modules', WP_EXTENDED_TEXT_DOMAIN),
            ),
        );
    }

    /**
     * Get sections for the modules tab.
     *
     * @return array Settings sections configuration
     */
    private function getSettingsSections()
    {
        // Get all modules
        $modules = Modules::getAvailableModules('asc');
        $enabled_modules = Utils::getSetting('modules', 'modules', array());

        // Add active state to modules
        if (!empty($enabled_modules)) {
            foreach ($modules as &$module) {
                $module['active'] = in_array($module['id'], $enabled_modules);
            }
        }

        return array(
            array(
                'tab_id'        => 'modules',
                'section_id'    => 'modules',
                'section_title' => '',
                'section_order' => 20,
                'fields'        => array(
                    array(
                        'id'      => 'modules',
                        'title'   => 'Modules',
                        'type'    => 'modules',
                        'modules' => $modules,
                    ),
                ),
            ),
        );
    }

    /**
     * Render module filters UI.
     *
     * @return void
     */
    public function renderModuleFilters()
    {
        // Verify nonce if form is being submitted
        if (isset($_POST) && !empty($_POST)) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpextended_modules_filter')) {
                wp_die(__('Security check failed', WP_EXTENDED_TEXT_DOMAIN));
            }
        }

        // Get filter values from URL parameters or defaults
        $current_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $valid_statuses = array('all', 'active', 'inactive', 'free', 'pro');

        // Validate status
        if (!in_array($current_status, $valid_statuses)) {
            $current_status = 'all';
        }
        ?>
        <div class="wpext-module-filters">
            <div class="wpext-module-filters__group">
                <div class="wpext-module-filters__search">
                    <input type="text"
                        id="module-search"
                        class="wpext-module-filters__search-input"
                        placeholder="<?php esc_attr_e('Search modules...', WP_EXTENDED_TEXT_DOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Search modules', WP_EXTENDED_TEXT_DOMAIN); ?>"
                        value="<?php echo esc_attr($current_search); ?>">
                    <button type="button" class="wpext-module-filters__search-clear" aria-label="<?php esc_attr_e('Clear search', WP_EXTENDED_TEXT_DOMAIN); ?>" style="display: none;">
                        <img src="<?php echo esc_url(WP_EXTENDED_URL . 'admin/assets/icons/cross.svg'); ?>" alt="">
                    </button>
                </div>
                <div class="wpext-module-filters__status">
                    <select id="module-status" class="wpext-module-filters__status-select">
                        <option value="all" <?php selected($current_status, 'all'); ?>><?php esc_html_e('All Modules', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                        <option value="active" <?php selected($current_status, 'active'); ?>><?php esc_html_e('Active', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                        <option value="inactive" <?php selected($current_status, 'inactive'); ?>><?php esc_html_e('Inactive', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                        <option value="free" <?php selected($current_status, 'free'); ?>><?php esc_html_e('Free', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                        <option value="pro" <?php selected($current_status, 'pro'); ?>><?php esc_html_e('Pro', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
                <div class="wpext-module-filters__total">
                    <p class="wpext-module-filters__total-text">
                        <?php
                        printf(
                            /* translators: %1$s: number of modules, %2$s: total number of modules */
                            esc_html__('%1$s of %2$s modules', WP_EXTENDED_TEXT_DOMAIN),
                            sprintf(
                                '<span class="wpext-module-filters__current-count">%d</span>',
                                count(Modules::getAvailableModules())
                            ),
                            sprintf(
                                '<span class="wpext-module-filters__total-count">%d</span>',
                                count(Modules::getAvailableModules())
                            )
                        );
                        ?>
                    </p>
                </div>
            </div>
            <div class="wpext-module-filters__layout">
                <button type="button"
                    class="wpext-module-filters__layout-button active"
                    data-layout="grid"
                    aria-label="<?php esc_attr_e('Grid view', WP_EXTENDED_TEXT_DOMAIN); ?>">
                    <img src="<?php echo esc_url(WP_EXTENDED_URL . 'admin/assets/icons/grid.svg'); ?>" alt="">
                </button>
                <button type="button"
                    class="wpext-module-filters__layout-button"
                    data-layout="list"
                    aria-label="<?php esc_attr_e('List view', WP_EXTENDED_TEXT_DOMAIN); ?>">
                    <img src="<?php echo esc_url(WP_EXTENDED_URL . 'admin/assets/icons/row.svg'); ?>" alt="">
                </button>
            </div>
        </div>
        <div class="wpext-no-modules" style="display: none;">
            <?php esc_html_e('No modules found', WP_EXTENDED_TEXT_DOMAIN); ?>
        </div>
        <div class="wpext-live-region" aria-live="polite"></div>
        <?php
        // Add both nonces for filtering and module updates
        wp_nonce_field('wpextended_modules_filter');
        wp_nonce_field('wpextended_modules_update', '_wpextended_modules_update_nonce');
    }

    /**
     * Render group links in the sidebar.
     *
     * @param string $sidebar Sidebar HTML content
     * @return string Modified sidebar HTML
     */
    public function renderGroupLinks($sidebar)
    {
        $modules =  Modules::getAvailableModules();
        $groups = array();
        $list = '';
        $current_group = isset($_GET['group']) ? sanitize_title($_GET['group']) : 'all-modules';

        $groups = array(
            'all-modules' => array(
                'id' => 'all-modules',
                'title' => esc_html__('All Modules', WP_EXTENDED_TEXT_DOMAIN),
                'active' => false,
            ),
            'admin-tools' => array(
                'id' => 'admin-tools',
                'title' => esc_html__('Admin Tools', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'admin-tools',
            ),
            'content-management' => array(
                'id' => 'content-management',
                'title' => esc_html__('Content Management', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'content-management',
            ),
            'media-tools' => array(
                'id' => 'media-tools',
                'title' => esc_html__('Media Tools', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'media-tools',
            ),
            'security-privacy' => array(
                'id' => 'security-privacy',
                'title' => esc_html__('Security & Privacy', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'security-privacy',
            ),
            'performance-control' => array(
                'id' => 'performance-control',
                'title' => esc_html__('Performance & Control', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'performance-control',
            ),
            'developer-tools' => array(
                'id' => 'developer-tools',
                'title' => esc_html__('Developer Tools', WP_EXTENDED_TEXT_DOMAIN),
                'active' => $current_group === 'developer-tools',
            ),
        );

        // Add category links
        foreach ($groups as $group) {
            $current_url = add_query_arg(
                array('group' => sanitize_title($group['id'])),
                esc_url(Utils::getModulePageLink('modules'))
            );

            $list .= sprintf(
                '<li class="wpext-nav__item%s">
                    <a href="%s" class="wpext-nav__item-link">%s</a>
                </li>',
                $group['active'] ? ' wpext-nav__item--active' : '',
                esc_url($current_url),
                esc_html($group['title'])
            );
        }

        return sprintf(
            '%s<ul class="wpext-nav" data-type="groups">%s</ul>',
            $sidebar,
            $list
        );
    }

    /**
     * Enqueue assets for the modules page.
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets($hook)
    {
        // Only load on modules page
        if (!in_array($hook, array('toplevel_page_wpextended', 'wp-extended_page_wpextended'))) {
            return;
        }

        Utils::enqueueStyle(
            'wpextended-modules',
            'admin/assets/css/modules.css',
            array(),
        );

        Utils::enqueueScript(
            'wpextended-modules',
            'admin/assets/js/modules.js',
            array('jquery', 'wpext-notify'),
            true
        );

        // Get all modules and prepare search data
        $modules = Modules::getAvailableModules();
        $modules_search = array();

        foreach ($modules as $module) {
            $search_terms = array(
                $module['name'],
                $module['description']
            );

            if (!empty($module['keywords'])) {
                $search_terms[] = implode(' ', $module['keywords']);
            }

            $modules_search[$module['id']] = strtolower(implode(' ', $search_terms));
        }

        // Add REST API settings and modules search data
        wp_localize_script('wpextended-modules', 'wpextended_modules', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'modulesSearch' => $modules_search,
        ));
    }

    /**
     * Handle module activation/deactivation when settings are saved.
     *
     * @param array $old_value Previous settings values
     * @param array $new_value New settings values
     * @return void
     */
    public function handleModuleStateChanges($old_value, $new_value)
    {
        $old_modules = isset($old_value['modules']) ? $old_value['modules'] : array();
        $new_modules = isset($new_value['modules']) ? $new_value['modules'] : array();

        // Find modules that were deactivated
        $deactivated_modules = array_diff($old_modules, $new_modules);

        if (!empty($deactivated_modules)) {
            $message = sprintf(
                __('Modules deactivated: %s', WP_EXTENDED_TEXT_DOMAIN),
                implode(', ', $deactivated_modules)
            );
            add_settings_error(
                'modules_settings',
                'modules_deactivated',
                $message,
                'info'
            );
        }

        foreach ($deactivated_modules as $module_id) {
            $module = Modules::get_module($module_id);
            if ($module) {
                $module->deactivate();
            }
        }
    }
}
