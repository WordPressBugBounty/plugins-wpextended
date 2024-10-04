<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Export_Posts extends Wp_Extended_Export {
  
  public $formats = array( 'csv' );
  public $action = 'wpext-export-post';
  public $action_download = 'wpext-export-posts-download';

	public function __construct() {
    parent::__construct();
    add_action( 'admin_enqueue_scripts',   array( $this, 'scripts') );
    add_filter( "post_row_actions", array( $this, "add_export_action" ), 10, 2 );
    add_filter( "page_row_actions", array( $this, "add_export_action" ), 10, 2 );
    
    $types_array = array( 'attachment' , 'elementor_library' );
    $types = get_post_types( ['public'   => true ], 'objects' );
    foreach ($types as $type ) {
      if(!in_array( $type->name, $types_array )) {
        add_filter( "bulk_actions-edit-".$type->name,  array( $this, 'add_bulk_action'), 10, 1 );
        add_filter( "bulk_actions-edit-".$type->name,  array( $this, 'add_bulk_action'), 10, 1 );
        add_filter( "handle_bulk_actions-edit-".$type->name, array( $this, 'do_bulk_action'), 10, 3 );
        add_filter( "handle_bulk_actions-edit-".$type->name, array( $this, 'do_bulk_action'), 10, 3 );
      }
    }
    add_action( "wp_ajax_" . $this->action_download, array( $this, 'download_file_ajax' ) );
    add_action( "wp_ajax_" . $this->action, array( $this, 'download_post_ajax' ) );
    add_action( "post_submitbox_misc_actions", array( $this, 'metabox_button' ), 10, 1 );
  }
  public static function init(){
    static $instance = null;
    if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Export_Posts( get_called_class(), WP_EXTENDED_VERSION );
    }
    return $instance;  
  } // init
  public function get_items( $ids ){
    if( empty($ids ) ) {
      return null;
    }
  // get posts
    $params = array( 
      'include'     => $ids, 
      'numberposts' => -1, 
      'post_type'   => 'any',
      'post_status' => 'any'
    );
    $params = apply_filters( 'wpext-export-posts-params', $params );
    $posts = get_posts( $params );
    if( empty($posts) ) {
      return null;
    }
    $asArray = array();
    
    /**
     * 
     * From version 2.2.1
     * Fixed blank array issues.
     * post_category, tags_input,post_mime_type,ancestors
     */

    foreach ( $posts as $post ) {
        $post_array = $post->to_array();
        $categories = get_the_category( $post->ID );
        $categories_names = array();
          foreach ( $categories as $category ) {
            $categories_names[] = $category->name;
          }
        $post_array['post_category'] = implode( ',', $categories_names );
        // Get tags
        $tags = wp_get_post_tags( $post->ID );
        $tag_names = array();
          foreach ( $tags as $tag ) {
            $tag_names[] = $tag->name;
          }
        $post_array['tags_input'] = implode( ',', $tag_names );
        // Get post mime type
        $post_array['post_mime_type'] = $post->post_mime_type;

        // Get ancestors
        $ancestors = get_post_ancestors( $post->ID );
        $ancestors = array_reverse( $ancestors );
        $post_array['ancestors'] = implode( ',', $ancestors );
    
        $asArray[] = $post_array;
    }
    return $asArray;
  }
  public function download_post_ajax(){
    try {
       if ( ! wp_verify_nonce( $_GET['wpext_nonce'], 'wpext-ajax-nonce' ) ) {
        throw new \Exception( "Invalid nonce!" );  
        die();
      }
      $id = intval($_GET['id']);
      $format = !empty(sanitize_mime_type($_GET['format'])) ? sanitize_mime_type($_GET['format']) : 'csv';
       
      $post = get_post( $id );

      if( !$post ) {
        throw new \Exception( "Post not found" );
      }

      $items = $this->get_items( array($id) );

      if( !$items ) {
        throw new \Exception( "No items to export" );
      }
      if( !method_exists( $this, "export_{$format}" ) ) {
        throw new \Exception( "Format is not supported" );
      }
      $filepath = $this->{"export_{$format}"}( $items );
      if( !$filepath ) {
        throw new \Exception( "Export failed" );
      }
      $filename = basename( $filepath );
      $this->download_file( $filename );
    }
    catch( \Exception $e ) {
      wp_send_json( "Not Found" );
    }
    wp_die();
  } // download_post_ajax
  public function scripts(){
    $screen = get_current_screen();
    // add script that adds button to single post / page edit screens
    if( $screen->base == 'post' && in_array( $screen->id, array('post', 'page' ) ) ) {
      wp_enqueue_script( 'wpext-export-post-button',
        plugins_url("/wpext-export-post-button.js", __FILE__ ), 
        array('wp-element', 'wp-edit-post','wp-plugins'),
        filemtime( plugin_dir_path( __FILE__ ) . "/wpext-export-post-button.js" )
      );
    }
  } // scripts
  public function metabox_button( $post ) {
    $actions = $this->add_export_action( array(), $post );
    ?>
    <div class="misc-pub-section">
      <span>
        <span class="dashicons dashicons-download"></span>        
        <?php _e('Download as', WP_EXTENDED_TEXT_DOMAIN );?>:
      </span>
      <?php
        foreach( $actions as $action ) {
          echo "<strong>{$action}</strong>";
        }
      ?>
    </div>
    <?php
  } // profile_button
}

Wp_Extended_Export_Posts::init();