<?php

namespace Wpextended\Modules\DisableBlog;

use Wpextended\Modules\BaseModule;

/**
 * DisableBlog module Bootstrap class
 *
 * Removes blog functionality from WordPress, including admin menus,
 * widgets, post types, and redirects. Designed for websites that don't need
 * the blog features.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('disable-blog');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    public function init()
    {
    }
}
