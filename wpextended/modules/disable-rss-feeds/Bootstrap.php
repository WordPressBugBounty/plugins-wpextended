<?php

namespace Wpextended\Modules\DisableRssFeeds;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('disable-rss-feeds');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        // Remove default feed links from wp_head
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);

        // Disable individual feed endpoints
        remove_action('do_feed_rdf', 'do_feed_rdf', 10, 0);
        remove_action('do_feed_rss', 'do_feed_rss', 10, 0);
        remove_action('do_feed_rss2', 'do_feed_rss2', 10, 1);
        remove_action('do_feed_atom', 'do_feed_atom', 10, 1);

        add_action('template_redirect', array($this, 'redirectFeed'), 10, 1);
    }

    /**
     * Redirect /feed/ requests with a 403 Forbidden response and message.
     *
     * @return void
     */
    public function redirectFeed(): void
    {
        if (!is_feed()) {
            return;
        }

        status_header(403);
        wp_die(
            esc_html__('Feeds are disabled on this site.', WP_EXTENDED_TEXT_DOMAIN),
            esc_html__('403 Forbidden', WP_EXTENDED_TEXT_DOMAIN),
            array('response' => 403)
        );
    }
}
