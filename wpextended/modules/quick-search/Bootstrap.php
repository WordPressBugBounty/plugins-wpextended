<?php

namespace Wpextended\Modules\QuickSearch;

use Wpextended\Modules\BaseModule;

/**
 * Bootstrap class for the QuickSearch module
 *
 * Provides quick search functionality throughout the WordPress admin interface
 * to help users find content, settings, and features more efficiently.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('quick-search');
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
