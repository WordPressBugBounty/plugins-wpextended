<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://wearenrcm.com
 * @since      1.0.0
 *
 * @package    wp_extend
 * @subpackage wp_extend/admin/partials
 */

$module_group = array(
'snippets'  => __('Code Snippets & Utilities', WP_EXTENDED_TEXT_DOMAIN ),
'security'  => __('Security & Maintenance', WP_EXTENDED_TEXT_DOMAIN ),
'post-page' => __('Posts & Pages', WP_EXTENDED_TEXT_DOMAIN ),
'users'     => __('Users', WP_EXTENDED_TEXT_DOMAIN ),
'media'     => __('Images & Media', WP_EXTENDED_TEXT_DOMAIN ),
'disable'   => __('Disable & Organize', WP_EXTENDED_TEXT_DOMAIN ),
); ?>
 
<div class="container-fluid wpe_brand_header border-bottom shadow-sm">
   <div class="container p-4"> 
     <div class="row top-header-content">
       <div class="col-sm-8 col-md-6 ps-0">
           <h4 class="text-white ps-2 m-0 wpe_brand_header_title"><?php _e('WP Extended', WP_EXTENDED_TEXT_DOMAIN); ?></h4>
       </div>
        <?php do_action( "admin_plugin_top_info" );?>
     </div>
   </div>  
