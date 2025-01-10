<?php

if (! defined('ABSPATH')) {
    die();
}
global $wpdb;

$login_failed = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT_FAILED;
$failed_query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($login_failed));

/*Create login failed table*/

if (! $wpdb->get_var($failed_query) == $login_failed) {
    $charset_collate = $wpdb->get_charset_collate();
    $login_failed_sql = "CREATE TABLE $login_failed (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `username` varchar(100) NOT NULL,
    `ip` varchar(45) NOT NULL,
    `country` varchar(60)  NULL,
    `redirect_to` varchar(255)  NULL,
    `status` tinyint(1) NOT NULL DEFAULT 0,
    `locktime` int(11) NOT NULL DEFAULT 30,
    `locklimit` int(11) NOT NULL DEFAULT 3,
    `date` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY  (id),
    KEY ip (ip),
    KEY status (status)
)$charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($login_failed_sql);
}

/*Create login count table*/

$login_attempt = $wpdb->prefix . self::WPEXT_LOGIN_ATTEMPT;
$attempt_query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($login_attempt));

if (! $wpdb->get_var($attempt_query) == $login_attempt) {
    $charset_collate = $wpdb->get_charset_collate();
    $login_attempt_sql = "CREATE TABLE $login_attempt (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `attempt` int(11) NOT NULL DEFAULT 1,
        `ip` varchar(45) NOT NULL,
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `date` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY  (id),
        KEY ip (ip),
        KEY status (status)
    )$charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($login_attempt_sql);
}
