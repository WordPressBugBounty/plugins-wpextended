<?php
/**
 * Wp Extended Rollback Action.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Theme rollback.
if ( ! empty( $_GET['theme_file'] ) && file_exists( WP_CONTENT_DIR . '/themes/' . $_GET['theme_file'] ) ) {

	// Theme specific vars.
	$title   = $_GET['rollback_name'];
	$nonce   = 'upgrade-theme_' . $_GET['theme_file'];
	$url     = 'admin.php?page=wp-extended-rollback&theme_file=' . $args['theme_file'] . 'action=upgrade-theme';
	$version = $_GET['theme_version'];
	$theme   = $_GET['theme_file'];
	$upgrader = new WP_Extended_Rollback_Theme_Upgrader( new Theme_Upgrader_Skin( compact( 'title', 'nonce', 'url', 'theme', 'version' ) ) );
	$result = $upgrader->wp_extended_rollback_module( $_GET['theme_file'] );
	if ( ! is_wp_error( $result ) && $result ) {
		do_action( 'wpext_theme_success', $_GET['theme_file'], $_GET['theme_version'] );
	} else {
		do_action( 'wpext_theme_failure', $result );
	} die;
} elseif ( ! empty( $_GET['plugin_file'] ) && file_exists( WP_PLUGIN_DIR . '/' . $_GET['plugin_file'] ) ) {

	// This is a plugin rollback.
	$title   = $_GET['rollback_name'];
	$nonce   = 'upgrade-plugin_' . self::set_plugin_slug();
	$url     = 'admin.php?page=wp-extended-rollback&plugin_file=' . esc_url( $args['plugin_file'] ) . 'action=upgrade-plugin';
	$plugin  = self::set_plugin_slug();
	$version = $args['plugin_version'];
	$upgrader = new WP_Extended_Rollback_Plugin_Upgrader( new Plugin_Upgrader_Skin( compact( 'title', 'nonce', 'url', 'plugin', 'version' ) ) );
	$result = $upgrader->wp_extended_rollback_module( plugin_basename($_GET['plugin_file']) );
	if ( ! is_wp_error( $result ) && $result ) {
		do_action( 'wpext_plugin_success', $_GET['plugin_file'], $version );
	} else {
		do_action( 'wpext_plugin_failure', $result );
	} die;
} else {
	_e( 'This rollback request is missing a proper query string. Please contact support.', WP_EXTENDED_TEXT_DOMAIN );
}

