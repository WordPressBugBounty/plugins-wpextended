<?php

namespace Wpextended\Modules\HideAdminNotices;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('hide-admin-notices');
    }

    /**
     * Initialize the module functionality.
     */
    public function init(): void
    {
    }
}
