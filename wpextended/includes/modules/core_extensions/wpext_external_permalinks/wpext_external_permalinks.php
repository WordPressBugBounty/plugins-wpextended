<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_External_Permalink extends Wp_Extended_Export {
const WPEXTEND_PERMALINK = 'wpext-external-permalink-url';
  public function __construct() {
    parent::__construct();
    
    add_action( 'add_meta_boxes', array( $this, 'wpext_add_external_permalink_meta_box' ), 10, 2 );
    add_action( 'save_post', array( $this, 'wpext_save_external_permalink' ) );
    add_filter( 'page_link', array( $this, 'wpext_use_external_permalink_for_pages' ), 20, 2 );
    add_filter( 'post_link', array( $this, 'wpext_use_external_permalink_for_posts' ), 20, 2 );
    add_filter( 'post_type_link', array( $this, 'wpext_use_external_permalink_for_posts' ), 20, 2 );
    add_action( 'wp', array( $this, 'wpext_redirect_to_external_permalink' ) );
    add_action('admin_menu', array( $this, 'wpext_external_permalink_menu'), 100 );
    add_action('admin_init', array( $this, 'wpext_external_permalink_option_settings') );
    add_action('admin_enqueue_scripts', array( $this, 'wpext_admin_external_permalink_scripts' ), 99999 );
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_External_Permalink( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init


  public function wpext_external_permalink_menu(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
     $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
        if (array_key_exists('wpext_external_permalinks', $wpext_admin_menu_favorite)) {
          if ($wpext_admin_menu_favorite['wpext_external_permalinks'] == 'true') {
            $flagfavorite = true;
          }
        }
    }
      if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
          add_submenu_page( 
          'wp-extended', 
          __('External Permalinks', WP_EXTENDED_TEXT_DOMAIN), 
          __('External Permalinks', WP_EXTENDED_TEXT_DOMAIN), 
          'manage_options', 
          'wp-extended-external_permalink', 
          array( get_called_class(), 'wpext_external_permalink_interface' ),
          null
        );
      }else{
         $capability = 'manage_options';
         $slug = 'wp-extended-external_permalink';
         $callback = [ get_called_class(), 'wpext_external_permalink_interface'];
         add_submenu_page('', '', '', $capability, $slug, $callback);
         add_rewrite_rule('^wp-extended-external_permalink/?', 'index.php?wp_extended_external_permalink=1', 'top');
         add_rewrite_tag('%wp_extended_external_permalink%', '([^&]+)');
      }
  }
  
  public function wpext_admin_external_permalink_scripts(){
    $screen = get_current_screen();
    if( $screen->id == "wp-extended_page_wp-extended-external_permalink" ) {
      wp_enqueue_script( 'wp-extended_jquery_wpext_external_permalink', plugin_dir_url( __FILE__ ) . "js/wpext_external_permalink.js", array(), WP_EXTENDED_VERSION );
    }
  }

  public function wpext_external_permalink_option_settings(){
    register_setting( self::WPEXTEND_PERMALINK, self::WPEXTEND_PERMALINK,  array( 'type' => 'array' ) );
  }
  /**
   * Insert an external permalink meta box for enabled content types
   * 
   */
  public function wpext_add_external_permalink_meta_box( $post_type, $post )
  { 
    $config = get_option(self::WPEXTEND_PERMALINK);
    $types_array = array( 'attachment' , 'elementor_library' );
    $types = get_post_types( ['public'   => true ], 'objects' );
    foreach ($types as $type ) {
      if(!in_array( $type->name, $types_array )) {
        if(isset( $config[$type->name]) == '0n' ) {
        add_meta_box('wpext-external-permalink', 'External Permalink', array( $this, 'wpext_external_permalink_meta_box' ), array($type->name), 'side', 'high' );
        }     
      }
    }  
  }

  /**
   * Render External Permalink content types
   *
   */
  public function wpext_external_permalink_meta_box( $post )
  {
    ?>
  <div class="external-permalink-input">
    <input name="<?php echo  esc_attr( 'wpext_external_permalink' ) ; ?>" class="large-text" id="<?php   echo  esc_attr( 'external_permalink' ) ; ?>" 
     type="text" value="<?php echo esc_url( get_post_meta( $post->ID, '_links_to', true ) ) ; ?>" placeholder="https://" />
    <div class="external-permalink-input-description">
      <?php _e('Keep empty to use the default WordPress permalink. External permalink will overide the default slug.',WP_EXTENDED_TEXT_DOMAIN ); ?></div>
    <?php wp_nonce_field( 'wpext_external_permalink_' . $post->ID, 'wpext_external_permalink_nonce', false,  true ); ?>
  </div>
  <?php 
  }

  /**
   * Replace WordPress default permalink with external permalink for posts and custom post types
   *
   */
    public function wpext_use_external_permalink_for_posts( $permalink, $post )
    {
        $external_permalink = get_post_meta( $post->ID, '_links_to', true );
        
        if ( !empty($external_permalink) ) {
            $permalink = $external_permalink;
            if ( !is_admin() ) {
                $permalink = $permalink . '#new_tab';
            }
        }
        
        return $permalink;
    }
    /**
     * Store the external permalink
     *
     */
    public function wpext_save_external_permalink( $post_id )
    {
        // Only proceed if nonce is verified        
        if ( isset( $_POST['wpext_external_permalink'] ) && wp_verify_nonce( $_POST['wpext_external_permalink_nonce'], 'wpext_external_permalink_' . $post_id ) ) {
            // Get the value of external permalink from input field
            $external_permalink = ( isset( $_POST['wpext_external_permalink'] ) ? esc_url_raw( trim( $_POST['wpext_external_permalink'] ) ) : '' );
            
            if ( !empty($external_permalink) ) {
                update_post_meta( $post_id, '_links_to', $external_permalink );
            } else {
                delete_post_meta( $post_id, '_links_to' );
            }
        }    
    }
    
    /**
     * Replace WordPress default permalink into external permalink for pages
     *
     */
    public function wpext_use_external_permalink_for_pages( $permalink, $post_id )
    {
        $external_permalink = get_post_meta( $post_id, '_links_to', true );
        if ( !empty($external_permalink) ) {
            $permalink = $external_permalink;
        }
        return $permalink;
    }

    /** 
     * Redirect cpt to external permalink if it's loaded directly from the WP default permalink
     *
     */

    public function wpext_redirect_to_external_permalink()
    {
        global $post ;
        // If not on/loading the single page/post URL, do nothing
        if ( !is_singular() ) {
            return;
        }
        $external_permalink = get_post_meta( $post->ID, '_links_to', true );
        
        if ( !empty($external_permalink) ) {
             wp_redirect(esc_url($external_permalink), 302);
            // temporary redirect
            exit;
        }
    }

    public static function wpext_external_permalink_interface(){
      require_once plugin_dir_path( __FILE__ ) . "templates/wp-extend-module-layout.php"; 
    }

}
Wp_Extended_External_Permalink::init(); 