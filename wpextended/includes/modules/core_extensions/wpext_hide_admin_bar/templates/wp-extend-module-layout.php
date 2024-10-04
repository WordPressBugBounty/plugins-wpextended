<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
?>
<?php do_action( "wpext_plugin_top_header" );?>
<div class=" text-dark px-3 py-4 m-3 wpext_hide_admin_bar">
  <?php $config = get_option(self::WPEXT_HIDEADMIN_BAR); ?>
    <form action="options.php" method="post" id="wp-extended-hide-adminbar-frm">
      <?php settings_fields( self::WPEXT_HIDEADMIN_BAR ); ?>
        <table class="table">
          <thead>
            <tr>
              <th><?php _e('Role Type', WP_EXTENDED_TEXT_DOMAIN ); ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php $roles = array( 'editor','author','contributor','subscriber');
              $currentuser = wp_get_current_user();
              foreach ($roles as $type ) {
                if(!empty($type)) { ?>
              <tr>
                <td class="wpext_font_size fw-normal align-middle p-3 px-2" style="width: 90%;">                  
                  <label role="button" for="<?php echo self::WPEXT_HIDEADMIN_BAR;?>-<?php echo $type; ?>"><?php _e( ucfirst($type), WP_EXTENDED_TEXT_DOMAIN );?></label>
                </td>
                <td class="p-3">
                  <div class="form-check form-switch form-switch-md">
                    <input role="switch" id="<?php echo self::WPEXT_HIDEADMIN_BAR;?>-<?php echo $type; ?>" name="<?php echo self::WPEXT_HIDEADMIN_BAR;?>[<?php echo $type; ?>]" type="checkbox" value="<?php echo $type; ?>" class="form-check-input" <?php if(!empty($config[$type])) { echo "checked"; } ?>> </div>
                </td>
              </tr>
              <?php }     
                } ?>
          </tbody>
        </table>
    </form>
</div>
<?php do_action( "wpext_plugin_footer" );?>