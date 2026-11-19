<?php
if (!defined('ABSPATH')) exit;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- This plugin owns custom tables. Table names are $wpdb->prefix plus a fixed literal (never user input) and every value goes through $wpdb->prepare() or $wpdb->insert/update/delete.

class XSOTP_OTP_Manager {

    public static function generate($email, $purpose) {
        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp';
        $wpdb->delete($table, ['email' => $email, 'purpose' => $purpose]);

        $otp     = str_pad(wp_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $now     = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', strtotime($now) + XSOTP_OTP_EXPIRY);

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
        $attempt_key = 'xsotp_otp_attempts_' . md5($email . '|' . $purpose);
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
        $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 600);

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

        // This email goes to the STORE's customers, so it carries the store's
        // own name — not the plugin author's branding or links.
        $name_html = esc_html($site_name);
        $message = '
        <html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <div style="background:#1a1a2e;padding:24px;text-align:center;">
                <h1 style="color:#fff;margin:0;letter-spacing:1px;">' . $name_html . '</h1>
                <p style="color:#aaa;margin:4px 0 0;">Email Verification</p>
            </div>
            <div style="padding:32px;background:#f9f9f9;">
                <h2 style="color:#1a1a2e;">Your ' . esc_html($label) . ' OTP</h2>
                <p style="color:#555;">Use the following OTP to complete your <strong>' . esc_html($label) . '</strong>.
                   This code is valid for <strong>2 minutes</strong>.</p>
                <div style="background:#1a1a2e;color:#fff;font-size:38px;font-weight:bold;
                            text-align:center;padding:22px;letter-spacing:12px;
                            border-radius:8px;margin:24px 0;">' . esc_html($otp) . '</div>
                <p style="color:#e74c3c;font-size:13px;">
                    ⚠️ Do not share this OTP with anyone. ' . $name_html . ' will never ask for your OTP.
                </p>
                <p style="color:#999;font-size:13px;">If you did not request this, please ignore this email.</p>
            </div>
            <div style="background:#1a1a2e;padding:16px;text-align:center;">
                <p style="color:#aaa;font-size:12px;margin:0;">© ' . esc_html(gmdate('Y')) . ' ' . $name_html . '</p>
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

    public static function log($level, $context, $email, $message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'xantel_email_otp_log', [
            'created_at' => current_time('mysql'),
            'level'      => $level,   // 'info' | 'warning' | 'error'
            'context'    => $context, // 'registration' | 'login' | 'checkout'
            'email'      => $email,
            'message'    => $message,
        ]);
    }

    public static function cleanup() {
        global $wpdb;
        // Cutoffs are computed in the same clock (WordPress local time) the
        // rows were stamped with, rather than the database server's NOW(),
        // which can be in a different timezone.
        $now      = strtotime(current_time('mysql'));
        $otp_cut  = gmdate('Y-m-d H:i:s', $now - HOUR_IN_SECONDS);
        $log_cut  = gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}xantel_email_otp WHERE expires_at < %s", $otp_cut));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}xantel_email_otp_log WHERE created_at < %s", $log_cut));
        XSOTP_Trusted_Device::cleanup();
    }
}

// The hourly cleanup event itself is scheduled on activation (and re-checked
// on version upgrade) and cleared on deactivation — see the main plugin file.
add_action('xsotp_cleanup_otps', ['XSOTP_OTP_Manager', 'cleanup']);
