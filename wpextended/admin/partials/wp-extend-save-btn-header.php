<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}
$save_button_text = !empty($save_button_text) ? $save_button_text : __("Save Changes", WP_EXTENDED_TEXT_DOMAIN) ;    

$modules = $this->modules();

$url_page_parameters = $_GET['page'];

//page form id get in JSON file used in each form
$formID = '';
foreach ($modules as $module) {
  foreach ($module as $moduleName => $moduleData) {
    if($moduleData['path'] == $url_page_parameters) {
      $formID = $moduleData['path'].'-frm';
      $doc_url = (isset($moduleData['doc_url'])) ? $moduleData['doc_url'] : 'https://wpextended.io/docs/' ;
    }
  }
}
?>
<div class="container-fluid wp_brand_sub_header">
  <div class="container">
    <div class="row align-items-baseline">
      <div class="col-lg-6 px-1"><p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="<?php echo esc_url($doc_url); ?>" target="_blank" class="wp_brand_sub_header_back_document"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p></div>
      <div class="col-lg-6 wp_brand_sub_header_right mx-lg-0">
        <button class="wpext_module_action wp-ext-btn-prim" form="<?php echo $formID; ?>"><?php _e('Save Changes', WP_EXTENDED_TEXT_DOMAIN);?></button>
      </div>
    </div>
  </div>
</div>

<?php
if($formID == 'wp-extended-disable-comments-frm') {?>
<div class="container wpext-container wpext_success_message_container disable_comments_success_message" style="display: none;">
    <div class="row">
      <div class="wpext-success-message rounded-2">
        <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('Your settings have been saved successfully!', WP_EXTENDED_TEXT_DOMAIN ); ?>
      </div>
    </div>
  </div>
<?php } ?>

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

<?php 
if($url_page_parameters == "wp-extended-block-username"){
  $block_username_datas = get_option('wpext-block-username-tag');
  if(isset($block_username_datas['wpext_block_username'])){
    $block_username_data = $block_username_datas['wpext_block_username'];
    if(!empty($block_username_data)){
      $usernames_and_emails = explode(',', $block_username_data);
      $message = '';
      foreach ($usernames_and_emails as $data) {
          $username = trim($data);
          // Check if it's a username
          if (username_exists($username)) {
            // Check if the user exists and has the Administrator role
            $user = get_user_by('login', $username);
            if($user->roles[0] == 'administrator' ){
              $message .= $username.", ";
            }
          }          
      }
      // Remove trailing comma
      $message = rtrim($message, ', ');
      if(!empty($message)){
        $users_url = admin_url('users.php');  
        ?>
         <div class="container wpext-container" style="padding-right: 35px;">
            <div class="row">
               <div class="wpext-fail-message rounded-2">
                 <span>&#9432; <?php _e('Warning!', WP_EXTENDED_TEXT_DOMAIN);?></span> <?php echo sprintf( __('The username <b>%s</b> is already registered. Please view our documentation on the best steps to solve this as you are unable to change usernames.', WP_EXTENDED_TEXT_DOMAIN), $message ); ?>
               </div>
            </div>
         </div>

      <?php } 
    }
  }
}
?>
