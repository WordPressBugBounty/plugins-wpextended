<?php

namespace Wpextended\Modules\HideWpVersion;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('hide-wp-version');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_filter('the_generator', '__return_false');
    }
}
