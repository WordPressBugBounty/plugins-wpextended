<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Custom_List_Table extends WP_List_Table {
    function __construct() {
        parent::__construct(array(
            'post_type'      => 'snippet',
            'singular' => 'item',
            'plural'   => 'items',
            'ajax'     => false
        ));

    }
    function column_default($item, $column_name) {
        switch ($column_name) {
        case 'date':  // Add this case
            return $item['date'];
        default:
            return isset($item[$column_name]) ? $item[$column_name] : '';
        }
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            $item['ID']
        );
    }
    function column_actions($item) {
        $status = '';
        $active = get_post_meta($item['ID'], 'snippet_active', true);
        if (!empty($active) && $active == 1) {
            $status = 'checked';  
        } else {
            $status = '';  
        }
        $checkbox = sprintf(
            '<div class="field form-check form-switch form-switch-md d-flex align-items-center gap-5">
                <input type="checkbox" class= "form-check-input" role="switch" data-snippet-id="%s" %s>
            </div>',
            $item['ID'],
            $status
        );
        return $checkbox;
    }

    function column_name($item) {
        $Edit_url = admin_url('admin.php?page=wp-extended-edit-snippet&snippet_id=' . $item['ID']); 
        $Delete_url = wp_nonce_url(admin_url('admin.php?page=wp-extended-snippets&action=delete&snippet_id=' . $item['ID']));
        $duplicate_url = wp_nonce_url(admin_url('admin.php?page=wp-extended-snippets&action=duplicate&snippet_id=' . $item['ID']), 'duplicate_snippet_' . $item['ID']);

        $actions = array(
            'edit'   => sprintf('<a href="'.$Edit_url.'">Edit</a>', $_REQUEST['page'], 'edit', $item['ID']),
            'delete' => sprintf('<a href="#" wpext-attr-id='.$item['ID'].'>Delete</a>', $_REQUEST['page'], 'delete', $item['ID']),
            'duplicate' => sprintf('<a href="%s">Duplicate</a>', $duplicate_url),
        );

        return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions));
    }
    function get_columns() {
        $columns = array(
            'cb'    => '<input type="checkbox" />',
            'name'  => 'Name',
            'codetype' => 'Type',
            'scope' => 'Description',
            'date'  => 'Modified', 
            'actions' => 'Actions',
        );
        return $columns;
    }
    
    function get_sortable_columns() {
        $sortable_columns = array(
        'name'       => array('name', false),
        'codetype'  => array('Code Type', false),
        'scope'      => array('scope', false),
        'date'       => array('date', false), 
        'actions'     => array('Actions', false),
     );
     return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            // Security check
            check_admin_referer('bulk-snippets');
             // Handle bulk delete action
            $selected = (isset($_REQUEST['item']) && is_array($_REQUEST['item'])) ? $_REQUEST['item'] : array();
             $deleted_count = 0;
            foreach ($selected as $item_id) {
                if (get_post_type($item_id) === 'snippet') {
                    wp_trash_post($item_id);
                    $deleted_count++;
                }
            }
            set_transient('snippets_deleted', $deleted_count, 30);

            $del = true;
            return $del;
        }

        // Handle duplicate action
        if ('duplicate' === $this->current_action() && isset($_GET['snippet_id'])) {
            $nonce = $_REQUEST['_wpnonce'];
            if ( !wp_verify_nonce( $nonce, 'duplicate_snippet_' . sanitize_text_field( $_GET['snippet_id'] ) ) ) {
                die('Security check failed');
            }
            $this->duplicate_snippet( sanitize_text_field($_GET['snippet_id']) );
        }
    }

    function get_data($search = '') {
        $snippets = new WP_Query(array(
            'post_type'      => 'snippet',
            'posts_per_page' => -1,
            'orderby'        => 'title', // Order by title, adjust as needed
            'order'          => 'ASC',   // Ascending order, adjust as needed
        ));
        $data = array();
        if ($snippets->have_posts()) {
            while ($snippets->have_posts()) {
                $snippets->the_post();
                $active = get_post_meta(get_the_ID(), 'snippet_active', true);
                $time_diff = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago';
                    $data[] = array(
                    'ID'    => get_the_ID(),
                    'name'  => get_the_title(),
                    'codetype'  => get_post_meta(get_the_ID(), 'snippet_code_type', true),
                    'scope'  => get_post_meta(get_the_ID(), 'snippet_code_sesc', true),
                    'date'  =>  $time_diff,
                    'actions' => array(get_post_meta(get_the_ID(), 'snippet_active', true), false),
                );
            }
            wp_reset_postdata();
        }
        // Filter data based on search term
        if (!empty($search)) {
            $data = array_filter($data, function ($item) use ($search) {
                return stripos($item['name'], $search) !== false;
            });
        }
        return $data;
    }

    public function current_action() {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] !== -1) {
            return sanitize_key($_REQUEST['action']);
        }
        
        if (isset($_REQUEST['action2']) && $_REQUEST['action2'] !== -1) {
            return sanitize_key($_REQUEST['action2']);
        }
        
        return false;
    }
    function prepare_items() {
        $this->process_bulk_action();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $data = $this->get_data($search);
        // Pagination
        $posts_per_page = get_option('posts_per_page');
        if(!empty($posts_per_page)){
         $per_page     = $posts_per_page;
        }else{
           $per_page = 5;   
        }
        $current_page = $this->get_pagenum();
        $total_items  = count($data);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));
        // Sorting
        if (!empty($_REQUEST['orderby']) && isset($sortable[$_REQUEST['orderby']])) {
            $order = ($_REQUEST['order'] === 'asc') ? SORT_ASC : SORT_DESC;
            $data = $this->sort_data($data, $_REQUEST['orderby'], $order);
        }
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->items = $data;
    }
    function sort_data($data, $orderby, $order) {
        usort($data, function ($a, $b) use ($orderby, $order) {
            $result = strcmp($a[$orderby], $b[$orderby]);
            return ($order === SORT_DESC) ? -$result : $result;
        });
        return $data;
    }

    public function get_items() {
      return $this->items;
    }

    function custom_list_table_page() {
            $custom_list_table = new Custom_List_Table();
            $custom_list_table->prepare_items();
            $data = $custom_list_table->items; // Get the data items
            ?>
            <div class="wrap">
                <?php 
                $items = $custom_list_table->get_items(); ?>
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) ); ?>">
                    <?php $custom_list_table->search_box('Search Snippets', 'search_id'); ?>
                </form>
                <form method="post">
                    <?php 
                    $items = $custom_list_table->get_items(); 
                    if (!empty($items)) {
                        $custom_list_table->display();
                        wp_nonce_field('bulk-snippets');
                        self::popup_after_existing_items();  
                    } ?>
                </form>
            </div>
            <?php  
            $items = $custom_list_table->get_items();   
            if (empty($items)) { 
                self::wpex_first_step_snippet_button();  
            }
        
    }

    public function wpex_first_step_snippet_button(){ 
    ?>
        <div class="container wpext-container wpext_snippet_steps">
            <div class="col-sm-12 bg-white p-lg-5 border rounded-2 text-center">
                <h1 class="modal-title fs-5 pb-3"><?php _e("You haven't yet setup any code snippets.", WP_EXTENDED_TEXT_DOMAIN); ?></h1>
                <button type="button" class="btn wp-ext-btn-prim wpext_snippet_popup" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                <?php _e('Get Started', WP_EXTENDED_TEXT_DOMAIN); ?>
                </button>
                <!-- Modal -->
                <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <h1 class="modal-title fs-5" id="staticBackdropLabel"><?php _e('Select the code type', WP_EXTENDED_TEXT_DOMAIN ); ?></h1>
                                <div class="row">
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio('PHP', this);">
                                        <input type="radio" name="wpext_snippet_option" value="PHP" id="PHP">
                                        <label for="PHP"><?php _e('PHP', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio('JAVASCRIPT', this); ">
                                        <input type="radio" name="wpext_snippet_option" value="JAVASCRIPT" id="JAVASCRIPT">
                                        <label for="JAVASCRIPT"><?php _e('JS', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio('CSS', this); ">
                                        <input type="radio" name="wpext_snippet_option" value="CSS" id="CSS">
                                        <label for="CSS"><?php _e('CSS', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="<?php echo home_url(); ?>/wp-admin/admin.php?page=wp-extended-add-snippet"
                                    class="btn wp-ext-btn-prim wpext_snippet_popup" id="wpext_goto_snippet" onclick="appendRadioValue()">
                                    <?php _e('Get Started', WP_EXTENDED_TEXT_DOMAIN); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php 
     }

    public function popup_after_existing_items(){ ?>
        <div class="container wpext-container wpext_snippet_steps" id="wpext_existing_popup" style="height:0px;">
            <div class="col-sm-12 p-lg-5 text-center">
                <button type="button" class="btn wp-ext-btn-prim d-none wpext_snippet_popup" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                <?php _e('Get Started', WP_EXTENDED_TEXT_DOMAIN); ?>
                </button>
                <!-- Modal -->
                <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <h1 class="modal-title fs-5" id="staticBackdropLabel"><?php _e('Select the code type', WP_EXTENDED_TEXT_DOMAIN ); ?></h1>
                                <div class="row">
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio_existing('PHP', this);">
                                        <input type="radio" name="wpext_snippet_option" value="PHP" id="PHP">
                                        <label for="PHP"><?php _e('PHP', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio_existing('JAVASCRIPT', this); ">
                                        <input type="radio" name="wpext_snippet_option" value="JAVASCRIPT" id="JAVASCRIPT">
                                        <label for="JAVASCRIPT"><?php _e('JS', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                    <div class="col-sm radio-container rounded-2" onclick="selectRadio_existing('CSS', this); ">
                                        <input type="radio" name="wpext_snippet_option" value="CSS" id="CSS">
                                        <label for="CSS"><?php _e('CSS', WP_EXTENDED_TEXT_DOMAIN); ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="<?php echo home_url(); ?>/wp-admin/admin.php?page=wp-extended-add-snippet"
                                    class="btn wp-ext-btn-prim wpext_snippet_popup" id="wpext_goto_snippet" onclick="appendRadioValue()">
                                    <?php _e('Get Started', WP_EXTENDED_TEXT_DOMAIN); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <?php 
    }

    function display() {
        $this->display_tablenav('top');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
                <tr>
                    <?php $this->print_column_headers(); ?>
                </tr>
            </thead>
            <tbody id="the-list"<?php
                if ($this->has_items()) {
                echo ' class="has-items"';
                }
                ?>>
                <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
            <tfoot>
            <!-- Remove the footer -->
            </tfoot>
        </table>
        <?php
        // $this->display_tablenav('bottom'); // This line can be removed to hide bulk actions
    }

    function duplicate_snippet($id) {
        $post = get_post($id);

        if (isset($post) && $post != null) {
            $new_post = array(
                'post_title'    => $post->post_title . ' (Duplicate)',
                'post_content'  => $post->post_content,
                'post_status'   => 'draft',
                'post_type'     => $post->post_type,
                'post_author'   => get_current_user_id(),
            );

            $new_post_id = wp_insert_post($new_post);

            // Duplicate post meta
            $post_meta = get_post_meta($post->ID);

            foreach ($post_meta as $key => $value) {
                if($key == "snippet_active"){
                    $snippetActiveValue = 0;
                    update_post_meta($new_post_id, $key, maybe_unserialize($snippetActiveValue));
                }else{
                    update_post_meta($new_post_id, $key, maybe_unserialize($value[0]));
                }
            }

            // Construct the URL and escape it
            $redirect_url = admin_url('admin.php?page=wp-extended-edit-snippet&snippet_id=' . $new_post_id);
            
            echo '<script type="text/javascript">
                 window.location.href = "' . $redirect_url . '";
              </script>';
            exit;
        }
    }
}

 