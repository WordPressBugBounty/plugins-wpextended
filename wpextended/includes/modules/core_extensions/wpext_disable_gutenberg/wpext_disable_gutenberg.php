<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Disable_Gutenberg extends Wp_Extended {

    public function __construct() {
        parent::__construct();

        // Add filter to disable Gutenberg editor
        add_filter('use_block_editor_for_post', array($this, 'disable_gutenberg'), 101, 2); 
    }

    public static function init() {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new Wp_Extended_Disable_Gutenberg();
        }

        return $instance;  
    }

    /**
     * Disable Gutenberg editor for specific post types.
     *
     * @param bool $use_block_editor Whether to use the block editor.
     * @param WP_Post|null $post The post object.
     * @return bool Modified block editor usage decision.
     */
    public function disable_gutenberg($use_block_editor, $post) {
        // Ensure $post is a valid WP_Post object
        if (!$post instanceof WP_Post) {
            return $use_block_editor;
        }

        // Disable Gutenberg for specific post types
        if ($post->post_type === 'post' || $post->post_type === 'page') {
            return false;
        }

        // Default behavior
        return $use_block_editor;
    }
}

// Initialize the class
Wp_Extended_Disable_Gutenberg::init();
