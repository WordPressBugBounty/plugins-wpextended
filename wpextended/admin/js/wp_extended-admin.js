jQuery(document).ready(function (e) {
  /*Tooltip*/
  var QueryString = new URL(location.href).searchParams.get('page');
  if (QueryString == 'wp-extended') {
    jQuery('[data-toggle="tooltip"]').tooltip();
  }

  /*Erase data tab setting js*/
  jQuery('#wpext_module a').click(function () {
    jQuery('#module_lisence').removeClass('active').css('display', 'none');
    jQuery('#module_tools').removeClass('active').css('display', 'none');
    jQuery('#module_listing').addClass('active').css('display', 'block');
    jQuery('#wpext_tools a').removeClass('active');
    jQuery('.wptxt-action_switch').removeClass('d-none');
    jQuery('#wpext_module a').addClass('active');
  });
  jQuery('#wpext_settings a').click(function () {
    jQuery('#module_listing').removeClass('active').css('display', 'none');
    jQuery('#module_tools').removeClass('active').css('display', 'none');
    jQuery('#wpext_module a.wpext_nav').removeClass('active');
    jQuery('#wpext_tools a').removeClass('active');
  });
  jQuery('#wpext_tools a').click(function () {
    jQuery('#module_listing').removeClass('active').css('display', 'none');
    jQuery('#module_lisence')
      .removeClass('active')
      .css('display', 'none')
      .removeClass('show');
    jQuery('#module_tools').addClass('active');
    jQuery('#wpext_tools a').addClass('active');
    jQuery('#wpext_settings a').removeClass('active');
    jQuery('#wpext_module a').removeClass('active');
    jQuery('.wptxt-action_switch').addClass('d-none');
  });

  /*Log section*/
  jQuery('#smtp-clear-log').hide();
  jQuery('#smtp-setup').trigger('click');
  jQuery('.wpext-tab').click(function () {
    jQuery('.wpext-tab').removeClass('active');
    jQuery(this).addClass('active');
  });
  jQuery('#smtp-setup').click(function () {
    jQuery('.setting_section').addClass('active');
    jQuery('.log-section').removeClass('active');
    jQuery('#smtp-clear-log').hide();
  });
  jQuery('#smtp-setup-log').click(function () {
    jQuery('.setting_section').removeClass('active');
    jQuery('.log-section').addClass('active');
    jQuery('#smtp-clear-log').show();
  });

  /*Getting port value in radio button checked*/
  jQuery('input[name="smtp_post_number"]').change(function () {
    if (jQuery(this).is(':checked')) {
      var selectedValue = jQuery(this).val();
      jQuery('input#smtp_post').val(selectedValue);
    }
  });

  /*Erase data tab setting js*/

  jQuery('#wpext_module').click(function () {
    jQuery('.wpext_group_of_modules').removeClass('show');
    jQuery('.accordion.accordion-flush').addClass('show');
  });
  jQuery('#wpext_settings').click(function () {
    jQuery('.accordion.accordion-flush').removeClass('show');
    jQuery('.wpext_group_of_modules').addClass('show');
    jQuery('#wpext_module a').removeClass('active');
  });
  jQuery('.wpext_settings_tools .wpext_tab').click(function () {
    jQuery('.wpext_tab a').removeClass('active');
    jQuery(this).find('a').addClass('active');
  });

  jQuery('#wpext_settings a').click(function () {
    jQuery('#wpext_settings .a').addClass('active');
  });

  // Ajax action for reset data

  /*Action trigger for reset plugin data*/
  jQuery('#wpext_reset_settings').on('change', function () {
    var isChecked = this.checked;
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'post',
      data: {
        action: 'wpext_reset_plugin_settings',
        status: isChecked ? 'true' : 'false',
        nonce: wpext_extended_obj.ajax_nonce,
      },
      success: function (response) {
        var obj = JSON.parse(response);
        if (obj.status == 'true') {
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);
        } else {
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);
        }
      },
    });
  });

  /*Action trigger for reset plugin data*/
  jQuery('#wpext_show_submenu').on('change', function () {
    var isChecked = this.checked;
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'post',
      data: {
        action: 'wpext_show_plugin_menu',
        status: isChecked ? 'true' : 'false',
        nonce: wpext_extended_obj.ajax_nonce,
      },
      success: function (response) {
        var obj = JSON.parse(response);
        if (obj.status == 'true') {
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);

          jQuery('.wpext_menu_favorite').removeClass('d-none');
          //favorite sub menu check using database get data
          jQuery.each(obj.favorite_data, function (checkboxID, checkedState) {
            if (checkedState === 'true') {
              jQuery(
                '.wpext_admin_menu_favorite#' + checkboxID + '_favorite',
              ).prop('checked', true);
            } else {
              jQuery(
                '.wpext_admin_menu_favorite#' + checkboxID + '_favorite',
              ).prop('checked', false);
            }
          });
        } else {
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);

          jQuery('.wpext_menu_favorite').addClass('d-none');
        }
      },
    });
  });

  // If the checkbox is checked, perform your desired action
  var isCheckedSubmenu = jQuery('#wpext_show_submenu').prop('checked');
  if (!isCheckedSubmenu) {
    jQuery('.wpext_menu_favorite').addClass('d-none');
  }

  jQuery('.wpext_admin_menu_favorite').on('change', function () {
    var isChecked = this.checked;
    var dataSlug = jQuery(this).attr('data-slug');
    var dataName = jQuery(this).attr('data-name');
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'post',
      data: {
        action: 'wpext_admin_menu_favorite',
        status: isChecked ? 'true' : 'false',
        dataSlug: dataSlug,
        dataName: dataName,
        nonce: wpext_extended_obj.ajax_nonce,
      },
      success: function (response) {
        var obj = JSON.parse(response);
        if (isChecked) {
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);
        } else {
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);
        }
      },
    });
  });
  /*Show plugin menu end here*/

  /** script for Export all plugin setting **/
  jQuery('#wpext_export_setting').click(function (e) {
    var checkboxStatus = document.getElementById(
      'wpext_export_snippets_settings',
    ).checked;
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'POST',
      data: {
        action: 'wpext_export_options_to_json',
        status: checkboxStatus ? 'true' : 'false',
        security: wpext_extended_obj.ajax_nonce,
      },
      success: function (response) {
        var blob = new Blob([JSON.stringify(response)], {
          type: 'application/json',
        });
        var link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = 'wpextended_export.json';
        link.click();
        if (response) {
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html('Settings Successfully Exported!');
        } else {
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html('Somthing Went Wrong');
        }
      },
      error: function (errorThrown) {
        console.log(errorThrown);
        // Handle error
      },
    });
  });
  /** script for Import all plugin setting **/
  jQuery('#wpext_import_setting').on('click', function (e) {
    e.preventDefault();
    var file_data = jQuery('#wpext_import_file').prop('files')[0];
    if (!file_data) {
      jQuery('#liveToast').removeClass('popup');
      jQuery('#liveToast').addClass('bg-danger');
      jQuery('.toast').toast('show');
      jQuery('.toast-body').html('Please select the JSON file to import.');
      return;
    }
    var form_data = new FormData();
    form_data.append('file_data', file_data);
    form_data.append('action', 'wpext_import_json_data');
    form_data.append('security', wpext_extended_obj.ajax_nonce);
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'POST',
      data: form_data,
      processData: false,
      contentType: false,
      success: function (response) {
        var obj = JSON.parse(response);
        if (obj.success) {
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.success);
        } else {
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(obj.message);
        }
      },
      error: function (errorThrown) {
        console.log(errorThrown);
        // Handle error
      },
    });
  });
  /*Tools js end here*/

  /* Setting and Tools Main Screen  */
  jQuery('#wpext_settings').click(function () {
    jQuery('.wptxt-search-input-container').removeClass('active');
    jQuery('.wptxt-action_switch').addClass('d-none');
  });
  jQuery('#wpext_tools').click(function () {
    jQuery('.wptxt-search-input-container').removeClass('active');
    jQuery('.wptxt-action_switch').addClass('d-none');
  });
  jQuery('#wpext_module').click(function () {
    jQuery('.wptxt-search-input-container').addClass('active');
    jQuery('.wptxt-action_switch').removeClass('d-none');
  });
});

