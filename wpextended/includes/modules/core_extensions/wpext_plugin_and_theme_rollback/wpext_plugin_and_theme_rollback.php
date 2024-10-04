<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

class Wp_Extended_Plugin_And_Theme_Rollback extends Wp_Extended {
   
	/**
	 * Plugins API url.
	 *
	 * @var string
	 */
	const WPEXT_PLUGIN_API = 'https://api.wordpress.org/plugins';

	/**
	 * Themes repo url.
	 *
	 * @var string
	 */
	const WPEXT_THEME_API = 'https://themes.svn.wordpress.org';

	/**
	 * 
	 * Plugin File Url const
	 * 
	 * 
	 * */
	const WPEXT_PLUGIN_FILE_URL = 'admin.php?page=wp-extended-rollback&type=plugin&plugin_file=';

	/**
	 * 
	 * Theme File Url const
	 * 
	 * 
	 * */
	const WPEXT_THEME_FILE_URL = 'admin.php?page=wp-extended-rollback&type=theme&theme_file=';

	/**
	 * 
	 * Theme info const
	 * 
	 * 
	 * */
	const WPEXT_THEME_INFO = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information';

	/**
	 * 
	 * Theme update check const
	 * 
	 * 
	 * */
	const WPEXT_THEME_UPDATE_CHECK = 'http://api.wordpress.org/themes/update-check/1.1/';

	/**
	 * Versions.
	 *
	 * @var array
	 */
	var $versions = array();

	/**
	 * Current version.
	 *
	 * @var string
	 */
	public $current_version;

	public function __construct() {
	  parent::__construct();
	  	ini_set('max_execution_time', '300');
		add_action( 'admin_enqueue_scripts', array( $this, 'wpext_plugin_theme_rollback_script' ), 110 );
		add_filter( 'plugin_action_links', array($this, 'wpext_plugin_action_links' ), 1, 4 );
		add_filter( 'theme_action_links', array($this, 'wpext_theme_action_links' ), 20, 4 );
		add_action( 'set_site_transient_update_themes', array( $this, 'wpext_theme_updates_list' ) );
		add_filter( 'wp_prepare_themes_for_js', array( $this, 'wpext_prepare_themes_js' ) );
		add_action( 'wp_ajax_is_wordpress_theme', array( $this, 'wpext_if_wp_theme' ) );
		add_action( 'admin_menu', array( $this, 'wpext_rollback_admin_menu' ), 20 );
	}
	/**
	 * init Check if $instance is null, meaning it hasn't been initialized yet.
	 * Create a new instance of the Wp_Extended_Plugin_And_Theme_Rollback class
	 * using the class that called the method and WP_EXTENDED_VERSION as arguments
	 */
	public static function init(){
	  static $instance = null;
	  if ( is_null( $instance ) ) {
	      $instance = new Wp_Extended_Plugin_And_Theme_Rollback( get_called_class(), WP_EXTENDED_VERSION );
	       self::setup_plugin_vars();
	  }
	  return $instance;  
	} // init
	
	/**
	 * Include the 'wpext_plugin_and_theme_rollback_base.php' file from the 'inc' directory of the plugin 
	 * 
	 */
	public static function wpext_plugin_theme_rollback_script(){
	  require_once plugin_dir_path( __FILE__ ). '/inc/wpext_plugin_and_theme_rollback_base.php';
	}

	/**
	 * 
	 * Plugin action rollback link.
	 * @var string
	 * 
	 * */

