<?php

namespace Wpextended\Modules\ExportUsers;

use Wpextended\Modules\BaseModule;
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
    private const EXPORT_ACTION = 'wpextended_export_users';

    /**
     * @var ExportService
     */
    private $exportService;

    public function __construct()
    {
        parent::__construct('export-users');
        $this->exportService = new ExportService('export-users');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_filter("user_row_actions", array($this, "addExportLink"), 10, 2);
        add_filter("bulk_actions-users", array($this, 'addBulkAction'), 10, 1);
        add_action('personal_options', array($this, 'profileButton'), 10, 1);

        add_action('admin_init', array($this, 'handleIndividualExport'));
        add_filter('handle_bulk_actions-users', array($this, 'handleBulkExport'), 10, 3);
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
            'title' => __('Export Users', WP_EXTENDED_TEXT_DOMAIN),
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
                    )
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
            'user_login',
            'user_nicename',
            'user_email',
            'user_url',
            'user_registered',
            'display_name'
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
            'ID' => 'User ID',
            'user_login' => 'Username',
            'user_nicename' => 'Nicename',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'nickname' => 'Nickname',
            'user_email' => 'Email Address',
            'user_url' => 'Website URL',
            'description' => 'Description',
            'avatar' => 'Avatar',
            'user_registered' => 'Registration Date',
            'display_name' => 'Display Name',
            'roles' => 'Roles',
            'capabilities' => 'Capabilities',
        );

        return apply_filters('wpextended/export-users/default_fields', $fields);
    }

    /**
     * Validate and sanitize user ID
     *
     * @param mixed $user_id The user ID to validate
     * @return int|false The sanitized user ID or false if invalid
     */
    protected function validateUserId($user_id)
    {
        if (!is_numeric($user_id) || $user_id <= 0) {
            return false;
        }
        return absint($user_id);
    }

    /**
     * Check if user can export data
     *
     * @param int $user_id The ID of the user being exported
     * @return bool Whether the current user can export the specified user's data
     */
    protected function canExportUser($user_id)
    {
        $current_user_id = get_current_user_id();
        $can_export_self = apply_filters('wpextended/export-users/can_export_self', true);

        if ($current_user_id === (int) $user_id) {
            return $can_export_self;
        }

        return current_user_can('list_users');
    }

    /**
     * Get the export link
     *
     * @param string $export_type
     * @param int $user_id
     * @return string
     */
    public function getExportLink($export_type, $user_id)
    {
        if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
            return '';
        }

        $user_id = $this->validateUserId($user_id);
        if (!$user_id) {
            return '';
        }

        $url = add_query_arg(array(
            'action' => self::EXPORT_ACTION,
            'export_type' => $export_type,
            'user_id' => $user_id,
            'wpextended_nonce' => wp_create_nonce(self::NONCE_ACTION),
        ), admin_url('admin.php'));

        return $url;
    }

    /**
     * Add export link to the row actions in users
     *
     * @param array $actions
     * @param object $user
     * @return array
     */
    public function addExportLink($actions, $user)
    {
        if (!is_object($user) || !isset($user->ID) || !$this->canExportUser($user->ID)) {
            return $actions;
        }

        $export_types = $this->getSetting('export_types', array('csv'));
        if (empty($export_types)) {
            return $actions;
        }

        foreach ($export_types as $export_type) {
            if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
                continue;
            }

            $key = sprintf('%s_%s', self::EXPORT_ACTION, sanitize_key($export_type));
            $label = sprintf(__('Download as %s', WP_EXTENDED_TEXT_DOMAIN), strtoupper($export_type));
            $url = $this->getExportLink($export_type, $user->ID);

            if (!empty($url)) {
                $actions[$key] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
            }
        }

        return $actions;
    }

    /**
     * Add bulk action to the users page
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
     * Add the export buttons to the personal options section
     *
     * @param object $user
     * @return void
     */
    public function profileButton($user)
    {
        if (!$this->canExportUser($user->ID)) {
            return;
        }

        $export_types = $this->getSetting('export_types', array('csv'));
        if (empty($export_types)) {
            return;
        }
        ?>
        <tr class="user-export-wrap">
            <th scope="row"><?php _e('Export User Data', WP_EXTENDED_TEXT_DOMAIN); ?></th>
            <td>
                <?php
                foreach ($export_types as $export_type) {
                    $url = $this->getExportLink($export_type, $user->ID);
                    $label = sprintf(__('Export as %s', WP_EXTENDED_TEXT_DOMAIN), strtoupper($export_type));

                    echo sprintf(
                        '<a href="%s" class="button button-secondary" style="margin-right: 8px;">%s</a>',
                        esc_url($url),
                        esc_html($label)
                    );
                }
                ?>
                <p class="description"><?php _e('Export user data in different formats.', WP_EXTENDED_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Handle individual user export
     *
     * @return void
     */
    public function handleIndividualExport()
    {
        if (
            !isset($_GET['wpextended_nonce']) ||
            !isset($_GET['action']) ||
            !isset($_GET['user_id']) ||
            !isset($_GET['export_type']) ||
            $_GET['action'] !== self::EXPORT_ACTION ||
            !wp_verify_nonce($_GET['wpextended_nonce'], self::NONCE_ACTION)
        ) {
            return;
        }

        $user_id = $this->validateUserId($_GET['user_id']);
        if (!$user_id || !$this->canExportUser($user_id)) {
            wp_die(esc_html__('Invalid user ID or insufficient permissions.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_type = sanitize_key($_GET['export_type']);
        if (!in_array($export_type, ExportService::VALID_EXPORT_TYPES)) {
            wp_die(esc_html__('Invalid export type.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_data = $this->prepareExportData(array($user_id), $export_type);
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
     * Handle bulk user export
     *
     * @param string $sendback The redirect URL
     * @param string $doaction The action being taken
     * @param array $ids Array of user IDs
     * @return string|void
     */
    public function handleBulkExport($sendback, $doaction, $ids)
    {
        if (!preg_match('/^' . self::EXPORT_ACTION . '_(csv|json|txt)$/', $doaction, $matches)) {
            return $sendback;
        }

        if (!current_user_can('list_users')) {
            wp_die(esc_html__('You do not have sufficient permissions to export users.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $export_type = sanitize_key($matches[1]);
        $ids = array_map(array($this, 'validateUserId'), $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_die(esc_html__('No valid user IDs provided.', WP_EXTENDED_TEXT_DOMAIN));
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
     * Prepare user data for export based on settings
     *
     * @param array $user_ids Array of user IDs to export
     * @param string $export_type The type of export (csv, json, txt)
     *
     * @return array|WP_Error Array of prepared data or WP_Error on failure
     */
    protected function prepareExportData($user_ids, $export_type)
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
             * @param array  $user_ids   Array of user IDs
             * @param string $export_type The type of export (csv, json, txt)
             */
            $fields = apply_filters('wpextended/export-users/fields', $fields, $user_ids, $export_type);

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
            $headers = apply_filters('wpextended/export-users/headers', $headers, $fields, $export_type);

            // Prepare data rows
            $body = array();

            foreach ($user_ids as $user_id) {
                $user = \get_user_by('ID', $user_id);

                if (!$user) {
                    continue;
                }

                $row = array();

                // Process all fields in a single loop
                foreach ($fields as $field => $label) {
                    $value = '';

                    if (in_array($field, $default_fields)) {
                        // Handle special cases for default fields
                        if ($field === 'roles') {
                            $value = implode(', ', $user->roles);
                        } elseif ($field === 'capabilities') {
                            $value = implode(', ', array_keys($user->allcaps));
                        } elseif ($field === 'avatar') {
                            $value = get_avatar_url($user->ID);
                        } else {
                            $value = $user->$field ?? '';
                        }
                    }

                    /**
                     * Filter the field value before adding to export
                     *
                     * @param mixed  $value      The field value
                     * @param string $field      The field name/key
                     * @param string $label      The field label
                     * @param object $user       The WP_User object
                     * @param string $export_type The type of export (csv, json, txt)
                     */
                    $value = apply_filters('wpextended/export-users/field_value', $value, $field, $label, $user, $export_type);

                    /**
                     * Filter the field value for a specific field
                     *
                     * @param mixed  $value      The field value
                     * @param object $user       The WP_User object
                     * @param string $export_type The type of export (csv, json, txt)
                     */
                    $value = apply_filters("wpextended/export-users/field_{$field}_value", $value, $user, $export_type);

                    $row[] = $value;
                }

                /**
                 * Filter the entire row data before adding to export
                 *
                 * @param array  $row        The row data
                 * @param object $user       The WP_User object
                 * @param array  $fields     Array of field definitions
                 * @param string $export_type The type of export (csv, json, txt)
                 */
                $row = apply_filters('wpextended/export-users/row_data', $row, $user, $fields, $export_type);

                $body[] = $row;
            }

            /**
             * Filter the complete export data before returning
             *
             * @param array  $data       The complete export data
             * @param array  $user_ids   Array of user IDs
             * @param array  $fields     Array of field definitions
             * @param string $export_type The type of export (csv, json, txt)
             */
            $data = array(
                'headers' => $headers,
                'body' => $body
            );
            $data = apply_filters('wpextended/export-users/export_data', $data, $user_ids, $fields, $export_type);

            // Generate filename
            $filename = sprintf(
                '%s-users-export-%s.%s',
                sanitize_file_name(\get_bloginfo('name')),
                date('Y-m-d'),
                $export_type
            );

            /**
             * Filter the export filename
             *
             * @param string $filename    The generated filename
             * @param array  $user_ids   Array of user IDs
             * @param string $export_type The type of export (csv, json, txt)
             */
            $filename = apply_filters('wpextended/export-users/filename', $filename, $user_ids, $export_type);

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
     * Output the export data in the appropriate format
     *
     * @param array $export_data Array containing data, type, and filename
     *
     * @return void
     */
    protected function outputExport($export_data)
    {
        // Validate export data
        if (!is_array($export_data) || !isset($export_data['type']) || !isset($export_data['data'])) {
            wp_die(esc_html__('Invalid export data.', WP_EXTENDED_TEXT_DOMAIN));
        }

        // Validate export type
        if (!in_array($export_data['type'], ExportService::VALID_EXPORT_TYPES)) {
            wp_die(esc_html__('Invalid export type.', WP_EXTENDED_TEXT_DOMAIN));
        }

        header('Content-Type: ' . $this->getContentType($export_data['type']));
        header('Content-Disposition: attachment; filename="' . esc_attr($export_data['filename']) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die(esc_html__('Could not open output stream.', WP_EXTENDED_TEXT_DOMAIN));
        }

        $method = 'output' . strtoupper($export_data['type']);
        if (method_exists($this, $method)) {
            $this->$method($output, $export_data['data'], $export_data);
        } else {
            wp_die(esc_html__('Unsupported export format.', WP_EXTENDED_TEXT_DOMAIN));
        }

        fclose($output);
        exit;
    }

    /**
     * Output data in CSV format
     *
     * @param resource $output File handle
     * @param array $data Array of data rows
     *
     * @return void
     */
    protected function outputCSV($output, $data)
    {
        foreach ($data['body'] as $row) {
            fputcsv($output, $row);
        }
    }

    /**
     * Output data in JSON format
     *
     * @param resource $output File handle
     * @param array $data Array of data rows
     * @param array $export_data Full export data array containing keys
     *
     * @return void
     */
    protected function outputJSON($output, $data, $export_data)
    {
        // Convert data to associative array for JSON using field keys
        $json_data = array(
            'headers' => $data['headers'],
            'body' => $data['body']
        );
        fwrite($output, wp_json_encode($json_data, JSON_PRETTY_PRINT));
    }

    /**
     * Output data in TXT format
     *
     * @param resource $output File handle
     * @param array $data Array of data rows
     * @param array $export_data Full export data array containing keys and labels
     *
     * @return void
     */
    protected function outputTXT($output, $data, $export_data)
    {
        // Validate data
        if (!is_array($data['body']) || empty($data['body'])) {
            return;
        }

        $headers = $data['headers'];

        foreach ($data['body'] as $row) {
            foreach ($headers as $index => $label) {
                // Escape special characters in the value
                $value = isset($row[$index]) ? esc_html($row[$index]) : '';
                fwrite($output, sprintf("%s: %s\n", esc_html($label), $value));
            }

            fwrite($output, "\n");
        }
    }

    /**
     * Get the content type for the export format
     *
     * @param string $type The export type
     * @return string The content type
     */
    protected function getContentType($type)
    {
        $content_type = self::CONTENT_TYPES[$type] ?? 'text/plain';

        /**
         * Filter the content type for the export format
         *
         * @param string $content_type The content type
         * @param string $type         The export type (csv, json, txt)
         */
        return apply_filters('wpextended/export-users/content_type', $content_type, $type);
    }
}
