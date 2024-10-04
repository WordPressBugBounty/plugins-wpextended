<?php
if ( ! defined( 'ABSPATH' ) ) {
  die();
}
class Wp_Extended_Smtp extends Wp_Extended {
  private static $info = array();
  public function __construct() {
    parent::__construct();
    add_action('admin_init', array($this, 'wpext_smtp_register_settings'));
    add_action('admin_menu', array($this, 'wpext_smtp_settings_menu'));
    add_action('phpmailer_init', array($this, 'wpext_smtp_configure_wp_mail'));
    add_action('admin_enqueue_scripts', array( $this, 'wpext_login_log_scripts' ), 110 );
  }
  // Add a new settings page under the 'Settings' menu
  public function wpext_smtp_settings_menu() {
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_smtp_email', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_smtp_email'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

    if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
        add_submenu_page('wp-extended', __('WP Extended SMTP Settings', WP_EXTENDED_TEXT_DOMAIN), __('SMTP Email', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options','wp-extended_smtp-settings', array( get_called_class(), 'wp_extended_smtp_settings_page' ), null );
    }else{
       $capability = 'manage_options';
       $slug = 'wp-extended_smtp-settings';
       $callback = [$this, 'wp_extended_smtp_settings_page'];
       $icon = 'dashicons-admin-plugins';
       add_submenu_page('', '', '', $capability, $slug, $callback);
       add_rewrite_rule('^wp-extended_smtp-settings/?', 'index.php?wp_extended_smtp-settings=1', 'top');
       add_rewrite_tag('%wp_extended_smtp-settings%', '([^&]+)');
    }
  }
  public function wpext_login_log_scripts(){
    $screen = get_current_screen();  
    if($screen->base == "wp-extended_page_wp-extended_smtp-settings" || $screen->base == 'admin_page_wp-extended_smtp-settings') {
      wp_enqueue_style( 'wp-extended-log-css', plugin_dir_url( __FILE__ ) . "css/wpext_log_record.css", array(), WP_EXTENDED_VERSION ); 
      wp_enqueue_script( 'wpext_log_details', plugin_dir_url( __FILE__ ) . "/js/wpext_wpext_log_details.js", array(), WP_EXTENDED_VERSION );    
      $hide_byuser_role  = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'ajax_nonce' => wp_create_nonce( 'user-roles' ),
        );
        wp_localize_script( 'wpext_log_details', 'log_ajax_obj', $hide_byuser_role);
    }
  }

  // Register plugin settings
  public function wpext_smtp_register_settings() {
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_from_name');
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_from_email');
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_host');
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_port');
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_username');
    register_setting('wpext-smtp-settings-group', 'wpext_smtp_password');
    register_setting('wpext-smtp-settings-group', 'smtp_post_number');
  }

