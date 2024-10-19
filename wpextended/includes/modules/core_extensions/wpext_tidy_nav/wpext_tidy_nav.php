<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Tidy_Nav extends Wp_Extended_Export {

 const OPTION_MAIN_MENU = 'wpext-hide-menu-main';
 const OPTION_MAIN_MENU_BY_USER = 'wpext-user-tidy-nav';
 const OPTION_MAIN_MENU_BY_STORE_USERROLE = 'wpext-user-tidy-nav-store-user-role';
 const OPTION_MAIN_MENU_BY_STORE_USERID = 'wpext-user-tidy-nav-store-user-id';

  public function __construct() {
    parent::__construct();
    add_action( 'admin_menu', array( get_called_class(), 'tidy_menu'), 100 );
    add_action( 'admin_menu', array( $this,'wpext_custom_side_menu_page_removing') ,'9999');
    add_action( 'admin_bar_menu', array( $this,'wpext_custom_top_menu_page_removing') ,'9999');
    add_action( 'admin_enqueue_scripts', array( $this, 'wpext_tidy_nav_admin_scripts' ), 110 );
    add_action( 'admin_init',   array( $this, 'wpext_user_nav_settings_init') );
    add_action( 'admin_init',   array( $this, 'wpext_user_nav_settings_store') );
    add_action( 'wp_ajax_render_data_byuser_role', array($this, 'render_data_byuser_role'));
    add_filter( 'custom_menu_order', '__return_true' );

    add_action( 'wp_ajax_render_data_byuser_id', array($this, 'render_data_byuser_id'));
    add_action( 'admin_footer', array( $this,'wpext_user_data_fields'));
    add_action( 'wp_ajax_save_table_order', array( $this,'save_table_order_ajax'));
    $user = wp_get_current_user();
    $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array());
    $user_roles = $user->roles;
    if (!empty($user_roles) && isset($user_roles[0])) {
    $wpext_user_role_order = get_option('custom_table_order' . $user_roles[0], array());
    }else {
     $wpext_user_role_order = array(); // or some default value
    }
    if(!empty($wpext_user_role_order) && !empty($get_user_role)){
      add_filter( 'menu_order', array( $this, 'render_custom_menu_order'));
    }
   add_action( 'wp_ajax_wpext_remove_role_order', array( $this,'wpext_remove_role_order'));
     
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Tidy_Nav( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function wpext_user_nav_settings_init(){
    register_setting( self::OPTION_MAIN_MENU_BY_USER, self::OPTION_MAIN_MENU_BY_USER, array( 'type' => 'array' ) );
  }
 public static function tidy_menu(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_tidy_nav', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_tidy_nav'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

    if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
      add_submenu_page( 
        'wp-extended', __('Menu Editor', WP_EXTENDED_TEXT_DOMAIN), __('Menu Editor', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options', 'wp-extended-tidymenu', 
        array( get_called_class(), 'settings_tidy_menu' ),
        null );
      if(isset($_GET['page']) == 'wp-extended-tidymenu'){
        add_menu_page(null, '', '', 'manage_options', 'wp-extended-dependancy', 'wpext_nav_dependancy_menu');
      }
    }else{
       $capability = 'manage_options';
       $slug = 'wp-extended-tidymenu';
       $callback = [ get_called_class(), 'settings_tidy_menu'];
       add_submenu_page('', '', '', $capability, $slug, $callback);
       add_rewrite_rule('^wp-extended-tidymenu/?', 'index.php?wp_extended_tidymenu=1', 'top');
       add_rewrite_tag('%wp_extended_tidymenu%', '([^&]+)');
       if(isset($_GET['page']) == 'wp-extended-tidymenu'){
        add_menu_page(null, '', '', 'manage_options', 'wp-extended-dependancy', 'wpext_nav_dependancy_menu');
      }
    }
  }
public function wpext_nav_dependancy_menu(){
 /*Just Use For Dependancy Menu*/
}
public static function wpext_tidy_nav_admin_scripts(){
  $screen = get_current_screen();
  if(strpos($screen->id, "wp-extended-tidymenu")){ 
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script( 'wpext-tidynav-sections', 
      plugins_url("/js/wpext_tidy_nav.js", __FILE__), 
      array(), 
      filemtime( plugin_dir_path( __FILE__ ) . "/js/wpext_tidy_nav.js" ),
      true 
    );
    $hide_byuser_role  = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'ajax_nonce' => wp_create_nonce( 'user-roles' ),
        );
        wp_localize_script( 'wpext-tidynav-sections', 'role_ajax_obj', $hide_byuser_role);
    wp_enqueue_style( 'wp-extended-admin-theme', plugin_dir_url( __FILE__ ) . "css/wpext_tidy_nav.css", array(), WP_EXTENDED_VERSION );
    $screen = get_current_screen();
  }
} // admin_scripts

public static function settings_tidy_menu(){
  global $wp_session,$wp_roles;
  $remove_side_array = array();
  // $roles = $wp_roles->roles;
    $roles = array('editor'=> array('name'=> 'Editor'),
          'author' => array('name' => 'Author'),
          'contributor' => array('name' => 'Contributor'),
          'subscriber'  => array('name' => 'Subscriber'),
          'administrator'  => array('name' => 'Administrator' )
          );

   $currentuser = wp_get_current_user();
    
  $get_data = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE);
  $userroles = array($get_data => array('name'=> $get_data));
  if (isset($_POST['save'])) {
    //check administrator access required
    if(current_user_can('administrator'))
    {
      if(check_admin_referer('menu-remove')){
         
        //here we have to check this is array or not. (array check validation)
         $menu_list = isset( $_POST['menu_list'] ) ? (array) $_POST['menu_list'] : array();
         $new_menu_list = array();
         $stored_menu_list = array(); //define array for side menu array
        
          foreach ($menu_list as $list_data_key => $list_data_role) {

          // data sanitization functions used for valid text (text validation)
            $list_data_role = array_map( 'sanitize_text_field', $list_data_role );
            foreach ($list_data_role as $list_data) {
              if (is_numeric($list_data)) { //validate the data
                 if (isset($wp_session['all_side_menus'][$list_data]) && isset($wp_session['all_side_menus'][$list_data][2])) {
                   $new_menu_list[$list_data_key][] = $wp_session['all_side_menus'][$list_data][2];
                 }
              }
            }
        }
        $remove_side_array = $new_menu_list;
        $get_data = get_option(self::OPTION_MAIN_MENU);
        if($get_data && $remove_side_array){
          $revert_data = json_decode($get_data);
          foreach($revert_data as $rv => $store_data){
            $stored_menu_list[$rv] = $store_data;
          }
          $final_array = array_merge( $stored_menu_list, $remove_side_array );
          $json_remove_side_array = json_encode($final_array); //json array.
        }else{
          $json_remove_side_array = json_encode($remove_side_array); //json array.
        }
        //remove menus form the admin top menu.
        //here we have to check this is array or not.(array check validation)
        $top_menu_list = isset( $_POST['top_menu_list'] ) ? (array) $_POST['top_menu_list'] : array();
        $new_menu_list = array(); //array define for top menu        
        foreach ($top_menu_list as $list_data_key => $list_data_role) {
            $list_data_role = array_map( 'sanitize_text_field', $list_data_role );
            foreach ($list_data_role as $list_data) {
               $new_menu_list[$list_data_key][] = $wp_session['all_top_menus'][$list_data]->id;
            }
        }
        $remove_top_array = $new_menu_list;
        $json_remove_top_array = json_encode($remove_top_array); //json array.
        //update the values
         $menu_userlist = $_POST['menu_userlist'];
         if(isset($menu_userlist)){
           update_option(self::OPTION_MAIN_MENU,$json_remove_side_array);
           update_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE,$menu_userlist);
          }
       }//end of wpnonce   
    }//end of administrator check 
    
   } //complete post
 ?>
  <div class="container-fluid wpe_brand_header">
    <div class="container ps-2 p-4">
      <div class="row">
         <div class="col-sm-8 col-md-6 ps-0">
            <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e( "WP Extended Menu Editor", WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
         </div>
         <?php do_action( "admin_plugin_top_info" );?>
      </div>
    </div>
  </div>

  <div class="container-fluid wp_brand_sub_header">
    <div class="container">
      <div class="row align-items-baseline">
        <div class="col-lg-6 px-1"><p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="<?php echo esc_url("https://wpextended.io/module_resources/tidy-nav/"); ?>" target="_blank" class="wp_brand_sub_header_back_document"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p></div>
        <div class="col-lg-6 wp_brand_sub_header_right mx-lg-0">
          <?php 
          $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array()); 
          ?>
          <button name="<?php _e('save', WP_EXTENDED_TEXT_DOMAIN) ?>" class="wpext_module_action wp-ext-btn-prim role update-role <?php if($get_user_role == 'administrator'){ echo ' d-none'; }?>" form="wp-extended-tidymenu-frm" id="publish"><?php _e('Save Changes', WP_EXTENDED_TEXT_DOMAIN);?></button>
        </div>
      </div>
    </div>
  </div>

  <?php 
  //isset($_POST["Update"]) && $_POST["Update"]
  if (isset($_REQUEST["save"]) && $_REQUEST["save"] || isset($_POST["_wpnonce"]) && !empty($_POST["_wpnonce"]) ) { ?>
    <!-- Code for a success message on save -->
  <div class="container wpext-container wpext_success_message_container">
    <div class="row">
      <div class="wpext-success-message rounded-2">
        <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('Your settings have been saved successfully!', WP_EXTENDED_TEXT_DOMAIN ); ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <div class="container is-fluid css-wp-extend tidy_nav" id="wp-extended-nav-app">
    <div class="row tidy-table">
      <div class="col tidy_nav px-0">
        <div class="container border rounded-2 bg-white text-dark p-0">
          <div class="row p-lg-1 p-md-3 wpext_menu_editor_inner">
          <div class="row shorting-row">
          <div class="col-lg-8 p-lg-5 p-md-3 wpext_menu_editor_inner">
          
          <div class="container py-3 px-0 bg-white text-dark header-table pb-0"> 
            <!--Switcher dropdown - userwise and Role wise -->
            <div class="row g-5 py-3 " id="role-wise-form">
              <div class="col-sm-12 role-wise-form is-active pt-0 mt-0">
                <?php $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array()); ?>
                <?php $get_user_by_id = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERID, array()); ?>   
                <select name="by_user_role" id="by_user_role" class="form-select">
                  <option value=""><?php _e('Select Role Type...', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                      <?php foreach ($roles as $user_role =>  $role) { ?>
                      <option name="menu_userlist" value="<?php echo $user_role; ?>" 
                        <?= $get_user_role == $user_role ? ' selected="selected"' : '';?>
                        ><?php echo $role['name']; ?></option>
                        <?php } ?> 
                </select>
              </div>
            </div>
          </div>
    <?php
    //now we have to fetch all hide_menu_array from the db for side bar
    $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array());
    $get_data = get_option(self::OPTION_MAIN_MENU);
    if($get_data!='null' && $get_data!='' ){
      $fetch_hide_menu_array = json_decode($get_data);
    }
    else{
      $fetch_hide_menu_array = array();
    }
    //now we have to fetch all hide_sub_menu_array from the db for side bar
    $get_data = get_option('hide-sub-menu');
    if($get_data!='null' && $get_data!='' ){
      $get_data2 = json_decode($get_data);
        foreach ($get_data2 as $role_key => $get_data2_role) {     
        //now we remove the paren key
        foreach ($get_data2_role as $gets_data) {
          $new_get_data = explode('__con__', $gets_data);
          $fetch_hide_sub_menu_array[$role_key][] = $new_get_data['1']; 
        }
      }   

    }
    else{
      $fetch_hide_sub_menu_array = array();
    }
    //now we have to fetch all hide_menu_array from the db for top bar
    $get_data = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE); ?>
    <div class="col-lg wpext_tidy_nav_first">
      <div class="accordion-item_box">
        <div class="container bg-white text-dark role-wise-form is-active shorting-row wpext_tidymenu_role_list"> 
          <form method="post" class="g-5 py-3 pt-0" id="wp-extended-tidymenu-frm">
            <?php wp_nonce_field( 'menu-remove' ); ?>
            <div class="col-md-12">
              <?php $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array()); 
              if(empty($get_user_role)){
                $get_user_role = "administrator";
              }
              $all_menu = $wp_session['all_side_menus'];
              $all_sub_menu = $wp_session['all_side_sub_menus']; ?>
              <input type="hidden" name="menu_userlist" value="<?php if(isset($get_user_role) && !empty($get_user_role)){ echo $get_user_role; } ?>" id="userrole">
              <div class='table user_role_table' id="userlist-of-sections">
                  <?php
                  
                  $all_menu = $wp_session['all_side_menus'];
                  $all_sub_menu = $wp_session['all_side_sub_menus']; 
                  $currentuser = wp_get_current_user();
                
                  $selected_order = get_option('wpext_user_role_selected_order'.$get_user_role);
                  $selected_order_json = json_decode($selected_order); ?>
                  <div id="tbody">
                    <?php 
                      if(!empty($selected_order_json)){  
                        /* Start Menu synchronous after activate and Deactivate theme/plugin*/
                        $default_menu = $wp_session['all_side_menus'];
                          $new_menu = [];
                          foreach ($default_menu as $key => $menu_item) {
                              $new_item = new stdClass();
                              if (!empty($menu_item[0])) {
                                  $new_item->rorder = $menu_item;
                                  $new_item->Item = [0 => $key];
                                  $new_item->value = false;
                                  $new_item->menulabel = $menu_item[0];
                              } else {
                                  // If the first element is empty, just set checkboxValue to false
                                  $new_item->checkboxValue = false;
                              }
                              $new_menu[] = $new_item;
                          }
                          $all_menu = $selected_order_json;
                          foreach ($new_menu as $new_item) {
                              $found = false;
                              foreach ($all_menu as &$existing_item) {   
                                  if (isset($existing_item->rorder[5]) && isset($new_item->rorder[5]) && $existing_item->rorder[5] === $new_item->rorder[5]) {
                                      if($new_item->rorder[2] == $existing_item->rorder[2]){
                                      $existing_item = (object) array_merge((array) $existing_item, (array) $new_item);
                                      $found = true;
                                      break;
                                      }
                                  }
                              }
                              if (!$found) {
                                  $all_menu[] = $new_item;
                              }
                          }
                          /* Menu synchronous after activate and Deactivate theme/plugin End here */

                          foreach ($new_menu as $key=> $row) { 
                            if(isset($row->rorder['6']) && $row->rorder['6']!=''){ 
                              $menuorder = json_encode($row->rorder);
                              $orderdata = self::remove_html_tags($menuorder);
                              if($row->rorder['5'] == 'menu-comments'){
                                $title = "Comments";
                              }else{
                                $result = preg_replace('/[0-9]+/', '', $row->rorder['0']);
                                if ($result === null) {
                                  $title = sanitize_text_field(preg_replace('/[0-9]+/', null, $result));
                                }else{
                                  $title = sanitize_text_field(preg_replace('/[0-9]+/', '', $result)); 
                                }
                              } ?>
                              <div id="<?php echo esc_attr($row->rorder['5']); ?>" order-record='<?php echo esc_attr($orderdata); ?>' data_key="<?php echo esc_attr($row->Item[0]); ?>" class="wpext_user_role  source" >
                                <div class="accordion accordion-flush pt-0 pb-0 m-0">
                                  <div class="row pb-0 pt-0">
                                    <div class="col px-1">

                                      <div class="accordion-header card p-1 mt-0 menu-order-card">
                                        <div class="accordion-item">
                                          <div class="mt-0 menu-order-card">
                                            <div class="row p-2 px-1 wpext_menu_editor_container wpext_menu_editor_main">
                                              
                                              <div class="col p-0">
                                                
                                                <div class="row">
                                                  <div class="col-1 scope_icon">::</div>
                                                  <div class="main-menu fw-normal wpext_font_size align-middle px-0 col-lg-6 pb-1 d-flex">
                                                    <label class="d-flex">
                                                      <span class="wpext_edit_action_title" role="button"><?php echo $title; ?></span>
                                                    </label>
                                                  </div>
                                                
                                                <?php foreach ($userroles as $role_key =>$role) {  ?>
                                                  <div class="col-sm-1 pt-0 d-flex align-items-center wpext_menu_editor_switch">
                                                    <div class="form-check form-switch form-switch-md" id="<?php echo esc_attr($row->rorder['2']); ?>">
                                                    <input
                                                      <?php if(in_array($row->rorder['2'], isset($fetch_hide_menu_array->$role_key) ? $fetch_hide_menu_array->$role_key : array() )) echo 'checked'; ?>
                                                      type="checkbox" name="menu_list[<?php echo $role_key ?>][]" value="<?php echo esc_attr($row->Item[0]); ?>" class="form-check-input <?php if($role_key == 'administrator') { echo "disabledclass"; } ?>" id="menu_list[<?php echo $role_key.'-'.$row->Item[0] ?>]" <?php if($row->rorder[5] == 'toplevel_page_wp-extended-dependancy') { echo 'checked';} ?> role="switch" <?php if($role_key == 'administrator') { echo "disabled"; } ?>>
                                                      <label for="menu_list[<?php echo $role_key.'-'.$row->Item[0] ?>]"></label>
                                                    </div>        
                                                  </div> 
                                                <?php } ?>
                                              </div>
                                              </div>

                                            </div>
                                          </div>
                                        </div>
                                      </div>

                                    </div>
                                  </div>
                                </div>
                              </div>
                            <?php      
                            }//if
                          }  //for 
                      }
                        else{
                          $all_menu = $wp_session['all_side_menus'];
                          foreach ($all_menu as $key=> $row) { 
                          if(isset($row['6']) && $row['6']!=''){ 
                            $menuorder = json_encode($row);
                            $orderdata = self::remove_html_tags($menuorder);
                            $sub_menu_array = isset($all_sub_menu[$row['2']]) ? $all_sub_menu[$row['2']] : array() ;
                            $sub_menu_array = isset( $sub_menu_array ) ? (array) $sub_menu_array : array(); 
                            if($row['5'] == 'menu-comments'){
                              $title = "Comments";
                            }else{
                              $result = preg_replace('/[0-9]+/', '', $row['0']);
                              if ($result === null) {
                                $title = sanitize_text_field(preg_replace('/[0-9]+/', null, $result));
                              }else{
                                $title = sanitize_text_field(preg_replace('/[0-9]+/', '', $result)); 
                              }
                            } 
                            ?>
                            <div id="<?php echo esc_attr($row['5']); ?>" order-record='<?php echo esc_attr($orderdata); ?>' data_key="<?php echo esc_attr($key); ?>" class="wpext_user_role  source" >
                              <div class="accordion accordion-flush pt-0 pb-0 m-0">
                                <div class="row pb-0 pt-0">
                                  <div class="col px-1">

                                    <div class="accordion-header card p-1 mt-0 menu-order-card">
                                      <div class="accordion-item">
                                        <div class="mt-0 menu-order-card">
                                          <div class="row p-2 px-1 wpext_menu_editor_container wpext_menu_editor_main">

                                            <div class="col p-0">
                                              
                                              <div class="row">
                                                <div class="col-1 scope_icon">::</div>
                                                <div class="main-menu fw-normal wpext_font_size align-middle px-0 col-lg-6 pb-1 d-flex">
                                                  <label class="d-flex">
                                                    <span class="wpext_edit_action_title" role="button"><?php echo $title; ?></span>
                                                  </label>
                                                </div>

                                              <?php foreach ($userroles as $role_key =>$role) { ?>
                                              <div class="col-sm-1 pt-0 d-flex align-items-center wpext_menu_editor_switch">
                                                <div class="form-check form-switch form-switch-md" id="<?php echo esc_attr($row['2']); ?>">
                                                <input
                                                  <?php if(in_array($row['2'], isset($fetch_hide_menu_array->$role_key) ? $fetch_hide_menu_array->$role_key : array() )) echo 'checked'; ?>
                                                  type="checkbox" name="menu_list[<?php echo esc_attr($role_key) ?>][]" value="<?php echo esc_attr($key); ?>" class="form-check-input <?php if($role_key == 'administrator') { echo "disabledclass"; } ?>" id="menu_list[<?php echo esc_attr($role_key.'-'.$key) ?>]" <?php if($row[5] == 'toplevel_page_wp-extended-dependancy') { echo 'checked';} ?> role="switch" <?php if($role_key == 'administrator') { echo "disabled"; } ?>>
                                                  <label for="menu_list[<?php echo esc_attr($role_key.'-'.$key) ?>]"></label>
                                                </div>
                                              </div>  
                                            <?php } ?>
                                            </div>
                                            </div>

                                          </div>
                                        </div>
                                      </div>
                                    </div>

                                  </div>
                                </div>
                              </div>
                            </div>
                          <?php
                        
                          }//if
                        }  //for 
                      }
                    ?>
                  </div>
                </div>
            </div>
          </form>
        </div>

        <div class="wpext-loader-container" style="display: none;">
          <div class="wpext-loader-row">
            <div class="wpext-loader-box"></div>
          </div>
          <div class="wpext-loader-row">
            <div class="wpext-loader-box"></div>
          </div>
          <div class="wpext-loader-row">
            <div class="wpext-loader-box"></div>
          </div>
          <div class="wpext-loader-row">
            <div class="wpext-loader-box"></div>
          </div>
          <div class="wpext-loader-row">
            <div class="wpext-loader-box"></div>
          </div>
        </div> 

      </div>
    </div>
  </div>

    <div class="col-lg-4 pt-0 p-2 wpext_tidy_nav_second">
      <div class="pt-0 ps-4 ps-md-0 wpext_action_inner_section">
        <div class="wpext-help-message p-4 rounded-2 border-0 wpext-tidy-menu-help-message-position">
          <h5 class="fs-6"><strong><?php _e('Actions', WP_EXTENDED_TEXT_DOMAIN) ?></strong></h5>
          <p><?php _e('Resetting the menu items will revert them back into the default order.', WP_EXTENDED_TEXT_DOMAIN) ?></p>
          <button class="wpext_module_action wp-ext-btn-sec menu_editor_btn role" id="wpext_reset_role" ><?php _e('Reset Order', WP_EXTENDED_TEXT_DOMAIN); ?></button>
        </div>
      </div>
    </div>

  </div>
