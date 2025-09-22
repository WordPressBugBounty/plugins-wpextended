<?php

namespace Wpextended\Modules\CustomLoginUrl;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;
use Wpextended\Includes\Modules;

/**
 * Custom Login URL Module Bootstrap
 *
 * This module allows administrators to change the default WordPress login URL
 * from wp-login.php to a custom slug for enhanced security.
 *
 * @package Wpextended\Modules\CustomLoginUrl
 */
class Bootstrap extends BaseModule
{
    /**
     * Flag to track if wp-login.php was accessed directly
     *
     * @var bool
     */
    private $wp_login = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('custom-login-url');

        // Register plugins_loaded hook immediately to ensure it fires
        add_action('plugins_loaded', array($this, 'loginUrlPluginsLoaded'), 2);
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        $custom_slug = $this->getSetting('login_url');

        if (empty($custom_slug)) {
            return;
        }

        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function registerHooks()
    {
        add_action('wp_loaded', array($this, 'wpLoaded'));
        add_action('setup_theme', array($this, 'disableCustomizePhp'), 1);
        add_filter('site_url', array($this, 'siteUrl'), 10, 4);
        add_filter('wp_redirect', array($this, 'wpRedirect'), 10, 2);
        add_filter('site_option_welcome_email', array($this, 'welcomeEmail'));
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
    }

    /**
     * Filter site URL
     *
     * @param string $url     The complete site URL including scheme and path.
     * @param string $path    Path relative to the site URL. Blank string if no path is specified.
     * @param string $scheme  Scheme to give the site URL context. Accepts 'http', 'https', 'login',
     *                        'login_post', 'admin', 'relative' or null.
     * @param int    $blog_id Blog ID, or null for the current blog.
     * @return string Filtered site URL.
     */
    public function siteUrl($url, $path, $scheme, $blog_id)
    {
        return $this->filterWpLogin($url, $scheme);
    }

    /**
     * Filter wp_redirect
     *
     * @param string $location The path to redirect to.
     * @param int    $status   Status code to use.
     * @return string Filtered redirect location.
     */
    public function wpRedirect($location, $status)
    {
        if (is_user_logged_in()) {
            return $this->filterWpLogin($location);
        }

        $parsed_url = wp_parse_url($location);
        $login_slug = $this->getSetting('login_url');

        if (trim((string)wp_parse_url($location, PHP_URL_PATH), '/') !== $login_slug) {
            return $this->filterWpLogin($location);
        }

        $parsed_query = array();
        if (isset($parsed_url['query'])) {
            wp_parse_str($parsed_url['query'], $parsed_query);
        }

        // Check for reauth redirect
        if (empty($parsed_query['redirect_to']) || empty($parsed_query['reauth'])) {
            return $this->filterWpLogin($location);
        }

        $redirect_url = wp_parse_url($parsed_query['redirect_to']);
        $redirect_query = array();

        if (!empty($redirect_url['query'])) {
            wp_parse_str($redirect_url['query'], $redirect_query);
        }

        // Allow certain queries through
        if (!empty($redirect_query)) {
            $allowed_keys = array('newuseremail');
            foreach ($allowed_keys as $key) {
                if (isset($redirect_query[$key])) {
                    return $this->filterWpLogin($location);
                }
            }
        }

        $this->disableLoginUrl();
        return $this->filterWpLogin($location);
    }

    /**
     * Filter wp-login.php URLs
     *
     * @param string      $url    The URL to filter.
     * @param string|null $scheme The scheme to use. Default is null.
     * @return string Filtered URL.
     */
    public function filterWpLogin($url, $scheme = null)
    {
        if (!is_string($url) || strpos($url, 'wp-login.php') === false) {
            return $url;
        }

        // Set HTTPS Scheme if SSL
        if (is_ssl()) {
            $scheme = 'https';
        }

        // Check for Query String and Craft New Login URL
        $query_string = explode('?', $url);
        if (isset($query_string[1])) {
            parse_str($query_string[1], $query_string);
            if (isset($query_string['login'])) {
                $query_string['login'] = rawurlencode($query_string['login']);
            }
            $url = add_query_arg($query_string, $this->loginUrl($scheme));
        } else {
            $url = $this->loginUrl($scheme);
        }

        return $url;
    }

