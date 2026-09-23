<?php
if (!defined('ABSPATH')) exit;

/**
 * Surfaces the '_xeo_email_verified' order meta (set by
 * XEO_Checkout::tag_order_verification()) in the WooCommerce admin —
 * as a column on the Orders list and a note on the order screen —
 * regardless of checkout mode. This is the primary UI for "flag" mode,
 * where checkout itself never blocks an unverified order.
 *
 * Registers both the legacy (post-based) and HPOS (custom order tables)
 * hooks, since which one fires depends on the store's WooCommerce
 * order-storage setting.
 */
class XEO_Order_Column {

    public function __construct() {
        if (!xeo_is_enabled('checkout')) return;

        // Legacy, post-based orders
        add_filter('manage_edit-shop_order_columns', [$this, 'add_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_column'], 10, 2);

        // HPOS (custom order tables)
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_column'], 10, 2);

        // Note on the single-order edit screen
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'render_order_note']);
    }

    public function add_column($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_status') {
                $new['xeo_email_verified'] = __('Email Verified', 'secure-otp-verification-for-woocommerce');
            }
        }
        if (!isset($new['xeo_email_verified'])) {
            $new['xeo_email_verified'] = __('Email Verified', 'secure-otp-verification-for-woocommerce');
        }
        return $new;
    }

    // $column_or_order_id: legacy passes a post ID, HPOS passes a WC_Order.
    public function render_column($column, $post_id_or_order) {
        if ($column !== 'xeo_email_verified') return;

        $order = ($post_id_or_order instanceof WC_Order) ? $post_id_or_order : wc_get_order($post_id_or_order);
        if (!$order) return;

        echo $this->badge($order->get_meta('_xeo_email_verified'));
    }

    public function render_order_note($order) {
        if (!$order instanceof WC_Order) return;
        $meta = $order->get_meta('_xeo_email_verified');
        if ($meta === '') return; // checkout OTP wasn't enabled when this order was placed

        echo '<p class="form-field">
            <strong>' . esc_html__('Email Verified:', 'secure-otp-verification-for-woocommerce') . '</strong> '
            . $this->badge($meta) . '
        </p>';
    }

    private function badge($meta) {
        if ($meta === 'yes') {
            return '<span style="color:#27ae60;font-weight:600;">&#9989; ' . esc_html__('Verified', 'secure-otp-verification-for-woocommerce') . '</span>';
        }
        if ($meta === 'no') {
            return '<span style="color:#c0392b;font-weight:600;">&#9888; ' . esc_html__('Unverified', 'secure-otp-verification-for-woocommerce') . '</span>';
        }
        return '<span style="color:#999;">&mdash;</span>';
    }
}
