<?php

namespace Wpextended\Modules\DisableVideoUploads;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('disable-video-uploads');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        add_filter('upload_mimes', [$this, 'disableVideoUploads']);
    }

    /**
     * Disable video uploads
     *
     * @param array $mimes
     *
     * @return array
     */
    public function disableVideoUploads($mimes)
    {
        // Video mime types
        $video_mimes = array(
            'asf'     => 'video/x-ms-asf',
            'asx'     => 'video/x-ms-asf',
            'wmv'     => 'video/x-ms-wmv',
            'wmx'     => 'video/x-ms-wmx',
            'wm'      => 'video/x-ms-wm',
            'avi'     => 'video/avi',
            'divx'    => 'video/divx',
            'flv'     => 'video/x-flv',
            'mov|qt'  => 'video/quicktime',
            'mpeg|mpg|mpe' => 'video/mpeg',
            'mp4|m4v' => 'video/mp4',
            'ogv'     => 'video/ogg',
            'webm'    => 'video/webm',
            'mkv'     => 'video/x-matroska'
        );

        foreach ($video_mimes as $ext => $mime) {
            if (isset($mimes[$ext])) {
                unset($mimes[$ext]);
            }
        }
        return $mimes;
    }
}
