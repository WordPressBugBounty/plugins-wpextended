<?php

namespace Wpextended\Modules\AdminColumns;

use Wpextended\Modules\BaseModule;

/**
 * AdminColumns module Bootstrap class
 *
 * Prevents specific usernames from being registered in WordPress,
 * particularly focusing on blocking variations of "admin" to enhance security.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('admin-columns');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
    }
}