</div> 
<div class="container wpext-container wpext_dashboard" id="wp-extended-app">
   <div class="row">
      <div class="col-sm-12 gx-5 mb-3 bg-white p-0 rounded-2" id="wpext_module_settinglayout"> 
         <div class="container text-dark px-3 py-4 border rounded-2 wpext_toggle_settings">
            <div class="hstack px-3 py-3 wpext_headingbg_color rounded-2 mx-3 mt-2 mb-3">
            <div class="hstack col-sm-6 wpext_settings_tools">
              <div class="p-0 wpext_tab" id="wpext_module"><a class="nav-link wpext_nav me-3 px-1 active" aria-current="page" href="javascript:void(0);">
                <svg xmlns="http://www.w3.org/2000/svg" id="wpext_modules_modules_icon" data-name="Layer 1" viewBox="0 0 24 24" width="12" height="12"><path d="M20.527,4.217,14.5.737a5.015,5.015,0,0,0-5,0L3.473,4.217a5.014,5.014,0,0,0-2.5,4.33v6.96a5.016,5.016,0,0,0,2.5,4.331L9.5,23.317a5.012,5.012,0,0,0,5,0l6.027-3.479a5.016,5.016,0,0,0,2.5-4.331V8.547A5.014,5.014,0,0,0,20.527,4.217ZM10.5,2.47a3,3,0,0,1,3,0l6.027,3.479a2.945,2.945,0,0,1,.429.33L13.763,9.854a3.53,3.53,0,0,1-3.526,0L4.044,6.279a2.945,2.945,0,0,1,.429-.33ZM4.473,18.105a3.008,3.008,0,0,1-1.5-2.6V8.547a2.893,2.893,0,0,1,.071-.535l6.193,3.575A5.491,5.491,0,0,0,11,12.222v9.569a2.892,2.892,0,0,1-.5-.206Zm16.554-2.6a3.008,3.008,0,0,1-1.5,2.6L13.5,21.585a2.892,2.892,0,0,1-.5.206V12.222a5.491,5.491,0,0,0,1.763-.635l6.193-3.575a2.893,2.893,0,0,1,.071.535Z"/></svg>
                <?php _e('Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a></div>
              <div class="p-0 wpext_tab" id="wpext_settings"><a class="wpext_nav nav-link me-3 px-1" href="javascript:void(0);">
               <svg xmlns="http://www.w3.org/2000/svg" id="wpext_modules_setting_icon" viewBox="0 0 24 24" width="12" height="12"><path d="M12,8a4,4,0,1,0,4,4A4,4,0,0,0,12,8Zm0,6a2,2,0,1,1,2-2A2,2,0,0,1,12,14Z"/><path d="M21.294,13.9l-.444-.256a9.1,9.1,0,0,0,0-3.29l.444-.256a3,3,0,1,0-3-5.2l-.445.257A8.977,8.977,0,0,0,15,3.513V3A3,3,0,0,0,9,3v.513A8.977,8.977,0,0,0,6.152,5.159L5.705,4.9a3,3,0,0,0-3,5.2l.444.256a9.1,9.1,0,0,0,0,3.29l-.444.256a3,3,0,1,0,3,5.2l.445-.257A8.977,8.977,0,0,0,9,20.487V21a3,3,0,0,0,6,0v-.513a8.977,8.977,0,0,0,2.848-1.646l.447.258a3,3,0,0,0,3-5.2Zm-2.548-3.776a7.048,7.048,0,0,1,0,3.75,1,1,0,0,0,.464,1.133l1.084.626a1,1,0,0,1-1,1.733l-1.086-.628a1,1,0,0,0-1.215.165,6.984,6.984,0,0,1-3.243,1.875,1,1,0,0,0-.751.969V21a1,1,0,0,1-2,0V19.748a1,1,0,0,0-.751-.969A6.984,6.984,0,0,1,7.006,16.9a1,1,0,0,0-1.215-.165l-1.084.627a1,1,0,1,1-1-1.732l1.084-.626a1,1,0,0,0,.464-1.133,7.048,7.048,0,0,1,0-3.75A1,1,0,0,0,4.79,8.992L3.706,8.366a1,1,0,0,1,1-1.733l1.086.628A1,1,0,0,0,7.006,7.1a6.984,6.984,0,0,1,3.243-1.875A1,1,0,0,0,11,4.252V3a1,1,0,0,1,2,0V4.252a1,1,0,0,0,.751.969A6.984,6.984,0,0,1,16.994,7.1a1,1,0,0,0,1.215.165l1.084-.627a1,1,0,1,1,1,1.732l-1.084.626A1,1,0,0,0,18.746,10.125Z"/></svg>  
               <?php _e('Settings', WP_EXTENDED_TEXT_DOMAIN); ?></a></div>
               <div class="p-0" id="wpext_tools"><a class="wpext_nav nav-link me-3 px-1" href="javascript:void(0);">
               <svg xmlns="http://www.w3.org/2000/svg" id="wpext_modules_setting_icon" data-name="Layer 1" viewBox="0 0 24 24" width="12" height="12">
                <path d="m3.688,24c-.032,0-.063,0-.095,0-1.022-.027-1.963-.462-2.649-1.224-1.269-1.409-1.157-3.784.244-5.185l5.868-5.867c.253-.254.344-.631.241-1.009-.358-1.318-.393-2.676-.102-4.036C7.903,3.364,10.626.735,13.972.137c1.006-.18,2.015-.184,3.002-.007.731.129,1.299.625,1.52,1.325.251.799-.003,1.681-.682,2.359l-2.247,2.217c-.658.658-.758,1.69-.222,2.345.308.378.742.598,1.222.622.472.02.936-.155,1.271-.489l2.58-2.55c.539-.539,1.332-.735,2.07-.501.723.227,1.254.828,1.385,1.567h0c.175.987.172,1.998-.007,3.003-.6,3.347-3.229,6.07-6.544,6.777-1.363.291-2.721.256-4.036-.103-.377-.104-.754-.012-1.008.241l-5.976,5.975c-.69.69-1.637,1.081-2.612,1.081ZM15.61,1.993c-.422,0-.854.035-1.286.112-2.554.457-4.634,2.463-5.174,4.991-.224,1.045-.198,2.086.076,3.093.29,1.062,0,2.191-.756,2.948l-5.868,5.867c-.65.65-.732,1.81-.171,2.433.315.35.747.55,1.215.562.461.019.909-.163,1.241-.494l5.975-5.975c.755-.755,1.885-1.047,2.948-.757,1.004.274,2.045.3,3.093.076,2.528-.539,4.534-2.618,4.992-5.174.138-.772.14-1.547.006-2.301v-.007s-2.655,2.559-2.655,2.559c-.729.729-1.744,1.136-2.781,1.068-1.036-.052-2.009-.545-2.669-1.353-1.179-1.439-1.021-3.649.361-5.03l2.247-2.217c.179-.18.191-.314.184-.341-.315-.039-.643-.062-.976-.062Z"/>
              </svg>
              <?php _e('Tools', WP_EXTENDED_TEXT_DOMAIN); ?></a></div>
              </div>
                  <!-- start grid section -->
                  <div class="wptxt-action_switch hstack col-md-6 justify-content-md-end">
                    <div class="wptxt-search-input-container me-lg-3 active">
                       <input type="text" id="wpext_searchmodule" val="" onkeyup="wpext_search_module()" placeholder="Module Search..." class="">
                       <span class="wpext-clear-icon" id="wpext_clear_button">&#10006;</span>
                    </div>

                    <div class="wpext_layout_button p-0" id="wpext_list">
                       <a href="javascript:void(0);" class="wpext_nav me-2 px-1">
                          <svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24">
                             <path d="m19,2H5C2.243,2,0,4.243,0,7v10c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5V7c0-2.757-2.243-5-5-5ZM2,10h4v4H2v-4Zm6,0h14v4h-14v-4Zm14-3v1h-14v-4h11c1.654,0,3,1.346,3,3ZM5,4h1v4H2v-1c0-1.654,1.346-3,3-3Zm-3,13v-1h4v4h-1c-1.654,0-3-1.346-3-3Zm17,3h-11v-4h14v1c0,1.654-1.346,3-3,3Z"/>
                          </svg>
                       </a>
                    </div>
                     <div class="wpext_layout_button p-0" id="wpext_grid">
                       <a href="javascript:void(0);" class="wpext_nav me-2 px-1">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g id="_01_align_center" data-name="01 align center">
                          <path d="M11,11H0V3A3,3,0,0,1,3,0h8ZM2,9H9V2H3A1,1,0,0,0,2,3Z"/><path d="M24,11H13V0h8a3,3,0,0,1,3,3ZM15,9h7V3a1,1,0,0,0-1-1H15Z"/>
                          <path d="M11,24H3a3,3,0,0,1-3-3V13H11ZM2,15v6a1,1,0,0,0,1,1H9V15Z"/><path d="M21,24H13V13H24v8A3,3,0,0,1,21,24Zm-6-2h6a1,1,0,0,0,1-1V15H15Z"/></g></svg>
                       </a>
                    </div>
                    <div class="wpext_active_modules_container me-lg-3">
                     <?php 
                     $wpext_module_status = get_option('wp-extended-modules');
                       $has_displayed = false;
                        foreach($wpext_module_status as $key => $module_status) {
                            if(!empty($module_status) && !$has_displayed) { ?>
                                <label for="wpext_active_modules" class="mt-1"><?php _e('Active Modules', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                <div class="field form-check form-switch form-switch-md">
                                    <?php 
                                    $action_module_status = get_option('wpext_active_modules_status');
                                    if($action_module_status == 'true'){
                                        $status = 'checked';
                                    } else {
                                        $status = '';
                                    }                           
                                    ?>
                                    <input id="wpext_active_modules" name="wpext_active_modules" type="checkbox" class="form-check-input" <?php echo $status; ?>  role="switch">
                                </div>
                            <?php
                                $has_displayed = true; // Set the flag to true to prevent further display
                            }
                        } ?>
                  </div>
                  </div>
                  <!-- end grid section -->
            </div>
            
            <!-- start modules section -->
            <div class="container text-dark rounded-4 active mx-1" id="module_listing" style="display: block;">
               <div class="container wpext_ptpb30 mb-3" id="wp-extended-app">
                  <div class="row">
                     <?php
                        $wpext_tabGroups = array( 'snippets' => 'Utilities', 'security' => 'Security',
                        'post-page' => 'Posts & Pages', 'users' => 'Users', 'media' => 'Images & Media',
                        'disable' => 'Disable & Organise');
                        ?>
                     <div class="tab col-sm-3 wpext_tab sticky-sidebar-main">
                        <button class="tablinks active" onclick="moduleBody(event, 'AllModulesTab')" id="wpext_default_open_tab"><?php _e('All',WP_EXTENDED_TEXT_DOMAIN); ?></button>  
                        <?php foreach($wpext_tabGroups as $key => $tbgroup): ?>
                        <button class="tablinks" onclick="moduleBody(event, '<?php echo $key; ?>')"><?php _e($tbgroup, WP_EXTENDED_TEXT_DOMAIN); ?></button>
                        <?php endforeach; ?>
                     </div>
                     <div class="tab-content col-sm-9 wpext_tab_content sticky-sidebar-layout">
                     <div class="row view-group pt-0 group-option" id="wpext_products">
                     <?php foreach($wpext_tabGroups as $grpkey => $tbgroup): ?>
                         <?php foreach ($modules as $slug => $module_group): ?> 
                           <?php foreach ($module_group as $key => $module): ?>
                           <?php if (!empty($module['group']) && $module['group'] == $grpkey): ?>
                              <?php
                              $keywords = implode(',', $module['keywords']);
                              $name = $module['name'];
                              $description = $module['help'];

                              $search = trim(implode(' ', array($keywords, $name, $description)));
                              ?>
                           <div class="item col-xs-3 col-lg-3 mb-sm-0 group-module-option <?php echo $module['group']; ?>" data-search="<?php echo strtolower($search); ?>">
                              <div class="card">
                                 <div class="card-body">
                                 <h5 class="card-title"><?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?></h5>
                                 <?php if(isset($module['doc_url'])){ ?>
                                    <a href="<?php echo esc_url( $module['doc_url'] );  ?>" class="card-text wpext-grid-docuementation-link" data-toggle="tooltip" data-placement="right" class="tooltip-text" data-bs-original-title="Read documentation" target="_blank">
                                       <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="var(--wpe_blue)" class="bi bi-info-circle" aria-hidden="true" aria-label="Read documentation for <?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?>" viewBox="0 0 16 16">
                                          <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                                          <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                                       </svg>
                                    </a>
                                    <?php } ?>
                                    <?php if( !empty($module['tags']) ) {
                                          foreach( $module['tags'] as $tag ){  ?>
                                             <span class="badge rounded-pill <?php echo $tag['type'] ? $tag['type'] : "";?>">
                                                <?php _e( $tag['name'], WP_EXTENDED_TEXT_DOMAIN );?>
                                             </span> <?php 
                                          } 
                                       } ?>
                                    <p class="card-text m-0"><?php if(isset($module['help'])){ echo esc_attr( $module['help'] ); } ?></p>
                                    <?php if(isset($module['doc_url'])){ ?>
                                       <p class="card-docs m-0"><a href="<?php echo esc_url( $module['doc_url'] );  ?>" class="card-text wpext-list-docuementation-link " target="_blank" title="Read documentation for <?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?>"><?php _e('Read Documentation', WP_EXTENDED_TEXT_DOMAIN); ?><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 235 235" fill="var(--wpe_blue)" aria-hidden="true" aria-label="Read documentation for <?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?>" ><path fill="currentColor" d="M196 64v104a4 4 0 0 1-8 0V73.66L66.83 194.83a4 4 0 0 1-5.66-5.66L182.34 68H88a4 4 0 0 1 0-8h104a4 4 0 0 1 4 4"/></svg></a></p>
                                    <?php } ?>
                                 </div>
                                 <div class="card-footer text-muted wpext-m-footer">
                                       <div class="field form-check form-switch form-switch-md d-flex align-items-center gap-5">
                                       <div class="col-sm-4 mb-3 mb-sm-0">
                                       <input id="module-<?php echo $module['dirname']; ?>" 
                                          name="module[<?php echo $module['dirname']; ?>]" 
                                          data-module="<?php echo $module['dirname']; ?>"
                                          type="checkbox" 
                                          class="form-check-input" 
                                          <?php if ($module['status']) echo ' checked'; ?>
                                          <?php if (!$module['available']) echo ' disabled'; ?> role="switch">
                                       </div>
                                       <?php  
                                       if( !empty($module['action']) ){
                                          foreach( $module['action'] as $tag ){  ?>
                                             <div class="col-sm-8 mb-3 mb-sm-0 switcher_settings">
                                              <span class="<?php /*echo $tag['type'] ? $tag['type'] : "";*/ ?> <?php if($module['status']) {echo 'wpext_show'; } else{ echo "wpext_hide"; } ?>" id="<?php echo $module['dirname']; ?>" >
                                                <?php if(isset($tag['slug']))  {  ?>
                                                   <?php $action_url = admin_url().'admin.php?page='.$tag['slug']; ?>
                                                      <a href="<?php echo $action_url; ?>" class="wpext_module_action wpext_nav px-1 active" aria-label="<?php _e( $tag['configure_text'], WP_EXTENDED_TEXT_DOMAIN );?> <?php _e($module['name'], WP_EXTENDED_TEXT_DOMAIN); ?>">
                                                         <?php _e( $tag['configure_text'], WP_EXTENDED_TEXT_DOMAIN );?>
                                                      </a>
                                                </span>
                                             </div>
                                          <?php  } 
                                          }
                                       } ?>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <?php endif; ?>
                           <?php endforeach; ?>
                           <?php endforeach; ?>
                        <?php endforeach; ?>
                     </div>
                  </div>
               </div>
               </div>
            </div>
            <!-- end modules section -->

            <?php $status = get_option('wpext_plugin_reset_action'); ?>
               <div class="wpext_group_of_modules"  id="module_lisence" style="display: none;">
                  <?php self::wpext_license_page(); ?>
               </div>
            
            <div class="container bg-white text-dark p-3" style="display: none;" id="module_tools">
                <!-- start tools  -->
                <div class="row">
                    <?php $wpext_tabSettingGroups = array( 'wpext_export_section' => 'Export Settings', 'wpext_import_section' => 'Import Settings'); ?>
                    <div class="tab col-sm-3">
                        <?php foreach($wpext_tabSettingGroups as $key => $tbgroup): ?>
                        <button class="tablinkstool <?php if($key == "wpext_export_section"){ echo "active";} ?>" data-tab="<?php echo $key; ?>"><?php _e($tbgroup, WP_EXTENDED_TEXT_DOMAIN); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="tab-content col-sm-9">
                    <!-- start Export Settings -->
                      <div class="wpext_export tab_content_tool" id="wpext_export_section" style="display:block;">
                          <h5 class="text-black wpext_mb20"><?php _e('Export Settings', WP_EXTENDED_TEXT_DOMAIN); ?></h5>
                          <div class="col-sm-12 text-dark">
                             <div class="field form-switch form-switch-md mt-3 mb-3 px-0 wpext_resetaction">
                                <label for="wpext_export_snippets_settings" role="button" class="wpext_font_size"> <?php _e('Include Code Snippets', WP_EXTENDED_TEXT_DOMAIN) ?></label>
                                <input id="wpext_export_snippets_settings" name="wpext_export_snippets_settings" type="checkbox" class="form-check-input" role="switch">     
                             </div>
                          </div>
                          <button type="button" class="button button-primary" id="wpext_export_setting"><?php _e('Export File',WP_EXTENDED_TEXT_DOMAIN); ?></button>             
                       </div>            
                    <!-- end Export Settings -->
                    <!-- start Import Settings -->
                    <div class="wpext_import tab_content_tool" id="wpext_import_section" style="display:none;">
                       <h5 class="text-black wpext_mb20"><?php _e('Import Settings', WP_EXTENDED_TEXT_DOMAIN); ?></h5>
                       <div class="col-sm-12 text-dark mt-3 mb-3">
                          <input type="file" id="wpext_import_file">  
                       </div>
                       <button type="button" class="button button-primary mb-3" id="wpext_import_setting"><?php _e('Import File',WP_EXTENDED_TEXT_DOMAIN); ?></button>
                    </div>            
                    <!-- end Import Settings -->
                  </div>
               </div>
                <!-- end tools  -->
            </div>         
          </div>
      </div>
   </div>
</div>
<!-- Tost message -->
<div class="position-fixed top-0 end-0 p-3 license_status">
  <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <div class="toast-body"><?php _e('Invalid License.', WP_EXTENDED_TEXT_DOMAIN); ?></div>
    </div>
    </div>
</div>
<!-- tost Message end here -->