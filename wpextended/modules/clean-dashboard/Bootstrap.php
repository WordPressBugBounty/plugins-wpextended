<?php

namespace Wpextended\Modules\CleanDashboard;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
  /**
   * Constructor.
   *
   * @param object $module The parent module instance.
   * @return void
   */
    public function __construct()
    {
        parent::__construct('clean-dashboard');
    }

  /**
   * Initialize the module
   * This runs every time WordPress loads if the module is enabled
   */
    protected function init()
    {
    }
}
