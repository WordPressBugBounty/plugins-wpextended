<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Hide Admin Bar settings.
 */
class HideAdminBar
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
     * Perform migration from legacy options to new module settings.
     */
    public function migrate(): void
    {
        // If already migrated, skip
        $existing = Utils::getSettings('hide-admin-bar');
        if (!empty($existing)) {
            return;
        }

        // Legacy stored roles map like: ["editor"=>"editor", ...]
        $legacy = get_option('wpext-hide_admin_bar', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $roles = array();
        foreach ($legacy as $key => $value) {
            // Accept either key or value as the role slug
            $slug = '';
            if (is_string($key) && $key !== '') {
                $slug = sanitize_key($key);
            } elseif (is_string($value) && $value !== '') {
                $slug = sanitize_key($value);
            }
            if ($slug !== '') {
                $roles[] = $slug;
            }
        }

        $roles = array_values(array_unique(array_filter($roles)));
        if (empty($roles)) {
            return;
        }

        Utils::updateSettings('hide-admin-bar', array('roles' => $roles));
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-hide_admin_bar');
    }
}
