<?php

namespace Wpextended\Includes\Migrations\Modules;

use Wpextended\Includes\Utils;

if (!defined('ABSPATH')) {
    die;
}

class PostTypeOrder
{
    public function run(): void
    {
        $this->migrate();
        $this->cleanup();
    }

    public function migrate(): void
    {
        $existing = Utils::getSettings('post-type-order');
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $legacy = get_option('wpext-post-type-order', []);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $postTypes = [];
        foreach ($legacy as $key => $value) {
            $slug = is_string($key) ? $key : (is_string($value) ? $value : '');
            if ($slug !== '') {
                $postTypes[] = sanitize_key($slug);
            }
        }

        $postTypes = array_values(array_unique(array_filter($postTypes)));
        if (!empty($postTypes)) {
            Utils::updateSettings('post-type-order', array('post_types' => $postTypes));
        }
    }

    public function cleanup(): void
    {
        delete_option('wpext-post-type-order');
    }
}
