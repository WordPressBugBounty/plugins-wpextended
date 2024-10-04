jQuery(document).ready(function() {
    //jQuery('#smtp-clear-log').hide();
    var smtp_post = jQuery('input#smtp_post').val();
     if(smtp_post == ''){
        jQuery('input#smtp_post').val('587');
        jQuery('input[name="smtp_post_number"][value="587"]').prop('checked', true);
    }
    /*Log section*/

    jQuery('.wpext-tab').click(function(){
        jQuery('.wpext-tab').removeClass('active');
        jQuery(this).addClass('active');
    });
    jQuery('#smtp-setup').click(function(){
        jQuery('.setting_section').addClass('active');
        jQuery('.log-section').removeClass('active');
    });
    jQuery('#smtp-setup-log').click(function(){
        jQuery('.setting_section').removeClass('active');
        jQuery('.log-section').addClass('active');
        jQuery('#smtp-clear-log').show();
    });

    /*url Paramiter*/

    var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
        }
    }
    return false;
    };

    /*Url Paramiter end here*/
    var page = getUrlParameter('page');
    if(page == 'wp-extended_smtp-settings'){
      jQuery('#smtp-setup').trigger('click');
    }

    var pages = getUrlParameter('pages');
    if(pages != '' && pages != null){
        jQuery('#smtp-setup-log').trigger('click');
    }


    jQuery('#smtp-setup').click(function(){
        localStorage.setItem('lsetting', 'log_setting');
        localStorage.setItem('elog', '');
    });
    jQuery('#smtp-setup-log').click(function(){
        localStorage.setItem('elog', 'email_log');
        localStorage.setItem('lsetting', '');
    });
    var gettoken = localStorage.getItem('lsetting');
    if(gettoken == 'log_setting'){
          jQuery('#smtp-setup').trigger('click');
      }else{
        jQuery('#smtp-setup-log').trigger('click');
      }
    jQuery('#smtp-setup').click(function(){
        url = jQuery(location).attr('href');
        var uri = window.location.toString();
            if (uri.indexOf("&") > 0) {
                var clean_uri = uri.substring(0, uri.indexOf("&"));
                window.history.replaceState({}, document.title, clean_uri);
            }
        });
    
    /*Getting port value in radio button checked*/
    jQuery('input[name="smtp_post_number"]').change(function() {
        if (jQuery(this).is(':checked')) {
            var selectedValue = jQuery(this).val();
            jQuery('input#smtp_post').val(selectedValue);
        }
    });

 /*SMTP Tab Action*/
jQuery('.wpext_smtp_options .tablinks').click(function(){
    jQuery('.wpext_smtp_options .tablinks').removeClass('active');
    jQuery(this).addClass('active');
});

jQuery('#wpext_smtpconfig').click(function(){
 jQuery('.wpext_smtp-config').addClass('active');
 jQuery('.wpext_smtp_email_log').removeClass('active');
 jQuery('.wpext_smtp_test').removeClass('active');

 //disabled button 
 jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '1');
});
jQuery('#wpext_emailog').click(function(){
 jQuery('.wpext_smtp-config').removeClass('active');
 jQuery('.wpext_smtp_email_log').addClass('active');
 jQuery('.wpext_smtp_test').removeClass('active');

 //disabled button 
 jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '0');
});
jQuery('#wpext_emailtest').click(function(){
 jQuery('.wpext_smtp-config').removeClass('active');
 jQuery('.wpext_smtp_email_log').removeClass('active');
 jQuery('.wpext_smtp_test').addClass('active');

 //disabled button  
 jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '0');
});

/*SMTP Tab Action End Here*/     

    //page reload after tab active 
    jQuery(document).ready(function() {
        var action_smtp = localStorage.getItem('wpext_action_smtp_test');

        jQuery('#wpext_smtpconfig').removeClass('active');
        jQuery('.wpext_smtp-config').removeClass('active');
        
        jQuery('#wpext_emailog').removeClass('active');
        jQuery('.wpext_smtp_email_log').removeClass('active');

        jQuery("#wpext_emailtest").removeClass('active');
        jQuery(".wpext_smtp_test").removeClass('active');

        if(action_smtp == "#wpext_emailtest"){
            setTimeout(function() { 
                jQuery(".wpext_smtp_options "+action_smtp).addClass('active');
                jQuery(".wpext_smtp_test").addClass('active');
                //disabled button                 
                jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '0');
             }, ); 
        }else if(action_smtp == "#wpext_emailog"){
            setTimeout(function() { 
                jQuery(".wpext_smtp_options "+action_smtp).addClass('active');
                jQuery('.wpext_smtp_email_log').addClass('active');
                //disabled button                 
                jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '0');
             }, ); 
        }else {
            setTimeout(function() { 
                jQuery(".wpext_smtp_options #wpext_smtpconfig").addClass('active');
                jQuery('.wpext_smtp-config').addClass('active'); 
                
                jQuery(".wpext_module_action.wp-ext-btn-sec").css('opacity', '1');
             }, ); 
        }

        jQuery(document).on('click', '#wpext_smtpconfig, #wpext_emailog, #wpext_emailtest', function(event) {
            localStorage.setItem('wpext_action_smtp_test', '#' + this.id);
        });
    });

});


 