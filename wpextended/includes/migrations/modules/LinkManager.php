<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Link Manager settings.
 */
class LinkManager
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
        $existing = Utils::getSettings('link-manager');
        if (!empty($existing)) {
            return;
        }

        $settings = array();

        // Preserve prior behavior: Disable arrow by default (true) to keep same UX
        $settings['disable_arrow'] = true;

        // Save settings if non-empty
        if (!empty($settings)) {
            Utils::updateSettings('link-manager', $settings);
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
    }
}
