 jQuery(document).ready(function($) {
    $(document).on('change', 'input[type="checkbox"][data-snippet-id]', function(e) {
        var $checkbox = $(this);
        var snippetId = $checkbox.data('snippet-id');

        var $columnName = $checkbox.closest('tr').find('.column-name');
        var snippetTitle = $columnName.contents().filter(function() {
            return this.nodeType === 3; // Node type 3 is a text node
        }).text().trim();
        var snippetActive = $checkbox.prop('checked') ? 1 : 0;

        // AJAX request
        $.ajax({
            type: 'POST',
            url: wp_ajax_object.ajax_url,
            data: {
                action: 'wpext_update_snippet_status',
                snippet_id: snippetId,
                snippet_title: snippetTitle,
                snippet_active: snippetActive,
                wpext_update_flag:'status_token',
                security: wp_ajax_object.ajax_nonce
            }
        })
        .done(function(response) {
            console.log(response.data.snippet_title);
            if (response.success) {
                if (response.data.success == 1) {
                    jQuery('#liveToast').removeClass('bg-danger');
                    jQuery('#liveToast').addClass('popup');
                    jQuery('.toast-body').html('' + response.data.snippet_title + '<strong> ' + response.data.action + '</strong>');
                    jQuery('.toast').toast('show');
                } else {
                    jQuery('#liveToast').removeClass('popup');
                    jQuery('#liveToast').addClass('bg-danger');
                    jQuery('.toast-body').html('' + response.data.snippet_title + '<strong> ' + response.data.action + '</strong>');
                    jQuery('.toast').toast('show');
                }
            } else {
                console.error(response.data);
            }
        }).fail(function(xhr, status, error) {
            // console.error(xhr.responseText);
            var $checkbox = $('input[type="checkbox"][data-snippet-id="' + snippetId + '"]');
            if ($checkbox.length) {
                $checkbox.prop('checked', false);
            } else {
                console.error('Checkbox with data-snippet-id ' + snippetId + ' not found.');
            }
            console.log(jQuery('#' + snippetId));
            var errorMessage = xhr.responseText;
            var endIndex = errorMessage.indexOf('There has been a critical error on this website.');
            var specificErrorMessage = errorMessage.substring(0, Math.min(endIndex, 150)).trim();
            var wpext_msg = '<div class="wpext-fail-message rounded-2 d-flex"><span>&#x2717; Error</span> &nbsp;' + specificErrorMessage + '...</div>';
            $('.wpext_activationerror').html(wpext_msg);
            
        });
    });
    /*Delete code Snippet*/
    $(document).on('click', '.wpext_code_snippet_layout .delete a', function(e) {

        e.preventDefault();
        if (!confirm('Are you sure you want to permanently delete the selected code snippet? Click "Cancel" to stop or "OK" to delete.')) {
            return; // Exit if user cancels
        }
        var postId = $(this).attr("wpext-attr-id");
        var $row = $(this).closest('tr');
        $(this).closest('.delete').html('<div class="loading" style="padding: 10px;"><div class="spinner is-active" style="float:none;"></div></div>');
        $.ajax({
            type: 'POST',
            url: wp_ajax_object.ajax_url,
            data: {
                action: 'wpext_delete_code_snippet',
                post_id: postId,
                security: wp_ajax_object.ajax_nonce
            },
            success: function(response) {
                $row.remove();
                 jQuery('#snippet_del_message').removeClass('d-none');
                if (jQuery('.wpext_code_snippet #the-list th').length === 0) {
                    // location.reload();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error: ' + textStatus, errorThrown);
            }
        });
    });

    /*Edit Update */
    var message = '';
    jQuery(document).ready(function($) {
        $('.wpext_updeate_code_snippets').on('click', function(event) {
            var snippettitle = jQuery('.snippet_name').val();
            if (snippettitle == '') {
                jQuery('.snippet_name').addClass('wpext_danger');
                setTimeout(function() {
                    jQuery('.snippet_name').removeClass('wpext_danger');
                }, 1000);
                return false;
            }
            jQuery('.update_snippets').addClass('wpext_shaddow');
            event.preventDefault();
            var editor = $('.CodeMirror')[0].CodeMirror;
            var snippet_code = editor.getValue();
            var snippet_active = $('#snippet_active').is(':checked') ? '1' : '0';
            var formData = {
                action: 'handle_snippet_update',
                security: wp_ajax_object.nonce,
                snippet_id: $('#snippet_id').val(),
                snippet_name: $('.snippet_name').val(),
                snippet_code: snippet_code, // Get value from CodeMirror instance
                snippet_position: $('#snippet_position').val(),
                snippet_code_type: $('#snippet_code_type').val(),
                snippet_code_sesc: $('#snippet_code_sesc').val(),
                wpext_update_flag: 'update_token',
                snippet_active: snippet_active
            };
            $.post(wp_ajax_object.ajax_url, formData, function(response) {
                $('.update_snippets').removeClass('wpext_shaddow');
                if (response.success) {
                    // Display success message
                    $('.notice').remove();
                    var wpext_msg = '<div class="wpext-success-message rounded-2 "><span>&#x2713; Success</span> ' + response.data.message + '</div>';
                    $('#wpext_notice_message').html(wpext_msg);
                } else {
                    // Display error message
                    $('.notice').remove();
                    var wpext_msg = '<div class="wpext-fail-message rounded-2 "><span>&#x2717; Error</span> ' + response.data.message + '</div>';
                    $('#wpext_notice_message').html(wpext_msg);
                }
            }).fail(function(xhr, status, error) {
                // console.error(xhr.responseText);
                $('.notice').remove();
                var errorMessage = xhr.responseText;
                var endIndex = errorMessage.indexOf('There has been a critical error on this website.');
                var specificErrorMessage = errorMessage.substring(0, Math.min(endIndex, 120)).trim();
                var wpext_msg = '<div class="wpext-fail-message rounded-2 mx-3"><span>&#x2717; Error</span> ' + specificErrorMessage + '...</div>';
                $('#wpext_notice_message').html(wpext_msg);
            });
        });
    });
});
/*Create New Snippet*/

 jQuery(document).ready(function($) {
    $(document).on('click', '.wpext_save_snippet', function(e) {
        var snippettitle = jQuery('.wpext_snippet_name').val();
        if(snippettitle == ''){
        jQuery('.wpext_snippet_name').addClass('wpext_danger');
            setTimeout(function(){
                jQuery('.wpext_snippet_name').removeClass('wpext_danger');
            }, 2000);
            return false;
        }
        jQuery('.wpext_add_newsnippets').addClass('wpext_shaddow');
        e.preventDefault();
        var snippet_name = $('.wpext_snippet_name').val();
        var editor = $('.CodeMirror')[0].CodeMirror;
        var snippet_code = editor.getValue();
        /*Mirror Validation things End here */
        var snippet_position = $('#snippet_position').val();
        var snippet_code_type = $('#snippet_code_type').val();
        var snippet_code_sesc = $('#snippet_code_sesc').val();
        var snippet_active = $('.addnew_snippet_active').prop('checked') ? 1 : 0;
        var data = {
            'action': 'save_snippet',
            'snippet_name': snippet_name, 
            'snippet_code': snippet_code,
            'snippet_position': snippet_position,
            'snippet_code_type': snippet_code_type,
            'snippet_code_sesc': snippet_code_sesc,
            'snippet_active': snippet_active,
            'wpext_snippet_nonce': $('#wpext_snippet_nonce').val()
        };
        $.ajax({
            url: wp_ajax_object.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var wpext_msg = '<div class="wpext-success-message rounded-2"><span>&#x2713; Success</span> ' + response.data.message + '</div>';
                     $('#wpext_notice_message').html(wpext_msg);
                } else {
                   var wpext_msg = '<div class="wpext-fail-message rounded-2"><span>&#x2713; Whoops</span> ' + response.data.message + '</div>';
                    $('#wpext_notice_message').html(wpext_msg);
                }
                jQuery('.wpext_add_newsnippets').removeClass('wpext_shaddow');
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    });
});  
function selectRadio_existing(id, element) {
    document.getElementById(id).checked = true;
    // Remove active class from all radio containers
    const containers = document.querySelectorAll('.wpext_snippet_steps .radio-container');
    containers.forEach(container => container.classList.remove('active'));
    // Add active class to the clicked container
    element.classList.add('active');
    // Check active state and enable/disable button
    checkActiveState();
}

function selectRadio(id, element) {
    document.getElementById(id).checked = true;
    // Remove active class from all radio containers
    const containers = document.querySelectorAll('.wpext_snippet_steps .radio-container');
    containers.forEach(container => container.classList.remove('active'));
    // Add active class to the clicked container
     element.classList.add('active');
    // Check active state and enable/disable button
    checkActiveState();
}

function checkActiveState() {
    const currentPageUrl = window.location.href;
    const pageParam = 'page=wp-extended-snippets';
    if (currentPageUrl.includes(pageParam)) {
        const activeContainer = document.querySelector('.wpext_snippet_steps .radio-container.active');
        const goButton = document.getElementById('wpext_goto_snippet');
        if (activeContainer) {
            goButton.classList.remove('button-disabled');
        } else {
            goButton.classList.add('button-disabled');
        }
    }
}
// Initial check to set button state on page load
checkActiveState();
function appendRadioValue() {
    const selectedRadio = document.querySelector('input[name="wpext_snippet_option"]:checked');
    if (selectedRadio) {
        const selectedValue = selectedRadio.value;
        const link = document.getElementById('wpext_goto_snippet');
        link.href = `${link.href}&selected_option=${selectedValue}`;
    }
}
/*Heating btn from header button */

jQuery(document).ready(function(){
 if (jQuery('#staticBackdrop').hasClass('show')) {
    jQuery('.wpext_snippet_steps').css('display', 'none');
  }
 jQuery('.page-title-action').click(function(){
   jQuery('.wpext_snippet_popup').trigger('click');
   jQuery('.wpext_snippet_steps').css('display', 'block');
   jQuery('#wpext_existing_popup').removeClass('d-none');
 });
});

jQuery(document).ready(function($) {
    jQuery('#staticBackdrop').on('hide.bs.modal', function () {
       jQuery('#wpext_existing_popup').addClass('d-none');
    });
});


 