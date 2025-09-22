<?php

namespace Wpextended\Modules;

use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;
use Wpextended\Includes\Notices;
use Wpextended\Includes\Framework\Framework;

/**
 * Base module class that all WP Extended modules extend from.
 *
 * This class provides core functionality for module management including:
 * - Module metadata loading and validation
 * - Settings management with lazy loading
 * - Pro version integration
 * - Module state management
 * - Module initialization
 * - Database migrations
 *
 * @since 1.0.0
 * @package Wpextended\Modules
 */
abstract class BaseModule
{
    private static $instances = array();

    /**
     * Unique identifier for the module.
     *
     * @since 1.0.0
     * @var string
     */
    protected $module_id;

    /**
     * Module settings fields configuration.
     *
     * @since 1.0.0
     * @var array
     */
    protected $settings_fields;

    /**
     * Current settings data for the module.
     *
     * @since 1.0.0
     * @var array
     */
    protected $settings;

    /**
     * Framework instance for handling settings.
     *
     * @since 1.0.0
     * @var Framework
     */
    protected $framework;

    /**
     * Module metadata from meta.json.
     *
     * @since 1.0.0
     * @var array
     */
    protected $meta;

    /**
     * Whether the module has been initialized.
     *
     * @since 1.0.0
     * @var bool
     */
    protected $initialized = false;

    /**
     * Whether settings fields have been loaded.
     *
     * @since 1.0.0
     * @var bool
     */
    protected $settings_fields_loaded = false;

    /**
     * Array of dependencies that need to be met.
     *
     * @since 1.0.0
     * @var array
     */
    protected $dependencies = array();

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $module_id Unique identifier for the module.
     */
    public function __construct($module_id)
    {
        $this->module_id = $module_id;
        add_action('init', array($this, 'setup'));
    }

    /*
    |--------------------------------------------------------------------------
    | Module Setup & Initialization
    |--------------------------------------------------------------------------
    */

    /**
     * Setup the module.
     *
     * @since 1.0.0
     * @return void
     */
    public function setup()
    {
        if ($this->initialized) {
            return;
        }

        if (!$this->loadAndValidate()) {
            error_log("BaseModule: setup: " . $this->module_id . " failed to load and validate");
            return;
        }

        $this->settings = Utils::getSettings($this->module_id);

        $this->maybeLoadProVersion();
        $this->setupSettings();
        $this->setupAdminHooks();

        // Check dependencies but don't stop initialization
        $this->checkDependencies();

        // Only run init if the module is enabled
        if ($this->isModuleEnabled()) {
            $this->init();
        }

        $this->initialized = true;
    }

    /**
     * Initialize the module.
     * This method should be used for any initialization code including:
     * - Registering hooks
     * - Setting up functionality
     * - Initializing components
     * - Registering custom post types
     * - Setting up integrations
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init();

    /**
     * Get an instance of the module.
     *
     * @param string $module_id Unique identifier for the module.
     * @return static The module instance.
     */
    public static function getInstance($module_id)
    {
        if (!isset(self::$instances[$module_id])) {
            self::$instances[$module_id] = new static($module_id);
        }
        return self::$instances[$module_id];
    }

