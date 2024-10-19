<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://wearenrcm.com
 * @since      1.0.0
 *
 * @package    wp_extended
 * @subpackage wp_extended/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    wp_extended
 * @subpackage wp_extended/admin
 * @author     NRCM Web Design <hello@wearenrcm.com>
 */
class Wp_Extended_admin extends Wp_Extended {
  const WPEXT_CHECK_LICENSE_STSTUS = 'wpext_license_key';
 
  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   * @param      string    $wp_extended       The name of this plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct( $wp_extended, $version ) {

    parent::__construct( $wp_extended, $version );
 
    add_action('admin_menu', array( $this, 'menu' ) );

    add_action('admin_init',    array( $this, 'options_init' ) );
    add_action('rest_api_init', array( $this, 'options_init' ) );
    add_action('admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
    add_action('wp_ajax_wp-extended-module-toggle',   array( $this, 'module_toggle_ajax') );
    add_action('wpext_plugin_sidebar', array( $this, 'admin_plugin_info_sidebar' ), 100 );
    add_action('admin_plugin_top_info', array( $this, 'wpext_admin_plugin_top_info' ), 100 );
    
    add_filter( 'plugin_action_links_' . WP_EXTENDED_PLUGIN_BASE, array( $this,'add_pro_action_links') );
    add_action('wp_ajax_wpext_reset_plugin_settings', array( $this, 'wpext_reset_plugin_settings') );
    add_action('wp_ajax_wpext_admin_menu_favorite', array( $this, 'wpext_admin_menu_favorite_callback') );
    add_action('wp_ajax_wpext_show_plugin_menu', array( $this, 'wpext_show_plugin_menu') );
        
    add_action('wp_ajax_wpext_import_json_data', array( $this, 'wpext_import_json_data_callback') );
    add_action('wp_ajax_nopriv_wpext_import_json_data', array( $this, 'wpext_import_json_data_callback') );

    add_action('wp_ajax_wpext_export_options_to_json', array( $this, 'wpext_export_options_to_json') );
    add_action('wp_ajax_nopriv_wpext_export_options_to_json', array( $this, 'wpext_export_options_to_json') );
    add_filter('upload_mimes', array( $this,'wpext_allow_json_upload') );

    add_action('wpext_plugin_top_header', array( $this, 'wpext_plugin_top_header' ), 100 );    
    add_action('wpext_plugin_footer', array( $this, 'wpext_plugin_footer' ), 100 );
    add_action('wpext_module_save_btn_header', array( $this, 'wpext_module_save_btn_header' ), 100,1 ); 

    add_action('wp_ajax_wpext_active_modules', array( $this, 'wpext_active_modules_callback') );
    add_action('wp_ajax_nopriv_wpext_active_modules',   array( $this, 'wpext_active_modules_callback') );
  }

  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_admin( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  /* 
   * Register options, used by plugin
   */
  
  public function options_init(){
    register_setting( 'wp-extended', self::OPTION_MODULES, array( 'type' => 'array' ) );
    register_setting( self::WPEXT_CHECK_LICENSE_STSTUS, self::WPEXT_CHECK_LICENSE_STSTUS,  array( 'type' => 'array' ) );
    
} // options_init


  public function ver(){
    return $this->version;
  }

  /**
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_styles() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in wp_extended_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The wp_extended_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */

    $screen = get_current_screen();

    if( isset($_GET['page']) && preg_match( '/^wp-extended/', $_GET['page'] )) {
      wp_enqueue_style( 'wp-extended-bootstrap-css', plugin_dir_url( __FILE__ ) . "css/bootstrap.min.css", array(), WP_EXTENDED_VERSION );
      
      remove_all_actions('admin_notices');
    }
    add_filter('admin_body_class', array($this,'wpext_body_class'), 1);
    wp_enqueue_style( 'wp-extended-global', plugin_dir_url( __FILE__ ) . "css/wpext_global_admin.css", array(), WP_EXTENDED_VERSION);
    wp_enqueue_style( 'wp-extended-css', plugin_dir_url( __FILE__ ) . "css/options.css" , array(), WP_EXTENDED_VERSION);

    if ( get_current_screen()->id === 'plugins' ) {
      wp_enqueue_script( 'wpext_deactivation_survey',plugin_dir_url( __FILE__ ) . 'js/deactivation-survey.js', array(
        'jquery'
      ), WP_EXTENDED_VERSION );

      wp_enqueue_style( 'wpext_deactivation_survey', plugin_dir_url( __FILE__ ) . 'css/deactivation-survey.css', null, WP_EXTENDED_VERSION );

      add_action( 'admin_footer', [ $this, 'deactivation_survey_modal' ] );
    }
  }

  
  /**
   * Plugin action links.
   *
   * Adds action links to the plugin list table
   *
   * Fired by `plugin_action_links` filter.
   *
   * @since 2.1.1
   * @access public
   *
   * @param array $links An array of plugin action links.
   *
   * @return array An array of plugin action links.
   */
  public function add_pro_action_links ( $links ) {
    $pro_action_links = array(
      '<a href="https://wpextended.io/pricing" target="_blank" 
      class="wpext-plugins-gopro" style=" color: #00947E !important; font-weight: 600; ">'.esc_html__( 'Go Pro', WP_EXTENDED_TEXT_DOMAIN ).'</a>',
    );
    return array_merge( $links, $pro_action_links );
  }

  /**
   * Register the JavaScript for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in wp_extended_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The wp_extended_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */
    
      $screen = get_current_screen();
      wp_enqueue_script( 'wp_extended-admin', plugin_dir_url( __FILE__ ) . 'js/wp_extended-admin.js', 
      array( 'jquery' ), WP_EXTENDED_VERSION, false );
     
      $wpext_extended_obj  = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'ajax_nonce' => wp_create_nonce( 'extended_obj' ),
      );
      wp_localize_script( 'wp_extended-admin', 'wpext_extended_obj', $wpext_extended_obj);
      
