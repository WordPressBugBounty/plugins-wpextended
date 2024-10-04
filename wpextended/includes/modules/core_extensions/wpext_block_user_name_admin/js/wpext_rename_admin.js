jQuery(document).ready(function(){
  jQuery('#change_user').click(function(){
    var username = jQuery('.wpext_change_admin #change_username').val();
    var current_userid = jQuery('.wpext_change_admin #change_user').attr('data-id');
    jQuery('.wpext_change_admin .user_validation').css('color', '#d63638');
    if(username == null || username == ''){
      jQuery('.wpext_change_admin #change_username').addClass('invalid-name');
      return false;
    }
    /*Ajax Start*/
       jQuery.ajax({
        url:change_ajax_obj.ajax_url,
        type: 'post',
        data: {
          'action': 'wpext_change_admin_name',
          'username': username,
          'userid': current_userid,
          'nonce' : change_ajax_obj.ajax_nonce },
           success: function (response) {
            if(response.status != null){
              jQuery('.wpext_change_admin').removeClass('notice-error');
              jQuery('.wpext_change_admin').addClass('notice-success');
              jQuery('.wpext_change_admin p').text(response.status);
              jQuery('.wpext_change_admin input').remove();
              setTimeout(function() {
                  jQuery('.wpext_change_admin').remove();
              }, 3000);
            }
            if(response.usertext == 'admin'){
              jQuery('#change_username').addClass('invalid-name'); 
               jQuery('.user_validation').text(response.message);   
              return false;
            }else{
              jQuery('.user_validation').text(response.invalid); 

            }
          }
        });
       /*Ajax end here*/   
  });
  jQuery('.wpext_change_admin #change_username').keypress(function(){
    jQuery('.wpext_change_admin #change_username').removeClass('invalid-name');
  });
});
