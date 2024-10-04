jQuery(document).ready(function(){
	jQuery('li#wp-admin-bar-logout').click(function(){
	setTimeout(function() {
	      location.reload(true);
	    }, 500);
	});

	jQuery('.wpext_custom_login_url form input').keyup(function(){
      jQuery('.wpext-white-wrap').removeClass('wpext_hide');
    });
});