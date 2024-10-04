jQuery(function($) {
    var frame;
    // ADD IMAGE LINK
    jQuery('.upload_banner_img').on('click', function(event) {
        event.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Background Image',
            library: {
                type: ['video', 'image']
            },
            button: {
                text: 'Select Background Image'
            },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            jQuery('.wpext_coming_soon_img').attr('src', attachment.url);
            jQuery('.coming_img').val(attachment.url);
            if(attachment.url != '') {
                jQuery('.wpext_coming_soon_img').html('<img src="' + attachment.url + '" class="wpext_coming_soon"><span class="wpe_remove_img delete-img remove position-absolute" role="button"></span>');
            }
        });
        frame.open();
    });
    var background_img = jQuery('.coming_img').val();
    if (background_img != '') {
        jQuery('.wpext_coming_soon_img').html('<img src="' + background_img + '" class="wpext_coming_soon"><span class="wpe_remove_img delete-img remove position-absolute" role="button"></span>');
    }
    jQuery('.upload-img img').attr('src', background_img);

    jQuery(document).on('click', '.delete-img', function() {
        jQuery('.upload-img img').attr('src', '');
        jQuery('.coming_img').val('');
        jQuery('.wpext_coming_soon').remove();
        jQuery('.delete-img').remove();
    });
    
});

jQuery(function ($) {
    var logo;

    /*Logo Iframe*/

    jQuery('.wpext-admin-admin-bar-maintenance .upload_logo_img').on('click', function (event) {
        event.preventDefault();
        if (logo) {
            logo.open();
            return;
        }
        logo = wp.media({
            title: 'Select Logo',
            library: {
                type: ['video', 'image']
            },
            button: {
                text: 'Upload Logo'
            },
            multiple: false
        });
        logo.on('select', function () {
            var attachment = logo.state().get('selection').first().toJSON();
            jQuery('.wpext_coming_logo_img').attr('src', attachment.url); 
            jQuery('.header_logo-maintenance').val(attachment.url);             
            if (attachment.url != '') {
                jQuery('.wpext_coming_logo_img').html('<img src="' + attachment.url + '" class="wpext_coming_logo"><span role="button" class="wpe_remove_img delete-logo remove position-absolute">');
            }
        });
        logo.open();
    });


    var logotop = jQuery('.header_logo-maintenance').val();
    if (logotop != '') {
        jQuery('.wpext_coming_logo_img').html('<img src="' + logotop + '" class="wpext_coming_logo"><span class="wpe_remove_img delete-img-logo remove position-absolute" role="button"></span>');
    }

    jQuery(document).on('click', '.delete-logo', function () {
        jQuery('.wpext_coming_logo_img').attr('src', '');
        jQuery('.header_logo-maintenance').val('');
        jQuery('.wpext_coming_logo').remove();
        jQuery('.delete-logo').remove();
    });

    var background_img = jQuery('.header_logo-maintenance').val();
    if (background_img != '') {
        jQuery('.wpext_coming_logo_img').attr('src', background_img);
    }

    jQuery(document).on('click', '.delete-img-logo', function () {
        jQuery('.upload-img img').attr('src', '');        
        jQuery('.header_logo-maintenance').val('');
        jQuery('.wpext_coming_logo').remove();
        jQuery('.delete-img-logo').remove();
    });

    jQuery('.wp-color-picker').wpColorPicker();

    jQuery('.wpext_maintenance_layout form input').keyup(function () {
        jQuery('.wpext-white-wrap').removeClass('wpext_hide');
    });

    jQuery('.wpext_maintenance_layout form button[type="button"]').click(function () {
        jQuery('.wpext-white-wrap').removeClass('wpext_hide');
    });

    jQuery('.wpext_coming_logo_img img[src="undefined"]').remove();
});


