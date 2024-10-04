<?php

class Wp_Extended_Snippets_old extends Wp_Extended {

  public function __construct() {
    parent::__construct();
    add_action( 'admin_init',   array( $this, 'settings_init') );
    add_action( 'admin_menu',   array( $this, 'admin_init') );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_old' ), 110 );
    add_action( 'wp_head',      array( $this, 'snippets_head_old' ), 1);
    add_action( 'wp_footer',    array( $this, 'snippets_footer_old' ), 1 );    
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Snippets_old( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  
  public function settings_init() {
    
    register_setting( 'wpext-snippets', 'wpext-snippets', array( 'type' => 'string' ) );
  } // settings_init
  
  public function admin_init(){
   $menustatus = get_option('wpext_show_plugin_menu_action');
   $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_snippets', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_snippets'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

   if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
       add_submenu_page(
       '',
      __( 'Code Snippets Old', WP_EXTENDED_TEXT_DOMAIN ), // page title
      __( 'Code Snippets Old', WP_EXTENDED_TEXT_DOMAIN ), // menu title
      'manage_options',
      'wp-extended-snippets-old',
      array( $this, 'page_cb_old' ));
   }else{
      $capability = 'manage_options';
      $slug = 'wp-extended-snippets-old';
      $callback = [ $this, 'page_cb_old'];
      add_submenu_page('', '', '', $capability, $slug, $callback);
      add_rewrite_rule('^wp-extended-snippets-old/?', 'index.php?wp_extended_snippets=1', 'top');
      add_rewrite_tag('%wp_extended_snippets%', '([^&]+)');
   }

  }
  
  public function page_cb_old(){
      // get the value of the setting we've registered with register_setting()
      $codes = get_option('wpext-snippets', '[]' );
      if( empty($codes) ) {
      $codes = '[]';
      }
      ?>
      <div class="container-fluid wpe_brand_header">
         <div class="container p-4 ps-2">
            <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e('WP Extended Code Snippets', WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
         </div>
      </div>

      <div class="container-fluid wp_brand_sub_header wpext_old_snippet">
        <div class="container">
          <div class="row align-items-baseline">
            <div class="col-lg-6 px-1"><p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="<?php echo esc_url('https://wpextended.io/module_resources/insert-snippets/'); ?>" target="_blank" class="wp_brand_sub_header_back_document"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p></div>
            <div class="col-lg-6 wp_brand_sub_header_right mx-lg-0 px-1">
               <!--<a href='<?php // echo admin_url('admin.php?page=wp-extended-snippets'); ?>' class="page-title-action wp-ext-btn-sec"><?php _e('New Snippets', WP_EXTENDED_TEXT_DOMAIN);?></a>
              <button type="button" class="wp-ext-btn-sec wpext-snippet-btn" id="add_new_snippet">
                <?php // _e( 'Add New', WP_EXTENDED_TEXT_DOMAIN );?>
              </button> -->
              <button class="wpext_module_action wp-ext-btn-prim" form="wpext-snippets-codes"><?php _e('Save Changes', WP_EXTENDED_TEXT_DOMAIN);?></button>
            </div>
          </div>
        </div>
      </div>

      <?php if (isset($_GET["settings-updated"]) && $_GET["settings-updated"]) { ?>
        <div class="container wpext-container wpext_success_message_container">
          <div class="row">
            <div class="wpext-success-message rounded-2">
              <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('Your settings have been saved successfully!', WP_EXTENDED_TEXT_DOMAIN ); ?>
            </div>
          </div>
        </div>
      <?php } ?>
     <div class="container wpext-container wpext_code_snippet" id="wp-extended-app">
      <div class="container wpext-container px-0">
        <div class="row">
          <div class="wpext-help-message rounded-2"> 
            <span>ⓘ <?php _e(' Important !', WP_EXTENDED_TEXT_DOMAIN); ?></span>
              <?php _e('This module will be removed soon, please copy and paste your snippets into the ', WP_EXTENDED_TEXT_DOMAIN); ?> 
              <a href="<?php echo admin_url('admin.php?page=wp-extended-snippets'); ?>" class="wp_brand_sub_header_back_document" target="_blank"><?php _e('new version', WP_EXTENDED_TEXT_DOMAIN); ?></a> 
              <?php _e('of code snippets.', WP_EXTENDED_TEXT_DOMAIN); ?>
          </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 gx-5 mb-3 bg-white p-0 rounded-2">
          <div class="container text-dark p-5 border rounded-2">
            <?php require_once plugin_dir_path( __FILE__ ) . "/templates/wp-extend-module-layout.php";?>
          </div>
        </div>
      </div>
    </div>
     <?php
     }

  public function admin_scripts_old(){
    $screen = get_current_screen();
    if( $screen->id == "wp-extended_page_wp-extended-snippets-old" || $screen->id == 'admin_page_wp-extended-snippets-old') {
      wp_enqueue_script( 'wpext-snippets', 
        plugins_url("/js/wpext-snippets.js", __FILE__), 
        array('jquery'), 
        WP_EXTENDED_VERSION, 
        true 
      );       
      wp_enqueue_style( 'wpext_snippets_css', plugin_dir_url( __FILE__ ) . "/css/wpext_snippets.css", array() );
    }
  }
  
  public function snippets_head_old(){
    $this->print_snippets( 'head' );
  }
      
  public function snippets_footer_old() {
    $this->print_snippets( 'footer' );
  }
  
  public function print_snippets( $position ) {
    $code = '';
    $json = get_option( 'wpext-snippets' );
    if(is_string($json)){
      $snippets = (array) json_decode($json, true);
    }else{
      $snippets = [];
    } 
    $allowed_tags = array( 
      'head'  => array(),
      'link'  => array(
          'as'             => true,
          'disabled'       => true,
          'href'           => true,
          'hreflang'       => true,
          'importance'     => true,
          'integrity'      => true,
          'media'          => true,
          'referrerpolicy' => true,
          'rel'            => true,
          'sizes'          => true,
          'title'          => true,
          'type'           => true,
      ),
      'style' => array(
          'type'  => true,
          'media' => true,
          'nonce' => true,
          'title' => true,
      ),
      'script'   => array(
          'async'          => true,
          'crossorigin'    => true,
          'defer'          => true,
          'integrity'      => true,
          'language'       => true, 
          'nomodule'       => true,
          'referrerPolicy' => true,
          'src'            => true,
          'text'           => true,
          'type'           => true,
          'type.module'    => true,
      ),
      'meta'  => array(
        'content' => true,
        'name' => true,
      )
    );
    foreach( $snippets as $item ) {
      if( ( empty($item->position) && $position != 'footer' ) || $item->position != $position ) continue;    
          $code .= $item->code;
    }
    echo wp_kses( $code, $allowed_tags );
  } // print_snippets  
}
Wp_Extended_Snippets_old::init();