</div>
 </div>
  </div>  
  </div>
</div>
</div> 
<?php  

}
/*Hide Top Menu*/

public function wpext_custom_top_menu_page_removing(){
  global $wp_session, $wp_admin_bar;
  $login_user = wp_get_current_user();
  $login_user_roles = (array) $login_user->roles;
  $wp_session['all_top_menus']  = $wp_admin_bar->get_nodes();
  $all_menu = $wp_session['all_top_menus'];
  // $get_data = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE);
  
 //now we have to fetch all hide_menu_array from the db
  $get_data = get_option(self::OPTION_MAIN_MENU, array());
  if($get_data){
   $top_menu_recored = json_decode($get_data); 
  }
  if($login_user_roles['0'] != 'administrator' && !empty($top_menu_recored)){
  foreach($top_menu_recored as $menu_slug){
      if($menu_slug['0'] == 'index.php' || $menu_slug['0'] == 'upload.php'){
        $wp_admin_bar->remove_node('new-content');
      } 
    }

  }
  if($get_data!='null' && $get_data!='' && !empty($get_data)){
   $fetch_hide_menu_array = json_decode($get_data);
  }
  else{
    $fetch_hide_menu_array = array();
  }
 foreach ($fetch_hide_menu_array as $role_key => $hide_menu_array_role) {
   // print_r($hide_menu_array_role);
   if(in_array($role_key, $login_user_roles)){
      foreach ($hide_menu_array_role as $hide_menu_array) {
        $wp_admin_bar->remove_node( $hide_menu_array );
        
      }
       
    }
  }
}
public function wpext_custom_side_menu_page_removing(){

  global $wp_session,$menu,$submenu,$pagenow,$parent_file;
  $login_user = wp_get_current_user();
  $login_user_roles = (array) $login_user->roles;
  $wp_session['all_side_menus']  = $menu;
  $wp_session['all_side_sub_menus']  = $submenu;
  $all_menu = $wp_session['all_side_menus'];
  //now we have to fetch all hide_menu_array from the db
  $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array());
  $get_data = get_option(self::OPTION_MAIN_MENU); 
  if($get_data!='null' && $get_data!='' ){
   $fetch_hide_menu_array = json_decode($get_data);
  }
  else{
    $fetch_hide_menu_array = array();
  }
  //now fetch sub menu data
  $get_data = get_option('hide-sub-menu'); 
  if($get_data!='null' && $get_data!='' ){
   $fetch_hide_sub_menu_array = json_decode($get_data);
  }
  else{
    $fetch_hide_sub_menu_array = array();
  }
  foreach ($fetch_hide_menu_array as $role_key => $hide_menu_array_role) {
   if(in_array($role_key, $login_user_roles)){
        foreach ($hide_menu_array_role as $hide_menu_array) {
          remove_menu_page( $hide_menu_array );

          /**
           * From version 2.4.1
           * Fixed the issues(when disable default post the custom posts are disable itself) Now fixed.
           * For the user role.
           */
          
          if(isset($_REQUEST['post_type']) && !empty($_REQUEST['post_type'])){
            $cpt = $_REQUEST['post_type'];
          }else{
            $cpt = '';
          }
          if($hide_menu_array == $pagenow.'?post_type='.$cpt || $hide_menu_array == $parent_file  ) {
            if($parent_file != 'index.php'){
              do_action("admin_page_access_denied");
              wp_die( __( "Sorry, you are not allowed to access this page.", WP_EXTENDED_TEXT_DOMAIN), 403 );
            }
          }
        }
    }
  }
