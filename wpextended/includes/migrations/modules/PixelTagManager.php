<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Pixel Tag Manager settings to new structure.
 */
class PixelTagManager
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
        $existing = Utils::getSettings('pixel-tag-manager');
        if (!empty($existing)) {
            return;
        }

        // Legacy option contains provider IDs
        $legacy = get_option('wpext-pixel-tag', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        // Map legacy keys to new field IDs
        $map = [
            'wpxt-google-analitic' => 'google-analytics',
            'wpext-facebook'       => 'facebook-pixel',
            'wpext-pintrest'       => 'pinterest-tag',
        ];

        $new = [];
        foreach ($map as $legacyKey => $newKey) {
            if (!empty($legacy[$legacyKey]) && is_string($legacy[$legacyKey])) {
                $new[$newKey] = sanitize_text_field($legacy[$legacyKey]);
            }
        }

        if (!empty($new)) {
            Utils::updateSettings('pixel-tag-manager', $new);
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-pixel-tag');
    }
}
