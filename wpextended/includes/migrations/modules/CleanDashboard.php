<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

class CleanDashboard
{
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    public function migrate(): void
    {
        // Skip if target module already has settings.
        $existing = Utils::getSettings('clean-dashboard');
        if (!empty($existing)) {
            return;
        }

        // Legacy dashboard widgets state (array of widget ids to disable)
        $legacy = get_option('wpext-disable-dashboard-widget', []);

        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        // Collect IDs from both keys and values to tolerate different storage shapes
        $legacyIds = array();
        foreach ($legacy as $key => $value) {
            if (is_string($key) && $key !== '') {
                $legacyIds[] = $key;
            }
            if (is_string($value) && $value !== '') {
                $legacyIds[] = $value;
            }
        }
        $legacyIds = array_unique($legacyIds);

        // Map legacy ids to current ids
        $mapping = array(
            // Known typo in legacy data
            'welcome-deshboard' => 'dashboard_welcome',
        );

        $normalized = array();
        foreach ($legacyIds as $legacyId) {
            $currentId = isset($mapping[$legacyId]) ? $mapping[$legacyId] : $legacyId;
            $normalized[] = $currentId;
        }
        $normalized = array_values(array_unique($normalized));

        // Persist using the new settings structure
        $newSettings = array(
            'disable_all_widgets' => false,
            'widgets' => $normalized,
        );

        Utils::updateSettings('clean-dashboard', $newSettings);
    }

    public function cleanup(): void
    {
        delete_option('wpext_clean_admin_dashboard');
        delete_option('wpext-disable-dashboard-widget');
    }
}
