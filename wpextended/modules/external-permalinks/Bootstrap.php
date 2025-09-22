<?php

namespace Wpextended\Modules\ExternalPermalinks;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;

class Bootstrap extends BaseModule
{
    /**
     * Excluded post types
     *
     * @var array
     */
    private $excluded_post_types = array( 'attachment' , 'elementor_library', 'elementor_library', 'e-landing-page');

    public function __construct()
    {
        parent::__construct('external-permalinks');
    }

    protected function init()
    {
        // Register meta for block editor editing immediately within init
        $this->registerMeta();
        // Also ensure registration is present for REST schema generation
        add_action('rest_api_init', array($this, 'registerMeta'));

        // Classic editor meta box
        add_action('add_meta_boxes', array($this, 'addMetaBox'), 20, 1);
        add_action('save_post', array($this, 'saveMetaBox'), 10, 2);

        // Replace permalink generation with external URL when set
        add_filter('page_link', array($this, 'filterPageLink'), 20, 2);
        add_filter('post_link', array($this, 'filterPostLink'), 20, 2);
        add_filter('post_type_link', array($this, 'filterPostTypeLink'), 20, 4);

        // Frontend redirect to external URL if visiting default permalink
        add_action('template_redirect', array($this, 'maybeRedirectToExternal'), 1);

        // Assets for both editors
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueueAssets'));
    }

