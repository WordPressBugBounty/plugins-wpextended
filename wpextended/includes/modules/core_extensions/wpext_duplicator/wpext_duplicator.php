<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Duplicator extends Wp_Extended {
  public function __construct() {
    parent::__construct();
    add_action( 'wp_ajax_wp-extended-duplicate-post', array( $this, 'ajax_duplicate_post' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 120 );
    add_filter( 'page_row_actions', array( $this, 'add_duplicate_button' ), 10, 2 );
    add_filter( 'post_row_actions', array( $this, 'add_duplicate_button' ), 10, 2 );
    add_action( 'admin_head-post.php', array( $this,'wpext_product_duplicate_button'));
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Duplicator( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function ajax_duplicate_post(){

    try {
      if ( ! current_user_can( 'edit_post', $post_ID ) ) {
        throw new \Exception( __( "You are not allowed to duplicate this post.", WP_EXTENDED_TEXT_DOMAIN ) );
      }
      if ( ! wp_verify_nonce( $_REQUEST['wpext_nonce'], 'wpext-ajax-nonce' ) ) {
        throw new \Exception( "Invalid nonce!" );  
      }
      $post_ID = intval($_REQUEST['post_ID']);
      if( empty($post_ID) ) {
        throw new \Exception( "Post ID not specified" );
      }
      $duplicate = $this->duplicate_post( $post_ID );
      if( is_wp_error( $duplicate ) ) {
        throw new \Exception( $duplicate->get_error_message() );
      }
      $duplicate->url = get_the_permalink( $duplicate->ID );
      $duplicate->edit_url = get_edit_post_link( $duplicate, 'json' );
      $result = array( 'status' => true, 'duplicate' => $duplicate );
  }
  catch( \Exception $e ) {
    $result = array( 'status' => false, 'error' => $e->getMessage() );
  }
  wp_send_json( $result );
  wp_die();
  } // ajax_duplicate_post

  public function duplicate_post( $post_ID ) {

    try {
      if ( ! current_user_can( 'edit_post', $post_ID ) ) {
        throw new \Exception( __( "You are not allowed to duplicate this post.", WP_EXTENDED_TEXT_DOMAIN ) );
      }
      if( empty($post_ID) ) {
        throw new \Exception( "Post ID not specified" );
      }
      $post = get_post( $post_ID );
      if( !$post ) {
        throw new \Exception( "Source post not found" );
      }
      // Ensure that contributors can't duplicate password-protected posts.
      if ( 'private' === $post->post_status && ! current_user_can( 'read_private_posts' ) ) {
          throw new \Exception( __( "You cannot duplicate this private post.", WP_EXTENDED_TEXT_DOMAIN ) );
      }

      // copy
      $newPost = json_decode( json_encode($post), true );
      $args = array(
      'post_title' => __('[duplicate]', WP_EXTENDED_TEXT_DOMAIN) . " " . esc_attr($newPost['post_title']),
      'comment_status' => $post->comment_status,
      'ping_status'    => $post->ping_status,
      'post_author'    => get_current_user_id(),
      'post_content'   => $post->post_content,
      'post_excerpt'   => $post->post_excerpt,
      'post_parent'    => $post->post_parent,
      'post_password'  => $post->post_password,
      'post_status'    => 'draft',
      'post_type'      => $post->post_type,
      'to_ping'        => $post->to_ping,
      'menu_order'     => $post->menu_order
    );
    $inserted = wp_insert_post( $args );

      if( !$inserted ) {
        throw new \Exception( "Failed to save new post" );
      }
      if( is_wp_error( $inserted ) ) {
        throw new \Exception( $inserted->get_error_message(), $inserted->get_error_code() );
      }
      $new = get_post( $inserted );
      /*
      * duplicate all post meta
      */
      $post_meta_keys = get_post_custom_keys( $post->ID );
      if(!empty($post_meta_keys)){
          foreach ( $post_meta_keys as $meta_key ) {
              $meta_values = get_post_custom_values( $meta_key, $post->ID );
              foreach ( $meta_values as $meta_value ) {
                $meta_value = maybe_unserialize( $meta_value );
                update_post_meta( $new->ID, $meta_key, wp_slash( $meta_value ) );
              }
          }
      }

      // copy taxonomies
      $taxonomies = get_object_taxonomies( $post );
      foreach( $taxonomies as $taxonomy ) {
        $terms = get_the_terms( $post, $taxonomy );
        if( is_wp_error( $terms ) || !$terms ) {
          continue;
        }
        $list = array();
        foreach( $terms as $term ) {
          $list[] = $term->term_id;
        }
        wp_set_post_terms( $new->ID, $list, $taxonomy );
      }
       /**
       * Elementor compatibility fixes
       */
      if(is_plugin_active( 'elementor/elementor.php' )){
        $css = Elementor\Core\Files\CSS\Post::create( $new->ID );
        $css->update();
      } 
      return $new;
    }
    catch( \Exception $e ) {
      return new WP_Error( $e->getCode(), $e->getMessage() );
    }

  } // duplicate_post
  public static function admin_scripts(){
    $screen = get_current_screen();
    $types_array = array( 'attachment' , 'elementor_library', 'elementor_library', 'e-landing-page','product'  );
    $types = get_post_types( ['public'   => true ], 'objects' );
     wp_register_script( 'wpext-duplicator', plugins_url("/js/wpext-duplicator.js", __FILE__), array(), 
      WP_EXTENDED_VERSION, true 
    );
    wp_register_script( 'wpext-duplicator-post', plugins_url("/js/wpext-duplicator-post.js", __FILE__), 
      array('wp-element', 'wp-edit-post','wp-plugins', 'wp-i18n', 'wpext-duplicator','wpext-edit-main-button'), 
      WP_EXTENDED_VERSION, true  
    ); 
    $p = array( 'wpext_post_nonce'   => wp_create_nonce('wpext-ajax-nonce') );
    wp_add_inline_script( 'wpext-duplicator-post', 
      'const wpext_post_nonce = ' . json_encode( $p ), 
      'before' );

    foreach ($types as $type ) { 
     if(!in_array( $type->name, $types_array )) {
        if( preg_match( '/^edit-('.$type->name.'|)/', $screen->id ) ) {
          wp_enqueue_script( 'wpext-duplicator' );
        }
        wp_enqueue_script( 'wpext-duplicator-post' ); 
     }
   }
  }

  public function add_duplicate_button( $actions, $post ){
    $types_array = array( 'attachment' , 'elementor_library', 'elementor_library', 'e-landing-page', 'product');
    $types = get_post_types( ['public'   => true ], 'objects' );
    foreach ($types as $type ) {
     if(!in_array( $type->name, $types_array )) {
      if($post->post_type != 'product') {
         $actions['wp_extended_duplicate'] = sprintf(
          '<a href="%s" aria-label="%s" data-duplicate>%s</a>',
          admin_url( 'admin-ajax.php?action=wp-extended-duplicate-post&wpext_nonce='.wp_create_nonce('wpext-ajax-nonce').'&post_ID=' . $post->ID ),
          esc_attr( __( 'Duplicate', WP_EXTENDED_TEXT_DOMAIN ) ),
          __( 'Duplicate', WP_EXTENDED_TEXT_DOMAIN )
        );
        wp_enqueue_script( 'wpext-duplicator-post' );
        }
    }
  }
    return $actions;
  } // add_duplicate_button
   
  public function wpext_product_duplicate_button() {
    global $current_screen,$post;
    if ('product' != $current_screen->post_type) {
        return;
    }
    $admin_url = admin_url( 'admin-ajax.php?action=wp-extended-duplicate-post&wpext_nonce='.wp_create_nonce('wpext-ajax-nonce').'&post_ID=' .$post->ID );
    $duplicate_btn = '<a href= "'.$admin_url.'" class="page-title-action" data-duplicate>'. __('Duplicate', WP_EXTENDED_TEXT_DOMAIN).'</a>';
    echo "<script type='text/javascript'>
          jQuery(document).ready( function($) { jQuery('.wrap a:first').after('".$duplicate_btn."'); }); </script>"; 
      }
}
Wp_Extended_Duplicator::init(); 