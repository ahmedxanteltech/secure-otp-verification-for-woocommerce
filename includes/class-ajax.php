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

        if (!is_email($email))
            wp_send_json_error(['message' => 'Please enter a valid email address.']);

        if (!in_array($purpose, ['registration', 'login', 'checkout'], true))
            wp_send_json_error(['message' => 'Invalid request.']);

        if ($purpose === 'login') {
            $user = get_user_by('email', $email) ?: get_user_by('login', $email);
            if ($user) $email = $user->user_email;
            else wp_send_json_error(['message' => 'No account found with this email or username.']);
        }

        if ($purpose === 'registration' && email_exists($email))
            wp_send_json_error(['message' => 'An account with this email already exists. Please login instead.']);

        // Rate limit: max 3 per 10 min
        global $wpdb;
        $since = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 600);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}xantel_email_otp WHERE email=%s AND purpose=%s AND created_at > %s",
            $email, $purpose, $since
        ));
        if ($count >= 3)
            wp_send_json_error(['message' => 'Too many OTP requests. Please wait 10 minutes.']);

        $otp  = XEO_OTP_Manager::generate($email, $purpose);
        $sent = XEO_OTP_Manager::send_email($email, $otp, $purpose);

        if ($sent) {
            wp_send_json_success(['message' => 'OTP sent to ' . $email . '. Valid for 2 minutes.', 'email' => $email]);
        } else {
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

        if (!is_email($email) || empty($otp) || empty($purpose))
            wp_send_json_error(['message' => 'Invalid request.']);

        if (XEO_OTP_Manager::verify($email, $otp, $purpose)) {
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                do_action('xeo_otp_verified', $user->ID, $email);
            }
            wp_send_json_success(['message' => 'Email verified successfully!']);
        } else {
            wp_send_json_error(['message' => 'Invalid or expired OTP. Please try again.']);
        }
    }
}
