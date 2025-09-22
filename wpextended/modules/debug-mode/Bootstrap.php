<?php

namespace Wpextended\Modules\DebugMode;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('debug-mode');
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
