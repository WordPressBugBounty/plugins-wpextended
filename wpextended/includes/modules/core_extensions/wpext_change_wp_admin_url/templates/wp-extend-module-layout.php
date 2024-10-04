<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
$wpext_admin_color_tab = array( 'admin_bar' => 'Admin Bar', 'side_bar' => 'Side Bar',);
$i = 0;

do_action( "wpext_plugin_top_header" );?>
 
  <div class="px-3 py-4 m-3 text-dark gap-3 wpext_custom_login_url">
    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="check_limit admin_url" id="wp-extended-changes-wp-admin-url-frm">
        <?php settings_fields( self::WPEXT_CHANGE_WP_ADMIN_URL ); ?>
        <div class="form-group"> 
            <label for="wpext_login_url" class="fw-normal wpext_font_size" role="button"><?php _e( 'New Admin URL Slug', WP_EXTENDED_TEXT_DOMAIN );?></label>
            <div class="input-group">
              <span class="input-group-text" id="basic-addon3"><?php echo esc_url( site_url() ); ?>/</span>
              <input type="text" name="<?php echo esc_attr( self::WPEXT_CHANGE_WP_ADMIN_URL ); ?>[wpext_login_url]" 
                    class="form-control wpext_font_size" id="wpext_login_url"
                    value="<?php echo !empty( $wp_config['wpext_login_url'] ) ? esc_attr( $wp_config['wpext_login_url'] ) : ''; ?>" placeholder='Enter wp-admin url'/>
            </div>
            <div class="form-text" id="wpxt-google-analitic">
              <span class="label label-default"><?php _e('Your new login url:', WP_EXTENDED_TEXT_DOMAIN); ?>
              <b><?php echo esc_url( site_url() ); ?>/<span id="real-text"><?php echo !empty( $wp_config['wpext_login_url'] ) ? esc_html( $wp_config['wpext_login_url'] ) : 'wp-admin'; ?></span></span></b>
            </div>
        </div>
        <div class="form-group pt-2">
            <label for="wpext_redirect_url" class="fw-normal wpext_font_size" role="button"><?php _e('Redirect URL ', WP_EXTENDED_TEXT_DOMAIN);?>
              <a href="javascript:void('0');" data-toggle="tooltip" data-placement="right" class="tooltip-text" data-bs-original-title="<?php _e("If you leave the field blank, accessing /wp-admin will result in a redirection to the existing homepage.",WP_EXTENDED_TEXT_DOMAIN); ?>">
               <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" width="65%" height="65%"><path d="M256 512c141.4 0 256-114.6 256-256S397.4 0 256 0S0 114.6 0 256S114.6 512 256 512zM168 184c0-30.9 25.1-56 56-56h56.9c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4V288H232V264 250.5 236.6l12.1-6.9 44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H224c-4.4 0-8 3.6-8 8l0 6.5-48 0V184zm64 184V320h48v48H232z"></path></svg></a>
            </label>
            <input type="text" name="<?php echo esc_attr( self::WPEXT_CHANGE_WP_ADMIN_URL ); ?>[wpext_redirect_url]" 
                   class="form-control wpext_font_size" id="wpext_redirect_url"
                   value="<?php echo !empty( $wp_config['wpext_redirect_url'] ) ? esc_url( $wp_config['wpext_redirect_url'] ) : ''; ?>" placeholder='Redirect URL' />
            <div class="form-text" id="wpxt-google-analitic">
                <span class="label label-default"><?php _e('The full page URL where the user is redirected if accessing wp-admin directly', WP_EXTENDED_TEXT_DOMAIN); ?></span>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
   jQuery(function () {
     jQuery('[data-toggle="tooltip"]').tooltip();
   });
    jQuery(document).ready(function(){
     jQuery('#wpext_login_url').keyup(function() {
        var thisValue = jQuery(this).val();
        if (thisValue === "") {
          jQuery('#real-text').text("wp-admin");
        } else {
          jQuery('#real-text').text(thisValue);
        }
       if(jQuery(this).val() == 'wp-admin'){
         jQuery('.wp-extended_page_wp-extended-changes-wp-admin-url .admin_url input#submit').addClass('disabled');
       }else{
         jQuery('.wp-extended_page_wp-extended-changes-wp-admin-url .admin_url input#submit').removeClass('disabled');
       }
     });
    });
</script>
<?php do_action( "wpext_plugin_footer" );?>