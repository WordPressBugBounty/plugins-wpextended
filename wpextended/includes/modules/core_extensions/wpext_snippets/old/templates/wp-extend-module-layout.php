<?php
if ( ! defined( 'ABSPATH' ) ) {
die();
}
?>

<form action="options.php" method="post" id="wpext-snippets-codes" class="g-5">
   <?php settings_fields( 'wpext-snippets' ); ?>
   <div class="p-0" id="list-of-snippets">
      <!-- list of codes -->
      <textarea name="wpext-snippets" id="wpext-snippets-codes-json" class="form-control"><?php echo esc_html($codes); ?></textarea>
      <template class="p-3">
      <li>
         <div class="box">
            <div class="row pb-4">
               <div class="col-lg-5">
                  <input type="text" name="xxx-snippet-label[_cnkgvon76]" value="" class="form-control input snippet-label" placeholder="Snippet Name" required>
               </div>
               <div class="col-lg-5">
                  <select name="xxx-snippet-position[_4vmjpkyyr]" class="snippet-position form-select" required="">
                     <option value="head">
                        <?php _e('Header', WP_EXTENDED_TEXT_DOMAIN );?>
                     </option>
                     <option value="footer">
                        <?php _e('Footer', WP_EXTENDED_TEXT_DOMAIN );?>
                     </option>
                  </select>
               </div>
               <div class="col-lg-2">
                  <button type="submit" class="btn btn-danger btn-sm btn-delete" data-alert="<?php _e('Are you sure you want to delete?', WP_EXTENDED_TEXT_DOMAIN );?>">Delete</button>
                 </div>
            </div>
            <div class="mb-3">
               <div class="field snippet-code-section">
                  <div class="control"><textarea name="xxx-snippet-code[]" value="" class="textarea snippet-code is-family-monospace is-size-7 form-control" placeholder="<?php _e("Snippet Code ", WP_EXTENDED_TEXT_DOMAIN );?>" rows="6"></textarea>
                  </div>
               </div>
<!--                <div class="block pt-4 pb-2">
                  <button type="submit" class="button wpext-primary-btn btn-down wpext-snippet-btn"> <?php _e( 'Move Down', WP_EXTENDED_TEXT_DOMAIN); ?></button>
                  <button type="submit" class="button wpext-primary-btn btn-up  wpext-snippet-btn"> <?php _e( 'Move Up', WP_EXTENDED_TEXT_DOMAIN); ?></button>
               </div> -->
            </div>
         </div>
      </li>
      </template>
      <div class="block">
         <ul id="snippets-list" class="p-0"></ul>
      </div>
      <div class="block py-3 snippets-blocks">
         <button type="submit" class="button-primary">
         <?php _e( 'Save', WP_EXTENDED_TEXT_DOMAIN); ?>
         </button>
         <button type="button" class="button" id="add_new_code">
         <?php _e( 'Add New', WP_EXTENDED_TEXT_DOMAIN );?>
         </button>
      </div>
   </div>
   <br>
</form>