      wp_register_script( 'wpext-edit-main-button',  plugin_dir_url( __FILE__ ) . 'js/edit-main-button.js', 
      array('wp-element', 'wp-edit-post','wp-plugins', 'wp-i18n' ),  WP_EXTENDED_VERSION,  true ); 

      $id = get_the_ID();
      $post = get_post( $id );
      $p = array(
      'ID'          => $id,
      'post_title'  => html_entity_decode( get_the_title($id) ),
      'post_type'   => $post ? $post->post_type : null,
      'post_status' => $post ? $post->post_status : null
    );
    wp_add_inline_script( 'wpext-edit-main-button', 
      'const wpext_post = ' . json_encode( $p ), 
      'before' );
      
    if( preg_match( '/^(post|page)/', $screen->id ) ) {
      wp_enqueue_script( 'wpext-edit-main-button' );
      wp_enqueue_style( 'wpext-main-button' );
    }
    
    if( isset($_GET['page']) && preg_match( '/^wp-extended/', $_GET['page'] )) {
      wp_enqueue_script( 'wp-extended-bootstrap-js', plugin_dir_url( __FILE__ ) ."js/bootstrap.bundle.min.js", array(), WP_EXTENDED_VERSION );
    }

  }
  public function menu(){
    add_menu_page( 
      __('WP Extended Settings', WP_EXTENDED_TEXT_DOMAIN ), 
      __('WP Extended', WP_EXTENDED_TEXT_DOMAIN ), 
      'manage_options', 
      'wp-extended', 
      array( $this, 'admin_display' ) ,
      'data:image/svg+xml;base64,' . base64_encode('<svg version="1.0" xmlns="http://www.w3.org/2000/svg"
      width="50.000000pt" height="50.000000pt" viewBox="0 0 500.000000 500.000000"
      preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,500.000000) scale(0.100000,-0.100000)"
     fill="#2e47a7" stroke="none"><path d="M1335 4865 c-631 -100 -1104 -577 -1201 -1210 -20 -132 -20 -2178 0
     -2310 98 -638 573 -1113 1211 -1211 72 -11 300 -14 1155 -14 1115 0 1140 1
     1310 44 496 127 899 530 1026 1026 43 170 44 195 44 1310 0 855 -3 1083 -14
     1155 -98 638 -573 1113 -1211 1211 -126 19 -2198 18 -2320 -1z m1655 -938
     c220 -65 398 -244 455 -457 9 -32 15 -101 15 -167 0 -92 -4 -126 -25 -192 -31
     -98 -105 -211 -183 -281 -65 -58 -209 -133 -290 -149 -33 -7 -205 -11 -455
     -11 -325 0 -414 3 -467 16 -215 50 -400 229 -466 449 -25 83 -25 258 0 340 76
     252 281 431 535 466 31 4 229 6 441 5 320 -3 394 -6 440 -19z m-30 -1613 c215
     -50 400 -228 466 -449 13 -43 18 -92 18 -175 0 -102 -4 -125 -30 -200 -61
     -174 -182 -305 -348 -380 -118 -53 -139 -55 -576 -55 -452 0 -460 1 -599 67
     -157 75 -291 237 -336 408 -9 32 -15 101 -15 167 0 92 4 126 25 192 31 98 105
     211 183 281 64 57 193 126 274 145 83 21 851 20 938 -1z"/><path d="M2097 3689 c-90 -21 -178 -83 -232 -164 -50 -77 -68 -141 -63 -239 8
     -186 141 -336 324 -366 95 -16 706 -12 784 4 247 53 380 320 273 547 -50 108
     -142 184 -258 215 -58 16 -763 18 -828 3z m201 -168 c50 -25 109 -93 122 -140
     34 -123 -26 -251 -140 -299 -53 -22 -141 -18 -195 9 -145 73 -179 264 -67 382
     72 76 187 95 280 48z"/><path d="M2085 2075 c-242 -53 -374 -321 -268 -546 34 -74 105 -150 172 -183
     87 -44 131 -48 536 -44 l380 3 58 27 c80 38 128 78 172 143 50 77 68 141 63
     239 -8 186 -141 336 -324 366 -92 15 -716 11 -789 -5z m834 -168 c111 -54 161
     -194 110 -307 -43 -96 -117 -144 -219 -144 -79 0 -131 24 -184 86 -117 136
     -40 354 136 387 56 10 103 4 157 -22z"/></g></svg>'),
    );
    add_submenu_page(
      'wp-extended',
      __('Modules', WP_EXTENDED_TEXT_DOMAIN ),__('Modules', WP_EXTENDED_TEXT_DOMAIN ),'manage_options','wp-extended');
    } 
   
   /*
   * Display admin page
   */
  public function admin_display(){
    add_action('wpext_module_save_btn_header', array( $this, 'wpext_module_save_btn_header' ), 10,1 );

    $groups = $this->groups();
    $modules = $this->modules();

    include __DIR__ . '/partials/wp-extend-admin-display.php';
  } // admin_display
  
  /*
   * AJAX endpoint for module activation 
   */
  public function module_toggle_ajax() {
    // Verify the nonce
    check_ajax_referer('extended_obj', 'nonce');

    $module = isset($_POST['module']) ? $_POST['module'] : null;
    $status = isset($_POST['status']) ? (bool) $_POST['status'] : false;

    // Toggle the module
    $result = $this->module_toggle($module, $status);
    
    wp_send_json($result);

    wp_die();
 }

 // module_toggle_ajax
  /**
   * Activate single module
   */
  public function module_toggle( $module = null, $new_status = false ){
    $tmp = plugin_dir_path( __FILE__ ) . '__saving_options.tmp';

    try {
      if( !current_user_can('manage_options') ) {
        throw new \Exception( "Not allowed" );
      }

      if( empty($module) ) {
        throw new \Exception( "Module not specified" );
      }
      $exists = $this->module( $module );

      if( !$exists ) {
        throw new \Exception( "Module does not exists") ;
      }

      // check tmp file for existing over 30s
      if( file_exists( $tmp ) && time() - filemtime( $tmp ) >= 30 ) {
        @unlink( $tmp );
      }

      $n = 0;
      while( file_exists( $tmp ) ) {
        // wait for other process to end
        usleep( 250 );

        if( $n > 20 ) {
          throw new \Exception( "Operation timeout" );
        }

        $n++;
      };

      // create file to show that option is in-use
      @touch( $tmp );
 
      try {
        global $wpdb;
        $row = $wpdb->get_row( 
          $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", self::OPTION_MODULES ) 
        );

        $option = null;
        if ( is_object( $row ) ) {
          $option = maybe_unserialize( $row->option_value );
        }

        if( empty($option) ) {
          $option = array();
        }

        // $option = get_option( self::OPTION_MODULES, array() );

        $status = isset($option[ $module ]) ? $option[ $module ] : false;
        
        if( $status === $new_status ) {
          throw new \Exception( "Module is already " . ( $new_status ? 'active' : 'inactive' ) );
        }

        // SET NEW STATUS
        $option[ $module ] = $new_status;

        $updated = update_option( self::OPTION_MODULES, $option );
        
        if( !$updated ) {
          throw new \Exception( "Failed to update database record" );
        }      
      }
      catch( \Exception $e ) {
        @unlink( $tmp );

        throw new \Exception( $e->getMessage() );
      }

      @unlink( $tmp );  

      $result = array(
        'status'        => true,
        'module_status' => $this->module_status( $module ),
        'module_info'   => $exists,
        'n' => $n
      );


      // check if there's an activation/deactivation script
      try {
        $hook = $new_status ? '__activate' : '__deactivate';

        $dir = $this->folder_modules();
        $path = $dir . $exists['group'] . "/" . $exists['dirname'] . "/" . $hook . ".php";

        if( is_file( $path ) ) {
          require_once( $path );
          $result['module' . $hook ] = true;
        }
      }
      catch( \Exception $e ) {
        $result['module' . $hook ] = $e->getMessage();
      }
      
    }
    catch( \Exception $e ) {
      $result = array( 
        'status' => false, 
        'error' => __( $e->getMessage(), WP_EXTENDED_TEXT_DOMAIN ) 
      );
    }

    $result['module'] = $module;

    return $result;
  } // module_status_set

  /* Feedback and upgrade button */

  public function wpext_admin_plugin_top_info(){
     include_once __DIR__ . "/partials/wp-extend-top-info.php";
  }

  public function wpext_body_class($classes){
    $classes = 'wpext-base';
    return $classes;
  }

  public function deactivation_survey_modal(){

    $current_user = wp_get_current_user();
    $email = (string) $current_user->user_email;

      echo '<div class="wpext-deactivation-survey container is-fluid css-wp-extend">
        <div class="wpext-survey-inner columns">
        <div class="column is-two-thirds-widescreen">
        <div class="box">
          <div class="wp-ext_heading">
            <h3 class="title is-4">' . __( 'Sorry to see you go!', WP_EXTENDED_TEXT_DOMAIN ) . '</h3>
            <p class="heading-text">' . sprintf( __( 'We would appreciate if you let us know why you\'re deactivating WP Extended.', WP_EXTENDED_TEXT_DOMAIN ), WP_EXTENDED_TEXT_DOMAIN ) . '</p>
          </div>
          <form action="" method="POST" class="feedback-form">
            <label class="feedback-lable">
              <input type="radio" name="wpext_reason" value="I no longer use this plugin" />
              ' . __( 'I no longer use this plugin.', WP_EXTENDED_TEXT_DOMAIN ) . '
            </label>
            <label class="feedback-lable">
              <input type="radio" name="wpext_reason" value="The plugin caused my site to crash" />
              ' . __( 'The plugin caused my site to crash.', WP_EXTENDED_TEXT_DOMAIN) . '
            </label>
            <label class="feedback-lable"> 
              <input type="radio" name="wpext_reason" value="The plugin caused errors and conflicts with other plugins" />
              ' . __( 'The plugin caused errors and conflicts with other plugins.', WP_EXTENDED_TEXT_DOMAIN ) . '
            </label>
            <label class="feedback-lable">
              <input type="radio" name="wpext_reason" value="I found a better solution" />
              ' . __( 'I found a better solution.', WP_EXTENDED_TEXT_DOMAIN) . '
            </label>
            <label class="feedback-lable">
            <input type="radio" name="wpext_reason" value="I am temporarily disabling the plugin" />
              ' . __( 'I am temporarily disabling the plugin.', WP_EXTENDED_TEXT_DOMAIN) . '
            </label>
            <label class="other_issues">
              <input type="radio" name="wpext_reason" value="Other" />
              ' . __( 'Other (Please tell us more)', WP_EXTENDED_TEXT_DOMAIN ) . '
            </label>
            <label class="other-issues-textarea" style="display:none;">
             <textarea name="wpext_deactivation_description" class="widefat" placeholder="' . __( 'Details (optional)', WP_EXTENDED_TEXT_DOMAIN ) . '"></textarea>
            </label>
            <input type="hidden" name="wpext_site_url" value="' . site_url() . '" />
            <input type="hidden" name="wpext_version" value="' . WP_EXTENDED_VERSION . '" />
            <div class="wpext-uninstall-feedback-privacy-policy">
             We do not collect any personal data when you submit this form. It\'s your feedback that we value.
             <a href="https://wpextended.io/privacy-policy/" target="_blank">'.__('Privacy Policy', WP_EXTENDED_TEXT_DOMAIN ).'</a>
            </div>
            <hr>
            <div class="wpext-deactivate-buttons-wrapper">
              <button class="button button-primary submit-and-deactivate" disabled="disabled">' . __( 'Submit & Deactivate', WP_EXTENDED_TEXT_DOMAIN ) . '</button>
              <button class="button button-secondary deactivate-plugin" type="button">' . __( 'Skip & Deactivate', WP_EXTENDED_TEXT_DOMAIN) . '</button>
              <button class="button button-secondary cancel-deactivation-survey" type="button">' . __( 'Cancel', WP_EXTENDED_TEXT_DOMAIN) . '</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>';

  }

  /* Storing action for reset data via ajax*/
  public function wpext_reset_plugin_settings() {
    $status = sanitize_text_field($_POST['status']);
    $response = array();
    if (!empty($status)) {
        update_option('wpext_plugin_reset_action', $status);
        if ($status == 'true') {
            $message = __('Delete all plugin data <strong>Activated</strong>', WP_EXTENDED_TEXT_DOMAIN);
        } else {
            $message = __('Delete all plugin data <strong>Deactivated</strong>', WP_EXTENDED_TEXT_DOMAIN);
        }
        $response['status'] = $status;
        $response['message'] = $message;
    } else {
        $response['status'] = 'false';  
        $response['message'] = __('Status not provided', WP_EXTENDED_TEXT_DOMAIN);
    }
    wp_send_json(json_encode($response));
    die;
  }