/*Selected Maintinance layout js*/
jQuery(document).ready(function(){
    jQuery('.image-checkbox').on('click', function() {
        jQuery('.image-checkbox').removeClass('wpext_active_layout'); 
        jQuery(this).addClass('wpext_active_layout');  
    });

    var customCssCalled = false;

    if(jQuery("#wpext_existing_layout").val() == 'sel_layout'){
        wpext_maintenance_custom_css();
        customCssCalled = true;
    }

    /* Hide layout if existing page selected*/
    jQuery("#wpext_existing_layout").change(function(){
        var slected = jQuery(this).val();
         if(slected && slected == 'wp_page'){
            jQuery('#wpext_choose_layout_section').addClass('d-none');
            jQuery('#layout_text_section').addClass('d-none');
            jQuery('.wpext_custom_css_section').addClass('d-none');
            jQuery('#wpext_select_page').removeClass('d-none');
            jQuery('.existing_last_page_section').removeClass('border-bottom');
         }else{
            jQuery('#wpext_choose_layout_section').removeClass('d-none');
            jQuery('#layout_text_section').removeClass('d-none');
            jQuery('.wpext_custom_css_section').removeClass('d-none');
            jQuery('#wpext_select_page').addClass('d-none');
            jQuery('.existing_last_page_section').addClass('border-bottom');
            if (!customCssCalled) {
                wpext_maintenance_custom_css();
                customCssCalled = true; // Set the flag to true to indicate that the function has been called
            }
         }
    });
    /*Backgroung Clor Image*/
    jQuery('#wpext_bgcol_img').change(function(){
        var bgvalue = jQuery(this).val();
        if(bgvalue && bgvalue == 'wpext_bgimg'){
            jQuery('.maintinance_bg').addClass('d-none');
            jQuery('#wpext_bg_banner').removeClass('d-none');
        }else{
            jQuery('.maintinance_bg').removeClass('d-none');
        }
        if(bgvalue &&  bgvalue == "wpext_bgcolor"){
            jQuery('#wpext_bg_banner').addClass('d-none');
        } 

    });
    /* Backgroung Color Image end here*/

    /*Maintinance logo*/
    jQuery('#wpext_mm_logo_option').change(function(){
        var bgvalue = jQuery(this).val();
        if(bgvalue && bgvalue == '2'){
            jQuery('#wpext_mm_logo').addClass('d-none');
            jQuery('#wpext_mm_logo_width').addClass('d-none');
            jQuery('#wpext_mm_logo_height').addClass('d-none');
        }else{
            jQuery('#wpext_mm_logo').removeClass('d-none');
            jQuery('#wpext_mm_logo_width').removeClass('d-none');
            jQuery('#wpext_mm_logo_height').removeClass('d-none');
        }
    });
    /* Backgroung Color Image end here*/

    /* Authentication Status role access */
    jQuery('#wpext_uthentication').change(function(){
        var uthevalue = jQuery(this).val();
        if(uthevalue == 'wpext_accessbyrole'){
            jQuery('.list_of_role_access').removeClass('d-none');
        }else{
            jQuery('.list_of_role_access').addClass('d-none');
        }
    });
    /* Authentication Status role access */

    jQuery('input[name="wpext-maintanance_mode[wpext_choose_layout]"]').change(function() {
        if (jQuery(this).val() === 'wpe_mm_layout_1') {
            jQuery('#wpext_bgcol_img').val('wpext_bgcolor');
            jQuery('#wpext_bg_banner').addClass('d-none');
            jQuery('.maintinance_bg').removeClass('d-none');

        }
    });
});



/* Selected Maintinance layout js end here */ 
function wpext_maintenance_custom_css(){
    var cssEditor = wp.codeEditor.initialize(jQuery('#wpext_mm_custom_css'), {
        type: 'text/css'
    });
}


/*MM Disable*/

jQuery(document).ready(function(){
    jQuery('#wpext_maintanance_sitemode').change(function(){
        var Modename = jQuery(this).val();
             if(Modename == 'wpext_disable'){
            jQuery('.wpext-return-module-wrap .wpext-return-module').addClass('d-none');
        }else{
           jQuery('.wpext-return-module-wrap .wpext-return-module').removeClass('d-none'); 
        }

    });
});