jQuery(document).ready(function () {
  jQuery('.tablinksSetting').click(function () {
    var elementsinvalid = jQuery('#wpext_license_key.is-danger.is-invalid');
    if (elementsinvalid.length > 0) {
      jQuery('#license_and_settings.wpe_sidebar.tab-content-setting')
        .removeClass('active')
        .css('display', 'none');
    }

    var tabIdSetting = jQuery(this).attr('data-tab');
    jQuery('.tab-content-setting').removeClass('active');
    jQuery('.wpe_sidebar.tab-content-setting').css('display', 'none');
    jQuery('#' + tabIdSetting)
      .addClass('active')
      .css('display', 'block');
    jQuery('.tablinksSetting').removeClass('active');
    jQuery(this).addClass('active');
  });
  jQuery('.tablinkstool').click(function () {
    var tabIdTool = jQuery(this).attr('data-tab');
    jQuery('.tab_content_tool').removeClass('active').css('display', 'none');
    jQuery('.tab_content_tool#' + tabIdTool)
      .addClass('active')
      .css('display', 'block');
    jQuery('.tablinkstool').removeClass('active');
    jQuery(this).addClass('active');
  });
});

/*Tabbing js */

/*List Grid View Js*/
jQuery(document).ready(function () {
  var action = localStorage.getItem('wpext_action');
  //console.log(action);
  if (action != '' && action != null) {
    setTimeout(function () {
      jQuery('#' + action).trigger('click');
      jQuery('#' + action + ' .wpext_nav').addClass('active');
    }, 100);
  } else {
    setTimeout(function () {
      jQuery('#wpext_list').trigger('click');
      jQuery('#wpext_list .wpext_nav').addClass('active');
    }, 100);
  }
  jQuery('#wpext_list').click(function (event) {
    event.preventDefault();
    jQuery('#wpext_products .item').addClass('list-group-item');
    var flag = jQuery(this).attr('id');
    localStorage.setItem('wpext_action', flag);
  });
  jQuery('#wpext_grid').click(function (event) {
    var flag = jQuery(this).attr('id');
    event.preventDefault();
    jQuery('#wpext_products .item').removeClass('list-group-item');
    jQuery('#wpext_products .item').addClass('grid-group-item');
    localStorage.setItem('wpext_action', flag);
  });

  // Click event handler for the list view
  jQuery('.wptxt-action_switch #wpext_list a').click(function () {
    jQuery('#wpext_grid a').removeClass('active');
    jQuery(this).addClass('active');
  });

  // Click event handler for the grid view
  jQuery('.wptxt-action_switch #wpext_grid a').click(function () {
    // Remove 'active' class from all elements with class 'p-0'
    jQuery('#wpext_list a').removeClass('active');
    // Add 'active' class to the clicked element
    jQuery(this).addClass('active');
  });
});