foreach ($fetch_hide_sub_menu_array as $role_key => $hide_menu_array) {
     //now we ge the parent key and child key
    if(in_array($role_key, $login_user_roles)){
      foreach ($hide_menu_array as $hide_menu_role_array) {
        $pare_child = explode('__con__', $hide_menu_role_array);
        //this is the patch for the wordpress 4 or may be latetest version for the customize menu only
        if($pare_child[0] == 'themes.php'){
          $parse_data = parse_url($pare_child[1]);
          if($parse_data['path'] == 'customize.php'){
              unset($submenu['themes.php'][6]); // Customize
          }
        }
        remove_submenu_page($pare_child[0], $pare_child[1] );
      }
    }
  }
}

public function render_data_byuser_role(){
  $html = '';
  $user_role = sanitize_text_field($_REQUEST['user_role']);
  if(isset($user_role)){
    update_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE ,$_REQUEST['user_role']);
    $all_menu = $_REQUEST['menudata'];
    $get_usesdata = get_option(self::OPTION_MAIN_MENU); 
    if($get_usesdata !='null' && $get_usesdata !='' ){
     $fetch_hide_menu_array = json_decode($get_usesdata);
    }
    else{
      $fetch_hide_menu_array = array();
    }

    $selected_order = get_option('wpext_user_role_selected_order'.$_REQUEST['user_role']);
    $selected_order_json = json_decode($selected_order); 
    if(!empty($selected_order_json)){
      $all_menu = $selected_order_json;
      foreach($all_menu as $key=>$row){ 
        if(isset($row->rorder['6']) && $row->rorder['6']!=''){
          $encoded_menuorder = json_encode($row->rorder);
          $clean_menuorder = stripslashes($encoded_menuorder);
          $orderdata = self::remove_html_tags($clean_menuorder);
           
          if($row->rorder['5'] == 'menu-comments'){
            $title = "Comments";
          }else{
            $title = sanitize_text_field(preg_replace('/[0-9]+/', null, $row->rorder['0'])); 
          }
          $status = '';
          $get_user_by_id = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE);
          $username = array($get_user_by_id => array('name'=> $get_user_by_id));
          foreach ($username as $role_key =>$role) {
            $checked = '';
          if(in_array($row->rorder['2'], isset($fetch_hide_menu_array->$role_key) ? $fetch_hide_menu_array->$role_key : array() )) {
            $status = 'checked';
          }else{
            $status = '';
          }
          if($role_key == 'administrator'){
            $disable = 'disabled';
            $hidden = 'd-none';
          }else{
            $disable = ' ';
            $hidden = '';
          }

          if($row->rorder[5] == 'toplevel_page_wp-extended-dependancy') { $checked = 'checked'; }  
           $html .= '<div id=' . esc_attr($row->rorder[5]) . ' order-record=\'' . $orderdata . '\' data_key="' . $row->Item[0] . '" class="wpext_user_role">';

           $html .= '<div class="accordion accordion-flush pt-0 pb-0 m-0">
                      <div class="row pb-0 pt-0">
                        <div class="col px-1">

                          <div class="accordion-header card p-1 mt-0 menu-order-card">
                            <div class="accordion-item">
                              <div class="mt-0 menu-order-card">
                                <div class="row p-2 px-1 wpext_menu_editor_container wpext_menu_editor_main">
                                  
                                  <div class="col p-0">
                                    
                                    <div class="row">
                                      <div class="col-1 scope_icon">::</div>
                                      <div class="main-menu fw-normal wpext_font_size align-middle px-0 col-lg-6 pb-1 d-flex">
                                        <label class="d-flex">
                                          <span class="wpext_edit_action_title" role="button">'.$title.'</span>
                                        </label>
                                      </div>';

                         $html .= '<div class="col-sm-1 pt-0 d-flex align-items-center wpext_menu_editor_switch '.$hidden.' "><div class="form-check form-switch form-switch-md '.$disable.' " id=' . esc_attr($row->rorder[2]) . '>';
                         $html .= '<input ' . $status . ' id="menu_list' . $role_key . '-' . $row->Item[0] . '" name="menu_list[' . $role_key . '][]" type="checkbox" class="form-check-input" value=' . esc_attr($row->Item[0]) . ' ' . $checked . ' '.$disable.'>';
                         $html .= '<label for="menu_list' . $role_key . '-' . $row->Item[0] . '"></label></div></div>';
                         
                         $html .= '</div>
                                  </div>

                                </div>
                              </div>
                            </div>
                          </div>

                        </div>
                      </div>
                    </div>
                  </div>';
          }
      }
    }  
  $removescractor = preg_replace('/\\\\/', '', $html);
  echo wp_send_json(array('layoutdata' =>$removescractor));
  die; 
  }else{
     $html = '';
    $all_menu = $_REQUEST['menudata'];
     foreach ($all_menu as $key => $row) {
        if (isset($row['6']) && $row['6'] != '') {
            /*$menuorder = json_encode($row);
            $orderdata = self::remove_html_tags($menuorder);*/
            if($row['5'] == 'menu-comments'){
            $title = "Comments";
            }else{
              $title = preg_replace('/[0-9]+/', null, $row['0']); 
            }
            $status = '';
            $get_user_by_id = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE);
            $username = array($get_user_by_id => array('name' => $get_user_by_id));
            foreach ($username as $role_key => $role) {
              $checked = '';
              if (isset($fetch_hide_menu_array->$role_key) && in_array($row['2'], $fetch_hide_menu_array->$role_key)) {
                  $status = 'checked';
              } else {
                  $status = '';
              }
              if ($row[5] == 'toplevel_page_wp-extended-dependancy') {
                  $checked = 'checked';
              }
              if($role_key == 'administrator'){
                $disable = 'disabled';
                $hidden = 'd-none';
              }else{
                $disable = ' ';
                $hidden = '';
              }
              // Encode menuorder and remove slashes
              $encoded_orderdata = json_encode($row);
              $clean_orderdata = stripslashes($encoded_orderdata);
              // update_option('wpext_user_role_selected_order'.$_REQUEST['user_role'], $clean_orderdata);
              $html .= '<div id=' . esc_attr($row[5]) . ' order-record=\'' . $clean_orderdata . '\' data_key="' . $key . '" class="wpext_user_role">';

              $html .= '<div class="accordion accordion-flush pt-0 pb-0 m-0">
                      <div class="row pb-0 pt-0">
                        <div class="col px-1">

                          <div class="accordion-header card p-1 mt-0 menu-order-card">
                            <div class="accordion-item elsetest">
                              <div class="mt-0 menu-order-card">
                                <div class="row p-2 px-1 wpext_menu_editor_container wpext_menu_editor_main">
                                  
                                  <div class="col p-0">
                                    
                                    <div class="row">
                                      <div class="col-1 scope_icon">::</div>
                                      <div class="main-menu fw-normal wpext_font_size align-middle px-0 col-lg-6 pb-1 d-flex">
                                        <label class="d-flex">
                                          <span class="wpext_edit_action_title" role="button">'.$title.'</span>
                                        </label>
                                      </div>';

              $html .= '<div class="col-sm-1 pt-0 d-flex align-items-center wpext_menu_editor_switch '.$hidden.' "><div class="form-check form-switch form-switch-md '.$disable.' " id=' . esc_attr($row[2]) . '>';
              $html .= '<input ' . $status . ' id="menu_list' . $role_key . '-' . $key . '" name="menu_list[' . $role_key . '][]" type="checkbox" class="form-check-input" value=' . esc_attr($key) . ' ' . $checked . ' '.$disable.'>';
              $html .= '<label for="menu_list' . $role_key . '-' . $key . '"></label></div></div>';

                          $html .= '</div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>

                        </div>
                      </div>
                    </div>
                  </div>';
            }
        }
      }
      echo wp_send_json(array('layoutdata' => $html));
      die;
    }
  }
}

