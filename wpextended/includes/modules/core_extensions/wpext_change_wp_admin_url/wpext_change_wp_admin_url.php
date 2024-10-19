<?php
 
if ( ! defined( 'ABSPATH' ) ) {
  die();
}
 
class Wp_Extended_Change_Wp_Admin_Url {
  
  const WPEXT_CHANGE_WP_ADMIN_URL = 'wpext-change-wp-admin-default-url';
  private $wp_login_php;
  public function __construct() {

   $wp_config = get_option( self::WPEXT_CHANGE_WP_ADMIN_URL);
   $current_url =  esc_url_raw( $_SERVER['REQUEST_URI'] );
    if(!empty($wp_config['wpext_login_url'])){
      add_action( 'plugins_loaded', array( $this, 'wpext_plugins_loaded' ), 9999 );
      add_action( 'wp_loaded', array( $this, 'wpext_initialize' ) );
      add_filter( 'site_url', array( $this, 'site_url' ), 10, 4 );
      add_filter( 'site_option_welcome_email', array( $this, 'wpext_welcome_email' ) );
      add_filter( 'login_url', array( $this, 'wpext_login_url' ), 10, 3 );
      add_filter( 'user_request_action_email_content', array( $this, 'wpext_user_request_action_email_content' ), 999, 2 );
      add_action( 'template_redirect',  array( $this, 'wpext_redirect_404_to_custom_page' ));
    }
      add_action( 'admin_menu', array( get_called_class(), 'wpext_add_change_admin_url_menu'), 100 );
      add_action( 'admin_init', array( $this, 'wpext_load_option_configuration') );

      add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 110 );
    /**
     * 
     * Checking here if wp-login.php exist in url then it will redirect to redirection page which is setup via dashboard.
     * 
     */
    
