<?php
// If uninstall.php is not called by WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp_verified_users");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp_trusted_devices");

delete_option('xeo_otp_mode');
delete_option('xeo_disable_password_login');
delete_option('xeo_skip_checkout_logged_in');
delete_option('xeo_trusted_device_days');

$timestamp = wp_next_scheduled('xeo_cleanup_otps');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'xeo_cleanup_otps');
}
