<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Disable_Widgets_Gutenberg extends Wp_Extended {

    public function __construct() {
        parent::__construct();

        // Disable the block editor for widgets in both Gutenberg and WordPress.
        add_filter('gutenberg_use_widgets_block_editor', array($this, 'disable_widgets_block_editor'));
        add_filter('use_widgets_block_editor', array($this, 'disable_widgets_block_editor'));
    }

    public static function init() {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new Wp_Extended_Disable_Widgets_Gutenberg();
        }

        return $instance;  
    }

    /**
     * Disable the widgets block editor.
     *
     * @return bool Always returns false to disable the block editor for widgets.
     */
    public function disable_widgets_block_editor() {
        return false;
    }
}

// Initialize the class
Wp_Extended_Disable_Widgets_Gutenberg::init();
