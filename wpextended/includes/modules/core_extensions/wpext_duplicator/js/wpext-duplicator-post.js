document.addEventListener('DOMContentLoaded', function () {
  function addDuplicatePostButton() {
    function insertButton() {
      // Select the inserter toggle button
      const inserterToggleButton = document.querySelector(
        '.editor-document-tools__inserter-toggle',
      );

      if (!inserterToggleButton) {
        // If the target button is not found, try again after a delay
        setTimeout(insertButton, 500);
        return;
      }

      // Get dynamic values
      const ajaxurl = window.ajaxurl; // AJAX URL provided by WordPress
      const postID = wp.data.select('core/editor').getCurrentPostId();
      const nonce = wpext_post_nonce.wpext_post_nonce;

      // Check if the custom button already exists
      if (document.querySelector('.wpext-button-duplicate')) {
        return;
      }

      // Create your custom button
      const customButton = document.createElement('button');
      customButton.innerHTML = 'Duplicate';
      customButton.className =
        'components-button is-secondary wpext-button-duplicate';

      // Set the onClick handler
      customButton.addEventListener('click', function (event) {
        event.preventDefault();
        const url = `${ajaxurl}?action=wp-extended-duplicate-post&post_ID=${postID}&wpext_nonce=${nonce}`;
        fetch(url)
          .then((response) => response.json())
          .then((data) => {
            console.log(data);
            if (data.status && data.duplicate && data.duplicate.edit_url) {
              window.location.href = data.duplicate.edit_url;
            } else {
              alert('Failed to duplicate post.');
            }
          })
          .catch((error) => {
            console.error('Error duplicating post:', error);
            alert('Error duplicating post.');
          });
      });

      // Insert the custom button before the inserter toggle button
      inserterToggleButton.parentNode.insertBefore(
        customButton,
        inserterToggleButton,
      );
    }

    // Subscribe to changes in the editor state to ensure the button is added when the editor is fully loaded
    wp.data.subscribe(function () {
      setTimeout(insertButton, 1);
    });

    // Initial call to add the button in case the editor is already loaded
    insertButton();
  }

  // Call the functions to add custom buttons
  addDuplicatePostButton();
});
