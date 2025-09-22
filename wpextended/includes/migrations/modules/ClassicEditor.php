<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Disable Gutenberg (Classic Editor) settings.
 */
class ClassicEditor
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
        $existing = Utils::getSettings('classic-editor');
        if (!empty($existing)) {
            return;
        }

        // Legacy option: associative array of post type slugs to slugs
        $legacy = get_option('wpext-disable_gutenberg_editor', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        // Normalize into list of post types to disable
        $postTypes = [];
        foreach ($legacy as $key => $value) {
            // legacy format uses slug as both key and value
            $slug = is_string($key) ? $key : (is_string($value) ? $value : '');
            if ($slug !== '') {
                $postTypes[] = sanitize_key($slug);
            }
        }

        $postTypes = array_values(array_unique(array_filter($postTypes)));

        if (!empty($postTypes)) {
            Utils::updateSettings('classic-editor', array('post_types' => $postTypes));
        }
    }

    /**
     * Cleanup legacy options.
     */
    public function cleanup(): void
    {
        delete_option('wpext-disable_gutenberg_editor');
    }
}
