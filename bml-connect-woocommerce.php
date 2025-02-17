<?php
/**
 * Plugin Name: BML Connect for WooCommerce
 * Plugin URI: https://yourdomain.com/bml-connect-woocommerce
 * Description: Unofficial Bank of Maldives Connect payment gateway integration for WooCommerce. This plugin is not affiliated with, endorsed, or sponsored by Bank of Maldives.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourdomain.com
 * Text Domain: bml-connect-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 */

defined('ABSPATH') || exit;

// Plugin Constants
define('BML_CONNECT_VERSION', '1.0.0');
define('BML_CONNECT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BML_CONNECT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class BML_Connect_WooCommerce {
    /**
     * @var BML_Connect_WooCommerce The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        add_action('plugins_loaded', [$this, 'init']);
        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load plugin text domain
        load_plugin_textdomain('bml-connect-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Include required files
        $this->includes();
    }

    /**
     * Include required files
     */
    private function includes() {
        include_once BML_CONNECT_PLUGIN_DIR . 'includes/class-bml-connect-gateway.php';
        include_once BML_CONNECT_PLUGIN_DIR . 'includes/class-bml-connect-api.php';
        include_once BML_CONNECT_PLUGIN_DIR . 'includes/class-bml-connect-logger.php';
        include_once BML_CONNECT_PLUGIN_DIR . 'includes/class-bml-connect-security.php';
        include_once BML_CONNECT_PLUGIN_DIR . 'includes/admin/meta-box.php';
    }

    /**
     * Add the gateway to WooCommerce
     */
    public function add_gateway($methods) {
        $methods[] = 'BML_Connect_Gateway';
        return $methods;
    }

    /**
     * Show admin notice if WooCommerce is missing
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('BML Connect for WooCommerce requires WooCommerce to be installed and active.', 'bml-connect-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Add settings link on plugin page
     */
    public function plugin_action_links($links) {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=bml_connect') . '">' . __('Settings', 'bml-connect-woocommerce') . '</a>'
        ];
        return array_merge($plugin_links, $links);
    }
}

// Initialize plugin
function BML_Connect() {
    return BML_Connect_WooCommerce::instance();
}

// Global for backwards compatibility
$GLOBALS['bml_connect'] = BML_Connect();

// Activation hook
register_activation_hook(__FILE__, 'bml_connect_activate');
function bml_connect_activate() {
    // Create necessary database tables
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bml_connect_transactions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_id varchar(50) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(3) NOT NULL,
        status varchar(20) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY transaction_id (transaction_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Setup cron job
    if (!wp_next_scheduled('bml_connect_check_pending_payments')) {
        wp_schedule_event(time(), 'hourly', 'bml_connect_check_pending_payments');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'bml_connect_deactivate');
function bml_connect_deactivate() {
    // Clear scheduled cron job
    wp_clear_scheduled_hook('bml_connect_check_pending_payments');
}