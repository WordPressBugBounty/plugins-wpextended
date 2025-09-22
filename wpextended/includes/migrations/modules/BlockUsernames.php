<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

class BlockUsernames
{
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    public function migrate(): void
    {
        $existing = Utils::getSettings('block-usernames');
        if (!empty($existing)) {
            return;
        }

        $legacy = get_option('wpext-block-username-tag', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        // Extract comma-separated string from known key
        $usernames = [];
        if (isset($legacy['wpext_block_username']) && is_string($legacy['wpext_block_username'])) {
            $raw = $legacy['wpext_block_username'];
            $usernames = array_filter(array_map('sanitize_user', array_map('trim', explode(',', $raw))));
            $usernames = array_values(array_unique($usernames));
        }

        if (!empty($usernames)) {
            // Store as array of usernames
            Utils::updateSettings('block-usernames', ['usernames' => $usernames]);
        }
    }

    public function cleanup(): void
    {
        delete_option('wpext-block-username-tag');
    }
}