    if(strpos($current_url, 'wp-login.php') !== false && !empty($wp_config['wpext_login_url'])) {  header('Location: ' . home_url() ); exit(); } 
  }
  
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Change_Wp_Admin_Url( get_called_class() );
    } 
    return $instance;  
  } // init

  public static function admin_scripts(){
    $screen = get_current_screen();
    if( $screen->id == "wp-extended_page_wp-extended-changes-wp-admin-url" || $screen->id == 'admin_page_wp-extended-changes-wp-admin-url') {
      wp_enqueue_script( 'wpext-wpext-manipulation', 
        plugins_url("/js/wpext-manipulation.js", __FILE__), 
        array(), 
        WP_EXTENDED_VERSION,
        true 
      );  

      wp_enqueue_style( 'wpext_change_wp_admin_url_css', plugin_dir_url( __FILE__ ) . "css/wpext_change_wp_admin_url.css", array() );
    }  
  } // admin_scripts

  public function wpext_load_option_configuration(){
    $screen = get_current_screen();
     register_setting( self::WPEXT_CHANGE_WP_ADMIN_URL, self::WPEXT_CHANGE_WP_ADMIN_URL,  array( 'type' => 'array' ) );
  }

  /**
   * 
   * Creating (Change wp admin) menu for module interface. 
   * 
   */
  public static function wpext_add_change_admin_url_menu(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_change_wp_admin_url', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_change_wp_admin_url'] == 'true') {
          $flagfavorite = true;
        }
      }
    }
      if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
          add_submenu_page( 
          'wp-extended', __('Custom Login URL ', WP_EXTENDED_TEXT_DOMAIN), __('Custom Login URL', WP_EXTENDED_TEXT_DOMAIN), 
          'manage_options', 'wp-extended-changes-wp-admin-url', 
          array( get_called_class(), 'settings_changes_wp_admin_url'),
          null
        );
      }else{
         $capability = 'manage_options';
         $slug = 'wp-extended-changes-wp-admin-url';
         $callback = [ get_called_class(), 'settings_changes_wp_admin_url'];
         add_submenu_page('', '', '', $capability, $slug, $callback);
         add_rewrite_rule('^wp-extended-changes-wp-admin-url/?', 'index.php?wp_extended_changes_wp_admin-url=1', 'top');
         add_rewrite_tag('%wp_extended_changes_wp_admin%', '([^&]+)');
      }
  }

 /**
   * 
   * Module interface. 
   * 
   */

 public static function settings_changes_wp_admin_url(){
    $wp_config = get_option( self::WPEXT_CHANGE_WP_ADMIN_URL); 
    require_once plugin_dir_path( __FILE__ ) . "templates/wp-extend-module-layout.php";   ?>
    <?php
  }
  
  public function wpext_user_request_action_email_content( $email_text, $email_data ) {
    $email_text = str_replace( '###CONFIRM_URL###', esc_url_raw( str_replace( $this->wpext_new_login_slug() . '/', 'wp-login.php', $email_data['confirm_url'] ) ), $email_text );
    return $email_text;
  }

  private function use_trailing_slashes() {
    return ( '/' === substr( get_option( 'permalink_structure' ), - 1, 1 ) );
  }
  /**
   * Adds or removes a trailing slash from a string based on the configuration.
   *
   * @param string $string The input string.
   * @return string The modified string with or without a trailing slash.
   */
  private function user_trailingslashit( $string ) {
    return $this->use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );

  }

  private function wpext_template_loader() {

    global $pagenow;
    $pagenow = 'index.php';
    wp();
    require_once( ABSPATH . WPINC . '/template-loader.php' );
    die();

  }
  /**
   * 
   * Redirection rule for based on wpext_redirect_url option
   * 
   * 
   */
  private function wpext_new_login_slug( $blog_id = '' ) {
     $option = get_option('wpext-change-wp-admin-default-url', array());
      if(!empty($option['wpext_login_url'])){
        if ( $blog_id ) {
          if ( $slug = get_blog_option( $blog_id, $option['wpext_login_url'] ) ) {
            return $slug;
          }
        } else {
          if ( $slug = $option['wpext_login_url'] ) {
            return $slug;
          } else if ( ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) && ( $slug = get_site_option('wpext_login_url', 'login' ) ) ) ) {
            return $slug;
          } else if ( $slug = 'login' ) {
            return $slug;
          }
        }
    }
  }

  private function wpext_new_redirect_slug() {
     $option = get_option('wpext-change-wp-admin-default-url', array());
    if(!empty($option['wpext_redirect_url'])) {
      if ( $slug = $option['wpext_redirect_url']) {
       return esc_url_raw($slug);
      } else if ( ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) && ( $slug = get_site_option( 'wpext_redirect_url', '404' ) ) ) ) {
        return esc_url_raw($slug);
      } else if ( $slug = home_url($option['wpext_redirect_url']) ) {
        return esc_url_raw($slug);
      }
   }
  }

  /**
   * 
   *The purpose of this code is to generate a redirect URL based on the permalink structure of the site. 
   * 
   */

  public function wpext_new_login_url( $scheme = null ) {
    $url = apply_filters( 'wpext_hide_login_home_url', home_url( '/', $scheme ) );
    if ( get_option( 'permalink_structure' ) ) {
      return $this->user_trailingslashit( $url . $this->wpext_new_login_slug() );
    } else {
      return $url . '?' . $this->wpext_new_login_slug();
    }

  }

  public function new_redirect_url( $scheme = null ) {
    if ( get_option( 'permalink_structure' ) ) {
      return $this->user_trailingslashit( home_url( '/', $scheme ) . $this->wpext_new_redirect_slug() );
    } else {
      return home_url( '/', $scheme ) . '?' . $this->wpext_new_redirect_slug();
    }
  }

  /**
   *
   * Update url redirect : wp-register.php
   *
   * @param $login_url
   * @param $redirect
   * @param $force_reauth
   *
   * @return string
   */

  public function wpext_plugins_loaded() {
    global $pagenow;
    $request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );
    if ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
           || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) )
         && ! is_admin() ) {

      $this->wp_login_php = true;
      $_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );
      $pagenow = 'index.php';

    } elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( $this->wpext_new_login_slug(), 'relative' ) )
               || ( ! get_option( 'permalink_structure' )
                    && isset( $_GET[ $this->wpext_new_login_slug() ] )
                    && empty( $_GET[ $this->wpext_new_login_slug() ] ) ) ) {

      $_SERVER['SCRIPT_NAME'] = $this->wpext_new_login_slug();

      $pagenow = 'wp-login.php';

    } elseif ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-register.php' ) !== false
                 || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) )
               && ! is_admin() ) {

      $this->wp_login_php = true;
      $_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );
      $pagenow = 'index.php';
    }

  }

  /**
   *
   * Update url redirect : post_password, redirect_to
   *
   * @param $login_url
   * @param $redirect
   * @param $force_reauth
   *
   * @return string
   */

  public function wpext_initialize() {

    global $pagenow;

    $request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

    do_action( 'wpext_hide_login_before_redirect', $request );
      if ( is_admin() && ! is_user_logged_in() && ! defined( 'WP_CLI' ) && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) && $pagenow !== 'admin-post.php' && $request['path'] !== '/wp-admin/options.php' ) {
        wp_safe_redirect( $this->new_redirect_url() );
        die();
      }

      if ( ! is_user_logged_in() && isset( $_GET['wc-ajax'] ) && $pagenow === 'profile.php' ) {
        wp_safe_redirect( $this->new_redirect_url() );
        die();
      }

      if ( ! is_user_logged_in() && isset( $request['path'] ) && $request['path'] === '/wp-admin/options.php' ) {
        header('Location: ' . $this->new_redirect_url() );
        die;
      }

      if ( $pagenow === 'wp-login.php' && isset( $request['path'] ) && $request['path'] !== $this->user_trailingslashit( $request['path'] ) && get_option( 'permalink_structure' ) ) {
        wp_safe_redirect( $this->user_trailingslashit( $this->wpext_new_login_url() )
                          . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );

        die;

      } elseif ( $this->wp_login_php ) {

        if ( ( $referer = wp_get_referer() )  && strpos( $referer, 'wp-activate.php' ) !== false  && ( $referer = parse_url( $referer ) ) && ! empty( $referer['query'] ) ) {
          parse_str( $referer['query'], $referer );
          @require_once WPINC . '/ms-functions.php';
          if ( ! empty( $referer['key'] ) && is_wp_error( $result ) && ( $result->get_error_code() === 'already_active' || $result->get_error_code() === 'blog_taken' ) ) {
            wp_safe_redirect( $this->wpext_new_login_url(). ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );
            die;

          }
        }
      $this->wpext_template_loader();

      } elseif ( $pagenow === 'wp-login.php' ) {
        global $error, $interim_login, $action, $user_login;

        $redirect_to = admin_url();
        $requested_redirect_to = '';
        if ( isset( $_REQUEST['redirect_to'] ) ) {
          $requested_redirect_to = $_REQUEST['redirect_to'];
        }
        if ( is_user_logged_in() ) {
          $user = wp_get_current_user();
          if ( ! isset( $_REQUEST['action'] ) ) {
            $logged_in_redirect = apply_filters( 'wpext_logged_in_redirect', $redirect_to, $requested_redirect_to, $user );
            wp_safe_redirect( $logged_in_redirect );
            die();
          }
        }

        @require_once ABSPATH . 'wp-login.php';

        die;

      }
  }

  public function site_url( $url, $path, $scheme, $blog_id ) {
    return $this->filter_wp_login_php( $url, $scheme );
  }
  public function wp_redirect( $location, $status ) {
    return $this->filter_wp_login_php( $location );
  }

  /**
   * 
   * Protect the url via hardcoded login (WP core protected action=postpass)
   * 
   * 
   * 
   */

  public function filter_wp_login_php( $url, $scheme = null ) {

    /*if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
      return $url;
    } */

    if ( strpos( $url, 'wp-login.php' ) !== false && strpos( wp_get_referer(), 'wp-login.php' ) === false ) {

      if ( is_ssl() ) {

        $scheme = 'https';

      }

      $args = explode( '?', $url );

      if ( isset( $args[1] ) ) {

        parse_str( $args[1], $args );

        if ( isset( $args['login'] ) ) {
          $args['login'] = rawurlencode( $args['login'] );
        }

        $url = add_query_arg( $args, $this->wpext_new_login_url( $scheme ) );

      } else {

        $url = $this->wpext_new_login_url( $scheme );

      }

    }

    return $url;

  }

  public function wpext_welcome_email( $value ) {

    return $value = str_replace( 'wp-login.php', trailingslashit( get_site_option( 'wpext_login_url', 'login' ) ), $value );

  }

  /**
   *
   * Update url redirect : wp-admin
   *
   * @param $login_url
   * @param $redirect
   * @param $force_reauth
   *
   * @return string
   */

  public function wpext_login_url( $login_url, $redirect, $force_reauth ) {
    $option = get_option('wpext-change-wp-admin-default-url', array());
    if ( is_404() ) {

      return '#';
    }
    if ( $force_reauth === false ) {
      return $login_url;
    }

    if ( empty( $redirect ) ) {
      return $login_url;
    }
    $redirect = explode( '?', $redirect );
    if ( $redirect[0] === admin_url( 'options.php' ) ) {
      $login_url = admin_url();
    }
    return $login_url;
  }

  /** 
   * 
   * Setup redirection rule of 404
   * 
   * 
   */
  public function wpext_redirect_404_to_custom_page(){

    $option = get_option('wpext-change-wp-admin-default-url', array());
    if(!empty($option['wpext_redirect_url'])){
      if(is_404()):
            wp_safe_redirect( home_url($option['wpext_redirect_url']) );
            exit;
        endif;
      }
  }

}
Wp_Extended_Change_Wp_Admin_Url::init(); 
 
 