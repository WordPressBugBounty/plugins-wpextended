<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy External Permalinks settings.
 */
class ExternalPermalinks
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
        $existing = Utils::getSettings('external-permalinks');
        if (!empty($existing)) {
            return;
        }

        // Legacy option: associative array of post type slug => 'on'|'1'|true
        $legacy = get_option('wpext-external-permalink-url', array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $postTypes = array();
        foreach ($legacy as $slug => $value) {
            if (!is_string($slug) || $slug === '') {
                continue;
            }

            if (!Utils::isTruthy($value)) {
                continue;
            }

            $sanitized = sanitize_key($slug);

            if ($sanitized === '') {
                continue;
            }

            // Only include valid post types
            if (post_type_exists($sanitized)) {
                $postTypes[] = $sanitized;
            }
        }

        $postTypes = array_values(array_unique(array_filter($postTypes)));

        if (empty($postTypes)) {
            return;
        }

        Utils::updateSettings('external-permalinks', array('post_types' => $postTypes));
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-external-permalink-url');
    }
}
