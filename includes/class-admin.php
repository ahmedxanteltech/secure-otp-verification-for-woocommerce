<?php
if (!defined('ABSPATH')) exit;

class XEO_Admin {

    public function __construct() {
        add_action('admin_menu',    [$this, 'add_menu']);
        add_action('admin_init',    [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'password_disabled_notice']);
    }

    public function add_menu() {
        add_options_page('Secure OTP Verification for WooCommerce', 'Secure OTP', 'manage_options', 'secure-otp-verification-for-woocommerce', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('xeo_settings', 'xeo_otp_mode');
        register_setting('xeo_settings', 'xeo_disable_password_login', ['sanitize_callback' => 'absint']);
        register_setting('xeo_settings', 'xeo_skip_checkout_logged_in', ['sanitize_callback' => 'absint']);
        register_setting('xeo_settings', 'xeo_trusted_device_days', ['sanitize_callback' => 'absint']);
        register_setting('xeo_settings', 'xeo_delete_data_on_uninstall', ['sanitize_callback' => 'absint']);
        register_setting('xeo_settings', 'xeo_checkout_mode', ['sanitize_callback' => function ($v) {
            return $v === 'flag' ? 'flag' : 'block';
        }]);
    }

    public function password_disabled_notice() {
        if (!xeo_password_login_disabled()) return;
        echo '<div class="notice notice-warning"><p>
            <strong>Secure OTP Verification:</strong> Password-based login is currently <strong>disabled</strong>.
            Users can only log in via Email OTP. &nbsp;
            <a href="' . esc_url(admin_url('options-general.php?page=secure-otp-verification-for-woocommerce')) . '">Change setting</a>
        </p></div>';
    }

    public function settings_page() {
        global $wpdb;
        $table  = $wpdb->prefix . 'xantel_email_otp';
        $vtable = $wpdb->prefix . 'xantel_email_otp_verified_users';

        $total          = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $verified_otps  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE verified=1");
        $verified_users = (int) $wpdb->get_var("SELECT COUNT(*) FROM $vtable");
        $recent         = $wpdb->get_results("SELECT email,purpose,created_at,verified FROM $table ORDER BY id DESC LIMIT 20");

        $current_mode      = xeo_get_mode();
        $password_disabled = xeo_password_login_disabled();
        $skip_checkout     = xeo_skip_checkout_for_logged_in();
        $trusted_days      = xeo_trusted_device_days();
        $delete_on_uninstall = (bool) get_option('xeo_delete_data_on_uninstall', 0);
        $checkout_mode        = xeo_checkout_mode();

        $modes = [
            'register_only'           => 'Registration Only',
            'register_login'          => 'Registration + Login',
            'register_login_checkout' => 'Registration + Login + Checkout',
        ];
        ?>
        <div class="wrap">
            <!-- Header -->
            <div style="display:flex;align-items:center;gap:15px;margin-bottom:20px;">
                <div style="background:#1a1a2e;color:#fff;padding:10px 18px;border-radius:8px;font-size:18px;font-weight:bold;">
                    Xantel
                </div>
                <div>
                    <h1 style="margin:0;">Email OTP Settings</h1>
                    <p style="margin:2px 0 0;color:#999;font-size:13px;">by <a href="https://xanteltech.com" target="_blank">Xantel Technologies</a> · v<?php echo XEO_VERSION; ?></p>
                </div>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:25px;">
                <?php foreach ([
                    ['Total OTPs Sent',  $total,                         '#1a1a2e'],
                    ['OTPs Verified',    $verified_otps,                  '#27ae60'],
                    ['Spam Blocked',     max(0,$total-$verified_otps),    '#e74c3c'],
                    ['Verified Users',   $verified_users,                 '#8e44ad'],
                ] as [$label,$val,$color]): ?>
                <div style="background:#fff;padding:20px;border-radius:8px;border-left:4px solid <?=$color?>;box-shadow:0 2px 5px rgba(0,0,0,.08);">
                    <p style="margin:0;color:<?=$color?>;font-size:13px;font-weight:600;"><?=esc_html($label)?></p>
                    <p style="font-size:32px;font-weight:bold;margin:8px 0 0;color:<?=$color?>"><?=$val?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Settings form -->
            <div style="background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,.08);margin-bottom:25px;">
                <h2 style="margin-top:0;">⚙️ OTP Configuration</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('xeo_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="xeo_otp_mode">Enable OTP For</label></th>
                            <td>
                                <select name="xeo_otp_mode" id="xeo_otp_mode" style="min-width:320px;padding:8px;">
                                    <?php foreach ($modes as $val => $label): ?>
                                    <option value="<?=esc_attr($val)?>" <?php selected($current_mode,$val); ?>><?=esc_html($label)?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Choose which actions require email OTP verification.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xeo_disable_password_login">Disable Password Login</label></th>
                            <td>
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="xeo_disable_password_login" id="xeo_disable_password_login"
                                        value="1" <?php checked($password_disabled,1); ?> style="width:18px;height:18px;" />
                                    <span style="font-weight:500;">Force OTP-only login (hide password field for all non-admin users)</span>
                                </label>
                                <div style="margin-top:10px;padding:12px 15px;border-radius:6px;border-left:4px solid #e74c3c;background:#fdf2f0;">
                                    ⚠️ <strong>Important:</strong> When enabled, regular users <strong>cannot</strong> log in with a password.
                                    Administrators are <strong>always exempt</strong>. Ensure SMTP is configured first.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xeo_checkout_mode">Checkout OTP Behavior</label></th>
                            <td>
                                <select name="xeo_checkout_mode" id="xeo_checkout_mode" style="min-width:320px;padding:8px;">
                                    <option value="block" <?php selected($checkout_mode,'block'); ?>>Require OTP before placing order</option>
                                    <option value="flag" <?php selected($checkout_mode,'flag'); ?>>Flag unverified orders for review (don't block checkout)</option>
                                </select>
                                <p class="description">
                                    <strong>Require OTP:</strong> the order can't be placed until the billing email is verified. Strictest, but checkout stops working entirely if OTP emails can't be delivered (e.g. an SMTP outage).<br>
                                    <strong>Flag for review:</strong> checkout is never blocked. Every order gets an "Email Verified: Yes/No" marker on the Orders list and order screen, based on whether the billing email (or the logged-in account) has a verified email on file — you decide whether to follow up on unverified ones.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xeo_skip_checkout_logged_in">Skip Checkout OTP for Logged-In Customers</label></th>
                            <td>
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="xeo_skip_checkout_logged_in" id="xeo_skip_checkout_logged_in"
                                        value="1" <?php checked($skip_checkout,1); ?> style="width:18px;height:18px;" />
                                    <span style="font-weight:500;">Don't ask for checkout OTP if the customer is already logged in</span>
                                </label>
                                <p class="description">
                                    Only applies when Checkout OTP Behavior above is set to "Require OTP" — in "Flag for review" mode, checkout never shows an OTP field for anyone.
                                    Guest checkouts always require OTP. Logged-in customers already proved their account at registration/login,
                                    so this avoids asking twice. Turn off to always require a fresh OTP at checkout, even for logged-in customers.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xeo_trusted_device_days">Remember Login Devices For</label></th>
                            <td>
                                <input type="number" name="xeo_trusted_device_days" id="xeo_trusted_device_days"
                                    min="1" max="365" value="<?=esc_attr($trusted_days)?>" style="width:80px;padding:6px;" /> days
                                <p class="description">
                                    After a successful login OTP, this device won't be asked for OTP again for this many days
                                    (resets the clock each time it's used). Set lower for tighter security, higher for less friction.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xeo_delete_data_on_uninstall">On Plugin Deletion</label></th>
                            <td>
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="xeo_delete_data_on_uninstall" id="xeo_delete_data_on_uninstall"
                                        value="1" <?php checked($delete_on_uninstall,1); ?> style="width:18px;height:18px;" />
                                    <span style="font-weight:500;">Delete all plugin data (OTP logs, verified-user records, trusted devices, and these settings) when the plugin is deleted</span>
                                </label>
                                <p class="description">
                                    <strong>Unchecked (default, recommended):</strong> deactivating or deleting this plugin leaves your data untouched, so reinstalling later picks up right where you left off — no re-verifying every customer.<br>
                                    <strong>Checked:</strong> clicking "Delete" in Plugins will permanently drop the plugin's database tables and settings. This cannot be undone.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <!-- Activity log -->
            <div style="background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,.08);">
                <h2 style="margin-top:0;">📋 Recent OTP Activity</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Email</th><th>Purpose</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?=esc_html($r->email)?></td>
                            <td><span style="background:#ecf0f1;padding:3px 8px;border-radius:4px;font-size:12px;"><?=esc_html(ucfirst($r->purpose))?></span></td>
                            <td><?=esc_html($r->created_at)?></td>
                            <td><?=$r->verified
                                ? '<span style="color:#27ae60;font-weight:bold;">✅ Verified</span>'
                                : '<span style="color:#e74c3c;">❌ Not Verified</span>'?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
