<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Hide_WordPress_Version extends Wp_Extended {

    public function __construct() {
        parent::__construct();

        // Add a filter to remove the WordPress version
        add_filter('the_generator', array($this, 'wpext_remove_wp_version'));
    }

    public static function init() {
        static $instance = null;

        if ( is_null( $instance ) ) {
            $instance = new Wp_Extended_Hide_WordPress_Version();
        }

        return $instance;  
    }

    /**
     * Remove the WordPress version number from the generator meta tag.
     *
     * @return string Empty string to remove the version.
     */
    public function wpext_remove_wp_version() {
        return ''; // Return an empty string to hide the version
    }
}
// Initialize the class
Wp_Extended_Hide_WordPress_Version::init();
