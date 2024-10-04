<?php

class Wp_Extended_User_Sections extends Wp_Extended {

  static $titles_map = array();

  public function __construct() {
    parent::__construct();

    add_action( 'admin_init',   array( $this, 'settings_init') );
    add_action( 'admin_menu',   array( $this, 'admin_init') );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 110 );
    add_action( 'user_edit_form_tag', array( $this, "add_title_filter" ) );
    add_filter( 'pre_update_option_wpext-user-sections', array( $this, 'invert' ), 10, 3 );
    add_action( 'admin_footer', array( $this,'user_profile_fields'));
     
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_User_Sections( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public static function admin_scripts(){
    wp_enqueue_script( 'wpext-user-sections', 
      plugins_url("js/wpext-user-sections.js", __FILE__), 
      array(), 
      WP_EXTENDED_VERSION,
      true 
    );
    $screen = get_current_screen();
  } // admin_scripts

  public function settings_init() {
    
    register_setting( 'wpext-user-sections', 'wpext-user-sections', array( 'type' => 'array' ) );
    register_setting( 'wpext-user-sections', 'wpext-user-sections-toggle', array( 'type' => 'boolean' ) );

  } // settings_init

  // function to clean profile
  public function user_profile_fields(){
    $user_option = get_option( 'wpext-user-sections');
    wp_add_inline_script( 'wpext-user-sections', 'const wpExtUserSections = ' . json_encode( $user_option ), 'before' );
  }

  public function admin_init(){

     $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_user_sections', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_user_sections'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

      if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
        add_submenu_page(
        'wp-extended',
        __( 'Clean Profiles', WP_EXTENDED_TEXT_DOMAIN ), // page title
        __( 'Clean Profiles', WP_EXTENDED_TEXT_DOMAIN ), // menu title
        'manage_options',
        'wp-extended-user-sections',
        array( $this, 'page_cb' )
      );
      }else{
         $capability = 'manage_options';
         $slug = 'wp-extended-user-sections';
         $callback = [ $this, 'page_cb'];
         add_submenu_page('', '', '', $capability, $slug, $callback);
         add_rewrite_rule('^wp-extended-user-sections/?', 'index.php?wp_extended_user_sections=1', 'top');
         add_rewrite_tag('%wp_extended_user_sections%', '([^&]+)');
      }
  } // admin_init

  public static function page_cb(){
    require_once plugin_dir_path( __FILE__ ) . "templates/wp-extend-module-layout.php"; 
  }
  
  public function add_title_filter(){
    add_filter( 'gettext', array( $this, 'filter_title' ), 999, 3 );
    add_action( 'admin_footer', array( $this, 'print_titles_map' ) );
  } 

  public function filter_title( $translation, $text, $domain ){
    if( !isset( self::$titles_map[ $translation ] ) ) {
      self::$titles_map[ $translation ] = $text;
    }

    return $translation;
  } // filter_title

  public function print_titles_map(){  
    echo "<script type='application/json' id='wpext-user-sections-titles'>", json_encode( self::$titles_map ), "</script>";
  }

  public function invert( $value, $old_value, $option ){
    return $value;
  } // invert
  
}

Wp_Extended_User_Sections::init();