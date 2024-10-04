document.addEventListener('DOMContentLoaded', function () {
  function addCreateNewPostButton() {
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

      console.log('Adding new post button');

      // Get dynamic values
      const postType = wp.data.select('core/editor').getCurrentPostType();

      // Check if the custom button already exists
      if (document.querySelector('.wpext-button-create')) {
        return;
      }

      // Create your custom button
      const customButton = document.createElement('a');
      customButton.innerHTML = 'New';
      customButton.className =
        'components-button is-secondary wpext-button-create';
      customButton.href = `/wp-admin/post-new.php?post_type=${postType}`;

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
  addCreateNewPostButton();
});
