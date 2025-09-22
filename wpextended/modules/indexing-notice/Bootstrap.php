<?php

namespace Wpextended\Modules\IndexingNotice;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('indexing-notice');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init(): void
    {
        // Enqueue assets
        add_action('admin_head', array($this, 'enqueueAssets'));
        add_action('wp_head', array($this, 'enqueueAssets'));

        add_action('admin_bar_menu', array($this, 'addNotice'), 1000, 1);
    }

    /**
     * Determine if the module should run.
     *
     * @return bool True if the module should run, false otherwise.
     */
    public function shouldRun(): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        if (!is_admin_bar_showing()) {
            return false;
        }

        if (get_option('blog_public') != 0) {
            return false;
        }

        return true;
    }

    /**
     * Enqueue module assets.
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        Utils::enqueueStyle(
            'wpext-indexing-notice',
            $this->getPath('assets/css/style.css')
        );
    }

    /**
     * Add notice to the admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance.
     * @return void
     */
    public function addNotice($wp_admin_bar): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'wpext-indexing-notice',
            'parent' => 'top-secondary',
            'title' => __('Search Engines Discouraged', WP_EXTENDED_TEXT_DOMAIN),
            'href'  => admin_url('options-reading.php'),
        ));
    }
}
