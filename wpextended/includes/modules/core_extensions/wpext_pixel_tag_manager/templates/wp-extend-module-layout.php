<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
?>
<?php do_action( "wpext_plugin_top_header" );?>
<div class="text-dark px-4 py-4 m-2 wpext_pixel_tag_manager">
   <form name="pixel" method="post" action="<?php echo admin_url( 'options.php' );?>" class="wpext-pixel-tag" id="wp-extended-pixel-tag-manager-frm">
     <?php settings_fields( self::WPEXT_GOOGLE_ANALITIC ); ?>
       <?php $pixeldata = get_option(self::WPEXT_GOOGLE_ANALITIC);  ?>
         <div class="row pt-3">
           <div class="mb-3">
             <div class="input-group"> <span class="input-group-text" id="wpxt-google-analitic"><img src="<?php echo plugin_dir_url( dirname( __FILE__ )  ) ?>img/wpext_analytics.png"></span>
               <input class="form-control" type="text" name="wpext-pixel-tag[wpxt-google-analitic]" placeholder="<?php _e('Google Analytics tracking ID',WP_EXTENDED_TEXT_DOMAIN); ?>" id="wpxt-google-analitic" value="<?php if(isset($pixeldata['wpxt-google-analitic'])) { echo $pixeldata['wpxt-google-analitic']; }  ?>"> </div>
             <div class="form-text" id="wpxt-google-analitic"><a target="_blank" href="https://support.google.com/analytics/answer/9539598?hl=en" class="fw-normal wpext_link_action wpext_font_size"><?php _e('How to find the tracking ID', WP_EXTENDED_TEXT_DOMAIN); ?></a></div>
           </div>
           <div class="mb-3">
             <div class="input-group"> <span class="input-group-text" id="wpxt-facebook-pixel"><img src="<?php echo plugin_dir_url( dirname( __FILE__ )  ) ?>img/wpext-fb.png"></span>
               <input class="form-control" type="text" name="wpext-pixel-tag[wpext-facebook]" placeholder="<?php _e('Facebook Pixel ID', WP_EXTENDED_TEXT_DOMAIN); ?>" id="wpxt-facebook-pixel" value="<?php if(isset($pixeldata['wpext-facebook'])) { echo $pixeldata['wpext-facebook']; } ?>"> </div>
             <div class="form-text" id="wpxt-facebook-pixel"><a target="_blank" href="https://en-gb.facebook.com/business/help/952192354843755?id=1205376682832142" class="fw-normal wpext_font_size wpext_link_action"><?php _e('How to find the Facebook pixel ID', WP_EXTENDED_TEXT_DOMAIN); ?></a></div>
           </div>
           <div class="pb-3">
             <div class="input-group"> <span class="input-group-text" id="wpext-pintrest"><img width="20" src="<?php echo plugin_dir_url( dirname( __FILE__ )  ) ?>img/pintrest.png"></span>
               <input class="form-control" type="text" name="wpext-pixel-tag[wpext-pintrest]" placeholder="<?php _e('Pintrest Pixel ID',WP_EXTENDED_TEXT_DOMAIN); ?>" id="wpext-pintrest" value="<?php if(isset($pixeldata['wpext-pintrest'])) { echo $pixeldata['wpext-pintrest']; } ?>"> </div>
             <div class="form-text" id="wpext-pintrest"><a target="_blank" href="https://help.pinterest.com/en/business/article/install-the-pinterest-tag" class="fw-normal wpext_font_size wpext_link_action"><?php _e('How to find the Pinterest pixel ID',WP_EXTENDED_TEXT_DOMAIN); ?></a></div>
           </div>
         </div>
   </form>
 </div>
<?php do_action( "wpext_plugin_footer" );?>