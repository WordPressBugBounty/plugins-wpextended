<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link    https://wpextended.io
 * @since   1.0.0
 * @package Wpextended
 *
 * @wordpress-plugin
 * Plugin Name:       WP Extended
 * Plugin URI:        https://wpextended.io
 * Description:       WP Extended Pro is a modular plugin designed to enhance the core WordPress experience by adding many of the tools you need without having to install multiple plugins.
 * Version:           3.1.0
 * Author:            WP Extended
 * Author URI:        https://wpextended.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpextended
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    die;
}

/**
 * Plugin conflict detection and resolution.
 * Detects which version is loading and disables the other if needed.
 */

$current_plugin_dir = basename(dirname(__FILE__));

$other_plugin_dir = ($current_plugin_dir === 'wpextended-pro') ? 'wpextended' : 'wpextended-pro';
$other_plugin = $other_plugin_dir . '/' . $other_plugin_dir . '.php';

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

if (in_array($other_plugin, $active_plugins)) {
    deactivate_plugins($other_plugin, true);
}

// If constants are already defined by the other version, exit to prevent conflicts
if (defined('WP_EXTENDED_VERSION')) {
    return;
}

// Load custom autoloader
require_once __DIR__ . '/Autoloader.php';

use Wpextended\Includes\Wpextended;
use Wpextended\Includes\Activator;
use Wpextended\Includes\Deactivator;

/**
 * Define constants for the plugin.
 */
define('WP_EXTENDED_VERSION', '3.1.0');
define('WP_EXTENDED_TEXT_DOMAIN', 'wp-extended');
define('WP_EXTENDED_PATH', plugin_dir_path(__FILE__));
define('WP_EXTENDED_URL', plugin_dir_url(__FILE__));
define('WP_EXTENDED_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_EXTENDED_PRO', false);
define('WP_EXTENDED_API_NAMESPACE', 'wpextended/v1');


$site_url = is_multisite() ? network_site_url() : site_url();

if (!defined('WP_EXTENDED_SITE_URL')) {
    define('WP_EXTENDED_SITE_URL', $site_url);
}

register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
new Wpextended();
