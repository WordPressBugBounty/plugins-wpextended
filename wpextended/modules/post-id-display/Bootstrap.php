<?php

namespace Wpextended\Modules\PostIdDisplay;

use Wpextended\Modules\BaseModule;

/**
 * Bootstrap class for the Post ID Display module
 *
 * @package Wpextended\Modules\ShowPostId
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('post-id-display');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
    }
}
