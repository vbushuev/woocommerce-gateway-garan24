<?php
/**
 * Garan24 checkout class
 *
 * @link http://www.woothemes.com/products/garan24/
 * @since 1.0.0
 *
 * @package WC_Gateway_Garan24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_Garan24_Checkout extends WC_Gateway_Garan24 {

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		global $woocommerce;

		parent::__construct();

		$this->id           = 'garan24_checkout';
		$this->method_title = __( 'Garan24 Checkout', 'woocommerce-gateway-garan24' );
		$this->has_fields   = false;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		include( GARAN24_DIR . 'includes/variables-checkout.php' );

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

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Push listener
		add_action( 'woocommerce_api_wc_gateway_garan24_checkout', array( $this, 'check_checkout_listener' ) );
		// Validate listener
		add_action( 'woocommerce_api_wc_gateway_garan24_order_validate', array(
			'WC_Gateway_Garan24_Order_Validate',
			'validate_checkout_listener'
		) );

		// We execute the woocommerce_thankyou hook when the KCO Thank You page is rendered,
		// because other plugins use this, but we don't want to display the actual WC Order
		// details table in KCO Thank You page. This action is removed here, but only when
		// in Garan24 Thank You page.
		if ( is_page() ) {
			global $post;
			$garan24_checkout_page_id = url_to_postid( $this->garan24_checkout_thanks_url );
			if ( $post->ID == $garan24_checkout_page_id ) {
				remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
			}
		}

		// Subscription support
		$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'multiple_subscriptions'
		);

		// Add link to KCO page in standard checkout

		/**
		 * Checkout page AJAX
		 */

		// Add coupon
		add_action( 'wp_ajax_garan24_checkout_coupons_callback', array( $this, 'garan24_checkout_coupons_callback' ) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_coupons_callback', array(
			$this,
			'garan24_checkout_coupons_callback'
		) );

		// Remove coupon
		add_action( 'wp_ajax_garan24_checkout_remove_coupon_callback', array(
			$this,
			'garan24_checkout_remove_coupon_callback'
		) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_remove_coupon_callback', array(
			$this,
			'garan24_checkout_remove_coupon_callback'
		) );

		// Cart quantity
		add_action( 'wp_ajax_garan24_checkout_cart_callback_update', array(
			$this,
			'garan24_checkout_cart_callback_update'
		) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_cart_callback_update', array(
			$this,
			'garan24_checkout_cart_callback_update'
		) );

		// Cart remove
		add_action( 'wp_ajax_garan24_checkout_cart_callback_remove', array(
			$this,
			'garan24_checkout_cart_callback_remove'
		) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_cart_callback_remove', array(
			$this,
			'garan24_checkout_cart_callback_remove'
		) );

		// Shipping method selector
		add_action( 'wp_ajax_garan24_checkout_shipping_callback', array( $this, 'garan24_checkout_shipping_callback' ) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_shipping_callback', array(
			$this,
			'garan24_checkout_shipping_callback'
		) );

		// Shipping option inside KCO iframe
		add_action( 'wp_ajax_kco_iframe_shipping_option_change_cb', array(
			$this,
			'kco_iframe_shipping_option_change_cb'
		) );
		add_action( 'wp_ajax_nopriv_kco_iframe_shipping_option_change_cb', array(
			$this,
			'kco_iframe_shipping_option_change_cb'
		) );

		// Country selector
		add_action( 'wp_ajax_garan24_checkout_country_callback', array( $this, 'garan24_checkout_country_callback' ) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_country_callback', array(
			$this,
			'garan24_checkout_country_callback'
		) );

		// Order note
		add_action( 'wp_ajax_garan24_checkout_order_note_callback', array(
			$this,
			'garan24_checkout_order_note_callback'
		) );
		add_action( 'wp_ajax_nopriv_garan24_checkout_order_note_callback', array(
			$this,
			'garan24_checkout_order_note_callback'
		) );

		// KCO iframe JS event callbacks
		// V2
		add_action( 'wp_ajax_kco_iframe_change_cb', array( $this, 'kco_iframe_change_cb' ) );
		add_action( 'wp_ajax_nopriv_kco_iframe_change_cb', array( $this, 'kco_iframe_change_cb' ) );
		// V3
		add_action( 'wp_ajax_kco_iframe_shipping_address_change_cb', array(
			$this,
			'kco_iframe_shipping_address_change_cb'
		) );
		add_action( 'wp_ajax_nopriv_kco_iframe_shipping_address_change_cb', array(
			$this,
			'kco_iframe_shipping_address_change_cb'
		) );

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Process subscription payment
			// add_action( 'woocommerce_scheduled_subscription_renewal_garan24_checkout', array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'woocommerce_scheduled_subscription_payment_garan24_checkout', array(
				$this,
				'scheduled_subscription_payment'
			), 10, 2 );

			// Do not copy invoice number to recurring orders
			// add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'kco_recurring_do_not_copy_meta_data' ), 10, 4 );
		}

		// Purge kco_incomplete orders hourly
		add_action( 'wp', array( $this, 'register_purge_cron_job' ) );
		add_action( 'garan24_purge_cron_job_hook', array( $this, 'purge_kco_incomplete' ) );

		// Add activate settings field for recurring orders
		add_filter( 'garan24_checkout_form_fields', array( $this, 'add_activate_recurring_option' ) );

		// Register new order status
		add_action( 'init', array( $this, 'register_garan24_incomplete_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_kco_incomplete_to_order_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'kco_incomplete_payment_complete'
		) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'kco_incomplete_payment_complete' ) );

		// Hide "Refunded" and "KCO Incomplete" statuses for KCO orders
		add_filter( 'wc_order_statuses', array( $this, 'remove_refunded_and_kco_incomplete' ), 1000 );

		// Hide "Manual Refund" button for KCO orders
		add_action( 'admin_head', array( $this, 'remove_refund_manually' ) );

		// Cancel unpaid orders for KCO orders too
		add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'cancel_unpaid_kco' ), 10, 2 );
	}

	/**
	 * Cancel unpaid KCO orders if the option is enabled
	 *
	 * @param  $cancel    boolean    Cancel or not
	 * @param  $order    Object    WooCommerce order object
	 *
	 * @return boolean
	 * @since  2.0
	 **/
	function cancel_unpaid_kco( $cancel, $order ) {
		if ( 'garan24_checkout' == get_post_meta( $order->id, '_created_via', true ) ) {
			$cancel = true;
		}

		return $cancel;
	}

	/**
	 * Remove "Refunded" and "KCO Incomplete" statuses from the dropdown for KCO orders
	 *
	 * @since  2.0
	 **/
	function remove_refunded_and_kco_incomplete( $order_statuses ) {
		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( is_object( $screen ) && 'shop_order' == $screen->id ) {
				if ( isset( $_GET['post'] ) && absint( $_GET['post'] ) == $_GET['post'] ) {
					$order_id = $_GET['post'];
					$order    = wc_get_order( $order_id );

					if ( false != $order && 'refunded' != $order->get_status() && 'garan24_checkout' == get_post_meta( $order_id, '_created_via', true ) ) {
						/**
						 * Filter that allows merchants to show Refunded status in order status dropdown.
						 *
						 * @param boolean $hide Default value true.
						 */
						if ( apply_filters( 'garan24_checkout_hide_refunded_status', true ) ) {
							unset( $order_statuses['wc-refunded'] );
						}
					}

					// NEVER make it possible to change status to KCO Incomplete
					if ( false != $order ) {
						unset( $order_statuses['wc-kco-incomplete'] );
					}
				}
			}
		}

		return $order_statuses;
	}

	/**
	 * Hide "Refund x Manually" for KCO orders
	 *
	 * @since  2.0
	 **/
	function remove_refund_manually() {
		$screen = get_current_screen();

		if ( 'shop_order' == $screen->id ) {
			if ( absint( $_GET['post'] ) == $_GET['post'] ) {
				$order_id = $_GET['post'];
				$order    = wc_get_order( $order_id );

				if ( false != $order && 'garan24_checkout' == get_post_meta( $order_id, '_created_via', true ) ) {
					echo '<style>.do-manual-refund{display:none !important;}</style>';
				}
			}
		}
	}

	/**
	 * Register purge KCO Incomplete orders cronjob
	 *
	 * @since  2.0
	 **/
	function register_purge_cron_job() {
		if ( ! wp_next_scheduled( 'garan24_purge_cron_job_hook' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'daily', 'garan24_purge_cron_job_hook' );
		}
	}

	/**
	 * Purge KCO Incomplete orders
	 *
	 * Deletes KCO Incomplete orders that are older than one day and have KCO Incomplete email
	 * set to guest_checkout@garan24.com indicating customer email was never captured before
	 * checkout was abandoned and all KCO incomplete orders older than 2 weeks.
	 *
	 * @since  2.0
	 **/
	function purge_kco_incomplete() {
		// Get KCO Incomplete orders that are older than a day and don't have a real customer email captured.
		$kco_incomplete_args = array(
			'post_type'      => 'shop_order',
			'post_status'    => 'wc-kco-incomplete',
			'posts_per_page' => - 1,
			'date_query'     => array(
				array(
					'before' => '1 day ago'
				)
			)

		);

		$kco_incomplete_query = new WP_Query( $kco_incomplete_args );

		if ( $kco_incomplete_query->have_posts() ) {
			while ( $kco_incomplete_query->have_posts() ) {
				$kco_incomplete_query->the_post();
				global $post;
				if ( 'guest_checkout@garan24.com' == get_post_meta( $post->ID, '_kco_incomplete_customer_email', true ) ) {
					wp_delete_post( $post->ID, true );
				}
			}
		}

		wp_reset_postdata();

		// Get all KCO Incomplete orders older than 2 weeks.
		$kco_incomplete_args_1 = array(
			'post_type'      => 'shop_order',
			'post_status'    => 'wc-kco-incomplete',
			'posts_per_page' => - 1,
			'date_query'     => array(
				array(
					'before' => '2 weeks ago'
				)
			)

		);

		$kco_incomplete_query_1 = new WP_Query( $kco_incomplete_args_1 );

		if ( $kco_incomplete_query_1->have_posts() ) {
			while ( $kco_incomplete_query_1->have_posts() ) {
				$kco_incomplete_query_1->the_post();
				global $post;
				wp_delete_post( $post->ID, true );
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Register KCO Incomplete order status
	 *
	 * @since  2.0
	 **/
	function register_garan24_incomplete_order_status() {
		if ( 'yes' == $this->debug ) {
			$show_in_admin_status_list = true;
		} else {
			$show_in_admin_status_list = false;
		}
		register_post_status( 'wc-kco-incomplete', array(
			'label'                     => 'KCO incomplete',
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => $show_in_admin_status_list,
			'label_count'               => _n_noop( 'KCO incomplete <span class="count">(%s)</span>', 'KCO incomplete <span class="count">(%s)</span>' ),
		) );
	}


	/**
	 * Add KCO Incomplete to list of order status
	 *
	 * @since  2.0
	 **/
	function add_kco_incomplete_to_order_statuses( $order_statuses ) {
		// Add this status only if not in account page (so it doesn't show in My Account list of orders)
		if ( ! is_account_page() ) {
			$order_statuses['wc-kco-incomplete'] = 'Incomplete Garan24 Checkout';
		}

		return $order_statuses;
	}

	/**
	 * Allows $order->payment_complete to work for KCO incomplete orders
	 *
	 * @since  2.0
	 **/
	function kco_incomplete_payment_complete( $order_statuses ) {
		$order_statuses[] = 'kco-incomplete';

		return $order_statuses;
	}

	/**
	 * Add options for recurring order activation.
	 *
	 * @since  2.0
	 **/
	function add_activate_recurring_option( $settings ) {
		if ( class_exists( 'WC_Subscriptions_Manager' ) ) {
			$settings['activate_recurring_title'] = array(
				'title' => __( 'Recurring orders', 'woocommerce-gateway-garan24' ),
				'type'  => 'title',
			);
			$settings['activate_recurring']       = array(
				'title'   => __( 'Automatically activate recurring orders', 'woocommerce-gateway-garan24' ),
				'type'    => 'checkbox',
				'label'   => __( 'If this option is checked recurring orders will be activated automatically', 'woocommerce-gateway-garan24' ),
				'default' => 'yes'
			);
		}

		return $settings;
	}

	/**
	 * Scheduled subscription payment.
	 *
	 * @since  2.0
	 **/
	function scheduled_subscription_payment( $amount_to_charge, $order ) {
		// Check if order was created using this method
		if ( $this->id == get_post_meta( $order->id, '_payment_method', true ) ) {
			// Prevent hook from firing twice
			if ( ! get_post_meta( $order->id, '_schedule_garan24_subscription_payment', true ) ) {
				$result = $this->process_subscription_payment( $amount_to_charge, $order );

				if ( false == $result ) {
					WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
				} else {
					WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
					$order->payment_complete(); // Need to mark new order complete, so Subscription is marked as Active again
				}
				add_post_meta( $order->id, '_schedule_garan24_subscription_payment', 'no', true );
			} else {
				delete_post_meta( $order->id, '_schedule_garan24_subscription_payment', 'no' );
			}
		}
	}


	/**
	 * Process subscription payment.
	 *
	 * @since  2.0
	 **/
	function process_subscription_payment( $amount_to_charge, $order ) {

		if ( 0 == $amount_to_charge ) {
			// Payment complete
			$order->payment_complete();

			return true;
		}

		$garan24_recurring_token = get_post_meta( $order->id, '_garan24_recurring_token', true );
		$garan24_currency        = get_post_meta( $order->id, '_order_currency', true );
		$garan24_country         = get_post_meta( $order->id, '_billing_country', true );
		$garan24_locale          = get_post_meta( $order->id, '_garan24_locale', true );

		// Can't use same methods to retrieve Eid and secret that are used in frontend.
		// Need to use order billing country as base instead.
		$garan24_checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$garan24_country_lowercase = strtolower( $garan24_country );
		$garan24_eid = $garan24_checkout_settings[ 'eid_' . $garan24_country_lowercase ];
		$garan24_secret = $garan24_checkout_settings[ 'secret_' . $garan24_country_lowercase ];

		$garan24_billing  = array(
			'postal_code'    => get_post_meta( $order->id, '_billing_postcode', true ),
			'email'          => get_post_meta( $order->id, '_billing_email', true ),
			'country'        => get_post_meta( $order->id, '_billing_country', true ),
			'city'           => get_post_meta( $order->id, '_billing_city', true ),
			'family_name'    => get_post_meta( $order->id, '_billing_last_name', true ),
			'given_name'     => get_post_meta( $order->id, '_billing_first_name', true ),
			'street_address' => get_post_meta( $order->id, '_billing_address_1', true ),
			'phone'          => get_post_meta( $order->id, '_billing_phone', true )
		);
		$shipping_email  = get_post_meta( $order->id, '_shipping_email', true ) ? get_post_meta( $order->id, '_shipping_email', true ) : get_post_meta( $order->id, '_billing_email', true );
		$shipping_phone  = get_post_meta( $order->id, '_shipping_phone', true ) ? get_post_meta( $order->id, '_shipping_phone', true ) : get_post_meta( $order->id, '_billing_phone', true );
		$garan24_shipping = array(
			'postal_code'    => get_post_meta( $order->id, '_shipping_postcode', true ),
			'email'          => $shipping_email,
			'country'        => get_post_meta( $order->id, '_shipping_country', true ),
			'city'           => get_post_meta( $order->id, '_shipping_city', true ),
			'family_name'    => get_post_meta( $order->id, '_shipping_last_name', true ),
			'given_name'     => get_post_meta( $order->id, '_shipping_first_name', true ),
			'street_address' => get_post_meta( $order->id, '_shipping_address_1', true ),
			'phone'          => $shipping_phone
		);

		// Products in subscription
		$cart = array();
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item_key => $item ) {

				$_product = $order->get_product_from_item( $item );
				if ( $_product->exists() && $item['qty'] ) {

					// Get SKU or product id
					$reference = '';
					if ( $_product->get_sku() ) {
						$reference = $_product->get_sku();
					} elseif ( $_product->variation_id ) {
						$reference = $_product->variation_id;
					} else {
						$reference = $_product->id;
					}

					$recurring_price    = $order->get_item_total( $item, true ) * 100;
					$recurring_tax_rate = ( $item['line_tax'] / $item['line_total'] ) * 10000;

					$cart[] = array(
						'reference'     => strval( $reference ),
						'name'          => utf8_decode( $item['name'] ),
						'quantity'      => (int) $item['qty'],
						'unit_price'    => (int) $recurring_price,
						'discount_rate' => 0,
						'tax_rate'      => (int) $recurring_tax_rate
					);
				}
			}
		}

		// Shipping
		if ( $order->get_total_shipping() > 0 ) {
			$shipping_price    = ( $order->get_total_shipping() + $order->get_shipping_tax() ) * 100;
			$shipping_tax_rate = ( $order->get_shipping_tax() / $order->get_total_shipping() ) * 10000;
			$cart[]            = array(
				'type'       => 'shipping_fee',
				'reference'  => 'SHIPPING',
				'name'       => __( 'Shipping Fee', 'woocommerce-gateway-garan24' ),
				'quantity'   => 1,
				'unit_price' => (int) $shipping_price,
				'tax_rate'   => (int) $shipping_tax_rate
			);
		}

		$create = array();
		if ( 'yes' == $this->activate_recurring ) {
			$create['activate'] = true;
		} else {
			$create['activate'] = false;
		}
		$create['purchase_currency']  = $garan24_currency;
		$create['purchase_country']   = $garan24_country;
		$create['locale']             = $garan24_locale;
		$create['merchant']['id']     = $garan24_eid;
		$create['billing_address']    = $garan24_billing;
		$create['shipping_address']   = $garan24_shipping;
		$create['merchant_reference'] = array(
			'orderid1' => ltrim( $order->get_order_number(), '#' )
		);
		$create['cart']               = array();
		foreach ( $cart as $item ) {
			$create['cart']['items'][] = $item;
		}

		$connector    = Garan24_Checkout_Connector::create( $garan24_secret, $this->garan24_server );
		$garan24_order = new Garan24_Checkout_RecurringOrder( $connector, $garan24_recurring_token );

		try {
			$garan24_order->create( $create );
			if ( isset( $garan24_order['invoice'] ) ) {
				add_post_meta( $order->id, '_garan24_order_invoice_recurring', $garan24_order['invoice'], true );
				$order->add_order_note( __( 'Garan24 subscription payment invoice number: ', 'woocommerce-gateway-garan24' ) . $garan24_order['invoice'] );
			} elseif ( isset( $garan24_order['reservation'] ) ) {
				add_post_meta( $order->id, '_garan24_order_reservation_recurring', $garan24_order['reservation'], true );
				$order->add_order_note( __( 'Garan24 subscription payment reservation number: ', 'woocommerce-gateway-garan24' ) . $garan24_order['reservation'] );
			}

			return true;
		} catch ( Garan24_Checkout_ApiErrorException $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 subscription payment failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );

			// error_log( var_export( $e, true ) );
			return false;
		}
	}

	/**
	 * Do not copy Garan24 invoice number from completed subscription order to its renewal orders.
	 *
	 * @since  2.0
	 **/
	function kco_recurring_do_not_copy_meta_data( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
		$order_meta_query .= " AND `meta_key` NOT IN ('_garan24_invoice_number')";

		return $order_meta_query;
	}


	//
	// AJAX callbacks
	//


	/**
	 * Garan24 Checkout cart AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_cart_callback_update() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		$updated_item_key = $_REQUEST['cart_item_key'];
		$new_quantity     = $_REQUEST['new_quantity'];

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$cart_items      = $woocommerce->cart->get_cart();
		$updated_item    = $cart_items[ $updated_item_key ];
		$updated_product = wc_get_product( $updated_item['product_id'] );

		// Update WooCommerce cart and transient order item
		$garan24_sid = $woocommerce->session->get( 'garan24_sid' );
		$woocommerce->cart->set_quantity( $updated_item_key, $new_quantity );
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$this->update_or_create_local_order();

		$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

		if ( WC()->session->get( 'garan24_checkout' ) ) {
			$this->ajax_update_garan24_order();
		}

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Garan24 Checkout cart AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_cart_callback_remove() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$cart_items = $woocommerce->cart->get_cart();

		// Remove line item row
		$removed_item_key = esc_attr( $_REQUEST['cart_item_key_remove'] );
		$woocommerce->cart->remove_cart_item( $removed_item_key );

		if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
			$woocommerce->cart->calculate_shipping();
			$woocommerce->cart->calculate_fees();
			$woocommerce->cart->calculate_totals();
			$this->update_or_create_local_order();
		} else {
			if ( $woocommerce->session->get( 'ongoing_garan24_order' ) ) {
				wp_delete_post( $woocommerce->session->get( 'ongoing_garan24_order' ) );
				$woocommerce->session->__unset( 'ongoing_garan24_order' );
			}
		}

		// This needs to be sent back to JS, so cart widget can be updated
		$data['item_count']  = $woocommerce->cart->get_cart_contents_count();
		$data['cart_url']    = $woocommerce->cart->get_cart_url();
		$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

		// Update ongoing Garan24 order
		if ( WC()->session->get( 'garan24_checkout' ) ) {
			$this->ajax_update_garan24_order();
		}

		wp_send_json_success( $data );
		wp_die();
	}


	/**
	 * Garan24 Checkout coupons AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_coupons_callback() {
		global $woocommerce;

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		$data = array();

		// Adding coupon
		if ( ! empty( $_REQUEST['coupon'] ) && is_string( $_REQUEST['coupon'] ) ) {

			$coupon          = $_REQUEST['coupon'];
			$coupon_success  = $woocommerce->cart->add_discount( $coupon );
			$applied_coupons = $woocommerce->cart->applied_coupons;
			$woocommerce->session->set( 'applied_coupons', $applied_coupons );
			$woocommerce->cart->calculate_totals();
			wc_clear_notices(); // This notice handled by Garan24 plugin

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			$woocommerce->cart->calculate_shipping();
			$woocommerce->cart->calculate_fees();
			$woocommerce->cart->calculate_totals();

			$this->update_or_create_local_order();

			$coupon_object = new WC_Coupon( $coupon );

			$amount                 = wc_price( $woocommerce->cart->get_coupon_discount_amount( $coupon, $woocommerce->cart->display_cart_ex_tax ) );
			$data['amount']         = $amount;
			$data['coupon_success'] = $coupon_success;
			$data['coupon']         = $coupon;
			$data['widget_html']    = $this->garan24_checkout_get_kco_widget_html();

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}


			if ( WC()->session->get( 'garan24_checkout' ) ) {
				$this->ajax_update_garan24_order();
			}

		}

		wp_send_json_success( $data );
		wp_die();
	}


	/**
	 * Garan24 Checkout coupons AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_remove_coupon_callback() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		$data = array();

		// Removing coupon
		if ( isset( $_REQUEST['remove_coupon'] ) ) {

			$remove_coupon = $_REQUEST['remove_coupon'];

			$woocommerce->cart->remove_coupon( $remove_coupon );
			$applied_coupons = $woocommerce->cart->applied_coupons;
			$woocommerce->session->set( 'applied_coupons', $applied_coupons );
			$woocommerce->cart->calculate_totals();
			wc_clear_notices(); // This notice handled by Garan24 plugin

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			$woocommerce->cart->calculate_shipping();
			$woocommerce->cart->calculate_fees();
			$woocommerce->cart->calculate_totals();

			$this->update_or_create_local_order();

			$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

			if ( WC()->session->get( 'garan24_checkout' ) ) {
				$this->ajax_update_garan24_order();
			}

		}

		wp_send_json_success( $data );

		wp_die();
	}


	/**
	 * Garan24 Checkout shipping AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_shipping_callback() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		$new_method                = $_REQUEST['new_method'];
		$chosen_shipping_methods[] = wc_clean( $new_method );
		$woocommerce->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$this->update_or_create_local_order();

		$data['new_method']  = $new_method;
		$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

		if ( WC()->session->get( 'garan24_checkout' ) ) {
			$this->ajax_update_garan24_order();
		}

		wp_send_json_success( $data );

		wp_die();
	}


	/**
	 * Garan24 Checkout coupons AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_order_note_callback() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		$data = array();

		// Adding coupon
		if ( isset( $_REQUEST['order_note'] ) && is_string( $_REQUEST['order_note'] ) ) {
			$order_note         = sanitize_text_field( $_REQUEST['order_note'] );
			$data['order_note'] = $order_note;

			if ( WC()->session->get( 'garan24_checkout' ) ) {
				$woocommerce->cart->calculate_shipping();
				$woocommerce->cart->calculate_fees();
				$woocommerce->cart->calculate_totals();

				$orderid       = $this->update_or_create_local_order();
				$order_details = array(
					'ID'           => $orderid,
					'post_excerpt' => $order_note
				);
				$order_update  = wp_update_post( $order_details );
				if ( $this->debug == 'yes' ) {
					$this->log->add( 'garan24', 'ORDERID: ' . $orderid );
				}

				$this->ajax_update_garan24_order();

				WC()->session->set( 'garan24_order_note', $order_note );
			}
		}

		wp_send_json_success( $data );
		wp_die();
	}


	/**
	 * Garan24 Checkout country selector AJAX callback.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_country_callback() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		$data = array();

		if ( isset( $_REQUEST['new_country'] ) && is_string( $_REQUEST['new_country'] ) ) {
			$new_country = sanitize_text_field( $_REQUEST['new_country'] );

			// Reset session
			$garan24_order = null;
			WC()->session->__unset( 'garan24_checkout' );
			WC()->session->__unset( 'garan24_checkout_country' );

			// Store new country as WC session value
			WC()->session->set( 'garan24_euro_country', $new_country );

			// Get new checkout URL
			$lowercase_country = strtolower( $new_country );
			$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
			$data['new_url']   = $checkout_settings["garan24_checkout_url_$lowercase_country"];

			// Send data back to JS function
			$data['garan24_euro_country'] = $new_country;
		}

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Pushes Garan24 order update in AJAX calls.
	 *
	 * Used to capture customer address, recalculate tax and shipping for order and user session
	 *
	 * @since  2.0
	 **/
	function kco_iframe_change_cb() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;
		$data = array();

		// Check stock
		if ( is_wp_error( $woocommerce->cart->check_cart_item_stock() ) ) {
			wp_send_json_error();
			wp_die();
		}

		// Capture email
		if ( isset( $_REQUEST['email'] ) && is_string( $_REQUEST['email'] ) && ! is_user_logged_in() ) {
			$this->update_or_create_local_order( $_REQUEST['email'] );
			$orderid         = $woocommerce->session->get( 'ongoing_garan24_order' );
			$data['orderid'] = $orderid;
			$connector       = Garan24_Checkout_Connector::create( $this->garan24_secret, $this->garan24_server );

			$garan24_order = new Garan24_Checkout_Order( $connector, WC()->session->get( 'garan24_checkout' ) );

			$garan24_order->fetch();

			$update['merchant']['push_uri']         = add_query_arg( array(
				'sid' => $orderid
			), $garan24_order['merchant']['push_uri'] );
			$update['merchant']['confirmation_uri'] = add_query_arg( array(
				'sid'            => $orderid,
				'order-received' => $orderid
			), $garan24_order['merchant']['confirmation_uri'] );

			$garan24_order->update( $update );
		}

		// Capture postal code
		if ( isset( $_REQUEST['postal_code'] ) && is_string( $_REQUEST['postal_code'] ) && WC_Validation::is_postcode( $_REQUEST['postal_code'], $this->garan24_country ) ) {
			$woocommerce->customer->set_shipping_postcode( $_REQUEST['postal_code'] );

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			// Update user session
			$woocommerce->cart->calculate_shipping();
			$woocommerce->cart->calculate_fees();
			$woocommerce->cart->calculate_totals();

			// Update ongoing WooCommerce order
			$this->update_or_create_local_order();

			$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

			if ( WC()->session->get( 'garan24_checkout' ) ) {
				$this->ajax_update_garan24_order();
			}
		}

		wp_send_json_success( $data );
		wp_die();
	}


	/**
	 * Garan24 order shipping address change callback function.
	 *
	 * @since  2.0
	 **/
	function kco_iframe_shipping_address_change_cb() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;
		$data = array();

		// Capture postal code
		if ( isset( $_REQUEST['postal_code'] ) && is_string( $_REQUEST['postal_code'] ) ) {
			$woocommerce->customer->set_postcode( $_REQUEST['postal_code'] );
			$woocommerce->customer->set_shipping_postcode( $_REQUEST['postal_code'] );
		}

		if ( isset( $_REQUEST['region'] ) && is_string( $_REQUEST['region'] ) ) {
			$woocommerce->customer->set_state( $_REQUEST['region'] );
			$woocommerce->customer->set_shipping_state( $_REQUEST['region'] );
		}

		if ( isset( $_REQUEST['country'] ) && is_string( $_REQUEST['country'] ) ) {
			$woocommerce->customer->set_country( 'US' );
			$woocommerce->customer->set_shipping_country( 'US' );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$this->update_or_create_local_order();

		$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

		if ( WC()->session->get( 'garan24_checkout' ) ) {
			$this->ajax_update_garan24_order();
		}

		wp_send_json_success( $data );

		wp_die();
	}


	/**
	 * Garan24 order shipping option change callback function.
	 *
	 * @since  2.0
	 **/
	function kco_iframe_shipping_option_change_cb() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'garan24_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		global $woocommerce;

		$new_method                = $_REQUEST['new_method'];
		$chosen_shipping_methods[] = wc_clean( $new_method );
		$woocommerce->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		$this->update_or_create_local_order();

		$data['new_method']  = $new_method;
		$data['widget_html'] = $this->garan24_checkout_get_kco_widget_html();

		if ( WC()->session->get( 'garan24_checkout' ) ) {
			$this->ajax_update_garan24_order();
		}

		wp_send_json_success( $data );

		wp_die();
	}


	/**
	 * Pushes Garan24 order update in AJAX calls.
	 *
	 * @since  2.0
	 **/
	function ajax_update_garan24_order() {
		global $woocommerce;

		// Check if Euro is selected, get correct country
		if ( 'EUR' == get_woocommerce_currency() && WC()->session->get( 'garan24_euro_country' ) ) {
			$garan24_c     = strtolower( WC()->session->get( 'garan24_euro_country' ) );
			$eid          = $this->settings["eid_$garan24_c"];
			$sharedSecret = $this->settings["secret_$garan24_c"];
		} else {
			$eid          = $this->garan24_eid;
			$sharedSecret = $this->garan24_secret;
		}

		if ( $this->is_rest() ) {
			if ( $this->testmode == 'yes' ) {
				if ( 'gb' == $this->garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
				} elseif ( 'us' == $this->garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
				}
			} else {
				if ( 'gb' == $this->garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
				} elseif ( 'us' == $this->garan24_country ) {
					$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
				}
			}
			$connector = Garan24\Rest\Transport\Connector::create( $eid, $sharedSecret, $garan24_server_url );

			$garan24_order = new \Garan24\Rest\Checkout\Order( $connector, WC()->session->get( 'garan24_checkout' ) );
		} else {
			$connector = Garan24_Checkout_Connector::create( $sharedSecret, $this->garan24_server );

			$garan24_order = new Garan24_Checkout_Order( $connector, WC()->session->get( 'garan24_checkout' ) );

			$garan24_order->fetch();
		}

		// Process cart contents and prepare them for Garan24
		include_once( GARAN24_DIR . 'classes/class-wc-to-garan24.php' );
		$wc_to_garan24 = new WC_Gateway_Garan24_WC2K( $this->is_rest(), $this->garan24_country );
		$cart         = $wc_to_garan24->process_cart_contents();

		if ( 0 == count( $cart ) ) {
			$garan24_order = null;
		} else {
			// Reset cart
			if ( $this->is_rest() ) {
				$update['order_lines'] = array();
				$garan24_order_total    = 0;
				$garan24_tax_total      = 0;

				foreach ( $cart as $item ) {
					$update['order_lines'][] = $item;
					$garan24_order_total += $item['total_amount'];

					// Process sales_tax item differently
					if ( array_key_exists( 'type', $item ) && 'sales_tax' == $item['type'] ) {
						$garan24_tax_total += $item['total_amount'];
					} else {
						$garan24_tax_total += $item['total_tax_amount'];
					}
				}
				$update['order_amount']     = $garan24_order_total;
				$update['order_tax_amount'] = $garan24_tax_total;
			} else {
				$update['cart']['items'] = array();
				foreach ( $cart as $item ) {
					$update['cart']['items'][] = $item;
				}
			}

			try {
				$garan24_order->update( apply_filters( 'kco_update_order', $update ) );
			} catch ( Exception $e ) {
				if ( $this->debug == 'yes' ) {
					$this->log->add( 'garan24', 'KCO ERROR: ' . var_export( $e, true ) );
					error_log( 'ERROR: ' . var_export( $e, true ) );
				}
			}
		}
	}


	//
	//
	//


	/**
	 * Gets Garan24 checkout widget HTML.
	 * Used in KCO widget.
	 *
	 * @param  $atts Array
	 *
	 * @return String shortcode output
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_kco_widget_html( $atts = null ) {
		global $woocommerce;

		ob_start();
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();
		?>

		<!-- Coupons -->
		<?php woocommerce_checkout_coupon_form(); ?>

		<!-- Cart items -->
		<?php echo $this->garan24_checkout_get_cart_contents_html( $atts ); ?>

		<!-- Totals -->
		<div>
			<table id="kco-totals">
				<tbody>
				<tr id="kco-page-subtotal">
					<td class="kco-col-desc"><?php _e( 'Subtotal', 'woocommerce-gateway-garan24' ); ?></td>
					<td id="kco-page-subtotal-amount" class="kco-col-number kco-rightalign"><span
							class="amount"><?php echo $woocommerce->cart->get_cart_subtotal(); ?></span></td>
				</tr>

				<?php echo $this->garan24_checkout_get_shipping_options_row_html(); // Shipping options ?>

				<?php echo $this->garan24_checkout_get_fees_row_html(); // Fees ?>

				<?php echo $this->garan24_checkout_get_coupon_rows_html(); // Coupons ?>

				<?php echo $this->garan24_checkout_get_taxes_rows_html(); // Taxes ?>

				<?php /* Cart total */ ?>
				<tr id="kco-page-total">
					<td class="kco-bold"><?php _e( 'Total', 'woocommerce-gateway-garan24' ); ?></a></td>
					<td id="kco-page-total-amount" class="kco-rightalign kco-bold"><span
							class="amount"><?php echo $woocommerce->cart->get_total(); ?></span></td>
				</tr>
				<?php /* Cart total */ ?>
				</tbody>
			</table>
		</div>

		<!-- Order note -->
		<?php if ( 'false' != $atts['order_note'] ) { ?>
			<div>
				<form>
					<?php
					if ( WC()->session->get( 'garan24_order_note' ) ) {
						$order_note = WC()->session->get( 'garan24_order_note' );
					} else {
						$order_note = '';
					}
					?>
					<textarea id="garan24-checkout-order-note" class="input-text" name="garan24-checkout-order-note"
					          placeholder="<?php _e( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce-gateway-garan24' ); ?>"><?php echo $order_note; ?></textarea>
				</form>
			</div>
		<?php }

		return ob_get_clean();
	}


	/**
	 * Gets cart contents as formatted HTML.
	 * Used in KCO widget.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_cart_contents_html( $atts ) {
		global $woocommerce;

		ob_start();
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();


		$hide_columns = array();
		if ( '' != $atts['hide_columns'] ) {
			$hide_columns = explode( ',', $atts['hide_columns'] );
		}
		?>
		<div>
			<table id="garan24-checkout-cart">
				<tbody>
				<tr>
					<?php if ( ! in_array( 'remove', $hide_columns ) ) { ?>
						<th class="product-remove kco-leftalign"></th>
					<?php } ?>
					<th class="product-name kco-leftalign"><?php _e( 'Product', 'woocommerce-gateway-garan24' ); ?></th>
					<?php if ( ! in_array( 'price', $hide_columns ) ) { ?>
						<th class="product-price kco-centeralign"><?php _e( 'Price', 'woocommerce-gateway-garan24' ); ?></th>
					<?php } ?>
					<th class="product-quantity kco-centeralign"><?php _e( 'Quantity', 'woocommerce-gateway-garan24' ); ?></th>
					<th class="product-total kco-rightalign"><?php _e( 'Total', 'woocommerce-gateway-garan24' ); ?></th>
				</tr>
				<?php
				foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
					$_product          = $cart_item['data'];
					$cart_item_product = wc_get_product( $cart_item['product_id'] );
					echo '<tr>';
					if ( ! in_array( 'remove', $hide_columns ) ) {
						echo '<td class="kco-product-remove kco-leftalign"><a href="#">x</a></td>';
					}
					echo '<td class="product-name kco-leftalign">';
					if ( ! $_product->is_visible() ) {
						echo apply_filters( 'woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key ) . '&nbsp;';
					} else {
						echo apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s </a>', $_product->get_permalink( $cart_item ), $_product->get_title() ), $cart_item, $cart_item_key );
					}
					// Meta data
					echo $woocommerce->cart->get_item_data( $cart_item );
					echo '</td>';
					if ( ! in_array( 'price', $hide_columns ) ) {
						echo '<td class="product-price kco-centeralign"><span class="amount">';
						echo $woocommerce->cart->get_product_price( $_product );
						echo '</span></td>';
					}
					echo '<td class="product-quantity kco-centeralign" data-cart_item_key="' . $cart_item_key . '">';
					if ( $_product->is_sold_individually() ) {
						$product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', esc_attr( $cart_item_key ) );
					} else {
						$product_quantity = woocommerce_quantity_input( array(
							'input_name'  => "cart[{$cart_item_key}][qty]",
							'input_value' => $cart_item['quantity'],
							'max_value'   => $_product->backorders_allowed() ? '' : $_product->get_stock_quantity(),
							'min_value'   => '1'
						), $_product, false );
					}
					echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key );
					echo '</td>';
					echo '<td class="product-total kco-rightalign"><span class="amount">';
					echo apply_filters( 'woocommerce_cart_item_subtotal', $woocommerce->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
					echo '</span></td>';
					echo '</tr>';
				}
				?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Gets shipping options as formatted HTML.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_shipping_options_row_html() {
		global $woocommerce;

		ob_start();
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();
		?>
		<tr id="kco-page-shipping">
			<?php
			// if ( WC()->session->get( 'garan24_is_rest', false ) ) { // Just show shipping cost for Rest
			// Temporarily commented out while Garan24 works on this feaure and replaced by the check below
			// that always returns true.
			?>
			<?php if ( 1 > 2 ) { // Just show shipping cost for Rest ?>
				<td>
					<?php _e( 'Shipping', 'woocommerce-gateway-garan24' ); ?>
				</td>
				<td id="kco-page-shipping-total" class="kco-rightalign">
					<?php echo $woocommerce->cart->get_cart_shipping_total(); ?>
				</td>
			<?php } else { ?>
				<td>
					<?php
					$woocommerce->cart->calculate_shipping();
					$packages = $woocommerce->shipping->get_packages();
					foreach ( $packages as $i => $package ) {
						$chosen_method        = isset( $woocommerce->session->chosen_shipping_methods[ $i ] ) ? $woocommerce->session->chosen_shipping_methods[ $i ] : '';
						$available_methods    = $package['rates'];
						$show_package_details = sizeof( $packages ) > 1;
						$index                = $i;
						?>
						<?php if ( ! empty( $available_methods ) ) { ?>
							<?php if ( 1 === count( $available_methods ) ) {
								$method = current( $available_methods );
								echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?>
								<input type="hidden" name="shipping_method[<?php echo esc_attr( $index ); ?>]"
								       data-index="<?php echo esc_attr( $index ); ?>"
								       id="shipping_method_<?php echo esc_attr( $index ); ?>"
								       value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method"/>
							<?php } else { ?>
								<p style="margin: 0 0 0.5em !important; padding: 0 !important;"><?php _e( 'Shipping', 'woocommerce-gateway-garan24' ); ?></p>
								<ul id="shipping_method">
									<?php foreach ( $available_methods as $method ) : ?>
										<li>
											<input style="margin-left:3px" type="radio"
											       name="shipping_method[<?php echo esc_attr( $index ); ?>]"
											       data-index="<?php echo esc_attr( $index ); ?>"
											       id="shipping_method_<?php echo esc_attr( $index ); ?>_<?php echo esc_attr( sanitize_title( $method->id ) ); ?>"
											       value="<?php echo esc_attr( $method->id ); ?>" <?php checked( $method->id, $chosen_method ); ?>
											       class="shipping_method"/>
											<label
												for="shipping_method_<?php echo esc_attr( $index ); ?>_<?php echo esc_attr( sanitize_title( $method->id ) ); ?>"><?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?></label>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php } ?>
						<?php } ?>
						<?php
					}
					?>
				</td>
				<td id="kco-page-shipping-total" class="kco-rightalign">
					<?php echo $woocommerce->cart->get_cart_shipping_total(); ?>
				</td>
			<?php } ?>
		</tr>
		<?php
		return ob_get_clean();
	}


	/**
	 * Gets shipping options as formatted HTML.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_fees_row_html() {
		global $woocommerce;

		ob_start();
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_fees();
		$woocommerce->cart->calculate_totals();

		// Fees
		foreach ( $woocommerce->cart->get_fees() as $cart_fee ) {
			echo '<tr class="kco-fee">';
			echo '<td class="kco-col-desc">';
			echo strip_tags( $cart_fee->name );
			echo '</td>';

			echo '<td class="kco-col-number kco-rightalign"><span class="amount">';
			// echo wc_price( $cart_fee->amount + $cart_fee->tax );
			echo wc_cart_totals_fee_html( $cart_fee );
			echo '</span></td>';
			echo '</tr>';
		}

		return ob_get_clean();
	}


	/**
	 * Gets coupons as formatted HTML.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_coupon_rows_html() {
		global $woocommerce;

		ob_start();
		foreach ( $woocommerce->cart->get_coupons() as $code => $coupon ) { ?>
			<tr class="kco-applied-coupon">
				<td>
					<?php echo apply_filters( 'woocommerce_cart_totals_coupon_label', esc_html( __( 'Coupon:',
							'woocommerce' ) . ' ' . $coupon->code ), $coupon ); ?>
					<a class="kco-remove-coupon" data-coupon="<?php echo $coupon->code; ?>"
					   href="#"><?php _e( '(remove)', 'woocommerce-gateway-garan24' ); ?></a>
				</td>
				<td class="kco-rightalign">
					-<?php echo wc_price( $woocommerce->cart->get_coupon_discount_amount( $code, $woocommerce->cart->display_cart_ex_tax ) ); ?></td>
			</tr>
		<?php }

		return ob_get_clean();
	}


	/**
	 * Gets coupons as formatted HTML.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_get_taxes_rows_html() {
		ob_start();
		if ( wc_tax_enabled() && 'excl' === WC()->cart->tax_display_cart ) {
			if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
				foreach ( WC()->cart->get_tax_totals() as $code => $tax ) { ?>
					<tr class="kco-tax-rate kco-tax-rate-<?php echo sanitize_title( $code ); ?>">
						<td><?php echo esc_html( $tax->label ); ?></td>
						<td class="kco-rightalign"
						    data-title="<?php echo esc_html( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php }
			} else { ?>
				<tr class="tax-total">
					<td><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></td>
					<td class="kco-rightalign" data-title="<?php echo esc_html( WC()->countries->tax_or_vat() );
					?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php }
		}

		return ob_get_clean();
	}


	/**
	 * WooCommerce cart to Garan24 cart items.
	 *
	 * Helper functions that format WooCommerce cart items for Garan24 order items.
	 *
	 * @since  2.0
	 **/
	function cart_to_garan24() {
		global $woocommerce;

		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_totals();

		/**
		 * Process cart contents
		 */
		if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {

			foreach ( $woocommerce->cart->get_cart() as $cart_item ) {

				if ( $cart_item['quantity'] ) {

					$_product = wc_get_product( $cart_item['product_id'] );

					// We manually calculate the tax percentage here
					if ( $_product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
						// Calculate tax percentage
						$item_tax_percentage = round( $cart_item['line_subtotal_tax'] / $cart_item['line_subtotal'], 2 ) * 100;
					} else {
						$item_tax_percentage = 00;
					}

					$cart_item_data = $cart_item['data'];
					$cart_item_name = $cart_item_data->post->post_title;

					if ( isset( $cart_item['item_meta'] ) ) {
						$item_meta = new WC_Order_Item_Meta( $cart_item['item_meta'] );
						if ( $meta = $item_meta->display( true, true ) ) {
							$item_name .= ' ( ' . $meta . ' )';
						}
					}

					// apply_filters to item price so we can filter this if needed
					$garan24_item_price_including_tax = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
					$item_price                      = apply_filters( 'garan24_item_price_including_tax', $garan24_item_price_including_tax );

					// Get SKU or product id
					$reference = '';
					if ( $_product->get_sku() ) {
						$reference = $_product->get_sku();
					} elseif ( $_product->variation_id ) {
						$reference = $_product->variation_id;
					} else {
						$reference = $_product->id;
					}

					$total_amount = (int) ( $cart_item['line_total'] + $cart_item['line_tax'] ) * 100;

					$item_price = number_format( $item_price * 100, 0, '', '' ) / $cart_item['quantity'];
					// Check if there's a discount applied

					if ( $cart_item['line_subtotal'] > $cart_item['line_total'] ) {
						$item_discount_rate = round( 1 - ( $cart_item['line_total'] / $cart_item['line_subtotal'] ), 2 ) * 10000;
						$item_discount      = ( $item_price * $cart_item['quantity'] - $total_amount );
					} else {
						$item_discount_rate = 0;
						$item_discount      = 0;
					}

					if ( $this->is_rest() ) {
						$garan24_item = array(
							'reference'             => strval( $reference ),
							'name'                  => strip_tags( $cart_item_name ),
							'quantity'              => (int) $cart_item['quantity'],
							'unit_price'            => (int) $item_price,
							'tax_rate'              => intval( $item_tax_percentage . '00' ),
							'total_amount'          => $total_amount,
							'total_tax_amount'      => $cart_item['line_subtotal_tax'] * 100,
							'total_discount_amount' => $item_discount
						);
					} else {
						$garan24_item = array(
							'reference'     => strval( $reference ),
							'name'          => strip_tags( $cart_item_name ),
							'quantity'      => (int) $cart_item['quantity'],
							'unit_price'    => (int) $item_price,
							'tax_rate'      => intval( $item_tax_percentage . '00' ),
							'discount_rate' => $item_discount_rate
						);
					}

					$cart[] = $garan24_item;

				} // End if qty

			} // End foreach

		} // End if sizeof get_items()


		/**
		 * Process shipping
		 */
		if ( $woocommerce->cart->shipping_total > 0 ) {
			// We manually calculate the tax percentage here
			if ( $woocommerce->cart->shipping_tax_total > 0 ) {
				// Calculate tax percentage
				$shipping_tax_percentage = round( $woocommerce->cart->shipping_tax_total / $woocommerce->cart->shipping_total, 2 ) * 100;
			} else {
				$shipping_tax_percentage = 00;
			}

			$shipping_price = number_format( ( $woocommerce->cart->shipping_total + $woocommerce->cart->shipping_tax_total ) * 100, 0, '', '' );

			// Get shipping method name
			$shipping_packages = $woocommerce->shipping->get_packages();
			foreach ( $shipping_packages as $i => $package ) {
				$chosen_method = isset( $woocommerce->session->chosen_shipping_methods[ $i ] ) ? $woocommerce->session->chosen_shipping_methods[ $i ] : '';
				if ( '' != $chosen_method ) {

					$package_rates = $package['rates'];
					foreach ( $package_rates as $rate_key => $rate_value ) {
						if ( $rate_key == $chosen_method ) {
							$garan24_shipping_method = $rate_value->label;
						}
					}
				}
			}
			if ( ! isset( $garan24_shipping_method ) ) {
				$garan24_shipping_method = __( 'Shipping', 'woocommerce-gateway-garan24' );
			}

			$shipping = array(
				'type'       => 'shipping_fee',
				'reference'  => 'SHIPPING',
				'name'       => $garan24_shipping_method,
				'quantity'   => 1,
				'unit_price' => (int) $shipping_price,
				'tax_rate'   => intval( $shipping_tax_percentage . '00' )
			);
			if ( $this->is_rest() ) {
				$shipping['total_amount']     = (int) $shipping_price;
				$shipping['total_tax_amount'] = $woocommerce->cart->shipping_tax_total * 100;
			}
			$cart[] = $shipping;

		}

		return $cart;
	}


	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	function init_form_fields() {
		$this->form_fields = include( GARAN24_DIR . 'includes/settings-checkout.php' );
	}


	/**
	 * Admin Panel Options
	 *
	 * @since 1.0.0
	 */
	public function admin_options() { ?>

		<h3><?php _e( 'Garan24 Checkout', 'woocommerce-gateway-garan24' ); ?></h3>

		<p>
			<?php printf( __( 'With Garan24 Checkout your customers can pay by invoice or credit card. Garan24 Checkout works by replacing the standard WooCommerce checkout form. Documentation <a href="%s" target="_blank">can be found here</a>.', 'woocommerce-gateway-garan24' ), 'https://docs.woothemes.com/document/garan24/' ); ?>
		</p>

		<?php
		// If the WooCommerce terms page isn't set, do nothing.
		$garan24_terms_page = get_option( 'woocommerce_terms_page_id' );
		if ( empty( $garan24_terms_page ) && empty( $this->terms_url ) ) {
			echo '<strong>' . __( 'You need to specify a Terms Page in the WooCommerce settings or in the Garan24 Checkout settings in order to enable the Garan24 Checkout payment method.', 'woocommerce-gateway-garan24' ) . '</strong>';
		}
		?>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table><!--/.form-table-->

	<?php }


	/**
	 * Disabled KCO on regular checkout page
	 *
	 * @since 1.0.0
	 */
	function is_available() {
		if ( defined( 'WOOCOMMERCE_GARAN24_AVAILABLE' ) || ! is_checkout() ) {
			return true;
		}

		return false;
	}


	/**
	 * Set up Garan24 configuration.
	 *
	 * @since  2.0
	 **/
	function configure_garan24( $garan24, $country ) {
		// Country and language
		switch ( $country ) {
			case 'NO' :
			case 'NB' :
				$garan24_country  = 'NO';
				$garan24_language = 'nb-no';
				$garan24_currency = 'NOK';
				$garan24_eid      = $this->eid_no;
				$garan24_secret   = $this->secret_no;
				break;
			case 'FI' :
				$garan24_country = 'FI';
				// Check if WPML is used and determine if Finnish or Swedish is used as language
				if ( class_exists( 'woocommerce_wpml' ) && defined( 'ICL_LANGUAGE_CODE' ) && strtoupper( ICL_LANGUAGE_CODE ) == 'SV' ) {
					$garan24_language = 'sv-fi'; // Swedish
				} else {
					$garan24_language = 'fi-fi'; // Finnish
				}
				$garan24_currency = 'EUR';
				$garan24_eid      = $this->eid_fi;
				$garan24_secret   = $this->secret_fi;
				break;
			case 'SE' :
			case 'SV' :
				$garan24_country  = 'SE';
				$garan24_language = 'sv-se';
				$garan24_currency = 'SEK';
				$garan24_eid      = $this->eid_se;
				$garan24_secret   = $this->secret_se;
				break;
			case 'DE' :
				$garan24_country  = 'DE';
				$garan24_language = 'de-de';
				$garan24_currency = 'EUR';
				$garan24_eid      = $this->eid_de;
				$garan24_secret   = $this->secret_de;
				break;
			case 'AT' :
				$garan24_country  = 'AT';
				$garan24_language = 'de-at';
				$garan24_currency = 'EUR';
				$garan24_eid      = $this->eid_at;
				$garan24_secret   = $this->secret_at;
				break;
			case 'GB' :
				$garan24_country  = 'gb';
				$garan24_language = 'en-gb';
				$garan24_currency = 'gbp';
				$garan24_eid      = $this->eid_uk;
				$garan24_secret   = $this->secret_uk;
				break;
			default:
				$garan24_country  = '';
				$garan24_language = '';
				$garan24_currency = '';
				$garan24_eid      = '';
				$garan24_secret   = '';
		}

		$garan24->config( $eid = $garan24_eid, $secret = $garan24_secret, $country = $country, $language = $garan24_language, $currency = $garan24_currency, $mode = $this->garan24_mode, $pcStorage = 'json', $pcURI = '/srv/pclasses.json', $ssl = $this->garan24_ssl, $candice = false );
	}


	/**
	 * Render checkout page
	 *
	 * @since 1.0.0
	 */
	function get_garan24_checkout_page() {
		global $woocommerce;
		global $current_user;
		get_currentuserinfo();

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'garan24', 'KCO page about to render...' );
		}

		// Check if Garan24 order exists, if it does display thank you page
		// otherwise display checkout page
		if ( isset( $_GET['garan24_order'] ) ) { // Display Order response/thank you page via iframe from Garan24

			ob_start();
			include( GARAN24_DIR . 'includes/checkout/thank-you.php' );

			return ob_get_clean();

		} else { // Display Checkout page

			ob_start();
			include( GARAN24_DIR . 'includes/checkout/checkout.php' );

			return ob_get_clean();

		} // End if isset($_GET['garan24_order'])
	} // End Function


	/**
	 * Creates a WooCommerce order, or updates if already created
	 *
	 * @since 1.0.0
	 */
	function update_or_create_local_order( $customer_email = '' ) {
		if ( is_user_logged_in() ) {
			global $current_user;
			$customer_email = $current_user->user_email;
		}

		if ( '' == $customer_email ) {
			$customer_email = 'guest_checkout@garan24.com';
		}

		if ( ! is_email( $customer_email ) ) {
			return;
		}

		// Check quantities
		global $woocommerce;
		$result = $woocommerce->cart->check_cart_item_stock();
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		// Update the local order
		include_once( GARAN24_DIR . 'classes/class-garan24-to-wc.php' );
		$garan24_to_wc = new WC_Gateway_Garan24_K2WC();
		$garan24_to_wc->set_rest( $this->is_rest() );
		$garan24_to_wc->set_eid( $this->garan24_eid );
		$garan24_to_wc->set_secret( $this->garan24_secret );
		$garan24_to_wc->set_garan24_log( $this->log );
		$garan24_to_wc->set_garan24_debug( $this->debug );
		$garan24_to_wc->set_garan24_test_mode( $this->testmode );
		$garan24_to_wc->set_garan24_server( $this->garan24_server );

		if ( $customer_email ) {
			$orderid = $garan24_to_wc->prepare_wc_order( $customer_email );
		} else {
			$orderid = $garan24_to_wc->prepare_wc_order();
		}

		return $orderid;
	}


	/**
	 * Order confirmation via IPN
	 *
	 * @since 1.0.0
	 */
	function check_checkout_listener() {
		$this->log->add( 'garan24', 'Checkout listener hit.' );

		if ( isset( $_GET['validate'] ) ) {
			exit;
		}

		switch ( $_GET['scountry'] ) {
			case 'SE':
				$garan24_secret = $this->secret_se;
				$garan24_eid    = $this->eid_se;
				break;
			case 'FI' :
				$garan24_secret = $this->secret_fi;
				$garan24_eid    = $this->eid_se;
				break;
			case 'NO' :
				$garan24_secret = $this->secret_no;
				$garan24_eid    = $this->eid_no;
				break;
			case 'DE' :
				$garan24_secret = $this->secret_de;
				$garan24_eid    = $this->eid_de;
				break;
			case 'AT' :
				$garan24_secret = $this->secret_at;
				$garan24_eid    = $this->eid_at;
				break;
			case 'gb' :
				$garan24_secret = $this->secret_uk;
				$garan24_eid    = $this->eid_uk;
				break;
			case 'us' :
				$garan24_secret = $this->secret_us;
				$garan24_eid    = $this->eid_us;
				break;
			default:
				$garan24_secret = '';
		}

		// Process cart contents and prepare them for Garan24
		if ( isset( $_GET['garan24_order'] ) ) {
			include_once( GARAN24_DIR . 'classes/class-garan24-to-wc.php' );
			$garan24_to_wc = new WC_Gateway_Garan24_K2WC();
			$garan24_to_wc->set_rest( $this->is_rest() );
			$garan24_to_wc->set_eid( $garan24_eid );
			$garan24_to_wc->set_secret( $garan24_secret );
			$garan24_to_wc->set_garan24_order_uri( $_GET['garan24_order'] );
			$garan24_to_wc->set_garan24_log( $this->log );
			$garan24_to_wc->set_garan24_test_mode( $this->testmode );
			$garan24_to_wc->set_garan24_debug( $this->debug );
			$garan24_to_wc->set_garan24_server( $this->garan24_server );
			$garan24_to_wc->listener();
		}
	} // End function check_checkout_listener


	/**
	 * Helper function get_enabled
	 *
	 * @since 1.0.0
	 */
	function get_enabled() {
		return $this->enabled;
	}

	/**
	 * Helper function get_modify_standard_checkout_url
	 *
	 * @since 1.0.0
	 */
	function get_modify_standard_checkout_url() {
		return $this->modify_standard_checkout_url;
	}

	/**
	 * Helper function get_garan24_checkout_page
	 *
	 * @since 1.0.0
	 */
	function get_garan24_checkout_url() {
		return $this->garan24_checkout_url;
	}


	/**
	 * Helper function get_garan24_country
	 *
	 * @since 1.0.0
	 */
	function get_garan24_country() {
		return $this->garan24_country;
	}


	/**
	 * Helper function - get correct currency for selected country
	 *
	 * @since 1.0.0
	 */
	function get_currency_for_country( $country ) {
		switch ( $country ) {
			case 'DK':
				$currency = 'DKK';
				break;
			case 'DE' :
				$currency = 'EUR';
				break;
			case 'NL' :
				$currency = 'EUR';
				break;
			case 'NO' :
				$currency = 'NOK';
				break;
			case 'FI' :
				$currency = 'EUR';
				break;
			case 'SE' :
				$currency = 'SEK';
				break;
			case 'AT' :
				$currency = 'EUR';
				break;
			default:
				$currency = '';
		}

		return $currency;
	}


	/**
	 * Helper function - get Account Signup Text
	 *
	 * @since 1.0.0
	 */
	public function get_account_signup_text() {
		return $this->account_signup_text;
	}


	/**
	 * Helper function - get Account Login Text
	 *
	 * @since 1.0.0
	 */
	public function get_account_login_text() {
		return $this->account_login_text;
	}


	/**
	 * Helper function - get Subscription Product ID
	 *
	 * @since 2.0.0
	 */
	public function get_subscription_product_id() {
		global $woocommerce;
		$subscription_product_id = false;
		if ( ! empty( $woocommerce->cart->cart_contents ) ) {
			foreach ( $woocommerce->cart->cart_contents as $cart_item ) {
				if ( WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) ) {
					$subscription_product_id = $cart_item['product_id'];
					break;
				}
			}
		}

		return $subscription_product_id;
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

				return new WP_Error( 'error', __( 'This order cannot be refunded. Please make sure it is activated.', 'woocommerce-gateway-garan24' ) );
			}

			if ( 'v2' == get_post_meta( $order->id, '_garan24_api', true ) ) {
				$country = get_post_meta( $orderid, '_billing_country', true );

				$garan24 = new Garan24();
				$this->configure_garan24( $garan24, $country );
				$invNo = get_post_meta( $order->id, '_garan24_invoice_number', true );

				$garan24_order = new WC_Gateway_Garan24_Order( $order, $garan24 );
				$refund_order = $garan24_order->refund_order( $amount, $reason, $invNo );
			} elseif ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
				$country = get_post_meta( $orderid, '_billing_country', true );

				/**
				 * Need to send local order to constructor and Garan24 order to method
				 */
				if ( $this->testmode == 'yes' ) {
					if ( 'gb' == strtolower( $country ) ) {
						$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
					} elseif ( 'us' == strtolower( $country ) ) {
						$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
					}
				} else {
					if ( 'gb' == strtolower( $country ) ) {
						$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
					} elseif ( 'us' == strtolower( $country ) ) {
						$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
					}
				}

				if ( 'gb' == strtolower( $country ) ) {
					$connector = Garan24\Rest\Transport\Connector::create( $this->eid_uk, $this->secret_uk, $garan24_server_url );
				} elseif ( 'us' == strtolower( $country ) ) {
					$connector = Garan24\Rest\Transport\Connector::create( $this->eid_us, $this->secret_us, $garan24_server_url );
				}

				$garan24_order_id = get_post_meta( $orderid, '_garan24_order_id', true );
				$k_order         = new Garan24\Rest\OrderManagement\Order( $connector, $garan24_order_id );
				$k_order->fetch();

				$garan24_order = new WC_Gateway_Garan24_Order( $order );
				$refund_order = $garan24_order->refund_order_rest( $amount, $reason, $k_order );
			}

			if ( $refund_order ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Determines which version of Garan24 API should be used
	 *
	 * @return boolean
	 * @since  2.0.0
	 */
	function is_rest() {
		if ( 'GB' == $this->garan24_country || 'gb' == $this->garan24_country || 'US' == $this->garan24_country || 'us' == $this->garan24_country ) {
			// Set it in session as well, to be used in Shortcodes class
			WC()->session->set( 'garan24_is_rest', true );

			return true;
		}

		// Set it in session as well, to be used in Shortcodes class
		WC()->session->set( 'garan24_is_rest', false );

		return false;
	}


	/**
	 * Determines if KCO checkout page should be displayed.
	 *
	 * @return boolean
	 * @since  2.0.0
	 */
	function show_kco() {
		// Don't render the Garan24 Checkout form if the payment gateway isn't enabled.
		if ( $this->enabled != 'yes' ) {
			// Set it in session as well, to be used in Shortcodes class
			WC()->session->set( 'garan24_show_kco', false );

			return false;
		}

		// If checkout registration is disabled and not logged in, the user cannot checkout
		global $woocommerce;
		$checkout = $woocommerce->checkout();
		if ( ! $checkout->enable_guest_checkout && ! is_user_logged_in() ) {
			echo apply_filters(
				'garan24_checkout_must_be_logged_in_message',
				sprintf(
					__( 'You must be logged in to checkout. %s or %s.', 'woocommerce' ),
					'<a href="' . wp_login_url() . '" title="' . __( 'Login', 'woocommerce-gateway-garan24'
					) . '">' . __( 'Login', 'woocommerce-gateway-garan24' ) . '</a>',
					'<a href="' . wp_registration_url() . '" title="' . __( 'create an account',
						'woocommerce-gateway-garan24' ) . '">' . __( 'create an account', 'woocommerce-gateway-garan24'
					) . '</a>'
				)
			);
			echo '</div>';

			WC()->session->set( 'garan24_show_kco', false );

			return false;
		}

		// If no Garan24 country is set - return.
		if ( empty( $this->garan24_country ) ) {
			echo apply_filters( 'garan24_checkout_wrong_country_message', sprintf( __( 'Sorry, you can not buy via Garan24 Checkout from your country or currency. Please <a href="%s">use another payment method</a>. ', 'woocommerce-gateway-garan24' ), get_permalink( get_option( 'woocommerce_checkout_page_id' ) ) ) );

			WC()->session->set( 'garan24_show_kco', false );

			return false;
		}

		// If the WooCommerce terms page or the Garan24 Checkout settings field
		// Terms Page isn't set, do nothing.
		if ( empty( $this->terms_url ) ) {
			WC()->session->set( 'garan24_show_kco', false );

			return false;
		}

		WC()->session->set( 'garan24_show_kco', true );

		return true;
	}

	/**
	 * Get a link to the transaction on the 3rd party gateway size (if applicable).
	 *
	 * @param  WC_Order $order the order object.
	 *
	 * @return string transaction URL, or empty string.
	 */
	public function get_transaction_url( $order ) {
		// Check if order is completed
		if ( get_post_meta( $order->id, '_garan24_order_activated', true ) ) {
			if ( $this->testmode == 'yes' ) {
				$this->view_transaction_url = 'https://testdrive.garan24.com/invoices/%s.pdf';
			} else {
				$this->view_transaction_url = 'https://online.garan24.com/invoices/%s.pdf';
			}
		}

		return parent::get_transaction_url( $order );
	}

} // End class WC_Gateway_Garan24_Checkout


