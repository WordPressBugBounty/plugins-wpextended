<?php

if (! defined('ABSPATH') ) {
    die();
}

class Wp_Extended_Disable_Feeds extends Wp_Extended_Export
{
    private function __construct()
    {
        parent::__construct();
        
        // Remove default feed links from wp_head
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        
        // Disable individual feed endpoints
        remove_action('do_feed_rdf', 'do_feed_rdf', 10, 0);
        remove_action('do_feed_rss', 'do_feed_rss', 10, 0);
        remove_action('do_feed_rss2', 'do_feed_rss2', 10, 1);
        remove_action('do_feed_atom', 'do_feed_atom', 10, 1);
        
        // Redirect feed requests
        add_action('template_redirect', array($this, 'wpext_redirect_feed_to_page'), 10, 1);
    }

    public static function init()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Redirect /feed/ requests with a 403 Forbidden response and message.
     */
    public function wpext_redirect_feed_to_page()
    {
        // Check if this is a feed request
        if (is_feed()) {
            status_header(403);
            wp_die(
                esc_html__('Feeds are disabled on this site.', WP_EXTENDED_TEXT_DOMAIN),
                esc_html__('403 Forbidden', WP_EXTENDED_TEXT_DOMAIN),
                array('response' => 403)
            );
        }
    }
}

Wp_Extended_Disable_Feeds::init();
