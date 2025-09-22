<?php

namespace Wpextended\Modules\DisableAutoUpdates;

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
        parent::__construct('disable-auto-updates');
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
