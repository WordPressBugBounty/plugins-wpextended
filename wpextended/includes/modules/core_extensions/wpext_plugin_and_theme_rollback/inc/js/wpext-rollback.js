var wpext_vars;

jQuery.noConflict();
(function($) {

	// On DOM Ready
	$(function() {
		var form = $('.rollback-form');
		var form_labels = $('label', form.get(0));
		var form_submit_btn = $('.wpextended_popup');
		form_submit_btn.removeClass('wpext-rollback-disabled');
		var rollback_version = jQuery('#wpext_selected_version').val();
		form_labels.removeClass('wpext-selected');
		form_submit_btn.removeClass('wpext-rollback-disabled');
		jQuery('#selected_ver').val(rollback_version);

		/**
		 * Modal WP Extended rollback.
		 */
		form_submit_btn.on('click', function() {
			var rollback_form_vals = form.serializeArray();
			var rollback_version = jQuery('#selected_ver').val();
			if (!rollback_version) {
				rollback_version = jQuery('#selected_ver').val();
			}
			var installed_version = form.find('input[name="installed_version"]').val();
			var new_version = form.find('input[name="new_version"]').val();
			var rollback_name = form.find('input[name="rollback_name"]').val();
			// Ensure a version is selected
			if (!rollback_version) {
				// console.log(wpext_vars.version_missing);   
				jQuery('#exampleModal').modal('hide'); 
			} else {
				jQuery('#exampleModal').modal('show');
				jQuery('span.wpext-plugin-name').text(rollback_name);
				jQuery('span.wpext-installed-version').text(installed_version);
				jQuery('span.wpext-new-version').text(rollback_version);
			}
		});

		// Modal Close
		$('.wpext-close').on('click', function(e) {
			e.preventDefault();
			jQuery('#exampleModal').modal('hide'); 
			 
		});
		// Close popup
		$('.wpext-go').on('click', function(e) {
			form.submit();
		});
		jQuery('#wpext_selected_version').change(function(){
			var rollback_version = jQuery('#wpext_selected_version').val();
			form_labels.removeClass('wpext-selected');
			form_submit_btn.removeClass('wpext-rollback-disabled');
			jQuery('#selected_ver').val(rollback_version);
			$(this).addClass('wpext-selected');
		});
	});

	/*Open Popup*/
	jQuery(document).ready(function(){
	 	jQuery("#rollback_btn").click(function(){
	 		jQuery('#exampleModal').modal('show');
	 	});
 	});

})(jQuery);


 