  // Render the settings page
  public static function wp_extended_smtp_settings_page() {
    ?>
    <div class="container-fluid wpe_brand_header">
      <div class="container ps-2 p-4">
        <div class="row">
           <div class="col-sm-8 col-md-6 ps-0">
              <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e( "WP Extended SMTP Email", WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
           </div>
           <?php do_action( "admin_plugin_top_info" );?>
        </div>
      </div>
    </div>

    <?php do_action('wpext_module_save_btn_header'); ?>

    <?php 
    if (isset($_POST['wpext_smtp_test']) && $_POST['wpext_smtp_test'] == 1) {
      if (!wp_verify_nonce($_POST['wpext_smtp_test_nonce'], 'wpext_smtp_test_nonce')) {
        wp_die('Invalid nonce');
      }
      if($_POST['wpe_email_smtp_test']){
        $test_email = sanitize_email($_POST['wpe_email_smtp_test']); // Enter your test email address here
        $mail_result = self::wpext_smtp_send_test_email($test_email);
        $mailinfo = self::check_the_valid_message(self::$info);

        $successmessage = __('Congratulations!!!Test email sent successfully to', WP_EXTENDED_TEXT_DOMAIN);
        $failedmessage = __('Failed to send test email. Error:', WP_EXTENDED_TEXT_DOMAIN);
        if ($mailinfo[0] != 'error') {
          ?>
          <div class="container wpext-container wpext_success_message_container">
            <div class="row">
              <div class="wpext-success-message rounded-2">
                <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php echo $successmessage.' '.$test_email; ?>
              </div>
            </div>
          </div>
        <?php } else { ?>
          <div class="container wpext-container wpext_fail_message_container">
            <div class="row">
              <div class="wpext-fail-message rounded-2">
                <span>&#x2715; <?php _e('Whoops!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php echo $failedmessage.' '.$mail_result; ?>
              </div>
            </div>
          </div>
        <?php }
      } 
    } ?>

    <style type="text/css">
      .wpext-notice{display: none;} /* temp disabling the annoying notice */
    </style>

    <div class="container wpext-container wpext_smtp_layout" id="wp-extended-app">
    <div class="row">
      <div class="col-sm-12 gx-5 mb-3 bg-white p-0 rounded-2 log_table">
        <div class="container bg-white text-dark border rounded-2 wpext_toggle_settings">

        <div class=" container text-dark rounded-2 setting_section px-3 py-5 mx-1 active">
         <div class="container wpext_ptpb30 wpext-test-email">
          <div class="row">
            <div class="tab col-sm-3 wpext_smtp_options">
              <button class="tablinks active" id="wpext_smtpconfig"><?php _e('SMTP Config', WP_EXTENDED_TEXT_DOMAIN); ?></button>
              <button class="tablinks" id="wpext_emailtest"><?php _e('Email Test', WP_EXTENDED_TEXT_DOMAIN); ?></button>
              <button class="tablinks" id="wpext_emailog"><?php _e('Email Log', WP_EXTENDED_TEXT_DOMAIN); ?></button>
            </div>
            <div class="tab-content col-sm-9 px-1">
            <form method="post" action="options.php" class="form-table" id="wp-extended_smtp-settings-frm">
            <?php settings_fields('wpext-smtp-settings-group'); ?>
            <?php do_settings_sections('wpext-smtp-settings-group'); ?>
            <div class="wpext_smtp-config border rounded-2 mx-2 active">
              <div class="row">
                <div class="col-sm">
                 <div class="form-group">
                    <label for="wpext_smtp_from_name" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('From Name', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                      <input type="text" name="wpext_smtp_from_name" value="<?php echo esc_attr(get_option('wpext_smtp_from_name')); ?>" class="form-control wpext_smtp_config_from" id="wpext_smtp_from_name"/>
                  </div>
                </div>
                <?php 
                  $wpext_from_email = get_option('wpext_smtp_from_email');
                  if(!empty($wpext_from_email)){
                    $wpext_email_from = $wpext_from_email;
                  }else{
                     $wpext_email_from = get_option('admin_email');
                  }
                  ?>
                <div class="col-sm">
                 <div class="form-group">
                     <label for="wpext_smtp_from_email" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('From Email', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                      <input type="email" name="wpext_smtp_from_email" value="<?php echo esc_attr($wpext_email_from); ?>" class="form-control" id="wpext_smtp_from_email"/>
                  </div>
                </div>
              </div>
              <div class="row smtp_row pt-3">  
                <div class="col-sm">
                  <div class="form-group">
                      <label for="smtp_host" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('SMTP Host', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                        <input type="text" name="wpext_smtp_host" value="<?php echo esc_attr(get_option('wpext_smtp_host')); ?>" class="form-control wpext_smtp_config_from" id="smtp_host" />
                    </div>
                </div>
                <div class="col-sm">
                  <div class="form-group">
                      <label for="smtp_post" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('SMTP Port', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                    <input type="text" name="wpext_smtp_port" value="<?php echo esc_attr(get_option('wpext_smtp_port')); ?>" class="form-control wpext_smtp_config_from" id="smtp_post"/>
                  </div>
                </div>
              </div>
              <?php $smtp_number = esc_attr(get_option('wpext_smtp_port')); ?>
              <div class="row encryption_type pt-3">
                <div class="col-sm">
                  <div class="form-group col-12">
                      <label for="smtp_port_title" class="label fw-normal p-1 px-0 wpext_font_size"><?php _e('Encryption Type', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                    </div> 
                    <div class="form-group col-12">
                      <div class="smtp-port form-check-inline">
                        <div class="form-group wp-picker-container field form-check form-switch form-switch-md">
                        <input type="radio" name="smtp_post_number" value="25" class="form-check-input wpext_smtp_config_from" id="smtp_post_zero" require 
                        <?php if(!empty($smtp_number) && $smtp_number != '465' && $smtp_number != '587') { echo 'checked'; } ?> />
                        <label for="smtp_post_zero" class="form-check-label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('None', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                        </div>
                      </div> 
                      <div class="smtp-port form-check-inline">
                        <div class="form-group wp-picker-container field form-check form-switch form-switch-md">
                        <input type="radio" name="smtp_post_number" value="465" class="form-check-input wpext_smtp_config_from" id="smtp_post_ssl" require <?php if(!empty($smtp_number) && $smtp_number == '465') { echo 'checked'; } ?> /> 
                        <label for="smtp_post_ssl" class="form-check-label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('SSL', WP_EXTENDED_TEXT_DOMAIN); ?></label>  
                      </div>
                      </div> 
                      <div class="smtp-port form-check-inline">
                        <div class="form-group wp-picker-container field form-check form-switch form-switch-md">
                          <input type="radio" name="smtp_post_number" value="587" class="form-check-input wpext_smtp_config_from" id="smtp_post_tls" <?php if(!empty($smtp_number) && $smtp_number == '587') { echo 'checked'; } ?>
                          />
                          <label for="smtp_post_tls" class="form-check-label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('TLS', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                        </div>
                      </div>
                    </div>

                </div>
              </div>
              <div class="row smtp_username_password pt-3">
                <div class="col-sm">
                  <div class="form-group">
                    <label for="smtp_username" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('SMTP Username', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                    <input type="text" name="wpext_smtp_username" value="<?php echo esc_attr(get_option('wpext_smtp_username')); ?>" class="form-control wpext_smtp_config_from" id="smtp_username" />
                  </div>
                </div>
                <div class="col-sm">
                  <div class="form-group">
                    <label for="smtp_password" class="label fw-normal p-1 px-0 wpext_font_size" role="button"><?php _e('SMTP Password', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                    <input type="password" name="wpext_smtp_password" value="<?php echo esc_attr(get_option('wpext_smtp_password')); ?>" class="form-control wpext_smtp_config_from" id="smtp_password" />
                  </div>
                </div>
              </div>
            </div>

            <div class="wpext_smtp_email_log wpext_smtp_log mx-2">
              <div class="row">
                <div class="col-sm">
                  <?php echo self::log_view(); ?>
                </div>
              </div>
            </div>

           </form>
          <div class="wpext_smtp_test mx-2">
            <div class="container p-0 wpext-test-email">
              <form method="post" action="">
                <div class="row">
                  <div class="form-group pb-2">
                      <label class="label label-default form-table wpext_font_size" for="smtp_test" role="button"><?php _e('Email Address', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                  </div>
                    <div class="col-sm-8">
                    <input type="email" name="wpe_email_smtp_test" value="<?php echo wp_get_current_user()->user_email; ?>" class="form-control" id="smtp_test" 
                      placeholder="<?php _e('Enter test email', WP_EXTENDED_TEXT_DOMAIN); ?>" required>
                      <?php wp_nonce_field('wpext_smtp_test_nonce', 'wpext_smtp_test_nonce'); ?>
                      <input type="hidden" name="wpext_smtp_test" value="1">
                    </div>
                  <div class="col-sm-4"> <input type="submit" class="wp-ext-btn-sec" value="<?php _e('Send', WP_EXTENDED_TEXT_DOMAIN); ?>"> </div>
                </div>
              </form>
            </div>
        </div>
      </div>
      </div> 
    </div>
    </div>
    </div>
  </div>
  </div>
  </div>
    <?php
  }

  // Configure WordPress to use wpext SMTP settings
  public function wpext_smtp_configure_wp_mail($phpmailer) {
    $from_name = get_option('wpext_smtp_from_name');
    $from_email = get_option('wpext_smtp_from_email');
    $smtp_host = get_option('wpext_smtp_host');
    $smtp_port = get_option('wpext_smtp_port');
    $smtp_username = get_option('wpext_smtp_username');
    $smtp_password = get_option('wpext_smtp_password');
    if ( empty( $smtp_username ) || empty( $smtp_host ) || empty( $smtp_password ) ) {
      return;
    }
    if(empty($from_email)){
      $from_email = get_option('admin_email');
    }
    $phpmailer->isSMTP();
    $phpmailer->SMTPAuth = true; 
    //$phpmailer->From = $smtp_username;

    $phpmailer->Sender = $smtp_username;   
    $phpmailer->SetFrom($from_email, $from_name, FALSE); 

    $phpmailer->Host = $smtp_host;
    $phpmailer->Port = $smtp_port;
    if($smtp_port == 465){
      $phpmailer->SMTPSecure = 'ssl';
    }else{
      $phpmailer->SMTPSecure = 'tls';
    }
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = $smtp_username;
    $phpmailer->Password = $smtp_password;
    $phpmailer->Timeout = 10; 

    // Maybe override FROM email and/or name if the sender is "WordPress <wordpress@sitedomain.com>", the default from WordPress core and not yet overridden by another plugin.
    if(empty($from_email)){
     $default_from_name = $phpmailer->FromName;
      if ( ( 'WordPress' === $default_from_name ) && ! empty( $from_name ) ) {
        $phpmailer->FromName = $from_name;
      }
      $from_email_as_wordpress = substr( $phpmailer->From, 0, 0 ); // Get the first 9 characters of the current FROM email
      if ( ( 'wordpress' === $from_email_as_wordpress ) && ! empty( $from_name ) ) {
        $phpmailer->From = $from_name;
      }
    }
    $phpmailer->XMailer   = 'WP Extended v' . WP_EXTENDED_VERSION . ' - a WordPress plugin';
  }
  
  // Send a test email
  public static function wpext_smtp_send_test_email($email, $errorReason = '') {
    $subject = __('Test Email from WP Extended SMTP Module', WP_EXTENDED_TEXT_DOMAIN);
    $message = self::get_test_email();
    $from_name = get_option('wpext_smtp_from_name'); 
    $smtp_username = get_option('wpext_smtp_username');
    $headers = array('Content-Type: text/html; charset=UTF-8');

    if(!empty($errorReason)){
      $message .= " </br> Error Message : ".$errorReason;
    }

    $result = wp_mail($email, $subject, $message, $headers);
    global $wpdb, $phpmailer; 
    if ($result) {
      $error = __('Success', WP_EXTENDED_TEXT_DOMAIN);
      $message_result = "";
      self::check_the_valid_message($mailmessage = 'success');
    } else {
      $message = $phpmailer->Body;
      $message_result = $message;
      $from = $phpmailer->From;
      $from_name = $phpmailer->FromName;
      $error = $phpmailer->ErrorInfo;
      
      //error store into database
      $error_reason = $error;

      $error = __('Fail', WP_EXTENDED_TEXT_DOMAIN);
      self::check_the_valid_message($mailmessage = 'error');
    }

    if ( isset( $phpmailer->ErrorInfo ) && ! empty( $phpmailer->ErrorInfo ) ) {
        $message .= ' - Error: ' . $phpmailer->ErrorInfo;
    }
    
    return true;
  }

  public static function get_test_email()
    {
        $user = wp_get_current_user();
        $username = $user->user_login;
        $first_name = $user->first_name != '' ? $user->first_name : $username;

        $data = array(
          'user' => array(
            'username' => $username,
            'first_name' => $first_name,
          ),
          'time' => current_time('mysql'),
          'website' => get_bloginfo('url'),
        );

        ob_start();
        include dirname(__FILE__). '/test-email.php';
        $content = ob_get_clean();
        return $content;
    }

  public static function check_the_valid_message($mailmessage){
    if(!empty($mailmessage)){
     self::$info[] = $mailmessage;
     } 
     return self::$info;
  }

  public static function log_view() { ?>
    <div class=" bg-white text-dark log-section">
      <div class="row">
        <div class="col-sm-12 wpext_email_log_chart">
          <img src="<?php echo plugin_dir_url( __FILE__ ) . '/img/wpext_log.png'; ?>" >
        </div>
      </div>
    </div>
    <?php
  }

}
new Wp_Extended_Smtp();