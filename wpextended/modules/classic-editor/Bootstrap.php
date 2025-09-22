<?php

namespace Wpextended\Modules\ClassicEditor;

use Wpextended\Modules\BaseModule;

class Bootstrap extends BaseModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('classic-editor');
    }

    /**
     * Initialize the module
     *
     * @return void
     */
    protected function init()
    {
        $this->maybeDisableGutenberg();
    }

    /**
     * Maybe disable Gutenberg for selected post types
     *
     * @return void
     */
    public function maybeDisableGutenberg()
    {
        $version = get_bloginfo('version');

        if (version_compare($version, '5.0', '<')) {
            add_filter('gutenberg_can_edit_post_type', array($this, 'disableGutenberg'), 10, 2);
        } else {
            add_filter('use_block_editor_for_post_type', array($this, 'disableGutenberg'), 10, 2);
        }
    }

    /**
     * Disable Gutenberg for selected post types
     *
     * @param bool $use_block_editor Whether to use the block editor
     * @param string $post_type The post type to check
     * @return bool
     */
    public function disableGutenberg($use_block_editor, $post_type)
    {
        return apply_filters('wpextended/classic-editor/disable_gutenberg', false, $post_type);
    }
}
