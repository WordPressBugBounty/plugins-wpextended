<?php
/*
 Export helper class
*/


class Wp_Extended_Export extends Wp_Extended {

    public $formats = array('csv');
    public $action = 'wpext-export';
    public $action_download = 'wpext-export-download';

    public function __construct() {
        parent::__construct();
        
        add_action('admin_enqueue_scripts', array('Wp_Extended_Export', 'admin_scripts'));
    }

    public static function init() {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new Wp_Extended_Export(get_called_class(), WP_EXTENDED_VERSION);
        }

        return $instance;  
    } // init

    public function add_export_action($actions, $object) {
        foreach ($this->formats as $format) {
            $url = add_query_arg(
                array(
                    'action' => $this->action,
                    'id'     => $object->ID,
                    'format' => $format      
                ),
                admin_url('admin-ajax.php')
            );

            // Security: Escape the URL
            $action = sprintf( 
                '<a href="%1$s&wpext_nonce='.wp_create_nonce('wpext-ajax-nonce').'" target="_blank">
                    Download
                    %2$s
                </a>',
                esc_url($url), // Escape the URL for output
                esc_html(__(strtoupper($format), WP_EXTENDED_TEXT_DOMAIN)) // Escape HTML
            );

            $actions['export_' . $format] = apply_filters('wpext-export-action', $action);
        }

        return $actions;
    } // add_export_action

    public function add_bulk_action($actions) {
        foreach ($this->formats as $format) {
            if (!isset($actions['wpext_export_' . $format])) {
                $actions['wpext_export_' . $format] = __('Export to ' . strtoupper($format), WP_EXTENDED_TEXT_DOMAIN);
            }
        }

        return $actions;
    }

    public function export_csv($data = array()) {
        $data = apply_filters('wpext-export-data', $data, 'csv');

        // Make tmp dir
        $dir = $this->_get_files_dir();

        do {
            $fname = uniqid();
            $tmp_path = "{$dir}/{$fname}.csv";
        } while (file_exists($tmp_path));

        $tmp_path = apply_filters('wpext-export-tmp-path', $tmp_path, 'csv');

        // Save CSV 
        $file = fopen($tmp_path, 'w');
        if (!$file) {
            // Error creating file
            return null;
        }

        // Put column names
        $names = array_keys((array) $data[0]);
        fputcsv($file, $names);
        
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        return $tmp_path;
    } // export_csv  

    public function _get_files_dir() {
        if (wpext_has_permissions(plugin_dir_path(__FILE__), '0777') || wpext_has_permissions(plugin_dir_path(__FILE__), '0775')) {     
            $dir = plugin_dir_path(__FILE__);
            $dir .= 'tmp';
            // Create the directory with full permissions
            mkdir($dir, 0777, true);
        } else {
            // Use the WordPress uploads directory as a fallback
            $upload_dir = wp_upload_dir();
            $directory = $upload_dir['basedir'] . '/wpextended/tmp/';

            if (!file_exists($directory)) {
                // Create the directory with standard permissions
                mkdir($directory, 0755, true);
            }
            // Update $dir to point to the new location
            $dir = $directory;
        }
        
        return $dir;
    } // _get_files_dir

    public function download_file_ajax() {
        /* Security Issues fixed */
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to access this file.', WP_EXTENDED_TEXT_DOMAIN));
            wp_die();
        }

        // Security: Verify the nonce for security.
        check_ajax_referer('wpext-ajax-nonce', 'wpext_nonce');

        // Sanitize the filename.
        $filename = isset($_GET['filename']) ? sanitize_file_name($_GET['filename']) : '';

        if (empty($filename)) {
            wp_send_json_error(__('Invalid file request.', WP_EXTENDED_TEXT_DOMAIN));
            wp_die();
        }  
        /* Security Issues fixed end here */
        
        $sent = $this->download_file($filename);
        if ($sent !== true) {
            wp_send_json($sent);
        }

        wp_die();
    } // download_file_ajax

    public function download_file($filename) {
        try {
            $dir = $this->_get_files_dir();
            $file_path = realpath($dir . '/' . $filename);
            
            if (!file_exists($file_path)) {
                throw new \Exception("File not found");
            }

            $content_type = mime_content_type($file_path);
            $size = filesize($file_path);

            // Send response to browser
            if (!headers_sent()) {
                header('Content-type: ' . $content_type);
                header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"'); // Escape the filename
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . $size);      
            }

            readfile($file_path);

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    } // download_file

    public static function admin_scripts() {
        static $done = false;

        if ($done) {
            return;
        }

        $screen = get_current_screen();

        // This script is used for posts / pages / users export
        wp_enqueue_script('wpext-export',
            plugins_url("wpext-export.js", __FILE__), 
            array(),
            filemtime(plugin_dir_path(__FILE__) . "wpext-export.js"),
            true
        );

        if (!empty($_GET['wpext-export'])) {
            // Security: Escape the URL before outputting it fix
            wp_add_inline_script('wpext-export', 'const wpext_download_url = "' . esc_url($_GET['wpext-export']) . '";', 'before');
        }

        $done = true;
    } // admin_scripts  

    public function do_bulk_action($sendback, $doaction, $ids) {
        $format = preg_replace('/^wpext_export_/', '', $doaction);

        if (method_exists($this, "get_items") 
            && method_exists($this, "export_{$format}")) {
                
            $items = $this->get_items($ids);

            $filepath = $this->{"export_{$format}"}($items);

            if ($filepath) {
                $download_url = add_query_arg(
                    array(
                        'action'    => $this->action_download,
                        'filename'  => basename($filepath) // Use basename to avoid path traversal
                    ), 
                    admin_url('admin-ajax.php') 
                );

                // Security: Escape the URL before adding it to the query string
                $sendback = add_query_arg(array('wpext-export' => urlencode(esc_url($download_url))), $sendback);
            }
        }

        return $sendback;
    } // do_bulk_action  

    public function check_formats() {
        $clean = array();

        $formats = apply_filters('wpext-export-formats-before-check', $this->formats);

        foreach ($formats as $format) {
            if (!method_exists($this, "export_{$format}")) {
                continue;
            }

            $clean[] = $format;
        }

        $clean = apply_filters('wpext-export-formats', $clean);

        $this->formats = $clean;
    } // check_formats
}
