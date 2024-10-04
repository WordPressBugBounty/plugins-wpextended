<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'wp-extend-module-listing.php'; 
require_once plugin_dir_path( __FILE__ ) . 'old/wpext_snippets.php'; 

// Initialize the plugin
class Wp_Extended_Snippets extends Custom_List_Table {
   public function __construct() {
        add_action('init', array($this, 'wpext_register_snippet_post_type'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'wpext_admin_scripts_snippets'));
        add_action('wp_enqueue_scripts', array($this, 'wpext_frontend_scripts_snippets'));
        if(!isset($_GET['wpext_safe_mode']) || $_GET['wpext_safe_mode'] != '1') {
            add_action('wp_head', array($this, 'wpext_output_snippets_in_head'));
            add_action('wp_footer', array($this, 'wpext_output_snippets_in_footer'));
            add_action('init', array($this, 'wpext_output_snippets_in_admin_head'), 120);
        }
        add_action('admin_footer', array($this, 'wpext_output_snippets_in_admin_footer'));
        add_action('wp_ajax_wpext_update_snippet_status', array($this, 'wpext_update_snippet_status'));
        add_action('wp_ajax_wpext_delete_code_snippet', array($this, 'wpext_delete_code_snippet'));
        add_action('wp_ajax_handle_snippet_update', array($this, 'wpext_handle_snippet_update'));
        add_action('wp_ajax_save_snippet', array($this, 'wpext_create_snippet_page'));
        add_action('wp_ajax_nopriv_save_snippet', array($this, 'wpext_create_snippet_page')); // For non logged-in users
        add_action('admin_init', array($this, 'wpext_website_safe_mode_callback'));
        add_action('admin_notices', array($this, 'wpext_show_safe_mode_notice'));
    }
   
    public function wpext_admin_scripts_snippets($hook) {
        wp_enqueue_script('jquery');
        if (in_array($hook, [
            'snippets_page_wp-extended-add-snippet',
            'snippets_page_wp-extended-snippets',
            'snippets_page_wp-extended-edit-snippet',
            'wp-extended_page_wp-extended-snippets',
            'admin_page_wp-extended-edit-snippet',
            'admin_page_wp-extended-add-snippet',
            'admin_page_wp-extended-snippets',
            'wp-extended_page_wp-extended-snippets'
        ])) {
            wp_enqueue_style('wpext_snippets_css', plugin_dir_url(__FILE__) . "css/wpext_snippets.css", array());
            wp_enqueue_script('snippet-action', plugin_dir_url(__FILE__) . '/js/wpext-snippets.js', array('jquery'), false, true);
            wp_enqueue_script('snippet-css-hints', plugin_dir_url(__FILE__) . '/js/csslint.js', array('jquery'), false, true);
            wp_enqueue_script('snippet-js-hints', plugin_dir_url(__FILE__) . '/js/jshint.js', array('jquery'), false, true);
            $ajax_nonce = wp_create_nonce('update_snippet_status');
            $ajax_url = admin_url('admin-ajax.php');
            wp_localize_script('snippet-action', 'wp_ajax_object', array('ajax_url' => $ajax_url, 'ajax_nonce' => $ajax_nonce));
            // Enqueue CodeMirror
            wp_enqueue_script('wp-codemirror');
            wp_enqueue_style('wp-codemirror');
            wp_enqueue_script( 'htmlhint' );
            wp_enqueue_script( 'csslint' );
            wp_enqueue_script( 'jshint' );

            // Enqueue code editor
            $settings = wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            wp_localize_script('snippet-action', 'codeEditorSettings', $settings);
        }
    }

