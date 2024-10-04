<?php

if (! defined('ABSPATH') ) {
    die();
}

class Wp_Extended_Indexing_Notice extends Wp_Extended
{

    /**
     * Constructor function.
     * Initializes the plugin and adds necessary actions.
     */
    public function __construct()
    {
        parent::__construct();
        add_action('admin_head',  array($this, 'wpext_admin_bar_notice_styles'));
        add_action('wp_head', array($this, 'wpext_admin_bar_notice_styles'));
        add_action('admin_bar_menu', array($this, 'wpext_admin_bar_notice'), 1000, 1);
    }

    /**
     * Initializes the plugin instance.
     *
     * @return Wp_Extended_Indexing_Notice The plugin instance.
     */
    public static function init()
    {
        static $instance = null;
        if (is_null($instance) ) {
            $instance = new Wp_Extended_Indexing_Notice(get_called_class(), WP_EXTENDED_VERSION);
        }
        return $instance;
    }

    /**
     * Enqueues the CSS stylesheet for the admin bar notice.
     */
    public function wpext_admin_bar_notice_styles()
    {

        if (current_user_can('manage_options') && is_admin_bar_showing()) {
            if (get_option('blog_public') == '0') {
                wp_enqueue_style('wp-extended_indexing_notice', plugin_dir_url(__FILE__) . "css/wpext_indexing_notice.css", array(), WP_EXTENDED_VERSION);
            }
        }
    }

    /**
     * Adds the admin bar notice if the user is an administrator and the admin bar is showing.
     * The notice is added if the 'blog_public' option is set to '0'.
     *
     * @param object $wp_admin_bar The WordPress admin bar object.
     */
    public function wpext_admin_bar_notice($wp_admin_bar)
    {
        // Check if the user is an administrator and the admin bar is showing
        if (current_user_can('manage_options') && is_admin_bar_showing()) {
            // Check if the 'blog_public' option is set to '0'
            if (get_option('blog_public') == '0') {
                $args = array(
                    'id'    => 'wpext_notice',
                    'parent' => 'top-secondary',
                    'title' => 'Search Engines Discouraged',
                    'href'  => admin_url('options-reading.php'),
                    'meta'  => array('class' => 'wpext-indexing-notice') // Use your custom class for styling
                );
                $wp_admin_bar->add_node($args);
            }
        }
    }
}
// Initialize this module class
Wp_Extended_Indexing_Notice::init();
