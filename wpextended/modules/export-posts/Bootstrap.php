<?php

namespace Wpextended\Modules\ExportPosts;

use Wpextended\Modules\BaseModule;
use Wpextended\Includes\Utils;
use Wpextended\Includes\Services\Export\ExportService;

class Bootstrap extends BaseModule
{
    /**
     * Valid export types
     */
    private const VALID_EXPORT_TYPES = array('csv', 'json', 'txt');

    /**
     * Content types for export formats
     */
    private const CONTENT_TYPES = array(
        'csv' => 'text/csv',
        'json' => 'application/json',
        'txt' => 'text/plain'
    );

    /**
     * Nonce action name
     */
    private const NONCE_ACTION = 'wpextended-nonce';

    /**
     * Export action name
     */
    private const EXPORT_ACTION = 'wpextended_export_posts';

    /**
     * @var ExportService
     */
    private $exportService;

    public function __construct()
    {
        parent::__construct('export-posts');
        $this->exportService = new ExportService('export-posts');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_init', array($this, 'handleIndividualExport'));

        // Register bulk actions for all post types
        $this->registerBulkActions();
        $this->registerPostRowActions();

        // Add meta box and assets
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueueAssets'));
        add_filter('add_meta_boxes', array($this, 'addMetaBox'));
    }

    /**
     * Get post types that can be exported
     *
     * @param string $return Return format: 'objects' or 'names'
     * @return array The available post types
     */
    private function getPostTypes($return = 'objects')
    {
        $exclude_post_types = apply_filters(
            'wpextended/export-posts/exclude_post_types',
            array('attachment', 'elementor_library', 'e-landing-page')
        );

        // Get exportable types
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

        return $return === 'objects' ? $post_types : array_keys($post_types);
    }

