<?php

if ( ! defined( 'ABSPATH' ) ) {
   die();
}

$modules = $this->modules();

$url_page_parameters = $_GET['page'];

//page title name get in JSON file
$module_title = '';
foreach ($modules as $module) {
  foreach ($module as $moduleName => $moduleData) {
    if($moduleData['path'] == $_GET['page']) {
      $module_title = $moduleData['name'];
    }
  }
}
?>
<div class="container-fluid wpe_brand_header">
   <div class="container ps-2 p-4">
      <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e( "WP Extended ".$module_title, WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
   </div>
</div>
<?php do_action('wpext_module_save_btn_header'); ?>

<?php if($url_page_parameters == "wp-extended-changes-wp-admin-url" ){ ?>
<div class="container wpext-container" style="padding-right: 35px;">
  <div class="row">
    <div class="wpext-help-message rounded-2">
      <span>&#9432; <?php _e('Tip!', WP_EXTENDED_TEXT_DOMAIN);?></span> <?php _e('If you leave the redirect field blank, accessing ', WP_EXTENDED_TEXT_DOMAIN);?><strong><?php _e('/wp-admin', WP_EXTENDED_TEXT_DOMAIN);?></strong> <?php _e('will result in a redirection to the existing homepage.', WP_EXTENDED_TEXT_DOMAIN);?>
    </div>
  </div>
</div> 
<?php } ?>

<div class="container wpext-container">
<div class="row">
   <div class="col-sm-12 gx-5 mb-3 p-0 rounded-2">
      <div class="container text-dark p-0 rounded-2 wpext_toggle_settings">
         <div class="container wpext-container" id="wp-extended-app">
            <div class="row">
               <div class="col-sm-12 gx-5 p-0 rounded-2">
                  <div class="container bg-white text-dark p-0 rounded-2 wpext-admin_ui border">
                      