<?php

class Wp_Extended_Post_Order extends Wp_Extended {

	public function __construct() {
    parent::__construct();

    add_post_type_support( 'post', 'page-attributes' );
    add_action( 'admin_init',   array( $this, 'settings_init') );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 110 );
    // add_filter( 'posts_orderby', array( $this, 'orderby_menu_order' ), 10, 2 );
    add_action( 'wp_insert_post', array( $this, 'post_created' ), 10, 3 );
    add_action( 'rest_api_init', array( $this, 'route_register' ) );
  }

  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Post_Order( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init

    
  public function settings_init() {    
    add_action( 'manage_posts_custom_column', array( $this, 'manage_columns_column' ), 10, 2 );
    add_action( 'manage_pages_custom_column', array( $this, 'manage_columns_column' ), 10, 2 );
    add_filter( 'manage_edit-post_sortable_columns', array( $this, 'add_sortable_column' ), 10, 1 );
    add_filter( 'manage_edit-page_sortable_columns', array( $this, 'add_sortable_column' ), 10, 1 );
    add_action( 'admin_notices', array( $this, 'notice' ) );
    // check if we need to redirect
    add_action( 'current_screen', array( $this, 'redirect_to_orderby_menu_order' ) );
  } // settings_init
  

  public function admin_scripts(){
    $screen = get_current_screen();
    if( $screen->id == "edit-post" || $screen->id == "edit-page" ) {
      wp_enqueue_script( 'jquery-ui-sortable', false, array('jquery', 'jquery-ui-core') );
      wp_enqueue_script( 'wpext-post-order', 
        plugins_url("/js/wpext-post-order.js", __FILE__), 
        array(), 
        filemtime( plugin_dir_path( __FILE__ ) . "/js/wpext-post-order.js" ), 
        true 
      );
      wp_enqueue_style( 'wpext-post-order', 
        plugins_url("/css/wpext-post-order.css", __FILE__), 
        array(), 
        filemtime( plugin_dir_path( __FILE__ ) . "/css/wpext-post-order.css" )
      );
       wp_localize_script( 'wp-api', 'wpApiSettings', array(
        'root' => esc_url_raw( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' )
      ) );
      wp_enqueue_script('wp-api');      
    }
  }
  public function manage_columns_column( $name, $post_ID ) {
    if( $name !== 'wpext_order' ) {
      return;
    }
    $order = get_post_field( 'menu_order', $post_ID );
    echo absint($order) ? absint($order) : 0;
  } // manage_columns_column
 

  public function add_sortable_column( $columns ){
    $columns[ 'wpext_order' ] = 'menu_order';
    return $columns;
  } // add_sortable_column

  
  public function redirect_to_orderby_menu_order(){
    $screen = get_current_screen();
    if( $screen->id === 'edit-post' || $screen->id === 'edit-page' ) {
      if( !isset($_REQUEST['orderby']) ) {
        $path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        $query = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
        $extra = http_build_query( array( "orderby" => "menu_order", "order" => "asc") );
        $query .= ( $query ? "&" : "" ) . $extra;
        $url = $path . "?" . $query;
        if($screen->post_type != 'page'){
          wp_redirect($url, 302, 'WP-Extended-Post-order');
          exit;
        }
      }
      else if( $_REQUEST['orderby'] === 'menu_order' ) {
        // check if we have any with order = 0;
        global $wpdb;
        $post_type = str_replace( 'edit-', '', $screen->id );
        $query = $wpdb->prepare(
          " 
            SELECT COUNT(1) 
            FROM {$wpdb->posts} 
            WHERE `post_type` = %s
                AND `post_status` IN ('publish', 'pending', 'draft', 'future', 'private')
                AND `menu_order` = 1 
          ",
          $post_type
        );

        $zeros = $wpdb->get_var( $query );

        if( $zeros ) {
          $page = 1;
          $counter = 1;

          while( $posts = get_posts( array(
            'post_type'   => str_replace( 'edit-', '', $screen->id ),
            'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'fields'      => 'ids',
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'numberposts' => 100,
            'paged'       => $page
          ) ) ) {
            foreach( $posts as $post_ID ) {

              wp_update_post( array( 'ID' => $post_ID, 'menu_order' => $counter ) );

              $counter++;
            }

            $page++;            
          }  

        }
       
      }
    }

  } // redirect_to_orderby_menu_order

  public function orderby_menu_order( $orderby, $query ){
    /*
    if( !is_admin() ) {
      return 'menu_order';
    }
    */

    return $orderby;
  } // 


  public function post_created( int $post_ID, WP_Post $post, $update ) {
    if( $update ) {
      return;
    }

    if( !in_array( $post->post_type, array('post','page')) ) {
      return;
    }

    if( $post->menu_order !== 0 ) {
      return;
    }

    // get post maximum menu_order
    $args = array(
      'post_type'   => $post->post_type,
      'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
      'numberposts' => 1,
      'orderby'     => 'menu_order',
      'order'       => 'DESC',
    );

    $top = get_posts( $args );

    if( !count($top) ) {
      return;
    }

    $post->menu_order = $top[0]->menu_order + 1;

    wp_update_post( $post, false, false );
  } // post_created


  public function notice(){
    $screen = get_current_screen();

    if( $screen->id === 'edit-post' || $screen->id === 'edit-page' ) {
      if( !isset($_REQUEST['orderby']) || $_REQUEST['orderby'] !== 'menu_order' ) {
        ?>
        <div class="notice notice-info is-dismissible">
          <p><?php _e( 'To be able to reorder posts by drag and drop, please order posts by Order field', WP_EXTENDED_TEXT_DOMAIN ); ?></p>
        </div>
        <?php
      }
    }

  } // notice

public function route_register(){
  
    register_rest_route( 'wpext/v1', '/reorder', array(
      'methods' => 'POST',
      'callback' => array( $this, 'reorder_route' ),
      'permission_callback' => array( $this, 'route_rights_check' )
    ) );
  
  } // route_register

  public function route_rights_check(){
    return true;
    // return current_user_can( 'edit_others_posts' );
  }
  public function reorder_route(){
      try {
      $items = sanitize_post($_POST['items']);
      if( empty($items) ) {
        throw new \Exception( "Empty request" );
      }
      $errors = array();
      $saved = array();

      foreach( $items as $item ) {
        try {
          if( empty($item['id']) ) {
            throw new \Exception( "Item does not have ID: " . json_encode( $item ) );
          }
        
          $post = get_post( $item['id'] );

          if( !$post ) {
            throw new \Exception( "Post not found: " . json_encode( $item ) );
          }

          if( $post->menu_order === $item['order'] ) {
            throw new \Exception( "Item {$item->id} order has not changed" );
          }

          $post->menu_order = $item['order'];

          $updated = wp_update_post( $post );

          if( !$updated ) {
            throw new \Exception( "Update of post {$item->id} failed" );
          }

          if( is_wp_error($updated) ) {
            throw new \Exception( "Updated of post {$item->id} failed with " . implode( "\n", $updated->errors) );
          }

          $saved[] = $item;
        }
        catch( \Exception $e ) {
          $errors[] = $e->getMessage();
        }
      }
      $result = array( 'status' => true, 'errors' => $errors, 'saved' => $saved );
    }
    catch( \Exception $e ) {
      $result = array( 'status' => false, 'error' => $e->getMessage() );
    }
    wp_send_json( $result );
    wp_die();
  } // reorder_route
}
Wp_Extended_Post_Order::init();