<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

?>

<div class="container bg-white text-dark p-0 border rounded-0 shadow-sm wpe_sidebar mb-4">

<div class="hstack gap-3 p-3" style="background: #F9FAFB;">
  <div class="p-2 fw-semibold"><?php _e('System', WP_EXTENDED_TEXT_DOMAIN);?> </div>
</div>
<div class="container bg-white text-dark p-3"> 
  <ul class="list-group list-group-flush px-2">
    <li class="list-group-item fs-6 px-0"><?php _e('License:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold">Free</span></li>
    <li class="list-group-item fs-6 px-0"><?php _e('Plugin Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php echo $this->ver();?></span></li>
    <li class="list-group-item fs-6 px-0"><?php _e('PHP Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold">
      <?php
        if (strnatcmp(phpversion(),'7.4.0') < 0)
        {
          echo phpversion().'<span class="has-tooltip-arrow wpext-help wp-extended-alert" data-tooltip="You should consider updating your PHP version"><span class="icon"><i class="fa-solid fa-circle-exclamation"></i></span></span>';
        }
        else
        {
          echo phpversion(); 
        }
      ?>  
   </li>
        <?php 
        $active_page_builder = '';
        if ( is_plugin_active( 'elementor/elementor.php' ) ) {
        // Elementor is active
        $active_page_builder .= 'Elementor';
        } 

        if ( is_plugin_active( 'oxygen/functions.php' ) ) {
        // oxygen is active
        if($active_page_builder){
          $active_page_builder .= ', Oxygen';
        }else{
          $active_page_builder .= 'Oxygen';
        }   
        } 

        if ( is_plugin_active( 'breakdance/plugin.php' ) ) {
        // breakdance is active
        if($active_page_builder){
          $active_page_builder .= ', Breakdance';
        }else{
          $active_page_builder .= 'Breakdance';
        }
        } 

        if ( is_plugin_active( 'visualcomposer/plugin-wordpress.php' ) ) {
        // visualcomposer is active
        if($active_page_builder){
          $active_page_builder .= ', Visual Composer';
        }else{
          $active_page_builder .= 'Visual Composer';
        }
        }
        if($active_page_builder){ 
        ?>
          <li class="list-group-item fs-6 px-0"> 
              <?php _e('Active Page Builder:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php _e($active_page_builder, WP_EXTENDED_TEXT_DOMAIN);?></span>
          </li>
        <?php 
        }
        ?>
    <li class="list-group-item fs-6 px-0"><?php _e('WordPress Version:', WP_EXTENDED_TEXT_DOMAIN);?> <span class="fw-bold"><?php bloginfo( 'version' ); ?></span></li>
  </ul>

</div>
</div>