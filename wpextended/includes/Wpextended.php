<?php

namespace Wpextended\Includes;

use Wpextended\Includes\Modules;
use Wpextended\Admin\ModulesPage;
use Wpextended\Admin\SettingsPage;
use Wpextended\Includes\Framework\Framework;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since 1.0.0
 * @package Wpextended
 * @author WP Extended <support@wpextended.io>
 */
class Wpextended
{
    /**
     * The unique identifier of this plugin.
     *
     * @since 1.0.0
     * @access protected
     * @var string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since 1.0.0
     * @access protected
     * @var string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        if (defined('WP_EXTENDED_VERSION')) {
            $this->version = WP_EXTENDED_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wpextended';

        $this->load_dependencies();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Wpextended\I18n. Defines internationalization functionality.
     * - Wpextended\Admin\Admin. Defines all hooks for the admin area.
     * - Wpextended\Framework\Framework. Defines the settings framework for the plugin.
     *
     * @since 1.0.0
     * @access public
     */
    public function load_dependencies()
    {
        // The class responsible for defining internationalization functionality of the plugin.
        $plugin_i18n = new I18n();

        // Initialize migrations runner (checks if migrations should run)
        if (class_exists('Wpextended\\Includes\\Migrations\\Migrations')) {
            new \Wpextended\Includes\Migrations\Migrations();
        }

        // Initialize the notices system
        Notices::init();

        // Initialize licensing only for pro version
        if (defined('WP_EXTENDED_PRO') && file_exists(WP_EXTENDED_PATH . '/includes/licensing/Licensing.php')) {
            new \Wpextended\Includes\Licensing\Licensing();
        }

        // Initialize modules
        Modules::getInstance()->init();

        // Initialize admin pages
        new ModulesPage();
        new SettingsPage();

        // The class responsible for generating the settings framework for the plugin.
        new Framework();

        // Record version on successful update/install
        add_action('upgrader_process_complete', [$this, 'onUpgraderProcessComplete'], 10, 2);

        // Add plugin links
        $this->setup_plugin_links();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since 1.0.0
     * @return string The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since 1.0.0
     * @return string The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Handle plugin upgrader completion and record current version.
     *
     * @param mixed $upgrader Upgrader instance (unused)
     * @param array $extra    Extra data about the upgrade process
     * @return void
     */
    public function onUpgraderProcessComplete($upgrader, $extra)
    {
        if (!isset($extra['type']) || $extra['type'] !== 'plugin') {
            return;
        }
        if (!isset($extra['action']) || !in_array($extra['action'], array('update', 'install'), true)) {
            return;
        }
        if (empty($extra['plugins']) || !is_array($extra['plugins'])) {
            return;
        }

        if (in_array(WP_EXTENDED_PLUGIN_BASENAME, $extra['plugins'], true) && defined('WP_EXTENDED_VERSION')) {
            update_option('wpextended_version', WP_EXTENDED_VERSION, false);
        }
    }

    /**
     * Setup plugin links for both action links and row meta.
     *
     * @since 1.0.0
     * @return void
     */
    private function setup_plugin_links()
    {
        // Add action links
        add_filter(sprintf('plugin_action_links_%s', WP_EXTENDED_PLUGIN_BASENAME), [$this, 'add_action_links']);

        // Add row meta links
        add_filter('plugin_row_meta', [$this, 'add_row_meta_links'], 10, 2);
    }

    /**
     * Add action links (appears with Activate/Deactivate).
     *
     * @since 1.0.0
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_action_links($links)
    {
        // Add Settings link
        $links[] = sprintf(
            '<a href="%s">%s</a>',
            Utils::getModulePageLink('modules'),
            __('Settings', WP_EXTENDED_TEXT_DOMAIN)
        );

        // Add upgrade link for free version
        if (!defined('WP_EXTENDED_PRO') || !WP_EXTENDED_PRO) {
            $links[] = sprintf(
                '<a href="%s" style="color: #168200; font-weight: 600;" target="_blank" aria-label="%s">%s</a>',
                Utils::generateTrackedLink('https://wpextended.io/pricing/', 'upgrade'),
                __('Upgrade to PRO, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
                __('Upgrade to PRO', WP_EXTENDED_TEXT_DOMAIN)
            );
        }

        return $links;
    }

    /**
     * Add row meta links (appears below plugin description).
     *
     * @since 1.0.0
     * @param array $links Existing plugin row meta links
     * @param string $file Plugin file path
     * @return array Modified plugin row meta links
     */
    public function add_row_meta_links($links, $file)
    {
        // Only add links for our plugin
        if ($file !== WP_EXTENDED_PLUGIN_BASENAME) {
            return $links;
        }

        // Add Documentation link
        $links[] = sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            Utils::generateTrackedLink('https://wpextended.io/docs/', 'docs'),
            __('Docs, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
            __('Docs', WP_EXTENDED_TEXT_DOMAIN)
        );

        // Add Help & Support link
        $links[] = sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            Utils::generateTrackedLink('https://wpextended.io/support/', 'support'),
            __('Help & Support, opens in new tab', WP_EXTENDED_TEXT_DOMAIN),
            __('Help & Support', WP_EXTENDED_TEXT_DOMAIN)
        );

        return $links;
    }
}
