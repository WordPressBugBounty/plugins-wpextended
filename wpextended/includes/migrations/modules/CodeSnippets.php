<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Modules\CodeSnippets\Includes\SnippetManager;

if (!defined('ABSPATH')) {
    die;
}

/**
 * Migrate legacy Code Snippets from CPT and option to file-based storage.
 */
class CodeSnippets
{
    /**
     * Run the module migration and clean up legacy options.
     */
    public function run(): void
    {
        $this->migrateFromCpt();
        $this->migrateFromOption();
        $this->cleanup();
    }

    /**
     * Migrate legacy snippets stored as CPT `snippet`.
     */
    private function migrateFromCpt(): void
    {
        if (!function_exists('get_posts')) {
            return;
        }

        $posts = get_posts(array(
            'post_type' => 'snippet',
            'post_status' => array('publish'),
            'numberposts' => -1,
            'suppress_filters' => true,
        ));

        if (empty($posts) || !is_array($posts)) {
            return;
        }

        $manager = new SnippetManager();

        foreach ($posts as $post) {
            $post_id = isset($post->ID) ? (int) $post->ID : 0;
            if ($post_id <= 0) {
                continue;
            }

            $title = isset($post->post_title) ? $post->post_title : '';
            $code  = isset($post->post_content) ? $post->post_content : '';

            $code_type = get_post_meta($post_id, 'snippet_code_type', true);
            $position  = get_post_meta($post_id, 'snippet_position', true);
            $active    = get_post_meta($post_id, 'snippet_active', true);
            $desc      = get_post_meta($post_id, 'snippet_code_sesc', true);
            $scope     = get_post_meta($post_id, 'snippet_scope', true);
            $executed  = get_post_meta($post_id, 'snippet_executed', true);

            $type = $this->normalizeType($code_type);
            $run_location = $this->mapRunLocation($type, $position);

            $enabled = $this->truthy($active);
            if ($scope === 'once' && $this->truthy($executed)) {
                $enabled = false;
            }

            $data = array(
                'name' => is_string($title) ? $title : '',
                'type' => $type,
                'code' => is_string($code) ? $code : '',
                'enabled' => $enabled,
                'description' => is_string($desc) ? $desc : '',
                'run_location' => $run_location,
                'priority' => 10,
            );

            $this->createSnippetWithDedup($manager, $data);
        }
    }

    /**
     * Migrate legacy snippets stored in option `wpext-snippets`.
     */
    private function migrateFromOption(): void
    {
        $legacy = get_option('wpext-snippets', array());

        if (is_string($legacy)) {
            $decoded = json_decode($legacy, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $legacy = $decoded;
            }
        }

        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $manager = new SnippetManager();

        foreach ($legacy as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $label = isset($entry['label']) ? $entry['label'] : '';
            $code  = isset($entry['code']) ? $entry['code'] : '';
            $code_type = isset($entry['code_type']) ? $entry['code_type'] : '';
            $position  = isset($entry['position']) ? $entry['position'] : '';
            $active    = isset($entry['active']) ? $entry['active'] : null;
            $desc      = isset($entry['description']) ? $entry['description'] : '';

            // Old wpext-snippets supported basic HTML blocks with position only.
            // When no explicit code_type is provided, map directly to HTML snippets.
            if ($code_type === '' || $code_type === null) {
                $type = 'html';
                $run_location = $this->mapRunLocation($type, $position);
                $data = array(
                    'name' => is_string($label) ? $label : '',
                    'type' => $type,
                    'code' => is_string($code) ? $code : '',
                    'enabled' => $active === null ? true : $this->truthy($active),
                    'description' => is_string($desc) ? $desc : '',
                    'run_location' => $run_location,
                    'priority' => 10,
                );
            } else {
                $type = $this->normalizeType($code_type);
                $run_location = $this->mapRunLocation($type, $position);
                $data = array(
                    'name' => is_string($label) ? $label : '',
                    'type' => $type,
                    'code' => is_string($code) ? $code : '',
                    'enabled' => $this->truthy($active),
                    'description' => is_string($desc) ? $desc : '',
                    'run_location' => $run_location,
                    'priority' => 10,
                );
            }

            $this->createSnippetWithDedup($manager, $data);
        }
    }

    /**
     * Attempt to create a snippet, retrying with a unique name on duplicate.
     */
    private function createSnippetWithDedup(SnippetManager $manager, array $data): bool
    {
        if (empty($data['name']) || empty($data['code'])) {
            return false;
        }

        $result = $manager->createSnippet($data);
        if (is_array($result) && isset($result['success']) && $result['success'] === true) {
            return true;
        }

        // If duplicate name error, append numeric suffix and retry a few times
        $message = is_array($result) && isset($result['message']) ? (string) $result['message'] : '';
        if (stripos($message, 'already exists') !== false) {
            $baseName = $data['name'];
            for ($i = 2; $i <= 10; $i++) {
                $data['name'] = $baseName . ' (' . $i . ')';
                $retry = $manager->createSnippet($data);
                if (is_array($retry) && isset($retry['success']) && $retry['success'] === true) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Normalize legacy code type values to new keys.
     */
    private function normalizeType($legacy)
    {
        $val = is_string($legacy) ? strtolower($legacy) : '';
        switch ($val) {
            case 'php':
                return 'php';
            case 'css':
                return 'css';
            case 'javascript':
                return 'js';
            case 'js':
                return 'js';
            case 'html':
                return 'html';
            default:
                return 'php';
        }
    }

    /**
     * Map legacy position to new run_location keys per type.
     */
    private function mapRunLocation($type, $position)
    {
        $pos = is_string($position) ? strtolower($position) : '';
        if ($type === 'php') {
            return 'everywhere';
        }

        if ($pos === 'footer') {
            return 'site_footer';
        }
        if ($pos === 'head' || $pos === 'header') {
            return 'site_header';
        }

        // Sensible defaults per type
        return ($type === 'css') ? 'site_header' : 'site_footer';
    }

    /**
     * Convert mixed legacy truthy to boolean.
     */
    private function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, array('1', 'true', 'on', 'yes'), true);
        }
        return false;
    }

    /**
     * Cleanup legacy options.
     */
    private function cleanup(): void
    {
        // Remove legacy option container
        delete_option('wpext-snippets');

        // Remove legacy CPT posts and associated post meta
        if (!function_exists('get_posts')) {
            return;
        }

        $posts = get_posts(array(
            'post_type' => 'snippet',
            'post_status' => array('publish', 'draft', 'pending', 'private ', 'trash'),
            'numberposts' => -1,
            'suppress_filters' => true,
        ));

        if (!is_array($posts) || empty($posts)) {
            return;
        }

        $deletedCount = 0;
        foreach ($posts as $post) {
            $post_id = isset($post->ID) ? (int) $post->ID : 0;
            if ($post_id > 0) {
                // Force delete to ensure post meta is removed too
                wp_delete_post($post_id, true);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            set_transient('snippets_deleted', (int) $deletedCount, MINUTE_IN_SECONDS);
            return;
        }
    }
}
