<?php
if (!defined('ABSPATH')) exit;

/**
 * Lets a device skip login OTP after it has already completed one
 * successfully, for an admin-configurable number of days. Scoped to
 * login only — checkout OTP is a separate, always-fresh check.
 *
 * The cookie holds a random token; only its SHA-256 hash is stored in
 * the database, so a leaked/stolen database row can't be replayed as
 * a cookie. Each successful trusted-device login slides the expiry
 * forward, so an active device stays trusted; an idle one falls back
 * to requiring OTP once it passes xeo_trusted_device_days().
 */
class XEO_Trusted_Device {

    const COOKIE_NAME = 'xeo_trusted_device';

    public static function remember($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp_trusted_devices';

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $now   = current_time('mysql');
        $days  = xeo_trusted_device_days();
        $expires = date('Y-m-d H:i:s', strtotime($now) + $days * DAY_IN_SECONDS);

        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'token_hash' => $hash,
            'created_at' => $now,
            'expires_at' => $expires,
        ]);

        self::set_cookie($token, $days);
    }

    public static function is_trusted($user_id) {
        if (empty($_COOKIE[self::COOKIE_NAME])) return false;

        $token = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
        if (!ctype_xdigit($token) || strlen($token) !== 64) return false;

        $hash = hash('sha256', $token);

        global $wpdb;
        $table = $wpdb->prefix . 'xantel_email_otp_trusted_devices';
        $now   = current_time('mysql');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id=%d AND token_hash=%s AND expires_at > %s LIMIT 1",
            $user_id, $hash, $now
        ));

        if (!$row) return false;

        // Sliding expiry: an active device stays trusted.
        $days    = xeo_trusted_device_days();
        $expires = date('Y-m-d H:i:s', strtotime($now) + $days * DAY_IN_SECONDS);
        $wpdb->update($table, ['expires_at' => $expires], ['id' => $row->id]);
        self::set_cookie($token, $days);

        return true;
    }

    public static function forget_all($user_id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'xantel_email_otp_trusted_devices', ['user_id' => $user_id]);
    }

    private static function set_cookie($token, $days) {
        if (headers_sent()) return;
        setcookie(
            self::COOKIE_NAME,
            $token,
            time() + $days * DAY_IN_SECONDS,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }

    public static function cleanup() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}xantel_email_otp_trusted_devices WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
}
