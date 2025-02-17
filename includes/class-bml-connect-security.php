<?php
/**
 * BML Connect Security Handler
 */
class BML_Connect_Security {
    /**
     * Initialize security measures
     */
    public static function init() {
        // Ensure SSL is used in production
        add_action('admin_notices', [__CLASS__, 'check_ssl']);
        
        // Add security headers
        add_action('send_headers', [__CLASS__, 'add_security_headers']);
        
        // Sanitize and validate inputs
        add_filter('bml_connect_sanitize_payment_data', [__CLASS__, 'sanitize_payment_data']);
    }
    
    /**
     * Check SSL requirement
     */
    public static function check_ssl() {
        $gateway = new BML_Connect_Gateway();
        if (!$gateway->testmode && !is_ssl()) {
            ?>
            <div class="error">
                <p><?php _e('BML Connect requires SSL certificate to be installed for secure payment processing.', 'bml-connect-woocommerce'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Add security headers
     */
    public static function add_security_headers() {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self' https://*.bml.com.mv; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://*.bml.com.mv; style-src 'self' 'unsafe-inline';");
    }
    
    /**
     * Sanitize payment data
     */
    public static function sanitize_payment_data($data) {
        $sanitized = [];
        
        // Basic sanitization
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = floatval($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        // Required fields validation
        $required = ['amount', 'currency', 'orderReference'];
        foreach ($required as $field) {
            if (empty($sanitized[$field])) {
                throw new Exception(sprintf(__('Missing required field: %s', 'bml-connect-woocommerce'), $field));
            }
        }
        
        // Amount validation
        if (!is_numeric($sanitized['amount']) || $sanitized['amount'] <= 0) {
            throw new Exception(__('Invalid amount', 'bml-connect-woocommerce'));
        }
        
        // Currency validation
        if (!in_array($sanitized['currency'], ['MVR', 'USD'])) {
            throw new Exception(__('Invalid currency', 'bml-connect-woocommerce'));
        }
        
        return $sanitized;
    }
    
    /**
     * Validate API response
     */
    public static function validate_api_response($response) {
        if (empty($response) || !is_array($response)) {
            throw new Exception(__('Invalid API response', 'bml-connect-woocommerce'));
        }
        
        $required = ['status', 'transactionId'];
        foreach ($required as $field) {
            if (!isset($response[$field])) {
                throw new Exception(sprintf(__('Missing field in API response: %s', 'bml-connect-woocommerce'), $field));
            }
        }
        
        return true;
    }
}

// Initialize security measures
BML_Connect_Security::init();