<?php

namespace Wpextended\Includes;

use Wpextended\Includes\Framework\Framework;
use Wpextended\Includes\Modules;

class Utils
{
    /**
     * The prefix used for all settings
     */
    private const SETTINGS_PREFIX = 'wpextended__';

    public static function initializeFramework($option_group, array $settings = [])
    {
        return Framework::getInstance($option_group, $settings);
    }

    /**
     * Get the full option name for a context
     *
     * @param string $context Context (module_id or 'core')
     * @return string Full option name
     */
    public static function getOptionName($context)
    {
        if ($context === 'core') {
            return self::SETTINGS_PREFIX . 'settings';
        }
        return self::SETTINGS_PREFIX . $context . '_settings';
    }

    /**
     * Get settings for an option group or context
     *
     * @param string $option_group_or_context Option group or context (module_id or 'core')
     * @return array
     */
    public static function getSettings($option_group_or_context)
    {
        $option_name = self::getOptionName($option_group_or_context);
        $settings = get_option($option_name, []);

        // Ensure we always return an array
        return is_array($settings) ? $settings : [];
    }

    /**
     * Get a specific setting value
     *
     * @param string $option_group_or_context Option group or context (module_id or 'core')
     * @param string $field_id_or_key Field ID or setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed
     */
    public static function getSetting($option_group_or_context, $field_id_or_key, $default = null)
    {
        $settings = self::getSettings($option_group_or_context);

        // Handle nested keys (new style)
        if (strpos($field_id_or_key, '.') !== false) {
            $keys = explode('.', $field_id_or_key);
            $value = $settings;

            foreach ($keys as $key) {
                if (!isset($value[$key])) {
                    return $default;
                }
                $value = $value[$key];
            }

            return $value;
        }

        // Handle direct key (both styles)
        return isset($settings[$field_id_or_key]) ? $settings[$field_id_or_key] : $default;
    }

    /**
     * Update settings for an option group or context
     *
     * @param string $option_group_or_context Option group or context (module_id or 'core')
     * @param array $settings The settings to update
     * @return bool
     */
    public static function updateSettings($option_group_or_context, $settings)
    {
        $option_name = self::getOptionName($option_group_or_context);
        return update_option($option_name, $settings);
    }

    /**
     * Delete settings for an option group or context
     *
     * @param string $option_group_or_context Option group or context (module_id or 'core')
     * @return bool
     */
    public static function deleteSettings($option_group_or_context)
    {
        $option_name = self::getOptionName($option_group_or_context);
        return delete_option($option_name);
    }

    /**
     * Update a specific setting
     *
     * @param string $option_group Option group
     * @param string $field_id Field ID
     * @param mixed $value Value to set
     * @return bool
     */
    public static function updateSetting($option_group, $field_id, $value)
    {
        $settings = self::getSettings($option_group);

        // Handle nested keys
        if (strpos($field_id, '.') !== false) {
            $keys = explode('.', $field_id);
            $current = &$settings;

            foreach ($keys as $i => $key) {
                if ($i === count($keys) - 1) {
                    $current[$key] = $value;
                } else {
                    if (!isset($current[$key]) || !is_array($current[$key])) {
                        $current[$key] = [];
                    }
                    $current = &$current[$key];
                }
            }
        } else {
            $settings[$field_id] = $value;
        }

        return self::updateSettings($option_group, $settings);
    }

    /**
     * Delete a specific setting
     *
     * @param string $option_group Option group
     * @param string $field_id Field ID
     * @return bool
     */
    public static function deleteSetting($option_group, $field_id)
    {
        $settings = self::getSettings($option_group);

        // Handle nested keys
        if (strpos($field_id, '.') !== false) {
            $keys = explode('.', $field_id);
            $current = &$settings;

            for ($i = 0; $i < count($keys) - 1; $i++) {
                if (!isset($current[$keys[$i]]) || !is_array($current[$keys[$i]])) {
                    return false;
                }
                $current = &$current[$keys[$i]];
            }

            unset($current[end($keys)]);
        } else {
            unset($settings[$field_id]);
        }

        return self::updateSettings($option_group, $settings);
    }

    /**
     * Convert a string to PascalCase.
     *
     * @param string $string The string to convert.
     * @return string|false PascalCase string or false if invalid.
     */
    private static function toPascalCase($string)
    {
        if (!is_string($string)) {
            return false;
        }
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }

    /**
     * Get module class name from module ID.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @return string|false PascalCase class name or false if invalid.
     */
    public static function getModuleClassName($module_id)
    {
        return self::toPascalCase($module_id);
    }

    /**
     * Get module file path from module ID.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @return string|false Lowercase-dashed path or false if invalid.
     */
    public static function getModulePath($module_id)
    {
        if (!is_string($module_id)) {
            return false;
        }
        return $module_id;
    }

