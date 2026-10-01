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
        add_action('wp_login',  [$this, 'on_wp_login'], 10, 2);

        // OTP-only login (the password-less tab, and the forced-OTP mode)
        // is handled entirely over AJAX rather than a native <form> submit.
        // Every bug we chased in earlier versions — an invalid nested
        // <form>, a theme's own hidden-but-required fields blocking native
        // HTML5 validation on submit, a homepage popup silently hijacking
        // or mishandling the form submission, a full-page cache serving a
        // stale copy of the page the browser lands on after a server-side
        // redirect — all stemmed from relying on a real form submission
        // inside theme-controlled markup. An AJAX call sidesteps all of it:
        // there's no form for the browser to validate or a theme to
        // restructure, and the redirect happens client-side, after the
        // browser already has the fresh session cookie in hand. This
        // mirrors how checkout OTP verification already works in this
        // plugin, and how at least one well-established SMS-OTP login
        // plugin (Digits) structures its own login flow.
        add_action('wp_ajax_nopriv_xeo_otp_login', [$this, 'ajax_otp_login']);
        add_action('wp_ajax_xeo_otp_login',        [$this, 'ajax_otp_login']);
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

        <!--
            This whole block is a plain <div>, not a <form> — the OTP-only
            login is submitted entirely over AJAX (see ajax_otp_login()
            below), so there is nothing here for the browser to validate or
            for the surrounding theme form to interfere with.
        -->
        <div id="xeo-otp-only-login" style="<?php echo esc_attr($wrap_style); ?>">
            <div class="woocommerce-form" id="xeo-otp-login-form">
                <input type="hidden" id="xeo_otp_login_ajax_nonce" value="<?php echo esc_attr(wp_create_nonce('xeo_otp_login')); ?>" />

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
                    <button type="button" class="woocommerce-Button button alt" id="xeo-otp-login-submit-btn">
                        <?php esc_html_e('Login with OTP', 'secure-otp-verification-for-woocommerce'); ?>
                    </button>
                </p>
            </div>
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

            // Some themes (e.g. Nasa) rename WooCommerce's own login fields
            // (nasa_username / nasa_password) but still mark them `required`.
            // Hiding their wrapping <p> above makes them display:none while
            // still required, which the browser's native form validation
            // refuses to submit — it can't focus a hidden field to show the
            // error, so it silently blocks the whole form instead. Since
            // we've deliberately hidden every field except our own OTP
            // fields, native validation no longer applies to this form at
            // all. (Our own OTP-only login button is a plain AJAX click
            // now and never triggers this anyway, but the password+OTP
            // tab's fields still live in this same <form>.)
            form.noValidate = true;
        });
        </script>
        <?php endif;
    }

    /**
     * OTP-only login, handled entirely over AJAX. No password gates this
     * login path, so trusted-device is never consulted here — a stolen
     * cookie must not be enough on its own to sign in. A fresh OTP is
     * always required.
     */
    public function ajax_otp_login() {
        check_ajax_referer('xeo_otp_login', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $otp   = sanitize_text_field($_POST['otp'] ?? '');

        XEO_OTP_Manager::log('info', 'login_otp_only', $email, 'AJAX OTP-only login attempt received.');

        if (!is_email($email)) {
            XEO_OTP_Manager::log('error', 'login_otp_only', $email, 'Email field failed is_email() validation.');
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'secure-otp-verification-for-woocommerce')]);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            XEO_OTP_Manager::log('error', 'login_otp_only', $email, 'No WordPress user found with this email.');
            wp_send_json_error(['message' => __('No account found with this email address.', 'secure-otp-verification-for-woocommerce')]);
        }

        if (!XEO_OTP_Manager::verify($email, $otp, 'login')) {
            XEO_OTP_Manager::log('error', 'login_otp_only', $email, 'OTP failed verify() — wrong code, expired, or already consumed.');
            wp_send_json_error(['message' => __('Invalid or expired OTP.', 'secure-otp-verification-for-woocommerce')]);
        }

        XEO_OTP_Manager::log('info', 'login_otp_only', $email, 'OTP verified. Setting auth cookie for user ID ' . $user->ID . '.');

        do_action('xeo_otp_verified', $user->ID, $email);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        XEO_OTP_Manager::log('info', 'login_otp_only', $email, 'is_user_logged_in() after wp_set_auth_cookie: ' . (is_user_logged_in() ? 'yes' : 'NO — cookie did not take effect'));

        // The redirect happens client-side (script.js does
        // window.location.href to this URL) once this AJAX response is
        // back — by then the browser already holds the fresh session
        // cookie, so the destination page should render logged-in on its
        // very first real request. Base My Account page rather than the
        // "dashboard" endpoint specifically, since the endpoint depends on
        // WooCommerce's rewrite rules being registered/flushed correctly.
        //
        // A unique query string is appended to defeat the browser's own
        // speculative prefetching (Chrome's "Preload pages" / NoState
        // Prefetch can fetch /my-account/ before the customer even submits
        // the OTP — e.g. because it appears as a nearby link — and that
        // prefetched copy is necessarily the pre-login, logged-out page).
        // Without this, window.location.href can be served straight from
        // that stale prefetch cache instead of making a real request, so
        // the browser never actually asks the server again at all — no
        // amount of server-side cache configuration can fix that, since
        // the server is never even contacted for that navigation.
        $redirect = add_query_arg('_xeo_fresh', (string) time(), wc_get_page_permalink('myaccount'));
        wp_send_json_success(['redirect' => $redirect]);
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
