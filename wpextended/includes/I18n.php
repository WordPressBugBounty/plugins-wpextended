<?php

namespace Wpextended\Includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Wpextended
 * @subpackage Wpextended/includes
 * @author     WP Extended <support@wpextended.io>
 */

class I18n
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'load_plugin_textdomain']);
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain()
    {

        load_plugin_textdomain(
            WP_EXTENDED_TEXT_DOMAIN,
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
