<?php

/**
 * Processes checkout fields for Garan24 Part Payment and Garan24 Invoice
 *
 * @link  http://www.woothemes.com/products/garan24/
 * @since 2.0.4
 *
 * @package WC_Gateway_Garan24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Process checkout fields for KPM
 *
 * Checks if it's OK to continue with the checkout and send data to Garan24.
 * @package WC_Gateway_Garan24
 */
class WC_Gateway_Garan24_Process_Checkout_KPM {

	public $garan24_field_prefix = '';
	public $garan24_shop_country = '';
	public $garan24_de_consent_terms_setting = '';

	/**
	 * Class constructor.
	 *
	 * @since 2.0.4
	 */
	public function __construct() {
		// Only run this if Garan24 Part Payment is the choosen payment method
		if ( isset( $_POST['payment_method'] ) ) {
			if ( $_POST['payment_method'] == 'garan24_part_payment' || $_POST['payment_method'] == 'garan24_invoice' ) {
				$payment_method_option_name = 'woocommerce_' . $_POST['payment_method'] . '_settings';
				$payment_method_option      = get_option( $payment_method_option_name );

				// Set variables
				$this->garan24_field_prefix             = $_POST['payment_method'] . '_';
				$this->garan24_shop_country             = apply_filters( 'garan24_shop_country', get_option( 'woocommerce_default_country' ) );
				$this->garan24_de_consent_terms_setting = $payment_method_option['de_consent_terms'];

				add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout_fields' ) );
			}
		}
	}

	/**
	 * Runs all the checks, hooks into woocommerce_checkout_process.
	 */
	public function process_checkout_fields() {
		// Check personal number (SE, NO, DK, FI)
		$this->check_pno();

		// Check gender
		$this->check_gender();

		// Check date of birth
		$this->check_dob();

		// Check if shipping and billing address match
		$this->compare_billing_and_shipping();

		// Check consent terms (DE, AT)
		$this->check_consent_terms();
	}


	/**
	 * Checks if personal number is entered for SE, NO, DK and FI.
	 */
	public function check_pno() {
		if ( isset( $_POST['billing_country'] ) && ( $_POST['billing_country'] == 'SE' || $_POST['billing_country'] == 'NO' || $_POST['billing_country'] == 'DK' || $_POST['billing_country'] == 'FI' ) ) {
			// Check if set, if its not set add an error.
			if ( empty( $_POST[ $this->garan24_field_prefix . 'pno' ] ) ) {
				wc_add_notice( __( '<strong>Date of birth</strong> is a required field.', 'woocommerce-gateway-garan24' ), 'error' );
			}
		}
	}

	/**
	 * Checks if gender is set for NL and DE.
	 */
	public function check_gender() {
		if ( isset( $_POST['billing_country'] ) && ( $_POST['billing_country'] == 'NL' || $_POST['billing_country'] == 'DE' || $_POST['billing_country'] == 'AT' ) ) {
			// Check if gender is set, if not add an error
			if ( empty( $_POST[ $this->garan24_field_prefix . 'gender' ] ) ) {
				wc_add_notice( __( '<strong>Gender</strong> is a required field.', 'woocommerce-gateway-garan24' ), 'error' );
			}
		}
	}

	/**
	 * Checks if gender is set for NL and DE.
	 */
	public function check_dob() {
		if ( isset( $_POST['billing_country'] ) && ( $_POST['billing_country'] == 'NL' || $_POST['billing_country'] == 'DE' || $_POST['billing_country'] == 'AT' ) ) {
			// Check if date of birth is set, if not add an error
			if ( empty( $_POST[ $this->garan24_field_prefix . 'date_of_birth_day' ] ) || empty( $_POST[ $this->garan24_field_prefix . 'date_of_birth_month' ] ) || empty( $_POST[ $this->garan24_field_prefix . 'date_of_birth_year' ] ) ) {
				wc_add_notice( __( '<strong>Date of birth</strong> is a required field.', 'woocommerce-gateway-garan24' ), 'error' );
			}
		}
	}

	/**
	 * Compares if billing and shipping address match.
	 */
	public function compare_billing_and_shipping() {
		$compare_billing_and_shipping = false;

		if ( isset( $_POST['ship_to_different_address'] ) && $_POST['ship_to_different_address'] = 1 ) {
			$compare_billing_and_shipping = true;
		}

		if ( $compare_billing_and_shipping == true && isset( $_POST['billing_first_name'] ) && isset( $_POST['shipping_first_name'] ) && $_POST['shipping_first_name'] !== $_POST['billing_first_name'] ) {
			wc_add_notice( __( 'Shipping and billing address must be the same when paying via Garan24.', 'woocommerce-gateway-garan24' ), 'error' );
		}

		if ( $compare_billing_and_shipping == true && isset( $_POST['billing_last_name'] ) && isset( $_POST['shipping_last_name'] ) && $_POST['shipping_last_name'] !== $_POST['billing_last_name'] ) {
			wc_add_notice( __( 'Shipping and billing address must be the same when paying via Garan24.', 'woocommerce-gateway-garan24' ), 'error' );
		}

		if ( $compare_billing_and_shipping == true && isset( $_POST['billing_address_1'] ) && isset( $_POST['shipping_address_1'] ) && $_POST['shipping_address_1'] !== $_POST['billing_address_1'] ) {
			wc_add_notice( __( 'Shipping and billing address must be the same when paying via Garan24.', 'woocommerce-gateway-garan24' ), 'error' );
		}

		if ( $compare_billing_and_shipping == true && isset( $_POST['billing_postcode'] ) && isset( $_POST['shipping_postcode'] ) && $_POST['shipping_postcode'] !== $_POST['billing_postcode'] ) {
			wc_add_notice( __( 'Shipping and billing address must be the same when paying via Garan24.', 'woocommerce-gateway-garan24' ), 'error' );
		}

		if ( $compare_billing_and_shipping == true && isset( $_POST['billing_city'] ) && isset( $_POST['shipping_city'] ) && $_POST['shipping_city'] !== $_POST['billing_city'] ) {
			wc_add_notice( __( 'Shipping and billing address must be the same when paying via Garan24.', 'woocommerce-gateway-garan24' ), 'error' );
		}
	}

	/**
	 * Checks if consent terms checkbox is checked for AT and DE.
	 */
	public function check_consent_terms() {
		if ( ( $this->garan24_shop_country == 'DE' || $this->garan24_shop_country == 'AT' ) && $this->garan24_de_consent_terms_setting == 'yes' ) {
			error_log( 'inside' );
			// Check if set, if its not set add an error.
			if ( empty( $_POST[ $this->garan24_field_prefix . 'de_consent_terms' ] ) ) {
				wc_add_notice( __( 'You must accept the Garan24 consent terms.', 'woocommerce-gateway-garan24' ), 'error' );
			}
		}
	}

}

$wc_gateway_garan24_process_checkout_kpm = new WC_Gateway_Garan24_Process_Checkout_KPM;