// Extra Class for Garan24 Checkout
class WC_Gateway_Garan24_Checkout_Extra {

	public function __construct() {

		add_action( 'init', array( $this, 'start_session' ), 1 );
		add_action( 'before_woocommerce_init', array( $this, 'prevent_caching' ) );

		add_filter( 'woocommerce_get_checkout_url', array( $this, 'change_checkout_url' ), 20 );

		add_action( 'woocommerce_register_form_start', array( $this, 'add_account_signup_text' ) );
		add_action( 'woocommerce_login_form_start', array( $this, 'add_account_login_text' ) );

		add_action( 'woocommerce_checkout_after_order_review', array( $this, 'garan24_add_link_to_kco_page' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'garan24_checkout_enqueuer' ) );

		// Filter Checkout page ID, so WooCommerce Google Analytics integration can
		// output Ecommerce tracking code on Garan24 Thank You page
		add_filter( 'woocommerce_get_checkout_page_id', array( $this, 'change_checkout_page_id' ) );

		// Change is_checkout to true on KCO page
		add_filter( 'woocommerce_is_checkout', array( $this, 'change_is_checkout_value' ) );

		// Address update listener
		add_action( 'template_redirect', array( $this, 'address_update_listener' ) );

	}


	/**
	 * Prevent caching in KCO and KCO thank you pages
	 *
	 * @since 1.9.8.2
	 */
	function prevent_caching() {
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$checkout_pages    = array();
		$thank_you_pages   = array();

		// Clean request URI to remove all parameters
		$clean_req_uri = explode( '?', $_SERVER['REQUEST_URI'] );
		$clean_req_uri = $clean_req_uri[0];
		$clean_req_uri = trailingslashit( $clean_req_uri );
		$length        = strlen( $clean_req_uri );

		// Get arrays of checkout and thank you pages for all countries
		if ( is_array( $checkout_settings ) ) {
			foreach ( $checkout_settings as $cs_key => $cs_value ) {
				if ( strpos( $cs_key, 'garan24_checkout_url_' ) !== false ) {
					$checkout_pages[ $cs_key ] = substr( $cs_value, 0 - $length );
				}
				if ( strpos( $cs_key, 'garan24_checkout_thanks_url_' ) !== false ) {
					$thank_you_pages[ $cs_key ] = substr( $cs_value, 0 - $length );
				}
			}
		}

		// Check if string is longer than 1 character, to avoid homepage caching
		if ( strlen( $clean_req_uri ) > 1 ) {
			if ( in_array( $clean_req_uri, $checkout_pages ) || in_array( $clean_req_uri, $thank_you_pages ) ) {
				// Prevent caching
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( "DONOTCACHEPAGE", "true" );
				}
				if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
					define( "DONOTCACHEOBJECT", "true" );
				}
				if ( ! defined( 'DONOTCACHEDB' ) ) {
					define( "DONOTCACHEDB", "true" );
				}

				nocache_headers();
			}
		}
	}


	/**
	 * Add link to KCO page from standard checkout page.
	 * Initiated here because KCO class is instantiated multiple times
	 * making the hook fire multiple times as well.
	 *
	 * @since  2.0
	 */
	function garan24_add_link_to_kco_page() {
		global $garan24_checkout_url;

		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );

		if ( 'yes' == $checkout_settings['enabled'] && '' != $checkout_settings['garan24_checkout_button_label'] && 'yes' == $checkout_settings['add_garan24_checkout_button'] ) {
			echo '<div class="woocommerce"><a style="margin-top:1em" href="' . $garan24_checkout_url . '" class="button std-checkout-button">' . $checkout_settings['garan24_checkout_button_label'] . '</a></div>';
		}
	}


	// Set session
	function start_session() {
		$data = new WC_Gateway_Garan24_Checkout; // Still need to initiate it here, otherwise shortcode won't work

		// if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$is_enabled        = ( isset( $checkout_settings['enabled'] ) ) ? $checkout_settings['enabled'] : '';

		$checkout_pages  = array();
		$thank_you_pages = array();

		// Clean request URI to remove all parameters
		$clean_req_uri = explode( '?', $_SERVER['REQUEST_URI'] );
		$clean_req_uri = $clean_req_uri[0];
		$clean_req_uri = trailingslashit( $clean_req_uri );
		$length        = strlen( $clean_req_uri );

		// Get arrays of checkout and thank you pages for all countries
		if ( is_array( $checkout_settings ) ) {
			foreach ( $checkout_settings as $cs_key => $cs_value ) {
				if ( strpos( $cs_key, 'garan24_checkout_url_' ) !== false ) {
					$checkout_pages[ $cs_key ] = substr( trailingslashit( $cs_value ), 0 - $length );
				}
				if ( strpos( $cs_key, 'garan24_checkout_thanks_url_' ) !== false ) {
					$thank_you_pages[ $cs_key ] = substr( trailingslashit( $cs_value ), 0 - $length );
				}
			}
		}

		// Start session if on a KCO or KCO Thank You page and KCO enabled
		if ( ( in_array( $clean_req_uri, $checkout_pages ) || in_array( $clean_req_uri, $thank_you_pages ) ) && 'yes' == $is_enabled ) {
			session_start();
		}
		// }
	}


	function garan24_checkout_css() {
		global $post;
		global $garan24_checkout_url;

		$checkout_page_id  = url_to_postid( $garan24_checkout_url );
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );

		if ( $post->ID == $checkout_page_id ) {
			if ( '' != $checkout_settings['color_button'] || '' != $checkout_settings['color_button_text'] ) { ?>
				<style>
					a.std-checkout-button,
					.garan24_checkout_coupon input[type="submit"] {
						background: <?php echo $checkout_settings['color_button']; ?> !important;
						border: none !important;
						color: <?php echo $checkout_settings['color_button_text']; ?> !important;
					}
				</style>
			<?php }
		}
	}


	/**
	 *  Change Checkout URL
	 *
	 *  Triggered from the 'woocommerce_get_checkout_url' action.
	 *  Alter the checkout url to the custom Garan24 Checkout Checkout page.
	 *
	 **/
	function change_checkout_url( $url ) {
		global $woocommerce;
		global $garan24_checkout_url;

		$checkout_settings            = get_option( 'woocommerce_garan24_checkout_settings' );
		$enabled                      = $checkout_settings['enabled'];
		$modify_standard_checkout_url = $checkout_settings['modify_standard_checkout_url'];
		$garan24_country               = WC()->session->get( 'garan24_country' );

		$available_countries = $this->get_authorized_countries();

		// Change the Checkout URL if this is enabled in the settings
		if ( $modify_standard_checkout_url == 'yes' && $enabled == 'yes' && ! empty( $garan24_checkout_url ) && in_array( strtoupper( $garan24_country ), $available_countries ) && array_key_exists( strtoupper( $garan24_country ), WC()->countries->get_allowed_countries() ) ) {
			if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
				if ( in_array( strtoupper( $garan24_country ), array( 'SE', 'FI', 'NO' ) ) ) {
					$url = $garan24_checkout_url;
				} else {
					return $url;
				}
			} else {
				$url = $garan24_checkout_url;
			}
		}

		return $url;
	}

	/**
	 *  Function Add Account signup text
	 *
	 * @since version 1.8.9
	 *    Add text above the Account Registration Form.
	 *  Useful for legal text for German stores. See documentation for more information. Leave blank to disable.
	 *
	 **/
	public function add_account_signup_text() {
		$checkout_settings   = get_option( 'woocommerce_garan24_checkout_settings' );
		$account_signup_text = ( isset( $checkout_settings['account_signup_text'] ) ) ? $checkout_settings['account_signup_text'] : '';

		// Change the Checkout URL if this is enabled in the settings
		if ( ! empty( $account_signup_text ) ) {
			echo $account_signup_text;
		}
	}


	/**
	 *  Function Add Account login text
	 *
	 * @since version 1.8.9
	 *    Add text above the Account Login Form.
	 *  Useful for legal text for German stores. See documentation for more information. Leave blank to disable.
	 **/
	public function add_account_login_text() {
		$checkout_settings  = get_option( 'woocommerce_garan24_checkout_settings' );
		$account_login_text = ( isset( $checkout_settings['account_login_text'] ) ) ? $checkout_settings['account_login_text'] : '';

		// Change the Checkout URL if this is enabled in the settings
		if ( ! empty( $account_login_text ) ) {
			echo $account_login_text;
		}
	}

	/**
	 * Change checkout page ID to Garan24 Thank You page, when in Garan24 Thank You page only
	 */
	public function change_checkout_page_id( $checkout_page_id ) {
		global $post;
		global $garan24_checkout_thanks_url;

		if ( is_page() ) {
			$current_page_url = get_permalink( $post->ID );
			// Compare Garan24 Thank You page URL to current page URL
			if ( esc_url( trailingslashit( $garan24_checkout_thanks_url ) ) == esc_url( trailingslashit( $current_page_url ) ) ) {
				$checkout_page_id = $post->ID;
			}
		}

		return $checkout_page_id;
	}


	/**
	 * Set is_checkout to true on KCO page
	 */
	function change_is_checkout_value( $bool ) {
		global $post;
		global $garan24_checkout_url;

		if ( is_page() ) {
			$current_page_url = get_permalink( $post->ID );
			// Compare Garan24 Checkout page URL to current page URL
			if ( esc_url( trailingslashit( $garan24_checkout_url ) ) == esc_url( trailingslashit( $current_page_url ) ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Enqueue Garan24 Checkout javascript.
	 *
	 * @since  2.0
	 **/
	function garan24_checkout_enqueuer() {
		global $woocommerce;

		$suffix               = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$assets_path          = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';
		$frontend_script_path = $assets_path . 'js/frontend/';
		if ( true == $this->is_rest() ) {
			$version = 'v3';
		} else {
			$version = 'v2';
		}
		wp_register_script( 'garan24_checkout', GARAN24_URL . 'assets/js/garan24-checkout.js', array(), false, true );

		wp_localize_script( 'garan24_checkout', 'kcoAjax', array(
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'garan24_checkout_nonce' => wp_create_nonce( 'garan24_checkout_nonce' ),
			'version'               => $version
		) );

		wp_register_style( 'garan24_checkout', GARAN24_URL . 'assets/css/garan24-checkout.css' );

		if ( is_page() ) {
			global $post;

			$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
			$checkout_pages    = array();
			$thank_you_pages   = array();

			// Clean request URI to remove all parameters
			$clean_req_uri = explode( '?', $_SERVER['REQUEST_URI'] );
			$clean_req_uri = $clean_req_uri[0];
			$clean_req_uri = trailingslashit( $clean_req_uri );
			$length        = strlen( $clean_req_uri );

			// Get arrays of checkout and thank you pages for all countries
			if ( is_array( $checkout_settings ) ) {
				foreach ( $checkout_settings as $cs_key => $cs_value ) {
					if ( strpos( $cs_key, 'garan24_checkout_url_' ) !== false ) {
						$checkout_pages[ $cs_key ] = substr( $cs_value, 0 - $length );
					}
					if ( strpos( $cs_key, 'garan24_checkout_thanks_url_' ) !== false ) {
						$thank_you_pages[ $cs_key ] = substr( $cs_value, 0 - $length );
					}
				}
			}

			// Start session if on a KCO or KCO Thank You page and KCO enabled
			if ( in_array( $clean_req_uri, $checkout_pages ) || in_array( $clean_req_uri, $thank_you_pages ) ) {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'wc-checkout', $frontend_script_path . 'checkout' . $suffix . '.js', array(
					'jquery',
					'woocommerce',
					'wc-country-select',
					'wc-address-i18n'
				) );
				wp_enqueue_script( 'garan24_checkout' );
				wp_enqueue_style( 'garan24_checkout' );
			}
		}
	}


	/**
	 * Get authorized KCO Countries.
	 *
	 * @since  2.0
	 **/
	public function get_authorized_countries() {
		$checkout_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$this->eid_se      = ( isset( $checkout_settings['eid_se'] ) ) ? $checkout_settings['eid_se'] : '';
		$this->eid_no      = ( isset( $checkout_settings['eid_no'] ) ) ? $checkout_settings['eid_no'] : '';
		$this->eid_fi      = ( isset( $checkout_settings['eid_fi'] ) ) ? $checkout_settings['eid_fi'] : '';
		$this->eid_de      = ( isset( $checkout_settings['eid_de'] ) ) ? $checkout_settings['eid_de'] : '';
		$this->eid_at      = ( isset( $checkout_settings['eid_at'] ) ) ? $checkout_settings['eid_at'] : '';
		$this->eid_uk      = ( isset( $checkout_settings['eid_uk'] ) ) ? $checkout_settings['eid_uk'] : '';
		$this->eid_us      = ( isset( $checkout_settings['eid_us'] ) ) ? $checkout_settings['eid_us'] : '';

		$this->authorized_countries = array();
		if ( ! empty( $this->eid_se ) ) {
			$this->authorized_countries[] = 'SE';
		}
		if ( ! empty( $this->eid_no ) ) {
			$this->authorized_countries[] = 'NO';
		}
		if ( ! empty( $this->eid_fi ) ) {
			$this->authorized_countries[] = 'FI';
		}
		if ( ! empty( $this->eid_de ) ) {
			$this->authorized_countries[] = 'DE';
		}
		if ( ! empty( $this->eid_at ) ) {
			$this->authorized_countries[] = 'AT';
		}
		if ( ! empty( $this->eid_uk ) ) {
			$this->authorized_countries[] = 'GB';
		}
		if ( ! empty( $this->eid_us ) ) {
			$this->authorized_countries[] = 'US';
		}

		return $this->authorized_countries;
	}


	/**
	 * Determines which version of Garan24 API should be used
	 *
	 * @todo remove or move this function to a separate class. This function exist in the WC_Gateway_Garan24_Checkout class as well.
	 * We needed to move it here because the is_rest function in the WC_Gateway_Garan24_Checkout class was probably called after the garan24_checkout_enqueuer function in this class.
	 * This caused is_rest to be false on first pageload of the checkout even if the Garan24 country was UK or US.
	 *
	 */
	function is_rest() {
		$this->garan24_country = WC()->session->get( 'garan24_country' );

		if ( 'GB' == $this->garan24_country || 'gb' == $this->garan24_country || 'US' == $this->garan24_country || 'us' == $this->garan24_country ) {
			// Set it in session as well, to be used in Shortcodes class
			WC()->session->set( 'garan24_is_rest', true );

			return true;
		}

		// Set it in session as well, to be used in Shortcodes class
		WC()->session->set( 'garan24_is_rest', false );

		return false;
	}


	/**
	 * Change the template for address_update callback
	 *
	 * Can't use WC_API here because we need output on the page.
	 * Checks if KCO shortcode is used and query parameter exists.
	 *
	 * Output JSON and die()
	 *
	 * @since  2.0
	 **/
	function address_update_listener() {
		global $post;

		// Check if page has Garan24 Checkout shortcode in it and address_update query parameter
		if ( has_shortcode( $post->post_content, 'woocommerce_garan24_checkout' ) && isset( $_GET['address_update'] ) && 'yes' == $_GET['address_update'] ) {
			// Read the post body
			$post_body = file_get_contents( 'php://input' );

			// Convert post body into native object
			$data = json_decode( $post_body, true );

			$order_id = $_GET['sid'];

			// Capture address from Garan24
			$order = wc_get_order( $order_id );

			$billing_address = array(
				'country'    => strtoupper( $data['billing_address']['country'] ),
				'first_name' => $data['billing_address']['given_name'],
				'last_name'  => $data['billing_address']['family_name'],
				// 'company'    => $data['billing_address']['company'],
				'address_1'  => $data['billing_address']['street_address'],
				'address_2'  => $data['billing_address']['street_address2'],
				'postcode'   => $data['billing_address']['postal_code'],
				'city'       => $data['billing_address']['city'],
				'state'      => $data['billing_address']['region'],
				'email'      => $data['billing_address']['email'],
				'phone'      => $data['billing_address']['phone'],
			);

			$shipping_address = array(
				'country'    => strtoupper( $data['shipping_address']['country'] ),
				'first_name' => $data['shipping_address']['given_name'],
				'last_name'  => $data['shipping_address']['family_name'],
				// 'company'    => $data['shipping_address']['company'],
				'address_1'  => $data['shipping_address']['street_address'],
				'address_2'  => $data['shipping_address']['street_address2'],
				'postcode'   => $data['shipping_address']['postal_code'],
				'city'       => $data['shipping_address']['city'],
				'state'      => $data['shipping_address']['region'],
				'email'      => $data['shipping_address']['email'],
				'phone'      => $data['shipping_address']['phone'],
			);

			$order->set_address( $billing_address, 'billing' );
			$order->set_address( $shipping_address, 'shipping' );

			$order->calculate_taxes();
			$sales_tax = round( ( $order->get_cart_tax() + $order->get_shipping_tax() ) * 100 );

			if ( 'us' == strtolower( $data['billing_address']['country'] ) ) {
				/**
				 * Update order total by removing old tax value and then adding the
				 * new one and set new order_tax_amount to $sales_tax value.
				 */
				$data['order_amount']     = $data['order_amount'] - $data['order_tax_amount'];
				$data['order_amount']     = $data['order_amount'] + $sales_tax;
				$data['order_tax_amount'] = $sales_tax;

				/**
				 * Loop through $data['order_lines'], then create new array only with
				 * elements where 'type' is not equal to 'sales_tax'. Then add new
				 * sales_tax element to this new array, json_encode the array and send
				 * it back to Garan24.
				 */
				foreach ( $data['order_lines'] as $order_line_key => $order_line ) {
					if ( 'sales_tax' == $order_line['type'] ) {
						unset( $data['order_lines'][ $order_line_key ] );
					}
				}

				// Add sales tax line item
				$data['order_lines'][] = array(
					'type'                  => 'sales_tax',
					'reference'             => __( 'Sales Tax', 'woocommerce-gateway-garan24' ),
					'name'                  => __( 'Sales Tax', 'woocommerce-gateway-garan24' ),
					'quantity'              => 1,
					'unit_price'            => $sales_tax,
					'tax_rate'              => 0,
					'total_amount'          => $sales_tax,
					'total_discount_amount' => 0,
					'total_tax_amount'      => 0
				);
			}

			// Remove array indexing for order lines
			$data['order_lines'] = array_values( $data['order_lines'] );
			$response            = json_encode( $data );

			echo $response;
			die();
		}
	}

} // End class WC_Gateway_Garan24_Checkout_Extra

$wc_garan24_checkout_extra = new WC_Gateway_Garan24_Checkout_Extra;
