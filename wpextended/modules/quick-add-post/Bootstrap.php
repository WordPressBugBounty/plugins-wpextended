<?php

namespace WPExtended\Modules\QuickAddPost;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * Quick Add Post module
 */
class Bootstrap extends BaseModule
{
    /**
     * Excluded post types
     *
     * @var array
     */
    public $excludedPostTypes = array(
        'attachment',
        'elementor_library',
        'e-landing-page',
        'product',
        'sfwd-courses'
    );

    /**
     * Module constructor
     */
    public function __construct()
    {
        parent::__construct('quick-add-post');

        $this->excludedPostTypes = apply_filters('wpextended/quick-add-post/excluded_post_types', $this->excludedPostTypes);
    }

    /**
     * Initialize the module
     */
    public function init()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'), 10);
    }

    /**
     * Enqueue module assets
     */
    public function enqueueAssets()
    {
        if (!$this->isEnabled()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        Utils::enqueueNotify();

        // Only enqueue if we're in the block editor
        if (Utils::isBlockEditor()) {
            Utils::enqueueScript(
                'wpext-quick-add-post',
                $this->getPath('assets/js/script.js'),
                array('wp-data', 'wp-components', 'wp-element', 'wpext-notify')
            );

            wp_localize_script(
                'wpext-quick-add-post',
                'wpextQuickAddPost',
                array(
                    'isEditScreen' => $screen->base === 'post',
                    'isBlockEditor' => Utils::isBlockEditor(),
                    'postType' => $screen->post_type,
                    'newPostUrl' => admin_url(sprintf('post-new.php?post_type=%s', $screen->post_type)),
                    'i18n' => array(
                        'new' => __('New', WP_EXTENDED_TEXT_DOMAIN),
                        'new_post' => sprintf(
                            /* translators: %s: post type singular name */
                            __('New %s', WP_EXTENDED_TEXT_DOMAIN),
                            $this->getPostTypeLabel()
                        ),
                    ),
                )
            );
        }
    }

    /**
     * Check if the quick add post is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        // Check if post type is excluded
        if (in_array($screen->post_type, $this->excludedPostTypes)) {
            return false;
        }

        return apply_filters('wpextended/quick-add-post/is_enabled', true, $screen);
    }


    /**
     * Get the post type label
     *
     * @return string
     */
    public function getPostTypeLabel()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return '';
        }

        $post_type = get_post_type_object($screen->post_type);

        if (!$post_type) {
            return '';
        }

        if (isset($post_type->labels->singular_name)) {
            return $post_type->labels->singular_name;
        }

        return $screen->post_type;
    }
}
