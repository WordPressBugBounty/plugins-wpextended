<?php

namespace Wpextended\Modules\PostTypeSwitcher;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

/**
 * PostTypeSwitcher module Bootstrap class.
 *
 * Handles post type switching functionality in WordPress admin.
 */
class Bootstrap extends BaseModule
{
    /**
     * Constructor.
     */
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('post-type-switcher');
    }

    /**
     * Initialize the module.
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueueAssets'));
        add_filter('add_meta_boxes', array($this, 'addMetaBox'));

        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'registerRestRoutes'));
    }

    /**
     * Get settings fields for the module
     *
     * @param array $settings Current settings
     * @return array Modified settings
     */
    protected function getSettingsFields(): array
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Post Type Switcher', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id'    => 'settings',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields'        => array(
                    array(
                        'id' => 'post_types',
                        'type' => 'checkboxes',
                        'title' => __('Post Types', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select the post types to enable post type switcher for.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices' => $this->getPostTypes('array'),
                    )
                )
            ),
        );

        return $settings;
    }

    /**
     * Register REST API routes for post type switching.
     *
     * @return void
     */
    public function registerRestRoutes()
    {
        register_rest_route(WP_EXTENDED_API_NAMESPACE, '/post-type-switcher', array(
            'methods' => 'POST',
            'callback' => array($this, 'handlePostTypeSwitch'),
            'permission_callback' => array($this, 'checkPermission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'post_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ));
    }

    /**
     * Check if user has permission to switch post types.
     *
     * @return bool Whether the current user has permission
     */
    public function checkPermission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Handle post type switch REST request.
     *
     * @param \WP_REST_Request $request The REST request object
     *
     * @return \WP_REST_Response|\WP_Error Response object on success, error object on failure
     */
    public function handlePostTypeSwitch(\WP_REST_Request $request)
    {
        $post_id = $request->get_param('post_id');
        $post_type = $request->get_param('post_type');

        // Get post type object
        $post_type_object = get_post_type_object($post_type);

        // Check if post type exists
        if (empty($post_type_object)) {
            return new \WP_Error(
                'invalid_post_type',
                __('Invalid post type', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 400)
            );
        }

        if (!$this->isSelectedPostType(get_post_type($post_id))) {
            return new \WP_Error(
                'invalid_post_type',
                __('Invalid post type', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 400)
            );
        }

        // Check user capabilities
        if (!current_user_can($post_type_object->cap->publish_posts)) {
            return new \WP_Error(
                'insufficient_permissions',
                __('Sorry, you cannot do this.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 403)
            );
        }

        // Update the post type
        $result = set_post_type($post_id, $post_type);

        if (!$result) {
            return new \WP_Error(
                'post_type_update_failed',
                __('Failed to update post type.', WP_EXTENDED_TEXT_DOMAIN),
                array('status' => 500)
            );
        }

        // Get the raw edit link for the post with the new post type (not HTML-escaped)
        $edit_link = get_edit_post_link($post_id, 'raw');

        return new \WP_REST_Response(array(
            'success' => true,
            'message' => __('Post type updated successfully.', WP_EXTENDED_TEXT_DOMAIN),
            'edit_link' => $edit_link,
        ), 200);
    }

    /**
     * Enqueue assets for both block editor and classic editor
     *
     * @return void
     */
    public function enqueueAssets()
    {
        if (!is_admin()) {
            return;
        }

        $current_post_type = get_post_type();

        if (!$this->isSelectedPostType($current_post_type)) {
            return;
        }

        // Get post types before using them
        $post_types = $this->getPostTypes('names');

        // Load notification library for admin notices
        if (!Utils::isBlockEditor()) {
            Utils::enqueueNotify();
        }

        // Enqueue shared CSS and JS
        Utils::enqueueStyle(
            'wpextended-post-type-switcher',
            $this->getPath('assets/css/style.css')
        );

        Utils::enqueueScript(
            'wpextended-post-type-switcher',
            $this->getPath('assets/js/script.js'),
            Utils::isBlockEditor() ? array() : array('wpext-notify')
        );

        // Localize script data for both editors
        wp_localize_script(
            'wpextended-post-type-switcher',
            'wpextendedPostTypeSwitcher',
            array(
                'rest_url' => rest_url(WP_EXTENDED_API_NAMESPACE . '/post-type-switcher'),
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'is_block_editor' => Utils::isBlockEditor(),
                'post_types' => $post_types,
                'current_post_type' => $current_post_type,
                'current' => array(
                    'post_type' => $current_post_type,
                    'post_id' => get_the_ID(),
                ),
                'i18n' => $this->getTranslations()
            )
        );
    }

    /**
     * Add a meta box to the current post type
     *
     * @param string $post_type The current post type
     * @return void
     */
    public function addMetaBox($post_type)
    {
        if (Utils::isBlockEditor()) {
            return;
        }

        if (!$this->isSelectedPostType($post_type)) {
            return;
        }

        add_meta_box(
            'wpextended-post-type-switcher',
            __('Post Type Switcher', WP_EXTENDED_TEXT_DOMAIN),
            array($this, 'renderMetaBox'),
            $post_type,
            'side',
            'high'
        );
    }

    /**
     * Callback for the meta box rendering
     *
     * @return void
     */
    public function renderMetaBox()
    {
        if (Utils::isBlockEditor()) {
            return;
        }

        $current_post_type = get_post_type();
        $post_types = $this->getPostTypes();

        if (!$this->isSelectedPostType($current_post_type)) {
            return;
        }

        // Remove current post type from the list
        unset($post_types[$current_post_type]);

        $select_options = array();

        $select_options[] = array(
            'value' => '',
            'label' => __('Select Post Type', WP_EXTENDED_TEXT_DOMAIN),
        );

        foreach ($post_types as $post_type => $type) {
            if (!current_user_can($type->cap->publish_posts)) {
                continue;
            }
            foreach ($post_types as $post_type => $type) {
                if (!current_user_can($type->cap->publish_posts)) {
                    continue;
                }

                $select_options[] = array(
                    'value' => $post_type,
                    'label' => $type->labels->singular_name,
                );
            }

            $select_field = sprintf(
                '<select name="wpextended_post_type_switcher" class="wpextended-post-type-switcher__select">
                %s
            </select>',
                implode('', array_map(function ($option) {
                    return sprintf(
                        '<option value="%s">%s</option>',
                        $option['value'],
                        $option['label']
                    );
                }, $select_options))
            );
        }

        $select_field = sprintf(
            '<select name="wpextended_post_type_switcher" class="wpextended-post-type-switcher__select">
                %s
            </select>',
            implode('', array_map(function ($option) {
                return sprintf(
                    '<option value="%s">%s</option>',
                    $option['value'],
                    $option['label']
                );
            }, $select_options))
        );

        printf(
            '<div class="wpextended-post-type-switcher-container">
                %s
                <button class="button wpextended-post-type-switcher__button">%s</button>
            </div>',
            $select_field,
            esc_html__('Switch', WP_EXTENDED_TEXT_DOMAIN)
        );
    }

    /**
     * Get post types that can be switched to
     *
     * @param string $return Return format: 'objects' or 'names'
     * @return array The available post types
     */
    private function getPostTypes($return = 'objects')
    {
        $exclude_post_types = apply_filters(
            'wpextended/post-type-switcher/exclude_post_types',
            array('attachment', 'elementor_library', 'e-landing-page')
        );

        // Get switchable types
        $post_types = get_post_types(
            array(
                'public'  => true,
                'show_ui' => true
            ),
            'objects'
        );

        // Unset excluded post types
        foreach ($exclude_post_types as $post_type) {
            if (isset($post_types[$post_type])) {
                unset($post_types[$post_type]);
            }
        }

        switch ($return) {
            case 'objects':
                return $post_types;
            case 'names':
                return array_keys($post_types);
            case 'labels':
                return array_map(function ($post_type) {
                    return $post_type->labels->singular_name;
                }, $post_types);
            case 'array':
                $result = array();
                foreach ($post_types as $post_type) {
                    $result[$post_type->name] = $post_type->labels->singular_name;
                }
                return $result;
            default:
                return $post_types;
        }
    }

    /**
     * Get translations for the post type switcher
     *
     * @return array Translated strings
     */
    public function getTranslations()
    {
        $i18n = array(
            'postTypeLabel' => __('Post Type', WP_EXTENDED_TEXT_DOMAIN),
            'confirmChange' => __('Are you sure you want to change the post type? Any unsaved changes will be lost.', WP_EXTENDED_TEXT_DOMAIN),
            'successNotice' => __('Post type updated to %s', WP_EXTENDED_TEXT_DOMAIN),
            'errorNotice' => __('Failed to update post type: %s', WP_EXTENDED_TEXT_DOMAIN),
            'ariaLabel' => __('Change post type', WP_EXTENDED_TEXT_DOMAIN),
            'noOptionsAvailable' => __('No alternative post types available', WP_EXTENDED_TEXT_DOMAIN),
            'switchButton' => __('Switch', WP_EXTENDED_TEXT_DOMAIN),
            'switching' => __('Switching...', WP_EXTENDED_TEXT_DOMAIN),
            'selectPostType' => __('Please select a post type', WP_EXTENDED_TEXT_DOMAIN),
            'successMessage' => __('Post type updated successfully.', WP_EXTENDED_TEXT_DOMAIN),
            'errorMessage' => __('Failed to update post type.', WP_EXTENDED_TEXT_DOMAIN),
        );

        return apply_filters('wpextended/post-type-switcher/translations', $i18n);
    }

    /**
     * Check if the post type is selected in settings
     *
     * @param string $post_type The post type to check
     * @return bool Whether the post type is selected
     */
    public function isSelectedPostType($post_type)
    {
        $post_types = $this->getSetting('post_types');

        if (!$post_types) {
            return false;
        }

        return in_array($post_type, $post_types);
    }
}
