<?php
/**
 * The duplication menu implemented on  version 2.2.2
 */
if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Duplicate_Menu extends Wp_Extended {
  const WPEXT_DUPLICATE_MENU = 'wpext-duplicate-menu';
  
  public function __construct() {
    parent::__construct();
     add_action( 'admin_menu', array( get_called_class(), 'wpext_duplicate_menu'), 100 );
     add_action('admin_enqueue_scripts', array( $this, 'wpext_admin_duplicate_menu_scripts' ), 99999 );
  }

  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_duplicate_menu( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

  public function wpext_admin_duplicate_menu_scripts(){
    $screen = get_current_screen();
    if(strpos($screen->id, "wp-extended-duplicate-menu")){
      wp_enqueue_script( 'wp-extended_jquery_wpext_wpext_duplicate_menu', plugin_dir_url( __FILE__ ) . "js/wpext_duplicate_menu.js", array(), WP_EXTENDED_VERSION );
      wp_enqueue_style( 'wpext_duplicate_menu_css', plugin_dir_url( __FILE__ ) . "css/wpext_duplicate_menu.css", array(), WP_EXTENDED_VERSION );
    }
  }

  public function wpext_duplicate_menu_configuration(){
    register_setting( self::WPEXT_DUPLICATE_MENU, self::WPEXT_DUPLICATE_MENU,  array( 'type' => 'array' ) );
  }

  public static function wpext_duplicate_menu(){
    $menustatus = get_option('wpext_show_plugin_menu_action');
    $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');

    // Initialize flag
    $flagfavorite = false;

    //Check if the favorite admin menu settings marked 
    if (!empty($wpext_admin_menu_favorite)) {
      if (array_key_exists('wpext_duplicate_menu', $wpext_admin_menu_favorite)) {
        if ($wpext_admin_menu_favorite['wpext_duplicate_menu'] == 'true') {
          $flagfavorite = true;
        }
      }
    }

    if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
       add_submenu_page( 
        'wp-extended', __('Duplicate Menu', WP_EXTENDED_TEXT_DOMAIN), __('Duplicate Menu', WP_EXTENDED_TEXT_DOMAIN), 
        'manage_options',  'wp-extended-duplicate-menu',  array( get_called_class(), 'settings_duplicate_menu' ),
        null
      );
    }else{
      $slug = 'wp-extended-duplicate-menu';
      $capability = 'manage_options';
      $callback = [get_called_class(), 'settings_duplicate_menu'];
      add_submenu_page('', '', '', $capability, $slug, $callback);
      add_rewrite_rule('^wp-extended-duplicate-menu/?', 'index.php?wp_extended_duplicate_menu=1', 'top');
      add_rewrite_tag('%wp_extended_duplicate_menu%', '([^&]+)');
    }
  }
 
  public static function settings_duplicate_menu(){
    $nav_menus = wp_get_nav_menus();
    ?>
    <div class="container-fluid wpe_brand_header">
     <div class="container ps-2 p-4">
        <div class="row">
           <div class="col-sm-8 col-md-6 ps-0">
              <h4 class="text-white ps-1 m-0 wpe_brand_header_title"><?php _e( "WP Extended Duplicate Menu", WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
           </div>
           <?php do_action( "admin_plugin_top_info" );?>
        </div>
     </div>
    </div>

    <div class="container-fluid wp_brand_sub_header">
      <div class="container">
        <div class="row align-items-baseline">
          <div class="col-lg-6 px-1"><p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="<?php echo esc_url("https://wpextended.io/module_resources/duplicate-pages-posts/"); ?>" target="_blank" class="wp_brand_sub_header_back_document"><?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p></div>          
        </div>
      </div>
    </div>

    <?php if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['wpext_duplicate_menu_nonce'], 'wpext_duplicate_menu' ) ) : 
      $source         = intval( $_POST['wpext_source'] );
      $destination    = sanitize_text_field( $_POST['wpext_new_menu_name'] );
      $duplicator = new Wp_Extended_duplicate_menu();
      $new_menu_id = $duplicator->wpext_duplicate( $source, $destination ); 
        if ( $new_menu_id ) :  ?>
          <div class="container wpext-container wpext_success_message_container">
            <div class="row">
              <div class="wpext-success-message rounded-2">
                <span>&#x2713; <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('Menu Duplicated', WP_EXTENDED_TEXT_DOMAIN ); ?> <a href="nav-menus.php?action=edit&amp;menu=<?php echo absint( $new_menu_id ); ?>"><?php _e( 'View', WP_EXTENDED_TEXT_DOMAIN ) ?></a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="container wpext-container wpext_body_container">
            <div class="row">
              <div class="wpext-fail-message rounded-2">
                <span>&#x2715; <?php _e('Whoops!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e('There was a problem duplicating your menu. No action was taken..', WP_EXTENDED_TEXT_DOMAIN ); ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( empty( $nav_menus ) ) : ?>
      <div class="container wpext-container wpext_body_container">
        <div class="row">
          <div class="wpext-fail-message rounded-2">
            <span>&#x2715; <?php _e('Whoops!', WP_EXTENDED_TEXT_DOMAIN ); ?></span> <?php _e("You haven't created any Menus yet.", WP_EXTENDED_TEXT_DOMAIN ); ?> <a href="nav-menus.php" target="_blank"><?php _e( 'Create new menu' ) ?></a>
          </div>
        </div>
      </div>
    <?php else: ?>
    <div class="container wpext-container" id="wp-extended-app">
      <div class="container wpext-container wpext_main_container px-0">
        <div class="row">
          <div class="col-sm-12 mb-3 bg-white p-lg-4 border rounded-2">
            <div class="container text-dark p-0">
              <div class="row">
                <div class="col-md p-3 wpext_duplicate_menu pt-0">
                  <form method="post" action="" id="wp-extended-duplicate-menu-frm">
                    <?php wp_nonce_field( 'wpext_duplicate_menu','wpext_duplicate_menu_nonce' ); ?>
                    <label for="smtp_host" class="label fw-normal wpext_font_size p-1 px-0"><?php _e( "Select Menu", WP_EXTENDED_TEXT_DOMAIN ); ?></label>
                    <select name="wpext_source" class="mw-100 form-select mb-2" aria-label="Select Menu">
                      <?php foreach ( (array) $nav_menus as $_nav_menu ) : ?>
                      <option value="<?php echo esc_attr($_nav_menu->term_id) ?>">
                        <?php echo esc_html( $_nav_menu->name ); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <label for="wpext_new_menu_name" class="label fw-normal wpext_font_size p-1 px-0" role="button"><?php _e( "Duplicate Menu Name", WP_EXTENDED_TEXT_DOMAIN ); ?></label>
                    <input name="wpext_new_menu_name" type="text" id="wpext_new_menu_name" value="" class="form-control" require='true' required/>
                    <p><?php _e( 'This is the name that will appear in the menu select.' , WP_EXTENDED_TEXT_DOMAIN); ?></p>
                    <div class="col wp_brand_sub_header_left">
                      <button class="wpext_module_action wp-ext-btn-prim" form="wp-extended-duplicate-menu-frm"><?php _e('Duplicate Menu', WP_EXTENDED_TEXT_DOMAIN);?></button>
                    </div>
                  </form>
                </div>
                <div class="col-md-4 p-3">
                  <div class="pt-2 ps-md-6">
                    <div class="wpext-help-message p-4 mt-2 rounded-2">
                      <?php _e( "Simply choose a menu you have already created, enter a name for your new navigation menu and click on 'Duplicate Menu'." , WP_EXTENDED_TEXT_DOMAIN); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
     
    <?php endif; ?>
<?php }
  /**
   * The duplication process
   */
  public function wpext_duplicate( $id = null, $name = null ) {

    // sanity check
    if ( empty( $id ) || empty( $name ) ) {
      return false;
    }

    $id = intval( $id );
    $name = sanitize_text_field( $name );
    
    //flag set for menu check
    $menuflag = false;
    // Retrieve all registered navigation menus
    $menus = wp_get_nav_menus();
    
    if ( ! empty( $menus ) ) {
      foreach ( $menus as $menu_obj ) {
        $name_slug = sanitize_title( $name );
        // If the current menu slug matches the target slag, set the flag to true
        if ( $name_slug === $menu_obj->slug ) {            
            $menuflag = true;            
        }
      }
    }

    if ( $menuflag ) {
        // If a duplicate menu is found, returning false
        return false;
    } else {
      // If no duplicate menu is found so create menu
      $source = wp_get_nav_menu_object( $id );
      $source_items = wp_get_nav_menu_items( $id );
      $new_id = wp_create_nav_menu( $name );

      if ( ! $new_id ) {
          return false;
      }

      // key is the original db ID, val is the new
      $rel = array();

      $i = 1;
      foreach ( $source_items as $menu_item ) {
          $args = array(
              'menu-item-db-id'       => $menu_item->db_id,
              'menu-item-object-id'   => $menu_item->object_id,
              'menu-item-object'      => $menu_item->object,
              'menu-item-position'    => $i,
              'menu-item-type'        => $menu_item->type,
              'menu-item-title'       => $menu_item->title,
              'menu-item-url'         => $menu_item->url,
              'menu-item-description' => $menu_item->description,
              'menu-item-attr-title'  => $menu_item->attr_title,
              'menu-item-target'      => $menu_item->target,
              'menu-item-classes'     => implode( ' ', $menu_item->classes ),
              'menu-item-xfn'         => $menu_item->xfn,
              'menu-item-status'      => $menu_item->post_status
          );

          $parent_id = wp_update_nav_menu_item( $new_id, 0, $args );

          $rel[$menu_item->db_id] = $parent_id;

          // did it have a parent? if so, we need to update with the NEW ID
          if ( $menu_item->menu_item_parent ) {
              $args['menu-item-parent-id'] = $rel[$menu_item->menu_item_parent];
              $parent_id = wp_update_nav_menu_item( $new_id, $parent_id, $args );
          }

        // allow developers to run any custom functionality they'd like
        do_action( 'wpext_duplicate_menu_item', $menu_item, $args );

        $i++;
      } 
    }
    return $new_id;
  }
}
Wp_Extended_duplicate_menu::init();