public function wpext_user_nav_settings_store(){

 /*For Users */
 /*Remove menu*/
  global $wp_session, $menu, $submenu, $pagenow, $parent_file;
  $login_user = wp_get_current_user();
  
  $login_user_roles = array();
  if (!empty($login_user->data)) {
    $login_user_roles = (array) $login_user->data->user_login;
    if(empty($login_user_roles)){
      $login_user_roles = array();
    }
  }
  $wp_session['all_side_menus']  = $menu;
  $wp_session['all_side_sub_menus']  = $submenu;
  $all_menu = $wp_session['all_side_menus'];
  $_GLOBALS['user_data'] = $all_menu;
  //now we have to fetch all hide_menu_array from the db
  $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERID ,array());
  $get_usesdata = get_option(self::OPTION_MAIN_MENU_BY_USER); 
  if($get_usesdata!='null' && $get_usesdata!='' ){
   $fetch_hide_menu_array = json_decode($get_usesdata);
  }
  else{
    $fetch_hide_menu_array = array();
  }
  //now fetch sub menu data
  $get_usesdata = get_option(self::OPTION_MAIN_MENU_BY_USER); 
  if($get_usesdata!='null' && $get_usesdata!='' ){
   $fetch_hide_sub_menu_array = json_decode($get_usesdata);
  }
  else{
    $fetch_hide_sub_menu_array = array();
  }
  foreach ($fetch_hide_menu_array as $role_key => $hide_menu_for_user) {
   if(in_array($role_key, $login_user_roles)){
    foreach ($hide_menu_for_user as $hide_menu_array) {
       remove_menu_page( $hide_menu_array );
        if($hide_menu_array == $pagenow || $hide_menu_array == $parent_file  ) {
          do_action("admin_page_access_denied");
          wp_die( __( "Sorry, you are not allowed to access this page.", WP_EXTENDED_TEXT_DOMAIN), 403 );
        }
      }
    }
  }  
  /*Remove menu end Here*/
}

