document.addEventListener('DOMContentLoaded', () => {
  if (typeof wpExtUserSections === "undefined") {
    return;
  }
 const URLs = JSON.parse(JSON.stringify(wpExtUserSections)),
    sections = {};
      jQuery.each(URLs, function(key, val) {
      var userkey = jQuery('.' + key).css('display', 'none');
   });
});