    /**
     * Get module namespace parts.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @param bool $is_pro Whether this is a Pro module.
     * @return array|false Array of namespace parts or false if invalid.
     */
    private static function getModuleNamespaceParts($module_id, $is_pro = false)
    {
        $class_name = self::getModuleClassName($module_id);
        if (!$class_name) {
            return false;
        }

        $parts = ['Wpextended', 'Modules', $class_name];

        if ($is_pro) {
            $parts[] = 'Pro';
        }

        $parts[] = 'Bootstrap';

        return $parts;
    }

    /**
     * Get module class path.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @param bool $is_pro Whether this is a Pro module.
     * @return string|false Full class path or false if invalid module ID.
     */
    public static function getModuleClassPath($module_id, $is_pro = false)
    {
        $parts = self::getModuleNamespaceParts($module_id, $is_pro);
        if (!$parts) {
            return false;
        }
        return implode('\\', $parts);
    }

    /**
     * Get module file path with optional subpath.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @param string $subpath Optional subpath to append.
     * @return string|false Full file path or false if invalid.
     */
    public static function getModuleFilePath($module_id, $subpath = '')
    {
        $path = self::getModulePath($module_id);
        if (!$path) {
            return false;
        }
        return 'modules/' . $path . ($subpath ? '/' . ltrim($subpath, '/') : '');
    }

    /**
     * Get module absolute file path with optional subpath.
     *
     * @param string $module_id Module ID (in lowercase-dashed format).
     * @param string $subpath Optional subpath to append.
     * @return string|false Full absolute file path or false if invalid.
     */
    public static function getModuleAbsolutePath($module_id, $subpath = '')
    {
        $path = self::getModuleFilePath($module_id, $subpath);
        if (!$path) {
            return false;
        }
        return WP_EXTENDED_PATH . $path;
    }

    /**
     * Check if the current screen is a screen for WP Extended.
     *
     * @param string $module_id Module ID.
     * @return bool True if the current screen is a plugin screen, false otherwise.
     */
    public static function isPluginScreen($module_id)
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

        if (!is_admin()) {
            return false;
        }

        $screen = get_current_screen();

        if (!$screen) {
            return false;
        }

        // Check for main modules page
        if ($module_id === 'modules' && $screen->id === 'toplevel_page_wpextended') {
            return true;
        }

        // Check for settings page
        if ($module_id === 'settings' && $screen->id === 'wp-extended_page_wpextended-settings') {
            return true;
        }

        // Check for module-specific pages
        $sublevel = sprintf('wp-extended_page_wpextended-%s', $module_id);
        $top_level = sprintf('admin_page_wpextended-%s', $module_id);

        if ($screen->id === $sublevel || $screen->id === $top_level) {
            return true;
        }

