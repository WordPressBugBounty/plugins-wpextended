jQuery(document).ready(function() {
    jQuery('#wpext_blocked_user_list').DataTable({
        'columns': [{
                data: 'Username'
            },
            {
                data: 'IP'
            },
            {
                data: 'Date'
            },
            {
                data: 'Lockout Time'
            },
        ],
        'columnDefs': [{
            'targets': [0, 1, 2, 3],
            'orderable': false,
        }]
    });
    
  /*validation*/
  jQuery('.check_limit .button-primary').click(function() {
      var login_attempt = jQuery('#login_attempt').val();
      var limit_time = jQuery('#limit_time').val();
      if (login_attempt == null || login_attempt == '') {
          jQuery('#login_attempt').val('3');
      }
      if (limit_time == null || limit_time == '') {
          jQuery('#limit_time').val('30');
      }
  });

  /*Validation*/
  jQuery('#limit_time').keyup(function() {
      this.value = this.value.replace(/[^0-9\.]/g, '');
  });
  jQuery(document).on('keyup', '#wp-extended-login-attempts input', function(){
    console.log('herer');
    jQuery('.wpext-white-wrap').removeClass('wpext_hide');
  }); 

});