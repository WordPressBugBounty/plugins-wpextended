<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Block_the_Name_Admin extends Wp_Extended {

  public function __construct() {
    parent::__construct();
    add_filter( 'registration_errors', array($this,'wpext_block_the_username_admin'), 10, 3 );
    add_action( 'admin_init', array($this,'wpext_check_the_current_username'));
    add_action( 'wp_ajax_wpext_change_admin_name', array($this, 'wpext_change_admin_name'));
    add_action( 'admin_enqueue_scripts', array( $this, 'wpext_tidy_nav_admin_scripts' ), 110 );
  }
  public function wpext_block_the_username_admin($errors, $sanitized_user_login, $user_email){
    if ( $sanitized_user_login == 'admin' ) {
      $errors->add( 'username_unavailable', __( 'Sorry, that username is not allowed.',WP_EXTENDED_TEXT_DOMAIN ) );
    }
    return $errors;
  }

  public function wpext_check_the_current_username() {
    $admin_user = get_user_by('login', 'admin');
    $user = wp_get_current_user();
    $roles = is_array($user->roles) ? $user->roles : array();
    // Validate that the current user is an administrator and the 'admin' user exists
    if ($admin_user && isset($roles[0]) && sanitize_text_field($roles[0]) === 'administrator') {
        add_action('admin_notices', array($this, 'wpext_admin_notice'));
    }
  }

  public function wpext_admin_notice() {
        $class = 'notice notice-error';
        $message = __( "Detected username as 'admin'. We recommend changing username for security purposes.", WP_EXTENDED_TEXT_DOMAIN );
        printf(
            '<div class="%1$s wpext_change_admin"><p><strong>%2$s</strong> %3$s</p><input type="text" name="change_username" id="change_username" class="form-control" placeholder="%4$s"><input type="submit" name="change_user" id="change_user" value="%5$s" class="button button-primary"><p class="user_validation"></p></div>',
            esc_attr( $class ),
            esc_html__('WP Extended', WP_EXTENDED_TEXT_DOMAIN),
            esc_html($message),
            esc_attr(__('Enter new username', WP_EXTENDED_TEXT_DOMAIN)),
            esc_attr(__('Change', WP_EXTENDED_TEXT_DOMAIN))
        );
    }

  public static function wpext_tidy_nav_admin_scripts(){
    wp_enqueue_script( 'wpext-admin-admin-name', 
      plugins_url("js/wpext_rename_admin.js", __FILE__), 
      array(), 
      filemtime( plugin_dir_path( __FILE__ ) . "js/wpext_rename_admin.js" ),
      true 
    );
    $rename_admin  = array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'ajax_nonce' => wp_create_nonce( 'wpext-nonce' ),
      );
      wp_localize_script( 'wpext-admin-admin-name', 'change_ajax_obj', $rename_admin);
    } // admin_scripts 

  public function wpext_change_admin_name() {
        global $wpdb;

        // Verify nonce
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), 'wpext-nonce' ) ) {
            wp_send_json_error( array( 'error' => __( 'Invalid nonce!', WP_EXTENDED_TEXT_DOMAIN ) ) );
        }

        // Capability check: Allow only administrators
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'error' => __( 'You do not have sufficient permissions to perform this action.', WP_EXTENDED_TEXT_DOMAIN ) ) );
        }

        // Sanitize and validate new username
        $new_username = isset($_POST['username']) ? sanitize_user(trim($_POST['username']), true) : '';

        // Check if the new username is empty
        if ( empty($new_username) ) {
            wp_send_json_error( array( 'error' => __( 'Username cannot be empty.', WP_EXTENDED_TEXT_DOMAIN ) ) );
        }

        $admin_user = get_user_by('login', 'admin');

        if ( !$admin_user ) {
            wp_send_json_error( array( 'error' => __( 'No user has the username "admin". Nothing to update.', WP_EXTENDED_TEXT_DOMAIN ) ) );
            return;
        }

        if ( username_exists( $new_username ) ) {
            wp_send_json_error( array( 'error' => sprintf( __( 'The new username "%s" already exists. Please choose a different one.', WP_EXTENDED_TEXT_DOMAIN ), esc_html($new_username) ) ) );
            return;
        }

        $result = $wpdb->update(
            $wpdb->users,
            array( 'user_login' => $new_username ),
            array( 'ID' => intval($admin_user->ID) )
        );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Username changed successfully. Please log out and log in with new username.', WP_EXTENDED_TEXT_DOMAIN ) ) );
        } else {
            wp_send_json_error( array( 'error' => __( 'Username change failed.', WP_EXTENDED_TEXT_DOMAIN ) ) );
        }
        die;
    }
}

new Wp_Extended_Block_the_Name_Admin();




 
