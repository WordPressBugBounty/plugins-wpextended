<?php
if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Hide_Admin_Bar extends Wp_Extended {
  const WPEXT_HIDEADMIN_BAR = 'wpext-hide_admin_bar';
  public function __construct() {
    parent::__construct();
     add_action( 'admin_menu', array( get_called_class(), 'wpext_hide_admin_bar'), 100 );
     add_action( 'admin_init', array( $this, 'wpext_hide_admin_bar_configuration') );
     add_action( 'init', array( $this,'wpext_hide_admin_bar_rolewise_from_top') ,'9999');
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Hide_Admin_Bar( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function wpext_hide_admin_bar_configuration(){
    register_setting( self::WPEXT_HIDEADMIN_BAR, self::WPEXT_HIDEADMIN_BAR,  array( 'type' => 'array' ) );
  }
  public static function wpext_hide_admin_bar(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_hide_admin_bar', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_hide_admin_bar'] == 'true') {
          $flagfavorite = true;
        }
      }
    }
    
    if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
      add_submenu_page( 
        'wp-extended', __('Hide Admin Bar', WP_EXTENDED_TEXT_DOMAIN), __('Hide Admin Bar', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options',  'wp-extended-hide-adminbar',  array( get_called_class(), 'settings_hide_admin_bar' ),
        null
      );
    }else{
       $capability = 'manage_options';
       $slug = 'wp-extended-hide-adminbar';
       $callback = [ get_called_class(), 'settings_hide_admin_bar'];
       add_submenu_page('', '', '', $capability, $slug, $callback);
       add_rewrite_rule('^wp-extended-hide-adminbar/?', 'index.php?wp_extended_hide_adminbar=1', 'top');
       add_rewrite_tag('%wp_extended_hide_adminbar%', '([^&]+)');
    }
  }

  public static function settings_hide_admin_bar(){
    require_once plugin_dir_path( __FILE__ ) . "templates/wp-extend-module-layout.php"; 
  }

  /**
   * 
   * Apply filter to hide adminbar as per user role
   * 
   * 
   * */
  public function wpext_hide_admin_bar_rolewise_from_top(){
    $user_role_data = get_option(self::WPEXT_HIDEADMIN_BAR); 
    if(!empty($user_role_data)){
       $user = wp_get_current_user();
       $roles = ( array ) $user->roles;
        foreach($user_role_data as $key => $urole){
          if(!empty($roles[0]) && $urole == $roles[0]){
            add_filter('show_admin_bar', '__return_false');
          }
        } 
      }
  }
}
Wp_Extended_Hide_Admin_Bar::init();