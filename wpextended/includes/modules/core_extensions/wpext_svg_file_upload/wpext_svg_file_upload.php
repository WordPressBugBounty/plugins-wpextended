<?php

if (! defined('ABSPATH')) {
    die();
}

class Wp_Extended_Svg_Upload extends Wp_Extended_Export
{
    private $allowed_tags = [
        'svg',
        'g',
        'path',
        'rect',
        'circle',
        'ellipse',
        'line',
        'polyline',
        'polygon',
        'text',
        'tspan',
        'tref',
        'textPath',
        'use',
        'image',
        'defs',
        'desc',
        'title',
        'symbol',
        'clipPath',
        'mask',
        'pattern',
        'filter',
        'linearGradient',
        'radialGradient',
        'stop',
        'marker',
        'metadata',
        'view',
        'switch',
        'style'
    ];

    public function __construct()
    {
        parent::__construct();

        // Add SVG file type support
        add_filter('wp_check_filetype_and_ext', [$this, 'wpext_svg_upload'], 10, 4);

        // Allow SVG in mime types for uploads
        add_filter('upload_mimes', [$this, 'wpext_cc_mime_types']);

        // Add pre-upload validation
        add_filter('wp_handle_upload_prefilter', [$this, 'wpext_validate_svg_upload']);

        // Sanitize SVG content before saving
        add_filter('wp_handle_upload', [$this, 'wpext_sanitize_svg_content'], 10, 2);
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
            // Check user capabilities
            if (!current_user_can('upload_files')) {
                return ['error' => __('You do not have permission to upload SVG files.', 'wpextended')];
            }

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

    /**
     * Validate SVG upload before processing
     *
     * @param array $file Uploaded file data
     * @return array Modified file data
     */
    public function wpext_validate_svg_upload($file)
    {
        if ($file['type'] === 'image/svg+xml') {
            // Check file size (max 1MB)
            if ($file['size'] > 1024 * 1024) {
                $file['error'] = __('SVG file size must be less than 1MB.', 'wpextended');
                return $file;
            }

            // Check if file is actually an SVG
            $content = file_get_contents($file['tmp_name']);
            if (strpos($content, '<svg') === false) {
                $file['error'] = __('Invalid SVG file.', 'wpextended');
                return $file;
            }
        }

        return $file;
    }

    /**
     * Sanitize SVG content
     *
     * @param array $file Uploaded file data
     * @param string $action Action being performed
     * @return array Modified file data
     */
    public function wpext_sanitize_svg_content($file, $action)
    {
        if ($file['type'] === 'image/svg+xml') {
            $content = file_get_contents($file['file']);

            // Remove potentially dangerous elements and attributes
            $content = $this->sanitize_svg_content($content);

            // Write sanitized content back to file
            file_put_contents($file['file'], $content);
        }

        return $file;
    }

    /**
     * Sanitize SVG content by removing dangerous elements and attributes
     *
     * @param string $content SVG content
     * @return string Sanitized SVG content
     */
    private function sanitize_svg_content($content)
    {
        // Remove script tags and their contents
        $content = preg_replace('/<script[\s\S]*?<\/script>/i', '', $content);

        // Remove event handlers
        $content = preg_replace('/on\w+=(["\'])(?:(?=(\\\\?))\\2.)*?\\1/i', '', $content);

        // Remove style attributes with url()
        $content = preg_replace('/style=["\'].*?url\(.*?\).*?["\']/i', '', $content);

        // Remove potentially dangerous tags
        $allowed_tags_regex = implode('|', $this->allowed_tags);
        $content = preg_replace('/<(\/?)(?!' . $allowed_tags_regex . ')(\w+)[^>]*>/is', '', $content);

        // Remove foreignObject elements
        $content = preg_replace('/<foreignObject[\s\S]*?<\/foreignObject>/i', '', $content);

        // Remove external references
        $content = preg_replace('/xlink:href=["\'](?!data:image\/svg\+xml)/i', '', $content);

        // Remove data URIs except for images
        $content = preg_replace('/data:image\/svg\+xml[^,]*,[^"]*/i', '', $content);

        return $content;
    }
}

// Initialize the class
Wp_Extended_Svg_Upload::init();
