<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Pixel_Tag_Manager extends Wp_Extended {
  const WPEXT_GOOGLE_ANALITIC = 'wpext-pixel-tag';

  public function __construct() {
    parent::__construct();
     add_action( 'admin_menu', array( get_called_class(), 'wpext_pixel_tag_manager_menu'), 100 );
     add_action( 'admin_enqueue_scripts', array( $this, 'wpext_pixel_tag_manager_script' ), 110 );
     add_action( 'admin_init',   array( $this, 'wpext_pixel_tag_settings_init') );
     add_action( 'wp_head', array($this, 'wpext_pixel_tag_manager_wp_header'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Pixel_Tag_Manager( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public static function wpext_pixel_tag_manager_menu(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_pixel_tag_manager', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_pixel_tag_manager'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

    if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
      add_submenu_page( 
        'wp-extended', __('Pixel Tag Manager', WP_EXTENDED_TEXT_DOMAIN), __('Pixel Tag Manager', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options', 'wp-extended-pixel-tag-manager', 
        array( get_called_class(), 'settings_pixel_tag_manager'),
        null );
    }else{
      $slug = 'wp-extended-pixel-tag-manager';
      $capability = 'manage_options';
      $callback = [get_called_class(), 'settings_pixel_tag_manager'];
      add_submenu_page('', '', '', $capability, $slug, $callback);
      add_rewrite_rule('^wp-extended-pixel-tag-manager/?', 'index.php?wp_extended_pixel_tag_manager=1', 'top');
      add_rewrite_tag('%wp_extended_pixel_tag_manager%', '([^&]+)');
    }
  }
  
  public static function wpext_pixel_tag_manager_script(){
    $screen = get_current_screen();
    if( $screen->id == "wp-extended_page_wp-extended-pixel-tag-manager" ) {
      wp_enqueue_style( 'wp-extended-pixel-tag.min.css', plugin_dir_url( __FILE__ ) . "css/wpext_pixel_tag.css", array(), WP_EXTENDED_VERSION );         
    }
  }
  public function wpext_pixel_tag_settings_init(){
    register_setting( self::WPEXT_GOOGLE_ANALITIC, self::WPEXT_GOOGLE_ANALITIC, array( 'type' => 'array' ) );
  }

  public static function settings_pixel_tag_manager(){  
    require_once plugin_dir_path( __FILE__ ) . "templates/wp-extend-module-layout.php"; 
  } 

  public function wpext_pixel_tag_manager_wp_header(){ 
    $getstorage = get_option(self::WPEXT_GOOGLE_ANALITIC);
    /*Google analitic pixel code*/
      if(!empty($getstorage['wpxt-google-analitic'])){  ?>
      <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $getstorage['wpxt-google-analitic']; ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag("js", new Date());
          gtag("config", "<?php echo $getstorage['wpxt-google-analitic']; ?>");
        </script> 
      <?php }  
    /*Google analitic pixel code end here*/
    /*Facebook pixel code*/
      if(!empty($getstorage['wpext-facebook'])){ ?>
        <script>
          !function(f,b,e,v,n,t,s)
          {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
          n.callMethod.apply(n,arguments):n.queue.push(arguments)};
          if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
          n.queue=[];t=b.createElement(e);t.async=!0;
          t.src=v;s=b.getElementsByTagName(e)[0];
          s.parentNode.insertBefore(t,s)}(window, document,'script',
          'https://connect.facebook.net/en_US/fbevents.js');
          fbq('init', '<?php echo $getstorage['wpext-facebook']; ?>');
          fbq('track', 'PageView');
        </script>
      <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $getstorage['wpext-facebook']; ?>&ev=PageView&noscript=1"/>
      </noscript>
    <?php }
    /* End Facebook Pixel Code */
    /*Pinterest Pixel Code End Here*/
      if(!empty($getstorage['wpext-pintrest'])){ ?>
        <script type="text/javascript"> 
          !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};
            var n=window.pintrk;
              n.queue=[],n.version="3.0";
            var t=document.createElement("script");
              t.async=!0,t.src=e;
            var r=document.getElementsByTagName("script")[0];
              r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
            pintrk('load', '<?php echo $getstorage['wpext-pintrest']; ?>'); 
            pintrk('page'); 
        </script>
        <noscript>
          <img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?tid=<?php echo $getstorage['wpext-pintrest']; ?>&noscript=1" />
      </noscript>
      <?php }
    /*Pinterest Pixel Code End Here*/
 }

}

Wp_Extended_Pixel_Tag_Manager::init(); 