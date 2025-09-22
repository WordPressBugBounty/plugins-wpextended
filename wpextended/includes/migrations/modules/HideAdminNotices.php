<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}
/**
 * Migrate legacy Hide Admin Notices settings.
 */
class HideAdminNotices
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
        $existing = Utils::getSettings('hide-admin-notices');
        if (!empty($existing)) {
            return;
        }

        // Legacy option structure example:
        // [ 'editor' => ['plugin_update','theme_update','core_update','all_notices'], ... ]
        $legacy = get_option('wpext-notices-config', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $settings = array();

        // Map legacy update keys to new keys
        $updateMap = array(
            'plugin_update' => 'plugin',
            'theme_update'  => 'theme',
            'core_update'   => 'core',
        );

        foreach ($legacy as $role => $values) {
            if (!is_array($values)) {
                continue;
            }

            $roleSlug = is_string($role) ? sanitize_key($role) : '';
            if ($roleSlug === '') {
                continue;
            }

            // Enable override for this role
            $settings[sprintf('%s__override_global', $roleSlug)] = true;

            // Map update notifications
            $updates = array();
            foreach ($values as $legacyFlag) {
                if (!is_string($legacyFlag)) {
                    continue;
                }
                $flag = strtolower(trim($legacyFlag));
                if (isset($updateMap[$flag])) {
                    $updates[] = $updateMap[$flag];
                }
            }
            if (!empty($updates)) {
                $settings[sprintf('%s__update_notifications', $roleSlug)] = array_values(array_unique($updates));
            }

            // Map all_notices → role notice_type = 'none'
            $hasAllNotices = in_array('all_notices', array_map('strtolower', $values), true);
            if ($hasAllNotices) {
                $settings[sprintf('%s__notice_type', $roleSlug)] = 'none';
            }
        }

        if (!empty($settings)) {
            Utils::updateSettings('hide-admin-notices', $settings);
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-notices-config');
    }
}
