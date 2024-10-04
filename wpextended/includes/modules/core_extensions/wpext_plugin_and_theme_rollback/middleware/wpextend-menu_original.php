<?php
/**
 * Wp Extended Rollback Menu
 *
 * Provides the rollback screen view with releases.
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

if ( ( ! isset( $_GET['type'] ) && ! isset( $_GET['theme'] ) ) || ( ! isset( $_GET['type'] ) && ! isset( $_GET['plugin_file'] ) ) ) {
	wp_die( __( 'WP Extended Rollback is missing necessary parameters to continue. Please contact support.', WP_EXTENDED_TEXT_DOMAIN ) );
}
$theme_rollback  = $_GET['type'] == 'theme' ? true : false;
$plugin_rollback = $_GET['type'] == 'plugin' ? true : false;
$plugins         = get_plugins();
?>
<div class="container-fluid wpe_brand_header">
  <div class="container p-4">
      <h4 class="text-white ps-3 m-0"><?php _e('WP Extended - Plugin & Theme Rollback', WP_EXTENDED_TEXT_DOMAIN ); ?></h4>
  </div>  
</div>
<div class="container wp-extend-rollback-manager">
	<div class="row rollback-table">
     <div class="col-sm-8 gx-5 mb-3">
		   	<div class="container bg-white text-dark p-0 border rounded-0 shadow-sm">
			   	<div class="hstack gap-3 p-3" style="background: #F9FAFB;">
		            <div class="p-2 fw-semibold px-0">
		            	<div class="col"><span class="p-2 fw-semibold px-0">
			            	<?php if(isset($_GET['type'])){ _e(ucfirst($_GET['type']), WP_EXTENDED_TEXT_DOMAIN); } ?>
			            	<?php _e( ' Rollback', WP_EXTENDED_TEXT_DOMAIN); ?></span>
		            	</div>
		            </div>
	          	</div>
	          	<div class="container bg-white text-dark p-3 gap-3">
					 <label class="label label-default"><?php echo apply_filters( 'wpext_rollback_description', sprintf( __( 'Please select which %1$s version you would like to rollback to from the releases listed below. You currently have version %2$s installed of %3$s.', WP_EXTENDED_TEXT_DOMAIN ), '<span class="type">' . ( $theme_rollback == true ? __( 'theme', WP_EXTENDED_TEXT_DOMAIN ) : __( 'plugin', WP_EXTENDED_TEXT_DOMAIN ) ) . '</span>', '<span class="current-version">' . esc_html( $args['current_version'] ) . '</span>', '<span class="rollback-name">' . esc_html( $args['rollback_name'] ) . '</span>' ) ); ?>
					</label> 
			    </div>
			<div class="wpext-changelog"></div>
			<?php
			   // Plugin rollbacks in first conditional:
			   $newobj = new Wp_Extended_Plugin_And_Theme_Rollback();
				if ( isset( $args['plugin_file'] ) && in_array( $args['plugin_file'], array_keys( $plugins ) ) ) {
					$versions = $newobj->versions_select( 'plugin' );
				} elseif ( $theme_rollback == true && isset( $_GET['theme_file'] ) ) {
					// Theme rollback: set up our theme vars
					$svn_tags = $newobj->wpext_svn_tags( 'theme', $_GET['theme_file'] );
					$newobj->set_svn_versions_data( $svn_tags );
					$this->current_version = $_GET['current_version'];
					$versions = $newobj->versions_select( 'theme' );
            } else {
					// Fallback check
					wp_die( __( 'Oh no! We\'re missing required rollback query strings. Please contact support so we can check this bug out and squash it!', WP_EXTENDED_TEXT_DOMAIN ) ); }
			   ?>
   <div class="container bg-white text-dark p-3 gap-3 mb-3">
      <form name="check_for_rollbacks" class="rollback-form" action="<?php echo admin_url( '/admin.php' ); ?>">
         <?php
            // Output Versions
            if ( ! empty( $versions ) ) { ?>
         <div class="wpext-versions-wrap px-0">
            <?php
               do_action( 'wpext_pre_versions' );
               echo apply_filters( 'wpext_versions_output', $versions );
               do_action( 'wpext_post_version' ); ?>
         </div>
         <?php } ?>
         <?php do_action( 'wpext_hidden_fields' ); ?>
         <input type="hidden" name="page" value="wp-extended-rollback">
         <?php
         // Important: We need the appropriate file to perform a rollback
         if ( $plugin_rollback == true ) {
         ?>
         <input type="hidden" name="plugin_file" value="<?php echo esc_attr( $args['plugin_file'] ); ?>">
         <input type="hidden" name="plugin_slug" value="<?php echo esc_attr( $args['plugin_slug'] ); ?>">
         <?php } else { ?>
         <input type="hidden" name="theme_file" value="<?php echo esc_attr( $_GET['theme_file'] ); ?>">
         <?php } ?>
         <input type="hidden" name="rollback_name" value="<?php echo esc_attr( $args['rollback_name'] ); ?>">
         <input type="hidden" name="installed_version" value="<?php echo esc_attr( $args['current_version'] ); ?>">
         <?php wp_nonce_field( 'wpext_rollback_nonce' ); ?>
         <!-- bootstrap model popup -->	
         <div class="wpext-submit-wrap">
            <button type="button" class="button-primary wpextended_popup " data-coreui-toggle="modal" data-coreui-target="#exampleModal" 
               id="rollback_btn"><?php _e('Rollback', WP_EXTENDED_TEXT_DOMAIN); ?></button>
            <input type="button" value="<?php _e( 'Back', WP_EXTENDED_TEXT_DOMAIN ); ?>" class="button-secondary" onclick="location.href='<?php 
               if(isset($_GET['type']) && $_GET['type'] == 'plugin'){
               	echo home_url().'/wp-admin/plugins.php';
               }else{
               	echo home_url().'/wp-admin/themes.php';
               } ?>' " />
         </div>
         <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
               <div class="modal-content">
                  <div class="modal-body p-3 px-0 pt-0">
                     <!-- Rollback Popup layout -->
                     <div id="wpext-modal-confirm" class="">
                        <div class="container-fluid wpe_brand_header">
                           <div class="container px-0 p-4">
                              <h4 class="text-white ps-0 m-0"><?php if(isset($_GET['type'])){ _e(ucfirst($_GET['type']), WP_EXTENDED_TEXT_DOMAIN); } ?>
                                 <?php _e( ' Rollback', WP_EXTENDED_TEXT_DOMAIN); ?>
                              </h4>
                           </div>
                        </div>
                        <div class="wpext-modal-inner px-3">
                           <p class="wpext-rollback-intro"><?php _e( 'Are you sure you want to perform the WP Rollback?', WP_EXTENDED_TEXT_DOMAIN ); ?></p>
                           <div class="rollback-details">
                              <table class="table is-fullwidth is-striped">
                                 <tbody>
                                    <tr>
                                       <td class="row-title">
                                          <label for="tablecell">
                                          <?php if ( $plugin_rollback == true ) { _e( 'Plugin Name:', WP_EXTENDED_TEXT_DOMAIN ); } else {
                                             _e( 'Theme Name:', WP_EXTENDED_TEXT_DOMAIN ); } ?>
                                          </label>
                                       </td>
                                       <td><span class="wpext-plugin-name"></span></td>
                                    </tr>
                                    <tr class="alternate">
                                       <td class="row-title">
                                          <label for="tablecell"><?php _e( 'Current Installed Version:', WP_EXTENDED_TEXT_DOMAIN ); ?></label>
                                       </td>
                                       <td><span class="wpext-installed-version"></span></td>
                                    </tr>
                                    <tr>
                                       <td class="row-title">
                                          <label for="tablecell"><?php _e( 'New Version:', WP_EXTENDED_TEXT_DOMAIN ); ?></label>
                                       </td>
                                       <td><span class="wpext-new-version"></span></td>
                                    </tr>
                                 </tbody>
                              </table>
                           </div>
                           <div class="wpext-error">
                              <p><?php _e( '<strong>Notice:</strong> We strongly recommend you create a complete backup', WP_EXTENDED_TEXT_DOMAIN ); ?></p>
                           </div>
                           <?php do_action( 'wpext_pre_rollback_buttons' ); ?>
                           <input type="submit" value="<?php _e( 'Confirm', WP_EXTENDED_TEXT_DOMAIN ); ?>" class="button-primary wpext-go" />
                           <a href="#" class="button-secondary wpext-close"><?php _e( 'Cancel', WP_EXTENDED_TEXT_DOMAIN ); ?></a>
                           <?php do_action( 'wpext_post_rollback_buttons' ); ?>
                        </div>
                     </div>
                     <!-- Rollback popup layout -->
                  </div>
               </div>
            </div>
         </div>
         <!-- Bootstrap popup End here -->
      </form>
   </div>
</div>
</div>
</div>
</div>
 