    /**
     * Get the custom login URL
     *
     * @param string|null $scheme The scheme to give the site URL context.
     * @return string The custom login URL.
     */
    public function loginUrl($scheme = null)
    {
        $login_slug = $this->getSetting('login_url');

        // Return Full New Login URL Based on Permalink Structure
        if (get_option('permalink_structure')) {
            return $this->trailingslashit(home_url('/', $scheme) . $login_slug);
        }

        return home_url('/', $scheme) . '?' . $login_slug;
    }

    /**
     * Add trailing slash if necessary
     *
     * @param string $string The string to potentially add a trailing slash to.
     * @return string The string with or without a trailing slash.
     */
    public function trailingslashit($string)
    {
        // Check for Permalink Trailing Slash and Add to String
        if ((substr(get_option('permalink_structure'), -1, 1)) === '/') {
            return trailingslashit($string);
        }

        return untrailingslashit($string);
    }

    /**
     * Get the login slug
     *
     * @return string The custom login slug.
     */
    public function loginSlug()
    {
        return $this->getSetting('login_url');
    }

    /**
     * Check if module is enabled and settings are loaded
     *
     * @return bool
     */
    private function maybeLoadModule()
    {
        if (!Modules::isModuleEnabled('custom-login-url')) {
            return false;
        }

        // Load settings directly if not available yet
        if (empty($this->settings)) {
            $this->settings = Utils::getSettings($this->module_id);
        }

        return true;
    }

    /**
     * Handle login URL detection during plugins_loaded
     *
     * @return void
     */
    public function loginUrlPluginsLoaded()
    {
        if (!$this->maybeLoadModule()) {
            return;
        }

        $custom_slug = $this->getSetting('login_url');
        if (empty($custom_slug)) {
            return;
        }

        $this->processLoginUrlDetection();
    }

    /**
     * Process login URL detection logic
     *
     * @return void
     */
    private function processLoginUrlDetection()
    {
        global $pagenow, $wpextended_wp_login;

        $URI = parse_url($_SERVER['REQUEST_URI']);
        $path = !empty($URI['path']) ? untrailingslashit($URI['path']) : '';
        $slug = $this->loginSlug();

        if ($this->isWpLoginRequest($path)) {
            $this->handleWpLoginRequest($wpextended_wp_login, $pagenow);
            return;
        }

        if ($this->isWpRegisterRequest($path)) {
            $this->handleWpRegisterRequest($wpextended_wp_login, $pagenow);
            return;
        }

        if ($this->isCustomLoginUrl($path, $slug)) {
            $pagenow = 'wp-login.php';
        }
    }

    /**
     * Check if current request is for wp-login.php
     *
     * @param string $path The current request path.
     * @return bool
     */
    private function isWpLoginRequest($path)
    {
        return !is_admin() && (
            strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-login.php') !== false ||
            $path === site_url('wp-login', 'relative')
        );
    }

    /**
     * Check if current request is for wp-register.php or wp-signup.php
     *
     * @param string $path The current request path.
     * @return bool
     */
    private function isWpRegisterRequest($path)
    {
        return !is_admin() && (
            strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-register.php') !== false ||
            strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-signup.php') !== false ||
            $path === site_url('wp-register', 'relative')
        );
    }

    /**
     * Check if current request is for the custom login URL
     *
     * @param string $path The current request path.
     * @param string $slug The custom login slug.
     * @return bool
     */
    private function isCustomLoginUrl($path, $slug)
    {
        return $path === home_url($slug, 'relative') ||
            (!get_option('permalink_structure') && isset($_GET[$slug]) && empty($_GET[$slug]));
    }

