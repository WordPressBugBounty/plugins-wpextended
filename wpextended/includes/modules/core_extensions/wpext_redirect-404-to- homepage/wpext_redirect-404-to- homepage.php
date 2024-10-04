<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Redirect_404_to_Home_Page extends Wp_Extended {

  public function __construct() {
    parent::__construct();
    add_filter( 'wp', array( $this, 'wpext_redirect_404_to_homepage' ));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Redirect_404_to_Home_Page( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  /**
   * Redirect 404 to homepage
   *
   */

  public function wpext_redirect_404_to_homepage() {
    if ( ! is_404() || is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
      return;
    } else {
    header( 'HTTP/1.1 301 Moved Permanently');
    header( 'Location: ' . home_url() );
    exit();
  }

  }
}
Wp_Extended_Redirect_404_to_Home_Page::init(); 