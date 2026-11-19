<?php
if (!defined('ABSPATH')) exit;

/**
 * Versions up to 1.2.8.3 used the short prefix "xeo_". WordPress.org asks for
 * prefixes of at least four characters, so everything is now "xsotp_". This
 * carries an existing site's saved settings over once, so updating never
 * resets anyone's configuration. It is safe to run repeatedly: it only moves
 * a value when the old option exists, and never overwrites a new one.
 *
 * Not migrated, on purpose:
 *  - the trusted-device cookie: customers are asked for a login OTP once more
 *    on their next password login, then get a new cookie under the new name;
 *  - the per-order "_xeo_email_verified" flag: the order screens still read
 *    it as a fallback (see XSOTP_Order_Column), so existing orders keep their
 *    Verified/Unverified badge without rewriting order data.
 */
function xsotp_migrate_legacy_data() {
    $options = [
        'otp_mode',
        'disable_password_login',
        'skip_checkout_logged_in',
        'trusted_device_days',
        'checkout_mode',
        'delete_data_on_uninstall',
    ];
    foreach ($options as $name) {
        $old = get_option('xeo_' . $name, null);
        if ($old === null) continue;
        if (get_option('xsotp_' . $name, null) === null) {
            add_option('xsotp_' . $name, $old);
        }
        delete_option('xeo_' . $name);
    }
    delete_option('xeo_db_version');

    // The old hourly cleanup event would otherwise stay scheduled forever
    // with nothing listening to it.
    wp_clear_scheduled_hook('xeo_cleanup_otps');

    // Keep each admin's dismissed-notice choices.
    global $wpdb;
    $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time rename of this plugin's own user-meta key.
        $wpdb->usermeta,
        ['meta_key' => 'xsotp_dismissed_notices'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        ['meta_key' => 'xeo_dismissed_notices']    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    );
}
