<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Svg_Upload extends Wp_Extended_Export {

  public function __construct() {
    parent::__construct();
    // add svg icons
    add_filter( 'wp_check_filetype_and_ext', array( $this, 'wpext_svg_upload'),10, 4);
    add_filter( 'upload_mimes', array( $this,'wpext_cc_mime_types'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Svg_Upload( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  public function wpext_svg_upload($data, $file, $filename, $mimes){
   $filetype = wp_check_filetype( $filename, $mimes );
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
  }
  public function wpext_cc_mime_types( $mimes ){
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
  }

}
Wp_Extended_Svg_Upload::init(); 