<?php
 
if ( ! defined( 'ABSPATH' ) ) {
    die(); // Prevent direct access to the file
}

// Class for exporting posts in various formats
class Wp_Extended_Export_Posts extends Wp_Extended_Export {
  
    // Define supported export formats
    public $formats = array( 'csv' );
    public $action = 'wpext-export-post'; // Action name for single post export
    public $action_download = 'wpext-export-posts-download'; // Action name for bulk export

    public function __construct() {
        parent::__construct(); // Call parent constructor

        // Enqueue scripts and add export actions for posts and pages
        add_action( 'admin_enqueue_scripts',   array( $this, 'scripts' ) );
        add_filter( "post_row_actions", array( $this, "add_export_action" ), 10, 2 );
        add_filter( "page_row_actions", array( $this, "add_export_action" ), 10, 2 );

        // Exclude specific post types from export actions
        $types_array = array( 'attachment' , 'elementor_library' );
        $types = get_post_types( ['public' => true], 'objects' );
        foreach ( $types as $type ) {
            if (!in_array( $type->name, $types_array )) {
                // Add bulk export actions for supported post types
                add_filter( "bulk_actions-edit-" . $type->name, array( $this, 'add_bulk_action' ), 10, 1 );
                add_filter( "handle_bulk_actions-edit-" . $type->name, array( $this, 'do_bulk_action' ), 10, 3 );
            }
        }

        // Register AJAX actions for downloading posts
        add_action( "wp_ajax_" . $this->action_download, array( $this, 'download_file_ajax' ) );
        add_action( "wp_ajax_" . $this->action, array( $this, 'download_post_ajax' ) );
        
        // Add a download button to the post edit screen
        add_action( "post_submitbox_misc_actions", array( $this, 'metabox_button' ), 10, 1 );
    }

    // Initialize the class instance
    public static function init() {
        static $instance = null;
        if ( is_null( $instance ) ) {
            $instance = new Wp_Extended_Export_Posts( get_called_class(), WP_EXTENDED_VERSION );
        }
        return $instance;  
    }

    // Retrieve items based on post IDs
    public function get_items( $ids ) {
        if ( empty($ids) ) {
            return null; // Return null if no IDs are provided
        }

        // Set up parameters to retrieve posts
        $params = array( 
            'include'     => $ids, 
            'numberposts' => -1, 
            'post_type'   => 'any',
            'post_status' => 'any'
        );

        // Allow filters to modify the parameters
        $params = apply_filters( 'wpext-export-posts-params', $params );
        $posts = get_posts( $params );

        if ( empty($posts) ) {
            return null; // Return null if no posts are found
        }

        $asArray = array();

        // Loop through posts to collect data
        foreach ( $posts as $post ) {
            $post_array = $post->to_array(); // Convert post object to array
            
            // Get post categories
            $categories = get_the_category( $post->ID );
            $categories_names = array();
            foreach ( $categories as $category ) {
                $categories_names[] = $category->name;
            }
            $post_array['post_category'] = implode( ',', $categories_names );

            // Get post tags
            $tags = wp_get_post_tags( $post->ID );
            $tag_names = array();
            foreach ( $tags as $tag ) {
                $tag_names[] = $tag->name;
            }
            $post_array['tags_input'] = implode( ',', $tag_names );

            // Get post mime type and ancestors
            $post_array['post_mime_type'] = $post->post_mime_type;
            $ancestors = get_post_ancestors( $post->ID );
            $ancestors = array_reverse( $ancestors );
            $post_array['ancestors'] = implode( ',', $ancestors );

            $asArray[] = $post_array; // Add post array to the result set
        }
        return $asArray; // Return the array of post data
    }

    // Handle AJAX request for downloading a single post
    public function download_post_ajax() {
        try {
            // Verify nonce for security
            if ( ! wp_verify_nonce( $_GET['wpext_nonce'], 'wpext-ajax-nonce' ) ) {
                throw new \Exception( "Invalid nonce!" );  
            }

            // Get post ID and export format
            $id = intval($_GET['id']);
            $format = !empty(sanitize_mime_type($_GET['format'])) ? sanitize_mime_type($_GET['format']) : 'csv';

            $post = get_post( $id );

            if ( !$post ) {
                throw new \Exception( "Post not found" );
            }

            $items = $this->get_items( array($id) );

            if ( !$items ) {
                throw new \Exception( "No items to export" );
            }

            // Check if the export format method exists
            if ( !method_exists( $this, "export_{$format}" ) ) {
                throw new \Exception( "Format is not supported" );
            }

            // Generate the export file
            $filepath = $this->{"export_{$format}"}( $items );
            if ( !$filepath ) {
                throw new \Exception( "Export failed" );
            }

            // Download the generated file
            $filename = basename( $filepath );
            $this->download_file( $filename );
        } catch ( \Exception $e ) {
            // Send error message in JSON response
            wp_send_json_error( $e->getMessage() );
        }
        wp_die(); // Terminate the script
    }

    // Enqueue scripts for the admin area
    public function scripts() {
        $screen = get_current_screen();
        // Enqueue script only on post/page edit screens
        if ( $screen->base == 'post' && in_array( $screen->id, array('post', 'page') ) ) {
            wp_enqueue_script( 'wpext-export-post-button',
                plugins_url("/wpext-export-post-button.js", __FILE__), 
                array('wp-element', 'wp-edit-post', 'wp-plugins'),
                filemtime( plugin_dir_path( __FILE__ ) . "/wpext-export-post-button.js" )
            );
        }
    }

    // Add a download button in the post edit screen
    public function metabox_button( $post ) {
        $actions = $this->add_export_action( array(), $post ); // Get export actions
        ?>
        <div class="misc-pub-section">
            <span>
                <span class="dashicons dashicons-download"></span>        
                <?php _e('Download as', WP_EXTENDED_TEXT_DOMAIN ); ?>:
            </span>
            <?php
                // Display available export actions
                foreach ( $actions as $action ) {
                    echo "<strong>" . $action . "</strong>"; // Escape output for security fix
                }
            ?>
        </div>
        <?php
    }
}

// Initialize the class instance
Wp_Extended_Export_Posts::init();