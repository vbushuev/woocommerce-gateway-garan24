<?php
/**
 * WooCommerce Garan24 Gateway
 *
 * @link http://www.woothemes.com/products/Garan24/
 * @since 0.3
 *
 * @package WC_Gateway_garan24
 *
 * @wordpress-plugin
 * Plugin Name:     WooCommerce Garan24 Gateway
 * Plugin URI:      http://woothemes.com/woocommerce
 * Description:     Extends WooCommerce. Provides a <a href="http://www.garan24.ru" target="_blank">Garan24</a> gateway for WooCommerce.
 * Version:         2.1.4
 * Author:          WooThemes
 * Author URI:      http://woothemes.com/
 * Developer:       Krokedil
 * Developer URI:   http://garan24.ru/
 * Text Domain:     woocommerce-gateway-garan24
 * Domain Path:     /languages
 * Copyright:       © 2009-2015 WooThemes.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Register activation hook
 */
function woocommerce_gateway_garan24_activate() {
	if ( version_compare( PHP_VERSION, '5.0', '<' ) ) {
		deactivate_plugins( basename( __FILE__ ) );
		wp_die( __( '<p><strong>WooCommerce Gateway Garan24</strong> plugin requires PHP version 5.0 or greater.</p>', 'woocommerce-gateway-garan24' ) );
	}
}

register_activation_hook( __FILE__, 'woocommerce_gateway_garan24_activate' );

/**
 * Show welcome notice
 */
function woocommerce_gateway_garan24_welcome_notice() {
	// Check if either one of three payment methods is configured
	if ( false == get_option( 'woocommerce_garan24_invoice_settings' ) && false == get_option( 'woocommerce_garan24_part_payment_settings' ) && false == get_option( 'woocommerce_garan24_checkout_settings' ) ) {
		$html = '<div class="updated">';
		$html .= '<p>';
		$html .= __( 'Thank you for choosing Garan24 as your payment provider. WooCommerce Garan24 Gateway is almost ready. Please visit <a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_garan24_checkout">Garan24 Checkout</a>, <a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_garan24_invoice">Garan24 Invoice</a> or <a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_garan24_part_payment">Garan24 Part Payment</a> settings to enter your EID and shared secret for countries you have an agreement for with Garan24. ', 'woocommerce-gateway-garan24' );
		$html .= '</p>';
		$html .= '</div><!-- /.updated -->';

		echo $html;
	}
}

add_action( 'admin_notices', 'woocommerce_gateway_garan24_welcome_notice' );

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '4edd8b595d6d4b76f31b313ba4e4f3f6', '18624' );

/**
 * Check if update is from 1.x to 2.x
 *
 * Names for these two options for changed, for better naming standards, so option values
 * need to be copied from old options.
 */
function Garan24_2_update() {
	// Invoice
	if ( false == get_option( 'woocommerce_garan24_invoice_settings' ) ) {
		if ( get_option( 'woocommerce_garan24_settings' ) ) {
			add_option( 'woocommerce_garan24_invoice_settings', get_option( 'woocommerce_garan24_settings' ) );
		}
	}

	// Part Payment
	if ( false == get_option( 'woocommerce_garan24_part_payment_settings' ) ) {
		if ( get_option( 'woocommerce_garan24_account_settings' ) ) {
			add_option( 'woocommerce_garan24_part_payment_settings', get_option( 'woocommerce_garan24_account_settings' ) );
		}
	}
}

add_action( 'plugins_loaded', 'garan24_2_update' );


/** Init Garan24 Gateway after WooCommerce has loaded.
 *
 * Hooks into 'plugins_loaded'.
 */
