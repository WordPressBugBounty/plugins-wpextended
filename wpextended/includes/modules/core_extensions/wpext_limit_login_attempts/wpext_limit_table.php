<?php

if ( ! defined( 'ABSPATH' ) ) {
  die();
}
global $wpdb;
$failed_record = $wpdb->prefix.self::WPEXT_LOGIN_ATTEMPT_FAILED;
$log_block_time = $wpdb->get_results("SELECT * FROM ".$failed_record." WHERE status = '1' ORDER BY id DESC");
if(!empty($log_block_time)){ ?>
<div class="container bg-white text-dark p-3"> 
<table id="wpext_blocked_user_list" class="display table is-striped dataTable responsive nowrap">
<thead>
    <tr class="wpext_export_posts block_list-head">
        <th class="module-name text-left"><?php esc_html_e('Username', WP_EXTENDED_TEXT_DOMAIN); ?></th>
        <th class="module-name text-center"><?php esc_html_e('IP', WP_EXTENDED_TEXT_DOMAIN); ?></th>
        <th class="module-name text-center"><?php esc_html_e('Date', WP_EXTENDED_TEXT_DOMAIN); ?></th>
        <th class="module-name text-center"><?php esc_html_e('Lockout Time', WP_EXTENDED_TEXT_DOMAIN); ?></th>
    </tr>
</thead>
<tbody>
<?php 
 foreach ($log_block_time as $value) { ?>
    <tr class="wpext_export_posts block_list item-list" id="<?php echo esc_attr($value->id); ?>">
        <td><?php echo esc_html($value->username); ?></td>
        <td class="text-center"><?php echo esc_html($value->ip); ?></td>
        <td class="text-center">
        <?php  
         $date_formate = get_option('date_format');
         $time_formate = get_option('time_format');
         echo esc_html(date($date_formate . ' ' . $time_formate, strtotime($value->date)));
        ?>
        </td>
        <td class="module-name has-text-centered text-center"><?php echo esc_html($value->locktime); ?> <?php esc_html_e(' minutes', WP_EXTENDED_TEXT_DOMAIN); ?></td>
    </tr>
    <?php }?>
</tbody>
</table>
</div>
<?php } ?>

