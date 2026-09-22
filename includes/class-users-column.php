<?php
if (!defined('ABSPATH')) exit;

class XEO_Users_Column {

    public function __construct() {
        add_filter('manage_users_columns',        [$this, 'add_column']);
        add_filter('manage_users_custom_column',  [$this, 'column_content'], 10, 3);
        add_filter('manage_users_sortable_columns',[$this, 'sortable_column']);
        add_action('xeo_otp_verified',            [$this, 'mark_user_verified'], 10, 2);
        add_action('show_user_profile',           [$this, 'profile_section']);
        add_action('edit_user_profile',           [$this, 'profile_section']);
        add_filter('bulk_actions-users',          [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-users',   [$this, 'handle_bulk_actions'], 10, 3);
    }

    public function add_column($columns) {
        $columns['xeo_email_verified'] = '📧 Email Verified';
        return $columns;
    }

    public function column_content($output, $column, $user_id) {
        if ($column !== 'xeo_email_verified') return $output;
        $verified    = $this->is_user_verified($user_id);
        $verified_at = $this->get_verified_at($user_id);
        if ($verified) {
            $title = $verified_at ? 'Verified on ' . $verified_at : 'Verified';
            return '<span title="' . esc_attr($title) . '" style="color:#27ae60;font-weight:bold;">✅ Verified</span>';
        }
        return '<span style="color:#e74c3c;">❌ Not Verified</span>';
    }

    public function sortable_column($columns) {
        $columns['xeo_email_verified'] = 'xeo_email_verified';
        return $columns;
    }

    public function is_user_verified($user_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}xantel_email_otp_verified_users WHERE user_id=%d", $user_id
        ));
    }

    public function get_verified_at($user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT verified_at FROM {$wpdb->prefix}xantel_email_otp_verified_users WHERE user_id=%d", $user_id
        ));
    }

    public function mark_user_verified($user_id, $email) {
        global $wpdb;
        $vtable = $wpdb->prefix . 'xantel_email_otp_verified_users';
        $now    = current_time('mysql');

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $vtable WHERE user_id=%d", $user_id));
        if ($exists) {
            $wpdb->update($vtable, ['last_otp_login' => $now], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($vtable, ['user_id' => $user_id, 'email' => $email, 'verified_at' => $now]);
        }
    }

    public function profile_section($user) {
        global $wpdb;
        $vtable      = $wpdb->prefix . 'xantel_email_otp_verified_users';
        $verified    = $this->is_user_verified($user->ID);
        $verified_at = $this->get_verified_at($user->ID);
        $last_login  = $wpdb->get_var($wpdb->prepare("SELECT last_otp_login FROM $vtable WHERE user_id=%d", $user->ID));
        ?>
        <h2>Secure OTP Verification — Status</h2>
        <table class="form-table">
            <tr>
                <th>Email Status</th>
                <td>
                    <?php if ($verified): ?>
                        <span style="color:#27ae60;font-weight:bold;font-size:16px;">✅ Verified</span>
                        <?php if ($verified_at): ?><p class="description">First verified: <?=esc_html($verified_at)?></p><?php endif; ?>
                        <?php if ($last_login):  ?><p class="description">Last OTP login: <?=esc_html($last_login)?></p><?php endif; ?>
                    <?php else: ?>
                        <span style="color:#e74c3c;font-weight:bold;font-size:16px;">❌ Not Verified</span>
                        <p class="description">This user has not verified their email via OTP yet.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function add_bulk_actions($actions) {
        $actions['xeo_mark_verified']     = 'Mark as Email Verified';
        $actions['xeo_mark_unverified']   = 'Mark as Email Unverified';
        $actions['xeo_forget_devices']    = 'Forget Trusted Login Devices';
        return $actions;
    }

    public function handle_bulk_actions($redirect_to, $action, $user_ids) {
        global $wpdb;
        if ($action === 'xeo_mark_verified') {
            foreach ($user_ids as $uid) {
                $u = get_userdata($uid);
                if ($u) do_action('xeo_otp_verified', $uid, $u->user_email);
            }
            $redirect_to = add_query_arg('xeo_verified', count($user_ids), $redirect_to);
        }
        if ($action === 'xeo_mark_unverified') {
            foreach ($user_ids as $uid) {
                $wpdb->delete($wpdb->prefix . 'xantel_email_otp_verified_users', ['user_id' => $uid]);
            }
            $redirect_to = add_query_arg('xeo_unverified', count($user_ids), $redirect_to);
        }
        if ($action === 'xeo_forget_devices') {
            foreach ($user_ids as $uid) {
                XEO_Trusted_Device::forget_all($uid);
            }
            $redirect_to = add_query_arg('xeo_devices_forgotten', count($user_ids), $redirect_to);
        }
        return $redirect_to;
    }
}
