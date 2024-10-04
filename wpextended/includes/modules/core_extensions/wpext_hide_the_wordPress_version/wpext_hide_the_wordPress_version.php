<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Hide_WordPress_Version extends Wp_Extended {

  public function __construct() {
    parent::__construct();

    // add version filter
    add_filter('the_generator', array($this, 'wpext_remove_wp_version'));

  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Hide_WordPress_Version( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  
  /**
   * Manipulate  the wp default version functionality via filter   
   * 
   * */

  public function wpext_remove_wp_version(){
    return '';
  }

}
Wp_Extended_Hide_WordPress_Version::init(); 