    public function wpext_register_snippet_post_type() {
        register_post_type('snippet', array(
            'labels' => array(
                'name' => __('Snippets'),
                'singular_name' => __('Snippet'),
                'add_new' => __('Add New'),
                'add_new_item' => __('Add New Snippet'),
                'edit_item' => __('Edit Snippet'),
                'new_item' => __('New Snippet'),
                'view_item' => __('View Snippet'),
                'search_items' => __('Search Snippets'),
                'not_found' => __('No snippets found'),
                'not_found_in_trash' => __('No snippets found in Trash')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'wp-extended',
            'capability_type' => 'post',
            'supports' => array('title', 'editor'),
            'menu_position' => 100,
            'rewrite' => false,
        ));
    }

    public function admin_menu() {
        $menustatus = get_option('wpext_show_plugin_menu_action');
        $wpext_admin_menu_favorite = get_option('wpext_admin_menu_favorite');
        $flagfavorite = false;
        if (!empty($wpext_admin_menu_favorite)) {
          if (array_key_exists('wpext_snippets', $wpext_admin_menu_favorite)) {
            if ($wpext_admin_menu_favorite['wpext_snippets'] == 'true') {
              $flagfavorite = true;
            }
          }
        }
        if((isset($menustatus) && $menustatus == 'true') && !empty($flagfavorite) ) {
         add_submenu_page('wp-extended', __('Code Snippets', 'wp-extended-snippets'), __('Code Snippets', 'wp-extended-snippets'), 'manage_options', 'wp-extended-snippets', array($this, 'list_snippets_page'));
        }else{
          $capability = 'manage_options';
          $slug = 'wp-extended-snippets';
          $callback = [ $this, 'list_snippets_page'];
          add_submenu_page('', '', '', $capability, $slug, $callback);
          add_rewrite_rule('^wp-extended-snippets/?', 'index.php?wp_extended_snippets=1', 'top');
          add_rewrite_tag('%wp_extended_snippets%', '([^&]+)');
        }  
        add_submenu_page('', __('Add New Snippet', 'wp-extended-snippets'), __('Add New Snippet', 'wp-extended-snippets'), 'manage_options', 'wp-extended-add-snippet', array($this, 'wpext_add_snippet_page'));
        add_submenu_page('', __('Edit Snippet', 'wp-extended-snippets'), __('Edit Snippet', 'wp-extended-snippets'), 'manage_options', 'wp-extended-edit-snippet', array($this, 'edit_snippet_page'));
    }

    public function list_snippets_page() { 

        $snippets = new WP_Query(array( 'post_type' => 'snippet', 'posts_per_page' => -1 )); ?>
        <div class="position-fixed top-0 end-0 p-3 license_status">
           <div id="liveToast" class="toast bg-danger fade hide" role="alert" aria-live="assertive" aria-atomic="true">
              <div class="toast-header">
                 <div class="toast-body"><strong></strong></div>
              </div>
           </div>
         </div>
        <!-- Header button -->
        <?php $this->wpext_header_button(); ?>
        <!-- Header button end here -->
        <div class="container wpext-container wpext_code_snippet wpext_code_snippet_layout" id="wp-extended-app">
            <div class="row">
                <div class="col-sm-12 gx-5 p-0 rounded-2">
                    <div class="container text-dark rounded-2">
                        <?php
                        $codes = get_option('wpext-snippets', '[]' );
                        // Check if the retrieved option is an array or a JSON string
                        if (is_array($codes)) {
                            $codes_array = $codes;
                        } else {
                            $codes_array = json_decode($codes, true);
                        }
                        if (!empty($codes_array) && (!isset($_GET['wpext_safe_mode']) || $_GET['wpext_safe_mode'] != '1')) {
                        ?>
                        <div class="container wpext-container px-0">
                            <div class="row">
                                <div class="wpext-help-message rounded-2">
                                    <span>ⓘ <?php _e('Important!', WP_EXTENDED_TEXT_DOMAIN); ?> </span>
                                    <?php _e('Welcome to the new code snippets module. To find your existing code snippets please', WP_EXTENDED_TEXT_DOMAIN); ?>
                                    <a href="<?php echo admin_url('admin.php?page=wp-extended-snippets-old'); ?>" class="wp_brand_sub_header_back_document" target="_blank">
                                    <?php _e('click here', WP_EXTENDED_TEXT_DOMAIN); ?></a>
                                </div>
                                
                            </div>
                        </div>
                        <?php } elseif (isset($_GET['page']) && $_GET['page'] === 'wp-extended-snippets' && isset($_GET['wpext_safe_mode']) && $_GET['wpext_safe_mode'] === '1') { ?>
                        <div class="container wpext-container px-0">
                            <div class="row">
                                <div class="wpext_notice_message rounded-2 px-0">
                                    <div class="wpext-success-message rounded-2"><span>✓ Success</span>
                                    <?php _e('Safe mode is active due to an error in all PHP snippet. All custom PHP code has been temporarily disabled.', WP_EXTENDED_TEXT_DOMAIN); ?>
                                </div>
                            </div>

                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 gx-5 mb-3 p-0">
                <div id="snippet_del_message" class="d-none">
                    <div class="wpext-success-message rounded-2">
                    <span>ⓘ <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN); ?></span> <?php _e('1 Code Snippets deleted.', WP_EXTENDED_TEXT_DOMAIN ); ?></div>
                </div>
                <div class="wpext_activationerror px-0"></div>
                <?php
                $display_toc = new Custom_List_Table();
                $del = '';
                $snippet_del = $display_toc->process_bulk_action($del);
                if(!empty($snippet_del) && $snippet_del == 1){
                    $this->wpext_show_snippets_deleted_notice();
                }
               ?>

                <div class="container text-dark p-4 bg-white border rounded-2">
                    <div class="row">
                        <?php $display_toc->custom_list_table_page(); ?>
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php  }

    public function wpext_create_snippet_page() {
        if (isset($_POST['action']) && $_POST['action'] == 'save_snippet') {
            check_admin_referer('save_snippet', 'wpext_snippet_nonce');
            $snippet_name = isset($_POST['snippet_name']) ? sanitize_text_field($_POST['snippet_name']) : '';
            
            $snippet_code_type = isset($_POST['snippet_code_type']) ? sanitize_text_field($_POST['snippet_code_type']) : '';
            $snippet_position = isset($_POST['snippet_position']) ? sanitize_text_field($_POST['snippet_position']) : '';
            if(isset($snippet_code_type) && $snippet_code_type == 'PHP'){
                $snippet_position = 'head';
            }else{
               $snippet_position = $snippet_position; 
            }
            $snippet_code_sesc = isset($_POST['snippet_code_sesc']) ? sanitize_text_field($_POST['snippet_code_sesc']) : '';
            $snippet_active = isset($_POST['snippet_active']) ? sanitize_text_field($_POST['snippet_active']) : '';
            $snippet_code = isset($_POST['snippet_code']) ? wp_unslash($_POST['snippet_code']) : "<?php echo 'Hello World'; ?>";
            if ($snippet_code_type == 'PHP') {
                $syntax_error = $this->check_php_syntax_error($snippet_code);
                if ($syntax_error) {
                    wp_send_json_error(['message' => $syntax_error]);
                } 
            }
            try {
                // Check if a snippet with the same title already exists
                $args = array(
                    'post_type' => 'snippet',
                    'post_status' => 'any',
                    'name' => $snippet_name,
                    'posts_per_page' => 1,
                );
                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    $existing_post = $query->post;
                    $post_id = $existing_post->ID;
                    $post_update_result = wp_update_post(array(
                        'ID'            => $post_id,
                        'post_title'    => $snippet_name,
                        'post_content'  => $snippet_code,
                        'post_status'   => 'publish',
                        'post_type'     => 'snippet'
                    ));

                    if (is_wp_error($post_update_result)) {
                        wp_send_json_error(['message' => $post_update_result->get_error_message()]);
                    }

                    update_post_meta($post_id, 'snippet_position', $snippet_position);
                    update_post_meta($post_id, 'snippet_code_type', $snippet_code_type);
                    update_post_meta($post_id, 'snippet_active', $snippet_active);
                    update_post_meta($post_id, 'snippet_code_sesc', $snippet_code_sesc);
                    wp_send_json_success(['message' => __('Snippet updated successfully.', WP_EXTENDED_TEXT_DOMAIN)]);
                } else {
                    // If no existing post found, create a new one
                    $post_id = wp_insert_post(array(
                        'post_title'   => $snippet_name,
                        'post_content' => $snippet_code,
                        'post_status'  => 'publish',
                        'post_type'    => 'snippet'
                    ));

                    if (is_wp_error($post_id)) {
                        wp_send_json_error(['message' => $post_id->get_error_message()]);
                    }

                    update_post_meta($post_id, 'snippet_position', $snippet_position);
                    update_post_meta($post_id, 'snippet_code_type', $snippet_code_type);
                    update_post_meta($post_id, 'snippet_active', $snippet_active);
                    update_post_meta($post_id, 'snippet_code_sesc', $snippet_code_sesc);

                    wp_send_json_success(['message' => __('Snippet created successfully.', WP_EXTENDED_TEXT_DOMAIN)]);
                }

                // Restore global post data
                wp_reset_postdata();

            } catch (Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }

        exit;
    }

public function wpext_add_snippet_page() { ?>
<?php $this->wpext_header_button(); ?>
<div class="container wpext-container">
    <div class="row">
        <div class="wpext_add_new_mainsection p-0 rounded-2">
         <div id="wpext_notice_message"></div>
      </div>
        <div class="wpext_add_new_mainsection gx-5 mb-3 bg-white p-0 border rounded-2">
            <div class="container">
                <form method="post" action="" class="wpext_add_newsnippets" id="wpext_save_snippet_form">
                    <?php wp_nonce_field('save_snippet', 'wpext_snippet_nonce'); ?>
                    <div class="wrap addnew">
                        <div class="container">
                            <div class="wpext_code_snippet_body" id="poststuff">
                                <div class="row justify-content-between wpext_add_snippet_body">
                                    <div class="wpext_snippet_top_section d-flex mb-3">
                                        <div class="col-3">
                                            <h2 class="wpext_snippet_title"> 
                                                <?php 
                                                    $selected_option = isset($_GET['selected_option']) ? sanitize_text_field($_GET['selected_option']) : ''; 
                                                    echo esc_html($selected_option); 
                                                ?>
                                            </h2>
                                            <?php self::wpext_custom_css_content($selected_option); ?>
                                            <input type="hidden" name="snippet_position" value="<?php echo $selected_option; ?>" id="snippet_code_type">
                                        </div>
                                        <div class="col-9 d-flex wpext_snippet_position_action">
                                            <?php if(!empty($selected_option) && $selected_option != 'PHP') { ?>
                                            <div id="wpext_login_action">
                                                <label for="snippet_position"><?php _e('Display In', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                <select name="snippet_position" id="snippet_position">
                                                    <option value="head"><?php _e('Head', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                                                    <option value="footer"><?php _e('Footer', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                                                </select>
                                            </div>
                                            <?php } ?>
                                            <div class="field form-check form-switch form-switch-md d-flex align-items-center gap-5 wpext_action_switch">
                                                <label for="snippet_active" class="wpext_module_status"><?php _e('Enable', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                <input type="checkbox" name="snippet_active" id="snippet_active" class="form-check-input addnew_snippet_active">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="post-body-content">
                                        <div class="wpext_snippet_short_desc mb-3">
                                            
                                            <div id="titlewrap" class="wpext_snippet_code_title mb-3">
                                                <label class="screen-reader-text" for="snippet_name"><?php _e('Enter title here', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                <input type="text" name="snippet_name" size="30" value="" spellcheck="true" autocomplete="off" placeholder="<?php _e('Snippet Title', WP_EXTENDED_TEXT_DOMAIN); ?>" class="form-control wpext_smtp_config_from  wpext_snippet_name mb-2" required>
                                            </div>
                                            
                                            <div class="wpext_snippet_description mb-2">
                                                <div class="inside">
                                                    <textarea name="snippet_code_sesc" id="snippet_code_sesc" class="w-100 large-text code-editor" placeholder="<?php _e('Description', WP_EXTENDED_TEXT_DOMAIN ); ?>" rows="4"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Code Editor -->
                                        <div class="code_teaxarea_box postbox mb-0">
                                            <h2 class="hndle ui-sortable-handle"><span><?php _e('', WP_EXTENDED_TEXT_DOMAIN); ?></span></h2>
                                            <div class="inside">
                                                <textarea name="snippet_code" id="snippet_codess" rows="50" class="large-text code-editor"></textarea>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <?php 
                $mode = '';
                if (isset($selected_option) && $selected_option == 'JAVASCRIPT') {
                    $mode = 'text/javascript';
                    }else if(isset($selected_option) && $selected_option == 'PHP'){
                        $mode = 'application/x-httpd-php-open';
                    }else if(isset($selected_option) && $selected_option == 'CSS'){
                        $mode = 'text/css';
                    }
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                        var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                        editorSettings.codemirror = _.extend(
                            {},
                            editorSettings.codemirror,
                            {   
                                mode: '<?php echo $mode; ?>', 
                                indentUnit: 4,
                                indentWithTabs: true,
                                lineNumbers: true,
                                tabSize: 4,
                                lineWrapping: true,
                                autoCloseBrackets: true,
                                gutters: ["CodeMirror-lint-markers", "CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                                lint: true, // Enable linting
                                extraKeys: {
                                    "Ctrl-Space": "autocomplete",
                                    "Ctrl-Q": function(cm) { cm.foldCode(cm.getCursor()); }
                                },
                                foldGutter: true,
                                matchBrackets: true
                            }
                        );
                        if ('<?php echo $selected_option; ?>' === 'JAVASCRIPT') {
                            wp.codeEditor.initialize($('.addnew #snippet_codess'), editorSettings);
                        } else if ('<?php echo $selected_option; ?>' === 'CSS') {
                            wp.codeEditor.initialize($('.addnew #snippet_codess'), editorSettings);
                        } else {
                            // Initialize editor without additional linting for other modes
                           wp.codeEditor.initialize($('.addnew #snippet_codess'), editorSettings);
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</div>
</div>
 <?php  }

    public function edit_snippet_page() {
        $snippet_id = isset($_GET['snippet_id']) ? intval($_GET['snippet_id']) : 0;
        $snippet = get_post($snippet_id);
        if (!$snippet || $snippet->post_type !== 'snippet') {
            wp_die(__('Invalid snippet', 'wp-extended-snippets'));
        }
        $snippet_position = get_post_meta($snippet_id, 'snippet_position', true);
        $snippet_code_type = get_post_meta($snippet_id, 'snippet_code_type', true);

    ?>
     
    <!-- Header button -->
    <?php $this->wpext_header_button(); ?>
    <!-- Header button end here -->

    <div class="container wpext-container wpext_update_section">
        <div class="row">
            <div class="wpext_add_new_mainsection p-0 rounded-2">
             <div id="wpext_notice_message"></div>
          </div>
            <div class="wpext_add_new_mainsection gx-5 mb-3 bg-white p-0 border rounded-2">
                <div class="container">
                    <div class="wrap">
                        <form method="post" action="" class="wpext_add_newsnippets" id="wpext_save_snippet_form">
                            <?php wp_nonce_field('save_snippet', 'wpext_snippet_nonce'); ?>
                            <div class="wrap addnew_edit">
                                <div class="container">
                                    <div class="wpext_code_snippet_body" id="poststuff">
                                        <div class="row justify-content-between wpext_add_snippet_body">
                                            <div class="wpext_snippet_top_section d-flex mb-3">
                                                <div class="col-3">
                                                    <h2 class="wpext_snippet_title"> <?php echo $snippet_code_type; ?></h2>
                                                    <?php self::wpext_custom_css_content($snippet_code_type); ?>
                                                    <input type="hidden" name="snippet_position" value="<?php if(!empty($snippet_code_type)){ echo $snippet_code_type; } ?>" id="snippet_code_type">
                                                </div>
                                                <div class="col-9 d-flex wpext_snippet_position_action">
                                                    <?php if(!empty($snippet_code_type) && $snippet_code_type != 'PHP') { ?>
                                                    <div id="wpext_login_action">
                                                        <label for="snippet_position"><?php _e('Display In', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                        <select name="snippet_position" id="snippet_position">
                                                            <option value="head" <?php selected($snippet_position, 'head'); ?>><?php _e('Head', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                                                            <option value="footer" <?php selected($snippet_position, 'footer'); ?>><?php _e('Footer', WP_EXTENDED_TEXT_DOMAIN); ?></option>
                                                        </select>
                                                    </div>
                                                    <?php } ?>
                                                    <div class="field form-check form-switch form-switch-md d-flex align-items-center gap-5 wpext_action_switch">
                                                        <?php $_snippet_active = get_post_meta($snippet_id, 'snippet_active', true); ?>
                                                        <label for="snippet_active" class="wpext_module_status"><?php _e('Enable', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                        <input type="checkbox" class="form-check-input"  name="snippet_active" id="snippet_active" <?php checked($_snippet_active, 1); ?>>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="post-body-content">
                                                <div class="wpext_snippet_short_desc mb-3">
                                                    <div id="titlewrap" class="wpext_snippet_code_title mb-3">
                                                        <label class="screen-reader-text" for="snippet_name"><?php _e('Enter title here', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                                        <input type="text" name="snippet_name" size="30" value="<?php echo esc_attr($snippet->post_title); ?>" spellcheck="true" autocomplete="off" placeholder="<?php _e('Snippet Title', 'wp-extended-snippets'); ?>" class="form-control wpext_smtp_config_from snippet_name mb-2" required>
                                                    </div>
                                                    <div class="wpext_snippet_description mb-2">
                                                        <div class="inside">
                                                            <?php $code_snipet_desc = get_post_meta($snippet_id, 'snippet_code_sesc', true); ?>
                                                            <textarea name="snippet_code_sesc" id="snippet_code_sesc" class="w-100 large-text code-editor" rows="4" placeholder="Description"><?php echo esc_textarea($code_snipet_desc); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Code Editor -->
                                                <div class="code_teaxarea_box postbox mb-0">
                                                    <h2 class="hndle ui-sortable-handle"><span><?php _e('', WP_EXTENDED_TEXT_DOMAIN); ?></span></h2>
                                                    <div class="inside">
                                                        <textarea name="snippet_code" id="snippet_code" rows="50" cols="50" class="w-100 large-text code-editor"><?php echo htmlspecialchars($snippet->post_content); ?></textarea>
                                                        <input type="hidden" name="wpext_snippet_id" value="<?php echo $snippet_id; ?>" id="snippet_id">
                                                    </div>
                                                </div>
                                                <!-- Code Editor -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php
     
                    $mode = '';
                    if (isset($snippet_code_type) && $snippet_code_type == 'JAVASCRIPT') {
                        $mode = 'text/javascript';
                    } else if (isset($snippet_code_type) && $snippet_code_type == 'PHP') {
                        $mode = 'application/x-httpd-php-open';
                    } else if (isset($snippet_code_type) && $snippet_code_type == 'CSS') {
                        $mode = 'text/css';
                    }
                    ?>
                    <script>
                        jQuery(document).ready(function($) {
                            var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                            editorSettings.codemirror = _.extend(
                                {},
                                editorSettings.codemirror,
                                {   
                                    mode: '<?php echo $mode; ?>', 
                                    indentUnit: 4,
                                    indentWithTabs: true,
                                    lineNumbers: true,
                                    tabSize: 4,
                                    lineWrapping: true,
                                    autoCloseBrackets: true,
                                    gutters: ["CodeMirror-lint-markers", "CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                                    lint: true, // Enable linting
                                    extraKeys: {
                                        "Ctrl-Space": "autocomplete",
                                        "Ctrl-Q": function(cm) { cm.foldCode(cm.getCursor()); }
                                    },
                                    foldGutter: true,
                                    matchBrackets: true
                                }
                            );
                            if ('<?php echo $snippet_code_type; ?>' === 'JAVASCRIPT') {
                                wp.codeEditor.initialize($('#snippet_code'), editorSettings);
                            } else if ('<?php echo $snippet_code_type; ?>' === 'CSS') {
                                 wp.codeEditor.initialize($('#snippet_code'), editorSettings);
                            } else {
                                // Initialize editor without additional linting for other modes
                                wp.codeEditor.initialize($('#snippet_code'), editorSettings);
                            }
                        });
                    </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php  }
    public function wpext_output_snippets_in_head() {
        $this->wpext_output_snippets('head', 'frontend');
    }

    public function wpext_output_snippets_in_footer() {
        $this->wpext_output_snippets('footer', 'frontend');
    }

    public function wpext_output_snippets_in_admin_head() {
        if ($this->wpext_should_output_snippets('head', 'admin')) {
           $this->wpext_output_snippets('head', 'admin', 'PHP');
        }
    }
    public function wpext_output_snippets_in_admin_footer() {
        if ($this->wpext_should_output_snippets('footer', 'admin')) {
            $this->wpext_output_snippets('footer', 'admin', 'PHP');
        }
    }
    private function wpext_should_output_snippets($pagenow, $position) {
        if (is_admin()) {
            // Exclude the edit post and post-new pages
            if (in_array($pagenow, ['post.php', 'post-new.php'])) {
                return false;
            }
            // Additional check for the specific snippets page
            if ($pagenow === 'admin.php' && isset($_GET['page']) && sanitize_text_field($_GET['page']) === 'wp-extended-snippets') {
                return false;
            }
            // Check the post type in the global post variable
            global $post;
            if (isset($post) && $post->post_type !== 'snippet') {
                return false;
            }
            return true;
        }
        return false;
    }

    private function wpext_output_snippets($position, $context, $allowed_code_type = 'null') {
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__FILE__) . '/');
        }
        $snippets = new WP_Query(array(
            'post_type' => 'snippet',
            'meta_key' => 'snippet_position',
            'meta_value' => $position,
            'posts_per_page' => -1
        ));
        if ($snippets->have_posts()) {
            while ($snippets->have_posts()) {
                $snippets->the_post();
                $scope = get_post_meta(get_the_ID(), 'snippet_scope', true);
                $snippet_active = get_post_meta(get_the_ID(), 'snippet_active', true);
                $code = get_the_content();
                $code_type = get_post_meta(get_the_ID(), 'snippet_code_type', true);
                if ($snippet_active != 1) {
                    continue;  
                }
               if ($code_type === 'PHP' && (!isset($_POST['wpext_update_flag']) || $_POST['wpext_update_flag'] != 'update_token')) {
                    eval($code);
                } else if ($code_type === 'CSS' && $context === 'frontend') {
                    echo "<style type='text/css' id='wpextended-css-".get_the_ID()."'>" . $code . "</style>";
                } else if ($code_type === 'JAVASCRIPT' && $context === 'frontend') {
                    echo "<script type='text/javascript'  id='wpextended-js-".get_the_ID()."'>" . $code . "</script>";
                } else if ($code_type === 'CSS' && $context === 'admin') {
                    add_action('admin_head', function() use ($code) {
                       // echo "<style>" . $code . "</style>";
                    });
                } else if ($code_type === 'JAVASCRIPT' && $context === 'admin') {
                    add_action('admin_head', function() use ($code) {
                        //echo "<script>" . $code . "</script>";
                    });
                }
                if ($scope === 'once') {
                    update_post_meta(get_the_ID(), 'snippet_executed', true);
                }
            }
        }
        wp_reset_postdata();
    }
    public function wpext_update_snippet_status() {
        check_ajax_referer('update_snippet_status', 'security');
        $snippet_title = sanitize_text_field($_POST['snippet_title']);
        $snippet_id = intval($_POST['snippet_id']);
        $snippet_active = get_post_meta($snippet_id, 'snippet_active', true);
        if (!current_user_can('edit_post', $snippet_id)) {
            wp_send_json_error('You do not have permission to edit this snippet.');
            return;
        }
        $post = get_post($snippet_id);
        if($post) {
        $snippet_code = $post->post_content;
        $snippet_code_type = get_post_meta($snippet_id, 'snippet_code_type', true);
        if(isset($snippet_code_type) && $snippet_code_type == 'PHP' && $_POST['wpext_update_flag'] == 'status_token' && $snippet_active != 1){
            $syntax_error = $this->check_php_syntax_error($snippet_code);
            if ($syntax_error) {
            wp_send_json_error(['message' => $syntax_error]);
            } 
        }
        try {
            $code_snippet_title = __('Code Snippets ', WP_EXTENDED_TEXT_DOMAIN);
            $snippet_active = intval($_POST['snippet_active']);
        if($snippet_active == 1){
            $action = __('Activated', WP_EXTENDED_TEXT_DOMAIN);
        }else{
            $action = __('Deactivated', WP_EXTENDED_TEXT_DOMAIN);
        }
        if (update_post_meta($snippet_id, 'snippet_active', $snippet_active)) {
            wp_send_json_success(array('success' => $snippet_active, 'snippet_title' => $code_snippet_title, 'action' => $action));
        } else {
            wp_send_json_error('Failed to update snippet status.');
        }

        } catch (Exception $e) {
            // Catch any other potential errors
            wp_send_json_error(['message' => $e->getMessage()]);
        }
     }
    }

    public function wpext_delete_code_snippet() {
        check_ajax_referer('update_snippet_status', 'security');
        if ( get_post_type( $_POST['post_id'] ) != 'snippet' ) {
             wp_send_json_error('Post type is not "snippet".');
             return;
        }
        $post_id = intval($_POST['post_id']);

        if (wp_trash_post($post_id)) {
            $snippet_del = __('Post deleted successfully.', WP_EXTENDED_TEXT_DOMAIN);
            echo wp_send_json_success($snippet_del);
        } else {
            $snippet_del_failed = __('Failed to delete post.', WP_EXTENDED_TEXT_DOMAIN);
            wp_send_json_error($snippet_del_failed);
        }
        die;
    }

    public static function wpext_header_button(){ 
          $snippetList = new WP_List_Table();
          $items = $snippetList->get_items(); 
          ?>
        <!-- Header button -->
        <div class="container-fluid wpe_brand_header">
            <div class="container p-4 ps-2">
                <h4 class="text-white ps-1 m-0 wpe_brand_header_title wpext_code_snippet_title"><?php _e('WP Extended Code Snippets', WP_EXTENDED_TEXT_DOMAIN); ?><sub>Beta</sub></h4>
            </div>
        </div>
        <div class="container-fluid wp_brand_sub_header">
            <div class="container">
                <div class="row align-items-baseline">
                    <div class="col-lg-6 px-1">
                        <p class="wp_brand_sub_header_left"><a href="<?php echo get_admin_url(); ?>admin.php?page=wp-extended" class="wp_brand_sub_header_back_link">&#x2190; <?php _e('Back to Modules', WP_EXTENDED_TEXT_DOMAIN ); ?></a> | <a href="https://wpextended.io/module_resources/smtp-email/" class="wp_brand_sub_header_back_document" target="_blank">
                    <?php _e('Documentation', WP_EXTENDED_TEXT_DOMAIN);?></a></p>
                </div>
                <div class="col-lg-6 wp_brand_sub_header_right mx-lg-0 px-2">
                    <div class="wpext-return-module-wrap wpext_snippet_action">
                        <?php if(isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wp-extended-snippets') { ?>
                        <button class="page-title-action wp-ext-btn-prim" ><?php _e('Add New', WP_EXTENDED_TEXT_DOMAIN);?></button>
                        <?php } ?>
                        <?php if(isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wp-extended-add-snippet') { ?>
                        <a href='<?php echo admin_url('admin.php?page=wp-extended-snippets'); ?>' class="wp-ext-btn-sec"><?php _e('Back to Snippets', WP_EXTENDED_TEXT_DOMAIN);?></a>
                        <button class="wpext_module_action wp-ext-btn-prim wpext_save_snippet" ><?php _e('Save', WP_EXTENDED_TEXT_DOMAIN);?></button>
                        <?php }
                        if(isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wp-extended-edit-snippet') { ?>
                        <a href='<?php echo admin_url('admin.php?page=wp-extended-snippets'); ?>' class="wp-ext-btn-sec"><?php _e('Back to Snippets', WP_EXTENDED_TEXT_DOMAIN);?></a>
                        <button class="wpext_module_action wp-ext-btn-prim wpext_updeate_code_snippets" ><?php _e('Save', WP_EXTENDED_TEXT_DOMAIN);?></button>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php }

    public function wpext_handle_snippet_update() {
        ob_start(); 
        $snippet_id = isset($_POST['snippet_id']) ? intval($_POST['snippet_id']) : 0;
        $snippet_name = sanitize_text_field($_POST['snippet_name']);
        $snippet_code = isset($_POST['snippet_code']) ? wp_unslash($_POST['snippet_code']) : "<?php echo 'Hello World'; ?>"; 
        
        $snippet_code_type = sanitize_text_field($_POST['snippet_code_type']);
        $snippet_position = isset($_POST['snippet_position']) ? sanitize_text_field($_POST['snippet_position']) : '';
        if(isset($snippet_code_type) && $snippet_code_type == 'PHP'){
            $snippet_position = 'head';
        }else{
           $snippet_position = $snippet_position; 
        }
        $snippet_code_sesc = sanitize_text_field($_POST['snippet_code_sesc']);
        $snippet_active = sanitize_text_field($_POST['snippet_active']);
        if(isset($snippet_code_type) && $snippet_code_type == 'PHP' && $_POST['wpext_update_flag'] == 'update_token'){
            $syntax_error = $this->check_php_syntax_error($snippet_code);
            if ($syntax_error) {
            wp_send_json_error(['message' => $syntax_error]);
            } 
        }
        try {
             
            $post_id = wp_update_post(array(
                'ID' => $snippet_id,
                'post_title' => $snippet_name,
                'post_content' => $snippet_code
            ));
            if (is_wp_error($post_id)) {
                wp_send_json_error(array('message' => $post_id->get_error_message()));
            }
            if ($post_id !== 0) {
                // Update meta fields
                update_post_meta($post_id, 'snippet_position', $snippet_position);
                
                update_post_meta($post_id, 'snippet_code_type', $snippet_code_type);
                update_post_meta($post_id, 'snippet_code_sesc', $snippet_code_sesc);
                update_post_meta($post_id, 'snippet_active', $snippet_active);
                wp_send_json_success(array('message' => __('Snippet updated successfully.', WP_EXTENDED_TEXT_DOMAIN)));
            } else {
                wp_send_json_error(array('message' => __('Failed to update snippet.', WP_EXTENDED_TEXT_DOMAIN)));
            }
        } catch (Exception $e) {
            // Catch any other potential errors
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        ob_end_clean(); 
        wp_die();
    }

    public function wpext_custom_css_content($code){ 
        if(!empty($code) && $code == 'PHP'){  
            echo '<style type="text/css"> .wpext_add_newsnippets .CodeMirror-sizer:before {  content: "<?php"; padding-bottom: 5px; opacity: 0.5; } </style>';
            }else if(!empty($code) && $code == 'CSS'){  
                echo '<style type="text/css"> .wpext_add_newsnippets .CodeMirror-sizer:before { content: "<style>"; padding-bottom: 5px; opacity: 0.5; } </style>';
            }else if(!empty($code) && $code == 'HTML'){  
                echo '<style type="text/css"> .wpext_add_newsnippets .CodeMirror-sizer:before { content: "<HTML>"; padding-bottom: 5px; opacity: 0.5; } </style>';
            }else{ 
                echo '<style type="text/css"> .wpext_add_newsnippets .CodeMirror-sizer:before { content: "<script>"; padding-bottom: 5px; opacity: 0.5; } </style>';  
        }
    }

    private function check_php_syntax_error($snippet_code) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        $error = null;

        // Define a custom error handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        // Define a custom shutdown function to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
                // This is a fatal error, return a ParseError-like message
                return 'Fatal error detected on line';
                echo 'Fatal error detected on line ' . $error['line'] . ' in file ' . $error['file'] . ': ' . $error['message'];
            }
        });
        try {
            // Attempt to parse the code to check for syntax errors
            $tokens = @token_get_all('<?php ' . $snippet_code);
            if ($tokens === false) {
                return 'Syntax error detected: Invalid PHP code.';
            }
            ob_start();
            eval($snippet_code);
            ob_end_clean();
        } catch (ParseError $e) {
            return 'Syntax error detected on line ' . $e->getLine() . ': ' . $e->getMessage();
        } catch (ErrorException $e) {
            return 'Runtime error detected on line ' . $e->getLine() . ': ' . $e->getMessage();
        } catch (Throwable $e) { 
            return 'Error detected on line ' . $e->getLine();
        } finally {
            // Restore the original error handler
            restore_error_handler();
        }

        return false;
    }

    public function wpext_website_safe_mode_callback(){
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'wp-extended-snippets') {
            if (isset($_GET['wpext_safe_mode']) && $_GET['wpext_safe_mode'] === '1') {
                $this->wpext_deactivate_all_snippets();
            }
        }
    }
    function wpext_deactivate_all_snippets() {
        if (current_user_can('manage_options')) {
            $args = array(  'post_type'      => 'snippet', 'posts_per_page' => -1, 'post_status'    => 'publish', );
            $snippets = new WP_Query($args);
            if ($snippets->have_posts()) {
                while ($snippets->have_posts()) {
                    $snippets->the_post();
                    $post_id = get_the_ID();
                    $code_type = get_post_meta($post_id, 'snippet_code_type', true);
                    if(!empty($code_type) && $code_type == 'PHP'){
                     update_post_meta($post_id, 'snippet_active', '0');
                    }
                }
                
                wp_reset_postdata();
            }
        }
    }

    public function wpext_show_snippets_deleted_notice() {
    if ($deleted_count = get_transient('snippets_deleted')) {
         $delete_snippet_message = sprintf(_n('%d Code Snippet deleted.', '%d Code Snippets deleted.', $deleted_count, WP_EXTENDED_TEXT_DOMAIN), $deleted_count);
      ?>
          <div class="wpext-success-message rounded-2">
            <span>ⓘ <?php _e('Success!', WP_EXTENDED_TEXT_DOMAIN ); ?></span>  <?php echo $delete_snippet_message; ?> </div>
        <?php
        }
    }


}

new Wp_Extended_Snippets();