    /**
     * Get settings fields
     *
     * @param array $settings
     * @return array
     */
    protected function getSettingsFields()
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('External Permalinks', WP_EXTENDED_TEXT_DOMAIN),
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
                        'description' => __('Select the post types to enable external permalinks for.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices' => $this->getAvailablePostTypes(),
                    )
                )
            ),
        );

        return $settings;
    }

    /**
     * Register `_links_to` post meta for selected post types so it is editable in the block editor.
     *
     * @return void
     */
    public function registerMeta()
    {
        $post_types = $this->getSetting('post_types');

        if (empty($post_types) || !is_array($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            register_post_meta(
                $post_type,
                '_links_to',
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => function ($allowed, $meta_key, $post_id) {
                        return current_user_can('edit_post', $post_id);
                    },
                )
            );
        }
    }

    /**
     * Enqueue assets for block editor or classic editor when on selected post types.
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

        Utils::enqueueStyle(
            'wpextended-external-permalinks',
            $this->getPath('assets/css/style.css')
        );

        Utils::enqueueScript(
            'wpextended-external-permalinks',
            $this->getPath('assets/js/script.js'),
            Utils::isBlockEditor() ? array('wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-data', 'wp-dom-ready') : array()
        );

        wp_localize_script(
            'wpextended-external-permalinks',
            'wpextendedExternalPermalinks',
            array(
                'is_block_editor'   => Utils::isBlockEditor(),
                'post_types'        => $this->getSetting('post_types'),
                'current_post_type' => $current_post_type,
                'current'           => array(
                    'post_id' => get_the_ID(),
                ),
                'i18n'              => $this->getTranslations(),
            )
        );
    }

    /**
     * Add classic editor meta box for the external permalink.
     *
     * @param string $post_type
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
            'wpextended-external-permalinks',
            __('External Permalink', WP_EXTENDED_TEXT_DOMAIN),
            array($this, 'renderMetaBox'),
            $post_type,
            'side',
            'high'
        );
    }

    /**
     * Render the classic editor meta box.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function renderMetaBox($post)
    {
        $translations = $this->getTranslations();
        $value = get_post_meta($post->ID, '_links_to', true);

        wp_nonce_field('wpextended_external_permalink_' . $post->ID, 'wpextended_external_permalink_nonce');

        printf(
            '<div class="wpextended-external-permalink-input">
                <label for="wpextended_external_permalink" class="screen-reader-text">%s</label>
                <input name="wpextended_external_permalink" class="large-text" type="url" value="%s" placeholder="https://" />
                <p class="description">%s</p>
            </div>',
            esc_html($translations['urlLabel']),
            esc_attr($value),
            esc_html($translations['urlHelp'])
        );
    }

    /**
     * Save the classic editor meta box field.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function saveMetaBox($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['wpextended_external_permalink_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = $_POST['wpextended_external_permalink_nonce'];
        if (!wp_verify_nonce($nonce, 'wpextended_external_permalink_' . $post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!$post || !$this->isSelectedPostType($post->post_type)) {
            return;
        }

        $raw = filter_input(INPUT_POST, 'wpextended_external_permalink', FILTER_SANITIZE_URL);
        $value = $raw ? esc_url_raw(trim($raw)) : '';

        if (!empty($value)) {
            update_post_meta($post_id, '_links_to', $value);
        } else {
            delete_post_meta($post_id, '_links_to');
        }
    }

    /**
     * Replace post links with external URL when set (posts and CPTs).
     *
     * @param string   $permalink
     * @param \WP_Post $post
     * @return string
     */
    public function filterPostLink($permalink, $post)
    {
        if (!$post instanceof \WP_Post || !$this->isSelectedPostType($post->post_type)) {
            return $permalink;
        }

        $external = get_post_meta($post->ID, '_links_to', true);
        return !empty($external) ? $external : $permalink;
    }

    /**
     * Replace page links with external URL when set.
     *
     * @param string $permalink
     * @param int    $post_id
     * @return string
     */
    public function filterPageLink($permalink, $post_id)
    {
        $post = get_post($post_id);
        if (!$post || !$this->isSelectedPostType($post->post_type)) {
            return $permalink;
        }

        $external = get_post_meta($post_id, '_links_to', true);
        return !empty($external) ? $external : $permalink;
    }

    /**
     * Replace custom post type links with external URL when set.
     *
     * @param string   $permalink
     * @param \WP_Post $post
     * @param bool     $leavename
     * @param bool     $sample
     * @return string
     */
    public function filterPostTypeLink($permalink, $post, $leavename, $sample)
    {
        if (!$post instanceof \WP_Post || !$this->isSelectedPostType($post->post_type)) {
            return $permalink;
        }

        $external = get_post_meta($post->ID, '_links_to', true);
        return !empty($external) ? $external : $permalink;
    }

    /**
     * Redirect single views to the external URL when set.
     *
     * @return void
     */
    public function maybeRedirectToExternal()
    {
        if (is_admin() || is_preview() || is_customize_preview()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post || !$this->isSelectedPostType($post->post_type)) {
            return;
        }

        // Skip for feeds and API
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (is_feed()) {
            return;
        }

        $external = get_post_meta($post->ID, '_links_to', true);
        if (empty($external)) {
            return;
        }

        // Prevent redirect loops
        $current_url  = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if (rtrim($current_url, '/') === rtrim($external, '/')) {
            return;
        }

        // Choose status code (default 302)
        $status = (int) apply_filters('wpextended/external-permalinks/redirect_status', 302, $post);
        if ($status !== 301 && $status !== 302) {
            $status = 302;
        }

        // Allow redirecting to external hosts intentionally for this feature
        wp_redirect($external, $status);
        return;
    }

    /**
     * Translations for UI strings.
     *
     * @return array
     */
    public function getTranslations()
    {
        $i18n = array(
            'panelTitle' => __('External Permalink', WP_EXTENDED_TEXT_DOMAIN),
            'urlLabel'   => __('Enter URL', WP_EXTENDED_TEXT_DOMAIN),
            'urlHelp'    => __('Keep empty to use the default WordPress permalink. External permalink will overide the default slug.', WP_EXTENDED_TEXT_DOMAIN),
        );

        return apply_filters('wpextended/external-permalinks/translations', $i18n);
    }

    /**
     * Check if the post type is selected in settings.
     *
     * @param string $post_type
     * @return bool
     */
    public function isSelectedPostType($post_type)
    {
        $post_types = $this->getSetting('post_types');
        if (empty($post_types) || !is_array($post_types)) {
            return false;
        }

        return in_array($post_type, $post_types, true);
    }
     /**
     * Get excluded post types
     *
     * @return array
     */
    public function getExcludedPostTypes(): array
    {
        return apply_filters(
            'wpextended/external-permalinks/excluded_post_types',
            $this->excluded_post_types,
            $this
        );
    }

     /**
     * Get post types
     *
     * @return array
     */
    public function getAvailablePostTypes(): array
    {
        $post_types = get_post_types(
            array(
                'public' => true,
                'show_ui' => true,
            ),
            'objects'
        );

        $choices = array();

        foreach ($post_types as $type) {
            if (in_array($type->name, $this->getExcludedPostTypes())) {
                continue;
            }

            if (in_array($type->name, $post_types)) {
                continue;
            }

            $choices[$type->name] = $type->labels->name;
        }

        return $choices;
    }
}
