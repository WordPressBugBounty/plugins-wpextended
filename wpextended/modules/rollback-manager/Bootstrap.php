<?php

namespace Wpextended\Modules\RollbackManager;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;
use Wpextended\Modules\RollbackManager\WordPressApi;
use Wpextended\Modules\RollbackManager\PluginUpdater;
use Wpextended\Modules\RollbackManager\ThemeUpdater;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('rollback-manager');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (! function_exists('wp_get_themes')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_filter(sprintf('wpextended/%s/show_save_changes_button', $this->module_id), '__return_false');

        add_action('rest_api_init', array($this, 'registerRestRoutes'));

        add_filter('plugin_action_links', array($this, 'pluginActionLink'), 1, 4);
        add_filter('theme_action_links', array($this, 'themeActionLink'), 20, 4);
    }

    /**
     * Enqueue module assets
     */
    public function enqueueAssets()
    {
        if (!Utils::isPluginScreen($this->module_id)) {
            return;
        }

        Utils::enqueueNotify();

        Utils::enqueueScript(
            'wpext-rollback-module',
            $this->getPath('assets/js/script.js'),
            array('jquery'),
        );

        wp_localize_script('wpext-rollback-module', 'rollbackData', array(
            'restUrl' => rest_url(WP_EXTENDED_API_NAMESPACE),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n' => array(
                'rollback' => __('Rollback', WP_EXTENDED_TEXT_DOMAIN),
                'rollingBack' => __('Rolling back...', WP_EXTENDED_TEXT_DOMAIN),
                'loading' => __('Loading...', WP_EXTENDED_TEXT_DOMAIN),
                'errorFetchingItems' => __('Error fetching items', WP_EXTENDED_TEXT_DOMAIN),
                'errorFetchingVersions' => __('Error fetching versions', WP_EXTENDED_TEXT_DOMAIN),
                'selectTheme' => __('Select a theme...', WP_EXTENDED_TEXT_DOMAIN),
                'selectPlugin' => __('Select a plugin...', WP_EXTENDED_TEXT_DOMAIN),
                'selectVersion' => __('Select a version...', WP_EXTENDED_TEXT_DOMAIN),
            ),
        ));
    }

    /**
     * Get module settings fields
     */
    protected function getSettingsFields()
    {
        $defaults = $this->getDefaults();

        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Rollback Manager', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id'    => 'rollback',
                'section_title' => '',
                'section_description' => '',
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id' => 'type',
                        'type' => 'select',
                        'title' => __('Type', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the type of item you want to revert to a previous version.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => $defaults['type'],
                        'choices' => array(
                            'plugin' => __('Plugin', WP_EXTENDED_TEXT_DOMAIN),
                            'theme' => __('Theme', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                    ),
                    array(
                        'id' => 'plugin_list',
                        'type' => 'select',
                        'title' => __('Plugins', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the plugin you want to switch to a different version.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => $defaults['plugin']['slug'],
                        'choices' => array(
                            'empty' => __('Select a plugin...', WP_EXTENDED_TEXT_DOMAIN),
                            $defaults['plugin']['slug'] => $defaults['plugin']['name'],
                        ),
                        'slide' => false,
                        'show_if'  => array(
                            array(
                                'field' => 'type',
                                'value' => 'plugin',
                                'operator' => '==='
                            ),
                        ),
                    ),
                    array(
                        'id' => 'theme_list',
                        'type' => 'select',
                        'title' => __('Themes', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the theme you want to switch to a different version.', WP_EXTENDED_TEXT_DOMAIN),
                        'default' => $defaults['theme']['slug'],
                        'choices' => array(
                            'empty' => __('Select a theme...', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'slide' => false,
                        'show_if'  => array(
                            array(
                                'field' => 'type',
                                'value' => 'theme',
                                'operator' => '==='
                            ),
                        ),
                    ),
                    array(
                        'id' => 'version_list',
                        'type' => 'select',
                        'title' => __('Version', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the version you want to switch to.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices' => array(
                            'empty' => __('Select a version...', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'slide' => false,
                        'show_if'  => array(
                            array(
                                'field' => 'plugin_list',
                                'value' => 'empty',
                                'operator' => '!=='
                            ),
                            array(
                                'field' => 'theme_list',
                                'value' => 'empty',
                                'operator' => '!=='
                            ),
                        ),
                    ),
                    array(
                        'id' => 'rollback_button',
                        'type' => 'button',
                        'title' => __('Rollback', WP_EXTENDED_TEXT_DOMAIN),
                        'loader' => true,
                        'show_if'  => array(
                            array(
                                'field' => 'version_list',
                                'value' => 'empty',
                                'operator' => '!=='
                            ),
                        ),
                    ),
                ),
            ),
        );

        return $settings;
    }

    /**
     * Get settings defaults
     *
     * @return array
     */
    public function getDefaults()
    {
        $defaults = array(
            'type' => '',
            'plugin' => array(
                'slug' => '',
                'name' => '',
            ),
            'theme' => array(
                'slug' => '',
                'name' => '',
            ),
        );

        $allowed_types = array('plugin', 'theme');

        // wpextended:ignore Security.NonceVerification
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

        if (!empty($type) && !in_array($type, $allowed_types)) {
            return $defaults;
        }

        // plugin slug and plugin name
        $name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';

        if ($type === 'plugin') {
            $defaults['type'] = 'plugin';
            $defaults['plugin']['name'] = $name;
            $defaults['plugin']['slug'] = sanitize_title($name);
        }

        if ($type === 'theme') {
            $defaults['type'] = 'theme';
            $defaults['theme']['name'] = $name;
            $defaults['theme']['slug'] = sanitize_title($name);
        }

        return $defaults;
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function registerRestRoutes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/rollback-manager/get-items', array(
            'methods' => 'GET',
            'callback' => [$this, 'getItems'],
            'permission_callback' => [$this, 'checkPermissions'],
        ));

        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/rollback-manager/get-versions', array(
            'methods' => 'GET',
            'callback' => [$this, 'getVersions'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => array(
                'type' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return in_array($param, ['plugin', 'theme']);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/rollback-manager/rollback', array(
            'methods' => 'POST',
            'callback' => [$this, 'performRollback'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => array(
                'type' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return in_array($param, ['plugin', 'theme']);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'version' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'file' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Check permissions
     *
     * @return bool
     */
    public function checkPermissions()
    {
        return current_user_can('update_plugins') && current_user_can('update_themes');
    }

    /**
     * Get available themes and plugins
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getItems($request)
    {
        $plugins = $this->getPluginList();
        $themes = $this->getThemeList();

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'plugins' => $plugins,
                'themes' => $themes,
            ),
        ));
    }

    /**
     * Get list of installed plugins from wordpress.org
     *
     * @return array
     */
    private function getPluginList()
    {
        $all_plugins = get_plugins();
        $plugins = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $folder = dirname($plugin_file);
            $plugin_slug = ($folder === '.') ? basename($plugin_file, '.php') : $folder;

            // Get plugin info from WordPress.org
            $plugin_info = WordPressApi::getPluginInfo($plugin_slug);

            if (!$plugin_info) {
                continue;
            }

            $download_link = $plugin_info->download_link;
            $is_wp_plugin = strpos($download_link, 'downloads.wordpress.org') !== false;

            if (!$is_wp_plugin) {
                continue;
            }

            $plugins[$plugin_slug] = array(
                'name' => $plugin_data['Name'],
                'file' => $plugin_file
            );
        }

        return $plugins;
    }

    /**
     * Get list of installed themes from wordpress.org
     *
     * @return array
     */
    private function getThemeList()
    {
        $all_themes = wp_get_themes();
        $themes = array();

        foreach ($all_themes as $theme_slug => $theme_obj) {
            // Get theme info from WordPress.org
            $theme_info = WordPressApi::getThemeInfo($theme_slug);

            if (!$theme_info) {
                continue;
            }

            $download_link = $theme_info->download_link;
            $is_wp_theme = strpos($download_link, 'downloads.wordpress.org') !== false;

            if (!$is_wp_theme) {
                continue;
            }

            $themes[$theme_slug] = array(
                'name' => $theme_obj->get('Name'),
                'file' => $theme_obj->get_stylesheet()
            );
        }

        return $themes;
    }

    /**
     * Get versions
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getVersions($request)
    {
        $type = $request->get_param('type');
        $slug = $request->get_param('slug');

        if (empty($type) || empty($slug)) {
            return $this->createErrorResponse(__('Invalid request parameters', WP_EXTENDED_TEXT_DOMAIN));
        }

        $versions = $type === 'plugin'
            ? $this->getPluginVersions($slug)
            : $this->getThemeVersions($slug);

        if (empty($versions)) {
            return $this->createErrorResponse(__('No versions found', WP_EXTENDED_TEXT_DOMAIN));
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $versions,
        ));
    }

    /**
     * Get plugin versions
     *
     * @param string $slug
     * @return array
     */
    private function getPluginVersions($slug)
    {
        $current_version = $this->getCurrentPluginVersion($slug);
        $plugin_versions = WordPressApi::getPluginVersions($slug);

        if (empty($plugin_versions)) {
            return array();
        }

        return $this->formatVersions($plugin_versions, $current_version);
    }

    /**
     * Get theme versions
     *
     * @param string $slug
     * @return array
     */
    private function getThemeVersions($slug)
    {
        $theme = wp_get_theme($slug);
        $current_version = $theme->get('Version');
        $theme_versions = WordPressApi::getThemeVersions($slug);

        if (empty($theme_versions)) {
            return array();
        }

        return $this->formatVersions($theme_versions, $current_version);
    }

    /**
     * Get current plugin version
     *
     * @param string $slug
     * @return string
     */
    private function getCurrentPluginVersion($slug)
    {
        $all_plugins = get_plugins();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $folder = dirname($plugin_file);
            $plugin_slug = ($folder === '.') ? basename($plugin_file, '.php') : $folder;

            if ($plugin_slug === $slug) {
                return $plugin_data['Version'];
            }
        }

        return '';
    }

    /**
     * Format versions for dropdown
     *
     * @param array $versions
     * @param string $current_version
     * @return array
     */
    private function formatVersions($versions, $current_version)
    {
        $formatted_versions = array();

        foreach ($versions as $version => $download_link) {
            if (empty($version) || $version === 'trunk') {
                continue;
            }

            $formatted_versions[] = array(
                'value' => $version,
                'label' => $version,
                'current' => version_compare($version, $current_version, '==')
            );
        }

        // Sort versions in descending order (newest first)
        usort($formatted_versions, function ($a, $b) {
            return version_compare($b['value'], $a['value']);
        });

        return $formatted_versions;
    }

    /**
     * Create error response
     *
     * @param string $message
     * @return \WP_REST_Response
     */
    private function createErrorResponse($message)
    {
        return rest_ensure_response(array(
            'success' => false,
            'message' => $message,
        ));
    }

    /**
     * Perform rollback operation
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function performRollback($request)
    {
        $type = $request->get_param('type');
        $slug = $request->get_param('slug');
        $version = $request->get_param('version');
        $file = $request->get_param('file');

        if (empty($type) || empty($slug) || empty($version)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Invalid request parameters', WP_EXTENDED_TEXT_DOMAIN),
            ));
        }

        // Verify version is available
        $is_available = false;
        if ($type === 'plugin') {
            $is_available = WordPressApi::isPluginVersionAvailable($slug, $version);
        } else {
            $is_available = WordPressApi::isThemeVersionAvailable($slug, $version);
        }

        if (!$is_available) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => sprintf(__('Version %s is not available', WP_EXTENDED_TEXT_DOMAIN), $version),
            ));
        }

        // Make sure we have the required WordPress files
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        require_once(ABSPATH . 'wp-admin/includes/template.php');

        // Perform rollback
        $skin = new \WP_Ajax_Upgrader_Skin();

        if (empty($file)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('File not found.', WP_EXTENDED_TEXT_DOMAIN),
            ));
        }

        $result = null;

        if ($type === 'plugin') {
            // Set up the upgrader
            $upgrader = new PluginUpdater($skin);

            // Use the rollback method which handles deactivation and reactivation
            $result = $upgrader->rollback($file, $slug, $version);
        } else {
            // Set up the upgrader
            $upgrader = new ThemeUpdater($skin);

            // Use the rollback method
            $result = $upgrader->rollback($slug, $version);
        }

        // Handle error cases
        if (is_wp_error($result)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ));
        }

        if (is_wp_error($skin->result)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $skin->result->get_error_message(),
            ));
        }

        // Check for skin errors
        if ($skin->get_errors()->has_errors()) {
            $error_messages = $skin->get_error_messages();
            if (!empty($error_messages) && is_array($error_messages)) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => implode(', ', $error_messages),
                ));
            } elseif (!empty($error_messages)) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => $error_messages,
                ));
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => sprintf(__('Successfully rolled back %s to version %s', WP_EXTENDED_TEXT_DOMAIN), $type, $version),
        ));
    }

    /**
     * Add rollback link to plugin actions
     *
     * @param array $actions
     * @param string $plugin_file
     * @param array $plugin_data
     * @param string $context
     * @return array
     */
    public function pluginActionLink($actions, $plugin_file, $plugin_data, $context)
    {
        if (isset($plugin_data['url']) && strpos($plugin_data['url'], 'wordpress.org') === false) {
            return $actions;
        }

        $slug = isset($plugin_data['slug']) ? $plugin_data['slug'] : '';

        if (empty($slug)) {
            return $actions;
        }

        $rollback_url = Utils::getModulePageLink($this->module_id, array(
            'type' => 'plugin',
            'name' => $slug,
        ));

        $actions['rollback'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($rollback_url),
            __('Rollback', WP_EXTENDED_TEXT_DOMAIN)
        );

        return $actions;
    }

    /**
     * Add rollback link to theme actions
     *
     * @param array $actions
     * @param string $theme
     * @param WP_Theme $theme_obj
     * @param string $context
     * @return array
     */
    public function themeActionLink($actions, $theme, $theme_obj, $context)
    {
        // Only add to themes from wordpress.org
        if (!$theme_obj->exists() || !$theme_obj->get('Name')) {
            return $actions;
        }

        $theme_uri = $theme_obj->get('ThemeURI');
        if (empty($theme_uri) || strpos($theme_uri, 'wordpress.org') === false) {
            return $actions;
        }

        $slug = $theme;
        $name = $theme_obj->get('Name');

        $rollback_url = add_query_arg(array(
            'page' => 'wpextended-settings',
            'module' => $this->module_id,
            'type' => 'theme',
            'name' => $name,
        ), admin_url('admin.php'));

        $actions['rollback'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($rollback_url),
            __('Rollback', WP_EXTENDED_TEXT_DOMAIN)
        );

        return $actions;
    }
}
