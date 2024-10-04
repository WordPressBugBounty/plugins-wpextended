<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Disable_Video_Uplpading extends Wp_Extended_Export {

  public function __construct() {
    parent::__construct();
    // prohibition video files uploading 
    add_filter( 'upload_mimes', array( $this,'vid_mime_types'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Disable_Video_Uplpading( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  public function vid_mime_types( $mimes ){
    $mimes = array_filter(
			$mimes,
			function ($m) {
        if (0 === strpos($m,'video')) {
					return false;
        }
				return true;
			}
		);
    return $mimes;
  }

}
Wp_Extended_Disable_Video_Uplpading::init(); 