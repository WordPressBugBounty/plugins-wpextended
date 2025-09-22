<?php

namespace Wpextended\Admin;

use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;
use Wpextended\Includes\Services\Export\ExportService;
use Wpextended\Modules\CodeSnippets\Includes\SnippetManager;

/**
 * Settings page controller for WP Extended plugin.
 */
class SettingsPage
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
     * Settings array.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->option_group = 'global';
        $this->settings = Utils::getSettings($this->option_group);
        $this->init();
    }

    /**
     * Initialize the settings page.
     *
     * @return void
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'registerSettingsPage'));

        $this->framework = Utils::initializeFramework($this->option_group, $this->registerSettings());

        add_action('rest_api_init', array($this, 'registerRestRoutes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    /**
     * Register the settings page in the admin menu.
     *
     * @return void
     */
    public function registerSettingsPage()
    {
        $this->framework->add_settings_page(
            array(
                'parent_slug' => 'wpextended',
                'page_slug' => 'wpextended-settings',
                'page_title'  => esc_html__('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'menu_title'  => esc_html__('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'capability'  => 'manage_options',
            )
        );
    }

    /**
     * Register all settings for the plugin.
     *
     * @return array Settings configuration
     */
    public function registerSettings()
    {
        $settings = array(
            'tabs' => $this->getTabs(),
            'sections' => array_merge(
                $this->getSettingsSections(),
                $this->getImportExportSections(),
                $this->getSystemInfoSections()
            )
        );

        return $settings;
    }

    /**
     * Get tab definitions for the settings page.
     *
     * @return array Tabs configuration
     */
    private function getTabs()
    {
        return array(
            array(
                'id' => 'settings',
                'title' => esc_html__('Settings', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'import_export',
                'title' => esc_html__('Import/Export', WP_EXTENDED_TEXT_DOMAIN),
            ),
            array(
                'id' => 'system_info',
                'title' => esc_html__('System Info', WP_EXTENDED_TEXT_DOMAIN),
            ),
        );
    }

    /**
     * Get sections for the settings tab.
     *
     * @return array Settings sections configuration
     */
    private function getSettingsSections()
    {
        $sections = array();

        $sections[] = array(
            'tab_id'        => 'settings',
            'section_id'    => 'general',
            'section_title' => __('General Settings', WP_EXTENDED_TEXT_DOMAIN),
            'section_description' => __('Configure basic plugin behavior and functionality.', WP_EXTENDED_TEXT_DOMAIN),
            'section_order' => 10,
            'fields'        => array(
                array(
                    'id'      => 'remove_plugin_data',
                    'title'   => __('Delete Plugin Data on Removal', WP_EXTENDED_TEXT_DOMAIN),
                    'description' => __('When enabled, all plugin data including settings and module configurations will be permanently deleted when the plugin is removed.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'    => 'toggle',
                ),
            ),
        );

        $module_management_fields = $this->getModuleManagementFields();

        if (!empty($module_management_fields)) {
            $sections[] = array(
                'tab_id'        => 'settings',
                'section_id'    => 'module_management',
                'section_title' => __('Module Management', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Configure how modules are displayed and managed in the admin interface.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 20,
                'fields'        => $module_management_fields,
            );
        }

        return $sections;
    }

    /**
     * Register REST API routes for settings import/export.
     */
    public function registerRestRoutes()
    {
        register_rest_route(
            WP_EXTENDED_API_NAMESPACE,
            '/settings/export',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'restExportSettings'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'args' => array(
                    'include' => array(
                        'required' => false,
                        'type' => 'array',
                    ),
                ),
            )
        );

        register_rest_route(
            WP_EXTENDED_API_NAMESPACE,
            '/settings/import',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'restImportSettings'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            )
        );
    }

    /**
     * REST: Export settings. Streams a JSON download via ExportService.
     * Accepts include[]=global|modules|code_snippets via query.
     */
    public function restExportSettings($request)
    {
        // Accept nonce in query (optional) to align with REST cookie auth
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!empty($nonce) && !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('invalid_nonce', esc_html__('Invalid nonce.', WP_EXTENDED_TEXT_DOMAIN), array('status' => 403));
        }

        $include = $request->get_param('include');
        if (!is_array($include) || empty($include)) {
            $include = array('global', 'modules');
        }

        // Build rows (reuse logic from handleExportSettings)
        $rows = array();

        $meta = array(
            'site' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'generated_at' => current_time('mysql', false),
            'plugin_version' => defined('WP_EXTENDED_VERSION') ? WP_EXTENDED_VERSION : '',
        );
        $rows[] = array('meta', $meta);

        if (in_array('global', $include, true)) {
            $global_settings = Utils::getSettings('global');
            $rows[] = array('global', is_array($global_settings) ? $global_settings : array());
        }

        if (in_array('modules', $include, true)) {
            $enabled = Modules::getEnabledModules();
            $module_settings = array();
            $available = Modules::getAvailableModules();
            foreach ($available as $module) {
                $module_id = isset($module['id']) ? $module['id'] : '';
                if (!$module_id) {
                    continue;
                }
                $settings = Utils::getSettings($module_id);
                if (!empty($settings)) {
                    $module_settings[$module_id] = $settings;
                }
            }
            $rows[] = array('modules', array(
                'enabled' => array_values(is_array($enabled) ? $enabled : array()),
                'settings' => $module_settings,
            ));
        }

        if (in_array('code_snippets', $include, true) && class_exists('Wpextended\\Modules\\CodeSnippets\\Includes\\SnippetManager')) {
            $snippetManager = new SnippetManager();
            $snippets = $snippetManager->getAllSnippets();
            $export_snippets = array();
            if (is_array($snippets)) {
                foreach ($snippets as $snippet) {
                    $export_snippets[] = array(
                        'id' => method_exists($snippet, 'getId') ? $snippet->getId() : ($snippet['id'] ?? ''),
                        'name' => method_exists($snippet, 'getName') ? $snippet->getName() : ($snippet['name'] ?? ''),
                        'type' => method_exists($snippet, 'getType') ? $snippet->getType() : ($snippet['type'] ?? 'php'),
                        'code' => method_exists($snippet, 'getCode') ? $snippet->getCode() : ($snippet['code'] ?? ''),
                        'enabled' => method_exists($snippet, 'isEnabled') ? (bool) $snippet->isEnabled() : (bool) ($snippet['enabled'] ?? false),
                        'description' => method_exists($snippet, 'getDescription') ? $snippet->getDescription() : ($snippet['description'] ?? ''),
                        'run_location' => method_exists($snippet, 'getRunLocation') ? $snippet->getRunLocation() : ($snippet['run_location'] ?? ''),
                        'priority' => method_exists($snippet, 'getPriority') ? (int) $snippet->getPriority() : (int) ($snippet['priority'] ?? 10),
                    );
                }
            }
            $rows[] = array('code_snippets', $export_snippets);
        }

        $data = array('body' => $rows);
        $options = array(
            'keys' => array('section', 'data'),
            'filename' => sprintf('wpextended-settings-export-%s.json', date('Y-m-d')),
        );

        $service = new ExportService('settings');
        $service->export($data, 'json', $options);
        exit;
    }

    /**
     * REST: Import aggregated JSON.
     */
    public function restImportSettings($request)
    {
        $body = $request->get_body();
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new \WP_Error('invalid_payload', esc_html__('Invalid JSON payload.', WP_EXTENDED_TEXT_DOMAIN), array('status' => 400));
        }

        // Always overwrite on import for simplicity
        $overwrite = true;

        // Detect aggregated format
        if (!(isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('section', $decoded[0]) && array_key_exists('data', $decoded[0]))) {
            // Treat as single-group import for global
            Utils::updateSettings('global', $decoded);
            return rest_ensure_response(array('success' => true));
        }

        $sections = array();
        foreach ($decoded as $row) {
            if (!is_array($row) || !isset($row['section'])) {
                continue;
            }
            $sections[$row['section']] = $row['data'] ?? array();
        }

        if (isset($sections['global']) && is_array($sections['global'])) {
            Utils::updateSettings('global', (array) $sections['global']);
        }

        if (isset($sections['modules']) && is_array($sections['modules'])) {
            $modules_data = $sections['modules'];
            if (isset($modules_data['enabled']) && is_array($modules_data['enabled'])) {
                $enabled_new = array_values($modules_data['enabled']);
                $modules_settings = Utils::getSettings('modules');
                if (!is_array($modules_settings)) {
                    $modules_settings = array();
                }
                $modules_settings['modules'] = $enabled_new;
                Utils::updateSettings('modules', $modules_settings);
            }

            if (isset($modules_data['settings']) && is_array($modules_data['settings'])) {
                foreach ($modules_data['settings'] as $module_id => $module_settings) {
                    $module_id = sanitize_key($module_id);
                    if (!is_array($module_settings)) {
                        continue;
                    }
                    Utils::updateSettings($module_id, $module_settings);
                }
            }
        }

        if (isset($sections['code_snippets']) && class_exists('Wpextended\\Modules\\CodeSnippets\\Includes\\SnippetManager')) {
            $snippets = is_array($sections['code_snippets']) ? $sections['code_snippets'] : array();
            $snippetManager = new SnippetManager();
            foreach ($snippets as $snippet) {
                if (!is_array($snippet)) {
                    continue;
                }
                $payload = array(
                    'name' => isset($snippet['name']) ? sanitize_text_field($snippet['name']) : '',
                    'type' => isset($snippet['type']) ? sanitize_key($snippet['type']) : 'php',
                    'code' => isset($snippet['code']) ? (string) $snippet['code'] : '',
                    'enabled' => !empty($snippet['enabled']),
                    'description' => isset($snippet['description']) ? sanitize_textarea_field($snippet['description']) : '',
                    'run_location' => isset($snippet['run_location']) ? sanitize_key($snippet['run_location']) : 'init',
                    'priority' => isset($snippet['priority']) ? (int) $snippet['priority'] : 10,
                );

                if (isset($snippet['id']) && $snippet['id'] !== '' && method_exists($snippetManager, 'getAdminSnippet')) {
                    $existing = $snippetManager->getAdminSnippet($snippet['id']);
                    if ($existing && method_exists($snippetManager, 'updateSnippet')) {
                        $snippetManager->updateSnippet($snippet['id'], $payload);
                        continue;
                    }
                }

                if (method_exists($snippetManager, 'createSnippet')) {
                    $snippetManager->createSnippet($payload);
                }
            }
        }

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Enqueue settings script on the settings page.
     */
    public function enqueueAssets()
    {
        if (!Utils::isPluginScreen('settings')) {
            return;
        }

        Utils::enqueueScript('wpext-settings', 'admin/assets/js/settings.js', array('wpext-notify'), true);

        wp_localize_script(
            'wpext-settings',
            'wpextSettings',
            array(
                'restUrl' => rest_url(WP_EXTENDED_API_NAMESPACE),
                'nonce' => wp_create_nonce('wp_rest'),
                'i18n' => array(
                    'exporting' => __('Preparing export...', WP_EXTENDED_TEXT_DOMAIN),
                    'exportFailed' => __('Export failed.', WP_EXTENDED_TEXT_DOMAIN),
                    'exportSuccess' => __('Settings exported successfully.', WP_EXTENDED_TEXT_DOMAIN),
                    'importing' => __('Importing settings...', WP_EXTENDED_TEXT_DOMAIN),
                    'importSuccess' => __('Settings imported successfully.', WP_EXTENDED_TEXT_DOMAIN),
                    'importFailed' => __('Import failed.', WP_EXTENDED_TEXT_DOMAIN),
                ),
            )
        );
    }

    /**
     * Get module management fields.
     *
     * @return array Module management fields configuration
     */
    private function getModuleManagementFields()
    {
        $fields = array();

        // Only add submodule fields if there are available submodules
        $submodules = $this->getSubmodules();

        if (!empty($submodules)) {
            $fields[] = array(
                'id' => 'display_submodules',
                'title' => __('Display Modules in Submenu', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('Show individual module settings pages in the admin submenu for easier access.', WP_EXTENDED_TEXT_DOMAIN),
                'type' => 'toggle',
            );
            $fields[] = array(
                'id' => 'enable_all_submodules',
                'title' => __('Enable All Submodules', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('Enable all modules in the submenu.', WP_EXTENDED_TEXT_DOMAIN),
                'type' => 'toggle',
                'default' => false,
                'show_if' => array(
                    array(
                        'field' => 'display_submodules',
                        'value' => array(1),
                    ),
                ),
            );
            $fields[] = array(
                'id' => 'enabled_submodules',
                'title' => __('Select Submodules to Display', WP_EXTENDED_TEXT_DOMAIN),
                'description' => __('Select which modules should appear in the submenu when the above option is enabled.', WP_EXTENDED_TEXT_DOMAIN),
                'type' => 'checkboxes',
                'choices' => $submodules,
                'show_if'  => array(
                    array(
                        'field' => 'display_submodules',
                        'value' => array(1),
                    ),
                    array(
                        'field' => 'enable_all_submodules',
                        'value' => array(0),
                    ),
                ),
            );
        }

        return $fields;
    }

    /**
     * Get sections for the import/export tab.
     *
     * @return array Import/export sections configuration
     */
    private function getImportExportSections()
    {
        return array(
            array(
                'tab_id'        => 'import_export',
                'section_id'    => 'import',
                'section_title' => __('Import Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Import plugin settings and module configurations from a backup file.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'      => 'import_file',
                        'title'   => __('Import File', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select a JSON file containing your exported settings.', WP_EXTENDED_TEXT_DOMAIN),
                        'type'    => 'file',
                        'mime_types' => array('application/json', '.json'),
                        'use_media_library' => false,
                        'button_text' => __('Choose File', WP_EXTENDED_TEXT_DOMAIN),
                    ),
                    array(
                        'id'      => 'import_button',
                        'title'   => __('Import Settings', WP_EXTENDED_TEXT_DOMAIN),
                        'type'    => 'button',
                        'button_text' => __('Import Settings', WP_EXTENDED_TEXT_DOMAIN),
                        'button_type' => 'primary',
                    ),
                ),
            ),
            array(
                'tab_id'        => 'import_export',
                'section_id'    => 'export',
                'section_title' => __('Export Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Export your current plugin settings and module configurations for backup or migration purposes.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 20,
                'fields'        => array(
                    array(
                        'id'      => 'export_include_options',
                        'title'   => __('Include Options', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select which settings you want to include in the export file.', WP_EXTENDED_TEXT_DOMAIN),
                        'type'    => 'checkboxes',
                        'choices' => array(
                            'global' => __('Global Settings', WP_EXTENDED_TEXT_DOMAIN),
                            'modules' => __('Module Settings', WP_EXTENDED_TEXT_DOMAIN),
                            'code_snippets' => __('Code Snippets', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'default' => array(
                            'global',
                            'modules',
                        ),
                    ),
                    array(
                        'id'      => 'export_button',
                        'title'   => __('Export Settings', WP_EXTENDED_TEXT_DOMAIN),
                        'type'    => 'button',
                        'button_text' => __('Download Export File', WP_EXTENDED_TEXT_DOMAIN),
                        'button_type' => 'primary',
                    ),
                ),
            ),
        );
    }

    /**
     * Get sections for the system info tab.
     *
     * @return array System info sections configuration
     */
    private function getSystemInfoSections()
    {
        $sections = array(
            array(
                'tab_id'        => 'system_info',
                'section_id'    => 'plugin_info',
                'section_title' => __('Plugin Information', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Current plugin version and licensing information.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'      => 'plugin_info',
                        'type'    => 'custom',
                        'callback' => array($this, 'getPluginInfo'),
                    )
                ),
            ),
            array(
                'tab_id'        => 'system_info',
                'section_id'    => 'system_requirements',
                'section_title' => __('System Requirements', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Server environment and system requirements information.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 20,
                'fields'        => array(
                    array(
                        'id'      => 'system_requirements',
                        'type'    => 'custom',
                        'callback' => array($this, 'getSystemRequirements'),
                    )
                ),
            ),
        );

        // Only add active integrations section if integrations are detected
        if ($this->hasActiveIntegrations()) {
            $sections[] = array(
                'tab_id'        => 'system_info',
                'section_id'    => 'active_integrations',
                'section_title' => __('Active Integrations', WP_EXTENDED_TEXT_DOMAIN),
                'section_description' => __('Detected plugins and themes that integrate with WP Extended.', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 30,
                'fields'        => array(
                    array(
                        'id'      => 'active_integrations',
                        'type'    => 'custom',
                        'callback' => array($this, 'getActiveIntegrations'),
                    )
                ),
            );
        }

        return $sections;
    }

    /**
     * Get plugin information details.
     *
     * @return void
     */
    public function getPluginInfo()
    {
        $plugin_info = array(
            array(
                'label' => __('License:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => defined('WP_EXTENDED_PRO') && WP_EXTENDED_PRO ? __('Pro', WP_EXTENDED_TEXT_DOMAIN) : __('Free', WP_EXTENDED_TEXT_DOMAIN)
            ),
            array(
                'label' => __('Plugin Version:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => WP_EXTENDED_VERSION
            ),
            array(
                'label' => __('Plugin Path:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => sprintf('<code>%s</code>', WP_EXTENDED_PATH)
            ),
            array(
                'label' => __('Plugin URL:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => sprintf('<code>%s</code>', WP_EXTENDED_URL)
            ),
        );

        $this->renderInfoList($plugin_info);
    }

    /**
     * Get system requirements information.
     *
     * @return void
     */
    public function getSystemRequirements()
    {
        $system_info = array(
            array(
                'label' => __('PHP Version:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => phpversion() . (strnatcmp(phpversion(), '7.4.0') < 0 ?
                    ' <span data-tooltip="You should consider updating your PHP version"><i class="fa-solid fa-circle-exclamation"></i></span>' : '')
            ),
            array(
                'label' => __('WordPress Version:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => get_bloginfo('version')
            ),
            array(
                'label' => __('MySQL Version:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => $this->getMySQLVersion()
            ),
            array(
                'label' => __('Memory Limit:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => WP_MEMORY_LIMIT
            ),
            array(
                'label' => __('Max Upload Size:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => size_format(wp_max_upload_size())
            ),
            array(
                'label' => __('Max Post Size:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => ini_get('post_max_size')
            ),
        );

        $this->renderInfoList($system_info);
    }

    /**
     * Get active integrations information.
     *
     * @return void
     */
    public function getActiveIntegrations()
    {
        $integrations = array();

        // Check for page builders
        $page_builders = array();
        if (defined('ELEMENTOR_VERSION') || defined('ELEMENTOR_PRO_VERSION')) {
            $page_builders[] = 'Elementor';
        }
        if (defined('CT_VERSION')) {
            $page_builders[] = 'Oxygen';
        }
        if (defined('__BREAKDANCE_VERSION')) {
            $page_builders[] = 'Breakdance';
        }
        if (defined('WPB_VC_VERSION')) {
            $page_builders[] = 'Visual Composer';
        }

        if (!empty($page_builders)) {
            $integrations[] = array(
                'label' => __('Active Page Builders:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => implode(', ', $page_builders)
            );
        }

        // Check for popular plugins
        $popular_plugins = array();
        if (class_exists('WooCommerce')) {
            $popular_plugins[] = 'WooCommerce';
        }
        if (class_exists('Easy_Digital_Downloads')) {
            $popular_plugins[] = 'Easy Digital Downloads';
        }
        if (function_exists('bbpress')) {
            $popular_plugins[] = 'bbPress';
        }
        if (class_exists('BuddyPress')) {
            $popular_plugins[] = 'BuddyPress';
        }

        if (!empty($popular_plugins)) {
            $integrations[] = array(
                'label' => __('Active Popular Plugins:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => implode(', ', $popular_plugins)
            );
        }

        // Check for caching plugins
        $caching_plugins = array();
        if (defined('W3TC_VERSION')) {
            $caching_plugins[] = 'W3 Total Cache';
        }
        if (defined('WP_ROCKET_VERSION')) {
            $caching_plugins[] = 'WP Rocket';
        }
        if (class_exists('WpeCommon')) {
            $caching_plugins[] = 'WP Engine Cache';
        }

        if (!empty($caching_plugins)) {
            $integrations[] = array(
                'label' => __('Active Caching Plugins:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => implode(', ', $caching_plugins)
            );
        }

        if (empty($integrations)) {
            $integrations[] = array(
                'label' => __('No Integrations Detected:', WP_EXTENDED_TEXT_DOMAIN),
                'value' => __('No known integrations are currently active.', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        $this->renderInfoList($integrations);
    }

    /**
     * Get MySQL version.
     *
     * @return string MySQL version
     */
    private function getMySQLVersion()
    {
        global $wpdb;
        return $wpdb->db_version();
    }

    /**
     * Render an information list.
     *
     * @param array $info_array Array of information items with 'label' and 'value' keys
     * @return void
     */
    private function renderInfoList($info_array)
    {
        $output = '<ul class="wpext-system-info-list">';
        foreach ($info_array as $info) {
            $output .= sprintf(
                '<li><strong>%s</strong> <span>%s</span></li>',
                esc_html($info['label']),
                wp_kses_post($info['value'])
            );
        }
        $output .= '</ul>';

        echo $output;
    }

    /**
     * Get submodules for the choices
     *
     * @return array Submodules configuration
     */
    public function getSubmodules()
    {
        // Get available modules for the choices
        $module_choices = array();
        $modules = Modules::getAvailableModules();

        foreach ($modules as $module) {
            // Skip modules that don't have settings
            if (!Modules::hasSettings($module['id'])) {
                continue;
            }
            $module_choices[$module['id']] = $module['name'];
        }

        // Sort modules by name in ascending order
        asort($module_choices);

        return $module_choices;
    }

    /**
     * Check if there are active integrations.
     *
     * @return bool True if there are active integrations, false otherwise
     */
    private function hasActiveIntegrations()
    {
        // Check for page builders
        if (
            defined('ELEMENTOR_VERSION') || defined('ELEMENTOR_PRO_VERSION') ||
            defined('CT_VERSION') ||
            defined('__BREAKDANCE_VERSION') ||
            defined('WPB_VC_VERSION')
        ) {
            return true;
        }

        // Check for popular plugins
        if (
            class_exists('WooCommerce') ||
            class_exists('Easy_Digital_Downloads') ||
            function_exists('bbpress') ||
            class_exists('BuddyPress')
        ) {
            return true;
        }

        // Check for caching plugins
        if (
            defined('W3TC_VERSION') ||
            defined('WP_ROCKET_VERSION') ||
            class_exists('WpeCommon')
        ) {
            return true;
        }

        return false;
    }
}
