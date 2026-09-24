<?php
if (!defined('ABSPATH')) exit;

class XEO_Ajax {

    public function __construct() {
        foreach (['send_otp', 'verify_otp'] as $action) {
            add_action('wp_ajax_nopriv_xeo_' . $action, [$this, $action]);
            add_action('wp_ajax_xeo_'        . $action, [$this, $action]);
        }
    }

    public function send_otp() {
        check_ajax_referer('xeo_nonce', 'nonce');

        $email   = sanitize_email($_POST['email'] ?? '');
        $purpose = sanitize_text_field($_POST['purpose'] ?? '');

        if (!is_email($email)) {
            XEO_OTP_Manager::log('warning', $purpose ?: 'unknown', $_POST['email'] ?? '', 'Send OTP rejected: not a valid email address.');
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        if (!in_array($purpose, ['registration', 'login', 'checkout'], true)) {
            XEO_OTP_Manager::log('warning', 'unknown', $email, 'Send OTP rejected: invalid/missing purpose.');
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        if ($purpose === 'login') {
            $user = get_user_by('email', $email) ?: get_user_by('login', $email);
            if ($user) {
                $email = $user->user_email;
            } else {
                XEO_OTP_Manager::log('warning', 'login', $email, 'Send OTP rejected: no account found for this email/username.');
                wp_send_json_error(['message' => 'No account found with this email or username.']);
            }
        }

        if ($purpose === 'registration' && email_exists($email)) {
            XEO_OTP_Manager::log('warning', 'registration', $email, 'Send OTP rejected: an account with this email already exists.');
            wp_send_json_error(['message' => 'An account with this email already exists. Please login instead.']);
        }

        // Rate limit: max 3 per 10 min
        global $wpdb;
        $since = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 600);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}xantel_email_otp WHERE email=%s AND purpose=%s AND created_at > %s",
            $email, $purpose, $since
        ));
        if ($count >= 3) {
            XEO_OTP_Manager::log('warning', $purpose, $email, 'Send OTP rejected: rate limit (3 per 10 minutes) reached.');
            wp_send_json_error(['message' => 'Too many OTP requests. Please wait 10 minutes.']);
        }

        $otp  = XEO_OTP_Manager::generate($email, $purpose);
        $sent = XEO_OTP_Manager::send_email($email, $otp, $purpose);

        if ($sent) {
            XEO_OTP_Manager::log('info', $purpose, $email, 'OTP sent successfully.');
            wp_send_json_success(['message' => 'OTP sent to ' . $email . '. Valid for 2 minutes.', 'email' => $email]);
        } else {
            $reason  = XEO_OTP_Manager::$last_mail_error ?: 'wp_mail() returned false with no further detail (often an SMTP plugin/config issue).';
            XEO_OTP_Manager::log('error', $purpose, $email, 'Failed to send OTP: ' . $reason);

            $message = 'Failed to send OTP. Please check your SMTP settings.';
            if (current_user_can('manage_options') && XEO_OTP_Manager::$last_mail_error) {
                $message .= ' [Admin diagnostic: ' . XEO_OTP_Manager::$last_mail_error . ']';
            }
            wp_send_json_error(['message' => $message]);
        }
    }

    public function verify_otp() {
        check_ajax_referer('xeo_nonce', 'nonce');

        $email   = sanitize_email($_POST['email'] ?? '');
        $otp     = sanitize_text_field($_POST['otp'] ?? '');
        $purpose = sanitize_text_field($_POST['purpose'] ?? '');

        if (!is_email($email) || empty($otp) || empty($purpose)) {
            XEO_OTP_Manager::log('warning', $purpose ?: 'unknown', $email, 'Verify OTP rejected: missing email, code, or purpose.');
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        if (XEO_OTP_Manager::verify($email, $otp, $purpose)) {
            XEO_OTP_Manager::log('info', $purpose, $email, 'OTP verified successfully.');
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                do_action('xeo_otp_verified', $user->ID, $email);
            }
            wp_send_json_success(['message' => 'Email verified successfully!']);
        } else {
            XEO_OTP_Manager::log('warning', $purpose, $email, 'OTP verification failed: incorrect, expired, or too many attempts.');
            wp_send_json_error(['message' => 'Invalid or expired OTP. Please try again.']);
        }
    }
}
