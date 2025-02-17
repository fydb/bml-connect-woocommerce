<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('woocommerce_bml_connect_settings');

// Drop custom tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bml_connect_transactions");

// Clear any stored logs
$log_dir = WP_CONTENT_DIR . '/bml-connect-logs';
if (is_dir($log_dir)) {
    array_map('unlink', glob("$log_dir/*.*"));
    rmdir($log_dir);
}