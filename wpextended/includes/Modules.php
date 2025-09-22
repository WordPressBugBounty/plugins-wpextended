<?php

namespace Wpextended\Includes;

use Wpextended\Includes\Utils;

class Modules
{
    private static $instance = null;
    private $modules = [];
    private $initialized = false;

    private function __construct()
    {
    }

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the modules system.
     *
     * @return void
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        $this->loadEnabledModules();
        $this->initialized = true;

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register REST API routes for module management.
     *
     * @return void
     */
    public function registerRestRoutes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/modules/(?P<module_id>[a-zA-Z0-9-]+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggleModule'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'module_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Verify that a module exists.
     *
     * @param string $moduleId The module ID to verify.
     * @return \WP_Error|array The module data or error.
     */
    private function verifyModuleExists($moduleId)
    {
        $module = self::findModule($moduleId);
        if (!$module) {
            return new \WP_Error(
                'module_not_found',
                esc_html__('Module not found', WP_EXTENDED_TEXT_DOMAIN),
                ['status' => 404]
            );
        }
        return $module;
    }

    /**
     * Get current enabled modules.
     *
     * @return array
     */
    public static function getEnabledModules()
    {
        $enabledModules = Utils::getSetting('modules', 'modules', []);
        return is_array($enabledModules) ? $enabledModules : [];
    }

    /**
     * Deactivate a module.
     *
     * @param string $moduleId The module ID.
     * @return \WP_Error|array The result or error.
     */
    private function deactivateModule($moduleId)
    {
        // Verify module exists first
        $module = self::findModule($moduleId);
        if (!$module) {
            return new \WP_Error(
                'module_not_found',
                esc_html__('Module not found', WP_EXTENDED_TEXT_DOMAIN),
                ['status' => 404]
            );
        }

        try {
            $moduleInstance = self::getModule($moduleId);
            if (!$moduleInstance) {
                return [
                    'success' => true,
                    'message' => sprintf(
                        esc_html__('%s deactivated', WP_EXTENDED_TEXT_DOMAIN),
                        $module['name']
                    )
                ];
            }

            $result = $moduleInstance->deactivate();
            if ($result !== true) {
                return new \WP_Error(
                    'deactivation_failed',
                    sprintf(
                        esc_html__('Failed to deactivate %s: %s', WP_EXTENDED_TEXT_DOMAIN),
                        $module['name'],
                        $result
                    ),
                    ['status' => 500]
                );
            }

            return [
                'success' => true,
                'message' => sprintf(
                    esc_html__('%s deactivated', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name']
                )
            ];
        } catch (\Exception $e) {
            return new \WP_Error(
                'deactivation_failed',
                sprintf(
                    esc_html__('Failed to deactivate %s: %s', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name'],
                    $e->getMessage()
                ),
                ['status' => 500]
            );
        }
    }

    /**
     * Activate a module.
     *
     * @param string $moduleId The module ID.
     * @return \WP_Error|array The result or error.
     */
    private function activateModule($moduleId)
    {
        // Verify module exists first
        $module = self::findModule($moduleId);
        if (!$module) {
            return new \WP_Error(
                'module_not_found',
                esc_html__('Module not found', WP_EXTENDED_TEXT_DOMAIN),
                ['status' => 404]
            );
        }

        try {
            if (!$this->loadModule($moduleId)) {
                return new \WP_Error(
                    'activation_failed',
                    sprintf(
                        esc_html__('Failed to activate %s: Module could not be loaded', WP_EXTENDED_TEXT_DOMAIN),
                        $module['name']
                    ),
                    ['status' => 500]
                );
            }

            $moduleInstance = self::getModule($moduleId);
            if (!$moduleInstance) {
                return new \WP_Error(
                    'activation_failed',
                    sprintf(
                        esc_html__('Failed to activate %s: Module instance not found', WP_EXTENDED_TEXT_DOMAIN),
                        $module['name']
                    ),
                    ['status' => 500]
                );
            }

            $result = $moduleInstance->activate();
            if ($result !== true) {
                return new \WP_Error(
                    'activation_failed',
                    sprintf(
                        esc_html__('Failed to activate %s: %s', WP_EXTENDED_TEXT_DOMAIN),
                        $module['name'],
                        $result
                    ),
                    ['status' => 500]
                );
            }

            return [
                'success' => true,
                'message' => sprintf(
                    esc_html__('%s activated', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name']
                )
            ];
        } catch (\Exception $e) {
            return new \WP_Error(
                'activation_failed',
                sprintf(
                    esc_html__('Failed to activate %s: %s', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name'],
                    $e->getMessage()
                ),
                ['status' => 500]
            );
        }
    }

    /**
     * Update module settings.
     *
     * @param array $enabledModules The enabled modules.
     * @param string $moduleId The module ID.
     * @return \WP_Error|true The result or error.
     */
    private function updateModuleSettings($enabledModules, $moduleId)
    {
        $module = self::findModule($moduleId);
        if (!$module) {
            return new \WP_Error(
                'module_not_found',
                esc_html__('Module not found', WP_EXTENDED_TEXT_DOMAIN),
                ['status' => 404]
            );
        }

        $currentSettings = Utils::getSettings('modules');
        if (!is_array($currentSettings)) {
            $currentSettings = [];
        }

        // Update the modules array in the settings
        $currentSettings['modules'] = array_values($enabledModules);

        $updated = Utils::updateSettings('modules', $currentSettings);
        if (!$updated) {
            return new \WP_Error(
                'update_failed',
                sprintf(
                    esc_html__('Failed to update module %s state', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name']
                ),
                ['status' => 500]
            );
        }

        return true;
    }

    /**
     * Toggle module state via REST API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response or error.
     */
    public function toggleModule($request)
    {
        $moduleId = $request->get_param('module_id');

        // Verify module exists and get its metadata
        $module = self::findModule($moduleId);
        if (!$module) {
            return new \WP_Error(
                'module_not_found',
                esc_html__('Module not found', WP_EXTENDED_TEXT_DOMAIN),
                ['status' => 404]
            );
        }

        // Check if module can be toggled based on pro/free status
        $isProModule = isset($module['pro']) && $module['pro'] === true;
        $isProInstallation = defined('WP_EXTENDED_PRO') && WP_EXTENDED_PRO === true;

        if ($isProModule && !$isProInstallation) {
            return new \WP_Error(
                'pro_module_restricted',
                sprintf(
                    esc_html__('Module "%s" is only available in the Pro version.', WP_EXTENDED_TEXT_DOMAIN),
                    $module['name']
                ),
                ['status' => 403]
            );
        }

        // Get current enabled modules
        $enabledModules = $this->getEnabledModules();
        $isEnabled = in_array($moduleId, $enabledModules);

        // Toggle module state
        if ($isEnabled) {
            $result = $this->deactivateModule($moduleId);
            if (is_wp_error($result)) {
                return $result;
            }
            $enabledModules = array_diff($enabledModules, [$moduleId]);
        } else {
            $result = $this->activateModule($moduleId);
            if (is_wp_error($result)) {
                return $result;
            }
            $enabledModules[] = $moduleId;
        }

        // Update settings
        $updateResult = $this->updateModuleSettings($enabledModules, $moduleId);
        if (is_wp_error($updateResult)) {
            return $updateResult;
        }

        return [
            'success' => true,
            'enabled' => !$isEnabled,
            'message' => $result['message'],
        ];
    }

    /**
     * Get all available modules with their metadata.
     *
     * @param string $order The order to sort modules by.
     * @return array The available modules.
     */
    public static function getAvailableModules($order = 'module')
    {
        $modules = [];
        $modulesDir = WP_EXTENDED_PATH . 'modules';

        if (!is_dir($modulesDir)) {
            return $modules;
        }

        $moduleFolders = array_filter(glob($modulesDir . '/*'), 'is_dir');

        foreach ($moduleFolders as $moduleFolder) {
            $metaFile = $moduleFolder . '/meta.json';
            if (!file_exists($metaFile)) {
                continue;
            }

            $meta = json_decode(file_get_contents($metaFile), true);
            if (!$meta || !isset($meta['id']) || !isset($meta['name']) || !isset($meta['description'])) {
                continue;
            }

            if (isset($meta['keywords'])) {
                $keywords = [];
                foreach ($meta['keywords'] as $keyword) {
                    $keywords[] = __($keyword, WP_EXTENDED_TEXT_DOMAIN);
                }
                $meta['keywords'] = $keywords;
            }

            $modules[] = [
                'id' => $meta['id'],
                'name' => __($meta['name'], WP_EXTENDED_TEXT_DOMAIN),
                'description' => __($meta['description'], WP_EXTENDED_TEXT_DOMAIN),
                'keywords' => isset($meta['keywords']) ? $meta['keywords'] : [],
                'group' => isset($meta['group']) ? ucfirst($meta['group']) : 'Other',
                'pro' => isset($meta['pro']) ? $meta['pro'] : false,
                'settings' => $meta['settings'] ?? false,
                'documentation_link' => isset($meta['docs']) ? $meta['docs'] : '',
                'order' => isset($meta['order']) ? $meta['order'] : 999,
            ];
        }

        // Sort modules by order
        switch ($order) {
            case 'asc':
                usort($modules, function ($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
                break;
            case 'desc':
                usort($modules, function ($a, $b) {
                    return strcmp($b['name'], $a['name']);
                });
                break;
            case 'random':
                shuffle($modules);
                break;
            case 'module':
            default:
                usort($modules, function ($a, $b) {
                    return $a['order'] - $b['order'];
                });
                break;
        }

        return $modules;
    }

    // Removed disable_all_modules support and related admin notice

    /**
     * Load enabled modules.
     *
     * @return void
     */
    public function loadEnabledModules()
    {
        $enabledModules = Utils::getSetting('modules', 'modules');

        if (empty($enabledModules) || !is_array($enabledModules)) {
            return;
        }

        foreach ($enabledModules as $moduleId) {
            $this->loadModule($moduleId);
        }
    }

    /**
     * Load a module.
     *
     * @param string $moduleId The module ID to load.
     * @return bool Whether the module was loaded successfully.
     */
    private function loadModule($moduleId)
    {
        // Convert module_id to PascalCase for class name
        $className = Utils::getModuleClassPath($moduleId);

        // Keep module_id in lowercase-dashed format for folder path
        $moduleFile = WP_EXTENDED_PATH . 'modules/' . $moduleId . '/Bootstrap.php';

        if (!file_exists($moduleFile)) {
            return false;
        }

        require_once $moduleFile;

        if (!class_exists($className)) {
            return false;
        }

        $this->modules[$moduleId] = new $className($moduleId);
        return true;
    }

    /**
     * Check if a specific module has been loaded.
     *
     * @param string $moduleId The module ID to check.
     * @return bool Whether the module is loaded.
     */
    public static function isModuleLoaded($moduleId)
    {
        return isset(self::getInstance()->modules[$moduleId]);
    }

    /**
     * Get an instance of a loaded module.
     *
     * @param string $moduleId The module ID to get.
     * @return object|null The module instance or null.
     */
    public static function getModule($moduleId)
    {
        return self::isModuleLoaded($moduleId) ? self::getInstance()->modules[$moduleId] : null;
    }

    /**
     * Get all loaded modules.
     *
     * @return array The loaded modules.
     */
    public static function getLoadedModules()
    {
        return self::getInstance()->modules;
    }

    /**
     * Find a module by its ID.
     *
     * @param string $moduleId The module ID to find.
     * @return array|null The module data or null.
     */
    public static function findModule($moduleId)
    {
        $modules = self::getAvailableModules();
        foreach ($modules as $module) {
            if ($module['id'] === $moduleId) {
                return $module;
            }
        }
        return null;
    }

    /**
     * Check if a module has settings available for the current version.
     *
     * @param string $moduleId The module ID to check.
     * @return bool Whether the module has settings.
     */
    public static function hasSettings($moduleId)
    {
        $module = self::findModule($moduleId);
        if (!$module) {
            return false;
        }

        if (!is_array($module['settings'])) {
            return $module['settings'] ?? false;
        }

        return WP_EXTENDED_PRO ? ($module['settings']['pro'] ?? false) : ($module['settings']['free'] ?? false);
    }

    /**
     * Check if module is enabled.
     *
     * @since 1.0.0
     * @return bool Whether the module is enabled.
     */
    public static function isModuleEnabled($moduleId)
    {
        $enabledModules = self::getEnabledModules();
        return in_array($moduleId, $enabledModules);
    }
}
