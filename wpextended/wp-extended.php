<?php
/**
 * Plugin Name: WPExtended
 * Plugin URI: https://wordpress.org/plugins/wpextended
 * Description: Many of your favourite plugins rolled up into 1 modular plugin making your WordPress faster and easier to manage.
 * Version: 3.0.10
 * Requires at least: 5.6 
 * Requires PHP:      7.4
 * Author: WP Extended
 * Author URI: https://wpextended.io/
 * Text Domain:  wpextended
 * Domain Path:  /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0
 * Rename this for your plugin and update it as you release new versions.
 */
if ( in_array( 'wpextended-pro/wp-extended.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
 
  deactivate_plugins( 'wpextended-pro/wp-extended.php' );
   
}else{
  define( 'WP_EXTENDED_VERSION', '3.0.10' );
  define( 'WP_EXTENDED_TEXT_DOMAIN', 'wp-extended' );
  define( 'WP_EXTENDED_PATH', plugin_dir_path( __FILE__ ) );
  define( 'WP_EXTENDED_URL', plugins_url( __FILE__ ) );
  define( 'WP_EXTENDED_PLUGIN_BASE', plugin_basename( __FILE__ ) );
  if ( is_multisite() ) {
      $site_url =  network_site_url();
  } else{
      $site_url =  site_url();
  }
  if (!defined('WP_EXTENDED_SITE_URL'))
  define('WP_EXTENDED_SITE_URL', $site_url);
  define('WP_EXTENDED_STORE_URL', 'https://wpextended.io/');  
  define('WP_EXTENDED_ITEM_ID', 548);  
  require_once plugin_dir_path( __FILE__ ) . "/class-wp-extended.php";
  require_once plugin_dir_path( __FILE__ ) . "/admin/class-wp-extended-admin.php";

  
  /**
   * Below code Check if the 'wpext_change_wp_admin_url' module is enabled and the necessary file exists.
   *
   * @since    2.0.2
   * @access   private
   * @var      string    $option   Check the module status.
   */

    $option = get_option('wp-extended-modules', array());
    $check_file = WP_EXTENDED_PATH . "/includes/modules/core_extensions/wpext_change_wp_admin_url/";
    if(isset($option['wpext_change_wp_admin_url']) && $option['wpext_change_wp_admin_url'] == 1 && file_exists($check_file)){
      require_once plugin_dir_path( __FILE__ ) . "/includes/modules/core_extensions/wpext_change_wp_admin_url/wpext_change_wp_admin_url.php";
    } 

  add_action( 'init', 'wp_extended_load_libraries', 1 );
  add_action( 'init', array( 'Wp_Extended', 'init' ), 10 );
  add_action( 'init', array( 'Wp_Extended_admin', 'init' ), 20 );
  add_action( 'init', 'wp_extended_load_modules', 30 );
  
  function wpext_has_permissions($path, $expected_perms) {
      if (is_dir($path)) {
          $perms = substr(decoct(fileperms($path)), -4);
          return $perms == $expected_perms;
      }
      return false;
  }

  if (wpext_has_permissions(plugin_dir_path(__FILE__), '0777') || wpext_has_permissions(plugin_dir_path(__FILE__), '0775') ) {     
      $wpext_image_cache = WP_EXTENDED_PATH . "cache/";
      // Create cache directory inside uploads directory if not exists
      if (!file_exists($wpext_image_cache)) {
        mkdir($wpext_image_cache, 0777, true);
      }
  } else {
      // Create cache directory inside WP_EXTENDED_PATH if it doesn't exist
      $upload_dir = wp_upload_dir();
      $wpext_image_cache = $upload_dir['basedir'] . '/wpextended/cache/';
      if (!file_exists($wpext_image_cache)) {
        mkdir($wpext_image_cache, 0777, true);
      }
  }
  function wp_extended_load_libraries(){
    $wp_extended = Wp_Extended::init();

    $dir = $wp_extended->folder_libraries();

    $libs = $wp_extended->scan_folder( $dir );

    foreach( $libs as $name => $library ) {
      $include_path = $dir . $name . "/" . $name . '.php';

      if( is_file( $include_path ) ) {
        include_once $include_path;
      }
    }

  } // wp_extended_load_libraries

 /**
 * Enqueue the module file based on module status.
 *
 * @since    1.0.0
 * @access   private
 * @var      string    $grouped, $dir   Check the module status.
 */

  function wp_extended_load_modules(){
    $wp_extended = Wp_Extended::init();

    $dir = $wp_extended->folder_modules();

    $grouped = $wp_extended->modules();
    
    foreach( $grouped as $group => $modules ) {

      foreach( $modules as $name => $module ) {

        $include_path = $dir . $group . "/" . $name . "/" . $name . '.php';

        if( $module['status'] && is_file( $include_path ) ) {
          include_once $include_path;
        }
      }
      
    } 
  } // wp_extended_load_modules
}