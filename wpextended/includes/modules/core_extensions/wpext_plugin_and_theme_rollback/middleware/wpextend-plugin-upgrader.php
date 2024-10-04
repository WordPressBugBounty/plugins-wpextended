<?php
/**
 * WP Extended Rollback Plugin Upgrader
 *
 * Class that extends the WP Core Plugin_Upgrader found in core to do WP Extended rollbacks.
 *
 * 
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Extended_Rollback_Plugin_Upgrader
 */
class WP_Extended_Rollback_Plugin_Upgrader extends Plugin_Upgrader {

	/**
	 * Plugin rollback.
	 *
	 * @param       $plugin
	 * @param array $args
	 * Plugin: The plugin to perform the rollback for.
	 * Args (optional): Additional arguments for the rollback module.
	 * This function is responsible for executing a WordPress extended rollback module for a plugin.
	 * It accepts the plugin name and optional arguments as parameters.
	 * If the plugin does not exist, an error is returned.
	 * The function initializes the necessary components, sets upgrade strings, and performs checks
	 * It checks if the plugin is currently active and adds filters for the installation process.
	 * @return array|bool|\WP_Error
	 */

	public function wp_extended_rollback_module( $plugin, $args = array() ) {
		$defaults    = array(
			'clear_update_cache' => true,
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		$this->init();
		$this->upgrade_strings();
		// TODO: Add final check to make sure plugin exists
		if (0) {
			$this->skin->before();
			$this->skin->set_result( false );
			$this->skin->error( 'up_to_date' );
			$this->skin->after();

			return false;
		}
		$plugin_slug = $this->skin->plugin;
		$plugin_version = $this->skin->options['version'];
		$download_endpoint = 'https://downloads.wordpress.org/plugin/';
		$url = $download_endpoint . $plugin_slug . '.' . $plugin_version . '.zip';
		$is_plugin_active = is_plugin_active( $plugin );
		add_filter( 'upgrader_pre_install', array( $this, 'active_before' ), 10, 2 );
        add_filter( 'upgrader_post_install', array( $this, 'active_after' ), 10, 2 );
		$this->run( array(
			'package'           => $url,
			'destination'       => WP_PLUGIN_DIR,
			'clear_destination' => true,
			'clear_working'     => true,
			'hook_extra'        => array(
				'plugin' => $plugin,
				'type'   => 'plugin',
				'action' => 'update',
				'bulk'   => 'false',
			),
		) );
		remove_filter( 'upgrader_pre_install', array( $this, 'active_before' ) );
        remove_filter( 'upgrader_post_install', array( $this, 'active_after' ) );
		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}
		if( $is_plugin_active ) {
            activate_plugin( $plugin );
        }
		return true;
	}

}
