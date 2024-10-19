<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Last_Login_Status extends Wp_Extended_Export {

  public function __construct() {
    parent::__construct();
      add_action( 'wp_login', array( $this, 'wpext_login_datetime' ) );
      add_filter( 'manage_users_columns',  array( $this,'wpext_add_last_login_status_column' ));
      add_filter( 'manage_users_custom_column',  array( $this, 'wpext_last_login_info' ), 10, 4 );
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Last_Login_Status( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  /**
   * Log date time when a user last logged in successfully
   *
   */
  public function wpext_login_datetime( $user_login ) {

    $user = get_user_by( 'login', $user_login ); // by username 
    if ( $user ) {
     update_user_meta( $user->ID, 'wpext_user_last_login_status', time() );
    }
  }

  /**
   * Add Last Login column to users list table
   *
   */
  public function wpext_add_last_login_status_column( $columns ) {

    $columns['wpext_last_login'] = __('Last Login', WP_EXTENDED_TEXT_DOMAIN);
    return $columns;

  }
  
  /**
   * Display user last login info in the last login column
   */
  public function wpext_last_login_info( $output, $column_name, $user_id ) {
    if ( 'wpext_last_login' === $column_name ) {
      // Validate user ID
      if ( !empty($user_id) && is_numeric($user_id) && $user_id > 0 ) {
        $last_login_timestamp = get_user_meta( $user_id, 'wpext_user_last_login_status', true );

        if ( ! empty( $last_login_timestamp ) ) {
          $wpext_last_login = (int) $last_login_timestamp;
          if ( function_exists( 'wp_date' ) ) {
            $date_format = sanitize_text_field( get_option('date_format') );
            $time_format = sanitize_text_field( get_option('time_format') );
            $output = date( $date_format . ' ' . $time_format, $wpext_last_login );
          } else {
            $output  = date_i18n( 'M j, Y H:i A', $wpext_last_login );
          }
        } else {
          $output = __('No data yet', WP_EXTENDED_TEXT_DOMAIN);
        }
      } else {
        // Fallback for invalid user ID
        $output = __('Invalid user ID', WP_EXTENDED_TEXT_DOMAIN);
      }
    }
    return esc_html( $output ); // Escape the output for safety
  }
}
Wp_Extended_Last_Login_Status::init();