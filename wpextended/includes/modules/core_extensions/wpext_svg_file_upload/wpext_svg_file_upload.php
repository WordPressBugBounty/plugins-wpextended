<?php

class Wp_Extended_Svg_Upload extends Wp_Extended_Export {

    public function __construct() {
        parent::__construct();
        // Add SVG icons
        add_filter('wp_check_filetype_and_ext', array($this, 'wpext_svg_upload'), 10, 4);
        add_filter('upload_mimes', array($this, 'wpext_cc_mime_types'));
    }

    public static function init() {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new Wp_Extended_Svg_Upload(get_called_class(), WP_EXTENDED_VERSION);
        }
        return $instance;  
    }

    /**
     * Checks the file type for SVG uploads.
     *
     * @param array $data Existing file data.
     * @param string $file The full path of the file.
     * @param string $filename The name of the file.
     * @param array $mimes The accepted MIME types.
     * @return array Filtered file data.
     */
    public function wpext_svg_upload(array $data, string $file, string $filename, array $mimes): array {
        $filetype = wp_check_filetype($filename, $mimes);

        // Ensure proper filename is set
        $data['proper_filename'] = $data['proper_filename'] ?? $filename;

        return [
            'ext'             => $filetype['ext'],
            'type'            => $filetype['type'],
            'proper_filename' => $data['proper_filename']
        ];
    }

    /**
     * Adds SVG MIME types to the upload MIME types.
     *
     * @param array $mimes Existing MIME types.
     * @return array Updated MIME types.
     */
    public function wpext_cc_mime_types(array $mimes): array {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

}
Wp_Extended_Svg_Upload::init();