    /**
     * Handle wp-login.php request
     *
     * @param bool $wpextended_wp_login Reference to the global flag.
     * @param string $pagenow Reference to the global pagenow variable.
     * @return void
     */
    private function handleWpLoginRequest(&$wpextended_wp_login, &$pagenow)
    {
        $wpextended_wp_login = true;
        $_SERVER['REQUEST_URI'] = $this->trailingslashit('/' . str_repeat('-/', 10));
        $pagenow = 'index.php';
    }

    /**
     * Handle wp-register.php request
     *
     * @param bool $wpextended_wp_login Reference to the global flag.
     * @param string $pagenow Reference to the global pagenow variable.
     * @return void
     */
    private function handleWpRegisterRequest(&$wpextended_wp_login, &$pagenow)
    {
        $wpextended_wp_login = true;
        $_SERVER['REQUEST_URI'] = $this->trailingslashit('/' . str_repeat('-/', 10));
        $pagenow = 'index.php';
    }

    /**
     * Handle request during wp_loaded
     *
     * @return void
     */
    public function wpLoaded()
    {
        if (!$this->maybeLoadModule()) {
            return;
        }

        $custom_slug = $this->getSetting('login_url');
        if (empty($custom_slug)) {
            return;
        }

        $this->processWpLoadedRequest($custom_slug);
    }

    /**
     * Process wp_loaded request logic
     *
     * @param string $custom_slug The custom login slug.
     * @return void
     */
    private function processWpLoadedRequest($custom_slug)
    {
        global $pagenow, $wpextended_wp_login;

        $URI = parse_url($_SERVER['REQUEST_URI']);
        $path = !empty($URI['path']) ? untrailingslashit($URI['path']) : '';

        // Fallback: Check if we're on the custom login URL but pagenow wasn't set
        if ($this->shouldSetPagenowToWpLogin($pagenow, $wpextended_wp_login, $path, $custom_slug)) {
            $pagenow = 'wp-login.php';
        }

        // Disable Normal WP-Admin
        if ($this->shouldDisableWpAdmin()) {
            $this->disableLoginUrl();
            return;
        }

        // Requesting Hidden Login Form - Path Mismatch
        if ($this->isPathMismatch($pagenow, $URI)) {
            $this->redirectToCustomLoginUrl();
            return;
        }

        // Requesting wp-login.php Directly, Disabled
        if ($wpextended_wp_login) {
            $this->disableLoginUrl();
            return;
        }

        // Requesting Hidden Login Form
        if ($pagenow === 'wp-login.php') {
            $this->handleLoginFormRequest();
        }
    }

    /**
     * Check if pagenow should be set to wp-login.php
     *
     * @param string $pagenow The current pagenow value.
     * @param bool $wpextended_wp_login The wp login flag.
     * @param string $path The current request path.
     * @param string $custom_slug The custom login slug.
     * @return bool
     */
    private function shouldSetPagenowToWpLogin($pagenow, $wpextended_wp_login, $path, $custom_slug)
    {
        return $pagenow === 'index.php' && !$wpextended_wp_login &&
            ($path === home_url($custom_slug, 'relative') ||
                (!get_option('permalink_structure') && isset($_GET[$custom_slug]) && empty($_GET[$custom_slug])));
    }

    /**
     * Check if wp-admin should be disabled
     *
     * @return bool
     */
    private function shouldDisableWpAdmin()
    {
        global $pagenow;

        return is_admin() && !is_user_logged_in() && !defined('WP_CLI') && !defined('DOING_AJAX') &&
            $pagenow !== 'admin-post.php' && (isset($_GET) && empty($_GET['adminhash']) && empty($_GET['newuseremail']));
    }

    /**
     * Check if there's a path mismatch for the login form
     *
     * @param string $pagenow The current pagenow value.
     * @param array $URI The parsed URI.
     * @return bool
     */
    private function isPathMismatch($pagenow, $URI)
    {
        return $pagenow === 'wp-login.php' && $URI['path'] !== $this->trailingslashit($URI['path']) && get_option('permalink_structure');
    }

    /**
     * Redirect to custom login URL
     *
     * @return void
     */
    private function redirectToCustomLoginUrl()
    {
        $URL = $this->trailingslashit($this->loginUrl()) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        wp_safe_redirect($URL);
        die();
    }

