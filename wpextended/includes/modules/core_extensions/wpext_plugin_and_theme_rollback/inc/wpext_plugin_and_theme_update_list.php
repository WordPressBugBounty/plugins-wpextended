<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

include ABSPATH . WPINC . '/version.php'; // include an unmodified $wp_version
  
  // Bounce out if improperly called.
  if ( defined( 'WP_INSTALLING' ) || ! is_admin() ) {
    return false;
  }
  $expiration       = 12 * HOUR_IN_SECONDS;
  $installed_themes = wp_get_themes();
  $last_update = get_site_transient( 'update_themes' );
  if ( ! is_object( $last_update ) ) {
    set_site_transient( 'wpext_rollback_themes', time(), $expiration );
  }
  $themes = $checked = $request = array();
  // Put slug of current theme into request.
  $request['active'] = get_option( 'stylesheet' );
  foreach ( $installed_themes as $theme ) {
    $checked[ $theme->get_stylesheet() ] = $theme->get( 'Version' );
    $themes[ $theme->get_stylesheet() ] = array(
      'Name'       => $theme->get( 'Name' ),
      'Title'      => $theme->get( 'Name' ),
      'Version'    => '0.0.0.0.0.0',
      'Author'     => $theme->get( 'Author' ),
      'Author URI' => $theme->get( 'AuthorURI' ),
      'Template'   => $theme->get_template(),
      'Stylesheet' => $theme->get_stylesheet(),
    );
  }
  
  $request['themes'] = $themes;
  // echo "<pre>"; print_r($_REQUEST['page'] == 'wp-extended-rollback');
  $timeout = 3 + (int) ( count( $themes ) / 10 );
  global $wp_version;
  $options = array(
    'timeout'    => $timeout,
    'body'       => array(
      'themes' => json_encode( $request ),
    ),
    'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ),
  );
  $http_url = self::WPEXT_THEME_UPDATE_CHECK;
  if ( $ssl = wp_http_supports( array( 'ssl' ) )) {
    $url = set_url_scheme( $http_url, 'https' );
  }
  $raw_response = wp_remote_post( $url, $options );
  if ( $ssl && is_wp_error( $raw_response ) ) {
    trigger_error( __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/">support forums</a>.', WP_EXTENDED_TEXT_DOMAIN ) . ' ' . __( '(WordPress could not establish a secure connection to WordPress.org. Please contact your server administrator.)', WP_EXTENDED_TEXT_DOMAIN ), headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE );
    $raw_response = wp_remote_post( $http_url, $options );
  }
  set_site_transient( 'wpext_rollback_themes', time(), $expiration );
  if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) ) {
    return false;
  }
  $new_update               = new stdClass();
  $new_update->last_checked = time();
  $new_update->checked      = $checked;
  $response = json_decode( wp_remote_retrieve_body( $raw_response ), true );
  if ( is_array( $response ) && isset( $response['themes'] ) ) {
    $new_update->response = $response['themes'];
  }
  set_site_transient( 'wpext_rollback_themes', $new_update );

  return true;
  die;