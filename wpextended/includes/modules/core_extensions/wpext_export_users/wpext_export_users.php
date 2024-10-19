<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Export_Users extends Wp_Extended_Export {

  public $formats = array( 'csv' );
  public $action = 'wpext-export-user';
  public $action_download = 'wpext-export-users-download';

  public function __construct() {
    parent::__construct();
    // add export actions for individual user row in users screen
    add_filter( "user_row_actions",           array( $this, "add_export_action" ), 10, 2 );
    add_filter( "bulk_actions-users",         array( $this, 'add_bulk_action'), 10, 1 );
    add_filter( "handle_bulk_actions-users",  array( $this, 'do_bulk_action'), 10, 3 );
    add_action( "wp_ajax_" . $this->action_download, array( $this, 'download_file_ajax' ) );
    add_action( "wp_ajax_" . $this->action,  array( $this, 'download_user_ajax' ) );
    add_action( 'show_user_profile', array( $this, 'profile_button'), 1, 1 );
    add_action( 'edit_user_profile', array( $this, 'profile_button'), 1, 1 );
    // check, clear and apply filter to formats
    $this->check_formats();
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Export_Users( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function get_items( $ids ){
    if( empty($ids ) ) {
      return null;
    }
    // get users
    $params = array( 
      'include' => $ids, 
      'number'  => -1, 
    );
    $params = apply_filters( 'wpext-export-users-params', $params );
    $users = get_users( $params );
    if( empty($users) ) {
      return null;
    }
    $asArray = array();
    foreach( $users as $user ) {
      $user_array = $user->to_array();
      $asArray[] = $user_array;
    }
    return $asArray;
  } // get_items
  public function download_user_ajax(){
    check_ajax_referer('wpext-ajax-nonce', 'wpext_nonce');
    if (!current_user_can('export')) {
        wp_send_json_error(__('You do not have permission to export users.', WP_EXTENDED_TEXT_DOMAIN));
        wp_die();
    }
    try {
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $format = !empty($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';

      $user = get_userdata( $id );
      if( !$user ) {
        throw new \Exception( "User not found" );
      }
      $items = $this->get_items( array($id) );
      if( !$items ) {
        throw new \Exception( "No items to export" );
      }
      if( !method_exists( $this, "export_{$format}" ) ) {
        throw new \Exception( "Format is not supported" );
      }
      $filepath = $this->{"export_{$format}"}( $items );
      if( !$filepath ) {
        throw new \Exception( "Export failed" );
      }
      $filename = basename( $filepath );
      $this->download_file( $filename );
    }
    catch( \Exception $e ) {
      wp_send_json( "Not Found: " . $e->getMessage() );
    }
    wp_die();
  } // download_user_ajax

  public function profile_button( $user ) {
    if (!current_user_can('edit_user', $user->ID)) {
          return;
    }
    $actions = $this->add_export_action( array(), $user );
    ?>
    <h2><?php _e( 'Export User', WP_EXTENDED_TEXT_DOMAIN );?></h2>
    <table class="form-table" role="presentation">
      <tbody>
        <tr>
          <th scope="row"><?php _e('Formats', WP_EXTENDED_TEXT_DOMAIN); ?></th>
          <td>
            <?php
             foreach ($actions as $action) {
                  echo "<p>" . $action . "</p>"; // Escape action output for safe display 
              }
            ?>
          </td>
        </tr>
      </tbody>
    </table>
    <?php
  } // profile_button
}
Wp_Extended_Export_Users::init(); 