    /**
     * Handle login form request
     *
     * @return void
     */
    private function handleLoginFormRequest()
    {
        global $error, $interim_login, $action, $user_login;

        // User Already Logged In
        if (is_user_logged_in() && !isset($_REQUEST['action'])) {
            wp_safe_redirect(admin_url());
            die();
        }

        // Include Login Form
        @require_once ABSPATH . 'wp-login.php';
        die();
    }

    /**
     * Disable customize.php access when not logged in
     *
     * @return void
     */
    public function disableCustomizePhp()
    {
        global $pagenow;

        // Disable customize.php from Redirecting to Login URL
        if (!is_user_logged_in() && $pagenow === 'customize.php') {
            $this->disableLoginUrl();
        }
    }

    /**
     * Filter welcome email
     *
     * @param string $value The welcome email content.
     * @return string Filtered welcome email content.
     */
    public function welcomeEmail($value)
    {
        $custom_slug = $this->getSetting('login_url');

        // Check for Custom Login URL and Replace
        if (!empty($custom_slug)) {
            $value = str_replace(array('wp-login.php', 'wp-admin'), trailingslashit($custom_slug), $value);
        }

        return $value;
    }

    /**
     * Choose what to do when disabling a login url endpoint
     *
     * @return void
     */
    public function disableLoginUrl()
    {
        $disabled_behavior = $this->getSetting('disabled_behavior');

        if (empty($disabled_behavior)) {
            $this->showDefaultMessage();
            return;
        }

        switch ($disabled_behavior) {
            case '404':
                $this->redirectTo404();
                break;
            case 'home':
                $this->redirectToHome();
                break;
            case 'url_redirect':
                $this->redirectToCustomUrl();
                break;
            case 'existing_page':
                $this->redirectToExistingPage();
                break;
            default:
                $this->showDefaultMessage();
                break;
        }
    }

    /**
     * Show default disabled message
     *
     * @return void
     */
    private function showDefaultMessage()
    {
        $message = $this->getSetting('disabled_message');
        $message = !empty($message) ? $message : __('This has been disabled.', 'wpextended');

        wp_die($message, 403);
    }

    /**
     * Redirect to 404 page
     *
     * @return void
     */
    private function redirectTo404()
    {
        wp_safe_redirect(home_url('404'));
        die();
    }

    /**
     * Redirect to homepage
     *
     * @return void
     */
    private function redirectToHome()
    {
        wp_safe_redirect(home_url());
        die();
    }

    /**
     * Redirect to custom URL
     *
     * @return void
     */
    private function redirectToCustomUrl()
    {
        $url_redirect = $this->getSetting('url_redirect');
        if (empty($url_redirect)) {
            $this->showDefaultMessage();
            return;
        }

        // Use header() directly for external URLs to bypass wp_redirect filter
        if ($this->isExternalUrl($url_redirect)) {
            header('Location: ' . $url_redirect);
            exit;
        }

        wp_safe_redirect($url_redirect);
        die();
    }

    /**
     * Check if URL is external
     *
     * @param string $url The URL to check.
     * @return bool
     */
    private function isExternalUrl($url)
    {
        return strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0;
    }

    /**
     * Redirect to existing page
     *
     * @return void
     */
    private function redirectToExistingPage()
    {
        $existing_page = $this->getSetting('existing_page');
        if (empty($existing_page)) {
            $this->showDefaultMessage();
            return;
        }

        wp_safe_redirect(get_permalink($existing_page));
        die();
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
        // Validate custom redirect URL field
        if (!isset($input['url_redirect']) || empty($input['url_redirect'])) {
            return $validations;
        }

        $url_value = trim($input['url_redirect']);

        // Check if it's a relative URL (starts with /)
        if (strpos($url_value, '/') === 0) {
            return $validations;
        }

        // Check if it's a full URL using wp_http_validate_url
        if (!wp_http_validate_url($url_value)) {
            $validations[] = [
                'field' => 'url_redirect',
                'code' => 'invalid_url',
                'message' => __('Custom Redirect URL must be a valid URL (starting with http:// or https://) or a relative path (starting with /).', WP_EXTENDED_TEXT_DOMAIN),
                'type' => 'error'
            ];
        }

        return $validations;
    }