/*List Grid View js end here*/
function moduleBody(evt, moduleName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName('group-module-option');
  jQuery('#wpext_clear_button').trigger('click');
  if (moduleName === 'AllModulesTab') {
    for (i = 0; i < tabcontent.length; i++) {
      tabcontent[i].style.display = 'block';
    }
  } else {
    for (i = 0; i < tabcontent.length; i++) {
      tabcontent[i].style.display = 'none';
    }
    var selectedModules = document.getElementsByClassName(moduleName);
    for (i = 0; i < selectedModules.length; i++) {
      selectedModules[i].style.display = 'block';
    }
  }
  tablinks = document.getElementsByClassName('tablinks');
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(' active', '');
  }
  evt.currentTarget.className += ' active';
}
/* Module Tabbing end  */

/*Module Search Js*/
function wpext_search_module() {
  var input = document.getElementById('wpext_searchmodule').value.toLowerCase();
  var cards = document.querySelectorAll('.group-module-option');
  var clearButton = document.getElementById('wpext_clear_button');
  var activeModulesChecked = jQuery('#wpext_active_modules').is(':checked');

  if (input !== '') {
    clearButton.style.display = 'block';
  } else {
    clearButton.style.display = 'none';
  }

  // Iterate over each card to apply search and checkbox visibility logic
  cards.forEach(function (card) {
    var search = card.getAttribute('data-search').toLowerCase();
    var cardContainer = card.closest('.group-module-option');
    var checkbox = jQuery(cardContainer).find('.form-check-input');

    // Check if the card should be displayed based on search input
    var shouldDisplay = search.includes(input);

    // Further filter based on activeModulesChecked state
    if (activeModulesChecked && checkbox.length) {
      shouldDisplay = shouldDisplay && checkbox.is(':checked');
    }

    // Show or hide the card container based on the combined criteria
    cardContainer.style.display = shouldDisplay ? 'block' : 'none';
  });
}

