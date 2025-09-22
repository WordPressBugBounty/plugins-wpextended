<?php

namespace Wpextended\Modules\ClassicWidgets;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('classic-widgets');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_filter('gutenberg_use_widgets_block_editor', '__return_false');
        add_filter('use_widgets_block_editor', '__return_false');
    }
}