    /**
     * Load and validate module metadata.
     *
     * @return bool Whether the module was loaded and validated successfully.
     */
    private function loadAndValidate()
    {
        $this->loadMeta();
        if (!$this->validateMeta()) {
            return false;
        }
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Module Lifecycle Management
    |--------------------------------------------------------------------------
    */

    /**
     * Activate the module. This is called once when the module is first enabled.
     *
     * @return true|string Returns true on success, error message string on failure
     */
    public function activate()
    {
        try {
            // Run one-time activation script
            $this->runActivateScript();

            // Call module-specific activation code
            $this->onActivate();

            // Trigger pro activation hook
            do_action(sprintf('wpextended/%s/on_activate', $this->module_id));

            return true;
        } catch (\Exception $e) {
            $this->initialized = false;
            error_log('Error during module activation: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Deactivate the module. This is called once when the module is disabled.
     *
     * @return true|string Returns true on success, error message string on failure
     */
    public function deactivate()
    {
        try {
            // Run one-time deactivation script
            $this->runDeactivateScript();

            // Call module-specific deactivation code
            $this->onDeactivate();

            // Clean up any runtime hooks
            $this->unregisterHooks();

            // Trigger pro deactivation hook
            do_action(sprintf('wpextended/%s/on_deactivate', $this->module_id));

            return true;
        } catch (\Exception $e) {
            error_log('Error during module deactivation: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Called when the module is first activated.
     * Override this in child classes for module-specific activation code.
     *
     * @return void
     */
    protected function onActivate()
    {
        // Child classes can implement this
    }

    /**
     * Called when the module is deactivated.
     * Override this in child classes for module-specific deactivation code.
     *
     * @return void
     */
    protected function onDeactivate()
    {
        // Child classes can implement this
    }

    /**
     * Run the module's activation script if it exists.
     *
     * @return void
     */
    private function runActivateScript()
    {
        // Check base module activation script
        $activate_path = Utils::getModuleAbsolutePath($this->module_id, '__activate.php');
        if (file_exists($activate_path)) {
            require_once $activate_path;
        }

        // Check pro module activation script
        $pro_activate_path = Utils::getModuleAbsolutePath($this->module_id, 'pro/__activate.php');
        if (file_exists($pro_activate_path)) {
            require_once $pro_activate_path;
        }
    }

    /**
     * Run the module's deactivation script if it exists.
     *
     * @return void
     */
    private function runDeactivateScript()
    {
        // Check base module deactivation script
        $deactivate_path = Utils::getModuleAbsolutePath($this->module_id, '__deactivate.php');
        if (file_exists($deactivate_path)) {
            require_once $deactivate_path;
        }

        // Check pro module deactivation script
        $pro_deactivate_path = Utils::getModuleAbsolutePath($this->module_id, 'pro/__deactivate.php');
        if (file_exists($pro_deactivate_path)) {
            require_once $pro_deactivate_path;
        }
    }

    /**
     * Unregister module hooks.
     *
     * @return void
     */
    protected function unregisterHooks()
    {
        // Child classes should implement this if needed
    }

    /*
    |--------------------------------------------------------------------------
    | Pro Version Integration
    |--------------------------------------------------------------------------
    */

    /**
     * Load pro version of the module if available.
     *
     * @since 1.0.0
     * @return void
     */
    protected function maybeLoadProVersion()
    {
        if (WP_EXTENDED_PRO === false) {
            return;
        }

        $pro_file = Utils::getModuleAbsolutePath($this->module_id, 'pro/Bootstrap.php');
        if (file_exists($pro_file)) {
            require_once $pro_file;
            $class_name = Utils::getModuleClassPath($this->module_id, true);
            new $class_name($this);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Settings Management
    |--------------------------------------------------------------------------
    */

    /**
     * Set up module settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function setupSettings()
    {
        // Check if module has settings available for current version
        if (!Modules::hasSettings($this->module_id)) {
            return;
        }

        // Register a callback that will load settings fields when needed
        add_filter(sprintf('wpextended/framework/%s/register_settings', $this->module_id), array($this, 'lazyLoadSettingsFields'), 5);

        // Add validation and save hooks
        add_filter(sprintf('wpextended/%s/settings_validate', $this->module_id), array($this, 'handleValidate'), 10, 2);
        add_filter(sprintf('wpextended/%s/settings_before_save', $this->module_id), array($this, 'handleSave'));
    }

    /**
     * Lazy load settings fields when they're actually needed.
     * This ensures global variables are available when getSettingsFields() is called.
     *
     * @since 1.0.0
     * @param array $settings Current settings array (might be empty)
     * @return array Settings fields configuration
     */
    public function lazyLoadSettingsFields($settings)
    {
        // If settings fields are already loaded, return them
        if ($this->settings_fields_loaded && !empty($this->settings_fields)) {
            return $this->settings_fields;
        }

        // Load settings fields now that we're later in the WordPress lifecycle
        $settings_fields = $this->getSettingsFields();

        $settings_fields = apply_filters(
            sprintf('wpextended/%s/register_settings', $this->module_id),
            $settings_fields
        );

        // Cache the settings fields
        $this->settings_fields = $settings_fields;
        $this->settings_fields_loaded = true;

        // Return the settings fields
        // Pro version and other filters can modify them through the same filter at higher priority
        return $this->settings_fields;
    }

    /**
     * Initialize the framework when it's actually needed.
     * This is called by Framework::construct_settings() when processing the settings.
     *
     * @since 1.0.0
     * @return void
     */
    protected function initializeFramework()
    {
        if ($this->framework || empty($this->settings_fields)) {
            return;
        }

        $this->framework = Utils::initializeFramework($this->module_id, $this->settings_fields);
    }

    /**
     * Get settings fields for the module.
     *
     * @since 1.0.0
     * @return array Settings fields configuration.
     */
    protected function getSettingsFields()
    {
        return array();
    }

    /**
     * Handle validation of settings.
     *
     * @param array $validations Array of validation errors
     * @param array $input The input data to validate
     * @return array Updated validation errors
     */
    public function handleValidate($validations, $input)
    {
        return $this->validate($validations, $input);
    }

    /**
     * Handle saving of settings.
     *
     * @param array $input The input data to save
     * @return array Modified input data
     */
    public function handleSave($input)
    {
        return $this->onSave($input);
    }

    /**
     * Validate module settings.
     * Override this method to add custom validation rules.
     *
     * @param array $validations Array of validation errors
     * @param array $input The input data to validate
     * @return array Updated validation errors
     */
    protected function validate($validations, $input)
    {
        return $validations;
    }

    /**
     * Modify settings data before saving.
     * Override this method to modify data before it's saved.
     *
     * @param array $input The input data to save
     * @return array Modified input data
     */
    protected function onSave($input)
    {
        return $input;
    }

    /**
     * Get a specific setting value.
     *
     * @since 1.0.0
     * @param string $field_id Setting field ID (supports dot notation for nested values).
     * @param mixed $default Default value if setting doesn't exist.
     * @return mixed Setting value.
     */
    public function getSetting($field_id = '', $default = null)
    {
        return Utils::getArrayValue($this->settings, $field_id, $default);
    }

    /**
     * Get all module settings.
     *
     * @return array All module settings.
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Update a specific setting.
     *
     * @since 1.0.0
     * @param string $field_id Setting field ID (supports dot notation for nested values).
     * @param mixed $value Value to set.
     * @return bool Whether the setting was updated successfully.
     */
    public function updateSetting($field_id, $value)
    {
        Utils::setArrayValue($this->settings, $field_id, $value);
        return Utils::updateSettings($this->module_id, $this->settings);
    }

    /**
     * Check if a setting exists.
     *
     * @since 1.0.0
     * @param string $field_id Setting field ID (supports dot notation for nested values).
     * @return bool Whether the setting exists.
     */
    public function hasSetting($field_id)
    {
        return Utils::hasArrayKey($this->settings, $field_id);
    }

    /**
     * Remove a setting.
     *
     * @since 1.0.0
     * @param string $field_id Setting field ID (supports dot notation for nested values).
     * @return bool Whether the setting was removed successfully.
     */
    public function removeSetting($field_id)
    {
        Utils::removeArrayKey($this->settings, $field_id);
        return Utils::updateSettings($this->module_id, $this->settings);
    }

    /**
     * Clean up module settings.
     *
     * @since 1.0.0
     * @return void
     */
    protected function cleanupSettings()
    {
        // Child classes should implement this if needed
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Interface
    |--------------------------------------------------------------------------
    */

    /**
     * Set up admin hooks for the module.
     *
     * @return void
     */
    private function setupAdminHooks()
    {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'maybeAddSettingsPage'), 30);
            add_filter(sprintf('wpextended/%s/always_show_sidebar', $this->module_id), '__return_true');

            add_action(sprintf('wpextended/%s/settings_form_header_inner', $this->module_id), array($this, 'addHeaderLinks'), 10);
            add_action(sprintf('wpextended/%s/after_settings_form_header', $this->module_id), array($this, 'addModuleDescription'), 10);

            // Handle reset settings action
            add_action('admin_init', array($this, 'handleResetSettings'));

            // Display reset settings notice
            add_action('admin_notices', array($this, 'displayResetSettingsNotice'));
        }
    }

    /**
     * Add settings page if module is enabled and has settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybeAddSettingsPage()
    {
        if (!$this->isModuleEnabled()) {
            return;
        }

        if (!Modules::hasSettings($this->module_id)) {
            return;
        }

        $display_submodules = Utils::getSetting('global', 'display_submodules', false);
        $enable_all_submodules = Utils::getSetting('global', 'enable_all_submodules', false);
        $enabled_modules = Utils::getSetting('global', 'enabled_submodules', array());

        $visible = $display_submodules &&
            ($enable_all_submodules || is_array($enabled_modules) && in_array($this->module_id, $enabled_modules));

        $this->addSettingsPage($visible);
    }

    /**
     * Add module settings page.
     *
     * @since 1.0.0
     * @param bool $visible Whether the settings page should be visible in the menu
     * @return void
     */
    public function addSettingsPage($visible = true)
    {
        // Initialize framework if not already done
        $this->initializeFramework();

        if (!$this->framework) {
            // If we still don't have a framework, initialize it now
            // This will trigger the lazy loading of settings fields
            $settings_fields = apply_filters(
                sprintf('wpextended/framework/%s/register_settings', $this->module_id),
                array()
            );

            if (!empty($settings_fields)) {
                $this->settings_fields = $settings_fields;
                $this->framework = Utils::initializeFramework($this->module_id, $this->settings_fields);
            }
        }

        if (!$this->framework) {
            return;
        }

        $slug = $this->getPageSlug();
        $name = $this->getModuleName();

        if (!$slug || !$name) {
            return;
        }

        // Set the correct parent slug based on visibility
        $parent_slug = $visible ? 'wpextended' : '.';

        // Add the settings page
        $this->framework->add_settings_page(array(
            'parent_slug' => $parent_slug,
            'page_slug'   => $slug,
            'page_title'  => $name,
            'menu_title'  => $name,
            'capability'  => 'manage_options'
        ));
    }

    /**
     * Add links to settings pages
     *
     * @since 1.0.0
     * @return void | string
     */
    public function addHeaderLinks()
    {
        // Add documentation link if available
        if (!empty($this->meta['docs'])) {
            $links[] = array(
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" color="currentColor" fill="none"><path d="M22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12Z" stroke="currentColor" stroke-width="1.5"></path><path d="M12.2422 17V12C12.2422 11.5286 12.2422 11.2929 12.0957 11.1464C11.9493 11 11.7136 11 11.2422 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11.992 8H12.001" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
                'text' => esc_html__('Documentation', WP_EXTENDED_TEXT_DOMAIN),
                'attributes' => array(
                    'href' => Utils::generateTrackedLink($this->meta['docs'], 'modules'),
                    'target' => '_blank',
                    'aria-label' => sprintf(esc_attr__('Read documentation for %s, opens in new tab', WP_EXTENDED_TEXT_DOMAIN), $this->getModuleName()),
                    'title' => sprintf(esc_attr__('Read documentation for %s, opens in new tab', WP_EXTENDED_TEXT_DOMAIN), $this->getModuleName())
                )
            );
        }

        if ($this->getSettings() && !empty($this->getSettings())) {
            // Reset settings
            $links[] = array(
            'icon' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" color="currentColor" fill="none"><path d="M4.47461 6.10018L5.31543 18.1768C5.40886 19.3365 6.28178 21.5536 8.51889 21.8022C10.756 22.0507 15.2503 21.9951 16.0699 21.9951C16.8895 21.9951 19.0128 21.4136 19.0128 19.0059C19.0128 16.5756 16.9833 15.9419 15.7077 15.9635H12.0554M12.0554 15.9635C12.0607 15.7494 12.1515 15.5372 12.3278 15.3828L14.487 13.4924M12.0554 15.9635C12.0497 16.1919 12.1412 16.4224 12.33 16.5864L14.487 18.4609M19.4701 5.82422L19.0023 13.4792" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M3 5.49561H21M16.0555 5.49561L15.3729 4.08911C14.9194 3.15481 14.6926 2.68766 14.3015 2.39631C14.2148 2.33168 14.1229 2.2742 14.0268 2.22442C13.5937 2 13.0739 2 12.0343 2C10.9686 2 10.4358 2 9.99549 2.23383C9.89791 2.28565 9.80479 2.34547 9.7171 2.41265C9.32145 2.7158 9.10044 3.20004 8.65842 4.16854L8.05273 5.49561" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
            'text' => esc_html__('Reset', WP_EXTENDED_TEXT_DOMAIN),
            'attributes' => array(
                'href' => Utils::getModulePageLink($this->module_id, array('wpext_action' => 'reset_settings', 'nonce' => wp_create_nonce('wpext_reset_settings'), 'wpext_module' => $this->module_id)),
                'onclick' => 'return confirm("Are you sure you want to reset the menu?");',
                'aria-label' => esc_attr__('Reset Module Settings', WP_EXTENDED_TEXT_DOMAIN),
                'title' => esc_attr__('Reset Module Settings', WP_EXTENDED_TEXT_DOMAIN),
                'style' => 'color: #e0281f;'
            )
            );
        }

        $links = apply_filters(sprintf('wpextended/%s/header_links', $this->module_id), $links ?? array());

        if (empty($links)) {
            return;
        }

        $output = '';

        foreach ($links as $link) {
            $text = '';

            if (isset($link['icon']) && $link['icon']) {
                $text = $link['icon'];
            }
            if (isset($link['text'])) {
                $text .= ' ' . $link['text'];
            }

            $output .= sprintf(
                '<a %s>%s</a>',
                Framework::generate_html_atts($link['attributes']),
                $text
            );
        }

        printf(
            '<div class="wpext-module-header-links">%s</div>',
            $output
        );
    }

    /**
     * Add module description to settings pages
     *
     * @since 1.0.0
     * @return void
     */
    public function addModuleDescription()
    {
        $description = $this->getModuleDescription();

        if (empty($description)) {
            return;
        }

        printf(
            '<div class="wpext-module-description">
                <p>%s</p>
            </div>',
            esc_html($description)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Module Metadata
    |--------------------------------------------------------------------------
    */

    /**
     * Load module metadata from meta.json file.
     *
     * @since 1.0.0
     * @return void
     */
    protected function loadMeta()
    {
        $meta_file = Utils::getModuleAbsolutePath($this->module_id, 'meta.json');
        if (!file_exists($meta_file)) {
            return;
        }

        $meta_content = file_get_contents($meta_file);
        if ($meta_content === false) {
            return;
        }

        $meta = json_decode($meta_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->meta = $meta;
        }
    }

    /**
     * Validate required meta fields exist.
     *
     * @since 1.0.0
     * @return bool Whether the meta is valid.
     */
    protected function validateMeta()
    {
        $required_fields = array('id', 'name', 'description');
        foreach ($required_fields as $field) {
            if (!isset($this->meta[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get module page slug.
     *
     * @since 1.0.0
     * @return string|null Page slug.
     */
    protected function getPageSlug()
    {
        return isset($this->meta['id']) ? sprintf('wpextended-%s', $this->meta['id']) : null;
    }

    /**
     * Get module name.
     *
     * @since 1.0.0
     * @return string|null Module name.
     */
    protected function getModuleName()
    {
        return isset($this->meta['name']) ? $this->meta['name'] : null;
    }

    /**
     * Get module description.
     *
     * @since 1.0.0
     * @return string|null Module description.
     */
    protected function getModuleDescription()
    {
        return isset($this->meta['description']) ? $this->meta['description'] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Dependencies
    |--------------------------------------------------------------------------
    */

    /**
     * Get module dependencies.
     * Override this method in child classes to define dependencies.
     *
     * @since 1.0.0
     * @return array Array of dependency warning messages
     */
    protected function dependencies()
    {
        return array();
    }

    /**
     * Check module dependencies and render notices if needed.
     *
     * @since 1.0.0
     * @return void
     */
    protected function checkDependencies()
    {
        $dependencies = apply_filters(sprintf('wpextended/%s/dependencies', $this->module_id), $this->dependencies());

        $this->dependencies = $dependencies;

        if (empty($this->dependencies)) {
            return;
        }

        add_action(sprintf('wpextended/%s/before_settings_form', $this->module_id), array($this, 'addDependencyNotices'), 10, 1);
        add_filter(sprintf('wpextended/%s/hide_settings_form', $this->module_id), '__return_true');
    }

    /**
     * Generate dependency notices.
     *
     * @return void
     */
    public function addDependencyNotices()
    {
        $notices = '';

        foreach ($this->dependencies as $dependency) {
            $notices .= sprintf(
                '<div class="notice notice-%s wpext-dependency-notice"><p>%s</p></div>',
                $dependency['type'],
                $dependency['message']
            );
        }

        echo sprintf(
            '<div class="wpext-notices">
                %s
            </div>',
            $notices
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Utilities
    |--------------------------------------------------------------------------
    */

    /**
     * Check if module is enabled.
     *
     * @since 1.0.0
     * @return bool Whether the module is enabled.
     */
    public function isModuleEnabled()
    {
        return Modules::isModuleEnabled($this->module_id);
    }

    /**
     * Get module URL.
     *
     * @param string $path Optional path to append
     * @return string Module URL
     */
    public function getUrl($path = '')
    {
        return WP_EXTENDED_URL . Utils::getModuleFilePath($this->module_id, $path);
    }

    /**
     * Get module path.
     *
     * @param string $path Optional path to append
     * @param bool $include_base Whether to include the base path
     * @return string Module path
     */
    public function getPath($path = '', $include_base = false)
    {
        $path = Utils::getModuleFilePath($this->module_id, $path);

        return $include_base
            ? WP_EXTENDED_PATH . $path
            : $path;
    }

    /**
     * Handle reset settings action.
     *
     * @since 1.0.0
     * @return void
     */
    public function handleResetSettings()
    {
        // Check if this is a reset action
        if (!isset($_GET['wpext_action']) || $_GET['wpext_action'] !== 'reset_settings') {
            return;
        }

        $module = isset($_GET['wpext_module']) ? sanitize_text_field($_GET['wpext_module']) : '';
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        // Validate module and nonce
        if (!$module || $module !== $this->module_id || !$nonce || !wp_verify_nonce($nonce, 'wpext_reset_settings')) {
            return;
        }

        // Perform the reset
        Utils::deleteSettings($module);

        // Check if reset was successful
        $settings = Utils::getSettings($module);
        $status = empty($settings) ? 'reset_success' : 'reset_error';

        // Store notice in transient
        $transient_key = '_site-transient_wpextended__reset_notice--' . $this->module_id;
        set_transient($transient_key, array(
            'type' => $status,
            'message' => $status === 'reset_success'
                ? sprintf(__('Settings for %s have been successfully reset.', WP_EXTENDED_TEXT_DOMAIN), $this->getModuleName())
                : sprintf(__('Failed to reset settings for %s. Please try again.', WP_EXTENDED_TEXT_DOMAIN), $this->getModuleName())
        ), 60); // Expire in 60 seconds

        // Redirect to clean URL
        $clean_url = remove_query_arg(array('wpext_action', 'wpext_module', 'nonce'));
        wp_safe_redirect($clean_url);
        exit;
    }

    /**
     * Handle reset settings notice.
     *
     * @since 1.0.0
     * @param string $status The status of the reset action
     * @param string $module The module ID
     * @return void
     */
    public function handleResetSettingsNotice($status, $module)
    {
        if (!$status || !$module || $module !== $this->module_id) {
            return;
        }

        if ($status === 'reset_success') {
            $message = sprintf(
                __('Settings for %s have been successfully reset.', WP_EXTENDED_TEXT_DOMAIN),
                $this->getModuleName()
            );
            $notice_type = 'success';
        }

        if ($status === 'reset_error') {
            $message = sprintf(
                __('Failed to reset settings for %s. Please try again.', WP_EXTENDED_TEXT_DOMAIN),
                $this->getModuleName()
            );
            $notice_type = 'error';
        }

        if (empty($message) || empty($notice_type)) {
            return;
        }

        // Add the notice
        Notices::add(array(
            'type' => $notice_type,
            'message' => $message,
        ));
    }

    /**
     * Display reset settings notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function displayResetSettingsNotice()
    {
        // Check for transient notice
        $transient_key = '_site-transient_wpextended__reset_notice--' . $this->module_id;
        $notice_data = get_transient($transient_key);

        if (!$notice_data) {
            return;
        }

        // Delete the transient immediately
        delete_transient($transient_key);

        // Add the notice
        Notices::add(array(
            'type' => $notice_data['type'] === 'reset_success' ? 'success' : 'error',
            'message' => $notice_data['message'],
        ));
    }
}