public function wpext_show_plugin_menu(){
    $status = sanitize_text_field($_POST['status']);
    $response = array();
    if (!empty($status)) {
        update_option('wpext_show_plugin_menu_action', $status);
        if ($status == 'true') {
            $message = __('Show plugin menu <strong>Activated</strong>', WP_EXTENDED_TEXT_DOMAIN);
            
            $current_data = get_option('wpext_admin_menu_favorite');

            // Check if all values are false
            if (empty($current_data)) {
              $favArrValue = Array ( 
                'wpext_admin_columns' => 'true',
                'wpext_admin_color_picker' => 'true',
                'wpext_duplicate_menu' => 'true',
                'wpext_snippets' => 'true',
                'wpext_pixel_tag_manager' => 'true',
                'wpext_smtp_email' => 'true',
                'wpext_block_user_name_admin' => 'true',
                'wpext_change_wp_admin_url' => 'true',
                'wpext_hide_notices' => 'true',
                'wpext_limit_login_attempts' => 'true',
                'wpext_maintenance_mode' => 'true',
                'wpext_external_permalinks' => 'true',
                'wpext_post_order' => 'true',
                'wpext_user_sections' => 'true',
                'wpext_quick_image_replace' => 'true',
                'wpext_disable_dashboard_widget' => 'true',
                'wpext_disable_gutenberg' => 'true',
                'wpext_hide_admin_bar' => 'true',
                'wpext_tidy_nav' => 'true');

              // Store updated data in the database
              update_option('wpext_admin_menu_favorite', $favArrValue);
            }

        } else {
            $message = __('Show plugin menu <strong>Deactivated</strong>', WP_EXTENDED_TEXT_DOMAIN);            
            $current_data = "";
        }
        $response['status'] = $status;
        $response['message'] = $message;        
        $response['favorite_data'] = $current_data;
    } else {
        $response['status'] = 'false';  
        $response['message'] = __('Status not provided', WP_EXTENDED_TEXT_DOMAIN);
    }
    wp_send_json(json_encode($response));
    die;
  }

  public function wpext_admin_menu_favorite_callback(){
    $status = sanitize_text_field($_POST['status']);
    $dataSlug = sanitize_text_field($_POST['dataSlug']);
    $dataName = sanitize_text_field($_POST['dataName']);
    
    $response = array();
    if (!empty($dataSlug)) {        
        $current_data = get_option('wpext_admin_menu_favorite');
        // If the option doesn't exist or is not an array, initialize an empty array
        if (!is_array($current_data)) {
            $current_data = array();
        }

        // Check if the key already exists
        if (array_key_exists($dataSlug, $current_data)) {
            $current_data[$dataSlug] = $status;
        } else {
            $current_data[$dataSlug] = $status;
        }
        update_option('wpext_admin_menu_favorite', $current_data);

        if ($status == 'true') {
            $message = __('Favourites '.$dataName.' Submenu <strong>Activated</strong>', WP_EXTENDED_TEXT_DOMAIN);
        } else {
            $message = __('Favourites '.$dataName.' Submenu <strong>Deactivated</strong>', WP_EXTENDED_TEXT_DOMAIN);
        }
        $response['message'] = $message;
    } else {
        $response['message'] = __('Status not provided', WP_EXTENDED_TEXT_DOMAIN);
    }
    wp_send_json(json_encode($response));
    die;
  }
  
 /** Import setting via JSON file **/
  /** Function for Import all plugin setting **/
    public function wpext_import_json_data_callback() {
      check_ajax_referer('extended_obj', 'security');
      $response = array();
      if (!empty($_FILES['file_data']['name'])) {
          $uploaded_file = $_FILES['file_data'];
          $file_extension = pathinfo($_FILES['file_data']['name'], PATHINFO_EXTENSION);
          if ($file_extension != 'json') {
              $response['message'] = __('Please upload a JSON file.', WP_EXTENDED_TEXT_DOMAIN);
          }
          $file = wp_handle_upload($uploaded_file, array('test_form' => false));
          if ($file && !isset($file['error'])) {
              $file_url = $file['file'];  
              $json_data = file_get_contents($file_url);
              $decoded_data = json_decode($json_data, true);
              if ($decoded_data !== null) {
                  foreach ($decoded_data as $key => $value) {
                    if (strpos($key, 'wp-extended') === 0 || strpos($key, 'wpext') === 0) {
                        update_option($key, $value);
                        //snippet data 
                        if($key == 'wpext-snippets'){
                          foreach ($value as $snippet) {
                              // Prepare post data
                              $post_data = array(
                                  'post_title'    => $snippet['label'],
                                  'post_content'  => $snippet['code'],
                                  'post_status'   => 'publish',
                                  'post_type'     => 'snippet', 
                              );

                              // Insert the post into the database
                              $post_id = wp_insert_post($post_data);

                              // Check for errors
                              if (is_wp_error($post_id)) {
                                  // Handle the error
                                  echo 'Error: ' . $post_id->get_error_message();
                                  continue;
                              }

                              // Add post meta data
                              add_post_meta($post_id, 'snippet_position', $snippet['position']);
                              add_post_meta($post_id, 'snippet_code_type', $snippet['code_type']);
                              $snippet_active = $snippet['active'];
                              if($snippet['code_type'] == 'PHP'){
                                $snippet_active = 0;
                              }
                              add_post_meta($post_id, 'snippet_active', $snippet_active);
                              add_post_meta($post_id, 'snippet_code_sesc', $snippet['description']);
                          }
                        }
                    } 
                  }
                  $response['success'] = __('Data imported successfully!', WP_EXTENDED_TEXT_DOMAIN);
                } else {
                  $response['message'] = __('Invalid JSON format', WP_EXTENDED_TEXT_DOMAIN);
              }
          } else {
              $response['message'] = __('Error uploading file.', WP_EXTENDED_TEXT_DOMAIN);
          }
      } else {
          $response['message'] = __('Please choose a file to import proceed', WP_EXTENDED_TEXT_DOMAIN);
      }
      wp_send_json(json_encode($response));
      wp_die();
  }
  
  /** Function for Import all plugin setting **/
  public function wpext_export_options_to_json() {
    check_ajax_referer('extended_obj', 'security');
    // Array of option names you want to export
     $response = array();
    $option_names = [
      'wp-extended-modules',
      'wpext_pixel_tag_manager',
      'wpext_plugin_and_theme_rollback',
      'wpext_block_user_name_admin',
      'wpext_change_wp_admin_url',
      'wpext_disable_user_numeration',
      'wpext_hide_the_wordPress_version',
      'wpext-hide_admin_bar',
      'wpext_last_login_status',
      'wpext_maintenance_mode',
      'wpext-maintanance_mode',
      'wpext_limit_login_attempts',
      'wpext_disable_dashboard_widget',
      'wpext_post_order',
      'wpext_disable_widgets_gutenberg',
      'wpext_disable_gutenberg',
      'wpext-post-type-order',
      'wpext-external-permalink-url',
      'wpext_login_url',
      'wpext-user-sections',
      'wpext_indexing_notice'
    ];
    $status = sanitize_text_field($_POST['status']);
    if(!empty($status) && $status == 'true'){
      $option_snippet = ['wpext-snippets'];
      $option_names = array_merge($option_names, $option_snippet);
    } 
    $options = array();
    foreach ($option_names as $name) {

      if($name == 'wpext-snippets'){
        $args = array(
            'post_type'      => 'snippet', 
            'posts_per_page' => -1,     
        );

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) { $query->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();
                $content = get_the_content();

                // Get the custom meta data
                $snippet_position   = get_post_meta($post_id, 'snippet_position', true);
                $snippet_code_type  = get_post_meta($post_id, 'snippet_code_type', true);
                $snippet_active     = get_post_meta($post_id, 'snippet_active', true);
                $snippet_code_sesc  = get_post_meta($post_id, 'snippet_code_sesc', true);

                // Prepare the snippet data
                $snippet_data = array(
                    'code'     => $content,
                    'label'    => $title,
                    'position' => $snippet_position,
                    'code_type' => $snippet_code_type,
                    'active' => $snippet_active,
                    'description' => $snippet_code_sesc
                );

                // Store the snippet data in the options array
                $options['wpext-snippets'][] = $snippet_data;
            }
        }

        // Restore original snippet Data
        wp_reset_postdata();

      }else{
        $option_value = get_option($name);
        if (is_array($option_value)) {
            $options[$name] = $option_value;
        } else {
            $options[$name] = maybe_unserialize($option_value);  
        }
      }
    }
    if (!empty($options)) {
        $json_data = json_encode($options, JSON_PRETTY_PRINT);
        header('Content-disposition: attachment; filename=options_export.json');
        header('Content-type: application/json');
        echo $json_data;
    } else {
        _e('No options found for export.', WP_EXTENDED_TEXT_DOMAIN);
    }
    wp_die();
  }
  public function wpext_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
  }
  /* Inner Header */
  public function wpext_plugin_top_header(){
    include_once __DIR__ . "/partials/wp-extend-header-display.php";
  }

  /*Save Button global */

  public function wpext_module_save_btn_header($save_button_text){    
    include_once __DIR__ . "/partials/wp-extend-save-btn-header.php";
  }  
  public function wpext_plugin_footer(){
     include_once __DIR__ . "/partials/wp-extend-footer.php";
  }

  public function wpext_license_page() { ?>
    
    <!-- start code -->
      <div class="row">
         <?php
            $wpext_tabSettingGroups = array( 'license_and_settings' => 'Settings', 'system_info' => 'System Info', 'support' => 'Support');
            ?>
         <div class="tab col-sm-3">
            <?php foreach($wpext_tabSettingGroups as $key => $tbgroup): ?>
            <button class="tablinksSetting <?php if($key == "license_and_settings"){ echo "active";} ?>" data-tab="<?php echo $key; ?>"><?php _e($tbgroup, WP_EXTENDED_TEXT_DOMAIN); ?></button>
            <?php endforeach; ?>
         </div>
         <div class="tab-content col-sm-9">
          <!-- start License & Settings -->
          <?php $status = get_option('wpext_plugin_reset_action'); ?>
          <div class="container bg-white text-dark px-0 wpe_sidebar tab-content-setting" id="license_and_settings" style="display:block;">
            <div class="row">
              <h5 class="text-black wpext_mb20 settings_tab_title"><?php _e('Settings', WP_EXTENDED_TEXT_DOMAIN); ?></h5>
            </div>
            <div class="row g-3">
              <div class="field form-switch form-switch-md mt-1 mb-2 wpext_resetaction">
                <input id="wpext_reset_settings" name="wpext_reset_settings" type="checkbox"  <?php if(!empty($status) && $status == 'true') { echo "checked"; } ?> class="form-check-input" role="switch"> 
                <label for="wpext_reset_settings" role="button" class="wpext_font_size"><?php _e('Delete all plugin data on plugin removal', WP_EXTENDED_TEXT_DOMAIN); ?></label>
              </div>

              <div class="field form-switch form-switch-md mt-0 mb-2 wpext_resetaction">
                <?php $menustatus = get_option('wpext_show_plugin_menu_action'); ?>
                  <input id="wpext_show_submenu" name="wpext_show_submenu" type="checkbox" 
                    <?php if(!empty($menustatus) && $menustatus == 'true') { echo "checked"; } ?>
                    class="form-check-input" role="switch">                    
                  <label for="wpext_show_submenu" role="button" class="wpext_font_size"> <?php _e('Display Modules in the Submenu', WP_EXTENDED_TEXT_DOMAIN) ?></label>
              </div>

              <!-- start enable sub module display -->
                <?php 
                $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');
                $wpext_tabGroups = array( 'snippets' => 'Utilities', 'security' => 'Security',
                  'post-page' => 'Posts & Pages', 'users' => 'Users', 'media' => 'Images & Media',
                  'disable' => 'Disable & Organise');
                $modules = $this->modules();
                foreach($wpext_tabGroups as $grpkey => $tbgroup):
                  foreach ($modules as $slug => $module_group):
                    foreach ($module_group as $key => $module):
                      if( isset($module['action']) &&  $module['action'] != '' ){
                        if (!empty($module['group']) && $module['group'] == $grpkey ){ ?>
                          <div class="form-check form-check-inline wpext_menu_favorite">
                            <input class="form-check-input wpext_admin_menu_favorite mt-1" type="checkbox" id="<?php echo $module['dirname']."_favorite"; ?>" name="wpext_admin_menu_favorite[<?php echo $module['dirname']; ?>]" <?php if(!empty($wpext_admin_menu_favorite[$module['dirname']]) && $wpext_admin_menu_favorite[$module['dirname']] == 'true') { echo "checked"; } ?> data-slug="<?php echo $module['dirname']; ?>" data-name="<?php echo $module['name']; ?>">
                            <label class="form-check-label" role="button" for="<?php echo $module['dirname']."_favorite"; ?>"><?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?></label>
                          </div>
                        <?php }
                      }
                    endforeach;
                  endforeach;
                endforeach; ?>
                <!-- end enable sub module display -->

            </div>
          </div>            
        <!-- end License & Settings -->
        <!-- start System Info -->

        <div class="container bg-white text-dark p-0 pt-0 wpe_sidebar tab-content-setting" id="system_info" style="display:none;"> 
            <div class="container bg-white text-dark p-0">
            <h5 class="text-black wpext_mb20">System Information</h5>
              <ul class="list-group list-group-flush px-0">
                <li class="fs-6 px-0"><?php _e('License:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php _e('Free', WP_EXTENDED_TEXT_DOMAIN); ?></span></li>
                <li class="fs-6 px-0"><?php _e('Plugin Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php echo $this->ver();?></span></li>
                <li class="fs-6 px-0"><?php _e('PHP Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold">
                  <?php
                    if (strnatcmp(phpversion(),'7.4.0') < 0)
                    {
                      echo phpversion().'<span class="has-tooltip-arrow wpext-help wp-extended-alert" data-tooltip="You should consider updating your PHP version"><span class="icon"><i class="fa-solid fa-circle-exclamation"></i></span></span>';
                    }
                    else
                    {
                      echo phpversion(); 
                    }
                  ?>  
                </li>
                    <?php 
                    $active_page_builder = '';
                    if ( is_plugin_active( 'elementor/elementor.php' ) ) {
                    // Elementor is active
                    $active_page_builder .= 'Elementor';
                    } 
                    if ( is_plugin_active( 'oxygen/functions.php' ) ) {
                    // oxygen is active
                    if($active_page_builder){
                      $active_page_builder .= ', Oxygen';
                    }else{
                      $active_page_builder .= 'Oxygen';
                    }   
                    } 
                    if ( is_plugin_active( 'breakdance/plugin.php' ) ) {
                    // breakdance is active
                    if($active_page_builder){
                      $active_page_builder .= ', Breakdance';
                    }else{
                      $active_page_builder .= 'Breakdance';
                    }
                    } 
                    if ( is_plugin_active( 'visualcomposer/plugin-wordpress.php' ) ) {
                    // visualcomposer is active
                    if($active_page_builder){
                      $active_page_builder .= ', Visual Composer';
                    }else{
                      $active_page_builder .= 'Visual Composer';
                    }
                    }
                    if($active_page_builder){ 
                    ?>
                      <li class="fs-6 px-0"> 
                          <?php _e('Active Page Builder:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php _e($active_page_builder, WP_EXTENDED_TEXT_DOMAIN);?></span>
                      </li>
                    <?php 
                    }
                    ?>
                <li class="fs-6 px-0"><?php _e('WordPress Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php bloginfo( 'version' ); ?></span></li>
              </ul>
            </div>
        </div>
        <!-- end System Info -->

        <!-- start support -->
        <div class="container bg-white text-dark p-0 pt-0 wpe_sidebar tab-content-setting" id="support" style="display:none;"> 
          <!-- Sidebar -->
            <div class="container bg-white text-dark p-0">
            <h5 class="text-black wpext_mb20"><?php _e('Support', WP_EXTENDED_TEXT_DOMAIN);?></h5>
              <ul class="list-group list-group-flush wpext_support_link">
                <li class="fs-6 px-0">
                  <a href="mailto:support@wpextended.io"><?php _e('support@wpextended.io ', WP_EXTENDED_TEXT_DOMAIN);?></a>
                </li>
                <li class="fs-6 px-0">
                  <a href="https://www.facebook.com/groups/wpextended" target="_blank"><?php _e('Facebook Group', WP_EXTENDED_TEXT_DOMAIN);?></a>
                </li>
                <li class="fs-6 px-0">
                  <a href="https://wpextended.io/docs" target="_blank"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a>
                </li>
                <li class="fs-6 px-0">
                  <a href="https://wpextended.io/changelog/" target="_blank"><?php _e('Changelog', WP_EXTENDED_TEXT_DOMAIN);?></a>
                </li>
                <li class="fs-6 px-0">
                  <a href="https://wpextended.io/blog/" target="_blank"><?php _e('Blog', WP_EXTENDED_TEXT_DOMAIN);?></a>
                </li>
              </ul>
            </div>
          <!-- Sidebar End here -->
        </div>
        <!-- end support -->
      </div>
   </div>   
    <!-- </div>    --> 
  <!-- end code -->    
    <?php
  }

  public function wpext_active_modules_callback(){
    check_ajax_referer('extended_obj', 'nonce');
    $module_action = sanitize_text_field($_REQUEST['status']);
    if(isset($module_action)){
      update_option('wpext_active_modules_status',$module_action);
    }
  }

}