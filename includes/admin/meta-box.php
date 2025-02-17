<?php
/**
 * BML Connect Meta Box for Order Details
 * 
 * @package BML_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add BML Connect meta box to order page
 */
function bml_connect_add_meta_box() {
    add_meta_box(
        'bml-connect-transaction',
        __('BML Connect Transaction (Unofficial)', 'bml-connect-woocommerce'),
        'bml_connect_meta_box_content',
        'shop_order',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'bml_connect_add_meta_box');

/**
 * Meta box content
 */
function bml_connect_meta_box_content($post) {
    $order = wc_get_order($post->ID);
    if (!$order) {
        return;
    }
    
    $gateway = new BML_Connect_Gateway();
    $transaction = $gateway->get_transaction_details($post->ID);
    
    if (!$transaction) {
        echo '<p>' . __('No BML Connect transaction found for this order.', 'bml-connect-woocommerce') . '</p>';
        return;
    }
    ?>
    <div class="bml-connect-transaction-details">
        <table>
            <tr>
                <th><?php _e('Transaction ID:', 'bml-connect-woocommerce'); ?></th>
                <td><?php echo esc_html($transaction->transaction_id); ?></td>
            </tr>
            <tr>
                <th><?php _e('Amount:', 'bml-connect-woocommerce'); ?></th>
                <td><?php echo esc_html(wc_price($transaction->amount, ['currency' => $transaction->currency])); ?></td>
            </tr>
            <tr>
                <th><?php _e('Status:', 'bml-connect-woocommerce'); ?></th>
                <td>
                    <span class="status-<?php echo strtolower($transaction->status); ?>">
                        <?php echo esc_html($transaction->status); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><?php _e('Date:', 'bml-connect-woocommerce'); ?></th>
                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($transaction->created_at))); ?></td>
            </tr>
        </table>
        
        <?php if ($transaction->status === 'PENDING'): ?>
            <p>
                <button type="button" class="button bml-connect-refresh-status" 
                        data-transaction="<?php echo esc_attr($transaction->transaction_id); ?>"
                        data-nonce="<?php echo wp_create_nonce('bml_connect_nonce'); ?>">
                    <?php _e('Refresh Status', 'bml-connect-woocommerce'); ?>
                </button>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * AJAX handler for refreshing transaction status
 */
function bml_connect_refresh_status() {
    check_ajax_referer('bml_connect_nonce', 'nonce');
    
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(['message' => __('Permission denied.', 'bml-connect-woocommerce')]);
    }
    
    $transaction_id = sanitize_text_field($_POST['transaction_id']);
    if (empty($transaction_id)) {
        wp_send_json_error(['message' => __('Invalid transaction ID.', 'bml-connect-woocommerce')]);
    }
    
    $gateway = new BML_Connect_Gateway();
    $api = new BML_Connect_API($gateway);
    $response = $api->check_transaction_status($transaction_id);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }
    
    // Update transaction status in database
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'bml_connect_transactions',
        ['status' => $response['status']],
        ['transaction_id' => $transaction_id],
        ['%s'],
        ['%s']
    );
    
    // Update order status if needed
    $transaction = $gateway->get_transaction_details_by_transaction_id($transaction_id);
    if ($transaction) {
        $order = wc_get_order($transaction->order_id);
        if ($order) {
            switch ($response['status']) {
                case 'SUCCESS':
                    if ($order->get_status() === 'pending') {
                        $order->payment_complete($transaction_id);
                        $order->add_order_note(__('BML Connect payment completed.', 'bml-connect-woocommerce'));
                    }
                    break;
                case 'FAILED':
                    if ($order->get_status() === 'pending') {
                        $order->update_status('failed', __('BML Connect payment failed.', 'bml-connect-woocommerce'));
                    }
                    break;
                case 'CANCELLED':
                    if ($order->get_status() === 'pending') {
                        $order->update_status('cancelled', __('BML Connect payment cancelled.', 'bml-connect-woocommerce'));
                    }
                    break;
            }
        }
    }
    
    wp_send_json_success(['status' => $response['status']]);
}
add_action('wp_ajax_bml_connect_refresh_status', 'bml_connect_refresh_status');