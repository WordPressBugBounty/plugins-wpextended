<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Wp_Extended_Convert_Post_Type extends Wp_Extended {

	public function __construct() {
	parent::__construct();
	add_action( 'admin_head', array( $this, 'wpext_admin_head') );
  // Override
	add_action( 'wp_ajax_wpext_post_type_switcher', array( $this, 'wpext_post_type_switcher' ) );
	add_filter( 'wp_insert_attachment_data',  array( $this, 'wpext_override_type' ), 10, 2 );
	add_filter( 'wp_insert_post_data',        array( $this, 'wpext_override_type' ), 10, 2 );
	add_filter( 'add_meta_boxes',        array( $this, 'wpext_global_notice_meta_box' ));
  // Pass object into an action
	do_action( 'wpext_post_type_switcher', $this );
	}
  public static function init(){
    static $instance = null;
		if ( is_null( $instance ) ) {
      $instance = new Wp_Extended_Convert_Post_Type( get_called_class(), WP_EXTENDED_VERSION );
    }
		return $instance;  
  } // init
   
  public function wpext_global_notice_meta_box() {
		$exclude = array( 'attachment' , 'elementor_library', 'elementor_library', 'e-landing-page');
    $screens = get_post_types( ['public'   => true ], 'objects' );
		foreach ( $screens as $posttype => $screen ) {
      if(!in_array($screen->name, $exclude)) {
        add_meta_box(
       'wpext_ctp_switcher',  __( 'Post Type Switcher', WP_EXTENDED_TEXT_DOMAIN),                  
        array($this, 'wpext_global_notice_meta_box_callback'), $screen->name, 'side',                   
       'high');
        	 
       }
    } 
}
public function wpext_global_notice_meta_box_callback() {
	// Post types
	$post_types = $this->get_post_types();
	$post_type  = get_post_type();
	$cpt_object = get_post_type_object( $post_type );
  $exclude = array( 'attachment' , 'elementor_library', 'elementor_library', 'e-landing-page' );
	// wpext if object does not exist or produces an error
	if ( empty( $cpt_object ) || is_wp_error( $cpt_object ) ) {
		return;
	}
	if ( ! in_array( $cpt_object, $post_types, true ) ) {
		$post_types[ $post_type ] = $cpt_object;
	}?>
	<div class="wpext-pub-section wpext-pub-section-last post-type-switcher">
		<label for="wpext_post_type"><?php esc_html_e( 'Post Type:', WP_EXTENDED_TEXT_DOMAIN ); ?></label>
		<label id="post-type-display"><?php echo esc_html( $cpt_object->labels->singular_name ); ?></label>

		<?php if ( current_user_can( $cpt_object->cap->publish_posts ) ) : ?>
			<label id="post-type-display">
			<a href="#" id="edit-post-type-switcher" class="hide-if-no-js"><?php esc_html_e( 'Edit', WP_EXTENDED_TEXT_DOMAIN ); ?></a> </label>
			<div id="post-type-select">
				<select name="wpext_post_type" id="wpext_post_type">
					<option value="#"><?php esc_html_e( ' Select Post Type ', WP_EXTENDED_TEXT_DOMAIN ); ?></option>
					<?php

					foreach ( $post_types as $_post_type => $cpt ) :
						if(!in_array($cpt->name, $exclude) && $cpt->name != $post_type) {

							if ( ! current_user_can( $cpt->cap->publish_posts ) ) :
								continue;
							endif;

						?><option value="<?php echo esc_attr( $cpt->name ); ?>" <?php selected( $post_type, $_post_type ); ?>>
							<?php echo esc_html( $cpt->labels->singular_name ); ?></option><?php
					}
					endforeach;

				?></select>
				<input type="hidden" id="wpext_post_id" name="wpext_post_id" value="<?php echo isset($_GET['post']) ? intval($_GET['post']) : ''; ?>">
				<a href="#" id="save-post-type-switcher" class="button-primary  hide-if-no-js button"><?php esc_html_e( 'OK', WP_EXTENDED_TEXT_DOMAIN ); ?></a>
				<a href="#" id="cancel-post-type-switcher" class="hide-if-no-js"><?php esc_html_e( 'Cancel', WP_EXTENDED_TEXT_DOMAIN ); ?></a>
			</div><?php

			wp_nonce_field( 'post-type-selector', 'wpext-nonce-select' );

		endif;

		?></div>

	<?php
	}
	
  /**
	 * Handles an admin-ajax request to change post types.
	 *
	 * Note that these use $_GET values specifically, to avoid collisions with
	 * upstream requests.
	 *
	 * @since 1.2.0
	 */
	public function wpext_post_type_switcher() {
		// wpext if missing data
		if (
			   empty( $_POST['wpext_post_type'] )
			|| empty( $_POST['wpext_post_id'] )
		) {
			return wp_die( esc_html__( 'Missing data.', WP_EXTENDED_TEXT_DOMAIN ) );
		}
		// Post type information
		$post_id          = absint( $_POST['wpext_post_id'] );
		$post_type        = sanitize_key( $_POST['wpext_post_type'] );
		$post_type_object = get_post_type_object( $post_type );

		// wpext if user isn't capable or nonce fails
		if ( ! current_user_can( $post_type_object->cap->publish_posts ) ) {
			return wp_die( esc_html__( 'Sorry, you cannot do this.', WP_EXTENDED_TEXT_DOMAIN ) );
		}

		// Update the post type
		set_post_type( $post_id, $post_type );
		get_edit_post_link( $post_id);
	  exit;
	}
	/**
	 * Override post_type in wp_insert_post()
	 *
	 * - Not during autosave
	 * - Check nonce
	 * - Check user capabilities
	 * - Check $_POST input name
	 * - Check if revision or current post-type
	 * - Check new post-type exists
	 * - Check that user can publish posts of new type
	 *
	 * @since 1.2.0
	 *
	 * @param  array  $data
	 * @param  array  $postarr
	 *
	 * @return Maybe modified $data
	 */

	public function wpext_override_type( $data = array(), $postarr = array() ) {
		// wpext if form field is missing
		if ( empty( $_REQUEST['wpext_post_type'] ) || empty( $_REQUEST['wpext-nonce-select'] ) ) {
			return $data;
		}

		// wpext if no specific post ID is being saved
		if ( empty( $postarr['post_ID'] ) ) {
			return $data;
		}
		$post_id          = absint( $postarr['post_ID'] );
		$post_type        = sanitize_key( $_REQUEST['wpext_post_type'] );
		$post_type_object = get_post_type_object( $post_type );

		// wpext if empty post type
		if ( empty( $post_id ) || empty( $post_type ) || empty( $post_type_object ) ) {
			return $data;
		}

		// wpext if no change
		if ( $post_type === $data['post_type'] ) {
			return $data;
		}
		if ( $post_id !== $postarr['ID'] ) {
			return $data;
		}

		// wpext if user cannot 'edit_post' on the current post ID
		if ( ! current_user_can( 'edit_post', $postarr['ID'] ) ) {
			return $data;
		}

		// wpext if user cannot 'publish_posts' on the new type
		if ( ! current_user_can( $post_type_object->cap->publish_posts ) ) {
			return $data;
		}

		// wpext if nonce is invalid
		if ( ! wp_verify_nonce( $_REQUEST['wpext-nonce-select'], 'post-type-selector' ) ) {
			return $data;
		}

		// wpext if autosave
		if ( wp_is_post_autosave( $postarr['ID'] ) ) {
			return $data;
		}

		// wpext if revision
		if ( wp_is_post_revision( $postarr['ID'] ) ) {
			return $data;
		}

		// wpext if it's a revision
		if ( in_array( $postarr['post_type'], array( $post_type, 'revision' ), true ) ) {
			return $data;
		}

		// Update post type
		$data['post_type'] = $post_type;

		// Return modified post data
		return $data;
	}
	/**
	 * Adds needed JS and CSS to admin header
	 *
	 * @since 1.2.0
	 *
	 * @return If on post-new.php
	 */
	public function wpext_admin_head() {
	?>
		<script type="text/javascript">
			jQuery( document ).ready( function($) {
				jQuery( '.wpext-pub-section.curtime.wpext-pub-section-last' ).removeClass( 'wpext-pub-section-last' );
				jQuery( '#edit-post-type-switcher' ).on( 'click', function(e) {
					jQuery( this ).hide();
					jQuery( '#post-type-select' ).slideDown();
					e.preventDefault();
				});
				jQuery( '#save-post-type-switcher' ).on( 'click', function(e) {
					jQuery( '#post-type-select' ).slideUp();
					jQuery( '#edit-post-type-switcher' ).show();
					jQuery( '#post-type-display' ).text( jQuery( '#wpext_post_type :selected' ).text() );
					// alert(jQuery( '#wpext_post_type').val());
					var wpext_post_type = jQuery( '#wpext_post_type').val();
					var wpext_post_id = jQuery( '#wpext_post_id').val();
					/*Ajax Start*/
					jQuery.ajax({
						url:wpext_extended_obj.ajax_url,
						type: 'post',
						data: {
						'action': 'wpext_post_type_switcher',
						'wpext_post_type': wpext_post_type,
						'wpext_post_id': wpext_post_id,
						 'nonce' : wpext_extended_obj.ajax_nonce 
					},
						success: function (response) {
							// window.location.replace(response);
							if(response != null){
							location.reload();
							}
						}
						});
					e.preventDefault();
				});
				jQuery( '#cancel-post-type-switcher' ).on( 'click', function(e) {
					jQuery( '#post-type-select' ).slideUp();
					jQuery( '#edit-post-type-switcher' ).show();
					e.preventDefault();
				});

			});
		</script>
		<style type="text/css">
			div#wpext_ctp_switcher .inside {
				padding-right: 10px;
			}
			#wpbody-content .inline-edit-row .inline-edit-col-right .alignleft + .alignleft {
				float: right;
			}
			#post-type-select {
				line-height: 2.5em;
				margin-top: 3px;
				display: none;
			}
			#post-type-select select#wpext_post_type {
				margin-right: 2px;
			}
			#post-type-select a#save-post-type-switcher {
				vertical-align: middle;
				margin-right: 2px;
			}
			#post-type-display {
				font-weight: bold;
			}
			.wp-list-table .column-post_type {
				width: 10%;
			}
		</style>

	<?php
	}
	/**
	 * Get switchable post type objects, based on post-type arguments.
	 *
	 * @since 1.2.0
	 *
	 * @param string $output objects|names
	 * @return array
	 */
	private function get_post_types( $output = 'objects' ) {
		// Get switchable types
		$types = get_post_types( $this->get_post_type_args(), $output );
		// Unset attachment types, since support seems to be broken
		if ( isset( $types['attachment'] ) ) {
			unset( $types['attachment'] );
		}
	  // Return switchable types
		return $types;
	}

	/**
	 * Returns the array of arguments used to narrow down the switchable post
	 * types from the globally registered $wp_post_types array.
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */

	private function get_post_type_args() {
		return (array) apply_filters( 'wpext_post_type_filter', array(
			'public'  => true,
			'show_ui' => true
		) );
	}

}
Wp_Extended_Convert_Post_Type::init();