function init_garan24_gateway() {

	// If the WooCommerce payment gateway class is not available, do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	// Localisation
	load_plugin_textdomain( 'woocommerce-gateway-garan24', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Define plugin constants
	 */
	define( 'GARAN24_DIR', dirname( __FILE__ ) . '/' );         // Root dir
	define( 'GARAN24_LIB', dirname( __FILE__ ) . '/library/' ); // Garan24 library dir
	define( 'GARAN24_URL', plugin_dir_url( __FILE__ ) );      // Plugin folder URL

	// Set CURLOPT_SSL_VERIFYPEER via constant in library/src/Garan24/Checkout/HTTP/CURLTransport.php.
	// No need to set it to true if the store doesn't use https.
	if ( is_ssl() ) {
		define( 'GARAN24_WC_SSL_VERIFYPEER', true );
	} else {
		define( 'GARAN24_WC_SSL_VERIFYPEER', false );
	}

	/**
	 * WooCommerce Garan24 Gateway class
	 *
	 * @class   WC_Gateway_garan24
	 * @package WC_Gateway_garan24
	 */
	class WC_Gateway_garan24 extends WC_Payment_Gateway {

		public function __construct() {

			global $woocommerce;

			$this->shop_country = get_option( 'woocommerce_default_country' );

			// Check if woocommerce_default_country includes state as well. If it does, remove state
			if ( strstr( $this->shop_country, ':' ) ) {
				$this->shop_country = current( explode( ':', $this->shop_country ) );
			}

			// Get current customers selected language if this is a multi lanuage site
			$iso_code            = explode( '_', get_locale() );
			$this->shop_language = strtoupper( $iso_code[0] ); // Country ISO code (SE)

			switch ( $this->shop_language ) {
				case 'NB' :
					$this->shop_language = 'NO';
					break;
				case 'SV' :
					$this->shop_language = 'SE';
					break;
			}

			// Currency
			$this->selected_currency = get_woocommerce_currency();

			// Apply filters to shop_country
			$this->shop_country = apply_filters( 'garan24_shop_country', $this->shop_country );

			// Actions
			add_action( 'wp_enqueue_scripts', array( $this, 'garan24_load_scripts' ) );

		}

		/**
		 * Register and enqueue Garan24 scripts
		 */
		function Garan24_load_scripts() {

			wp_enqueue_script( 'jquery' );

			if ( is_checkout() ) {
				wp_register_script( 'garan24-base-js', 'http://garan24.ru/public/core/v1.0/js/garan24.min.js', array( 'jquery' ), '1.0', false );
				//wp_register_script( 'garan24-base-js', 'http://cdn.garan24.ru/public/kitt/core/v1.0/js/Garan24.min.js', array( 'jquery' ), '1.0', false );
				wp_register_script( 'garan24-terms-js', 'http://garan24.ru/public/toc/v1.0/js/garan24.terms.min.js', array( 'Garan24-base-js' ), '1.0', false );
				//wp_register_script( 'garan24-terms-js', 'http://cdn.garan24.ru/public/kitt/toc/v1.1/js/Garan24.terms.min.js', array( 'Garan24-base-js' ), '1.0', false );
				wp_enqueue_script( 'garan24-base-js' );
				wp_enqueue_script( 'garan24-terms-js' );
			}

		}

	} // End class WC_Gateway_garan24


	// Composer autoloader
	require_once __DIR__ . '/vendor/autoload.php';


	// Include the WooCommerce Compatibility Utility class
	// The purpose of this class is to provide a single point of compatibility functions for dealing with supporting multiple versions of WooCommerce (currently 2.0.x and 2.1)
	/* to change *///require_once 'classes/class-wc-garan24-compatibility.php';

	// Include our Garan24 classes
	require_once 'classes/class-garan24-part-payment.php'; // KPM Part Payment
	require_once 'classes/class-garan24-invoice.php'; // KPM Invoice
	require_once 'classes/class-garan24-process-checkout-kpm.php'; // KPM process checkout fields
	require_once 'classes/class-garan24-payment-method-widget.php'; // Partpayment widget
	require_once 'classes/class-garan24-get-address.php'; // Get address
	require_once 'classes/class-garan24-pms.php'; // PMS
	require_once 'classes/class-garan24-order.php'; // Handles Garan24 orders
	require_once 'classes/class-garan24-payment-method-display-widget.php'; // WordPress widget

	// register Foo_Widget widget
	function register_garan24_pmd_widget() {
		register_widget( 'WC_garan24_Payment_Method_Display_Widget' );
	}

	add_action( 'widgets_init', 'register_garan24_pmd_widget' );

	// Garan24 Checkout class
	require_once 'classes/class-garan24-checkout.php';
	require_once 'classes/class-garan24-shortcodes.php';
	require_once 'classes/class-garan24-validate.php';

	// Send customer and merchant emails for KCO Incomplete > Processing status change

	// Add kco-incomplete_to_processing to statuses that can send email
	add_filter( 'woocommerce_email_actions', 'wc_garan24_kco_add_kco_incomplete_email_actions' );
	function wc_garan24_kco_add_kco_incomplete_email_actions( $email_actions ) {
		$email_actions[] = 'woocommerce_order_status_kco-incomplete_to_processing';

		return $email_actions;
	}

	// Triggers the email
	add_action( 'woocommerce_order_status_kco-incomplete_to_processing_notification', 'wc_garan24_kco_incomplete_trigger' );
	function wc_garan24_kco_incomplete_trigger( $orderid ) {
		$kco_mailer = WC()->mailer();
		$kco_mails  = $kco_mailer->get_emails();
		foreach ( $kco_mails as $kco_mail ) {
			$order = new WC_Order( $orderid );
			if ( 'new_order' == $kco_mail->id || 'customer_processing_order' == $kco_mail->id ) {
				$kco_mail->trigger( $order->id );
			}
		}
	}

}

add_action( 'plugins_loaded', 'init_garan24_gateway', 2 );

/**
 * Add payment gateways to WooCommerce.
 *
 * @param  array $methods
 *
 * @return array $methods
 */
function add_garan24_gateway( $methods ) {
	$methods[] = 'WC_Gateway_garan24_Part_Payment';
	$methods[] = 'WC_Gateway_garan24_Invoice';
	$methods[] = 'WC_Gateway_garan24_Checkout';

	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_garan24_gateway' );


/**
 * Displays admin error messages if some of Garan24 Checkout and Garan24 Thank You pages are not valid URLs.
 */
function Garan24_checkout_admin_error_notices() {
	// Only show it on Garan24 settings pages
	if ( isset( $_GET['section'] ) && 'wc_gateway_garan24_' === substr( $_GET['section'], 0, 18 ) ) {
		// Get arrays of checkout and thank you pages for all countries
		$error_message = '';
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		if ( is_array( $checkout_settings ) ) {
			foreach ( $checkout_settings as $cs_key => $cs_value ) {
				if ( strpos( $cs_key, 'garan24_checkout_url_' ) !== false || strpos( $cs_key, 'garan24_checkout_thanks_url_' ) !== false ) {
					if ( '' != $cs_value && esc_url( $cs_value ) != $cs_value ) {
						$error_message .= '<p>' . sprintf( __( '%s is not a valid URL.', 'woocommerce-gateway-garan24' ),
						$cs_key ) . '</p>';
					}
				}
			}
		}

		// Display error message, if there is one
		if ( '' != $error_message ) {
			echo '<div class="notice notice-error">';
			echo $error_message;
			echo '</div>';
		}
	}
}
// add_filter( 'admin_notices', 'garan24_checkout_admin_error_notices' );
