<?php
/**
 * Formats WC data for creating/updating Garan24 orders
 *
 * @link  http://www.woothemes.com/products/garan24/
 * @since 2.0.0
 *
 * @package WC_Gateway_Garan24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * This class grabs WC cart contents and formats them so they can
 * be sent to Garan24 when a KCO order is being created or updated.
 *
 * Needs Garan24 order object passed as parameter
 * Checks if Rest API is in use
 * WC log class needs to be instantiated
 *
 * Get customer data
 * Create WC order
 * Add order items
 * Add order note
 * Add order fees
 * Add order shipping
 * Add order addresses
 * Add order tax rows - ?
 * Add order coupons
 * Add order payment method
 * EITHER Store customer (user) ID as post meta
 * OR     Maybe create customer account
 * Empty WooCommerce cart
 *
 */
class WC_Gateway_Garan24_K2WC {

	/**
	 * Is this for Rest API.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    boolean
	 */
	public $is_rest;

	/**
	 * Garan24 Eid.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $eid;

	/**
	 * Garan24 secret.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $secret;

	/**
	 * Garan24 order URI / ID.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $garan24_order_uri;

	/**
	 * Garan24 log object.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    object
	 */
	public $garan24_log;

	/**
	 * Garan24 debug.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string, yes or no
	 */
	public $garan24_debug;

	/**
	 * Garan24 test mode.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string, yes or no
	 */
	public $garan24_test_mode;

	/**
	 * Garan24 server URI.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string, yes or no
	 */
	public $garan24_server;

	/**
	 * Set is_rest value
	 *
	 * @since 2.0.0
	 */
	public function set_rest( $is_rest ) {
		$this->is_rest = $is_rest;
	}

	/**
	 * Set eid
	 *
	 * @since 2.0.0
	 */
	public function set_eid( $eid ) {
		$this->eid = $eid;
	}

	/**
	 * Set secret
	 *
	 * @since 2.0.0
	 */
	public function set_secret( $secret ) {
		$this->secret = $secret;
	}

	/**
	 * Set garan24_order_uri
	 *
	 * @since 2.0.0
	 */
	public function set_garan24_order_uri( $garan24_order_uri ) {
		$this->garan24_order_uri = $garan24_order_uri;
	}

	/**
	 * Set garan24_log
	 *
	 * @since 2.0.0
	 */
	public function set_garan24_log( $garan24_log ) {
		$this->garan24_log = $garan24_log;
	}

	/**
	 * Set garan24_debug
	 *
	 * @since 2.0.0
	 */
	public function set_garan24_debug( $garan24_debug ) {
		$this->garan24_debug = $garan24_debug;
	}

	/**
	 * Set garan24_debug
	 *
	 * @since 2.0.0
	 */
	public function set_garan24_test_mode( $garan24_test_mode ) {
		$this->garan24_test_mode = $garan24_test_mode;
	}

	/**
	 * Set garan24_server
	 *
	 * @since 2.0.0
	 */
	public function set_garan24_server( $garan24_server ) {
		$this->garan24_server = $garan24_server;
	}

	/**
	 * Prepares local order.
	 *
	 * Creates local order on Garan24's push notification.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  $customer_email KCO incomplete customer email
	 */
	public function prepare_wc_order( $customer_email ) {
		global $woocommerce;

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		if ( $woocommerce->session->get( 'ongoing_garan24_order' ) && wc_get_order( $woocommerce->session->get( 'ongoing_garan24_order' ) ) ) {
			$orderid = $woocommerce->session->get( 'ongoing_garan24_order' );
			$order   = wc_get_order( $orderid );
		} else {
			// Create order in WooCommerce if we have an email
			$order = $this->create_order();
			update_post_meta( $order->id, '_kco_incomplete_customer_email', $customer_email, true );
			$woocommerce->session->set( 'ongoing_garan24_order', $order->id );
		}

		// If there's an order at this point, proceed
		if ( isset( $order ) ) {
			// Need to clean up the order first, to avoid duplicate items
			$order->remove_order_items();

			// Add order items
			$this->add_order_items( $order );

			// Add order fees
			$this->add_order_fees( $order );

			// Add order shipping
			$this->add_order_shipping( $order );

			// Add order taxes
			$this->add_order_tax_rows( $order );

			// Store coupons
			$this->add_order_coupons( $order );

			// Store payment method
			$this->add_order_payment_method( $order );

			// Calculate order totals
			$this->set_order_totals( $order );

			// Tie this order to a user
			if ( email_exists( $customer_email ) ) {
				$user    = get_user_by( 'email', $customer_email );
				$user_id = $user->ID;
				update_post_meta( $order->id, '_customer_user', $user_id );
			}

			// Let plugins add meta
			do_action( 'woocommerce_checkout_update_order_meta', $order->id, array() );

			// Store which KCO API was used
			if ( $this->is_rest ) {
				update_post_meta( $order->id, '_garan24_api', 'rest' );
			} else {
				update_post_meta( $order->id, '_garan24_api', 'v2' );
			}

			// Other plugins need this hook
			do_action( 'woocommerce_checkout_order_processed', $order->id, false );

			return $order->id;
		}
	}

