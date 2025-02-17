<?php
/**
 * BML Connect API Handler
 */
class BML_Connect_API {
    /**
     * @var BML_Connect_Gateway
     */
    private $gateway;
    
    /**
     * API endpoints
     */
    private $endpoints = [
        'test' => 'https://api.uat.merchants.bankofmaldives.com.mv/public',
        'live' => 'https://api.merchants.bankofmaldives.com.mv/public'
    ];
    
    /**
     * Constructor
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    /**
     * Get API endpoint
     */
    private function get_endpoint() {
        return $this->gateway->testmode ? $this->endpoints['test'] : $this->endpoints['live'];
    }
    
    /**
     * Generate signature
     */
    private function generate_signature($data) {
        // Sort parameters alphabetically
        ksort($data);
        
        // Create string to sign
        $string_to_sign = '';
        foreach ($data as $key => $value) {
            if ($key !== 'signature') {
                $string_to_sign .= $key . $value;
            }
        }
        
        // Add api key to signature
        $string_to_sign .= $this->gateway->api_key;
        
        // Generate SHA1 hash
        $signature = sha1($string_to_sign);
        
        // Generate MD5 hash as final signature
        return md5($signature);
    }
    
    /**
     * Create payment session
     */
    public function create_payment_session($data) {
        $endpoint = $this->get_endpoint() . '/payment/initialize';
        
        // Add merchant details
        $data['merchantId'] = $this->gateway->merchant_id;
        $data['timestamp'] = time();
        
        // Generate signature
        $data['signature'] = $this->generate_signature($data);
        
        // Make API request
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->gateway->api_key
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            BML_Connect_Logger::log('API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON response from API');
        }
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            BML_Connect_Logger::log('API Error: ' . print_r($body, true));
            return new WP_Error('api_error', $body['message'] ?? 'Unknown API error');
        }
        
        return $body;
    }
    
    /**
     * Check transaction status
     */
    public function check_transaction_status($transaction_id) {
        $endpoint = $this->get_endpoint() . '/payment/status';
        
        $data = [
            'merchantId' => $this->gateway->merchant_id,
            'transactionId' => $transaction_id,
            'timestamp' => time()
        ];
        
        // Generate signature
        $data['signature'] = $this->generate_signature($data);
        
        // Make API request
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->gateway->api_key
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            BML_Connect_Logger::log('API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON response from API');
        }
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            BML_Connect_Logger::log('API Error: ' . print_r($body, true));
            return new WP_Error('api_error', $body['message'] ?? 'Unknown API error');
        }
        
        return $body;
    }
    
    /**
     * Verify webhook signature
     */
    public function verify_webhook_signature($payload, $headers) {
        if (empty($headers['X-Signature'])) {
            return false;
        }
        
        $received_signature = $headers['X-Signature'];
        
        // Generate signature from payload
        $data = json_decode($payload, true);
        if (!$data) {
            return false;
        }

        $calculated_signature = $this->generate_signature($data);
        
        return hash_equals($calculated_signature, $received_signature);
    }
}