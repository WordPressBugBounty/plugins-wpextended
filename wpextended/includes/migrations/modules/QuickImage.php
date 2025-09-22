<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

class QuickImage
{
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    public function migrate(): void
    {
        $existing = Utils::getSettings('quick-image');
        if (!empty($existing)) {
            return;
        }

        $legacy = get_option('quick-replace-feature-image', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $postTypes = [];
        foreach ($legacy as $postType => $flags) {
            if (!is_string($postType)) {
                continue;
            }
            $postTypes[] = sanitize_key($postType);
        }
        $postTypes = array_values(array_unique(array_filter($postTypes)));

        if (!empty($postTypes)) {
            Utils::updateSettings('quick-image', array('post_types' => $postTypes));
        }
    }

    public function cleanup(): void
    {
        delete_option('quick-replace-feature-image');
    }
}
