<?php
/**
 * Garan24 part payment class
 *
 * @link http://www.woothemes.com/products/garan24/
 * @since 1.0.0
 *
 * @package WC_Gateway_Garan24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class for Garan24 Part Payment.
 */
class WC_Gateway_Garan24_Part_Payment extends WC_Gateway_Garan24 {

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		global $woocommerce;

		parent::__construct();

		$this->id                 = 'garan24_part_payment';
		$this->method_title       = __( 'Garan24 Part Payment', 'woocommerce-gateway-garan24' );
		$this->method_description = sprintf( __( 'With Garan24 your customers can pay by invoice. Garan24 works by adding extra personal information fields and then sending the details to Garan24 for verification. Documentation <a href="%s" target="_blank">can be found here</a>.', 'woocommerce-gateway-garan24' ), 'https://docs.woothemes.com/document/garan24/' );
		$this->has_fields         = true;
		$this->order_button_text  = apply_filters( 'garan24_order_button_text', __( 'Place order', 'woocommerce' ) );
		$this->pclass_type        = array( 0, 1 ); // Part payment flexible and part payment fixed

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		include( GARAN24_DIR . 'includes/variables-part-payment.php' );

		// Load shortcodes. 
		// This is used so that the merchant easily can modify the displayed monthly 
		// cost text (on single product and shop page) via the settings page.
		include_once( GARAN24_DIR . 'classes/class-garan24-shortcodes.php' );

		// Garan24 PClasses handling. 
		include_once( GARAN24_DIR . 'classes/class-garan24-pclasses.php' );

		// Helper class
		include_once( GARAN24_DIR . 'classes/class-garan24-helper.php' );
		$this->garan24_helper = new WC_Gateway_Garan24_Helper( $this );

		// Test mode or Live mode		
		if ( $this->testmode == 'yes' ) {
			// Disable SSL if in testmode
			$this->garan24_ssl  = 'false';
			$this->garan24_mode = Garan24::BETA;
		} else {
			// Set SSL if used in webshop
			if ( is_ssl() ) {
				$this->garan24_ssl = 'true';
			} else {
				$this->garan24_ssl = 'false';
			}
			$this->garan24_mode = Garan24::LIVE;
		}

		// Apply filters to Country and language
		$this->garan24_part_payment_info = apply_filters( 'garan24_part_payment_info', '' );
		$this->icon                     = apply_filters( 'garan24_part_payment_icon', $this->garan24_helper->get_account_icon() );
		$this->icon_basic               = apply_filters( 'garan24_basic_icon', '' );

		// Apply filters to Garan24 warning banners (NL only)
		$garan24_wb = $this->get_garan24_wb();

		$this->garan24_wb_img_checkout       = apply_filters( 'garan24_wb_img_checkout', $garan24_wb['img_checkout'] );
		$this->garan24_wb_img_single_product = apply_filters( 'garan24_wb_img_single_product', $garan24_wb['img_single_product'] );
		$this->garan24_wb_img_product_list   = apply_filters( 'garan24_wb_img_product_list', $garan24_wb['img_product_list'] );

