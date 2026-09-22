<?php
if (!defined('ABSPATH')) exit;

class XEO_Registration {

    public function __construct() {
        add_action('woocommerce_register_form', [$this, 'add_otp_fields']);
        add_filter('woocommerce_registration_errors', [$this, 'validate_otp'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'on_registered']);
    }

    public function add_otp_fields() {
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
           id="xeo-reg-otp-section" style="display:none;">
            <label for="xeo_reg_otp"><?php esc_html_e('Email OTP', 'secure-otp-verification-for-woocommerce'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                name="xeo_reg_otp" id="xeo_reg_otp"
                placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="off"
                style="letter-spacing:5px;font-size:18px;" />
            <span class="xeo-otp-timer" id="xeo-reg-timer"></span>
            <a href="#" class="xeo-resend-otp" id="xeo-reg-resend"
               data-purpose="registration" data-email-field="#reg_email" style="display:none;">Resend OTP</a>
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
           id="xeo-reg-send-btn-row">
            <button type="button" class="woocommerce-Button button xeo-send-otp-btn"
                data-purpose="registration" data-email-field="#reg_email"
                data-show-input="xeo-reg-otp-section">
                <?php esc_html_e('Send OTP to Email', 'secure-otp-verification-for-woocommerce'); ?>
            </button>
        </p>
        <input type="hidden" name="xeo_reg_verified" id="xeo_reg_verified" value="0" />
        <?php
    }

    public function validate_otp($errors, $username, $email) {
        $otp = sanitize_text_field($_POST['xeo_reg_otp'] ?? '');

        if (empty($otp)) {
            $errors->add('otp_required', __('<strong>Error:</strong> Please verify your email with OTP before registering.', 'secure-otp-verification-for-woocommerce'));
        } elseif (!XEO_OTP_Manager::verify($email, $otp, 'registration')) {
            $errors->add('otp_invalid', __('<strong>Error:</strong> Invalid or expired OTP. Please try again.', 'secure-otp-verification-for-woocommerce'));
        }
        return $errors;
    }

    public function on_registered($customer_id) {
        $user = get_userdata($customer_id);
        if ($user) do_action('xeo_otp_verified', $customer_id, $user->user_email);
    }
}
