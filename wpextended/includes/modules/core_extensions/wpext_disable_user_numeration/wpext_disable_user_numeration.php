<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Disable_User_Numeration extends Wp_Extended {

  public function __construct() {
    parent::__construct();
   
    add_action('template_redirect', array($this, 'wpext_block_user_enumeration_attempts'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Disable_User_Numeration( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  public function wpext_block_user_enumeration_attempts(){
    if (is_author()) {
        $redirect_url = home_url('/');
        wp_redirect($redirect_url, 301);
        exit;
    }
  }

}
Wp_Extended_Disable_User_Numeration::init(); 