public function wpext_user_data_fields(){
    global $wp_session, $menu;
    $wp_session['all_side_menus'] = $menu;
    $all_menu = $wp_session['all_side_menus'];
    wp_add_inline_script( 'wpext-tidynav-sections', 'const wpExthidemenumain = ' . json_encode( $all_menu ), 'before' );
  }

// AJAX handler to save table order

public function save_table_order_ajax() {
  if (isset($_POST['order']) && is_array($_POST['order'])) {
    $order = $_POST['order']; 
    $user_role = $_POST['user_role'];
    $role_menu_order = $_POST['role_menu_order'];
    $selected_order = json_encode($role_menu_order);
    update_option('wpext_user_role_order', sanitize_text_field($user_role[0]));
    update_option('custom_table_order'.sanitize_text_field( $user_role[0]), $order);
    update_option('wpext_user_role_selected_order'.sanitize_text_field($user_role[0]), $selected_order); 
    wp_send_json_success();
  } else {
      wp_send_json_error();
  }
  
}
 
public function render_custom_menu_order($menu_order){
    global $menu ;
    $user = wp_get_current_user();
    $username = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERID);
    $get_user_role = get_option(self::OPTION_MAIN_MENU_BY_STORE_USERROLE , array());
    $wpext_user_role_order = get_option('custom_table_order'.$user->roles[0], array());
    /**
     * 
     * Get the array recorde for user order.
     * 
     */
    $options = get_option('custom_table_order'.$user->roles[0], array());
    if(isset($options) && !empty($options)){
      $arraykey = '';
      foreach($options as $keyorder){
        $arraykey .= $keyorder.',';
       }
    // Get current menu order. We're not using the default $menu_order which uses index.php, edit.php as array values.
    $current_menu_order = array();
    foreach ( $menu as $menu_key => $menu_info ) {
      if ( false !== strpos( $menu_info[4], 'wp-menu-separator' ) ) {
          $menu_item_id = $menu_info[2];
      } else {
          $menu_item_id = $menu_info[5];
      } 
      $current_menu_order[] = array( $menu_item_id, $menu_info[2] );
    }
    // Get custom menu order
    $custom_menu_order = $arraykey;
    // comma separated
    $custom_menu_order = explode( ",", $arraykey );
    $rendered_menu_order = array();
    // Render menu based on items saved in custom menu order
    foreach ( $custom_menu_order as $custom_menu_item_id ) {
        foreach ( $current_menu_order as $current_menu_item_id => $current_menu_item ) {
            if ( $custom_menu_item_id == $current_menu_item[0] ) {
                $rendered_menu_order[] = $current_menu_item[1];
            }
        }
    }
    // Add items from current menu not already part of custom menu order, e.g. new plugin activated and adds new menu item
    foreach ( $current_menu_order as $current_menu_item_id => $current_menu_item ) {
        if ( !in_array( $current_menu_item[0], $custom_menu_order ) ) {
            $rendered_menu_order[] = $current_menu_item[1];
        }
    }
    return $rendered_menu_order;
  }
 }
 
 public function wpext_remove_role_order(){
  if(isset($_REQUEST['current_role'])){
    $current_role = sanitize_text_field($_REQUEST['current_role']);
    delete_option('custom_table_order'.$current_role);
    delete_option('wpext_user_role_selected_order'.$current_role);
    delete_option('wpext_user_role_order'.$current_role);
  }
   die;
 }
 public static function remove_html_tags($data) {
  if (is_array($data)) {
    foreach ($data as $key => $value) {
        $data[$key] = remove_html_tags($value);
    }
  } else {
    $data = strip_tags($data);
  }
  return $data;
  }
}
Wp_Extended_Tidy_Nav::init();  