<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy User Sections to Clean Profiles settings.
 */
class CleanProfiles
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
        $existing = Utils::getSettings('clean-profiles');
        if (!empty($existing)) {
            return;
        }

        // Read legacy toggle. If explicitly disabled, skip migration
        $legacy_toggle = get_option('wpext-user-sections-toggle', '');
        $is_enabled = Utils::isTruthy($legacy_toggle);

        // Fetch legacy structure
        $legacy = get_option('wpext-user-sections', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        // Union of sections across roles
        $legacy_sections = array();
        if (isset($legacy['roles']) && is_array($legacy['roles'])) {
            foreach ($legacy['roles'] as $role => $config) {
                if (!is_array($config)) {
                    continue;
                }
                if (isset($config['hide_sections']) && is_array($config['hide_sections'])) {
                    foreach ($config['hide_sections'] as $section_key) {
                        if (is_string($section_key) && $section_key !== '') {
                            $legacy_sections[] = $section_key;
                        }
                    }
                }
            }
        }

        $legacy_sections = array_values(array_unique(array_filter($legacy_sections)));

        // Map legacy section slugs to new Clean Profiles selectors
        $map = array(
            'personal_options'      => 'user-rich-editing-wrap',
            'contact_info'          => 'user-email-wrap',
            'about_user'            => 'user-description-wrap',
            'account_management'    => 'user-pass1-wrap',
            'name'                  => 'user-user-login-wrap',
            'application_passwords' => 'application-passwords',
        );

        $sections = array();
        foreach ($legacy_sections as $legacy_key) {
            if (isset($map[$legacy_key])) {
                $sections[] = $map[$legacy_key];
            }
        }

        $sections = array_values(array_unique(array_filter($sections)));

        // If disabled or no sections resolved, do not set anything
        if (!$is_enabled || empty($sections)) {
            return;
        }

        $settings = array(
            'sections' => $sections,
        );

        Utils::updateSettings('clean-profiles', $settings);
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-user-sections');
        delete_option('wpext-user-sections-toggle');
    }
}
