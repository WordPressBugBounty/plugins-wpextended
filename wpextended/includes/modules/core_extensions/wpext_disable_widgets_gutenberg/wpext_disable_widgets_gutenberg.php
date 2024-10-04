<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Disable_Widgets_Gutenberg extends Wp_Extended {

	public function __construct() {
    parent::__construct();
      // Disables the block editor from managing widgets in the Gutenberg plugin.
      add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
      // Disables the block editor from managing widgets.
      add_filter( 'use_widgets_block_editor', '__return_false' );
  }

  public static function init(){
    static $instance = null;
 
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Disable_Widgets_Gutenberg( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  
}

Wp_Extended_Disable_Widgets_Gutenberg::init();