    /**
     * Check if current screen is an exportable post type
     *
     * @return bool Whether current screen is an exportable post type
     */
    private function isExportableScreen()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        $post_types = $this->getPostTypes('names');
        return in_array($screen->post_type, $post_types, true);
    }

    /**
     * Enqueue assets for both block editor and classic editor
     *
     * @return void
     */
    public function enqueueAssets()
    {
        // Early return if not on an exportable screen
        if (!$this->isExportableScreen()) {
            return;
        }

        // Load notification library for admin notices
        if (!Utils::isBlockEditor()) {
            Utils::enqueueNotify();
        }

        // Enqueue shared CSS and JS
        Utils::enqueueStyle(
            'wpextended-export-posts',
            $this->getPath('assets/css/style.css')
        );

        Utils::enqueueScript(
            'wpextended-export-posts',
            $this->getPath('assets/js/script.js'),
            Utils::isBlockEditor() ? array() : array('wpext-notify')
        );

        // Localize script data for both editors
        wp_localize_script(
            'wpextended-export-posts',
            'wpextendedExportPosts',
            array(
                'admin_url' => admin_url('admin.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'is_block_editor' => Utils::isBlockEditor(),
                'export_types' => Utils::getSetting('export-posts', 'export_types', array('csv')),
                'current' => array(
                    'post_type' => get_post_type(),
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

        $post_types = $this->getPostTypes('names');

        if (!in_array($post_type, $post_types, true)) {
            return;
        }

        add_meta_box(
            'wpextended-export-posts',
            __('Export Post', WP_EXTENDED_TEXT_DOMAIN),
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

        if (!in_array($current_post_type, array_keys($post_types), true)) {
            return;
        }

        $export_types = $this->getSetting('export_types', array('csv'));
        $select_options = array();

        $select_options[] = array(
            'value' => '',
            'label' => __('Select Type', WP_EXTENDED_TEXT_DOMAIN),
        );

        foreach ($export_types as $type) {
            $select_options[] = array(
                'value' => $type,
                'label' => strtoupper($type),
            );
        }

        $select_field = sprintf(
            '<select name="wpextended_export_type" class="wpextended-export-posts__select">
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
            '<div class="wpextended-export-posts-container">
                %s
                <button class="button wpextended-export-posts__button">%s</button>
            </div>',
            $select_field,
            esc_html__('Export', WP_EXTENDED_TEXT_DOMAIN)
        );
    }

    /**
     * Register bulk actions for all post types
     *
     * @return void
     */
    protected function registerBulkActions()
    {
        $post_types = $this->getPostTypes('objects');

        if (!$post_types) {
            return;
        }

        foreach ($post_types as $post_type) {
            add_filter("bulk_actions-edit-{$post_type->name}", array($this, 'addBulkAction'), 10, 1);
            add_filter("handle_bulk_actions-edit-{$post_type->name}", array($this, 'handleBulkExport'), 10, 3);
        }
    }

    /**
     * Register post row actions
     *
     * @return void
     */
    protected function registerPostRowActions()
    {
        $post_types = $this->getPostTypes('objects');

        if (!$post_types) {
            return;
        }

        foreach ($post_types as $post_type) {
            add_filter(sprintf("%s_row_actions", $post_type->name), array($this, "addExportLink"), 10, 2);
        }
    }

    /**
     * Get the settings fields
     *
     * @return array
     */
    protected function getSettingsFields()
    {
        $settings = array();

        $settings['tabs'][] = array(
            'id' => 'settings',
            'title' => __('Export Posts', WP_EXTENDED_TEXT_DOMAIN),
        );

        $settings['sections'] = array(
            array(
                'tab_id' => 'settings',
                'section_id' => 'settings',
                'section_title' => __('Settings', WP_EXTENDED_TEXT_DOMAIN),
                'section_order' => 10,
                'fields' => array(
                    array(
                        'id' => 'export_types',
                        'type' => 'checkboxes',
                        'title' => __('Export Types', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select available export types.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices' => array(
                            'csv' => __('CSV', WP_EXTENDED_TEXT_DOMAIN),
                            'json' => __('JSON', WP_EXTENDED_TEXT_DOMAIN),
                            'txt' => __('TXT', WP_EXTENDED_TEXT_DOMAIN),
                        ),
                        'default' => array('csv'),
                        'layout' => 'inline',
                    ),
                    array(
                        'id' => 'default_fields',
                        'type' => 'select',
                        'title' => __('Default Fields', WP_EXTENDED_TEXT_DOMAIN),
                        'description' => __('Select fields to export.', WP_EXTENDED_TEXT_DOMAIN),
                        'choices' => $this->getDefaultFields(),
                        'multiple' => true,
                        'select2' => array(
                            'placeholder' => __('Select default fields...', WP_EXTENDED_TEXT_DOMAIN),
                            'allowClear' => true,
                            'closeOnSelect' => false,
                            'tags' => true,
                            'tokenSeparators' => array(',', ' '),
                        ),
                        'default' => $this->setDefaultFields(),
                    ),
                )
            )
        );

        return $settings;
    }

    /**
     * Set default fields for export
     *
     * @return array
     */
    public function setDefaultFields()
    {
        return array(
            'ID',
            'post_title',
            'post_name',
            'post_content',
            'post_excerpt',
            'post_status',
            'post_type',
            'post_date',
            'post_author',
            'post_thumbnail',
        );
    }

    /**
     * Get the default fields
     *
     * @return array
     */
    public function getDefaultFields()
    {
        $fields = array(
            'ID' => 'Post ID',
            'post_title' => 'Title',
            'post_name' => 'Slug',
            'post_content' => 'Content',
            'post_excerpt' => 'Excerpt',
            'post_status' => 'Status',
            'post_type' => 'Post Type',
            'post_date' => 'Date',
            'post_modified' => 'Modified Date',
            'post_author' => 'Author',
            'post_thumbnail' => 'Featured Image',
            'comment_count' => 'Comment Count',
            'menu_order' => 'Menu Order',
            'guid' => 'GUID',
            'post_parent' => 'Parent Post',
            'post_password' => 'Password',
            'post_date_gmt' => 'Date (GMT)',
            'post_modified_gmt' => 'Modified Date (GMT)',
            'filter' => 'Filter',
            'ancestors' => 'Ancestors',
            'post_mime_type' => 'MIME Type',
            'comment_status' => 'Comment Status',
            'ping_status' => 'Ping Status',
            'to_ping' => 'To Ping',
            'pinged' => 'Pinged',
            'post_content_filtered' => 'Filtered Content',
            'post_category' => 'Categories',
            'tags_input' => 'Tags',
            'tax_input' => 'Taxonomies',
            'page_template' => 'Page Template',
            'post_category' => 'Categories',
            'tags_input' => 'Tags',
            'tax_input' => 'Taxonomies',
            'page_template' => 'Page Template'
        );

        return apply_filters('wpextended/export-posts/default_fields', $fields);
    }

    /**
     * Validate and sanitize post ID
     *
     * @param mixed $post_id The post ID to validate
     * @return int|false The sanitized post ID or false if invalid
     */
    protected function validatePostId($post_id)
    {
        if (!is_numeric($post_id) || $post_id <= 0) {
            return false;
        }
        return absint($post_id);
    }

    /**
     * Check if user can export post data
     *
     * @param int $post_id The ID of the post being exported
     * @return bool Whether the current user can export the specified post's data
     */
    protected function canExportPost($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }

    /**
     * Get the export link
     *
     * @param string $export_type
     * @param int $post_id
     * @return string
     */
    public function getExportLink($export_type, $post_id)
    {
        if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
            return '';
        }

        $post_id = $this->validatePostId($post_id);
        if (!$post_id) {
            return '';
        }

        $url = add_query_arg(array(
            'action' => self::EXPORT_ACTION,
            'export_type' => $export_type,
            'post_id' => $post_id,
            'wpextended_nonce' => wp_create_nonce(self::NONCE_ACTION),
        ), admin_url('admin.php'));

        return $url;
    }

    /**
     * Add export link to the row actions in posts
     *
     * @param array $actions
     * @param object $post
     * @return array
     */
    public function addExportLink($actions, $post)
    {
        if (!is_object($post) || !isset($post->ID) || !$this->canExportPost($post->ID)) {
            return $actions;
        }

        $export_types = Utils::getSetting('export-posts', 'export_types', array('csv'));
        if (empty($export_types)) {
            return $actions;
        }

        foreach ($export_types as $export_type) {
            if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
                continue;
            }

            $key = sprintf('%s_%s', self::EXPORT_ACTION, sanitize_key($export_type));
            $label = sprintf(__('Download as %s', WP_EXTENDED_TEXT_DOMAIN), strtoupper($export_type));
            $url = $this->getExportLink($export_type, $post->ID);

            if (!empty($url)) {
                $actions[$key] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
            }
        }

        return $actions;
    }

    /**
     * Add bulk action to the posts page
     *
     * @param array $actions
     * @return array
     */
    public function addBulkAction($actions)
    {
        $export_types = $this->getSetting('export_types', array('csv'));
        if (empty($export_types)) {
            return $actions;
        }

        foreach ($export_types as $export_type) {
            if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
                continue;
            }

            $key = sprintf('%s_%s', self::EXPORT_ACTION, $export_type);
            $label = sprintf(__('Export to %s', WP_EXTENDED_TEXT_DOMAIN), strtoupper($export_type));
            $actions[$key] = $label;
        }

        return $actions;
    }

    /**
     * Handle individual post export
     *
     * @return void
     */
    public function handleIndividualExport()
    {
        if (
            !isset($_GET['wpextended_nonce']) ||
            !isset($_GET['action']) ||
            !isset($_GET['post_id']) ||
            !isset($_GET['export_type']) ||
            $_GET['action'] !== self::EXPORT_ACTION ||
            !wp_verify_nonce($_GET['wpextended_nonce'], self::NONCE_ACTION)
        ) {
            return;
        }

        $post_id = $this->validatePostId($_GET['post_id']);
        if (!$post_id || !$this->canExportPost($post_id)) {
            wp_die(esc_html__('Invalid post ID or insufficient permissions.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_type = sanitize_key($_GET['export_type']);
        if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
            wp_die(esc_html__('Invalid export type.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_data = $this->prepareExportData(array($post_id), $export_type);
        if (is_wp_error($export_data)) {
            wp_die(esc_html($export_data->get_error_message()));
        }

        $this->exportService->export(
            $export_data['data'],
            $export_type,
            array(
                'filename' => $export_data['filename'],
                'keys' => $export_data['keys']
            )
        );
    }

    /**
     * Handle bulk post export
     *
     * @param string $sendback The redirect URL
     * @param string $doaction The action being taken
     * @param array $ids Array of post IDs
     * @return string|void
     */
    public function handleBulkExport($sendback, $doaction, $ids)
    {
        if (!preg_match('/^' . self::EXPORT_ACTION . '_(csv|json|txt)$/', $doaction, $matches)) {
            return $sendback;
        }

        // Get the current post type from the screen
        $screen = get_current_screen();
        if (!$screen || !$screen->post_type) {
            return $sendback;
        }

        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have sufficient permissions to export posts.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_type = sanitize_key($matches[1]);
        $ids = array_map(array($this, 'validatePostId'), $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_die(esc_html__('No valid post IDs provided.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_data = $this->prepareExportData($ids, $export_type);
        if (is_wp_error($export_data)) {
            wp_die(esc_html($export_data->get_error_message()));
        }

        $this->exportService->export(
            $export_data['data'],
            $export_type,
            array(
                'filename' => $export_data['filename'],
                'keys' => $export_data['keys']
            )
        );
    }

    /**
     * Prepare post data for export based on settings
     *
     * @param array $post_ids Array of post IDs to export
     * @param string $export_type The type of export (csv, json, txt)
     *
     * @return array|WP_Error Array of prepared data or WP_Error on failure
     */
    protected function prepareExportData($post_ids, $export_type)
    {
        try {
            // Get selected fields from settings
            $default_fields = $this->getSetting('default_fields', $this->setDefaultFields());

            if (empty($default_fields)) {
                return new \WP_Error('no_fields', __('No fields selected for export.', WP_EXTENDED_TEXT_DOMAIN));
            }

            // Prepare field definitions
            $fields = array();

            // Add default fields with their labels
            foreach ($default_fields as $field) {
                $fields[$field] = $this->getDefaultFields()[$field] ?? $field;
            }

            /**
             * Filter the fields and their labels before processing
             *
             * @param array  $fields     Array of field definitions (key => label)
             * @param array  $post_ids   Array of post IDs
             * @param string $export_type The type of export (csv, json, txt)
             */
            $fields = apply_filters('wpextended/export-posts/fields', $fields, $post_ids, $export_type);

            // Prepare headers (labels for CSV/TXT, keys for JSON)
            $headers = array_values($fields);
            $keys = array_keys($fields);

            /**
             * Filter the headers before processing
             *
             * @param array  $headers    Array of header labels
             * @param array  $fields     Array of field definitions
             * @param string $export_type The type of export (csv, json, txt)
             */
            $headers = apply_filters('wpextended/export-posts/headers', $headers, $fields, $export_type);

            // Prepare data rows
            $body = array();

            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);

                if (!$post) {
                    continue;
                }

                $row = array();

                // Process all fields in a single loop
                foreach ($fields as $field => $label) {
                    $value = '';

                    // Handle special cases for default fields
                    if ($field === 'post_author') {
                        $author = get_user_by('ID', $post->post_author);
                        $value = $author ? $author->display_name : '';
                    } elseif ($field === 'post_category') {
                        $categories = get_the_category($post_id);
                        $value = implode(', ', wp_list_pluck($categories, 'name'));
                    } elseif ($field === 'tags_input') {
                        $tags = get_the_tags($post_id);
                        $value = $tags ? implode(', ', wp_list_pluck($tags, 'name')) : '';
                    } elseif ($field === 'post_thumbnail') {
                        $value = get_the_post_thumbnail_url($post_id, 'full');
                    } else {
                        $value = $post->$field ?? '';
                    }

                    /**
                     * Filter the field value before adding to export
                     *
                     * @param mixed  $value      The field value
                     * @param string $field      The field name/key
                     * @param string $label      The field label
                     * @param object $post       The WP_Post object
                     * @param string $export_type The type of export (csv, json, txt)
                     */
                    $value = apply_filters('wpextended/export-posts/field_value', $value, $field, $label, $post, $export_type);

                    /**
                     * Filter the field value for a specific field
                     *
                     * @param mixed  $value      The field value
                     * @param object $post       The WP_Post object
                     * @param string $export_type The type of export (csv, json, txt)
                     */
                    $value = apply_filters("wpextended/export-posts/field_{$field}_value", $value, $post, $export_type);

                    $row[] = $value;
                }

                /**
                 * Filter the entire row data before adding to export
                 *
                 * @param array  $row        The row data
                 * @param object $post       The WP_Post object
                 * @param array  $fields     Array of field definitions
                 * @param string $export_type The type of export (csv, json, txt)
                 */
                $row = apply_filters('wpextended/export-posts/row_data', $row, $post, $fields, $export_type);

                $body[] = $row;
            }

            /**
             * Filter the complete export data before returning
             *
             * @param array  $data       The complete export data
             * @param array  $post_ids   Array of post IDs
             * @param array  $fields     Array of field definitions
             * @param string $export_type The type of export (csv, json, txt)
             */
            $data = array(
                'headers' => $headers,
                'body' => $body
            );
            $data = apply_filters('wpextended/export-posts/export_data', $data, $post_ids, $fields, $export_type);

            // Generate filename
            $filename = sprintf(
                '%s-posts-export-%s.%s',
                sanitize_file_name(\get_bloginfo('name')),
                date('Y-m-d'),
                $export_type
            );

            /**
             * Filter the export filename
             *
             * @param string $filename    The generated filename
             * @param array  $post_ids   Array of post IDs
             * @param string $export_type The type of export (csv, json, txt)
             */
            $filename = apply_filters('wpextended/export-posts/filename', $filename, $post_ids, $export_type);

            return array(
                'data' => $data,
                'type' => $export_type,
                'filename' => $filename,
                'keys' => $keys // Add keys for JSON export
            );
        } catch (\Exception $e) {
            return new \WP_Error('export_error', $e->getMessage());
        }
    }

    /**
     * Get translations for the export posts
     *
     * @return array Translated strings
     */
    public function getTranslations()
    {
        $i18n = array(
            'exportLabel' => __('Export', WP_EXTENDED_TEXT_DOMAIN),
            'selectExportType' => __('Select Type', WP_EXTENDED_TEXT_DOMAIN),
            'exporting' => __('Exporting...', WP_EXTENDED_TEXT_DOMAIN),
            'successMessage' => __('Export completed successfully', WP_EXTENDED_TEXT_DOMAIN),
            'errorMessage' => __('Failed to export post', WP_EXTENDED_TEXT_DOMAIN),
            'ariaLabel' => __('Export post', WP_EXTENDED_TEXT_DOMAIN),
            'exportButton' => __('Export', WP_EXTENDED_TEXT_DOMAIN),
            'confirmExport' => __('Are you sure you want to export this post?', WP_EXTENDED_TEXT_DOMAIN),
        );

        return apply_filters('wpextended/export-posts/translations', $i18n);
    }
}
