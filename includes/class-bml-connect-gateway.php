<?php
/**
 * BML Connect Payment Gateway
 */
class BML_Connect_Gateway extends WC_Payment_Gateway {
    /**
     * @var BML_Connect_API
     */
    private $api;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'bml_connect';
        $this->icon = BML_CONNECT_PLUGIN_URL . 'assets/images/bml-logo.png';
        $this->has_fields = false;
        $this->method_title = __('BML Connect (Unofficial)', 'bml-connect-woocommerce');
        $this->method_description = __('Accept payments through Bank of Maldives Connect. This is an unofficial plugin not affiliated with Bank of Maldives.', 'bml-connect-woocommerce');
        
        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define properties
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->api_key = $this->get_option('api_key');
        
        // Initialize API
        $this->api = new BML_Connect_API($this);
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_bml_connect', [$this, 'handle_webhook']);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 3);
        add_action('bml_connect_check_pending_payments', [$this, 'check_pending_payments']);
    }
    
    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'bml-connect-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable BML Connect', 'bml-connect-woocommerce'),
                'default' => 'no'
            ],
            'testmode' => [
                'title' => __('Test Mode', 'bml-connect-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'bml-connect-woocommerce'),
                'default' => 'yes',
                'description' => __('Place the payment gateway in test mode.', 'bml-connect-woocommerce')
            ],
            'title' => [
                'title' => __('Title', 'bml-connect-woocommerce'),
                'type' => 'text',
                'description' => __('Payment method title that the customer will see on your checkout.', 'bml-connect-woocommerce'),
                'default' => __('Bank of Maldives', 'bml-connect-woocommerce'),
                'desc_tip' => true
            ],
            'description' => [
                'title' => __('Description', 'bml-connect-woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'bml-connect-woocommerce'),
                'default' => __('Pay securely using your Bank of Maldives card.', 'bml-connect-woocommerce'),
                'desc_tip' => true
            ],
            'merchant_id' => [
                'title' => __('Merchant ID', 'bml-connect-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your BML Connect Merchant ID.', 'bml-connect-woocommerce'),
                'default' => '',
                'desc_tip' => true
            ],
            'api_key' => [
                'title' => __('API Key', 'bml-connect-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your BML Connect API Key.', 'bml-connect-woocommerce'),
                'default' => '',
                'desc_tip' => true
            ]
        ];
    }
    
    /**
     * Process payment
     */
    public function process_payment($order_id) {
        try {
            $order = wc_get_order($order_id);
            
            // Prepare payment data
            $payment_data = [
                'amount' => number_format($order->get_total(), 2, '.', ''),
                'currency' => $order->get_currency(),
                'orderId' => $order->get_id(),
                'language' => 'EN',
                'redirectUrl' => $this->get_return_url($order),
                'cancelUrl' => $order->get_cancel_order_url(),
                'notificationUrl' => WC()->api_request_url('bml_connect')
            ];
            
            // Create payment session
            $response = $this->api->create_payment_session($payment_data);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            // Store transaction reference
            $order->update_meta_data('_bml_transaction_id', $response['transactionId']);
            $order->save();
            
            // Save transaction details
            $this->save_transaction_data($order_id, $response);
            
            // Update order status
            $order->update_status('pending', __('Awaiting BML Connect payment.', 'bml-connect-woocommerce'));
            
            // Redirect to payment page
            return [
                'result' => 'success',
                'redirect' => $response['paymentUrl']
            ];
            
        } catch (Exception $e) {
            BML_Connect_Logger::log('Payment Error: ' . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            return [
                'result' => 'failure',
                'messages' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle webhook
     */
    public function handle_webhook() {
        try {
            $raw_post = file_get_contents('php://input');
            $headers = getallheaders();
            
            BML_Connect_Logger::log('Webhook received: ' . $raw_post);
            
            // Verify signature
            if (!$this->api->verify_webhook_signature($raw_post, $headers)) {
                throw new Exception('Invalid signature');
            }
            
            $notification = json_decode($raw_post, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payload');
            }
            
            $order_id = $notification['orderId'];
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception('Order not found: ' . $order_id);
            }
            
            // Update order status based on payment status
            switch ($notification['status']) {
                case 'SUCCESS':
                    if ($order->get_status() === 'pending') {
                        $order->payment_complete($notification['transactionId']);
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
                    
                case 'PENDING':
                    // Keep the order status as pending
                    $order->add_order_note(__('BML Connect payment pending.', 'bml-connect-woocommerce'));
                    break;
            }
            
            // Update transaction record
            $this->update_transaction_status($order_id, $notification);
            
            wp_send_json_success();
            
        } catch (Exception $e) {
            BML_Connect_Logger::log('Webhook Error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Check pending payments status
     */
    public function check_pending_payments() {
        global $wpdb;
        
        // Get pending transactions older than 5 minutes
        $pending_transactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bml_connect_transactions 
                WHERE status = 'PENDING' 
                AND created_at < %s",
                date('Y-m-d H:i:s', strtotime('-5 minutes'))
            )
        );
        
        foreach ($pending_transactions as $transaction) {
            try {
                $response = $this->api->check_transaction_status($transaction->transaction_id);
                
                if (is_wp_error($response)) {
                    continue;
                }
                
                $order = wc_get_order($transaction->order_id);
                if (!$order) {
                    continue;
                }
                
                // Update order status based on payment status
                switch ($response['status']) {
                    case 'SUCCESS':
                        if ($order->get_status() === 'pending') {
                            $order->payment_complete($transaction->transaction_id);
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
                
                // Update transaction record
                $this->update_transaction_status($transaction->order_id, $response);
                
            } catch (Exception $e) {
                BML_Connect_Logger::log('Status Check Error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle order status changes
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        if ($new_status === 'cancelled') {
            $transaction = $this->get_transaction_details($order_id);
            if ($transaction && $transaction->status === 'PENDING') {
                try {
                    // Attempt to cancel the payment at BML
                    $response = $this->api->cancel_payment($transaction->transaction_id);
                    if (!is_wp_error($response)) {
                        $this->update_transaction_status($order_id, ['status' => 'CANCELLED']);
                    }
                } catch (Exception $e) {
                    BML_Connect_Logger::log('Cancel Payment Error: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Save transaction data
     */
    private function save_transaction_data($order_id, $response) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'bml_connect_transactions',
            [
                'order_id' => $order_id,
                'transaction_id' => $response['transactionId'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'status' => 'PENDING',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%f', '%s', '%s', '%s']
        );
    }
    
    /**
     * Update transaction status
     */
    private function update_transaction_status($order_id, $data) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'bml_connect_transactions',
            ['status' => $data['status']],
            ['order_id' => $order_id],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Get transaction details
     */
    public function get_transaction_details($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bml_connect_transactions WHERE order_id = %d",
            $order_id
        ));
    }

    /**
     * Get transaction details by transaction ID
     */
    public function get_transaction_details_by_transaction_id($transaction_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bml_connect_transactions WHERE transaction_id = %s",
            $transaction_id
        ));
    }
}