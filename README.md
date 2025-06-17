# WCPV Zero Commission Handler

A WordPress plugin that automatically handles zero-value commissions in WooCommerce Product Vendors by marking them as void to prevent payout attempts.

## Description

This plugin solves the issue where WooCommerce Product Vendors attempts to process zero-value commissions, which can cause errors in payment systems and unnecessary processing overhead. The plugin automatically detects zero-value commissions and marks them as void, preventing them from being included in payout processes.

## Features

- **Automatic Detection**: Identifies zero-value commissions using robust comparison methods
- **Database Integration**: Fetches complete commission data when needed
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Dependency Checks**: Ensures WooCommerce and WooCommerce Product Vendors are active
- **Error Handling**: Graceful handling of exceptions and edge cases

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- WooCommerce Product Vendors plugin
- PHP 7.4 or higher

## Installation

1. Upload the `wcpv-zero-commission-handler` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically start working once activated

## How It Works

The plugin hooks into the `wcpv_commission_is_valid_to_pay` filter and:

1. **Receives Commission Data**: Gets commission and order objects from the filter
2. **Fetches Complete Data**: If the commission object is incomplete, fetches full data from the database
3. **Detects Zero Values**: Uses multiple comparison methods to identify zero-value commissions
4. **Marks as Void**: Updates the commission status to 'void' if it's not already void
5. **Prevents Payout**: Returns `false` to prevent the commission from being included in payouts
6. **Logs Activity**: Records all actions for debugging and monitoring

## Logging

The plugin uses WooCommerce's logging system. Logs can be viewed at:
**WooCommerce → Status → Logs**

Look for log entries with the source `wcpv-zero-commission` to monitor the plugin's activity.

## Configuration

No configuration is required. The plugin works automatically once activated.

## Troubleshooting

### Plugin Not Working

1. **Check Dependencies**: Ensure WooCommerce and WooCommerce Product Vendors are active
2. **Check Logs**: View WooCommerce logs for any error messages
3. **Verify Commission Data**: Check that commissions have the expected structure

### Zero Commissions Not Being Detected

1. **Check Logs**: Look for debug messages in WooCommerce logs
2. **Verify Commission Amounts**: Ensure commissions actually have zero values
3. **Check Database**: Verify commission data exists in the database

## Support

For support, please check the logs first and provide relevant log entries when reporting issues.

## Changelog

### Version 1.0.0
- Initial release
- Automatic zero-value commission detection
- Database integration for complete commission data
- Comprehensive logging system
- Dependency checks for WooCommerce and Product Vendors

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed to solve zero-value commission issues in WooCommerce Product Vendors. 