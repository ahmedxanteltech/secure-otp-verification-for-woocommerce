<?php
if (!defined('ABSPATH')) exit;

class XEO_Checkout {

    public function __construct() {
        add_action('woocommerce_after_checkout_billing_form', [$this, 'add_otp_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_otp']);
        add_action('woocommerce_checkout_order_processed', [$this, 'tag_order_verification'], 10, 3);
    }

    public function add_otp_fields($checkout) {
        if (!xeo_is_enabled('checkout')) return;
        if (xeo_checkout_mode() === 'flag') return; // soft mode: no field, no friction

        if (is_user_logged_in()) {
            if (xeo_skip_checkout_for_logged_in()) return;

            $user = wp_get_current_user();
            if (XEO_OTP_Manager::is_verified($user->user_email, 'checkout')) {
                echo '<input type="hidden" name="xeo_checkout_verified" value="1" />';
                return;
            }
        }
        ?>
        <div class="xeo-checkout-otp-wrapper" style="margin-top:20px;padding:20px;background:#f8f8f8;border:2px solid #1a1a2e;border-radius:8px;">
            <h3 style="margin-top:0;color:#1a1a2e;">📧 Email Verification</h3>
            <p style="color:#555;margin-bottom:15px;">Please verify your email address before placing your order.</p>

            <div id="xeo-checkout-send-section">
                <button type="button" class="woocommerce-Button button alt xeo-send-otp-btn"
                    data-purpose="checkout" data-email-field="#billing_email">
                    <?php esc_html_e('Send OTP to Email', 'secure-otp-verification-for-woocommerce'); ?>
                </button>
            </div>

            <div id="xeo-checkout-verify-section" style="display:none;margin-top:15px;">
                <label for="xeo_checkout_otp" style="display:block;margin-bottom:5px;font-weight:bold;">
                    <?php esc_html_e('Enter OTP', 'secure-otp-verification-for-woocommerce'); ?> <span style="color:red;">*</span>
                </label>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="text" name="xeo_checkout_otp" id="xeo_checkout_otp"
                        placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="off"
                        style="width:180px;padding:10px;border:2px solid #ddd;border-radius:4px;font-size:18px;letter-spacing:5px;" />
                    <button type="button" class="woocommerce-Button button" id="xeo-checkout-verify-otp"
                        data-purpose="checkout" data-email-field="#billing_email">
                        <?php esc_html_e('Verify', 'secure-otp-verification-for-woocommerce'); ?>
                    </button>
                </div>
                <div style="margin-top:8px;">
                    <span class="xeo-otp-timer" id="xeo-checkout-timer"></span>
                    <a href="#" class="xeo-resend-otp" id="xeo-checkout-resend"
                       data-purpose="checkout" data-email-field="#billing_email" style="display:none;margin-left:10px;">Resend OTP</a>
                </div>
            </div>

            <div id="xeo-checkout-verified-msg" style="display:none;color:#27ae60;font-weight:bold;margin-top:10px;">
                ✅ <?php esc_html_e('Email verified successfully!', 'secure-otp-verification-for-woocommerce'); ?>
            </div>
        </div>
        <input type="hidden" name="xeo_checkout_verified" id="xeo_checkout_verified" value="0" />
        <?php
    }

    public function validate_otp() {
        if (!xeo_is_enabled('checkout')) return;
        if (xeo_checkout_mode() === 'flag') return; // soft mode: never blocks — see tag_order_verification()

        if (is_user_logged_in() && xeo_skip_checkout_for_logged_in()) return;

        $otp   = sanitize_text_field($_POST['xeo_checkout_otp'] ?? '');
        $email = sanitize_email($_POST['billing_email'] ?? '');

        // Source of truth is the DB record from a real AJAX verify — never the
        // client-supplied hidden field, which can be spoofed in a raw POST.
        if (!empty($email) && XEO_OTP_Manager::is_verified($email, 'checkout')) return;

        if (empty($otp)) {
            wc_add_notice(__('Please verify your email with OTP before placing your order.', 'secure-otp-verification-for-woocommerce'), 'error');
            return;
        }
        if (!XEO_OTP_Manager::verify($email, $otp, 'checkout')) {
            wc_add_notice(__('Invalid or expired OTP. Please request a new OTP and try again.', 'secure-otp-verification-for-woocommerce'), 'error');
        }
    }

    /**
     * Records whether this order's email is a known-verified one, regardless
     * of checkout mode: in "block" mode the order could only exist if OTP
     * already passed, so this is a consistency record; in "flag" mode this
     * is the ONLY verification signal — checkout itself never blocked.
     * Uses the WC_Order object (not raw postmeta) so it's HPOS-safe.
     */
    public function tag_order_verification($order_id, $posted_data, $order) {
        if (!xeo_is_enabled('checkout') || !$order instanceof WC_Order) return;

        global $wpdb;
        $vtable = $wpdb->prefix . 'xantel_email_otp_verified_users';
        $email  = $order->get_billing_email();
        $verified = false;

        if (is_user_logged_in()) {
            $verified = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $vtable WHERE user_id=%d", get_current_user_id()
            ));
        }
        if (!$verified && !empty($email)) {
            $verified = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $vtable WHERE email=%s", $email
            ));
        }
        // A checkout OTP just entered in this exact request counts too,
        // even if the account/email has no prior verification on file.
        if (!$verified && !empty($email) && XEO_OTP_Manager::is_verified($email, 'checkout')) {
            $verified = true;
        }

        $order->update_meta_data('_xeo_email_verified', $verified ? 'yes' : 'no');
        $order->save();
    }
}