jQuery(document).on('click', '#wpext_clear_button', function () {
  // Hide the clear button
  jQuery(this).hide();
  jQuery('#module_listing .group-module-option').css('display', 'block');
  // Remove the value from the search module input
  jQuery('#wpext_searchmodule').val('');
});

/*Module search js end here*/

document.addEventListener('DOMContentLoaded', function () {
  // Check if the current URL contains the specified page
  currentURL = window.location.href;
  var urlObject = new URL(currentURL);

  // Get the value of the 'page' parameter
  var pageValue = urlObject.searchParams.get('page');
  if (
    (pageValue != '' && pageValue == 'wp-extended') ||
    pageValue == 'wp-extended-settings-tools' ||
    pageValue == 'wp-extended-settings-page'
  ) {
    // Get the sidebar and main elements
    var sidebar = document.querySelector('.sticky-sidebar-main');
    var main = document.querySelector('.sticky-sidebar-layout');

    // Get the initial position of the sidebar
    var sidebarPosition = sidebar.getBoundingClientRect().top;

    // Add scroll event listener
    window.addEventListener('scroll', function () {
      // Check if the scroll position surpasses the sidebar's initial position
      if (window.pageYOffset > sidebarPosition) {
        // Add sticky class to the sidebar
        sidebar.classList.add('sticky-sidebar');

        // Calculate the height of the main element
        var mainHeight = main.getBoundingClientRect().height;

        // Remove sticky class after main height is exceeded
        if (window.pageYOffset > sidebarPosition + mainHeight) {
          sidebar.classList.remove('sticky-sidebar');
        }
      } else {
        // Remove sticky class if the scroll position is above the sidebar's initial position
        sidebar.classList.remove('sticky-sidebar');
      }
    });
  }
});

window.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('#wp-extended-app');
  if (typeof container === 'undefined' || !container) {
    return;
  }
  container.addEventListener('change', function (event) {
    if (event.target.dataset.all_wpext_modules == 'all_wpext_modules') {
      return;
    }
    const input = event.target,
      mod = input.dataset.module,
      status = input.checked ? 1 : 0;

    if (!mod) {
      return;
    }
    const data = new FormData();
    data.append('action', 'wp-extended-module-toggle');
    data.append('module', mod);
    data.append('status', status);
    // Add the nonce to the FormData
    data.append('nonce', wpext_extended_obj.ajax_nonce);
    input.disabled = true;
    // Use the localized ajax_url
    fetch(wpext_extended_obj.ajax_url, {
      method: 'POST',
      cache: 'no-cache',
      body: data,
    }).then((response) => {
      if (!response.ok) {
        alert('Something went wrong');
        return;
      }
      response.json().then((result) => {
        if (!result.status) {
          alert(result.error || 'Failed');
          return;
        }
        input.disabled = false;
        input.checked = result.module_status;
        if (result.module_status == true) {
          let message = '<strong>Activated</strong>';
          jQuery('#liveToast').removeClass('bg-danger');
          jQuery('#liveToast').addClass('popup');
          jQuery('.toast').toast('show');
          jQuery('.toast-body').html(result.module_info.name + ' ' + message);
          jQuery('#' + result.module).show();
          jQuery('#' + result.module).removeClass('wpext_hide');
          jQuery('#' + result.module).addClass('wpext_show');
        } else {
          let message = '<strong>Deactivated</strong>';
          jQuery('#liveToast').removeClass('popup');
          jQuery('#liveToast').addClass('bg-danger');
          jQuery('.toast').toast('show');
          jQuery('#' + result.module).hide();
          jQuery('.toast-body').html(result.module_info.name + ' ' + message);
        }
      });
    });
  });
});

