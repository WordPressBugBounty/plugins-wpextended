<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
 $user_profile_fields = array(
      'user-admin-color-wrap'           => __('Admin Color Scheme', WP_EXTENDED_TEXT_DOMAIN ),
      'user-admin-bar-front-wrap'       => __('Toolbar', WP_EXTENDED_TEXT_DOMAIN ),
      'user-description-wrap'           => __('Biographical Info', WP_EXTENDED_TEXT_DOMAIN ),
      'user-role-wrap'                  => __('Role', WP_EXTENDED_TEXT_DOMAIN ),
      'user-email-wrap'                 => __('Email', WP_EXTENDED_TEXT_DOMAIN ),
      'user-pass1-wrap'                 => __('New Password', WP_EXTENDED_TEXT_DOMAIN ),
      'user-generate-reset-link-wrap'   => __('Reset Password', WP_EXTENDED_TEXT_DOMAIN ),
    ); 
?>
<?php do_action( "wpext_plugin_top_header" );?>
   <div class="text-dark px-3 py-4 m-3 wpext_clean_profile">
      <form action="options.php" method="post" id="wp-extended-user-sections-frm">
         <?php settings_fields( 'wpext-user-sections' ); ?>
           <table class="table">
            <thead>
              <tr>
                <th><?php _e('Profile Section', WP_EXTENDED_TEXT_DOMAIN); ?></th>
                <th></th>
              </tr>
            </thead>
             <?php $user_option = get_option( 'wpext-user-sections'); 
                if(isset($user_option)){ $option = $user_option; } ?>
               <tbody>
                 <?php foreach( $user_profile_fields as $fields_slug => $fields_name ): ?>
                   <tr>
                     <td class="fw-normal wpext_font_size align-middle p-3 px-2 wpext_width_90"> 
                     <label role="button" for="profile_<?php echo $fields_slug; ?>"><?php _e( $fields_name, WP_EXTENDED_TEXT_DOMAIN );?></label>
                     </td>
                     <td class="p-3">
                       <div class="form-check form-switch form-switch-md">
                         <input id="<?php _e("profile_".$fields_slug, WP_EXTENDED_TEXT_DOMAIN );?>" name="wpext-user-sections[<?php _e($fields_slug, WP_EXTENDED_TEXT_DOMAIN );?>]" type="checkbox" class="form-check-input" <?php if(isset($option[ $fields_slug ]) == 'on') echo 'checked'; ?> role="switch">
                       </div>
                     </td>
                   </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
      </form>
</div>
<?php do_action( "wpext_plugin_footer" );?>