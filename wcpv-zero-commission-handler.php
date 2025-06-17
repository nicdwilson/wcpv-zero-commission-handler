<?php
/**
 * Plugin Name: WCPV Zero Commission Handler
 * Plugin URI: https://github.com/your-username/wcpv-zero-commission-handler
 * Description: Handles zero-value commissions in WooCommerce Product Vendors by automatically marking them as void to prevent payout attempts.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcpv-zero-commission-handler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Requires Plugins: woocommerce, woocommerce-product-vendors
 * Network: false
 *
 * @package WCPV_Zero_Commission_Handler
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WCPV_ZCH_VERSION', '1.0.0' );
define( 'WCPV_ZCH_PLUGIN_FILE', __FILE__ );
define( 'WCPV_ZCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPV_ZCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WCPV_Zero_Commission_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
        
        // Initialize the zero commission handler
        $this->init_zero_commission_handler();
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    /**
     * Initialize the zero commission handler functionality
     */
    private function init_zero_commission_handler() {
        add_filter( 'wcpv_commission_is_valid_to_pay', array( $this, 'handle_zero_value_commissions' ), 1, 3 );
    }

    /**
     * Filter to handle zero-value commissions and mark them as void
     * 
     * @param bool   $is_valid   Whether the commission is valid to pay
     * @param object $commission The commission object
     * @param object $order      The order object
     * @return bool
     */
    public function handle_zero_value_commissions( $is_valid, $commission, $order ) {
        // Safety check: ensure we have a valid logger
        try {
            $logger = wc_get_logger();
            if ( ! $logger ) {
                // If logger is not available, return original value to prevent crashes
                return $is_valid;
            }
        } catch ( Exception $e ) {
            // If we can't get the logger, return original value to prevent crashes
            return $is_valid;
        }
        
        // Safety check: ensure commission is a valid object
        if ( ! is_object( $commission ) ) {
            try {
                $logger->log( 'warning', '[WCPV Zero Commission Debug] Commission is not an object: ' . gettype( $commission ), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            return $is_valid;
        }
        
        // Log function entry with basic info
        try {
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Function called - Order ID: %s, Commission ID: %s, Initial is_valid: %s',
                isset( $commission->order_id ) ? $commission->order_id : 'N/A',
                isset( $commission->id ) ? $commission->id : 'N/A',
                $is_valid ? 'true' : 'false'
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            // Silently fail if logging fails
        }
        
        // Log commission object structure to understand what properties are available
        try {
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Commission object class: %s',
                get_class( $commission )
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            // Silently fail if logging fails
        }
        
        // Log all available properties of the commission object
        try {
            $commission_properties = get_object_vars( $commission );
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Commission object properties: %s',
                print_r( $commission_properties, true )
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            // Silently fail if logging fails
        }
        
        // Check if we need to fetch complete commission data
        if ( ! isset( $commission->total_commission_amount ) || ! isset( $commission->commission_status ) ) {
            try {
                $logger->log( 'info', '[WCPV Zero Commission Debug] Commission object missing required properties, fetching complete data from database', array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            
            // Safety check: ensure we have a valid commission ID
            if ( ! isset( $commission->id ) || ! is_numeric( $commission->id ) ) {
                try {
                    $logger->log( 'error', '[WCPV Zero Commission Debug] Invalid commission ID for database lookup', array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $e ) {
                    // Silently fail if logging fails
                }
                return $is_valid;
            }
            
            // Fetch complete commission data from database
            try {
                global $wpdb;
                
                // Safety check: ensure wpdb is available
                if ( ! $wpdb ) {
                    try {
                        $logger->log( 'error', '[WCPV Zero Commission Debug] WordPress database object not available', array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    return $is_valid;
                }
                
                // Safety check: ensure commission table constant is defined
                if ( ! defined( 'WC_PRODUCT_VENDORS_COMMISSION_TABLE' ) ) {
                    try {
                        $logger->log( 'error', '[WCPV Zero Commission Debug] Commission table constant not defined', array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    return $is_valid;
                }
                
                $table_name = WC_PRODUCT_VENDORS_COMMISSION_TABLE;
                
                $full_commission = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    $commission->id
                ) );
                
                if ( $full_commission ) {
                    try {
                        $logger->log( 'info', sprintf(
                            '[WCPV Zero Commission Debug] Retrieved complete commission data - Amount: %s, Status: %s',
                            $full_commission->total_commission_amount,
                            $full_commission->commission_status
                        ), array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    
                    // Use the complete commission data
                    $commission = $full_commission;
                } else {
                    try {
                        $logger->log( 'error', sprintf(
                            '[WCPV Zero Commission Debug] Failed to retrieve commission data for ID: %d',
                            $commission->id
                        ), array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    return $is_valid;
                }
            } catch ( Exception $e ) {
                try {
                    $logger->log( 'error', '[WCPV Zero Commission Debug] Database error: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $log_e ) {
                    // Silently fail if logging fails
                }
                return $is_valid;
            }
        }
        
        // Log commission object details
        try {
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Commission object details - Amount: %s, Status: %s, Type: %s',
                isset( $commission->total_commission_amount ) ? $commission->total_commission_amount : 'N/A',
                isset( $commission->commission_status ) ? $commission->commission_status : 'N/A',
                gettype( $commission )
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            // Silently fail if logging fails
        }
        
        // Log order object details (HPOS compatible)
        if ( is_object( $order ) ) {
            try {
                $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : ( isset( $order->id ) ? $order->id : 'N/A' );
                $order_status = method_exists( $order, 'get_status' ) ? $order->get_status() : ( isset( $order->status ) ? $order->status : 'N/A' );
                $order_total = method_exists( $order, 'get_total' ) ? $order->get_total() : ( isset( $order->total ) ? $order->total : 'N/A' );
                
                $logger->log( 'info', sprintf(
                    '[WCPV Zero Commission Debug] Order object details - ID: %s, Status: %s, Total: %s',
                    $order_id,
                    $order_status,
                    $order_total
                ), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                try {
                    $logger->log( 'warning', '[WCPV Zero Commission Debug] Error getting order details: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $log_e ) {
                    // Silently fail if logging fails
                }
            }
        } else {
            try {
                $logger->log( 'warning', '[WCPV Zero Commission Debug] Order object is not valid: ' . gettype( $order ), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
        }
        
        // Check if commission object has required properties
        if ( ! isset( $commission->total_commission_amount ) ) {
            try {
                $logger->log( 'warning', '[WCPV Zero Commission Debug] Commission object missing total_commission_amount property', array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            return $is_valid;
        }
        
        if ( ! isset( $commission->id ) ) {
            try {
                $logger->log( 'warning', '[WCPV Zero Commission Debug] Commission object missing id property', array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            return $is_valid;
        }
        
        // Convert to float and check for zero value
        try {
            $commission_amount = (float) $commission->total_commission_amount;
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Commission amount check - Raw: %s, Float: %f, Is zero: %s',
                $commission->total_commission_amount,
                $commission_amount,
                ( 0 === $commission_amount ) ? 'true' : 'false'
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            try {
                $logger->log( 'error', '[WCPV Zero Commission Debug] Error converting commission amount: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $log_e ) {
                // Silently fail if logging fails
            }
            return $is_valid;
        }
        
        // Check if commission amount is zero (using multiple methods for robustness)
        try {
            $is_zero = ( 0 === $commission_amount ) || ( 0.0 === $commission_amount ) || ( abs( $commission_amount ) < 0.01 );
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Zero detection - Strict zero: %s, Float zero: %s, Near zero: %s, Final is_zero: %s',
                ( 0 === $commission_amount ) ? 'true' : 'false',
                ( 0.0 === $commission_amount ) ? 'true' : 'false',
                ( abs( $commission_amount ) < 0.01 ) ? 'true' : 'false',
                $is_zero ? 'true' : 'false'
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            try {
                $logger->log( 'error', '[WCPV Zero Commission Debug] Error in zero detection: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $log_e ) {
                // Silently fail if logging fails
            }
            return $is_valid;
        }
        
        if ( $is_zero ) {
            try {
                $logger->log( 'info', sprintf(
                    '[WCPV Zero Commission Debug] Zero-value commission detected - Order ID: %d, Commission ID: %d, Current Status: %s',
                    $commission->order_id,
                    $commission->id,
                    $commission->commission_status
                ), array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            
            // Get the commission instance
            try {
                // Safety check: ensure required classes exist
                if ( ! class_exists( 'WC_Product_Vendors_Commission' ) ) {
                    try {
                        $logger->log( 'error', '[WCPV Zero Commission Debug] WC_Product_Vendors_Commission class not found', array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    return $is_valid;
                }
                
                if ( ! class_exists( 'WC_Product_Vendors_PayPal_MassPay' ) ) {
                    try {
                        $logger->log( 'error', '[WCPV Zero Commission Debug] WC_Product_Vendors_PayPal_MassPay class not found', array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    return $is_valid;
                }
                
                $commission_handler = new WC_Product_Vendors_Commission( new WC_Product_Vendors_PayPal_MassPay() );
                try {
                    $logger->log( 'info', '[WCPV Zero Commission Debug] Commission handler created successfully', array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $e ) {
                    // Silently fail if logging fails
                }
            } catch ( Exception $e ) {
                try {
                    $logger->log( 'error', '[WCPV Zero Commission Debug] Error creating commission handler: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $log_e ) {
                    // Silently fail if logging fails
                }
                return $is_valid;
            }
            
            // Mark as void if it's not already void
            if ( 'void' !== $commission->commission_status ) {
                try {
                    $logger->log( 'info', sprintf(
                        '[WCPV Zero Commission Debug] Attempting to mark commission as void - Commission ID: %d',
                        $commission->id
                    ), array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $e ) {
                    // Silently fail if logging fails
                }
                
                try {
                    $update_result = $commission_handler->update_status( $commission->id, 0, 'void' );
                    try {
                        $logger->log( 'info', sprintf(
                            '[WCPV Zero Commission Debug] Update status result: %s',
                            $update_result ? 'success' : 'failed'
                        ), array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $e ) {
                        // Silently fail if logging fails
                    }
                    
                    if ( $update_result ) {
                        try {
                            $logger->log( 'info', sprintf(
                                '[WCPV Zero Commission Debug] Successfully marked zero-value commission as void - Order ID: %d, Commission ID: %d',
                                $commission->order_id,
                                $commission->id
                            ), array( 'source' => 'wcpv-zero-commission' ) );
                        } catch ( Exception $e ) {
                            // Silently fail if logging fails
                        }
                    } else {
                        try {
                            $logger->log( 'error', sprintf(
                                '[WCPV Zero Commission Debug] Failed to mark commission as void - Order ID: %d, Commission ID: %d',
                                $commission->order_id,
                                $commission->id
                            ), array( 'source' => 'wcpv-zero-commission' ) );
                        } catch ( Exception $e ) {
                            // Silently fail if logging fails
                        }
                    }
                } catch ( Exception $e ) {
                    try {
                        $logger->log( 'error', '[WCPV Zero Commission Debug] Exception during status update: ' . $e->getMessage(), array( 'source' => 'wcpv-zero-commission' ) );
                    } catch ( Exception $log_e ) {
                        // Silently fail if logging fails
                    }
                }
            } else {
                try {
                    $logger->log( 'info', sprintf(
                        '[WCPV Zero Commission Debug] Commission already void - Order ID: %d, Commission ID: %d',
                        $commission->order_id,
                        $commission->id
                    ), array( 'source' => 'wcpv-zero-commission' ) );
                } catch ( Exception $e ) {
                    // Silently fail if logging fails
                }
            }
            
            // Return false to prevent payout
            try {
                $logger->log( 'info', '[WCPV Zero Commission Debug] Returning false to prevent payout', array( 'source' => 'wcpv-zero-commission' ) );
            } catch ( Exception $e ) {
                // Silently fail if logging fails
            }
            return false;
        }
        
        try {
            $logger->log( 'info', sprintf(
                '[WCPV Zero Commission Debug] Commission is not zero, returning original is_valid: %s',
                $is_valid ? 'true' : 'false'
            ), array( 'source' => 'wcpv-zero-commission' ) );
        } catch ( Exception $e ) {
            // Silently fail if logging fails
        }
        
        return $is_valid;
    }
}

// Initialize the plugin
new WCPV_Zero_Commission_Handler(); 