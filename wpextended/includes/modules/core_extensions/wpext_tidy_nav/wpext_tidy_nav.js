 document.addEventListener('DOMContentLoaded', () => {
  if (typeof wpExthidemenumain === "undefined") {
    return;
  }
 var menudata = wpExthidemenumain,
 sections = {};
jQuery(document).ready(function(){
  var post_id = localStorage.getItem('user-id');
  var urole = localStorage.getItem('user-role');
  var current_role = jQuery('select#by_user_role').val();
  jQuery('select#by_user_role').val(current_role);
  if(current_role == 'administrator'){
    jQuery('.update-role').attr('disabled', 'disabled');
    }else{
        jQuery('.update-role').removeAttr("disabled");
    }
 jQuery(".header-table").on('click','.btn-group a',function(){
    jQuery(".header-table .btn-group a").removeClass("is-active"); 
    jQuery(this).addClass("is-active"); 
    });
    jQuery('#user-load').click(function(){
      jQuery('.role-wise-form').addClass('is-active');
      jQuery('.user-wise-form').removeClass('is-active');
    });
    jQuery('#user-id').click(function(){
      jQuery('.role-wise-form').removeClass('is-active');
      jQuery('.user-wise-form').addClass('is-active');
    });
  
 /*Paramiter passing in the url*/  

 jQuery('#user-id').click(function(){
  var id = localStorage.setItem('user-id', 'uid');
  var role = localStorage.setItem('user-role', '');
  /*var data = "admin.php?page=wp-extended-tidymenu&menu=userid";
  window.history.pushState({ path: data }, '', data);*/
 });
 jQuery('#user-load').click(function(){
  var role = localStorage.setItem('user-role', 'urole');
  var id = localStorage.setItem('user-id', '');
  /*var data = "admin.php?page=wp-extended-tidymenu&menu=userrole";
  window.history.pushState({ path: data }, '', data);*/
 });

/*Paramiter passing in the url End Here*/

jQuery('select#by_user_role').on('change', function(e) {
 var admin_user_role = this.value;
 if(admin_user_role != null || admin_user_data != ''){
   jQuery('#userrole').val(admin_user_role);
   jQuery('.role-wise-form').addClass('wait');
      /*Ajax began*/
       jQuery.ajax({
        url:role_ajax_obj.ajax_url,
        type: 'post',
        data: {
          'action': 'render_data_byuser_role',
          'user_role': admin_user_role,
          'menudata': menudata,
          'nonce' :  role_ajax_obj.ajax_nonce  },
          success: function (data) {
            jQuery('.role-wise-form').removeClass('wait');
            jQuery('.user_role_table tbody').html(data.layoutdata);
            if (jQuery('.user_role_table tbody input:checked').length == jQuery('.user_role_table tbody input').length) {
                jQuery('.user_role_table #enable-allrole').prop('checked', true);
            }else{
                jQuery('.user_role_table #enable-allrole').prop('checked', false);
            }
            if(admin_user_role == 'administrator'){
            jQuery('.update-role').attr('disabled', 'disabled');
            }else{
                jQuery('.update-role').removeAttr("disabled");
            }
            
          }
        });
      /*Ajax end here*/
    }
    e.preventDefault();
});
/*Submit Validation*/
jQuery('.update-role').click(function(){
 var checkrole = jQuery('select#by_user_role').val();
  if(checkrole == null || checkrole ==''){
    jQuery('#by_user_role').addClass('validate');
      jQuery('html, body').animate({
          scrollTop: jQuery("#by_user_role").offset().top
      }, 2000);
      setTimeout(function(){
          jQuery('#by_user_role').removeClass('validate');
      }, 2000);
    return false;
  }
});
jQuery('.update-user').click(function(){
 var checkrole = jQuery('select#by_user_id').val();
  if(checkrole == null || checkrole ==''){
    jQuery('#by_user_id').addClass('validate');
      jQuery('html, body').animate({
          scrollTop: jQuery("#by_user_role").offset().top
      }, 2000);
      setTimeout(function(){
          jQuery('#by_user_id').removeClass('validate');
      }, 2000);
    return false;
  }
});
/*Submit Validation end here*/ 
});
});


/*Reorder tidy menu*/

 jQuery(document).ready(function($) {
    // Assuming your table has an ID 'custom-table'
    jQuery("#userlist-of-sections tbody").sortable({
        axis: 'y',
        update: function(event, ui) {
            wpext_saveTableOrder_by_user_role();
        }
    });
    function wpext_saveTableOrder_by_user_role() {
         var order = [];
         var userrole = [];
         var role_menu_order = [];
         var data_key = [];
        jQuery("body #userlist-of-sections tbody tr").each(function() {
             order.push(jQuery(this).attr("id"));
             var dt = jQuery(this).attr("order-record");
             var data_key = jQuery(this).attr("data_key");
             var parsedDt = JSON.parse(dt);
             role_menu_order.push({rorder:parsedDt,Item:[data_key] });
             var user_role = jQuery('#userrole').val();
             userrole.push(user_role); 
        });
        // console.log(order);
        // AJAX call to save the order in WordPress options
       jQuery.ajax({
            type: "POST",
            url: role_ajax_obj.ajax_url,
            data: {
                action: "save_table_order",
                order: order,
                user_role:userrole,
                role_menu_order: role_menu_order
            },
            success: function(response) {
                console.log("Table order saved successfully.");
            },
            error: function(response) {
                console.error("Error saving table order.");
            }
        });
    }

    /*Reorder tidy nav end here*/
    jQuery('#wpext_reset_role').click(function() {
        if (!confirm("Are you sure you want to reset?.")) {
            return false;
        }
        var current_role = jQuery('#userrole').val();
        if (current_role != '' && current_role != null) {
            jQuery.ajax({
                type: "POST",
                url: role_ajax_obj.ajax_url,
                data: {
                    action: "wpext_remove_role_order",
                    current_role: current_role
                },
                success: function(response) {
                    jQuery('#by_user_role').change();
                    jQuery('#user-id').removeClass('is-active');
                    jQuery('#user-load').addClass('is-active');
                    console.log("Role order reset successfully.");
                },
                error: function(response) {
                    console.error("Error reset order.");
                }
            });
        }
    });
});
 /*Reorder tidy nav end here*/

