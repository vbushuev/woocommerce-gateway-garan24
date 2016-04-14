<?php

/**
 * Class WC_Garan24_Payment_Method_Widget
 *
 * The Part Payment Widget class informs consumers which payment methods you offer, and helps increase your conversion.
 * The Part Payment Widget can be displayed on single product pages.
 * Settings for the widget is configured in the Garan24 Account settings.
 *
 * @class        WC_Garan24_Payment_Method_Widget
 * @version        1.0
 * @since        1.8.1
 * @category    Class
 * @author        Krokedil
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Garan24_Payment_Method_Widget {

	public function __construct() {

		add_action( 'woocommerce_single_product_summary', array( $this, 'display_widget' ), $this->get_priority() );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'add_settings' ), 10, 2 );

	}


	function get_customer_country() {

		global $woocommerce;

		if ( $woocommerce->customer->get_country() ) {
			$garan24_country = $woocommerce->customer->get_country();
		} else {
			// Get current customers selected language if this is a multi language site
			$iso_code       = explode( '_', get_locale() );
			$shop_language  = strtoupper( $iso_code[0] ); // Country ISO code (SE)
			$garan24_country = $shop_language;
			switch ( $this->parent->shop_country ) {
				case 'NB' :
					$garan24_country = 'NO';
					break;
				case 'SV' :
					$garan24_country = 'SE';
					break;
			}
		}

		return strtolower( $garan24_country );

	}


	function get_garan24_locale() {

		$locale = get_locale();

		switch ( $locale ) {
			case 'da_DK':
				$garan24_locale = 'da_dk';
				break;
			case 'de_DE' :
				$garan24_locale = 'de_de';
				break;
			case 'no_NO' :
			case 'nb_NO' :
			case 'nn_NO' :
				$garan24_locale = 'nb_no';
				break;
			case 'nl_NL' :
				$garan24_locale = 'nl_nl';
				break;
			case 'fi_FI' :
			case 'fi' :
				$garan24_locale = 'fi_fi';
				break;
			case 'sv_SE' :
				$garan24_locale = 'sv_se';
				break;
			case 'de_AT' :
				$garan24_locale = 'de_at';
				break;
			case 'en_US' :
			case 'en_GB' :
				$garan24_locale = 'en_se';
				break;
			default:
				$garan24_locale = '';
		}

		return $garan24_locale;

	}


	function get_garan24_eid() {

		$customer_country = $this->get_customer_country();

		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		if ( isset( $checkout_settings[ 'eid_' . $customer_country ] ) ) {
			return $checkout_settings[ 'eid_' . $customer_country ];
		}

		$part_payment_settings = get_option( 'woocommerce_garan24_part_payment_settings' );
		if ( isset( $part_payment_settings[ 'eid_' . $customer_country ] ) ) {
			return $part_payment_settings[ 'eid_' . $customer_country ];
		}

		$invoice_settings = get_option( 'woocommerce_garan24_invoice_settings' );
		if ( isset( $invoice_settings[ 'eid_' . $customer_country ] ) ) {
			return $invoice_settings[ 'eid_' . $customer_country ];
		}

		return false;

	}


	function get_lower_threshold() {
		$lower_threshold = get_option( 'garan24_display_monthly_price_lower_threshold' );
		if ( is_numeric( $lower_threshold ) ) {
			return $lower_threshold;
		}

		return false;
	}


	function get_upper_threshold() {
		$upper_threshold = get_option( 'garan24_display_monthly_price_upper_threshold' );
		if ( is_numeric( $upper_threshold ) ) {
			return $upper_threshold;
		}

		return false;
	}

	function get_enabled() {
		$enabled = get_option( 'garan24_display_monthly_price' );
		if ( 'yes' == $enabled ) {
			return true;
		}

		return false;
	}


	function get_priority() {
		$priority = get_option( 'garan24_display_monthly_price_prio' );

		return $priority;
	}


	function display_widget() {
		if ( ! $this->get_enabled() ) {
			return false;
		}

		global $product;

		$garan24_product_total = $product->get_display_price();
		// Product with no price - do nothing
		if ( empty( $garan24_product_total ) ) {
			return;
		}

		$sum = apply_filters( 'garan24_product_total', $garan24_product_total ); // Product price.

		if ( $this->get_lower_threshold() ) {
			if ( $sum < $this->get_lower_threshold() ) {
				return false;
			}
		}

		if ( $this->get_upper_threshold() ) {
			if ( $sum > $this->get_upper_threshold() ) {
				return false;
			}
		}

		$locale = $this->get_garan24_locale();
		if ( empty( $locale ) ) {
			return;
		}

		$eid = $this->get_garan24_eid();
		if ( empty( $eid ) ) {
			return;
		}

		?>
		<div style="width:100%; height:70px"
		     class="garan24-widget garan24-part-payment"
		     data-eid="<?php echo $eid; ?>"
		     data-locale="<?php echo $locale; ?>"
		     data-price="<?php echo $sum; ?>"
		     data-layout="pale">
		</div>
		<?php
	}


	function get_customer_locale() {
		$locale = get_locale();

		switch ( $locale ) {

			case 'da_DK':
				$garan24_locale = 'da_dk';
				break;
			case 'de_DE' :
				$garan24_locale = 'de_de';
				break;
			case 'no_NO' :
			case 'nb_NO' :
			case 'nn_NO' :
				$garan24_locale = 'nb_no';
				break;
			case 'nl_NL' :
				$garan24_locale = 'nl_nl';
				break;
			case 'fi_FI' :
			case 'fi' :
				$garan24_locale = 'fi_fi';
				break;
			case 'sv_SE' :
				$garan24_locale = 'sv_se';
				break;
			case 'de_AT' :
				$garan24_locale = 'de_at';
				break;
			case 'en_US' :
			case 'en_GB' :
				$garan24_locale = 'en_se';
				break;
			default:
				$garan24_locale = '';

		}

		return $garan24_locale;
	}


	/**
	 * Register and Enqueue Garan24 scripts
	 */
	function enqueue_scripts() {
		//$this->show_monthly_cost = 'yes';
		//$this->enabled = 'yes';

		// Part Payment Widget js
		//if ( is_product() && $this->show_monthly_cost == 'yes' && $this->enabled == 'yes' ) {
		wp_register_script( 'garan24-part-payment-widget-js', 'https://cdn.garan24.com/1.0/code/client/all.js', array( 'jquery' ), '1.0', true );
		wp_enqueue_script( 'garan24-part-payment-widget-js' );
		//}

	} // End function


	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['garan24'] = __( 'Garan24 Payment Method (Monthly Cost) Widget', 'woocommerce-gateway-garan24' );

		return $sections;
	}


	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function add_settings( $settings, $current_section ) {
		if ( 'garan24' == $current_section ) {

			$settings = apply_filters( 'woocommerce_garan24_payment_method_widget_settings', array(

				// Start partpayment widget section
				array(
					'title' => __( 'Garan24 Payment Method Widget Settings', 'woocommerce-gateway-garan24' ),
					'type'  => 'title',
					'id'    => 'garan24_payment_method_widget_settings'
				),

				array(
					'title'    => __( 'Monthly cost', 'woocommerce-gateway-garan24' ),
					'desc'     => __( 'Display monthly cost in product pages', 'woocommerce-gateway-garan24' ),
					'desc_tip' => __( 'If enabled, this option will display Garan24 partpayment widget in product pages', 'woocommerce-gateway-garan24' ),
					'id'       => 'garan24_display_monthly_price',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'title'   => __( 'Monthly cost placement', 'woocommerce-gateway-garan24' ),
					'desc'    => __( 'Select where to display the widget in your product pages', 'woocommerce-gateway-garan24' ),
					'id'      => 'garan24_display_monthly_price_prio',
					'class'   => 'wc-enhanced-select',
					'default' => '15',
					'type'    => 'select',
					'options' => array(
						'4'  => __( 'Above Title', 'woocommerce-gateway-garan24' ),
						'7'  => __( 'Between Title and Price', 'woocommerce-gateway-garan24' ),
						'15' => __( 'Between Price and Excerpt', 'woocommerce-gateway-garan24' ),
						'25' => __( 'Between Excerpt and Add to cart button', 'woocommerce-gateway-garan24' ),
						'35' => __( 'Between Add to cart button and Product meta', 'woocommerce-gateway-garan24' ),
						'45' => __( 'Between Product meta and Product sharing buttons', 'woocommerce-gateway-garan24' ),
						'55' => __( 'After Product sharing-buttons', 'woocommerce-gateway-garan24' ),
					),
				),
				array(
					'title'    => __( 'Lower threshold', 'woocommerce-gateway-garan24' ),
					'desc'     => __( 'Lower threshold for monthly cost', 'woocommerce-gateway-garan24' ),
					'id'       => 'garan24_display_monthly_price_lower_threshold',
					'default'  => '',
					'type'     => 'number',
					'desc_tip' => __( 'Monthly cost widget will not be displayed in product pages if product price is less than this value.', 'woocommerce-gateway-garan24' ),
					'autoload' => false
				),
				array(
					'title'    => __( 'Upper threshold', 'woocommerce-gateway-garan24' ),
					'desc'     => __( 'Upper threshold for monthly cost', 'woocommerce-gateway-garan24' ),
					'id'       => 'garan24_display_monthly_price_upper_threshold',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => __( 'Monthly cost widget will not be displayed in product pages if product price is more than this value.', 'woocommerce-gateway-garan24' ),
					'autoload' => false
				),

				array(
					'type' => 'sectionend',
					'id'   => 'garan24_payment_method_widget_settings_end'
				),
				// End partpayment widget section

			) );

		}

		return $settings;
	}

} // End class
$wc_garan24_partpayment_widget = new WC_Garan24_Payment_Method_Widget;