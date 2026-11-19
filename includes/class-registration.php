<?php
if (!defined('ABSPATH')) exit;

class XSOTP_Registration {

    public function __construct() {
        add_action('woocommerce_register_form', [$this, 'add_otp_fields']);
        add_filter('woocommerce_registration_errors', [$this, 'validate_otp'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'on_registered']);
    }

    public function add_otp_fields() {
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
           id="xsotp-reg-otp-section" style="display:none;">
            <label for="xsotp_reg_otp"><?php esc_html_e('Email OTP', 'secure-otp-verification-for-woocommerce'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                name="xsotp_reg_otp" id="xsotp_reg_otp"
                placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="off"
                style="letter-spacing:5px;font-size:18px;" />
            <span class="xsotp-otp-timer" id="xsotp-reg-timer"></span>
            <a href="#" class="xsotp-resend-otp" id="xsotp-reg-resend"
               data-purpose="registration" data-email-field="#reg_email" style="display:none;">Resend OTP</a>
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
           id="xsotp-reg-send-btn-row">
            <button type="button" class="woocommerce-Button button xsotp-send-otp-btn"
                data-purpose="registration" data-email-field="#reg_email"
                data-show-input="xsotp-reg-otp-section">
                <?php esc_html_e('Send OTP to Email', 'secure-otp-verification-for-woocommerce'); ?>
            </button>
        </p>
        <input type="hidden" name="xsotp_reg_verified" id="xsotp_reg_verified" value="0" />
        <?php
    }

    public function validate_otp($errors, $username, $email) {
        $otp = sanitize_text_field(wp_unslash($_POST['xsotp_reg_otp'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce core verifies its own form nonce before this hook runs.

        if (empty($otp)) {
            $errors->add('otp_required', __('<strong>Error:</strong> Please verify your email with OTP before registering.', 'secure-otp-verification-for-woocommerce'));
        } elseif (!XSOTP_OTP_Manager::verify($email, $otp, 'registration')) {
            $errors->add('otp_invalid', __('<strong>Error:</strong> Invalid or expired OTP. Please try again.', 'secure-otp-verification-for-woocommerce'));
        }
        return $errors;
    }

    public function on_registered($customer_id) {
        $user = get_userdata($customer_id);
        if ($user) do_action('xsotp_otp_verified', $customer_id, $user->user_email);
    }
}
