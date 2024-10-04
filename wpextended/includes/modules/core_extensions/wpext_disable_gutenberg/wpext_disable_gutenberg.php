<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Disable_Gutenberg extends Wp_Extended {

	public function __construct() {
    parent::__construct();

    add_filter('use_block_editor_for_post', array( $this, 'disable_gutenberg'), 101, 2 ); 
  }

  public static function init(){
    static $instance = null;
 
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Disable_Gutenberg( get_called_class(), WP_EXTENDED_VERSION );
    }

    return $instance;  
  } // init

  public function disable_gutenberg( $use_block_editor, $post  ){
    
    if( $post->post_type == 'post' || $post->post_type == 'page' ) {
      return false;
    }

    return $use_block_editor;
  }
}

Wp_Extended_Disable_Gutenberg::init();