(function ($) {
    $(document).ready(function () {
        var targetElement = 'tr[data-slug="wpextended"] span.deactivate a';
        var redirectUrl = $(targetElement).attr('href');
        $(document).on('click', targetElement, function(e) {
            e.preventDefault();
            $('.wpext-deactivation-survey').stop().css({opacity: 1, visibility: 'visible'});
        });
        $(document).on('click', '.cancel-deactivation-survey', function(e) {
            e.preventDefault();
            $('.wpext-deactivation-survey').stop().css({opacity: 0});
            setTimeout(function() {
                $('.wpext-deactivation-survey').stop().css({visibility: 'hidden'});
            }, 350)
            $('.wpext-deactivation-survey form input[type=radio]').prop('checked', false);
        })
        $(document).on('click', '.deactivate-plugin', function(e) {
            e.preventDefault();
            window.location.href = redirectUrl;
        })
        $(document).on('change', 'input[name=wpext_reason]', function(e) {
            if (e.target.value !== 'other' && e.target.value) {
                $('.submit-and-deactivate').removeAttr('disabled');
            } else {
                $('.submit-and-deactivate').attr('disabled', 'disabled');
            }
        })
        $(document).on('keyup', '[name=wpext_deactivation_description]', function(e) {
            if (e.target.value !== '') {
                $('.submit-and-deactivate').removeAttr('disabled');
            } else {
                $('.submit-and-deactivate').attr('disabled', 'disabled');
            }
        }) 
        $(document).on('click', '.submit-and-deactivate', function(e) {
            e.preventDefault();
            var reason = $.trim($('input[name=wpext_reason]:checked').val());
            var description = $.trim($('textarea[name=wpext_deactivation_description]').val());
            var share_email = $('[name=wpext_reply]').prop('checked');
            var version = $('[name=wpext_version]').val();
            if (reason === 'other' && !description) {
                alert('Please fill the description.');
                return;
            }  
            $.ajax({
                type: 'POST',
                url: 'https://feedback.wpextended.io/wp-json/feedback-api/get_feedback',
                data: {
                    feedback: reason,
                    description: description,
                    site_url: $('input[name=wpext_site_url]').val(),
                },
                success: function (response) {
                    console.log('RESPONSE', response);
                },
                complete: function() {
                   window.location.href = redirectUrl;
                   console.log('RESPONSE', response);
                }
            }); 

        });
        $('.other_issues').click(function() {
            jQuery('.other-issues-textarea').addClass('other_active');
        });
        jQuery('.feedback-lable').click(function(){
            jQuery('.other-issues-textarea').removeClass('other_active');
        });
         
    });
})(jQuery);
