<?php

namespace Wpextended\Modules\Redirect404ToHomepage;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Notices;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('redirect-404-to-homepage');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_action('template_redirect', array($this, 'redirect404ToHomepage'), 9999);
        add_action('admin_notices', array($this, 'checkForRedirectPlugins'));
    }

    /**
     * Redirect 404 pages to homepage
     *
     * Checks if current page is a 404 error and redirects to homepage if no other
     * redirection plugin is already handling it. Skips redirect if in admin area,
     * during cron jobs, or XML-RPC requests.
     *
     * @return void
     */
    public function redirect404ToHomepage()
    {
        // Skip if not a 404 page or if in admin/cron/xmlrpc context
        if (!is_404() || is_admin() || (defined('DOING_CRON') && DOING_CRON) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return;
        }

        // Skip if another plugin is already handling the redirect
        if ($this->isRedirectedByOtherPlugins()) {
            return;
        }

        // Redirect to homepage
        wp_safe_redirect(home_url(), 301);
        exit();
    }

    /**
     * Check if the current URL is redirected by other plugins
     *
     * @return bool
     */
    private function isRedirectedByOtherPlugins()
    {
        $supported_plugins = $this->getSupportedPlugins();

        foreach ($supported_plugins as $plugin) {
            if ($plugin['active']()) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedPlugins()
    {
        return array(
            // Redirection plugin
            array(
                'class' => 'Redirection',
                'active' => function () {
                    return class_exists('Redirection') &&
                        method_exists('Redirection', 'get_instance') &&
                        \Redirection::get_instance()->is_redirected();
                }
            ),
            // Rank Math SEO
            array(
                'class' => 'RankMath',
                'active' => function () {
                    return function_exists('rank_math_redirection') &&
                        rank_math_redirection()->redirect->is_redirected();
                }
            ),
            // Yoast SEO
            array(
                'class' => 'WPSEO_Redirect_Manager',
                'active' => function () {
                    if (!defined('WPSEO_VERSION') || !class_exists('WPSEO_Redirect_Manager')) {
                        return false;
                    }
                    $redirect_manager = new \WPSEO_Redirect_Manager();
                    return method_exists($redirect_manager, 'is_redirected') &&
                        $redirect_manager->is_redirected();
                }
            ),
            // All in One SEO Pack
            array(
                'class' => 'AIOSEOP_Redirection',
                'active' => function () {
                    return class_exists('AIOSEOP_Redirection') &&
                        method_exists('AIOSEOP_Redirection', 'is_redirected') &&
                        \AIOSEOP_Redirection::is_redirected();
                }
            ),
            // SEO Ultimate
            array(
                'class' => 'SEO_Ultimate_Module',
                'active' => function () {
                    return class_exists('SEO_Ultimate_Module') &&
                        method_exists('SEO_Ultimate_Module', 'is_redirected') &&
                        \SEO_Ultimate_Module::is_redirected();
                }
            ),
            // Simple 301 Redirects
            array(
                'class' => 'Simple301Redirects',
                'active' => function () {
                    return class_exists('Simple301Redirects') &&
                        method_exists('Simple301Redirects', 'is_redirected') &&
                        \Simple301Redirects::is_redirected();
                }
            ),
        );
    }

    /**
     * Show an admin notice if a redirection plugin is detected
     *
     * @return void
     */
    public function checkForRedirectPlugins()
    {
        $redirection_plugins = array(
            'Redirection' => 'redirection/redirection.php',
            'Rank Math SEO' => 'seo-by-rank-math/rank-math.php',
            'Yoast SEO' => 'wordpress-seo/wp-seo.php',
            'All in One SEO Pack' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'SEO Ultimate' => 'seo-ultimate/seo-ultimate.php',
            'Simple 301 Redirects' => 'simple-301-redirects/simple-301-redirects.php'
        );

        $active_redirection_plugins = array_filter(
            array_keys($redirection_plugins),
            function ($plugin_name) use ($redirection_plugins) {
                return in_array(
                    $redirection_plugins[$plugin_name],
                    get_option('active_plugins', [])
                );
            }
        );

        if (empty($active_redirection_plugins)) {
            return;
        }

        Notices::add(array(
            'message' => sprintf(
                __('Warning: The following redirection plugins are active and might conflict with your custom 404 redirection: %s', WP_EXTENDED_TEXT_DOMAIN),
                implode(', ', $active_redirection_plugins)
            ),
            'type' => 'warning',
            'dismissible' => true
        ));
    }
}
