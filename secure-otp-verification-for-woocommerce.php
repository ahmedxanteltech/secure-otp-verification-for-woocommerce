<?php
/**
 * Plugin Name: Secure OTP Verification for WooCommerce
 * Plugin URI: https://xanteltech.com
 * Description: Secure email OTP verification for WooCommerce — Registration, Login and Checkout.
 * Version: 1.1.0
 * Author: Xantel Technologies
 * Author URI: https://xanteltech.com
 * Text Domain: secure-otp-verification-for-woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('XEO_VERSION',    '1.1.0');
define('XEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XEO_OTP_EXPIRY', 120); // 2 minutes

// Includes
require_once XEO_PLUGIN_DIR . 'includes/class-otp-manager.php';
require_once XEO_PLUGIN_DIR . 'includes/class-trusted-device.php';
require_once XEO_PLUGIN_DIR . 'includes/class-registration.php';
require_once XEO_PLUGIN_DIR . 'includes/class-login.php';
require_once XEO_PLUGIN_DIR . 'includes/class-checkout.php';
require_once XEO_PLUGIN_DIR . 'includes/class-ajax.php';
require_once XEO_PLUGIN_DIR . 'includes/class-admin.php';
require_once XEO_PLUGIN_DIR . 'includes/class-users-column.php';

// Activation — create DB tables
register_activation_hook(__FILE__, 'xeo_create_tables');
function xeo_create_tables() {
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
}

// Settings helpers
function xeo_get_mode() {
    return get_option('xeo_otp_mode', 'register_login_checkout');
}

function xeo_is_enabled($feature) {
    $mode = xeo_get_mode();
    switch ($feature) {
        case 'registration': return true;
        case 'login':        return in_array($mode, ['register_login', 'register_login_checkout']);
        case 'checkout':     return $mode === 'register_login_checkout';
    }
    return false;
}

function xeo_password_login_disabled() {
    return (bool) get_option('xeo_disable_password_login', 0);
}

function xeo_skip_checkout_for_logged_in() {
    return (bool) get_option('xeo_skip_checkout_logged_in', 1);
}

function xeo_trusted_device_days() {
    $days = (int) get_option('xeo_trusted_device_days', 30);
    return $days > 0 ? $days : 30;
}

// Bootstrap
add_action('plugins_loaded', function () {
    new XEO_Registration();
    new XEO_Login();
    new XEO_Checkout();
    new XEO_Ajax();
    new XEO_Admin();
    new XEO_Users_Column();
});

// Front-end assets
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('xeo-style',  XEO_PLUGIN_URL . 'assets/style.css',  [], XEO_VERSION);
    wp_enqueue_script('xeo-script', XEO_PLUGIN_URL . 'assets/script.js', ['jquery'], XEO_VERSION, true);
    wp_localize_script('xeo-script', 'xeo_ajax', [
        'ajax_url'               => admin_url('admin-ajax.php'),
        'nonce'                  => wp_create_nonce('xeo_nonce'),
        'expiry'                 => XEO_OTP_EXPIRY,
        'mode'                   => xeo_get_mode(),
        'disable_password_login' => xeo_password_login_disabled() ? '1' : '0',
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
