<?php

/**
 * The functionality of the plugin.
 *
 * @link       http://wearenrcm.com
 * @since      1.0.0
 *
 * @package    wp_extended
 * @subpackage wp_extended/admin
 */

/**
 * The functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the stylesheet and JavaScript.
 *
 * @package    wp_extended
 * @author     NRCM Web Design <hello@wearenrcm.com>
 */
class Wp_Extended {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $wp_extended    The ID of this plugin.
	 */
	public $wp_extended;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	public $version;


  const OPTION_MODULES = 'wp-extended-modules';
  const OPTION_ALL_MODULES = 'wp-extended-all-modules';   


	public function __construct( $wp_extended = null, $version = null ) {

		$this->wp_extended = !empty( $wp_extended ) ? $wp_extended : get_called_class();
		$this->version = !empty( $version ) ? $version : WP_EXTENDED_VERSION;
	}


  public static function init(){
    static $instance = null;
 
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended( get_called_class(), WP_EXTENDED_VERSION );
      self::translation();
    }

    return $instance;  
  } // init  


  public static function folder_libraries(){
    return WP_EXTENDED_PATH . "includes/libraries/";
  } // folder_libraries

  public static function folder_modules(){
    return WP_EXTENDED_PATH . "includes/modules/";
  } // folder_modules

  public static function translation(){  
 
    $locale = apply_filters( 'plugin_locale', determine_locale(), WP_EXTENDED_TEXT_DOMAIN );

    if( !file_exists( WP_EXTENDED_PATH . 'languages/' . $locale . ".mo" ) 
        && strlen( $locale ) > 2 
        && file_exists( WP_EXTENDED_PATH . 'languages/' . substr( $locale, 0, 2 ) . ".mo" )
        ) {
      $locale = substr( $locale, 0, 2 );
    }

    $l = load_textdomain( WP_EXTENDED_TEXT_DOMAIN,  WP_EXTENDED_PATH . 'languages/' . $locale . ".mo" );
  } // translation


  public static function groups(){
    return self::scan_folder();
  } // groups 


  public static function modules(){
    
    $dir = self::folder_modules();

    $groups = self::scan_folder( $dir );

    $modules = array();

    foreach( $groups as $group ) {
      $modules[ $group['dirname'] ] = self::scan_folder( $dir . $group['dirname'] . "/" );
    }

    return $modules;
  } // modules


  /*
  * Build associate array of modules or groups
  * slug => info
  */
  public static function scan_folder( $dir = WP_EXTENDED_PATH . "includes/modules/" ){
 
    if ( !($handle = opendir( $dir )) ) {
      return false;
    }

    $modules = array();

    while( false !== ( $entry = readdir($handle) ) ) {
  
      if( $entry == '.' || $entry == '..' || !is_dir($dir . $entry ) ) {
        continue;
      }

      $module = array();
  
  
      $infoFile = $dir . $entry . "/info.json";
  
      if( is_file( $infoFile ) ) {
        $content = file_get_contents( $infoFile );
        $data = json_decode( $content, true );

        if( $data ) {
          $module = $data;
        }
      }

      if( empty($module[ 'name' ]) ) {
        $module[ 'name' ] = $entry;
      }

      if( empty($module[ 'dirname' ]) ) {
        $module[ 'dirname' ] = $entry;
      }

      if( empty($module[ 'path' ]) ) {
        $module[ 'path' ] = $dir . $entry; 
      }

      $module[ 'status' ] = self::module_status( $entry );
  
      $modules[ $entry ] = $module;
    }
  
    closedir($handle);

    uasort( $modules, array( get_called_class(), '_modules_sort' ) );
  
    return $modules;
  } // scan_folder


  /*
   * Get single module info 
   */
  public function module( $module ){
    try {

      $the_module = null;
      $grouped = self::modules();
      foreach( $grouped as $group => $modules ) {
        if( isset( $modules[ $module ] ) ) {
          $the_module = $modules[ $module ];

          $the_module['group'] = $group;

          break;
        }
      }

      return $the_module;
    }
    catch( \Exception $e ) {
      return false;
    }
  } // module


  /*
   * Function used to sort modules by order 
   */
  private static function _modules_sort($a, $b){
    $order_a = !empty( $a['order'] ) ? +$a['order'] : 10000;
    $order_b = !empty( $b['order'] ) ? +$b['order'] : 10000;
  
    if( $order_a > $order_b ) {
      return 1;
    }
    else if ( $order_a < $order_b ) {
      return -1;
    }
    
    return 0;
  } // _modules_sort

  /**
   * Get Module status
   */
  public static function module_status( $module ){
    
    if( !function_exists( 'get_option' ) ) {
      return null;
    }

    $option = get_option( self::OPTION_MODULES, array() );

    if( !isset( $option[ $module ] ) ) {
      return null;
    }

    return (bool) $option[ $module ];

  } // module_status
}