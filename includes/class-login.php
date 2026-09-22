<?php
if (!defined('ABSPATH')) exit;

class XEO_Login {

    public function __construct() {
        add_action('woocommerce_login_form_start', [$this, 'add_login_tabs']);
        add_action('woocommerce_login_form',       [$this, 'add_otp_fields']);
        add_filter('authenticate', [$this, 'maybe_block_password_login'],    25, 3);
        add_filter('authenticate', [$this, 'maybe_block_password_login_wp'], 25, 3);
        add_filter('authenticate', [$this, 'validate_otp'], 30, 3);
        add_action('woocommerce_login_form_end', [$this, 'add_otp_login_form']);
        add_action('wp_loaded', [$this, 'handle_otp_only_login']);
        add_action('wp_login',  [$this, 'on_wp_login'], 10, 2);
    }

    public function add_login_tabs() {
        if (xeo_password_login_disabled() || !xeo_is_enabled('login')) return;
        ?>
        <div class="xeo-login-tabs" style="display:flex;margin-bottom:20px;border-bottom:2px solid #e0e0e0;">
            <button type="button" id="xeo-tab-password" onclick="xeoSwitchTab('password')"
                style="flex:1;padding:12px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:bold;color:#1a1a2e;border-bottom:3px solid #1a1a2e;margin-bottom:-2px;">
                🔑 Password Login
            </button>
            <button type="button" id="xeo-tab-otp" onclick="xeoSwitchTab('otp')"
                style="flex:1;padding:12px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:bold;color:#999;border-bottom:3px solid transparent;margin-bottom:-2px;">
                📧 Login with OTP
            </button>
        </div>
        <?php
    }

    public function add_otp_fields() {
        if (xeo_password_login_disabled() || !xeo_is_enabled('login')) return;
        ?>
        <div id="xeo-password-login-otp-section" style="display:none;">
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="xeo_login_otp"><?php esc_html_e('Email OTP', 'secure-otp-verification-for-woocommerce'); ?> <span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                    name="xeo_login_otp" id="xeo_login_otp"
                    placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="off" />
                <span class="xeo-otp-timer" id="xeo-login-timer"></span>
                <a href="#" class="xeo-resend-otp" id="xeo-login-resend"
                   data-purpose="login" data-email-field="#username" style="display:none;">Resend OTP</a>
            </p>
        </div>
        <p class="woocommerce-form-row form-row" id="xeo-login-send-btn-row">
            <button type="button" class="woocommerce-Button button xeo-send-otp-btn"
                data-purpose="login" data-email-field="#username">
                <?php esc_html_e('Send OTP to Email', 'secure-otp-verification-for-woocommerce'); ?>
            </button>
        </p>
        <input type="hidden" name="xeo_login_verified" id="xeo_login_verified" value="0" />
        <?php
    }

    public function add_otp_login_form() {
        $force      = xeo_password_login_disabled();
        $show_tab   = xeo_is_enabled('login') && !$force;
        $wrap_style = $force ? '' : 'display:none;';
        ?>
        <?php if ($force): ?>
        <div style="background:#fff8e1;border:1px solid #f0c040;border-radius:6px;padding:12px 15px;margin-bottom:20px;font-size:14px;color:#7a6000;">
            🔐 Password login is disabled. Please use <strong>Email OTP</strong> to sign in.
        </div>
        <?php endif; ?>

        <div id="xeo-otp-only-login" style="<?php echo esc_attr($wrap_style); ?>">
            <form method="post" class="woocommerce-form" id="xeo-otp-login-form">
                <?php wp_nonce_field('xeo_otp_login', 'xeo_otp_login_nonce'); ?>
                <input type="hidden" name="xeo_otp_login_action" value="1" />

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="xeo_otp_login_email"><?php esc_html_e('Email Address', 'secure-otp-verification-for-woocommerce'); ?> <span class="required">*</span></label>
                    <input type="email" class="woocommerce-Input woocommerce-Input--text input-text"
                        name="xeo_otp_login_email" id="xeo_otp_login_email"
                        placeholder="Enter your email address" autocomplete="email" />
                </p>

                <p class="form-row" id="xeo-otp-login-send-row">
                    <button type="button" class="woocommerce-Button button <?php echo $force ? 'alt' : ''; ?> xeo-send-otp-btn"
                        data-purpose="login" data-email-field="#xeo_otp_login_email"
                        data-show-input="xeo-otp-login-otp-row">
                        <?php esc_html_e('Send OTP', 'secure-otp-verification-for-woocommerce'); ?>
                    </button>
                </p>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
                   id="xeo-otp-login-otp-row" style="display:none;">
                    <label for="xeo_otp_login_code"><?php esc_html_e('Enter OTP', 'secure-otp-verification-for-woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                        name="xeo_otp_login_code" id="xeo_otp_login_code"
                        placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="off"
                        style="letter-spacing:5px;font-size:20px;" />
                    <span class="xeo-otp-timer" id="xeo-otp-login-timer"></span>
                    <a href="#" class="xeo-resend-otp" id="xeo-otp-login-resend"
                       data-purpose="login" data-email-field="#xeo_otp_login_email" style="display:none;">Resend OTP</a>
                </p>

                <p class="form-row" id="xeo-otp-login-submit-row" style="display:none;">
                    <button type="submit" class="woocommerce-Button button alt" name="xeo_otp_login_submit">
                        <?php esc_html_e('Login with OTP', 'secure-otp-verification-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>

        <?php if ($force): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.querySelector('.woocommerce-form-login');
            if (!form) return;
            Array.from(form.children).forEach(function (el) {
                if (el.tagName !== 'INPUT' && el.id !== 'xeo-otp-only-login') el.style.display = 'none';
            });
            document.getElementById('xeo-otp-only-login').style.display = 'block';
        });
        </script>
        <?php endif;
    }