    /**
     * Register settings fields for the module
     *
     * Defines the settings configuration for the custom login URL module
     *
     * @return array The settings configuration array
     */
    public function getSettingsFields()
    {
        $settings = array();

        $settings['tabs'] = array(
            array(
                'id' => 'custom_login_url',
                'title' => __('Custom Login URL', WP_EXTENDED_TEXT_DOMAIN),
            ),
        );

        $settings['sections'] = array(
            array(
                'tab_id'        => 'custom_login_url',
                'section_id'    => 'settings',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id'          => 'login_url',
                        'type'        => 'text',
                        'title'       => __('Custom Login URL Slug', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Enter the new slug for the login page.', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => '',
                        'prefix'      => trailingslashit(site_url()),
                        'placeholder' => 'my-login',
                    ),
                    array(
                        'id'          => 'disabled_behavior',
                        'type'        => 'select',
                        'title'       => __('Redirect Type', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select where the user is redirected if accessing wp-admin directly.', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => 'home',
                        'choices'     => array(
                            'home' => __('Home Page', WP_EXTENDED_TEXT_DOMAIN),
                            '404' => __('404 Page', WP_EXTENDED_TEXT_DOMAIN),
                            'url_redirect' => __('Custom URL', WP_EXTENDED_TEXT_DOMAIN),
                            'existing_page' => __('Existing Page', WP_EXTENDED_TEXT_DOMAIN),
                            'message' => __('Custom Message', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                    ),
                    array(
                        'id'          => 'existing_page',
                        'type'        => 'select',
                        'title'       => __('Existing Page', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select an existing page to redirect to.', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => '',
                        'choices'     => $this->getPages(),
                        'show_if'     => array(
                            array(
                                'field' => 'disabled_behavior',
                                'value' => 'existing_page',
                            ),
                        ),
                    ),
                    array(
                        'id'          => 'url_redirect',
                        'type'        => 'text',
                        'title'       => __('Custom Redirect URL', WP_EXTENDED_TEXT_DOMAIN),
                        /* translators: %s: example full URL, %s: example relative path */
                        'description' => sprintf(__('Enter a full URL (e.g %s) or a relative path (e.g %s).', WP_EXTENDED_TEXT_DOMAIN), 'https://google.com', '/about-us'),
                        /* translators: %s: example full URL, %s: example relative path */
                        'placeholder' => sprintf(__('%s or %s', WP_EXTENDED_TEXT_DOMAIN), 'https://google.com', '/about-us'),
                        'show_if'     => array(
                            array(
                                'field' => 'disabled_behavior',
                                'value' => 'url_redirect',
                            ),
                        ),
                    ),
                    array(
                        'id'          => 'disabled_message',
                        'type'        => 'textarea',
                        'title'       => __('Access Denied Message', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('The message displayed to the user if they cannot access the page.', WP_EXTENDED_TEXT_DOMAIN),
                        'default'     => __('This has been disabled.', WP_EXTENDED_TEXT_DOMAIN),
                        'placeholder' => __('This has been disabled.', WP_EXTENDED_TEXT_DOMAIN),
                        'show_if'     => array(
                            array(
                                'field' => 'disabled_behavior',
                                'value' => 'message',
                            ),
                        ),
                    )
                )
            ),
        );

        return $settings;
    }

    /**
     * Get pages for redirect dropdown
     *
     * @return array
     */
    private function getPages()
    {
        $posts = get_posts(array(
            'sort_column' => 'title',
            'sort_order' => 'asc',
            'post_type' => 'page',
            'post_status' => 'publish',
        ));

        if (empty($posts)) {
            return array('' => __('No pages found', WP_EXTENDED_TEXT_DOMAIN));
        }

        $choices = array();
        foreach ($posts as $post) {
            $choices[$post->ID] = $post->post_title;
        }

        return $choices;
    }
}
