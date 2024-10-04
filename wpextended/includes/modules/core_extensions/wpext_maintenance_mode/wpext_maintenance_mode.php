<?php

if ( ! defined( 'ABSPATH' ) ) {
   die();
}

class Wp_Extended_Maintenance_Mode extends Wp_Extended {
  
  const WPEXT_MAINTANAMCE_MODE = 'wpext-maintanance_mode';
  public function __construct() {
    parent::__construct();
    add_action( 'admin_init', array( $this, 'wpext_maintinance_settings') );
    add_action('admin_bar_menu', array($this, 'wpext_maintenance_mode_admin_bar'), 99999);
    add_action('admin_menu', array($this,'wpext_maintenance_mode_admin_configuration'));
    add_action('admin_enqueue_scripts', array( $this, 'wpext_admin_maintinance_scripts' ), 110 );
    add_action('admin_init',  array($this,'wpext_echeck_default_status') );
    add_filter( 'theme_page_templates', array( $this, 'add_maintenance_template' ) );    
    add_action('template_include', array($this, 'wpext_template_include'), 999999);
  }
  
  public static function init(){
    static $instance = null;

    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Maintenance_Mode( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
 
  public function wpext_admin_maintinance_scripts(){
    $screen = get_current_screen();
    if(strpos($screen->id, "wp-extended-maintenance-mode")){
      wp_enqueue_script( 'wp-extended_jquery_wpext_maintenance_mode', plugin_dir_url( __FILE__ ) . "js/wpext_maintenance_mode.js", array(), WP_EXTENDED_VERSION );
      wp_enqueue_style( 'wp-extended_jquery_wpext_maintenance_css', plugin_dir_url( __FILE__ ) . "admin/css/wpext_maintenance_admin.css", array(), WP_EXTENDED_VERSION );
      wp_enqueue_media();
      wp_enqueue_script('wp-color-picker');
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_code_editor(array('type' => 'text/css'));
    }
  }
  public static function wpext_maintinance_settings(){
    register_setting( self::WPEXT_MAINTANAMCE_MODE, self::WPEXT_MAINTANAMCE_MODE,  array( 'type' => 'array' ) );
  }
  public function wpext_maintenance_mode_admin_bar(){
  global $wp_admin_bar, $wpdb;
  if (!is_super_admin() || !is_admin_bar_showing()) {
    return;
  }
  $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE ); 
   if(!empty($mentinance_record['setting'])){
    $url_to = admin_url('admin.php?page=wp-extended-maintenance-mode');
    $wp_admin_bar->add_menu(
      array(
        'id'    => 'maintenance_options',
        'title' => __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN),
        'href'  => $url_to,
        'meta'  => array(
          'title' => __( 'Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN )
        ),
      )
     );
    }
   }

/**
 * 
 * Creating menu (maintenance Mode) admin configuration.
 * 
 * */

public function wpext_maintenance_mode_admin_configuration(){
   $menustatus = get_option('wpext_show_plugin_menu_action');
   $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_maintenance_mode', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_maintenance_mode'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

   if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) { 
      add_submenu_page('wp-extended', __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN), __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options','wp-extended-maintenance-mode', array( get_called_class(), 'settings_admin_maintenance_mode' ), null );
   }else{
      $capability = 'manage_options';
      $slug = 'wp-extended-maintenance-mode';
      $callback = [ get_called_class(), 'settings_admin_maintenance_mode'];
      add_submenu_page('', '', '', $capability, $slug, $callback);
      add_rewrite_rule('^wp-extended-maintenance-mode/?', 'index.php?wp_extended_maintenance_mode=1', 'top');
      add_rewrite_tag('%wp_extended_maintenance_mode%', '([^&]+)');
   }
}

public static function settings_admin_maintenance_mode(){ 
?>
<div class="container-fluid wpe_brand_header">
  <div class="container ps-2 p-4">
     <div class="row">
        <div class="col-sm-8 col-md-6 ps-0">
           <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e( "WP Extended Maintenance Mode", WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
        </div>
        <?php do_action( "admin_plugin_top_info" );?>
     </div>
  </div>
 </div>

<div class="container-fluid wp_brand_sub_header">
   <div class="container">
     <div class="row align-items-baseline">
       <div class="col-lg-6 px-1"><p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="https://wpextended.io/docs/" target="_blank" class="wp_brand_sub_header_back_document"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p></div>
       <div class="col-lg-6 wp_brand_sub_header_right mx-lg-0">
         <?php $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE );  ?>
          <div class="wpext-return-module-wrap">
            <a class="wpext-return-module wp-ext-btn-sec me-2" aria-current="page" href="<?php echo home_url(); ?>/?wpext_maintenance_preview" target="_blank"><?php _e("Preview", WP_EXTENDED_TEXT_DOMAIN); ?></a>
             <button class="wpext_module_action wp-ext-btn-prim" form="wp-extended-maintenance-mode-frm"><?php _e('Save Changes', WP_EXTENDED_TEXT_DOMAIN);?></button>
          </div>
       </div>
     </div>
   </div>
</div>  

<?php if (isset($_GET["settings-updated"]) && $_GET["settings-updated"]) { ?>
  <!-- Code for a success message on save -->
  <div class="container wpext-container wpext_success_message_container">
    <div class="row">
      <div class="wpext-success-message rounded-2">
        <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('Your settings have been saved successfully!', WP_EXTENDED_TEXT_DOMAIN ); ?>
      </div>
    </div>
  </div>
<?php } ?>

<div class="container wpext-container wpext_maintenance_layout" id="wp-extended-app">
   <div class="row">
      <div class="col-lg-12 gx-5 mb-3 bg-white p-0 rounded-2"> 
         <?php $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE );  ?>
         <div class="container border border-dark rounded-2 p-3">
            <form method="post" action="<?php echo admin_url( 'options.php' );?>" class="coming_soon" id="wp-extended-maintenance-mode-frm">
               <?php settings_fields( self::WPEXT_MAINTANAMCE_MODE ); ?> 
                