	/**
	 * KCO listener function.
	 *
	 * Creates local order on Garan24's push notification.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function listener() {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Listener triggered...' );
		}

		global $woocommerce;

		// Retrieve Garan24 order
		$garan24_order = $this->retrieve_garan24_order();

		// Check if order has been completed by Garan24, for V2 and Rest
		if ( $garan24_order['status'] == 'checkout_complete' || $garan24_order['status'] == 'AUTHORIZED' ) {
			$local_order_id = sanitize_key( $_GET['sid'] );
			$order          = wc_get_order( $local_order_id );

			// Check if order was recurring
			if ( isset( $garan24_order['recurring_token'] ) ) {
				update_post_meta( $order->id, '_garan24_recurring_token', $garan24_order['recurring_token'] );
			}

			if ( sanitize_key( $_GET['garan24-api'] ) && 'rest' == sanitize_key( $_GET['garan24-api'] ) ) {
				update_post_meta( $order->id, '_garan24_order_id', $garan24_order['order_id'] );
				$order->add_order_note( sprintf( __( 'Garan24 order ID: %s.', 'woocommerce-gateway-garan24' ), $garan24_order['order_id'] ) );

			} else {
				update_post_meta( $order->id, '_garan24_order_reservation', $garan24_order['reservation'] );
			}

			// Change order currency
			$this->change_order_currency( $order, $garan24_order );

			// Add order addresses
			$this->add_order_addresses( $order, $garan24_order );

			// Store payment method
			$this->add_order_payment_method( $order );

			// Add order customer info
			$this->add_order_customer_info( $order, $garan24_order );

			// Confirm the order in Garan24s system
			$garan24_order = $this->confirm_garan24_order( $order, $garan24_order );

			$order->calculate_totals( false );

			// Other plugins and themes can hook into here
			do_action( 'garan24_after_kco_push_notification', $order->id );
		}
	}

	/**
	 * Fetch KCO order.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function retrieve_garan24_order() {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Garan24 order - ' . $this->garan24_order_uri );
		}

		if ( sanitize_key( $_GET['garan24-api'] ) && 'rest' == sanitize_key( $_GET['garan24-api'] ) ) {
			$garan24_country = sanitize_key( $_GET['scountry'] );

			if ( $this->garan24_test_mode == 'yes' ) {
				if ( 'gb' == $garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
				} elseif ( 'us' == $garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
				}
			} else {
				if ( 'gb' == $garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
				} elseif ( 'us' == $garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
				}
			}

			$connector = \Garan24\Rest\Transport\Connector::create( $this->eid, $this->secret, $garan24_server_url );

			$garan24_order = new \Garan24\Rest\OrderManagement\Order( $connector, $this->garan24_order_uri );
		} else {
			$connector    = Garan24_Checkout_Connector::create( $this->secret, $this->garan24_server );
			$checkoutId   = $this->garan24_order_uri;
			$garan24_order = new Garan24_Checkout_Order( $connector, $checkoutId );
		}

		$garan24_order->fetch();

		return $garan24_order;
	}

	/**
	 * Create WC order.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function create_order() {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Creating local order...' );
		}
		global $woocommerce;

		// Customer accounts
		$customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );

		// Order data
		$order_data = array(
			'status'      => apply_filters( 'garan24_checkout_incomplete_order_status', 'kco-incomplete' ),
			'customer_id' => $customer_id,
			'created_via' => 'garan24_checkout'
		);

		// Create the order
		$order = wc_create_order( $order_data );

		if ( is_wp_error( $order ) ) {
			throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
		}

		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Local order created, order ID: ' . $order->id );
		}

		return $order;
	}

	/**
	 * Changes local order currency.
	 *
	 * When Aelia currency switcher is used, default store currency is always saved.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function change_order_currency( $order, $garan24_order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Maybe fixing order currency...' );
		}

		if ( $order->get_order_currency != strtoupper( $garan24_order['purchase_currency'] ) ) {
			if ( $this->garan24_debug == 'yes' ) {
				$this->garan24_log->add( 'garan24', 'Updating order currency...' );
			}
			update_post_meta( $order->id, '_order_currency', strtoupper( $garan24_order['purchase_currency'] ) );
		}
	}

	/**
	 * Adds order items to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 */
	public function add_order_items( $order ) {
		$order->remove_order_items();

		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order items...' );
		}
		global $woocommerce;
		$order_id = $order->id;

		foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
			$item_id = $order->add_product( $values['data'], $values['quantity'], array(
				'variation' => $values['variation'],
				'totals'    => array(
					'subtotal'     => $values['line_subtotal'],
					'subtotal_tax' => $values['line_subtotal_tax'],
					'total'        => $values['line_total'],
					'tax'          => $values['line_tax'],
					'tax_data'     => $values['line_tax_data'] // Since 2.2
				)
			) );

			if ( ! $item_id ) {
				if ( $this->garan24_debug == 'yes' ) {
					$this->garan24_log->add( 'garan24', 'Unable to add order item.' );
				}
				throw new Exception( __( 'Error: Unable to add order item. Please try again.', 'woocommerce' ) );
			}

			// Allow plugins to add order item meta
			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key );
		}
	}

	/**
	 * Adds order fees to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 */
	public function add_order_fees( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order fees...' );
		}
		global $woocommerce;
		$order_id = $order->id;

		foreach ( $woocommerce->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );

			if ( ! $item_id ) {
				if ( $this->garan24_debug == 'yes' ) {
					$this->garan24_log->add( 'garan24', 'Unable to add order fee.' );
				}
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}

			// Allow plugins to add order item meta to fees
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}
	}

	/**
	 * Adds order shipping to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $garan24_order Garan24 order.
	 */
	public function add_order_shipping( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order shipping...' );
		}
		global $woocommerce;

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$order_id              = $order->id;
		$this_shipping_methods = $woocommerce->session->get( 'chosen_shipping_methods' );

		// Store shipping for all packages
		foreach ( $woocommerce->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );

				if ( ! $item_id ) {
					if ( $this->garan24_debug == 'yes' ) {
						$this->garan24_log->add( 'garan24', 'Unable to add shipping item.' );
					}
					throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
				}

				// Allows plugins to add order item meta to shipping
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}

	/**
	 * Adds order addresses to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $garan24_order Garan24 order.
	 */
	public function add_order_addresses( $order, $garan24_order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order addresses...' );
			$this->garan24_log->add( 'garan24', var_export( $garan24_order, true ) );
		}

		$order_id = $order->id;

		// Different names on the returned street address if it's a German purchase or not
		$received__billing_address_1  = '';
		$received__shipping_address_1 = '';

		if ( $_GET['scountry'] == 'DE' || $_GET['scountry'] == 'AT' ) {
			$received__billing_address_1  = $garan24_order['billing_address']['street_name'] . ' ' . $garan24_order['billing_address']['street_number'];
			$received__shipping_address_1 = $garan24_order['shipping_address']['street_name'] . ' ' . $garan24_order['shipping_address']['street_number'];
		} else {
			$received__billing_address_1  = $garan24_order['billing_address']['street_address'];
			$received__shipping_address_1 = $garan24_order['shipping_address']['street_address'];
		}

		// Add customer billing address - retrieved from callback from Garan24
		update_post_meta( $order_id, '_billing_first_name', $garan24_order['billing_address']['given_name'] );
		update_post_meta( $order_id, '_billing_last_name', $garan24_order['billing_address']['family_name'] );
		update_post_meta( $order_id, '_billing_address_1', $received__billing_address_1 );
		if ( isset( $garan24_order['billing_address']['care_of'] ) ) {
			update_post_meta( $order_id, '_billing_address_2', $garan24_order['billing_address']['care_of'] );
		}
		update_post_meta( $order_id, '_billing_postcode', $garan24_order['billing_address']['postal_code'] );
		update_post_meta( $order_id, '_billing_city', $garan24_order['billing_address']['city'] );
		update_post_meta( $order_id, '_billing_country', strtoupper( $garan24_order['billing_address']['country'] ) );
		update_post_meta( $order_id, '_billing_email', $garan24_order['billing_address']['email'] );
		update_post_meta( $order_id, '_billing_phone', $garan24_order['billing_address']['phone'] );

		// Add customer shipping address - retrieved from callback from Garan24
		$allow_separate_shipping = ( isset( $garan24_order['options']['allow_separate_shipping_address'] ) ) ? $garan24_order['options']['allow_separate_shipping_address'] : '';

		update_post_meta( $order_id, '_shipping_first_name', $garan24_order['shipping_address']['given_name'] );
		update_post_meta( $order_id, '_shipping_last_name', $garan24_order['shipping_address']['family_name'] );
		update_post_meta( $order_id, '_shipping_address_1', $received__shipping_address_1 );
		if ( isset( $garan24_order['shipping_address']['care_of'] ) ) {
			update_post_meta( $order_id, '_shipping_address_2', $garan24_order['shipping_address']['care_of'] );
		}
		update_post_meta( $order_id, '_shipping_postcode', $garan24_order['shipping_address']['postal_code'] );
		update_post_meta( $order_id, '_shipping_city', $garan24_order['shipping_address']['city'] );
		update_post_meta( $order_id, '_shipping_country', strtoupper( $garan24_order['shipping_address']['country'] ) );

		// Store Garan24 locale
		update_post_meta( $order_id, '_garan24_locale', $garan24_order['locale'] );
	}

	/**
	 * Adds order tax rows to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 */
	public function add_order_tax_rows( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order tax...' );
		}

		// Store tax rows
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				if ( $this->garan24_debug == 'yes' ) {
					$this->garan24_log->add( 'garan24', 'Unable to add taxes.' );
				}
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 405 ) );
			}
		}
	}

	/**
	 * Adds order coupons to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception
	 */
	public function add_order_coupons( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order coupons...' );
		}

		global $woocommerce;

		foreach ( $woocommerce->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, $woocommerce->cart->get_coupon_discount_amount( $code ) ) ) {
				if ( $this->garan24_debug == 'yes' ) {
					$this->garan24_log->add( 'garan24', 'Unable to add coupons.' );
				}
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
		}
	}

	/**
	 * Adds payment method to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @internal param object $garan24_order Garan24 order.
	 */
	public function add_order_payment_method( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Adding order payment method...' );
		}

		global $woocommerce;

		$available_gateways = $woocommerce->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['garan24_checkout'];

		$order->set_payment_method( $payment_method );
	}

	/**
	 * Set local order totals.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 */
	public function set_order_totals( $order ) {
		if ( $this->garan24_debug == 'yes' ) {
			$this->garan24_log->add( 'garan24', 'Setting order totals...' );
		}

		global $woocommerce;

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$order->set_total( $woocommerce->cart->shipping_total, 'shipping' );
		$order->set_total( $woocommerce->cart->get_cart_discount_total(), 'order_discount' );
		$order->set_total( $woocommerce->cart->get_cart_discount_total(), 'cart_discount' );
		$order->set_total( $woocommerce->cart->tax_total, 'tax' );
		$order->set_total( $woocommerce->cart->shipping_tax_total, 'shipping_tax' );
		$order->set_total( $woocommerce->cart->total );
	}

	/**
	 * Create a new customer
	 *
	 * @param  string $email
	 * @param  string $username
	 * @param  string $password
	 *
	 * @return WP_Error on failure, Int (user ID) on success
	 *
	 * @since 1.0.0
	 */
	function create_new_customer( $email, $username = '', $password = '' ) {
		// Check the e-mail address
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( "registration-error", __( "Please provide a valid email address.", "woocommerce" ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( "registration-error", __( "An account is already registered with your email address. Please login.", "woocommerce" ) );
		}


		// Handle username creation
		$username = sanitize_user( current( explode( '@', $email ) ) );

		// Ensure username is unique
		$append     = 1;
		$o_username = $username;

		while ( username_exists( $username ) ) {
			$username = $o_username . $append;
			$append ++;
		}

		// Handle password creation
		$password           = wp_generate_password();
		$password_generated = true;

		// WP Validation
		$validation_errors = new WP_Error();
		do_action( 'woocommerce_register_post', $username, $email, $validation_errors );
		$validation_errors = apply_filters( 'woocommerce_registration_errors', $validation_errors, $username, $email );
		if ( $validation_errors->get_error_code() ) {
			$this->garan24_log->add( 'garan24', __( 'Customer creation error', 'woocommerce-gateway-garan24' ) . ' - ' . $validation_errors->get_error_code() );

			return 0;
		}

		$new_customer_data = apply_filters( 'woocommerce_new_customer_data', array(
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'role'       => 'customer'
		) );

		$customer_id = wp_insert_user( $new_customer_data );

		if ( is_wp_error( $customer_id ) ) {
			$validation_errors->add( "registration-error", '<strong>' . __( 'ERROR', 'woocommerce' ) . '</strong>: ' . __( 'Couldn&#8217;t register you&hellip; please contact us if you continue to have problems.', 'woocommerce' ) );
			$this->garan24_log->add( 'garan24', __( 'Customer creation error', 'woocommerce-gateway-garan24' ) . ' - ' . $validation_errors->get_error_code() );

			return 0;
		}

		// Send New account creation email to customer?
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		if ( 'yes' == $checkout_settings['send_new_account_email'] ) {
			do_action( 'woocommerce_created_customer', $customer_id, $new_customer_data, $password_generated );
		}

		return $customer_id;
	}

	/**
	 * Adds customer info to local order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $garan24_order Garan24 order.
	 */
	public function add_order_customer_info( $order, $garan24_order ) {
		$order_id = $order->id;

		// Store user id in order so the user can keep track of track it in My account
		if ( email_exists( $garan24_order['billing_address']['email'] ) ) {
			if ( $this->garan24_debug == 'yes' ) {
				$this->garan24_log->add( 'garan24', 'Billing email: ' . $garan24_order['billing_address']['email'] );
			}

			$user = get_user_by( 'email', $garan24_order['billing_address']['email'] );

			if ( $this->garan24_debug == 'yes' ) {
				$this->garan24_log->add( 'garan24', 'Customer User ID: ' . $user->ID );
			}

			$this->customer_id = $user->ID;

			update_post_meta( $order->id, '_customer_user', $this->customer_id );
		} else {
			// Create new user
			$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
			if ( 'yes' == $checkout_settings['create_customer_account'] ) {
				$password     = '';
				$new_customer = $this->create_new_customer( $garan24_order['billing_address']['email'], $garan24_order['billing_address']['email'], $password );

				if ( 0 == $new_customer ) { // Creation failed
					$order->add_order_note( sprintf( __( 'Customer creation failed. Check error log for more details.', 'garan24' ) ) );
					$this->customer_id = 0;
				} else { // Creation succeeded
					$order->add_order_note( sprintf( __( 'New customer created (user ID %s).', 'garan24' ), $new_customer, $garan24_order['id'] ) );

					// Add customer billing address - retrieved from callback from Garan24
					update_user_meta( $new_customer, 'billing_first_name', $garan24_order['billing_address']['given_name'] );
					update_user_meta( $new_customer, 'billing_last_name', $garan24_order['billing_address']['family_name'] );
					update_user_meta( $new_customer, 'billing_address_1', $received__billing_address_1 );
					update_user_meta( $new_customer, 'billing_address_2', $garan24_order['billing_address']['care_of'] );
					update_user_meta( $new_customer, 'billing_postcode', $garan24_order['billing_address']['postal_code'] );
					update_user_meta( $new_customer, 'billing_city', $garan24_order['billing_address']['city'] );
					update_user_meta( $new_customer, 'billing_country', $garan24_order['billing_address']['country'] );
					update_user_meta( $new_customer, 'billing_email', $garan24_order['billing_address']['email'] );
					update_user_meta( $new_customer, 'billing_phone', $garan24_order['billing_address']['phone'] );

					// Add customer shipping address - retrieved from callback from Garan24
					$allow_separate_shipping = ( isset( $garan24_order['options']['allow_separate_shipping_address'] ) ) ? $garan24_order['options']['allow_separate_shipping_address'] : '';

					if ( $allow_separate_shipping == 'true' || $_GET['scountry'] == 'DE' || $_GET['scountry'] == 'AT' ) {
						update_user_meta( $new_customer, 'shipping_first_name', $garan24_order['shipping_address']['given_name'] );
						update_user_meta( $new_customer, 'shipping_last_name', $garan24_order['shipping_address']['family_name'] );
						update_user_meta( $new_customer, 'shipping_address_1', $received__shipping_address_1 );
						update_user_meta( $new_customer, 'shipping_address_2', $garan24_order['shipping_address']['care_of'] );
						update_user_meta( $new_customer, 'shipping_postcode', $garan24_order['shipping_address']['postal_code'] );
						update_user_meta( $new_customer, 'shipping_city', $garan24_order['shipping_address']['city'] );
						update_user_meta( $new_customer, 'shipping_country', $garan24_order['shipping_address']['country'] );
					} else {
						update_user_meta( $new_customer, 'shipping_first_name', $garan24_order['billing_address']['given_name'] );
						update_user_meta( $new_customer, 'shipping_last_name', $garan24_order['billing_address']['family_name'] );
						update_user_meta( $new_customer, 'shipping_address_1', $received__billing_address_1 );
						update_user_meta( $new_customer, 'shipping_address_2', $garan24_order['billing_address']['care_of'] );
						update_user_meta( $new_customer, 'shipping_postcode', $garan24_order['billing_address']['postal_code'] );
						update_user_meta( $new_customer, 'shipping_city', $garan24_order['billing_address']['city'] );
						update_user_meta( $new_customer, 'shipping_country', $garan24_order['billing_address']['country'] );
					}

					$this->customer_id = $new_customer;
				}

				update_post_meta( $order->id, '_customer_user', $this->customer_id );
			}
		}
	}

	/**
	 * Confirms Garan24 order.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $garan24_order Garan24 order.
	 *
	 * @return object $garan24_order Garan24 order.
	 */
	public function confirm_garan24_order( $order, $garan24_order ) {
		// Rest API
		if ( isset( $_GET['garan24-api'] ) && 'rest' == sanitize_key( $_GET['garan24-api'] ) ) {
			if ( ! get_post_meta( $order->id, '_kco_payment_created', true ) ) {
				$order->add_order_note( sprintf( __( 'Garan24 Checkout payment created. Garan24 reference number: %s.', 'woocommerce-gateway-garan24' ), $garan24_order['garan24_reference'] ) );
				$garan24_order->acknowledge();
				$garan24_order->fetch();

				$garan24_order->updateMerchantReferences( array(
					'merchant_reference1' => ltrim( $order->get_order_number(), '#' )
				) );

				$order->calculate_totals( false );
				$order->payment_complete( $garan24_order['garan24_reference'] );

				delete_post_meta( $order->id, '_kco_incomplete_customer_email' );
				add_post_meta( $order->id, '_kco_payment_created', time() );
			}
			// V2 API
		} else {
			$order->add_order_note( sprintf( __( 'Garan24 Checkout payment created. Reservation number: %s.  Garan24 order number: %s', 'woocommerce-gateway-garan24' ), $garan24_order['reservation'], $garan24_order['id'] ) );

			// Add order expiration date
			$expiration_time = date( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $garan24_order['expires_at'] ) );
			$order->add_order_note( sprintf( __( 'Garan24 authorization expires at %s.', 'woocommerce-gateway-garan24' ), $expiration_time ) );

			$update['status']             = 'created';
			$update['merchant_reference'] = array(
				'orderid1' => ltrim( $order->get_order_number(), '#' )
			);
			$garan24_order->update( $update );

			// Confirm local order
			$order->calculate_totals( false );
			$order->payment_complete( $garan24_order['reservation'] );

			delete_post_meta( $order->id, '_kco_incomplete_customer_email' );
		}

		return $garan24_order;
	}

}