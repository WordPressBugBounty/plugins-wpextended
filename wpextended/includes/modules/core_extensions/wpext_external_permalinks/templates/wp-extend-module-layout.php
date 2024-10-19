<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
do_action( "wpext_plugin_top_header" ); ?>
<div class="text-dark px-3 py-4 m-3 wpext_external_permalink">
   <?php $config = get_option(self::WPEXTEND_PERMALINK); ?>
   <form action="options.php" method="post" id="wp-extended-external_permalink-frm">
   <?php settings_fields( self::WPEXTEND_PERMALINK ); ?>
     <table class="table">
      <thead>
        <tr>
          <th><?php _e('Post Type', WP_EXTENDED_TEXT_DOMAIN); ?></th>
          <th></th>
        </tr>
      </thead>
       <tbody>
          <?php 
          $types_array = array('attachment', 'elementor_library', 'e-landing-page');
          $types = get_post_types(['public' => true], 'objects');
          foreach ($types as $type) {
              if (!in_array($type->name, $types_array)) {
                  if (isset($type->labels->name)) { ?>
                  <tr>
                    <td class="fw-normal align-middle wpext_font_size p-3 px-2 wpext_width_90">
                      <label role="button" for="flexSwitchCheckDefault_<?php echo esc_attr($type->name); ?>"><?php echo esc_html__($type->labels->name, WP_EXTENDED_TEXT_DOMAIN); ?></label>
                    </td>
                    <td class="fw-normal align-middle p-3">
                      <div class="form-check form-switch form-switch-md">
                        <input id="flexSwitchCheckDefault_<?php echo esc_attr($type->name); ?>"
                        name="<?php echo esc_attr(self::WPEXTEND_PERMALINK); ?>[<?php echo esc_attr($type->name); ?>][]"
                        type="checkbox" class="form-check-input"
                        <?php  if (isset($config[$type->name][0]) && sanitize_text_field($config[$type->name][0]) === 'on') { 
                            echo 'checked'; 
                        } ?> role="switch">
                      </div>
                    </td>
                  </tr>
                  <?php } 
              } 
          } ?>
        </tbody>
     </table>
     
   </form>
 </div>
<?php do_action( "wpext_plugin_footer" );?>