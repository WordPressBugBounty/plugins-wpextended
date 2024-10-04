jQuery( document ).ready(function() {
  
  jQuery('.wpext_duplicate_menu form select[name="wpext_source"]').change(function(){
    jQuery('.wpext-white-wrap').removeClass('wpext_hide');
  });

  jQuery('.wpext_duplicate_menu form input').keyup(function(){
    jQuery('.wpext-white-wrap').removeClass('wpext_hide');
  });
});