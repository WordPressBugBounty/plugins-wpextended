<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Change WP Admin URL (Custom Login URL) settings.
 */
class CustomLoginUrl
{
    /**
     * Run the module migration and clean up legacy options.
     */
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    /**
     * Perform migration from legacy option to new module settings.
     */
    public function migrate(): void
    {
        // If already migrated, skip
        $existing = Utils::getSettings('custom-login-url');
        if (!empty($existing)) {
            return;
        }

        // Legacy option is an array with keys: wpext_login_url, wpext_redirect_url
        $legacy = get_option('wpext-change-wp-admin-default-url', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $loginSlug = '';
        if (isset($legacy['wpext_login_url']) && is_string($legacy['wpext_login_url'])) {
            $loginSlug = sanitize_title($legacy['wpext_login_url']);
        }

        $redirect = '';
        if (isset($legacy['wpext_redirect_url']) && is_string($legacy['wpext_redirect_url'])) {
            $redirect = esc_url_raw($legacy['wpext_redirect_url']);
        }

        $settings = [];

        if ($loginSlug !== '') {
            $settings['login_url'] = $loginSlug;
        }

        if ($redirect !== '') {
            $settings['disabled_behavior'] = 'url_redirect';
            $settings['url_redirect'] = $redirect;
        }

        // If nothing to migrate, bail
        if (empty($settings)) {
            return;
        }

        Utils::updateSettings('custom-login-url', $settings);
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-change-wp-admin-default-url');
    }
}
