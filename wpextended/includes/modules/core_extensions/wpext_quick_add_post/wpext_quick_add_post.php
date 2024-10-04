<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Quick_Add_Post extends Wp_Extended {
  public function __construct() {
    parent::__construct();
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 110 );
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Quick_Add_Post( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function admin_scripts(){
    $screen = get_current_screen();
    wp_register_script( 'wpext-quick-add-post', 
      plugins_url("/js/wpext-quick-add-post.js", __FILE__), 
      array('wp-element', 'wp-edit-post','wp-plugins', 'wp-i18n', 'wpext-edit-main-button'), 
      WP_EXTENDED_VERSION, 
      true 
    ); 
    if( $screen->base == 'post' && in_array( $screen->id, array('post', 'page') ) ) {
      wp_enqueue_script( 'wpext-quick-add-post' );
    }
  }
}
Wp_Extended_Quick_Add_Post::init();