               <div id="layout_text_section" >
                 <div class="row mx-lg-4 py-4 border-bottom">
                    <div class="col-lg-3">
                       <h6><label for="site_heading"><?php _e('Headline', WP_EXTENDED_TEXT_DOMAIN ); ?></label></h6>
                       <p class="small"><?php _e('Enter the page headline in to the field provided.', WP_EXTENDED_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="col-lg-9">
                       <div class="row">
                          <div class="col-lg-6">
                             <input type="text" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[site_heading]" class="form-control mb-3 input_maintenance_mode_fields" id="site_heading" 
                             placeholder="Example input placeholder" value="<?php if(isset($mentinance_record['site_heading'])) { echo $mentinance_record['site_heading']; } ?>" required>
                             <input type="text" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[headline_color]" class="form-control wp-color-picker" 
                                id="headline_color" value="<?php if(isset($mentinance_record['headline_color']) && !empty($mentinance_record['headline_color'])) { echo $mentinance_record['headline_color']; } else{ echo "#000000"; } ?>" title="Choose your color" data-default-color="#000000"/>
                          </div>
                       </div>                     
                    </div>
                 </div>   
                 <div class="row mx-lg-4 py-4 border-bottom">
                    <div class="col-lg-3">
                       <h6><label for="wpext-maintanance_mode_discription"><?php _e('Body Text', WP_EXTENDED_TEXT_DOMAIN);?></label></h6>
                       <p class="small"><?php _e('Enter the body text in to the field provided.', WP_EXTENDED_TEXT_DOMAIN);?></p>
                    </div>
                    <div class="col-lg-9">
                       <div class="row">
                          <div class="col-lg-6">
                              <?php $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE ); ?>
                            <textarea name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[discription]" rows="3" cols="3" class="form-control mb-3 input_maintenance_mode_fields" id="wpext-maintanance_mode_discription"><?php if(!empty($mentinance_record['discription'])) { echo $mentinance_record['discription']; } ?></textarea>
                             <input type="text" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[description_color]" class="form-control wp-color-picker" 
                                id="description_color" value="<?php if(isset($mentinance_record['description_color']) && !empty($mentinance_record['description_color']) ) { echo $mentinance_record['description_color']; } else{ echo "#000000"; } ?>" title="Choose your color" data-default-color="#000000"/>
                          </div>
                       </div>                     
                    </div>
                 </div>

