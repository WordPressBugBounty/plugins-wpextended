<?php

if (! defined('ABSPATH') ) {
    die();
}

class Wp_Extended_Svg_Upload extends Wp_Extended_Export
{
    public function __construct()
    {
        parent::__construct();

        // Add SVG file type support
        add_filter('wp_check_filetype_and_ext', [$this, 'wpext_svg_upload'], 10, 4);

        // Allow SVG in mime types for uploads
        add_filter('upload_mimes', [$this, 'wpext_cc_mime_types']);
    }

    /**
     * Singleton instance initializer
     */
    public static function init()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new Wp_Extended_Svg_Upload(get_called_class(), WP_EXTENDED_VERSION);
        }
        return $instance;
    }

    /**
     * Verify and allow SVG file uploads
     *
     * @param array  $data     File type data.
     * @param string $file     Full path of the file.
     * @param string $filename Name of the file.
     * @param array  $mimes    Allowed mime types.
     * @return array Filtered file data.
     */
    public function wpext_svg_upload($data, $file, $filename, $mimes = [])
    {
        // Ensure $mimes is an array
        $mimes = is_array($mimes) ? $mimes : [];
        // Check file type against mime types
        $filetype = wp_check_filetype($filename, $mimes);
        // Only process SVG files
        if ($filetype['ext'] === 'svg') {
            // Update the file data for SVG
            $data = [
                'ext'             => $filetype['ext'],
                'type'            => $filetype['type'],
                'proper_filename' => $data['proper_filename'] ?? $filename,
            ];
        }

        return $data;
    }

    /**
     * Add SVG mime type support
     *
     * @param array $mimes Existing mime types.
     * @return array Updated mime types.
     */
    public function wpext_cc_mime_types($mimes)
    {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
}

// Initialize the class
Wp_Extended_Svg_Upload::init();
