<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}
$screen = get_current_screen();
if($screen->id == 'themes'){
 wp_enqueue_script( 'wpext_rollback_themes_script', plugin_dir_url( __FILE__ ) . 'js/wpext_plugin_and_theme_rollback.js', array( 'jquery' ), false, true );
   
 /**
  * Set Paramiters for wpext_vars
  * Localize script with variables for wpext_rollback_themes_script.
  * Parameters:
  * Script handle: 'wpext_rollback_themes_script'
  * Object name: 'wpext_vars'
  * Variables: 'ajaxurl', 'nonce', 'text_rollback_label', 'text_not_rollbackable', 'text_loading_rollback'
  */
  wp_localize_script(
    'wpext_rollback_themes_script', 'wpext_vars', array(
      'ajaxurl'               => admin_url(),
      'nonce'                 => wp_create_nonce( 'wpext_rollback_nonce' ),
      'text_rollback_label'   => __( 'Rollback', WP_EXTENDED_TEXT_DOMAIN ),
      'text_not_rollbackable' => __( 'No Rollback Available: This is a non-WordPress.org theme.', WP_EXTENDED_TEXT_DOMAIN ),
      'text_loading_rollback' => __( 'Loading...', WP_EXTENDED_TEXT_DOMAIN ),
    )
  );
}

// CSS
wp_register_style( 'wpext_rollback_css', plugin_dir_url( __FILE__ ) . 'css/wpext-rollback.css', array(), WP_EXTENDED_VERSION );
wp_enqueue_style( 'wpext_rollback_css' );

wp_register_script( 'wpext_rollback_script', plugin_dir_url( __FILE__ ) . 'js/wpext-rollback.js', array( 'jquery' ), WP_EXTENDED_VERSION );
wp_enqueue_script( 'wpext_rollback_script' );

wp_enqueue_script( 'updates' );

/**
 * Localize script with variables for wpext_rollback_script.
 * Parameters:
 * Script handle: 'wpext_rollback_script'
 * Object name: 'wpext_vars'
 * Variables: 'ajaxurl', 'text_no_changelog_found', 'version_missing'
 * 
 */
wp_localize_script(
  'wpext_rollback_script', 'wpext_vars', array(
    'ajaxurl'                 => admin_url(),
    'text_no_changelog_found' => isset( $_GET['plugin_slug'] ) ? sprintf( __( 'Sorry, we couldn\'t find a changelog entry found for this version. Try checking the <a href="%s" target="_blank">developer log</a> on WP.org.', WP_EXTENDED_TEXT_DOMAIN ), 'https://wordpress.org/plugins/' . $_GET['plugin_slug'] . '/#developers' ) : '',
    'version_missing'         => __( 'Please select a version number to perform a rollback.', WP_EXTENDED_TEXT_DOMAIN ),
  )
);