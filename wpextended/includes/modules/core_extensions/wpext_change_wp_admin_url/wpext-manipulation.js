jQuery(document).ready(function(){
	jQuery('li#wp-admin-bar-logout').click(function(){
	 setTimeout(function() {
          location.reload(true);
        }, 500);
	});
});