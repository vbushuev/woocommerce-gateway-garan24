<?php

/**
 * Class WC_Garan24_PMS
 *
 * The payment method service is a new API call, created to provide you with all the information
 * you need to render your checkout when using Garan24's invoice and part payment products - both logotypes,
 * descriptions and pricing details. It simplifies the integration process and provide our recommendations
 * on how our products should be presented, and your customers will enjoy a frictionless buying experience.
 *
 * @class     WC_Garan24_PMS
 * @version   1.0
 * @since     1.9.5
 * @category  Class
 * @author    Krokedil
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Garan24_PMS {

	public function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'wp_enqueue_scripts', 'add_thickbox' );

	}

	/**
	 * Register and Enqueue Garan24 scripts
	 */
	function load_scripts() {

		if ( is_checkout() ) {
			wp_register_script( 'garan24-pms-js', GARAN24_URL . 'assets/js/garan24pms.js', array( 'jquery' ), '1.0', false );
			wp_enqueue_script( 'garan24-pms-js' );
		}

	} // End function


	/**
	 * Gets response from Garan24
	 */
	function get_data(
		$eid, $secret, $selected_currency, $shop_country, $cart_total, $payment_method_group, $select_id, $mode, $invoice_fee = false
	) {

		$garan24 = new Garan24();
		$config = new Garan24Config();

		// Default required options
		if ( $mode == 'test' ) {
			$garan24_ssl      = 'false';
			$garan24_endpoint = 'https://api-test.garan24.com/touchpoint/checkout/';
			$garan24_mode     = Garan24::BETA;
		} else {
			// Set SSL if used in webshop
			if ( is_ssl() ) {
				$garan24_ssl = 'true';
			} else {
				$garan24_ssl = 'false';
			}
			$garan24_endpoint = 'https://api.garan24.com/touchpoint/checkout/';
			$garan24_mode     = Garan24::LIVE;
		}

		// Configuration needed for the checkout service
		$config['mode']                 = $garan24_mode;
		$config['ssl']                  = $garan24_ssl;
		$config['checkout_service_uri'] = $garan24_endpoint;
		$config['pcStorage']            = 'json';
		$config['pcURI']                = './pclasses.json';
		$config['eid']                  = $eid;
		$config['secret']               = $secret;

		$garan24->setConfig( $config );

		$garan24_pms_locale = $this->get_locale( $shop_country );

		try {
			$response = $garan24->checkoutService( $cart_total,        // Total price of the checkout including VAT
				$selected_currency, // Currency used by the checkout
				$garan24_pms_locale  // Locale used by the checkout
			);
		} catch ( Garan24Exception $e ) {
			// cURL exception
			return false;
		}

		$data = $response->getData();

		if ( $response->getStatus() >= 400 ) {
			// server responded with error
			echo '<pre>';
			throw new Exception( print_r( $data, true ) );
			echo '</pre>';

			return false;
		}

		// return options and their descriptions

		$payment_methods = $data['payment_methods'];

		$payment_options         = array();
		$payment_options_details = array();

		$i = 0;
		foreach ( $payment_methods as $payment_method ) {

			// Check if payment group we're looking for
			if ( $payment_method_group == $payment_method['group']['code'] ) {
				$i ++;

				// Create option element output
				$payment_options[] = '<option value="' . $payment_method['pclass_id'] . '">' . $payment_method['title'] . '</option>';

				// Create payment option details output
				if ( $i < 2 ) {
					$inline_style = 'style="clear:both;position:relative"';
					$extra_class  = 'visible-pms';
				} else {
					$inline_style = 'style="clear:both;display:none;position:relative"';
					$extra_class  = '';
				}

				$payment_options_details_output = '<div class="garan24-pms-details ' . $extra_class . '" data-pclass="' . $payment_method['pclass_id'] . '" ' . $inline_style . '>';

				if ( isset( $payment_method['logo']['uri'] ) && '' != $payment_method['logo']['uri'] ) {
					$payment_options_details_output .= '<img class="garan24-pms-logo" style="display:none" src="' . $payment_method['logo']['uri'] . '?width=100" />';
				}

				$payment_options_details_output .= '<div>';

				$payment_options_details_output .= '<strong style="font-size:1.2em;display:block;margin-bottom:0.5em;">' . $payment_method['group']['title'] . '</strong>';

				if ( ! empty( $payment_method['details'] ) ) {
					$payment_options_details_output .= '<ul style="list-style:none;margin-bottom:0.75em;margin-left:0">';
					foreach ( $payment_method['details'] as $pd_k => $pd_v ) {
						$payment_options_details_output .= '<li style="padding:0.5em 0 !important" id="pms-details-' . $pd_k . '">' . implode( ' ', $pd_v ) . '</li>';
					}
					$payment_options_details_output .= '</ul>';
				}

				if ( isset( $payment_method['use_case'] ) && '' != $payment_method['use_case'] ) {
					$payment_options_details_output .= '<div class="garan24-pms-use-case" style="margin-bottom:0.75em">' . $payment_method['use_case'] . '</div>';
				}

				if ( isset( $payment_method['terms']['uri'] ) && '' != $payment_method['terms']['uri'] ) {
					$garan24_terms_uri = $payment_method['terms']['uri'];

					// Check if invoice fee needs to be added
					// Invoice terms links ends with ?fee=
					if ( strpos( $garan24_terms_uri, '?fee=' ) ) {
						if ( $invoice_fee ) {
							$garan24_terms_uri = $garan24_terms_uri . $invoice_fee . '&TB_iframe=true&width=600&height=550';
						} else {
							$garan24_terms_uri = $garan24_terms_uri . '0&TB_iframe=true&width=600&height=550';
						}
					} else {
						$garan24_terms_uri .= '?TB_iframe=true&width=600&height=550';
					}

					if ( 'SE' == $shop_country ) {
						$read_more_text = 'Läs mer';
					} elseif ( 'NO' == $shop_country ) {
						$read_more_text = 'Les mer';
					} else {
						$read_more_text = 'Read more';
					}
					add_thickbox();
					$payment_options_details_output .= '<div class="garan24-pms-terms-uri" style="margin-bottom:1em;"><a class="thickbox" href="' . $garan24_terms_uri . '" target="_blank">' . $read_more_text . '</a></div>';
				}

				$payment_options_details_output .= '</div>';

				$payment_options_details_output .= '</div>';

				$payment_options_details[] = $payment_options_details_output;

			}

		}

		// Check if anything was returned
		if ( ! empty( $payment_options ) ) {
			$payment_methods_output = '<p class="form-row">';
			$payment_methods_output .= '<label for="' . esc_attr( $select_id ) . '">' . __( 'Payment plan', 'woocommerce-gateway-garan24' ) . ' <span class="required">*</span></label>';
			$payment_methods_output .= '<select id="' . esc_attr( $select_id ) . '" name="' . esc_attr( $select_id ) . '" class="woocommerce-select garan24_pms_select" style="max-width:100%;width:100% !important;">';

			$payment_methods_output .= implode( '', $payment_options );

			$payment_methods_output .= '</select>';
			$payment_methods_output .= '</p>';

			if ( ! empty( $payment_options_details ) ) {
				$payment_methods_output .= implode( '', $payment_options_details );
			}

		} else {
			$payment_methods_output = false;
		}

		return $payment_methods_output;

	}

	function get_locale( $shop_country ) {

		switch ( $shop_country ) {
			case 'SE' :
				$garan24_pms_locale = 'sv_SE';
				break;
			case 'NO' :
				$garan24_pms_locale = 'nb_NO';
				break;
			case 'DK' :
				$garan24_pms_locale = 'da_DK';
				break;
			case 'FI' :
				$garan24_pms_locale = 'fi_FI';
				break;
			case 'DE' :
				$garan24_pms_locale = 'de_DE';
				break;
			case 'NL' :
				$garan24_pms_locale = 'nl_NL';
				break;
			case 'AT' :
				$garan24_pms_locale = 'de_AT';
				break;
		}

		return $garan24_pms_locale;

	}

}

$wc_garan24_pms = new WC_Garan24_PMS;