	public function wpext_plugin_action_links($actions, $plugin_file, $plugin_data, $context){

		// Customize filter.
		$plugin_data = apply_filters( 'wpext_plugin_data', $plugin_data );

		// In case plugin is missing package data do not output Wpext Rollback option.
		if ( ! isset( $plugin_data['package'] ) || strpos( $plugin_data['package'], 'https://downloads.wordpress.org' ) === false ) {
			return $actions;
		}

		// Check if version available.
		if ( ! isset( $plugin_data['Version'] ) ) {
			return $actions;
		}

		// Base wpext_rollback_url
		$wpext_plugin_rollback_url = self::WPEXT_PLUGIN_FILE_URL . $plugin_file;
		$wpext_plugin_rollback_url = add_query_arg(
			apply_filters(
				'wpext_plugin_query_args', array(
					'current_version' => urlencode( $plugin_data['Version'] ),
					'rollback_name'   => urlencode( $plugin_data['Name'] ),
					'plugin_slug'     => urlencode( $plugin_data['slug'] ),
					'_wpnonce'        => wp_create_nonce( 'wpext_rollback_nonce' ),
				)
			), $wpext_plugin_rollback_url
		); 
		// Final Output
		$actions['wp-extended-rollback'] = apply_filters( 'wpext_plugin_markup', '<a href="' . esc_url( $wpext_plugin_rollback_url ) . '">' . __( 'Rollback', WP_EXTENDED_TEXT_DOMAIN ) . '</a>' );
		return apply_filters( 'wpext_plugin_action_links', $actions );
	}

	/**
	 * 
	 * Theme action rollback link 
	 * 
	 * @var string
	 * 
	 * */

	public function wpext_theme_action_links( $actions, $theme, $context ){

		$rollback_themes = get_site_transient( 'wpext_rollback_themes' );
		if ( ! is_object( $rollback_themes ) ) {
			self::wpext_theme_updates_list();
			$rollback_themes = get_site_transient( 'wpext_rollback_themes' );
		}

		$theme_slug = isset( $theme->template ) ? $theme->template : '';

		// Only WP.org themes.
		if ( empty( $theme_slug ) || ! array_key_exists( $theme_slug, $rollback_themes->response ) ) {
			return $actions;
		}

		$theme_file = isset( $rollback_themes->response[ $theme_slug ]['package'] ) ? $rollback_themes->response[ $theme_slug ]['package'] : '';

		// Base rollback URL.
		$wpext_theme_rollback_url = self::WPEXT_THEME_FILE_URL . $theme_file;

		// Add in the current version for later reference.
		if ( ! $theme->get( 'Version' ) ) {
			return $actions;
		}
		$wpext_theme_rollback_url = add_query_arg(
			apply_filters(
				'wpext_theme_query_args', array(
					'theme_file'      => urlencode( $theme_slug ),
					'current_version' => urlencode( $theme->get( 'Version' ) ),
					'rollback_name'   => urlencode( $theme->get( 'Name' ) ),
					'_wpnonce'        => wp_create_nonce( 'wpext_rollback_nonce' ),
				)
			), $wpext_theme_rollback_url
		);

		// Final Output
		$actions['wp-extended-rollback'] = apply_filters( 'wpext_theme_markup', '<a href="' . esc_url( $wpext_theme_rollback_url ) . '">' . __( 'Rollback', WP_EXTENDED_TEXT_DOMAIN ) . '</a>' );
		return apply_filters( 'wpext_theme_action_links', $actions );

	}

	public function wpext_theme_updates_list() {
		require_once plugin_dir_path( __FILE__ ). '/inc/wpext_plugin_and_theme_update_list.php';

	}

	public function wpext_prepare_themes_js( $prepared_themes ) {
		$themes    = array();
		$rollbacks = array();
		$wp_themes = get_site_transient( 'wpext_rollback_themes' );

		// Double-check our transient is present.
		if ( empty( $wp_themes ) || ! is_object( $wp_themes ) ) {
			self::wpext_theme_updates_list();
			$wp_themes = get_site_transient( 'wpext_rollback_themes' );
		}

		// Set $rollback response variable for loop ahead.
		if ( is_object( $wp_themes ) ) {
			$rollbacks = $wp_themes->response;
		}

		// Loop through themes and provide a 'hasRollback' boolean key for JS.
		foreach ( $prepared_themes as $key => $value ) {
			$themes[ $key ]                = $prepared_themes[ $key ];
			$themes[ $key ]['hasRollback'] = isset( $rollbacks[ $key ] );
		}

		return $themes;
	}

