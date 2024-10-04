<?php

if (! defined('ABSPATH') ) {
    die();
}

class Wp_Extended_Disable_Xml_Rcp extends Wp_Extended
{

    public function __construct()
    {
        parent::__construct();
        add_filter('xmlrpc_enabled', '__return_false');
    }

}
new Wp_Extended_Disable_Xml_Rcp();
