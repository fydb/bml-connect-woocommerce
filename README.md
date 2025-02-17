# BML Connect for WooCommerce

DISCLAIMER: This is an unofficial WooCommerce payment gateway integration for Bank of Maldives Connect API v2.0. This plugin is not affiliated with, endorsed, or sponsored by Bank of Maldives. Bank of Maldives and BML Connect are trademarks of Bank of Maldives.

## Description

This plugin integrates the Bank of Maldives Connect payment gateway with your WooCommerce store. It supports:

- Secure payment processing
- Transaction management
- Detailed reporting
- Order status synchronization
- Test mode for development

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- SSL certificate (for production use)

## Installation

1. Upload the `bml-connect-woocommerce` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments
4. Click on "BML Connect" to configure the payment gateway

## Configuration

1. Enable/disable the payment gateway
2. Enter your Merchant ID
3. Enter your API Key
4. Configure test mode if needed
5. Save changes

## Security Features

- SHA1 and MD5 signature validation
- HTTPS enforcement
- XSS prevention
- CSRF protection
- Input sanitization
- Secure storage of sensitive data

## Changelog

### 1.0.0

- Initial release

## License

GPL v2 or later
