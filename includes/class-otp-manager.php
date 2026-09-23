<?php
if (!defined('ABSPATH')) exit;

class XEO_OTP_Manager {

    public static function generate($email, $purpose) {
        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp';
        $wpdb->delete($table, ['email' => $email, 'purpose' => $purpose]);

        $otp     = str_pad(wp_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $now     = current_time('mysql');
        $expires = date('Y-m-d H:i:s', strtotime($now) + XEO_OTP_EXPIRY);

        $wpdb->insert($table, [
            'email'      => sanitize_email($email),
            'otp'        => $otp,
            'purpose'    => sanitize_text_field($purpose),
            'created_at' => $now,
            'expires_at' => $expires,
            'verified'   => 0,
        ]);
        return $otp;
    }

    public static function verify($email, $otp, $purpose) {
        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp';
        $now   = current_time('mysql');

        // Brute-force guard: max 5 failed attempts per email+purpose per 10 minutes.
        $attempt_key = 'xeo_otp_attempts_' . md5($email . '|' . $purpose);
        if ((int) get_transient($attempt_key) >= 5) {
            return false;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email=%s AND otp=%s AND purpose=%s AND verified=0 AND expires_at > %s ORDER BY id DESC LIMIT 1",
            $email, $otp, $purpose, $now
        ));

        if (!$row) {
            set_transient($attempt_key, (int) get_transient($attempt_key) + 1, 10 * MINUTE_IN_SECONDS);
            return false;
        }

        delete_transient($attempt_key);
        $wpdb->update($table, ['verified' => 1], ['id' => $row->id]);
        return true;
    }

    public static function is_verified($email, $purpose) {
        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp';
        $since = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 600);

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email=%s AND purpose=%s AND verified=1 AND created_at > %s",
            $email, $purpose, $since
        ));
    }

    public static $last_mail_error = null;

    public static function send_email($email, $otp, $purpose) {
        $site_name = get_bloginfo('name');
        $subject   = sprintf('[%s] Your OTP Code — %s', $site_name, ucfirst($purpose));

        $labels = ['registration' => 'Registration', 'login' => 'Login', 'checkout' => 'Checkout'];
        $label  = $labels[$purpose] ?? 'Verification';

        $message = '
        <html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <div style="background:#1a1a2e;padding:24px;text-align:center;">
                <h1 style="color:#fff;margin:0;letter-spacing:1px;">Xantel Technologies</h1>
                <p style="color:#aaa;margin:4px 0 0;">Email Verification</p>
            </div>
            <div style="padding:32px;background:#f9f9f9;">
                <h2 style="color:#1a1a2e;">Your ' . $label . ' OTP</h2>
                <p style="color:#555;">Use the following OTP to complete your <strong>' . $label . '</strong>.
                   This code is valid for <strong>2 minutes</strong>.</p>
                <div style="background:#1a1a2e;color:#fff;font-size:38px;font-weight:bold;
                            text-align:center;padding:22px;letter-spacing:12px;
                            border-radius:8px;margin:24px 0;">' . $otp . '</div>
                <p style="color:#e74c3c;font-size:13px;">
                    ⚠️ Do not share this OTP with anyone. Xantel Technologies will never ask for your OTP.
                </p>
                <p style="color:#999;font-size:13px;">If you did not request this, please ignore this email.</p>
            </div>
            <div style="background:#1a1a2e;padding:16px;text-align:center;">
                <p style="color:#aaa;font-size:12px;margin:0;">
                    © ' . date('Y') . ' Xantel Technologies &nbsp;|&nbsp;
                    <a href="https://xanteltech.com" style="color:#aaa;">xanteltech.com</a>
                </p>
            </div>
        </body></html>';

        self::$last_mail_error = null;
        $capture = function ($wp_error) {
            if (is_wp_error($wp_error)) self::$last_mail_error = $wp_error->get_error_message();
        };
        add_action('wp_mail_failed', $capture);
        $sent = wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
        remove_action('wp_mail_failed', $capture);

        return $sent;
    }

    public static function cleanup() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}xantel_email_otp WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        XEO_Trusted_Device::cleanup();
    }
}

if (!wp_next_scheduled('xeo_cleanup_otps')) {
    wp_schedule_event(time(), 'hourly', 'xeo_cleanup_otps');
}
add_action('xeo_cleanup_otps', ['XEO_OTP_Manager', 'cleanup']);
