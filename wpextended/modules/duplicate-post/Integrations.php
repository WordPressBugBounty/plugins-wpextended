<?php

namespace Wpextended\Modules\DuplicatePost;

/**
 * Handles integrations with other plugins for the Duplicate Post module
 *
 * @package Wpextended\Modules\DuplicatePost
 */
class Integrations
{
    /**
     * Available integrations configuration
     *
     * @var array
     */
    protected $integrations = array(
        'elementor' => array(
            'active_check' => 'ELEMENTOR_VERSION',
            'handler' => 'handleElementorIntegration'
        )
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        // Allow plugins to modify integrations
        $this->integrations = apply_filters('wpextended/duplicate-post/integrations', $this->integrations);
    }

    /**
     * Handle all plugin integrations for a duplicated post
     *
     * @param int $postId The ID of the duplicated post
     * @return void
     */
    public function handlePluginIntegrations($postId)
    {
        if (!is_numeric($postId) || $postId <= 0) {
            return;
        }

        foreach ($this->integrations as $integration => $config) {
            if (!$this->isIntegrationActive($config)) {
                continue;
            }

            $this->handleIntegration($config, $postId);
        }
    }

    /**
     * Check if an integration is active
     *
     * @param array $config Integration configuration
     * @return boolean
     */
    protected function isIntegrationActive($config)
    {
        if (empty($config['active_check'])) {
            return false;
        }

        // Check if constant exists
        if (defined($config['active_check'])) {
            return true;
        }

        // Check if function exists
        if (function_exists($config['active_check'])) {
            return true;
        }

        // Check if class exists
        if (class_exists($config['active_check'])) {
            return true;
        }

        return false;
    }

    /**
     * Handle a specific integration
     *
     * @param array $config Integration configuration
     * @param int $postId The ID of the duplicated post
     * @return void
     */
    protected function handleIntegration($config, $postId)
    {
        if (empty($config['handler']) || !method_exists($this, $config['handler'])) {
            return;
        }

        call_user_func(array($this, $config['handler']), $postId);
    }

    /**
     * Handle Elementor integration
     *
     * @param int $postId The ID of the duplicated post
     * @return void
     */
    protected function handleElementorIntegration($postId)
    {
        // Clear Elementor CSS cache
        delete_post_meta($postId, '_elementor_css');
        delete_post_meta($postId, '_elementor_page_assets');

        // Regenerate Elementor data
        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            $elementor->files_manager->clear_cache();
        }
    }
}
