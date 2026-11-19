<?php
if (!defined('ABSPATH')) exit;

class XSOTP_Ajax {

    public function __construct() {
        foreach (['send_otp', 'verify_otp'] as $action) {
            add_action('wp_ajax_nopriv_xsotp_' . $action, [$this, $action]);
            add_action('wp_ajax_xsotp_'        . $action, [$this, $action]);
        }
    }

    public function send_otp() {
        check_ajax_referer('xsotp_nonce', 'nonce');

        $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $purpose = sanitize_text_field(wp_unslash($_POST['purpose'] ?? ''));

        if (!is_email($email)) {
            XSOTP_OTP_Manager::log('warning', $purpose ?: 'unknown', sanitize_text_field(wp_unslash($_POST['email'] ?? '')), 'Send OTP rejected: not a valid email address.');
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        if (!in_array($purpose, ['registration', 'login', 'checkout'], true)) {
            XSOTP_OTP_Manager::log('warning', 'unknown', $email, 'Send OTP rejected: invalid/missing purpose.');
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        if ($purpose === 'login') {
            $user = get_user_by('email', $email) ?: get_user_by('login', $email);
            if ($user) {
                $email = $user->user_email;
            } else {
                XSOTP_OTP_Manager::log('warning', 'login', $email, 'Send OTP rejected: no account found for this email/username.');
                wp_send_json_error(['message' => 'No account found with this email or username.']);
            }
        }

        if ($purpose === 'registration' && email_exists($email)) {
            XSOTP_OTP_Manager::log('warning', 'registration', $email, 'Send OTP rejected: an account with this email already exists.');
            wp_send_json_error(['message' => 'An account with this email already exists. Please login instead.']);
        }

        // Rate limit: max 3 sends per 10 min per email + purpose.
        // Counted in a transient, not by counting rows in the OTP table:
        // generate() replaces any earlier code for the same email + purpose,
        // so that table only ever holds one row and a row count can never
        // reach the limit.
        $send_key = 'xsotp_otp_sends_' . md5($email . '|' . $purpose);
        $count    = (int) get_transient($send_key);
        if ($count >= 3) {
            XSOTP_OTP_Manager::log('warning', $purpose, $email, 'Send OTP rejected: rate limit (3 per 10 minutes) reached.');
            wp_send_json_error(['message' => 'Too many OTP requests. Please wait 10 minutes.']);
        }

        set_transient($send_key, $count + 1, 10 * MINUTE_IN_SECONDS);

        $otp  = XSOTP_OTP_Manager::generate($email, $purpose);
        $sent = XSOTP_OTP_Manager::send_email($email, $otp, $purpose);

        if ($sent) {
            XSOTP_OTP_Manager::log('info', $purpose, $email, 'OTP sent successfully.');
            wp_send_json_success(['message' => 'OTP sent to ' . $email . '. Valid for 2 minutes.', 'email' => $email]);
        } else {
            $reason  = XSOTP_OTP_Manager::$last_mail_error ?: 'wp_mail() returned false with no further detail (often an SMTP plugin/config issue).';
            XSOTP_OTP_Manager::log('error', $purpose, $email, 'Failed to send OTP: ' . $reason);

            $message = 'Failed to send OTP. Please check your SMTP settings.';
            if (current_user_can('manage_options') && XSOTP_OTP_Manager::$last_mail_error) {
                $message .= ' [Admin diagnostic: ' . XSOTP_OTP_Manager::$last_mail_error . ']';
            }
            wp_send_json_error(['message' => $message]);
        }
    }

    public function verify_otp() {
        check_ajax_referer('xsotp_nonce', 'nonce');

        $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $otp     = sanitize_text_field(wp_unslash($_POST['otp'] ?? ''));
        $purpose = sanitize_text_field(wp_unslash($_POST['purpose'] ?? ''));

        if (!is_email($email) || empty($otp) || empty($purpose)) {
            XSOTP_OTP_Manager::log('warning', $purpose ?: 'unknown', $email, 'Verify OTP rejected: missing email, code, or purpose.');
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        if (XSOTP_OTP_Manager::verify($email, $otp, $purpose)) {
            XSOTP_OTP_Manager::log('info', $purpose, $email, 'OTP verified successfully.');
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                do_action('xsotp_otp_verified', $user->ID, $email);
            }
            wp_send_json_success(['message' => 'Email verified successfully!']);
        } else {
            XSOTP_OTP_Manager::log('warning', $purpose, $email, 'OTP verification failed: incorrect, expired, or too many attempts.');
            wp_send_json_error(['message' => 'Invalid or expired OTP. Please try again.']);
        }
    }
}