    public function handle_otp_only_login() {
        if (empty($_POST['xeo_otp_login_action'])) return;
        if (!wp_verify_nonce($_POST['xeo_otp_login_nonce'] ?? '', 'xeo_otp_login')) return;

        $email = sanitize_email($_POST['xeo_otp_login_email'] ?? '');
        $otp   = sanitize_text_field($_POST['xeo_otp_login_code'] ?? '');

        if (!is_email($email)) { wc_add_notice('Please enter a valid email address.', 'error'); return; }
        $user = get_user_by('email', $email);
        if (!$user)            { wc_add_notice('No account found with this email address.', 'error'); return; }

        // No password gates this login path, so trusted-device is never
        // consulted here — a stolen cookie must not be enough on its own
        // to sign in. A fresh OTP is always required.
        if (!XEO_OTP_Manager::verify($email, $otp, 'login')) { wc_add_notice('Invalid or expired OTP.', 'error'); return; }

        do_action('xeo_otp_verified', $user->ID, $email);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);
        wp_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }

    public function maybe_block_password_login($user, $username, $password) {
        if (!xeo_password_login_disabled()) return $user;
        if (!isset($_POST['woocommerce-login-nonce'])) return $user;
        if (is_wp_error($user) || !$user instanceof WP_User) return $user;
        if (!empty($password) && user_can($user, 'manage_options')) return $user;
        return new WP_Error('password_login_disabled',
            __('<strong>Error:</strong> Password login is disabled. Please use the <strong>Login with OTP</strong> tab.', 'secure-otp-verification-for-woocommerce'));
    }

    public function maybe_block_password_login_wp($user, $username, $password) {
        if (!xeo_password_login_disabled()) return $user;
        if (isset($_POST['woocommerce-login-nonce'])) return $user;
        if (is_wp_error($user) || !$user instanceof WP_User) return $user;
        if (user_can($user, 'manage_options')) return $user;
        return new WP_Error('password_login_disabled',
            __('Password login is disabled. Please log in via the store login page using Email OTP.', 'secure-otp-verification-for-woocommerce'));
    }

    public function validate_otp($user, $username, $password) {
        if (xeo_password_login_disabled() || !xeo_is_enabled('login')) return $user;
        if (!isset($_POST['woocommerce-login-nonce'])) return $user;
        if (is_wp_error($user) || !$user instanceof WP_User) return $user;

        if (XEO_Trusted_Device::is_trusted($user->ID)) return $user;

        $otp = sanitize_text_field($_POST['xeo_login_otp'] ?? '');

        if (empty($otp))
            return new WP_Error('otp_required', __('<strong>Error:</strong> Please verify your email with OTP to login.', 'secure-otp-verification-for-woocommerce'));
        if (!XEO_OTP_Manager::verify($user->user_email, $otp, 'login'))
            return new WP_Error('otp_invalid', __('<strong>Error:</strong> Invalid or expired OTP.', 'secure-otp-verification-for-woocommerce'));

        XEO_Trusted_Device::remember($user->ID);
        return $user;
    }

    public function on_wp_login($user_login, $user) {
        $otp = sanitize_text_field($_POST['xeo_login_otp'] ?? '');
        if (!empty($otp)) do_action('xeo_otp_verified', $user->ID, $user->user_email);
    }
}
