<?php
// If uninstall.php is not called by WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The hourly cleanup event must never be left behind once the plugin is
// gone, whether or not the admin chose to wipe data.
wp_clear_scheduled_hook('xsotp_cleanup_otps');

// Default is to PRESERVE data on delete, so a reinstall picks up where it
// left off. Only wipe the database if the admin explicitly opted in via
// Settings → Secure OTP → "On Plugin Deletion".
if (!get_option('xsotp_delete_data_on_uninstall')) {
    return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping this plugin's own tables on uninstall; table names are $wpdb->prefix plus fixed literals.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp_verified_users");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp_trusted_devices");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}xantel_email_otp_log");
// phpcs:enable

delete_option('xsotp_otp_mode');
delete_option('xsotp_disable_password_login');
delete_option('xsotp_skip_checkout_logged_in');
delete_option('xsotp_trusted_device_days');
delete_option('xsotp_checkout_mode');
delete_option('xsotp_delete_data_on_uninstall');
delete_option('xsotp_db_version');

// Leftovers from before the prefix rename (1.2.8.3 and earlier), if any.
foreach (['otp_mode', 'disable_password_login', 'skip_checkout_logged_in', 'trusted_device_days', 'checkout_mode', 'delete_data_on_uninstall', 'db_version'] as $xsotp_legacy) {
    delete_option('xeo_' . $xsotp_legacy);
}