		// Refunds support
		$this->supports = array(
			'products',
			'refunds'
		);

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_receipt_garan24_part_payment', array( $this, 'receipt_page' ) );
		add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ) );

		// Add Garan24 shipping info to order confirmation page and email
		add_filter( 'woocommerce_thankyou_order_received_text', array(
			$this,
			'output_garan24_details_confirmation'
		), 20, 2 );
		// add_action( 'woocommerce_email_after_order_table', array( $this, 'output_garan24_details_confirmation_email' ), 10, 3 );

	}


	/**
	 * Add Garan24's shipping details to order confirmation page.
	 *
	 * @param  $text  Default order confirmation text
	 *
	 * @since  2.0.0
	 */
	public function output_garan24_details_confirmation( $text, $order ) {
		if ( $this->id == $order->payment_method ) {
			return $text . $this->get_garan24_shipping_info( $order->id );
		} else {
			return $text;
		}
	}


	/**
	 * Add Garan24's shipping details to confirmation email.
	 *
	 * @since  2.0.0
	 */
	public function output_garan24_details_confirmation_email( $order, $sent_to_admin, $plain_text ) {
		if ( $this->id == $order->payment_method ) {
			echo $this->get_garan24_shipping_info( $order->id );
		}
	}


	/**
	 * Get Garan24's shipping info.
	 *
	 * @since  2.0.0
	 */
	public function get_garan24_shipping_info( $orderid ) {
		$garan24_country = get_post_meta( $orderid, '_billing_country', true );

		switch ( $garan24_country ) {
			case 'SE' :
				$garan24_locale = 'sv_se';
				break;
			case 'NO' :
				$garan24_locale = 'nb_no';
				break;
			case 'DE' :
				$garan24_locale = 'de_de';
				break;
			case 'FI' :
				$garan24_locale = 'fi_fi';
				break;
			default :
				$garan24_locale = '';
		}

		// Only do this for SE, NO, DE and FI
		$allowed_locales = array(
			'sv_se',
			'nb_no',
			'de_de',
			'fi_fi'
		);
		if ( in_array( $garan24_locale, $allowed_locales ) ) {
			$garan24_info = wp_remote_get( 'http://cdn.garan24.com/1.0/shared/content/policy/packing/' . $this->garan24_helper->get_eid() . '/' . $garan24_locale . '/minimal' );

			if ( 200 == $garan24_info['response']['code'] ) {
				$garan24_message       = json_decode( $garan24_info['body'] );
				$garan24_shipping_info = wpautop( $garan24_message->template->text );

				return $garan24_shipping_info;
			}
		}

		return '';
	}


	/**
	 * Can the order be refunded via Garan24?
	 *
	 * @param  WC_Order $order
	 *
	 * @return bool
	 * @since  2.0.0
	 */
	public function can_refund_order( $order ) {
		if ( get_post_meta( $order->id, '_garan24_invoice_number', true ) ) {
			return true;
		}

		return false;
	}


	/**
	 * Refund order in Garan24 system
	 *
	 * @param  integer $orderid
	 * @param  integer $amount
	 * @param  string $reason
	 *
	 * @return bool
	 * @since  2.0.0
	 */
	public function process_refund( $orderid, $amount = null, $reason = '' ) {
		// Check if order was created using this method
		if ( $this->id == get_post_meta( $orderid, '_payment_method', true ) ) {
			$order = wc_get_order( $orderid );

			if ( ! $this->can_refund_order( $order ) ) {
				if ( $this->debug == 'yes' ) {
					$this->log->add( 'garan24', 'Refund Failed: No Garan24 invoice ID.' );
				}
				$order->add_order_note( __( 'This order cannot be refunded. Please make sure it is activated.', 'woocommerce-gateway-garan24' ) );

				return false;
			}

			$country = get_post_meta( $orderid, '_billing_country', true );

			$garan24 = new Garan24();
			$this->configure_garan24( $garan24, $country );
			$invNo = get_post_meta( $order->id, '_garan24_invoice_number', true );

			$garan24_order = new WC_Gateway_Garan24_Order( $order, $garan24 );
			$refund_order = $garan24_order->refund_order( $amount, $reason = '', $invNo );

			if ( $refund_order ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @since 1.0.0
	 */
	function init_form_fields() {
		$this->form_fields = include( GARAN24_DIR . 'includes/settings-part-payment.php' );
	}


	/**
	 * Admin Panel Options.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() { ?>
		<h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce-gateway-garan24' ); ?></h3>
		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>
		<table class="form-table">
			<?php $this->generate_settings_html(); // Generate the HTML For the settings form. ?>
		</table>
	<?php }


	/**
	 * Gets Garan24 warning banner images, used for NL only.
	 *
	 * @since  1.0.0
	 *
	 * @return $garan24_wb array
	 */
	function get_garan24_wb() {

		$garan24_wb = array();

		// Garan24 warning banner - used for NL only
		$garan24_wb['img_checkout']       = apply_filters( 'garan24_nl_banner', 'http://www.afm.nl/~/media/Images/wetten-regels/kredietwaarschuwing/balk_afm6-jpg.ashx', 'checkout' );
		$garan24_wb['img_single_product'] = apply_filters( 'garan24_nl_banner', 'http://www.afm.nl/~/media/Images/wetten-regels/kredietwaarschuwing/balk_afm6-jpg.ashx', 'single_product' );
		$garan24_wb['img_product_list']   = apply_filters( 'garan24_nl_banner', 'http://www.afm.nl/~/media/Images/wetten-regels/kredietwaarschuwing/balk_afm6-jpg.ashx', 'product_list' );

		return $garan24_wb;

	}


	/**
	 * Check if this gateway is enabled and available in user's country.
	 *
	 * @since 1.0.0
	 */
	function is_available() {

		if ( ! $this->check_enabled() ) {
			return false;
		}

		if ( ! is_admin() ) {
			if ( ! $this->check_required_fields() ) {
				return false;
			}
			if ( ! $this->check_customer_country() ) {
				return false;
			}
			if ( ! $this->check_customer_currency() ) {
				return false;
			}
			if ( ! $this->check_cart_total() ) {
				return false;
			}
			if ( ! $this->check_lower_threshold() ) {
				return false;
			}
			if ( ! $this->check_upper_threshold() ) {
				return false;
			}
			if ( ! $this->check_pclasses() ) {
				return false;
			}
		}

		return true;

	}


	/**
	 * Checks if payment method is enabled.
	 *
	 * @since  2.0
	 **/
	function check_enabled() {

		if ( 'yes' != $this->enabled ) {
			return false;
		}

		return true;

	}


	/**
	 * Checks if required fields are set.
	 *
	 * @since  2.0
	 **/
	function check_required_fields() {

		// Required fields check
		if ( ! $this->garan24_helper->get_eid() || ! $this->garan24_helper->get_secret() ) {
			return false;
		}

		return true;

	}


	/**
	 * Checks if there are PClasses.
	 *
	 * @since  2.0
	 **/
	function check_pclasses() {

		$country = $this->garan24_helper->get_garan24_country();
		$garan24  = new Garan24();
		$this->configure_garan24( $garan24, $country );

		$garan24_pclasses = new WC_Gateway_Garan24_PClasses( $garan24, false, $country );
		$pclasses        = $garan24_pclasses->fetch_pclasses();
		if ( empty( $pclasses ) ) {
			return false;
		}

		return true;

	}


	/**
	 * Checks if there is cart total.
	 *
	 * @since  2.0
	 **/
	function check_cart_total() {

		if ( ! is_admin() ) {
			global $woocommerce;

			if ( ! isset( $woocommerce->cart->total ) ) {
				return false;
			}
		}

		return true;

	}


	/**
	 * Checks if lower threshold is OK.
	 *
	 * @since  2.0
	 **/
	function check_lower_threshold() {

		if ( ! is_admin() ) {
			global $woocommerce;

			// Cart totals check - Lower threshold
			if ( $this->lower_threshold !== '' ) {
				if ( $woocommerce->cart->total < $this->lower_threshold ) {
					return false;
				}
			}
		}

		return true;

	}


	/**
	 * Checks if upper threshold is OK.
	 *
	 * @since  2.0
	 **/
	function check_upper_threshold() {

		if ( ! is_admin() ) {
			global $woocommerce;

			// Cart totals check - Upper threshold
			if ( $this->upper_threshold !== '' ) {
				if ( $woocommerce->cart->total > $this->upper_threshold ) {
					return false;
				}
			}
		}

		return true;

	}


	/**
	 * Checks if selling to customer's country is allowed.
	 *
	 * @since  2.0
	 **/
	function check_customer_country() {

		if ( ! is_admin() ) {
			global $woocommerce;

			// Only activate the payment gateway if the customers country is the same as 
			// the filtered shop country ($this->garan24_country)
			if ( $woocommerce->customer->get_country() == true && ! in_array( $woocommerce->customer->get_country(), $this->authorized_countries ) ) {
				return false;
			}

			// Don't allow orders over the amount of €250 for Dutch customers
			if ( ( $woocommerce->customer->get_country() == true && $woocommerce->customer->get_country() == 'NL' ) && $woocommerce->cart->total >= 251 ) {
				return false;
			}
		}

		return true;

	}


	/**
	 * Checks if customer's currency is allowed.
	 *
	 * @since  2.0
	 **/
	function check_customer_currency() {

		if ( ! is_admin() ) {
			global $woocommerce;

			// Currency check
			$currency_for_country = $this->garan24_helper->get_currency_for_country( $woocommerce->customer->get_country() );
			if ( ! empty( $currency_for_country ) && $currency_for_country !== $this->selected_currency ) {
				return false;
			}
		}

		return true;

	}


	/**
	 * Set up Garan24 configuration.
	 *
	 * @since  2.0
	 **/
	function configure_garan24( $garan24, $country ) {

		$garan24->config( $this->garan24_helper->get_eid(),                         // EID
			$this->garan24_helper->get_secret(),                      // Secret
			$country,                                                // Country
			$this->garan24_helper->get_garan24_language( $country ),   // Language
			$this->selected_currency,                                // Currency
			$this->garan24_mode,                                      // Live or test
			$pcStorage = 'jsondb',                                   // PClass storage
			$pcURI = 'garan24_pclasses_' . $country                   // PClass storage URI path
		);

	}


	/**
	 * Payment form on checkout page
	 *
	 * @since 1.0.0
	 */
	function payment_fields() {

		global $woocommerce;

		if ( 'yes' == $this->testmode ) { ?>
			<p><?php _e( 'TEST MODE ENABLED', 'woocommerce-gateway-garan24' ); ?></p>
		<?php }

		$garan24 = new Garan24();

		/**
		 * Setup Garan24 configuration
		 */
		$country = $this->garan24_helper->get_garan24_country();
		$this->configure_garan24( $garan24, $country );

		Garan24::$xmlrpcDebug = false;
		Garan24::$debug       = false;

		// apply_filters to cart total so we can filter this if needed
		$garan24_cart_total = $woocommerce->cart->total;
		$sum               = apply_filters( 'garan24_cart_total', $garan24_cart_total ); // Cart total.
		$flag              = Garan24Flags::CHECKOUT_PAGE; // or Garan24Flags::PRODUCT_PAGE, if you want to do it for one item.

		// Description
		if ( $this->description ) {
			$garan24_description = $this->description;
			// apply_filters to the description so we can filter this if needed
			echo '<p>' . apply_filters( 'garan24_part_payment_description', $garan24_description ) . '</p>';
		}

		// For countries other than NO do the old thing
		$pclass_type                  = $this->pclass_type;
		$garan24_select_pclass_element = $this->id . '_pclass';
		$garan24_dob_element           = $this->id . '_pno';
		include( GARAN24_DIR . 'views/public/payment-fields-part-payment.php' );

	}

	/**
	 * Collect DoB, based on country.
	 *
	 * @since  2.0
	 **/
	function collect_dob( $order_id ) {

		// Collect the dob different depending on country
		if ( isset( $_POST['billing_country'] ) && ( $_POST['billing_country'] == 'NL' || $_POST['billing_country'] == 'DE' ) ) {
			$garan24_pno_day   = isset( $_POST['garan24_part_payment_date_of_birth_day'] ) ? woocommerce_clean( $_POST['garan24_part_payment_date_of_birth_day'] ) : '';
			$garan24_pno_month = isset( $_POST['garan24_part_payment_date_of_birth_month'] ) ? woocommerce_clean( $_POST['garan24_part_payment_date_of_birth_month'] ) : '';
			$garan24_pno_year  = isset( $_POST['garan24_part_payment_date_of_birth_year'] ) ? woocommerce_clean( $_POST['garan24_part_payment_date_of_birth_year'] ) : '';

			$garan24_pno = $garan24_pno_day . $garan24_pno_month . $garan24_pno_year;
		} else {
			$garan24_pno = isset( $_POST['garan24_part_payment_pno'] ) ? woocommerce_clean( $_POST['garan24_part_payment_pno'] ) : '';
		}

		return $garan24_pno;

	}


	/**
	 * Process the payment and return the result
	 *
	 * @since 1.0.0
	 **/
	function process_payment( $order_id ) {

		global $woocommerce;

		$order = wc_get_order( $order_id );

		// Get values from garan24 form on checkout page

		// Collect the DoB
		$garan24_pno = $this->collect_dob( $order_id );

		// Store Garan24 specific form values in order as post meta
		update_post_meta( $order_id, 'garan24_pno', $garan24_pno );

		$garan24_pclass           = isset( $_POST['garan24_part_payment_pclass'] ) ? woocommerce_clean( $_POST['garan24_part_payment_pclass'] ) : '';
		$garan24_gender           = isset( $_POST['garan24_part_payment_gender'] ) ? woocommerce_clean( $_POST['garan24_part_payment_gender'] ) : '';
		$garan24_de_consent_terms = isset( $_POST['garan24_de_consent_terms'] ) ? woocommerce_clean( $_POST['garan24_de_consent_terms'] ) : '';

		// Split address into House number and House extension for NL & DE customers
		$garan24_billing  = array();
		$garan24_shipping = array();
		if ( isset( $_POST['billing_country'] ) && ( $_POST['billing_country'] == 'NL' || $_POST['billing_country'] == 'DE' ) ) {
			require_once( GARAN24_DIR . 'split-address.php' );

			// Set up billing address array
			$garan24_billing_address            = $order->billing_address_1;
			$splitted_address                  = splitAddress( $garan24_billing_address );
			$garan24_billing['address']         = $splitted_address[0];
			$garan24_billing['house_number']    = $splitted_address[1];
			$garan24_billing['house_extension'] = $splitted_address[2];

			// Set up shipping address array
			$garan24_shipping_address            = $order->shipping_address_1;
			$splitted_address                   = splitAddress( $garan24_shipping_address );
			$garan24_shipping['address']         = $splitted_address[0];
			$garan24_shipping['house_number']    = $splitted_address[1];
			$garan24_shipping['house_extension'] = $splitted_address[2];
		} else {
			$garan24_billing['address']         = $order->billing_address_1;
			$garan24_billing['house_number']    = '';
			$garan24_billing['house_extension'] = '';

			$garan24_shipping['address']         = $order->shipping_address_1;
			$garan24_shipping['house_number']    = '';
			$garan24_shipping['house_extension'] = '';
		}

		$garan24 = new Garan24();

		/**
		 * Setup Garan24 configuration
		 */
		$country = $this->garan24_helper->get_garan24_country();
		$this->configure_garan24( $garan24, $country );

		$garan24_order = new WC_Gateway_Garan24_Order( $order, $garan24 );
		$garan24_order->prepare_order( $garan24_billing, $garan24_shipping, $this->ship_to_billing_address );

		// Set store specific information so you can e.g. search and associate invoices with order numbers.
		$garan24->setEstoreInfo( $orderid1 = ltrim( $order->get_order_number(), '#' ), $orderid2 = $order_id, $user = '' // Username, email or identifier for the user?
		);


		try {
			// Transmit all the specified data, from the steps above, to Garan24.
			$result = $garan24->reserveAmount( $garan24_pno,            // Date of birth.
				$garan24_gender,            // Gender.
				- 1,                    // Automatically calculate and reserve the cart total amount
				Garan24Flags::NO_FLAG,    // No specific behaviour like RETURN_OCR or TEST_MODE.
				$garan24_pclass            // Get the pclass object that the customer has choosen.
			);

			// Prepare redirect url
			$redirect_url = $order->get_checkout_order_received_url();

			// Store the selected pclass in the order
			update_post_meta( $order_id, '_garan24_order_pclass', $garan24_pclass );

			// Retreive response
			$invno = $result[0];

			switch ( $result[1] ) {
				case Garan24Flags::ACCEPTED :
					$order->add_order_note( __( 'Garan24 payment completed. Garan24 Invoice number: ', 'woocommerce-gateway-garan24' ) . $invno );
					if ( $this->debug == 'yes' ) {
						$this->log->add( 'garan24', __( 'Garan24 payment completed. Garan24 Invoice number: ', 'woocommerce-gateway-garan24' ) . $invno );
					}
					update_post_meta( $order_id, '_garan24_order_reservation', $invno );
					update_post_meta( $order_id, '_transaction_id', $invno );
					$order->payment_complete(); // Payment complete
					$woocommerce->cart->empty_cart(); // Remove cart	
					// Return thank you redirect
					return array(
						'result'   => 'success',
						'redirect' => $redirect_url
					);
					break;

				case Garan24Flags::PENDING :
					$order->add_order_note( __( 'Order is PENDING APPROVAL by Garan24. Please visit Garan24 Online for the latest status on this order. Garan24 Invoice number: ', 'woocommerce-gateway-garan24' ) . $invno );
					if ( $this->debug == 'yes' ) {
						$this->log->add( 'garan24', __( 'Order is PENDING APPROVAL by Garan24. Please visit Garan24 Online for the latest status on this order. Garan24 reservation number: ', 'woocommerce-gateway-garan24' ) . $invno );
					}
					$order->payment_complete(); // Payment complete
					$woocommerce->cart->empty_cart(); // Remove cart
					// Return thank you redirect
					return array(
						'result'   => 'success',
						'redirect' => $redirect_url
					);
					break;

				case Garan24Flags::DENIED : // Order is denied, store it in a database.
					$order->add_order_note( __( 'Garan24 payment denied.', 'woocommerce-gateway-garan24' ) );
					if ( $this->debug == 'yes' ) {
						$this->log->add( 'garan24', __( 'Garan24 payment denied.', 'woocommerce-gateway-garan24' ) );
					}
					wc_add_notice( __( 'Garan24 payment denied.', 'woocommerce-gateway-garan24' ), 'error' );

					return;
					break;

				default: // Unknown response, store it in a database.
					$order->add_order_note( __( 'Unknown response from Garan24.', 'woocommerce-gateway-garan24' ) );
					if ( $this->debug == 'yes' ) {
						$this->log->add( 'garan24', __( 'Unknown response from Garan24.', 'woocommerce-gateway-garan24' ) );
					}
					wc_add_notice( __( 'Unknown response from Garan24.', 'woocommerce-gateway-garan24' ), 'error' );

					return;
					break;
			}

		} catch ( Exception $e ) {
			// The purchase was denied or something went wrong, print the message:
			wc_add_notice( sprintf( __( '%s (Error code: %s)', 'woocommerce-gateway-garan24' ), utf8_encode( $e->getMessage() ), $e->getCode() ), 'error' );
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'garan24', sprintf( __( '%s (Error code: %s)', 'woocommerce-gateway-garan24' ), utf8_encode( $e->getMessage() ), $e->getCode() ) );
			}

			return;
		}

	}

	/**
	 * Adds note in receipt page.
	 *
	 * @since 1.0.0
	 **/
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order.', 'woocommerce-gateway-garan24' ) . '</p>';
	}


	/**
	 * Disable the radio button for the Garan24 Part Payment payment method if Company name
	 * is entered and the customer is from Germany or Austria.
	 *
	 * @since 1.0.0
	 * @todo  move to separate JS file?
	 **/
	function footer_scripts() {
		if ( is_checkout() && 'yes' == $this->enabled ) { ?>
			<script type="text/javascript">
				//<![CDATA[
				jQuery(document).ajaxComplete(function () {
					if (jQuery.trim(jQuery('input[name=billing_company]').val()) && (jQuery("#billing_country").val() == 'DE' || jQuery("#billing_country").val() == 'AT')) {
						jQuery('#payment_method_garan24_part_payment').prop('disabled', true);
					} else jQuery('#payment_method_garan24_part_payment').prop('disabled', false);
				});

				jQuery(document).ready(function ($) {
					$(window).load(function () {
						$('input[name=billing_company]').keyup(function () {
							if ($.trim(this.value).length && ($("#billing_country").val() == 'DE' || $("#billing_country").val() == 'AT')) {
								$('#payment_method_garan24_part_payment').prop('disabled', true);
							} else $('#payment_method_garan24_part_payment').prop('disabled', false);
						});
					});
				});

				// Move PNO field and get address if SE
				jQuery(document).ajaxComplete(function (event, xhr, settings) {
					settings_url = settings.url;

					// Check if correct AJAX function
					if (settings_url.indexOf('?wc-ajax=update_order_review') > -1) {
						// Check if Garan24 Invoice and SE
						if (jQuery('input[name="payment_method"]:checked').val() == 'garan24_part_payment' &&
							jQuery('select#billing_country').val() == 'SE') {

							jQuery('.woocommerce-billing-fields #garan24-part-payment-get-address').remove();
							jQuery('#order_review #garan24-part-payment-get-address').show().prependTo(jQuery('.woocommerce-billing-fields'));

						} else {

							// if (jQuery('.woocommerce-billing-fields #garan24-invoice-get-address').length) {
							jQuery('.woocommerce-billing-fields #garan24-part-payment-get-address').hide().appendTo(jQuery('li.payment_method_garan24_part_payment div.payment_method_garan24_part_payment'));
							// }

						}
					}
				});
				//]]>
			</script>
		<?php }

	}


	/**
	 * Helper function, checks if payment method is enabled.
	 *
	 * @since 1.0.0
	 **/
	function get_enabled() {

		return $this->enabled;

	}

} // End class WC_Gateway_Garan24_Part_Payment