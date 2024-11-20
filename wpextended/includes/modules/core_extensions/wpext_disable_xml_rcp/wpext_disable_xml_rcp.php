<?php

if (! defined('ABSPATH') ) {
    die();
}

if (!class_exists('Wp_Extended_Disable_Xml_Rcp')) {

    class Wp_Extended_Disable_Xml_Rcp extends Wp_Extended
    {
        private static $instance = null;

        private function __construct()
        {
            parent::__construct();

            // Disable XML-RPC
            add_filter('xmlrpc_enabled', '__return_false');
        }

        /**
         * Initialize the singleton instance of the class.
         *
         * @return self
         */
        public static function init()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

    // Initialize the class
    Wp_Extended_Disable_Xml_Rcp::init();
}