                 <div class="row mx-lg-4 py-4 border-bottom">
                    <div class="col-lg-3">
                       <h6><label for="footer_text"><?php _e('Footer Text', WP_EXTENDED_TEXT_DOMAIN);?></label></h6>
                       <p class="small"><?php _e('Enter the page footer text in to the field provided.', WP_EXTENDED_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="col-lg-9">
                       <div class="row">
                          <div class="col-lg-6">
                             <input type="text" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[footer_text]" class="form-control mb-3 input_maintenance_mode_fields" id="footer_text" value="<?php if(isset($mentinance_record['footer_text'])) { echo $mentinance_record['footer_text']; } ?>" placeholder="<?php _e('Footer Text', WP_EXTENDED_TEXT_DOMAIN);?>"/> 
                             <input type="text" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[footer_text_color]" class="form-control wp-color-picker" 
                             id="footer_text_color" value="<?php if(isset($mentinance_record['footer_text_color']) && !empty($mentinance_record['footer_text_color'])) { echo $mentinance_record['footer_text_color']; } else{ echo "#000000"; } ?>" title="Choose your color" data-default-color="#000000"/>
                          </div>
                       </div>                     
                    </div>
                 </div>                                                

                 <div class="row mx-lg-4 py-4 border-bottom">
                 
                 <div class="row pb-2">
                    <div class="col-lg-3">
                       <h6><label for="wpext_mm_logo_option"><?php _e('Logo', WP_EXTENDED_TEXT_DOMAIN); ?></label></h6>
                       <p class="small"><?php _e('Upload or select the website logo to display on the page.',WP_EXTENDED_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="col-lg-9">
                       <div class="row">
                          <div class="col-lg-6">
                             <?php $selected_logo = isset($mentinance_record['wpext_logo_option']) ? $mentinance_record['wpext_logo_option'] : ''; ?>
                             <select class="form-select mb-3" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[wpext_logo_option]" id="wpext_mm_logo_option">
                               <option value="1" <?php selected('1', $selected_logo); ?>><?php _e('Yes', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                               <option value="2" <?php selected('2', $selected_logo); ?>><?php _e('No', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                             </select>
                             <div class="card form-control 
                             <?php if(isset($mentinance_record['wpext_logo_option']) && $mentinance_record['wpext_logo_option'] == '2') { echo 'd-none'; }?>" id="wpext_mm_logo">
                                <div class="wpext-admin-admin-bar-maintenance wpext-admin-color-bar">
                                  <div class="wpe_upload_img_field">
                                    <div class="image img-container-maintenance wp-picker-container-maintenance">
                                        <div class="wpext_admin_logo_picker_maintenance">
                                        <input type="hidden" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[header_logo]" value="<?php if(isset($mentinance_record['header_logo']) ) { echo $mentinance_record['header_logo']; } ?>" class="header_logo-maintenance">                 
                                          <button type="button" class="button wp-color-result upload_logo_img px-0">
                                            <span class="wp-color-result-text">Select Image</span>
                                          </button>
                                          <div class="wpext_coming_logo_img rounded float-left"></div>
                                        </div>
                                    </div>
                                  </div>
                                </div>
                           </div>    
                          </div>               
                       </div>                     
                    </div>
                 </div>

                 <div class="row py-2 <?php if(isset($mentinance_record['wpext_logo_option']) && $mentinance_record['wpext_logo_option'] == '2') { echo 'd-none'; }?>" id="wpext_mm_logo_width">
                    <div class="col-lg-3"> 
                       <h6><label for="width_in_px"><?php _e('Logo Width', WP_EXTENDED_TEXT_DOMAIN ); ?></label></h6>
                    </div>
                    <div class="col-lg-9">
                      <div class="row">
                        <div class="col-lg-6"> 
                          <input type="number" class="form-control mb-3 input_maintenance_mode_fields" id="width_in_px" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[logo_width]" placeholder="Width (px)" value="<?php if(isset($mentinance_record['logo_width'])) { echo $mentinance_record['logo_width']; } ?>">
                        </div>                     
                      </div>
                    </div>
                  </div>
                
                  <div class="row pt-2 <?php if(isset($mentinance_record['wpext_logo_option']) && $mentinance_record['wpext_logo_option'] == '2') { echo 'd-none'; }?>" id="wpext_mm_logo_height">
                    <div class="col-lg-3"> 
                       <h6><label for="height_in_px"><?php _e('Logo Height', WP_EXTENDED_TEXT_DOMAIN ); ?></label></h6>
                    </div>
                    <div class="col-lg-9">
                      <div class="row">
                        <div class="col-lg-6"> 
                          <input type="number" class="form-control mb-3 input_maintenance_mode_fields" id="height_in_px" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[logo_height]" placeholder="Height (px)" value="<?php if(isset($mentinance_record['logo_height'])) { echo $mentinance_record['logo_height']; } ?>">
                        </div>
                      </div>
                    </div> 
                  </div> 

                </div> 
                 <div class="row mx-lg-4 py-4">
                  <div class="row pb-2">
                    <div class="col-lg-3">
                       <h6><label for="wpext_bgcol_img"><?php _e('Background', WP_EXTENDED_TEXT_DOMAIN); ?></label></h6>
                       <p class="small"><?php _e('Choose either a background image or colour', WP_EXTENDED_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="col-lg-9">
                       <div class="row">
                          <div class="col-lg-6">
                             <?php $selected_bg = isset($mentinance_record['wpext_backgroung']) ? $mentinance_record['wpext_backgroung'] : ''; ?>
                             <select class="form-select mb-3" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[wpext_backgroung]" id="wpext_bgcol_img">
                              <option value="wpext_bgimg" <?php selected('wpext_bgimg', $selected_bg); ?>><?php _e('Background Image', WP_EXTENDED_TEXT_DOMAIN); ?></option>                               
                               <option value="wpext_bgcolor" <?php selected('wpext_bgcolor', $selected_bg); ?>><?php _e('Background Colour', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                             </select>

                             <div class="card form-control <?php if($selected_bg == 'wpext_bgcolor') { echo 'd-none'; }  ?>" id="wpext_bg_banner"> 
                                <div class="wpext-admin-admin-bar-maintenance wpext-admin-color-bar">
                                  <div class="wpe_upload_img_field">
                                    <div class="image img-container-maintenance wp-picker-container-maintenance">
                                      <div class="wpext_admin_logo_picker_maintenance">
                                        <input type="hidden" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[coming_img]" value="<?php if(isset($mentinance_record['coming_img'])) { echo $mentinance_record['coming_img']; } ?>" class="coming_img">
                                          <button type="button" class="button wp-color-result upload_banner_img px-0">
                                            <span class="wp-color-result-text">Select Image</span>
                                          </button>                
                                        <div class="rounded float-left wpext_coming_soon_img"></div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                             </div>

                             <div class="maintinance_bg <?php if($selected_bg == 'wpext_bgimg') { echo 'd-none'; }?>">   
                              <input title="Choose your color" id="bg_color_code" class="form-control wp-color-picker" name="<?php echo self::WPEXT_MAINTANAMCE_MODE ?>[bg_color_code]" type="text" value="<?php if(isset($mentinance_record['bg_color_code']) && !empty($mentinance_record['bg_color_code'])) { echo $mentinance_record['bg_color_code']; } else{ echo "#ffffff"; } ?>" title="Choose your color" id="wpext_bgcolor" data-default-color="#ffffff"/>   
                              </div>
                          </div>
                       </div>                     
                    </div>
                 </div>
                 </div>
               </div>

            </form>         
         </div>
      </div>
   </div>
</div>
<?php 
 }

/**
 * Adds the maintenance page template to the templates dropdown
 *
 * @param $templates
 * @return mixed
 */
public function add_maintenance_template( $templates ) {
   return array_merge(
      $templates,
      array(
         'templates/wpe-page-template.php' => html_entity_decode( '&harr; ' ) . __( 'Wp Extended Template',WP_EXTENDED_TEXT_DOMAIN ),
      )
   );
}

/**
 * Applies the maintenance page template to the page
 *
 * @param $template
 * @return mixed|string
 */

public function wpext_template_include($template){
  $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE ); 
  if (!is_user_logged_in() && !is_admin()) {
    if (file_exists(plugin_dir_path( __FILE__ ).'/view/index.php')) {
      $template =  plugin_dir_path( __FILE__ ).'/view/index.php';  
    }
   }elseif (file_exists(plugin_dir_path( __FILE__ ).'/view/index.php') && isset($_GET['wpext_maintenance_preview'])) {
      $template =  plugin_dir_path( __FILE__ ).'/view/index.php';
   }
  return $template;
}

public function wpext_echeck_default_status(){
 $mentinance_record = get_option( self::WPEXT_MAINTANAMCE_MODE );
   if(empty($mentinance_record)){
      $defaults_data = array(
        'site_title'         => __('Site is undergoing maintenance', WP_EXTENDED_TEXT_DOMAIN),
        'site_heading'      => __('Maintenance Mode', WP_EXTENDED_TEXT_DOMAIN),
        'headline_color'    => '#000000',
        'discription'       => __('Site will be available soon. Thank you for your patience!', WP_EXTENDED_TEXT_DOMAIN),
        'description_color' => '#000000',
        'bg_color_code'     => '#ffffff',
        'footer_text'       => '&copy; ' . get_bloginfo('name') . ' ' . date('Y'),
        'footer_text_color' => '#000000',
        'logo_height'       => '180',
        'logo_width'        => '180',
        'wpext_logo_option'   => '1',
        'wpext_backgroung'    => 'wpext_bgimg',        
      );
      update_option(self::WPEXT_MAINTANAMCE_MODE, $defaults_data);
    }
} 

}
Wp_Extended_Maintenance_Mode::init();