	public function wpext_if_wp_theme(){

		$url    = add_query_arg( 'request[slug]', $_POST['theme'], self::WPEXT_THEME_INFO );
		$wp_api = wp_remote_get( $url );

		if ( ! is_wp_error( $wp_api ) ) {
			if ( isset( $wp_api['body'] ) && strlen( $wp_api['body'] ) > 0 && $wp_api['body'] !== 'false' ) {

			echo 'wp'; } else { echo 'non-wp'; } } else { echo 'error'; }

		// Die is required to terminate immediately and return a proper response.
		wp_die();
	}

	/**
	 * 
	 * Check if the type is 'plugin' and if the 'page' query parameter is 'wp-extended-rollback' 
	 * Construct the URL for the plugin API info
	 * Return the body of the response
	 */

	public static function wpext_svn_tags( $type, $slug ) {
		$response = '';
		$url = '';
		if(isset($_GET['page']) && $_GET['page'] == 'wp-extended-rollback' && isset($_GET['type'])){ 
			if ('plugin' === $_GET['type'] && !empty($_GET['page']) && $_GET['type'] != "theme") {
				$url = self::WPEXT_PLUGIN_API . '/info/1.0/' . self::set_plugin_slug() . '.json';
				$response = wp_remote_get( $url );
			} elseif ( 'theme' === $_GET['type'] ) {
				  $url = self::WPEXT_THEME_API . '/' . $slug;
				  $response = wp_remote_get( $url );
			}
			if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {   // Return when error.
				return null;
			}
			// Nope: Return that bad boy
		 return wp_remote_retrieve_body( $response );
		}
	}

	 /**
	 * Format:
	 * - If $html is empty, return false
	 * - $html can be successfully decoded as JSON and is different from the original $html
	 */

	public static function set_svn_versions_data( $html ) {
		global $versions; 
		if ( ! $html ) {
			return false;
		}
		if ( ( $json = json_decode( $html ) ) && ( $html != $json ) ) {
			$versions = array_keys( (array) $json->versions );
		} else {
			$obj = new DOMDocument();
			$obj->loadHTML( $html );
			$versions = array();
			$items = $obj->getElementsByTagName( 'a' );

			foreach ( $items as $item ) {
				$href = str_replace( '/', '', $item->getAttribute( 'href' ) ); // Remove trailing slash

				if ( strpos( $href, 'http' ) === false && '..' !== $href ) {
					$versions[] = $href;
				}
			}
		}
		$versions = array_reverse( $versions );
		return $versions;
	}

	/**
	 * Setup Variables
	 *
	 * @access     private
	 * @description:
	 */
	private static function setup_plugin_vars() {
		self::set_plugin_slug();

		$wpext_tags = self::wpext_svn_tags( 'plugin', self::set_plugin_slug() );
		self::set_svn_versions_data( $wpext_tags );
	}
	/**
	 *  global $versions
	 *  versions_select - getting the plugins theme version.
	 * 
	 */
	public function versions_select($type){
		global $versions; 
		$this->versions = $versions;
		if ( empty( $this->versions ) ) {
		$versions_html = '<div class="wpext-error"><p>' . sprintf( __( 'It appears there are no version to select. This is likely due to the %s author not using tags for their versions and only committing new releases to the repository trunk.', WP_EXTENDED_TEXT_DOMAIN ), $type ) . '</p></div>';
			return apply_filters( 'versions_failure_html', $versions_html );
		}
		$versions_html = '<select class="wpext-version-list w-100 form-select" id="wpext_selected_version">';
		usort( $this->versions, 'version_compare' );
		$this->versions = array_reverse($this->versions );
		
		// Loop through versions and output in a radio list.
		
		foreach ( $this->versions as $version ) {
			$versions_html .= '<option class="wpext-version-li" value ="'.esc_attr( $version ).'" name="' . $type . '_version">'. $version;
			$versions_html .= '</option>';
		}
		if(isset($type) && !empty($type)){
		$versions_html .= '</select>';
		$versions_html .= '<input type="hidden" value="'.esc_attr( $version ).'" name="' . $type . '_version" id="selected_ver">';
		return apply_filters( 'versions_select_html', $versions_html ); }

	}

