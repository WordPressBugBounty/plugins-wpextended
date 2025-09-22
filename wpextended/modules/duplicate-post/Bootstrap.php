<?php

namespace Wpextended\Modules\DuplicatePost;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * Main Bootstrap class for the Duplicate Post module
 *
 * @package Wpextended\Modules\DuplicatePost
 */
class Bootstrap extends BaseModule
{
    /**
     * Default excluded post types
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
     * Default excluded meta keys
     *
     * @var array
     */
    public $excludedMeta = array(
        '_edit_lock',
        '_edit_last',
        '_elementor_css',         // Elementor CSS cache
        '_elementor_page_assets', // Elementor assets cache
    );

    /**
     * Integrations handler instance
     *
     * @var Integrations
     */
    public $integrations;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('duplicate-post');

        // Allow plugins to modify excluded items
        $this->excludedPostTypes = apply_filters('wpextended/duplicate-post/excluded_post_types', $this->excludedPostTypes);
        $this->excludedMeta = apply_filters('wpextended/duplicate-post/excluded_meta', $this->excludedMeta);

        // Initialize integrations handler
        $this->integrations = new Integrations();
    }

    /**
     * Initialize the module
     * This runs every time WordPress loads if the module is enabled
     */
    protected function init()
    {
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'registerRestRoutes'));

        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'), 120);
        add_filter('page_row_actions', array($this, 'addDuplicateButton'), 10, 2);
        add_filter('post_row_actions', array($this, 'addDuplicateButton'), 10, 2);
        add_action('admin_head-post.php', array($this, 'addProductDuplicateButton'));
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes()
    {
        register_rest_route(
            \WP_EXTENDED_API_NAMESPACE,
            '/duplicate-post/duplicate/(?P<id>\d+)',
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handleDuplicateRequest'),
                'permission_callback' => array($this, 'checkDuplicatePermission'),
                'args' => array(
                    'id' => array(
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && absint($param) > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
                'schema' => array($this, 'getDuplicateSchema'),
            )
        );
    }

    /**
     * Get the schema for the duplicate endpoint
     *
     * @return array
     */
    public function getDuplicateSchema()
    {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'duplicate',
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the duplicated post.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'        => 'integer',
                ),
                'title' => array(
                    'description' => __('The title of the duplicated post.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'        => 'string',
                ),
                'url' => array(
                    'description' => __('The URL of the duplicated post.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'        => 'string',
                    'format'      => 'uri',
                ),
                'edit_url' => array(
                    'description' => __('The edit URL of the duplicated post.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'        => 'string',
                    'format'      => 'uri',
                ),
                'status' => array(
                    'description' => __('The status of the duplicated post.', WP_EXTENDED_TEXT_DOMAIN),
                    'type'        => 'string',
                ),
            ),
        );
    }

    /**
     * Check if user has permission to duplicate posts
     *
     * @param \WP_REST_Request $request The request object
     * @return true|\WP_Error
     */
    public function checkDuplicatePermission(\WP_REST_Request $request)
    {
        if (!current_user_can('edit_posts')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to duplicate posts.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => rest_authorization_required_code())
            );
        }

        // Check if post exists and user can edit it
        $postId = $request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error(
                'rest_post_invalid_id',
                __('Invalid post ID.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 404)
            );
        }

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_Error(
                'rest_cannot_edit',
                __('Sorry, you are not allowed to edit this post.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => rest_authorization_required_code())
            );
        }

        return true;
    }

    /**
     * Handle duplicate post request
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleDuplicateRequest(\WP_REST_Request $request)
    {
        $postId = $request->get_param('id');

        try {
            $duplicate = $this->duplicatePost($postId);
            if (is_wp_error($duplicate)) {
                return $duplicate;
            }

            $responseData = array(
                'id' => $duplicate->ID,
                'title' => $this->getPostTitle($duplicate->ID),
                'url' => get_permalink($duplicate->ID),
                'edit_url' => get_edit_post_link($duplicate->ID, 'rest'),
                'status' => $duplicate->post_status,
            );

            return new \WP_REST_Response($responseData, 201);
        } catch (\Exception $e) {
            return new \WP_Error(
                'duplicate_failed',
                $e->getMessage(),
                array('status' => 500)
            );
        }
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

        // Main duplicator script
        Utils::enqueueScript(
            'wpext-duplicate-post',
            $this->getPath('assets/js/script.js'),
            array('wp-api-fetch', 'wpext-notify')
        );

        // Localize script data
        wp_localize_script(
            'wpext-duplicate-post',
            'wpextDuplicatePost',
            array(
                'root' => WP_EXTENDED_API_NAMESPACE,
                'nonce' => wp_create_nonce('wp_rest'),
                'excludedTypes' => $this->excludedPostTypes,
                'postRedirect' => $this->getSetting('post_redirect', 0),
                'isEditScreen' => $screen->base === 'post',
                'isBlockEditor' => Utils::isBlockEditor(),
                'i18n' => array(
                    'duplicate' => __('Duplicate', WP_EXTENDED_TEXT_DOMAIN),
                    'duplicate_post' => sprintf(
                        __('Duplicate %s', WP_EXTENDED_TEXT_DOMAIN),
                        $this->getPostTypeLabel()
                    ),
                    'duplicating' => __('Duplicating...', WP_EXTENDED_TEXT_DOMAIN),
                    'success' => __('Post duplicated successfully', WP_EXTENDED_TEXT_DOMAIN),
                    'error' => __('Duplication failed. Please try again.', WP_EXTENDED_TEXT_DOMAIN),
                    'errorInvalid' => __('Invalid response from server', WP_EXTENDED_TEXT_DOMAIN),
                ),
            )
        );

        // Post editor integration
        if (Utils::isBlockEditor()) {
            Utils::enqueueScript(
                'wpext-duplicate-post-editor',
                $this->getPath('assets/js/script-editor.js'),
                array(
                    'wpext-duplicate-post',
                    'wp-plugins',
                    'wp-edit-post',
                    'wp-element',
                    'wp-components',
                )
            );
        }
    }

    /**
     * Add duplicate button to post row actions
     *
     * @param array $actions
     * @param WP_Post $post
     * @return array
     */
    public function addDuplicateButton($actions, $post)
    {
        if (!$this->isEnabled()) {
            return $actions;
        }

        $actions['duplicate'] = sprintf(
            '<a href="#" class="wpext-duplicate-post" data-id="%d" aria-label="%s">%s</a>',
            esc_attr($post->ID),
            esc_attr(sprintf(
                /* translators: %s: post title */
                __('Duplicate %s', WP_EXTENDED_TEXT_DOMAIN),
                get_the_title($post->ID)
            )),
            esc_html__('Duplicate', WP_EXTENDED_TEXT_DOMAIN)
        );

        return $actions;
    }

    /**
     * Add duplicate button to product edit screen
     */
    public function addProductDuplicateButton()
    {
        global $post;

        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }

        if (!in_array($post->post_type, array('product'))) {
            return;
        }

        if (!$this->isEnabled()) {
            return;
        }

        printf(
            '<div class="misc-pub-section"><a href="#" class="wpext-duplicate-post button" data-id="%d">%s</a></div>',
            esc_attr($post->ID),
            esc_html__('Duplicate Product', WP_EXTENDED_TEXT_DOMAIN)
        );
    }

    /**
     * Duplicate a post
     *
     * @param int $postId Post ID to duplicate
     * @return \WP_Post|\WP_Error
     */
    public function duplicatePost($postId)
    {
        $post = get_post($postId);
        if (!$post) {
            return new \WP_Error('invalid_post', __('Invalid post ID.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Create new post
        $newPost = array(
            'post_title'    => $this->getPostTitle($postId),
            'post_content'  => $post->post_content,
            'post_excerpt'  => $post->post_excerpt,
            'post_status'   => $this->getPostStatus($postId),
            'post_type'     => $post->post_type,
            'post_author'   => get_current_user_id(),
            'post_parent'   => $post->post_parent,
            'menu_order'    => $post->menu_order,
            'to_ping'       => $post->to_ping,
            'pinged'        => $post->pinged,
        );

        // Insert the post
        $newPostId = wp_insert_post($newPost, true);
        if (is_wp_error($newPostId)) {
            return $newPostId;
        }

        // Duplicate post meta
        $this->duplicatePostMeta($postId, $newPostId);

        // Duplicate taxonomies
        $this->duplicateTaxonomies($postId, $newPostId);

        // Handle plugin integrations
        $this->integrations->handlePluginIntegrations($newPostId);

        return get_post($newPostId);
    }

    /**
     * Get post title for duplicate
     *
     * @param int $postId Original post ID
     * @return string
     */
    protected function getPostTitle($postId)
    {
        $title = get_the_title($postId);
        return apply_filters('wpextended/duplicate-post/post_title', $title, $postId);
    }

    /**
     * Get post status for duplicate
     *
     * @param int $postId Original post ID
     * @return string
     */
    protected function getPostStatus($postId)
    {
        $status = 'draft';
        return apply_filters('wpextended/duplicate-post/post_status', $status, $postId);
    }

    /**
     * Duplicate post meta
     *
     * @param int $sourceId Source post ID
     * @param int $targetId Target post ID
     */
    protected function duplicatePostMeta($sourceId, $targetId)
    {
        $meta = get_post_meta($sourceId);
        if (!is_array($meta)) {
            return;
        }

        foreach ($meta as $key => $values) {
            if (in_array($key, $this->excludedMeta)) {
                continue;
            }

            foreach ($values as $value) {
                $value = maybe_unserialize($value);
                add_post_meta($targetId, $key, $value);
            }
        }
    }

    /**
     * Duplicate post taxonomies
     *
     * @param int $sourceId Source post ID
     * @param int $targetId Target post ID
     */
    protected function duplicateTaxonomies($sourceId, $targetId)
    {
        $taxonomies = get_object_taxonomies(get_post_type($sourceId));
        if (!is_array($taxonomies)) {
            return;
        }

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($sourceId, $taxonomy, array('fields' => 'slugs'));
            if (!is_wp_error($terms)) {
                wp_set_object_terms($targetId, $terms, $taxonomy);
            }
        }
    }

    /**
     * Check if the duplicate post is enabled
     *
     * @return bool
     */
    protected function isEnabled()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        // Check if post type is excluded
        if (in_array($screen->post_type, $this->excludedPostTypes)) {
            return false;
        }

        return apply_filters('wpextended/duplicate-post/is_enabled', true, $screen);
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
