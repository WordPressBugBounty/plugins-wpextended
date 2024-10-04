<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
$wp_config = get_option( self::USER_LOGIN_ATTEMPT ); 
if (!is_array($wp_config) || $wp_config === false) {
   $wp_config = array();  
   $wp_config['login_attempts'] = 3;  
} else {
if (empty($wp_config['login_attempts']) && $wp_config['login_attempts'] !== 0) {
  $wp_config['login_attempts'] = 3; // Assign a default value of 3 if it's empty or false
}
}
if(empty($wp_config[ 'lockout_time' ])){
  $wp_config[ 'lockout_time' ] = 30;
}
if(!empty($wp_config[ 'login_attempts' ]) && !empty($wp_config[ 'lockout_time' ])){
   update_option('login_attempts', $wp_config[ 'login_attempts' ] );
   update_option('lockout_time', $wp_config[ 'lockout_time' ] );
}
?>
<?php do_action( "wpext_plugin_top_header" );?>
<div class="px-3 py-4 m-3" id="wp-extended-login-attempts"> 
   <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="check_limit" id="wp-extended_login_attempt-frm">
       <?php settings_fields(self::USER_LOGIN_ATTEMPT); ?>
       <div class="form-group py-2">
          <label for="login_attempt" class="label fw-normal wpext_font_size" role="button"><?php esc_html_e('Login Attempts', WP_EXTENDED_TEXT_DOMAIN); ?></label>
          <input type="number" 
             name="wpext-user-login-attempt-config[login_attempts]" 
             class="form-control" id="login_attempt"
             value="<?php echo esc_attr($wp_config['login_attempts']); ?>" min="1" placeholder="<?php esc_attr_e('Enter login limits', WP_EXTENDED_TEXT_DOMAIN); ?>" />
       </div>
       <div class="form-group">
          <label for="limit_time" class="label fw-normal wpext_font_size" role="button"><?php esc_html_e('Lockout Time (Minutes)', WP_EXTENDED_TEXT_DOMAIN); ?></label>
          <input type="number" 
             name="wpext-user-login-attempt-config[lockout_time]" 
             class="form-control" id="limit_time"
             value="<?php echo esc_attr($wp_config['lockout_time']); ?>" placeholder="<?php esc_attr_e('Enter time in minutes', WP_EXTENDED_TEXT_DOMAIN); ?>" />
       </div>
   </form>
</div>

<!-- Blocked User Listing -->
<?php require_once plugin_dir_path( dirname( __FILE__ ) ) . "wpext_limit_table.php"; ?>
<!-- Blocked User Listing End Here-->
<?php do_action( "wpext_plugin_footer" );?>