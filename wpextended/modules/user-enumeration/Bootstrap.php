<?php

namespace Wpextended\Modules\UserEnumeration;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('user-enumeration');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('template_redirect', array($this, 'blockUserEnumeration'));
    }

    /**
     * Block user enumeration attempts
     *
     * @return void
     */
    public function blockUserEnumeration()
    {
        if (!is_author()) {
            return;
        }

        $redirect_url = home_url('/');
        wp_redirect($redirect_url, 301);
        exit;
    }
}