        return false;
    }

    /**
     * Get module page link.
     *
     * @param string $module_id Module ID.
     * @param array $args Optional. Additional query arguments.
     * @return string|null Module settings page URL or null if not available.
     */
    public static function getModulePageLink($module_id, $args = [])
    {
        $base_url = null;

        if ($module_id === 'modules') {
            $base_url = admin_url('admin.php?page=wpextended');
        }

        if ($module_id === 'settings' || $module_id === 'global') {
            $base_url = admin_url('admin.php?page=wpextended-settings');
        }

        if ($base_url === null) {
            $module = Modules::findModule($module_id);
            if ($module) {
                $base_url = admin_url(sprintf('admin.php?page=wpextended-%s', $module['id']));
            }
        }

        if (!$base_url) {
            return null;
        }

        if (empty($args)) {
            return $base_url;
        }

        // Add each query arg individually since $args contains key/value pairs
        foreach ($args as $key => $value) {
            $base_url = add_query_arg($key, $value, $base_url);
        }

        return $base_url;
    }

    /**
     * Generate a tracked link with UTM and platform parameters, or an HTML <a> tag.
     *
     * @param string $url Base URL to add tracking to.
     * @param string $context Optional. Current page context (module, settings, modules).
     * @param array $args Optional. Additional arguments for tracking.
     * @param string $text Optional. If provided, returns an HTML <a> tag with this text.
     * @param array $attrs Optional. Array of HTML attributes for the <a> tag.
     * @return string Tracked URL or HTML <a> tag if $text is provided.
     */
    public static function generateTrackedLink($url, $context = '', $args = [], $text = '', $attrs = [])
    {
        global $wp_version;

        // Set campaign based on context
        $campaign = $context ? $context : '';

        // Default tracking parameters
        $default_args = [
            'utm_source'        => 'wpextended',
            'utm_medium'        => 'plugin',
            'utm_campaign'      => $campaign,
            'utm_content'       => 'plugin-link',
            'php_version'       => PHP_VERSION,
            'wordpress_version' => $wp_version,
            'plugin_type'       => defined('WPEXTENDED_PRO_VERSION') ? 'pro' : 'free',
            'plugin_version'    => WP_EXTENDED_VERSION,
            'days_active'       => self::getDaysActive()
        ];

        // Merge default args with provided args
        $tracking_args = array_filter(wp_parse_args($args, $default_args));

        // Add tracking parameters to URL
        $tracked_url = add_query_arg($tracking_args, $url);

        // If no link text, just return the URL
        if (empty($text)) {
            return $tracked_url;
        }

        // Build HTML attributes string, always escape
        $attr_str = '';
        if (!empty($attrs) && is_array($attrs)) {
            foreach ($attrs as $attr => $val) {
                if (is_bool($val)) {
                    if ($val) {
                        $attr_str .= ' ' . esc_attr($attr);
                    }
                    continue;
                }
                $attr_str .= sprintf(' %s="%s"', esc_attr($attr), esc_attr($val));
            }
        }

        // Always escape URL and text
        $html = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url($tracked_url),
            $attr_str,
            esc_html($text)
        );

        return $html;
    }

    /**
     * Get number of days the plugin has been active
     *
     * @return int Number of days active
     */
    private static function getDaysActive()
    {
        $activation_time = get_option('wpextended_activation_time');

        if (!$activation_time) {
            return 0;
        }

        $now = time();
        $days = floor(($now - $activation_time) / DAY_IN_SECONDS);

        return max(0, $days);
    }

    /**
     * Internal function to handle both script and style registration
     *
     * @param string  $handle    Asset handle
     * @param string  $path     Path to the asset relative to plugin root
     * @param array   $deps     Array of dependencies
     * @param string  $version   Version string
     * @param bool    $in_footer Whether to register script in footer (scripts only)
     * @param string  $type     Either 'script' or 'style'
     * @return void
     */
    private static function registerAsset($handle, $path, $deps, $version, $in_footer, $type)
    {
        $is_dev = defined('WP_EXTENDED_DEV') && WP_EXTENDED_DEV;

        // Only modify path if not already minified
        if (!$is_dev && !preg_match('/\.min\.(js|css)$/', $path)) {
            $path = str_replace(['.js', '.css'], ['.min.js', '.min.css'], $path);
        }

        if ($is_dev) {
            $file_path = WP_EXTENDED_PATH . $path;
            $version = file_exists($file_path) ? filemtime($file_path) : time();
        } else {
            $version = $version ?? WP_EXTENDED_VERSION;
        }

        if ($type === 'script') {
            wp_register_script(
                $handle,
                WP_EXTENDED_URL . $path,
                $deps,
                $version,
                $in_footer
            );
        } else {
            wp_register_style(
                $handle,
                WP_EXTENDED_URL . $path,
                $deps,
                $version
            );
        }
    }

    /**
     * Register a script with automatic dev/production file selection
     *
     * @param string  $handle    Script handle
     * @param string  $path     Path to the script relative to plugin root
     * @param array   $deps     Array of dependencies
     * @param bool    $in_footer Whether to register in footer
     * @param string  $version   Version string (defaults to plugin version)
     * @return void
     */
    public static function registerScript($handle, $path, $deps = [], $in_footer = true, $override_version = null)
    {
        self::registerAsset($handle, $path, $deps, $override_version, $in_footer, 'script');
    }

    /**
     * Register a stylesheet with automatic dev/production file selection
     *
     * @param string  $handle    Style handle
     * @param string  $path     Path to the stylesheet relative to plugin root
     * @param array   $deps     Array of dependencies
     * @param string  $version   Version string (defaults to plugin version)
     * @return void
     */
    public static function registerStyle($handle, $path, $deps = [], $override_version = null)
    {
        self::registerAsset($handle, $path, $deps, $override_version, false, 'style');
    }

    /**
     * Enqueue a script with automatic dev/production file selection
     *
     * @param string  $handle    Script handle
     * @param string  $path     Path to the script relative to plugin root
     * @param array   $deps     Array of dependencies
     * @param bool    $in_footer Whether to enqueue in footer
     * @param string  $version   Version string (defaults to plugin version)
     * @return void
     */
    public static function enqueueScript($handle, $path = null, $deps = [], $in_footer = true, $override_version = null)
    {
        if ($path !== null) {
            self::registerAsset($handle, $path, $deps, $override_version, $in_footer, 'script');
        }
        wp_enqueue_script($handle);
    }

    /**
     * Enqueue a stylesheet with automatic dev/production file selection
     *
     * @param string  $handle    Style handle
     * @param string  $path     Path to the stylesheet relative to plugin root
     * @param array   $deps     Array of dependencies
     * @param string  $version   Version string (defaults to plugin version)
     * @return void
     */
    public static function enqueueStyle($handle, $path = null, $deps = [], $override_version = null)
    {
        if ($path !== null) {
            self::registerAsset($handle, $path, $deps, $override_version, false, 'style');
        }
        wp_enqueue_style($handle);
    }

    public static function enqueueNotify()
    {
        self::enqueueStyle('wpext-notyf', 'includes/framework/assets/lib/notyf/notyf.min.css');
        self::enqueueScript('wpext-notyf', 'includes/framework/assets/lib/notyf/notyf.min.js');
        self::enqueueStyle('wpext-notify', 'includes/framework/assets/css/notify.css');
        self::enqueueScript('wpext-notify', 'includes/framework/assets/js/notify.js', ['wpext-notyf'], true);
    }

    /**
     * Generate an internal link to a specific tab and optionally a field within the tab
     *
     * @param string $module_id The module ID (e.g. 'smtp-email')
     * @param string $tab_id The tab ID to link to (e.g. 'email_logs')
     * @param string|null $field_id Optional. The field ID to scroll to
     * @param array $args Optional. Additional query arguments
     * @param string $link_text Optional. The text for the link. Defaults to tab name
     * @return string The formatted HTML link
     */
    public static function getInternalLink($module_id, $tab_id, $field_id = null, $args = [], $link_text = '')
    {
        // Get the base module page URL
        $url = self::getModulePageLink($module_id, $args);

        if (!$url) {
            return '';
        }

        // Build the internal link fragment
        $fragment = '#tab-' . $tab_id;
        if ($field_id) {
            $fragment .= '|field-' . $field_id;
        }

        // Combine URL and fragment
        $href = $url . $fragment;

        // If no link text provided, use the tab ID as a fallback
        if (empty($link_text)) {
            $link_text = ucwords(str_replace('_', ' ', $tab_id));
        }

        return sprintf(
            '<a href="%s" class="wpext-internal-link">%s</a>',
            esc_url($href),
            esc_html($link_text)
        );
    }

    /**
     * Checks if the current screen is the block editor.
     *
     * @return bool True if the current screen is the block editor, false otherwise.
     */
    public static function isBlockEditor()
    {
        if (!is_admin()) {
            return false;
        }

        $current_screen = get_current_screen();

        // Return false if the method 'is_block_editor' does not exist.
        if (!method_exists($current_screen, 'is_block_editor')) {
            return false;
        }

        return $current_screen->is_block_editor();
    }

    /**
     * Get a value from an array using dot notation.
     *
     * @param array $array The array to search in.
     * @param string|array $key The key to look for (can use dot notation for nested arrays).
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The value if found, otherwise the default value.
     */
    public static function getArrayValue($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }

        if (is_null($key)) {
            return $array;
        }

        // If the key is an array, we'll assume it's an array of keys and return an array of values
        if (is_array($key)) {
            $result = [];
            foreach ($key as $k) {
                $result[$k] = self::getArrayValue($array, $k, $default);
            }
            return $result;
        }

        // If the key contains a dot, we'll assume it's a nested array
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $array;

            foreach ($keys as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }

            return $value;
        }

        // If the key doesn't contain a dot, we'll just return the value directly
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    /**
     * Set a value in an array using dot notation.
     *
     * @param array $array The array to modify.
     * @param string $key The key to set (can use dot notation for nested arrays).
     * @param mixed $value The value to set.
     * @return array The modified array.
     */
    public static function setArrayValue(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }

        return $array;
    }

    /**
     * Check if a key exists in an array using dot notation.
     *
     * @param array $array The array to search in.
     * @param string $key The key to look for (can use dot notation for nested arrays).
     * @return bool True if the key exists, false otherwise.
     */
    public static function hasArrayKey($array, $key)
    {
        if (empty($array) || is_null($key)) {
            return false;
        }

        if (strpos($key, '.') === false) {
            return array_key_exists($key, $array);
        }

        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Remove a key from an array using dot notation.
     *
     * @param array $array The array to modify.
     * @param string $key The key to remove (can use dot notation for nested arrays).
     * @return array The modified array.
     */
    public static function removeArrayKey(&$array, $key)
    {
        if (is_null($key)) {
            return $array;
        }

        $keys = explode('.', $key);
        $current = &$array;

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($current[$key]) || !is_array($current[$key])) {
                return $array;
            }

            $current = &$current[$key];
        }

        $key = array_shift($keys);
        unset($current[$key]);

        return $array;
    }

    /**
     * Normalize a mixed value into a boolean using common truthy strings.
     *
     * Accepts true, 1, '1', 'true', 'yes', 'on' (case-insensitive) as true.
     * Everything else is false.
     *
     * @param mixed $value The value to evaluate
     * @return bool True if the value is a recognized truthy representation
     */
    public static function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