	/**
	 * Set Plugin Slug
	 *
	 * @return array|bool
	 */
	public static function set_plugin_slug() {

		if ( ! isset( $_GET['plugin_file'] ) ) {
			return false;
		}
		
		if ( isset( $_GET['current_version'] ) ) {
			$curr_version = explode( ' ', $_GET['current_version'] );
			apply_filters( 'wpext_current_version', $curr_version[0] );
			$current_version = $curr_version[0];

		}
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_file = WP_PLUGIN_DIR . '/' . $_GET['plugin_file'];

		if ( ! file_exists( $plugin_file ) ) {
			wp_die( 'Plugin you\'re referencing does not exist.' );
		}

		// the plugin slug is the base directory name without the path to the main file
		$plugin_slug = explode( '/', plugin_basename( $plugin_file ) );
		$plugin_file = apply_filters( 'wpext_plugin_file', $plugin_file );
		$plugin_slug = apply_filters( 'wpext_plugin_slug', $plugin_slug[0] );

		return $plugin_slug;

	}

	/**
	 * Admin Menu
	 *
	 * Adds a 'hidden' menu item that is activated when the user elects to wp extended rollback
	 */
	public function wpext_rollback_admin_menu(){

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'wp-extended-rollback' ) {
			$current_url = $_SERVER['REQUEST_URI'];
			if(isset($_GET['type']) && $_GET['type'] == "plugin" || isset($_GET['plugin_file'])){
					$current_url =  home_url()."/wp-admin/plugins.php";
				}else if(isset($_GET['type']) && $_GET['type'] == "theme" || isset($_GET['theme_file'])){
					$current_url =  home_url()."/wp-admin/themes.php";
				}else{
					$current_url =  "#";
				}  
				$menutxt = __('<a href='.$current_url.'>WP Rollback</a>', WP_EXTENDED_TEXT_DOMAIN);
			  add_submenu_page( 
				'wp-extended',
				__('rollback', WP_EXTENDED_TEXT_DOMAIN ),__($menutxt, WP_EXTENDED_TEXT_DOMAIN ),'update_plugins','wp-extended-rollback',array($this, 'wpext_html')
			  ); 
		}
 	}

 /**
 * Html layout for plugin theme version
 */

	public function wpext_html() {

		// Permissions check
		if ( ! current_user_can( 'update_plugins' ) ) {
			 wp_die( __( 'You do not have sufficient permissions to perform rollbacks for this site.', WP_EXTENDED_TEXT_DOMAIN ) );
		}

		// Get the necessary class
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$defaults = apply_filters(
			'wpext_rollback_html_args', array(
				'page'           => 'wp-extended-rollback',
				'plugin_file'    => '',
				'action'         => '',
				'plugin_version' => '',
				'plugin'         => '',
			)
		);

		$args = wp_parse_args( $_GET, $defaults );

		if ( ! empty( $args['plugin_version'] ) ) {
			
			/**
			 * Include the require filr from middleware directory 
			 * Wp extended Plugin: rolling back.
			 */

			check_admin_referer( 'wpext_rollback_nonce' );
			require_once plugin_dir_path( __FILE__ ). '/middleware/wpextend-plugin-upgrader.php';
			require_once plugin_dir_path( __FILE__ ). '/middleware/wpextend-rollback-action.php';

		} elseif ( ! empty( $args['theme_version'] ) ) {

			// Wp extended Theme: rolling back.
			check_admin_referer( 'wpext_rollback_nonce' );
			require_once plugin_dir_path( __FILE__ ). '/middleware/wpextend-theme-upgrader.php';
			require_once plugin_dir_path( __FILE__ ). '/middleware/wpextend-rollback-action.php';
 
		} else {
			// This is the menu.
			 check_admin_referer( 'wpext_rollback_nonce' );
			 require_once plugin_dir_path( __FILE__ ). '/middleware/wpextend-menu.php';
		}
	}

}

Wp_Extended_Plugin_And_Theme_Rollback::init(); 