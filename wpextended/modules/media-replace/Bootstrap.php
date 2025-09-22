<?php

namespace Wpextended\Modules\MediaReplace;

use Wpextended\Modules\BaseModule;

/**
 * MediaReplace module Bootstrap class
 *
 * Seamlessly replace an image or file in your Media Library by uploading a new file.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('media-replace');
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