jQuery(document).ready(function () {
  // Function to toggle visibility of .item.list-group-item and .item.grid-group-item based on the reset all checkbox state
  function toggleItemsVisibility() {
    var activeModulesChecked = jQuery('#wpext_active_modules').is(':checked');
    var activeModuleSlug = jQuery(
      '#wp-extended-app .wpext_tab .tablinks.active',
    )
      .attr('onclick')
      .match(/'([^']+)'/)[1];

    jQuery('.item.list-group-item, .item.grid-group-item').each(function () {
      var checkbox = jQuery(this).find('.form-check-input');
      var itemClassList = jQuery(this).attr('class');

      if (activeModuleSlug === 'AllModulesTab') {
        // Show or hide all items based on activeModulesChecked
        if (activeModulesChecked) {
          if (checkbox.length && !checkbox.is(':checked')) {
            jQuery(this).hide();
          } else {
            jQuery(this).show();
          }
        } else {
          jQuery(this).show();
        }
      } else {
        if (activeModulesChecked) {
          // Only show items with checked checkboxes if activeModulesChecked is true
          if (checkbox.length && !checkbox.is(':checked')) {
            jQuery(this).hide();
          } else {
            if (itemClassList.includes(activeModuleSlug)) {
              jQuery(this).show();
            } else {
              jQuery(this).hide();
            }
          }
        } else {
          // Show only items matching the activeModuleSlug
          if (itemClassList.includes(activeModuleSlug)) {
            jQuery(this).show();
          } else {
            jQuery(this).hide();
          }
        }
      }
    });
  }

  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('page') === 'wp-extended') {
    // Attach the change event listener to the Active Modules checkbox
    jQuery('#wpext_active_modules').change(function () {
      var activeModulesChecked = jQuery(this).is(':checked');
      localStorage.setItem('wpext_active_modules', activeModulesChecked);
      toggleItemsVisibility();
    });

    jQuery('#wp-extended-app .wpext_tab .tablinks').click(function () {
      toggleItemsVisibility();
    });
  }

  // Delay the visibility toggle when the page reloads
  setTimeout(function () {
    toggleItemsVisibility();
  }, 100);

  jQuery('#wpext_active_modules').on('change', function () {
    var isChecked = this.checked;
    jQuery.ajax({
      url: wpext_extended_obj.ajax_url,
      type: 'post',
      data: {
        action: 'wpext_active_modules',
        status: isChecked ? 'true' : 'false',
      },
      success: function (response) {},
    });
  });
});
/*Hide show active module */
jQuery(document).ready(function ($) {
  // Function to toggle visibility of the container based on checkboxes' state
  function toggleActiveModulesContainer() {
    var $checkboxes = $(
      '.wpext_toggle_settings .card-footer input[type="checkbox"]',
    );
    var $activeModulesContainer = $(
      '.wpext_toggle_settings .wpext_active_modules_container',
    );
    var anyChecked = $checkboxes.is(':checked');
    if (anyChecked) {
      $activeModulesContainer.show();
    } else {
      $activeModulesContainer.hide();
    }
  }
  $('.wpext_toggle_settings .card-footer input[type="checkbox"]').click(
    function () {
      toggleActiveModulesContainer();
    },
  );

  // Initial call to set the correct visibility based on checkbox states
  toggleActiveModulesContainer();
});
/*Hide show active module end here */
