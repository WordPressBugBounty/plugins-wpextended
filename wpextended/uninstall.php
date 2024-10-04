<?php 
global $wpdb;
$status = get_option('wpext_plugin_reset_action');
if(!empty($status) && $status == 'true'){
    $plugin_prefixes = 'wpext%';
    $plugin_prefixes_1 = 'wp-extended%';
    // Get all keys
    global $wpdb; // WordPress database access

    // Delete options.
    $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s", $plugin_prefixes,$plugin_prefixes_1 ) );

    $tables_to_delete = array(
        $wpdb->prefix . 'wpext_login_attempt',
        $wpdb->prefix . 'wpext_login_failed',
        $wpdb->prefix . 'wpext_logs'
    );
    foreach ($tables_to_delete as $table_name) {
        $result = $wpdb->query("DROP TABLE IF EXISTS $table_name");  
        if ($result === false) {
            error_log('Error deleting table ' . $table_name . ': ' . $wpdb->last_error);
        } 
    }

    // Get post IDs of post type "snippet"
    $post_type = 'snippet';
    $post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = %s",
            $post_type
        )
    );

    if (!empty($post_ids)) {
        // Convert post IDs array to a comma-separated string for SQL query
        $post_ids_placeholder = implode(',', array_fill(0, count($post_ids), '%d'));
        
        // Delete associated post meta data first
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->postmeta WHERE post_id IN ($post_ids_placeholder)",
                $post_ids
            )
        );

        // Then delete posts of post type "snippet"
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->posts WHERE ID IN ($post_ids_placeholder)",
                $post_ids
            )
        );
    }
}
?>