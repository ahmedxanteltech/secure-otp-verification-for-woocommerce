<?php
/**
 * Plugin Name: Secure OTP Verification for WooCommerce
 * Plugin URI: https://xanteltech.com
 * Description: Secure email OTP verification for WooCommerce — Registration, Login and Checkout.
 * Version: 1.2.9
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Xantel Technologies
 * Author URI: https://xanteltech.com
 * Text Domain: secure-otp-verification-for-woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('XSOTP_VERSION',    '1.2.9');
define('XSOTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XSOTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XSOTP_OTP_EXPIRY', 120); // 2 minutes

// Includes
require_once XSOTP_PLUGIN_DIR . 'includes/legacy-migration.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-otp-manager.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-trusted-device.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-registration.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-login.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-checkout.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-order-column.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-ajax.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-admin.php';
require_once XSOTP_PLUGIN_DIR . 'includes/class-users-column.php';

// Activation — refuse cleanly if WooCommerce isn't active, then create DB tables
register_activation_hook(__FILE__, 'xsotp_activate');
function xsotp_activate() {
    if (!class_exists('WooCommerce') && !in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins', []), true)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'Secure OTP Verification for WooCommerce requires WooCommerce to be installed and active. Please activate WooCommerce first, then activate this plugin again.',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    xsotp_create_tables();
    xsotp_schedule_cleanup();
}

// Hourly cleanup of expired OTPs, old log rows and expired trusted devices.
function xsotp_schedule_cleanup() {
    if (!wp_next_scheduled('xsotp_cleanup_otps')) {
        wp_schedule_event(time(), 'hourly', 'xsotp_cleanup_otps');
    }
}

register_deactivation_hook(__FILE__, 'xsotp_deactivate');
function xsotp_deactivate() {
    wp_clear_scheduled_hook('xsotp_cleanup_otps');
}

function xsotp_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xantel_email_otp (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        otp varchar(10) NOT NULL,
        purpose varchar(20) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        verified tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY email (email),
        KEY purpose (purpose)
    ) $charset;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xantel_email_otp_verified_users (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        email varchar(100) NOT NULL,
        verified_at datetime NOT NULL,
        last_otp_login datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id),
        KEY email (email)
    ) $charset;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xantel_email_otp_trusted_devices (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        token_hash varchar(64) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY token_hash (token_hash)
    ) $charset;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xantel_email_otp_log (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        level varchar(10) NOT NULL,
        context varchar(20) NOT NULL,
        email varchar(100) DEFAULT NULL,
        message text NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset;");
}

// Settings helpers
function xsotp_get_mode() {
    return get_option('xsotp_otp_mode', 'register_login_checkout');
}

function xsotp_is_enabled($feature) {
    $mode = xsotp_get_mode();
    switch ($feature) {
        case 'registration': return true;
        case 'login':        return in_array($mode, ['register_login', 'register_login_checkout']);
        case 'checkout':     return $mode === 'register_login_checkout';
    }
    return false;
}

function xsotp_password_login_disabled() {
    return (bool) get_option('xsotp_disable_password_login', 0);
}

function xsotp_skip_checkout_for_logged_in() {
    return (bool) get_option('xsotp_skip_checkout_logged_in', 1);
}

function xsotp_trusted_device_days() {
    $days = (int) get_option('xsotp_trusted_device_days', 30);
    return $days > 0 ? $days : 30;
}

function xsotp_checkout_mode() {
    $mode = get_option('xsotp_checkout_mode', 'block');
    return $mode === 'flag' ? 'flag' : 'block';
}

// Bootstrap — only runs if WooCommerce is actually active. If WooCommerce
// gets deactivated for any reason (conflict, bad update, admin error), this
// plugin must fail safe (do nothing + tell the admin) rather than fatal-error
// the whole site by calling WooCommerce functions that no longer exist.
add_action('plugins_loaded', function () {
    // Auto-migrate the DB schema when the plugin version changes, since
    // updates here are typically deployed by overwriting files in place
    // rather than deactivating/reactivating (which is what would otherwise
    // trigger the activation hook that creates tables). dbDelta() is safe
    // to re-run — it only adds/adjusts what's missing, never drops data.
    if (get_option('xsotp_db_version') !== XSOTP_VERSION) {
        xsotp_migrate_legacy_data(); // carries settings over from the old "xeo_" prefix (<= 1.2.8.3)
        xsotp_create_tables();
        xsotp_schedule_cleanup(); // also covers installs updated in place, which never re-run activation
        update_option('xsotp_db_version', XSOTP_VERSION);
    }

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Secure OTP Verification for WooCommerce</strong> is inactive because WooCommerce is not active. OTP verification is currently NOT being enforced on registration, login, or checkout. Please reactivate WooCommerce.</p></div>';
        });
        return;
    }

    new XSOTP_Registration();
    new XSOTP_Login();
    new XSOTP_Checkout();
    new XSOTP_Order_Column();
    new XSOTP_Ajax();
    new XSOTP_Admin();
    new XSOTP_Users_Column();
}, 20); // priority 20: run after WooCommerce's own plugins_loaded init (priority 10)

// Declare compatibility with WooCommerce's High-Performance Order Storage
// (HPOS). This plugin never reads/writes order data directly — it only
// hooks the checkout process to require OTP before an order is placed — so
// it has nothing that depends on the legacy post-based order storage.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Front-end assets
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('xsotp-style',  XSOTP_PLUGIN_URL . 'assets/style.css',  [], XSOTP_VERSION);
    wp_enqueue_script('xsotp-script', XSOTP_PLUGIN_URL . 'assets/script.js', ['jquery'], XSOTP_VERSION, true);
    wp_localize_script('xsotp-script', 'xsotp_ajax', [
        'ajax_url'               => admin_url('admin-ajax.php'),
        'nonce'                  => wp_create_nonce('xsotp_nonce'),
        'expiry'                 => XSOTP_OTP_EXPIRY,
        'mode'                   => xsotp_get_mode(),
        'disable_password_login' => xsotp_password_login_disabled() ? '1' : '0',
        'messages'               => [
            'otp_sent'    => 'OTP sent to your email. Valid for 2 minutes.',
            'otp_verified'=> 'Email verified successfully!',
            'otp_invalid' => 'Invalid or expired OTP. Please try again.',
            'otp_resent'  => 'New OTP sent to your email.',
            'enter_otp'   => 'Please enter the OTP sent to your email.',
            'enter_email' => 'Please enter your email address.',
        ],
    ]);
});
