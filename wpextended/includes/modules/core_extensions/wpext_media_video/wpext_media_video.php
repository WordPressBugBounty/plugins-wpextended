<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Disable_Video_Uploading extends Wp_Extended_Export {

  public function __construct() {
    parent::__construct();
    // Prohibition of video file uploads
    add_filter( 'upload_mimes', array( $this, 'vid_mime_types' ) );
  }

  public static function init() {
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Disable_Video_Uploading( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  }
  /**
   * Disable video MIME types from being uploaded.
   *
   * @param array $mimes Existing MIME types.
   * @return array Filtered MIME types.
   */
  public function vid_mime_types( $mimes ) {
    // Filter out video MIME types
    $mimes = array_filter(
      $mimes,
      function ( $m ) {
        return !str_starts_with($m, 'video'); // Using str_starts_with for clarity
      }
    );
    return $mimes;
  }
}

Wp_Extended_Disable_Video_Uploading::init();