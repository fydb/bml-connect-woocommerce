<?php
/**
 * BML Connect Reports Page
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get transaction data for reports
 */
function bml_connect_get_transaction_data($args = []) {
    global $wpdb;
    
    $defaults = [
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d'),
        'status' => '',
        'per_page' => 20,
        'paged' => 1
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $where = [
        "DATE(created_at) BETWEEN %s AND %s"
    ];
    
    $where_args = [
        $args['start_date'],
        $args['end_date']
    ];
    
    if (!empty($args['status'])) {
        $where[] = "status = %s";
        $where_args[] = $args['status'];
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get total count
    $total = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bml_connect_transactions WHERE $where_clause",
            $where_args
        )
    );
    
    // Get transactions
    $transactions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bml_connect_transactions 
            WHERE $where_clause 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d",
            array_merge($where_args, [
                $args['per_page'],
                ($args['paged'] - 1) * $args['per_page']
            ])
        )
    );
    
    return [
        'total' => $total,
        'transactions' => $transactions
    ];
}

// Get filter values
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Get transaction data
$data = bml_connect_get_transaction_data([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'status' => $status,
    'paged' => $paged
]);

$transactions = $data['transactions'];
$total_items = $data['total'];
$total_pages = ceil($total_items / 20);
?>

<div class="wrap">
    <h1><?php _e('BML Connect Reports', 'bml-connect-woocommerce'); ?></h1>
    
    <!-- Filters -->
    <form method="get">
        <input type="hidden" name="page" value="bml-connect-reports">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'bml-connect-woocommerce'); ?></option>
                    <option value="COMPLETED" <?php selected($status, 'COMPLETED'); ?>><?php _e('Completed', 'bml-connect-woocommerce'); ?></option>
                    <option value="PENDING" <?php selected($status, 'PENDING'); ?>><?php _e('Pending', 'bml-connect-woocommerce'); ?></option>
                    <option value="FAILED" <?php selected($status, 'FAILED'); ?>><?php _e('Failed', 'bml-connect-woocommerce'); ?></option>
                    <option value="CANCELLED" <?php selected($status, 'CANCELLED'); ?>><?php _e('Cancelled', 'bml-connect-woocommerce'); ?></option>
                </select>
                
                <?php submit_button(__('Filter', 'bml-connect-woocommerce'), 'button', false, false); ?>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Order ID', 'bml-connect-woocommerce'); ?></th>
                    <th><?php _e('Transaction ID', 'bml-connect-woocommerce'); ?></th>
                    <th><?php _e('Amount', 'bml-connect-woocommerce'); ?></th>
                    <th><?php _e('Status', 'bml-connect-woocommerce'); ?></th>
                    <th><?php _e('Date', 'bml-connect-woocommerce'); ?></th>
                    <th><?php _e('Actions', 'bml-connect-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions): ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($transaction->order_id)); ?>">
                                    <?php echo esc_html($transaction->order_id); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($transaction->transaction_id); ?></td>
                            <td><?php echo esc_html(wc_price($transaction->amount, ['currency' => $transaction->currency])); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($transaction->status); ?>">
                                    <?php echo esc_html($transaction->status); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($transaction->created_at))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($transaction->order_id)); ?>" class="button button-small">
                                    <?php _e('View Order', 'bml-connect-woocommerce'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6"><?php _e('No transactions found.', 'bml-connect-woocommerce'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>