<?php

namespace Wpextended\Modules\SvgUpload;

use Wpextended\Modules\BaseModule;
use Wpextended\Services\SvgSanitizer;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('svg-upload');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('wp_check_filetype_and_ext', [$this, 'validateSvgUpload'], 10, 4);
        add_filter('upload_mimes', [$this, 'addSvgMimeType']);

        // Validate and sanitize SVG files before upload
        add_filter('wp_handle_upload_prefilter', [$this, 'validateSvgFile']);
        add_filter('wp_handle_upload', [$this, 'sanitizeSvgContent'], 10, 2);
    }

    /**
     * Add SVG mime type to allowed upload types
     *
     * @param array $mimes
     * @return array
     */
    public function addSvgMimeType($mimes): array
    {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
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
    public function validateSvgUpload($data, $file, $filename, $mimes = []): array
    {
        // Ensure $mimes is an array
        $mimes = is_array($mimes) ? $mimes : [];

        // Check file type against mime types
        $filetype = wp_check_filetype($filename, $mimes);

        // Only process SVG files
        if ($filetype['ext'] !== 'svg' && $filetype['ext'] !== 'svgz') {
            return $data;
        }

        // Check user capabilities
        if (!current_user_can('upload_files')) {
            return ['error' => __('You do not have permission to upload SVG files.', 'wpextended')];
        }

        // Update the file data for SVG
        return [
            'ext'             => $filetype['ext'],
            'type'            => $filetype['type'],
            'proper_filename' => $data['proper_filename'] ?? $filename,
        ];
    }

    /**
     * Validate SVG upload before processing
     *
     * @param array $file Uploaded file data
     * @return array Modified file data
     */
    public function validateSvgFile($file): array
    {
        if ($file['type'] !== 'image/svg+xml') {
            return $file;
        }

        // Check file size (max 1MB)
        if ($file['size'] > 1024 * 1024) {
            $file['error'] = __('SVG file size must be less than 1MB.', 'wpextended');
            return $file;
        }

        // Check if file is actually an SVG
        $content = file_get_contents($file['tmp_name']);
        if (!SvgSanitizer::validate($content)) {
            $file['error'] = __('Invalid SVG file.', 'wpextended');
            return $file;
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
    public function sanitizeSvgContent($file, $action): array
    {
        if ($file['type'] !== 'image/svg+xml') {
            return $file;
        }

        $content = file_get_contents($file['file']);

        // Use the SVG sanitizer service
        $sanitized_content = SvgSanitizer::sanitize($content);

        // Write sanitized content back to file
        file_put_contents($file['file'], $sanitized_content);

        return $file;
    }
}
