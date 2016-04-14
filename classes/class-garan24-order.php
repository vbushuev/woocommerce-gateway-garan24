<?php
/**
 * Garan24 order management
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
 * Class that handles Garan24 orders.
 */
class WC_Gateway_Garan24_Order {

	/**
	 * Class constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param  $order  WooCoommerce order object
	 * @param  $garan24 Garan24 object in V2, not needed for Rest
	 */
	public function __construct( $order = false, $garan24 = false ) {
		$this->order  = $order;
		$this->garan24 = $garan24;

		// Cancel order
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_garan24_order' ) );

		// Capture an order
		add_action( 'woocommerce_order_status_completed', array( $this, 'activate_garan24_order' ) );

		// Add order item
		add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'update_garan24_order_add_item' ), 10, 3 );

		// Remove order item
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'update_garan24_order_delete_item' ) );

		// Edit an order item and save
		add_action( 'woocommerce_saved_order_items', array( $this, 'update_garan24_order_edit_item' ), 10, 2 );
	}


	/**
	 * Prepare Garan24 order for creation.
	 *
	 * @since  2.0
	 **/
	function prepare_order( $garan24_billing, $garan24_shipping, $ship_to_billing_address ) {
		$this->process_order_items();
		$this->process_discount();
		$this->process_fees();
		$this->process_shipping();
		$this->set_addresses( $garan24_billing, $garan24_shipping, $ship_to_billing_address );
	}


	/**
	 * Add shipping and billing address to Garan24 update order.
	 *
	 * @since  2.0
	 **/
	function add_addresses() {
		$order  = $this->order;
		$garan24 = $this->garan24;

		$billing_addr = new Garan24Addr( get_post_meta( $order->id, '_billing_email', true ), // Email address
			'', // Telephone number, only one phone number is needed
			get_post_meta( $order->id, '_billing_phone', true ), // Cell phone number
			get_post_meta( $order->id, '_billing_first_name', true ), // First name (given name)
			get_post_meta( $order->id, '_billing_last_name', true ), // Last name (family name)
			'', // No care of, C/O
			get_post_meta( $order->id, '_billing_address_1', true ), // Street address
			get_post_meta( $order->id, '_billing_postcode', true ), // Zip code
			get_post_meta( $order->id, '_billing_city', true ), // City
			get_post_meta( $order->id, '_billing_country', true ), // Country
			null, // House number (AT/DE/NL only)
			null // House extension (NL only)
		);

		$shipping_addr = new Garan24Addr( get_post_meta( $order->id, '_shipping_email', true ), // Email address
			'', // Telephone number, only one phone number is needed
			get_post_meta( $order->id, '_shipping_phone', true ), // Cell phone number
			get_post_meta( $order->id, '_shipping_first_name', true ), // First name (given name)
			get_post_meta( $order->id, '_shipping_last_name', true ), // Last name (family name)
			'', // No care of, C/O
			get_post_meta( $order->id, '_shipping_address_1', true ), // Street address
			get_post_meta( $order->id, '_shipping_postcode', true ), // Zip code
			get_post_meta( $order->id, '_shipping_city', true ), // City
			get_post_meta( $order->id, '_shipping_country', true ), // Country
			null, // House number (AT/DE/NL only)
			null // House extension (NL only)
		);

		$garan24->setAddress( Garan24Flags::IS_BILLING, $billing_addr );
		$garan24->setAddress( Garan24Flags::IS_SHIPPING, $shipping_addr );

		$garan24->setEstoreInfo( $orderid1 = ltrim( $order->get_order_number(), '#' ), $orderid2 = $order->id );
	}


	/**
	 * Process cart contents.
	 *
	 * @param  $skip_item Item ID to skip from adding, used when item is removed from cart widget
	 *
	 * @since  2.0
	 **/
	function process_order_items( $skip_item = null ) {
		$order  = $this->order;
		$garan24 = $this->garan24;

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item_key => $item ) {
				// Check if an item has been removed
				if ( $item_key != $skip_item ) {
					$_product = $order->get_product_from_item( $item );
					if ( $_product->exists() && $item['qty'] ) {

						// We manually calculate the tax percentage here
						if ( $order->get_line_tax( $item ) !== 0 ) {
							// Calculate tax percentage
							$item_tax_percentage = @number_format( ( $order->get_line_tax( $item ) / $order->get_line_total( $item, false ) ) * 100, 2, '.', '' );
						} else {
							$item_tax_percentage = 0.00;
						}

						// apply_filters to item price so we can filter this if needed
						$garan24_item_price_including_tax = $order->get_item_total( $item, true );
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

						$garan24->addArticle( $qty = $item['qty'],                  // Quantity
							$artNo = strval( $reference ),          // Article number
							$title = utf8_decode( $item['name'] ),   // Article name/title
							$price = $item_price,                   // Price including tax
							$vat = round( $item_tax_percentage ), // Tax
							$discount = 0,                             // Discount is applied later
							$flags = Garan24Flags::INC_VAT           // Price is including VAT.
						);
					}
				}
			}
		}
	}


	/**
	 * Process discount.
	 *
	 * @since  2.0
	 **/
	function process_discount() {
		$order  = $this->order;
		$garan24 = $this->garan24;

		if ( WC()->cart->applied_coupons ) {

			foreach ( WC()->cart->applied_coupons as $code ) {

				$smart_coupon = new WC_Coupon( $code );
				// var_dump(WC()->cart->coupon_discount_amounts);
				// var_dump(WC()->cart->coupon_discount_amounts[$code]);
				if ( $smart_coupon->is_valid() && $smart_coupon->discount_type == 'smart_coupon' ) {
					$garan24->addArticle( $qty = 1, $artNo = '', $title = __( 'Discount', 'woocommerce-gateway-garan24' ), $price = - WC()->cart->coupon_discount_amounts[ $code ], $vat = 0, $discount = 0, $flags = Garan24Flags::INC_VAT );

				}
			}
		}
		/*
		if ( $order->order_discount > 0 ) {
			// apply_filters to order discount so we can filter this if needed
			$garan24_order_discount = $order->order_discount;
			$order_discount = apply_filters( 'garan24_order_discount', $garan24_order_discount );
		
			$garan24->addArticle(
			    $qty = 1,
			    $artNo = '',
			    $title = __( 'Discount', 'woocommerce-gateway-garan24' ),
			    $price = -$order_discount,
			    $vat = 0,
			    $discount = 0,
			    $flags = Garan24Flags::INC_VAT
			);
		}
		*/
	}


	/**
	 * Process fees.
	 *
	 * @since  2.0
	 **/
	function process_fees() {
		$order  = $this->order;
		$garan24 = $this->garan24;

		if ( sizeof( $order->get_fees() ) > 0 ) {
			foreach ( $order->get_fees() as $item ) {
				// We manually calculate the tax percentage here
				if ( $order->get_total_tax() > 0 ) {
					// Calculate tax percentage
					$item_tax_percentage = number_format( ( $item['line_tax'] / $item['line_total'] ) * 100, 2, '.', '' );
				} else {
					$item_tax_percentage = 0.00;
				}

				$invoice_settings = get_option( 'woocommerce_garan24_invoice_settings' );
				$invoice_fee_id = $invoice_settings['invoice_fee_id'];
				$invoice_fee_product = wc_get_product( $invoice_fee_id );
				if ( $invoice_fee_product ) {
					$invoice_fee_name = $invoice_fee_product->get_title();
				} else {
					$invoice_fee_name = '';
				}

				// Invoice fee or regular fee
				if ( $invoice_fee_name == $item['name'] ) {
					$garan24_flags = Garan24Flags::INC_VAT + Garan24Flags::IS_HANDLING; // Price is including VAT and is handling/invoice fee
				} else {
					$garan24_flags = Garan24Flags::INC_VAT; // Price is including VAT
				}
				
				// apply_filters to item price so we can filter this if needed
				$garan24_item_price_including_tax = $item['line_total'] + $item['line_tax'];
				$item_price                      = apply_filters( 'garan24_fee_price_including_tax', $garan24_item_price_including_tax );

				$garan24->addArticle( $qty = 1, $artNo = '', $title = $item['name'], $price = $item_price, $vat = round( $item_tax_percentage ), $discount = 0, $flags = $garan24_flags );

			}

		}
	}


	/**
	 * Process shipping.
	 *
	 * @since  2.0
	 **/
	function process_shipping() {
		$order  = $this->order;
		$garan24 = $this->garan24;

		if ( $order->get_total_shipping() > 0 ) {
			// We manually calculate the shipping tax percentage here
			$calculated_shipping_tax_percentage = ( $order->order_shipping_tax / $order->get_total_shipping() ) * 100; //25.00
			$calculated_shipping_tax_decimal    = ( $order->order_shipping_tax / $order->get_total_shipping() ) + 1; //0.25

			// apply_filters to Shipping so we can filter this if needed
			$garan24_shipping_price_including_tax = $order->get_total_shipping() * $calculated_shipping_tax_decimal;
			$shipping_price                      = apply_filters( 'garan24_shipping_price_including_tax', $garan24_shipping_price_including_tax );

			$garan24->addArticle( $qty = 1, $artNo = 'SHIPPING', $title = $order->get_shipping_method(), $price = $shipping_price, $vat = round( $calculated_shipping_tax_percentage ), $discount = 0, $flags = Garan24Flags::INC_VAT + Garan24Flags::IS_SHIPMENT // Price is including VAT and is shipment fee
			);
		}
	}


	/**
	 * Set shipping and billing address.
	 *
	 * @since  2.0
	 **/
	function set_addresses( $garan24_billing, $garan24_shipping, $ship_to_billing_address ) {
		$order  = $this->order;
		$garan24 = $this->garan24;

		$garan24_billing_address         = $garan24_billing['address'];
		$garan24_billing_house_number    = $garan24_billing['house_number'];
		$garan24_billing_house_extension = $garan24_billing['house_extension'];

		$garan24_shipping_address         = $garan24_shipping['address'];
		$garan24_shipping_house_number    = $garan24_shipping['house_number'];
		$garan24_shipping_house_extension = $garan24_shipping['house_extension'];

		// Billing address
		$addr_billing = new Garan24Addr( $email = $order->billing_email, $telno = '', // We skip the normal land line phone, only one is needed.
			$cellno = $order->billing_phone, $fname = utf8_decode( $order->billing_first_name ), $lname = utf8_decode( $order->billing_last_name ), $careof = utf8_decode( $order->billing_address_2 ),  // No care of, C/O.
			$street = utf8_decode( $garan24_billing_address ), // For DE and NL specify street number in houseNo.
			$zip = utf8_decode( $order->billing_postcode ), $city = utf8_decode( $order->billing_city ), $country = utf8_decode( $order->billing_country ), $houseNo = utf8_decode( $garan24_billing_house_number ), // For DE and NL we need to specify houseNo.
			$houseExt = utf8_decode( $garan24_billing_house_extension ) // Only required for NL.
		);

		// Add Company if one is set
		if ( $order->billing_company ) {
			$addr_billing->setCompanyName( utf8_decode( $order->billing_company ) );
		}

		// Shipping address
		if ( $order->get_shipping_method() == '' || $ship_to_billing_address == 'yes' ) {
			// Use billing address if Shipping is disabled in Woocommerce
			$addr_shipping = new Garan24Addr( $email = $order->billing_email, $telno = '', //We skip the normal land line phone, only one is needed.
				$cellno = $order->billing_phone, $fname = utf8_decode( $order->billing_first_name ), $lname = utf8_decode( $order->billing_last_name ), $careof = utf8_decode( $order->billing_address_2 ),  // No care of, C/O.
				$street = utf8_decode( $garan24_billing_address ), // For DE and NL specify street number in houseNo.
				$zip = utf8_decode( $order->billing_postcode ), $city = utf8_decode( $order->billing_city ), $country = utf8_decode( $order->billing_country ), $houseNo = utf8_decode( $garan24_billing_house_number ), // For DE and NL we need to specify houseNo.
				$houseExt = utf8_decode( $garan24_billing_house_extension ) // Only required for NL.
			);

			// Add Company if one is set
			if ( $order->billing_company ) {
				$addr_shipping->setCompanyName( utf8_decode( $order->billing_company ) );
			}

		} else {
			$addr_shipping = new Garan24Addr( $email = $order->billing_email, $telno = '', //We skip the normal land line phone, only one is needed.
				$cellno = $order->billing_phone, $fname = utf8_decode( $order->shipping_first_name ), $lname = utf8_decode( $order->shipping_last_name ), $careof = utf8_decode( $order->shipping_address_2 ),  // No care of, C/O.
				$street = utf8_decode( $garan24_shipping_address ), // For DE and NL specify street number in houseNo.
				$zip = utf8_decode( $order->shipping_postcode ), $city = utf8_decode( $order->shipping_city ), $country = utf8_decode( $order->shipping_country ), $houseNo = utf8_decode( $garan24_shipping_house_number ), // For DE and NL we need to specify houseNo.
				$houseExt = utf8_decode( $garan24_shipping_house_extension ) // Only required for NL.
			);

			// Add Company if one is set
			if ( $order->shipping_company ) {
				$addr_shipping->setCompanyName( utf8_decode( $order->shipping_company ) );
			}
		}

		// Next we tell the Garan24 instance to use the address in the next order.
		$garan24->setAddress( Garan24Flags::IS_BILLING, $addr_billing ); // Billing / invoice address
		$garan24->setAddress( Garan24Flags::IS_SHIPPING, $addr_shipping ); // Shipping / delivery address
	}


	/**
	 * Refunds a Garan24 order
	 *
	 * @since  2.0
	 **/
	function refund_order( $amount, $reason = '', $invNo ) {
		$order  = $this->order;
		$garan24 = $this->garan24;

		/**
		 * Check if return amount is equal to order total, if yes
		 * refund entire order.
		 */
		if ( $order->get_total() == $amount ) {
			try {
				$ocr = $garan24->creditInvoice( $invNo ); // Invoice number

				if ( $ocr ) {
					$order->add_order_note( sprintf( __( 'Garan24 order fully refunded.', 'woocommerce-gateway-garan24' ), $ocr ) );

					return true;
				}
			} catch ( Exception $e ) {
				$order->add_order_note( sprintf( __( 'Garan24 order refund failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );

				return false;
			}
			/**
			 * If return amount is not equal to order total, maybe perform
			 * good-will partial refund.
			 */
		} else {
			/**
			 * Tax rate needs to be specified for good-will refunds.
			 * Check if there's only one tax rate in the entire order.
			 * If yes, go ahead with good-will refund.
			 */
			if ( 1 == count( $order->get_taxes() ) ) {
				$tax_rate = $order->get_cart_tax() / ( $order->get_total() - $order->get_cart_tax() ) * 100;

				try {
					$ocr = $garan24->returnAmount( // returns 1 on success
						$invNo,               // Invoice number
						$amount,              // Amount given as a discount.
						$tax_rate,            // VAT (%)
						Garan24Flags::INC_VAT, // Amount including VAT.
						$reason               // Description
					);

					if ( $ocr ) {
						$order->add_order_note( sprintf( __( 'Garan24 order partially refunded. Refund amount: %s.', 'woocommerce-gateway-garan24' ), wc_price( $amount, array( 'currency' => $order->get_order_currency() ) ) ) );

						return true;
					}
				} catch ( Exception $e ) {
					$order->add_order_note( sprintf( __( 'Garan24 order refund failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );

					return false;
				}
				/**
				 * If there are multiple tax rates, bail and leave order note.
				 */
			} else {
				$order->add_order_note( __( 'Refund failed. WooCommerce Garan24 partial refund not possible for orders containing items with different tax rates.', 'woocommerce-gateway-garan24' ) );

				return false;
			}
		}

		return false;
	}


	/**
	 * Refunds a Garan24 order for Rest API
	 *
	 * @since  2.0
	 **/
	function refund_order_rest( $amount, $reason = '', $k_order ) {
		$order   = $this->order;
		$orderid = $order->id;

		try {
			$k_order->refund( array(
				'refunded_amount' => $amount * 100,
				'description'     => $reason,
			) );

			$order->add_order_note( sprintf( __( 'Garan24 order refunded. Refund amount: %s.', 'woocommerce-gateway-garan24' ), wc_price( $amount, array( 'currency' => $order->get_order_currency() ) ) ) );

			return true;
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 order refund failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );

			return false;
		}
	}


	/**
	 * Set up Garan24 configuration.
	 *
	 * @since  2.0
	 **/
	function configure_garan24( $garan24, $country, $payment_method ) {
		if ( 'garan24_invoice' == $payment_method ) {
			$garan24_settings = get_option( 'woocommerce_garan24_invoice_settings' );
		} elseif ( 'garan24_part_payment' == $payment_method ) {
			$garan24_settings = get_option( 'woocommerce_garan24_part_payment_settings' );
		} elseif ( 'garan24_checkout' == $payment_method ) {
			$garan24_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		}

		// Country and language
		switch ( $country ) {
			case 'NO' :
			case 'NB' :
				$garan24_country  = 'NO';
				$garan24_language = 'nb-no';
				$garan24_currency = 'NOK';
				$garan24_eid      = $garan24_settings['eid_no'];
				$garan24_secret   = $garan24_settings['secret_no'];
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
				$garan24_eid      = $garan24_settings['eid_fi'];
				$garan24_secret   = $garan24_settings['secret_fi'];
				break;
			case 'SE' :
			case 'SV' :
				$garan24_country  = 'SE';
				$garan24_language = 'sv-se';
				$garan24_currency = 'SEK';
				$garan24_eid      = $garan24_settings['eid_se'];
				$garan24_secret   = $garan24_settings['secret_se'];
				break;
			case 'DE' :
				$garan24_country  = 'DE';
				$garan24_language = 'de-de';
				$garan24_currency = 'EUR';
				$garan24_eid      = $garan24_settings['eid_de'];
				$garan24_secret   = $garan24_settings['secret_de'];
				break;
			case 'AT' :
				$garan24_country  = 'AT';
				$garan24_language = 'de-at';
				$garan24_currency = 'EUR';
				$garan24_eid      = $garan24_settings['eid_at'];
				$garan24_secret   = $garan24_settings['secret_at'];
				break;
			case 'GB' :
				$garan24_country  = 'gb';
				$garan24_language = 'en-gb';
				$garan24_currency = 'gbp';
				$garan24_eid      = $garan24_settings['eid_uk'];
				$garan24_secret   = $garan24_settings['secret_uk'];
				break;
			default:
				$garan24_country  = '';
				$garan24_language = '';
				$garan24_currency = '';
				$garan24_eid      = '';
				$garan24_secret   = '';
		}

		// Test mode or Live mode		
		if ( $garan24_settings['testmode'] == 'yes' ) {
			// Disable SSL if in testmode
			$garan24_ssl  = 'false';
			$garan24_mode = Garan24::BETA;
		} else {
			// Set SSL if used in webshop
			if ( is_ssl() ) {
				$garan24_ssl = 'true';
			} else {
				$garan24_ssl = 'false';
			}
			$garan24_mode = Garan24::LIVE;
		}

		$garan24->config( $eid = $garan24_eid, $secret = $garan24_secret, $country = $country, $language = $garan24_language, $currency = $garan24_currency, $mode = $garan24_mode, $pcStorage = 'json', $pcURI = '/srv/pclasses.json', $ssl = $garan24_ssl, $candice = false );
	}


	/**
	 * Order activation wrapper function
	 *
	 * @since  2.0
	 **/
	function activate_garan24_order( $orderid ) {
		$order = wc_get_order( $orderid );

		$payment_method             = $this->get_order_payment_method( $order );
		$payment_method_option_name = 'woocommerce_' . $payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );

		// Check if option is enabled
		if ( 'yes' == $payment_method_option['push_completion'] ) {
			// Check if this order hasn't been activated already
			if ( ! get_post_meta( $orderid, '_garan24_invoice_number', true ) ) {
				// Activation for orders created with KCO Rest
				if ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
					$this->activate_order_rest( $orderid );
					// Activation for KCO V2 and KPM orders
				} else {
					$this->activate_order( $orderid );
				}
			}
		}
	}


	/**
	 * Activates a Garan24 order for V2 API
	 *
	 * @since  2.0
	 **/
	function activate_order( $orderid ) {
		$order = wc_get_order( $orderid );

		if ( get_post_meta( $orderid, '_garan24_order_reservation', true ) && get_post_meta( $orderid, '_billing_country', true ) ) {
			// Check if this is a subscription order
			if ( class_exists( 'WC_Subscriptions_Renewal_Order' ) && WC_Subscriptions_Renewal_Order::is_renewal( $order ) ) {
				if ( ! get_post_meta( $orderid, '_garan24_order_reservation_recurring', true ) ) {
					return;
				}
			}

			$rno            = get_post_meta( $orderid, '_garan24_order_reservation', true );
			$country        = get_post_meta( $orderid, '_billing_country', true );
			$payment_method = get_post_meta( $orderid, '_payment_method', true );

			$garan24 = new Garan24();
			$this->configure_garan24( $garan24, $country, $payment_method );

			try {
				$result = $garan24->activate( $rno, null, // OCR Number
					Garan24Flags::RSRV_SEND_BY_EMAIL );
				$risk   = $result[0]; // returns 'ok' or 'no_risk'
				$invNo  = $result[1]; // returns invoice number

				$order->add_order_note( sprintf( __( 'Garan24 order activated. Invoice number %s - risk status %s.', 'woocommerce-gateway-garan24' ), $invNo, $risk ) );
				update_post_meta( $orderid, '_garan24_order_activated', time() );
				update_post_meta( $orderid, '_garan24_invoice_number', $invNo );
				update_post_meta( $orderid, '_transaction_id', $invNo );
			} catch ( Exception $e ) {
				$order->add_order_note( sprintf( __( 'Garan24 order activation failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
			}
		}
	}


	/**
	 * Activates a Garan24 order for Rest API
	 *
	 * @since  2.0
	 **/
	function activate_order_rest( $orderid ) {
		$order           = wc_get_order( $orderid );
		$garan24_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$billing_country = get_post_meta( $orderid, '_billing_country', true );

		/**
		 * Need to send local order to constructor and Garan24 order to method
		 */
		if ( $garan24_settings['testmode'] == 'yes' ) {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
			}
		} else {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
			}
		}

		if ( 'gb' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_uk'], $garan24_settings['secret_uk'], $garan24_server_url );
		} elseif ( 'us' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_us'], $garan24_settings['secret_us'], $garan24_server_url );
		}

		$garan24_order_id = get_post_meta( $orderid, '_garan24_order_id', true );
		$k_order         = new Garan24\Rest\OrderManagement\Order( $connector, $garan24_order_id );
		$k_order->fetch();

		// Capture full order amount on WooCommerce order completion
		$data = array(
			'captured_amount' => $k_order['order_amount'],
			'description'     => __( 'WooCommerce order marked complete', 'woocommerce-gateway-garan24' ),
			'order_lines'     => $k_order['order_lines'],
		);

		try {
			$k_order->createCapture( $data );

			$k_order->fetch();

			$order->add_order_note( sprintf( __( 'Garan24 order captured. Invoice number %s.', 'woocommerce-gateway-garan24' ), $k_order['captures'][0]['capture_id'] ) );

			update_post_meta( $orderid, '_garan24_order_activated', time() );
			update_post_meta( $orderid, '_garan24_invoice_number', $k_order['captures'][0]['capture_id'] );
			update_post_meta( $orderid, '_transaction_id', $k_order['captures'][0]['capture_id'] );
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 order activation failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
		}
	}


	/**
	 * Order cancellation wrapper function
	 *
	 * @since  2.0
	 **/
	function cancel_garan24_order( $orderid ) {
		$order = wc_get_order( $orderid );

		$payment_method             = $this->get_order_payment_method( $order );
		$payment_method_option_name = 'woocommerce_' . $payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );

		// Check if option is enabled
		if ( 'yes' == $payment_method_option['push_cancellation'] ) {
			// Check if this order hasn't been activated already
			if ( ! get_post_meta( $orderid, '_garan24_order_cancelled', true ) ) {
				// Activation for orders created with KCO Rest
				if ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
					$this->cancel_order_rest( $orderid );
					// Activation for KCO V2 and KPM orders
				} else {
					$this->cancel_order( $orderid );
				}
			}
		}
	}


	/**
	 * Cancels a Garan24 order for V2 API
	 *
	 * @since  2.0
	 **/
	function cancel_order( $orderid ) {
		$order = wc_get_order( $orderid );

		// Garan24 reservation number and billing country must be set
		if ( get_post_meta( $orderid, '_garan24_order_reservation', true ) && get_post_meta( $orderid, '_billing_country', true ) ) {
			$rno            = get_post_meta( $orderid, '_garan24_order_reservation', true );
			$country        = get_post_meta( $orderid, '_billing_country', true );
			$payment_method = get_post_meta( $orderid, '_payment_method', true );

			$garan24 = new Garan24();
			$this->configure_garan24( $garan24, $country, $payment_method );

			try {
				$garan24->cancelReservation( $rno );
				$order->add_order_note( __( 'Garan24 order cancellation completed.', 'woocommerce-gateway-garan24' ) );
				add_post_meta( $orderid, '_garan24_order_cancelled', time() );
			} catch ( Exception $e ) {
				$order->add_order_note( sprintf( __( 'Garan24 order cancellation failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
			}
		}
	}

	/**
	 * Cancels a Garan24 order for Rest API
	 *
	 * @since  2.0
	 **/
	function cancel_order_rest( $orderid ) {
		$order           = wc_get_order( $orderid );
		$garan24_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$billing_country = get_post_meta( $orderid, '_billing_country', true );

		/**
		 * Need to send local order to constructor and Garan24 order to method
		 */
		if ( $garan24_settings['testmode'] == 'yes' ) {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
			}
		} else {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
			}
		}

		if ( 'gb' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_uk'], $garan24_settings['secret_uk'], $garan24_server_url );
		} elseif ( 'us' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_us'], $garan24_settings['secret_us'], $garan24_server_url );
		}

		$garan24_order_id = get_post_meta( $orderid, '_garan24_order_id', true );
		$k_order         = new Garan24\Rest\OrderManagement\Order( $connector, $garan24_order_id );
		$k_order->fetch();

		try {
			$k_order->cancel();
			$order->add_order_note( __( 'Garan24 order cancelled.', 'woocommerce-gateway-garan24' ) );
			add_post_meta( $orderid, '_garan24_order_cancelled', time() );
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 order cancelation failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
		}

	}


	/**
	 * Order update wrapper function
	 *
	 * @since  2.0
	 **/
	function update_garan24_order_add_item( $itemid, $item ) {
		// Get item row from the database table, needed for order id
		global $wpdb;
		$item_row = $wpdb->get_row( $wpdb->prepare( "
			SELECT      order_id
			FROM        {$wpdb->prefix}woocommerce_order_items
			WHERE       order_item_id = %d
		", $itemid ) );

		$orderid = $item_row->order_id;
		$order   = wc_get_order( $orderid );

		$payment_method             = $this->get_order_payment_method( $order );
		$payment_method_option_name = 'woocommerce_' . $payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );

		// Check if option is enabled
		if ( 'yes' == $payment_method_option['push_update'] ) {
			// Check if and order is on hold so it can be edited, and if it hasn't been captured or cancelled
			if ( 'on-hold' == $order->get_status() && ! get_post_meta( $orderid, '_garan24_order_cancelled', true ) && ! get_post_meta( $orderid, '_garan24_order_activated', true ) ) {
				if ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
					$this->update_order_rest( $orderid );
					// Activation for KCO V2 and KPM orders
				} else {
					$this->update_order( $orderid );
				}
			}
		}
	}


	/**
	 * Update order in Garan24 system, add new item
	 *
	 * @since  2.0.0
	 */
	function update_garan24_order_delete_item( $itemid ) {
		// Get item row from the database table, needed for order id
		global $wpdb;
		$item_row = $wpdb->get_row( $wpdb->prepare( "
			SELECT      order_id
			FROM        {$wpdb->prefix}woocommerce_order_items
			WHERE       order_item_id = %d
		", $itemid ) );

		$orderid = $item_row->order_id;
		$order   = wc_get_order( $orderid );

		$payment_method             = $this->get_order_payment_method( $order );
		$payment_method_option_name = 'woocommerce_' . $payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );

		// Check if option is enabled
		if ( 'yes' == $payment_method_option['push_update'] ) {
			// Check if order is on hold so it can be edited, and if it hasn't been captured or cancelled
			if ( 'on-hold' == $order->get_status() && ! get_post_meta( $orderid, '_garan24_order_cancelled', true ) && ! get_post_meta( $orderid, '_garan24_order_activated', true ) ) {
				if ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
					$this->update_order_rest( $orderid, $itemid );
					// Activation for KCO V2 and KPM orders
				} else {
					$this->update_order( $orderid, $itemid );
				}
			}
		}
	}


	/**
	 * Update order in Garan24 system, add new item
	 *
	 * @since  2.0.0
	 */
	function update_garan24_order_edit_item( $orderid, $items ) {
		$order = wc_get_order( $orderid );

		$payment_method             = $this->get_order_payment_method( $order );
		$payment_method_option_name = 'woocommerce_' . $payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );

		// Check if option is enabled
		if ( 'yes' == $payment_method_option['push_update'] ) {
			// Check if order is on hold so it can be edited, and if it hasn't been captured or cancelled
			if ( 'on-hold' == $order->get_status() && ! get_post_meta( $orderid, '_garan24_order_cancelled', true ) && ! get_post_meta( $orderid, '_garan24_order_activated', true ) ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					// Check if order was created using this method
					if ( 'on-hold' == $order->get_status() ) {
						if ( 'rest' == get_post_meta( $order->id, '_garan24_api', true ) ) {
							$this->update_order_rest( $orderid );
							// Activation for KCO V2 and KPM orders
						} else {
							$this->update_order( $orderid );
						}
					}
				}
			}
		}
	}


	/**
	 * Updates a Garan24 order
	 *
	 * @since  2.0
	 **/
	function update_order( $orderid, $itemid = false ) {
		$order       = wc_get_order( $orderid );
		$this->order = $order;

		$rno            = get_post_meta( $orderid, '_garan24_order_reservation', true );
		$country        = get_post_meta( $orderid, '_billing_country', true );
		$payment_method = get_post_meta( $orderid, '_payment_method', true );

		$garan24 = new Garan24();
		$this->configure_garan24( $garan24, $country, $payment_method );
		$this->garan24 = $garan24;

		$this->add_addresses();
		$this->process_order_items( $itemid );
		$this->process_fees();
		$this->process_shipping();
		$this->process_discount();

		try {
			$result = $garan24->update( $rno );
			if ( $result ) {
				$order->add_order_note( sprintf( __( 'Garan24 order updated.', 'woocommerce-gateway-garan24' ) ) );
			}
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 order update failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
		}
	}

	/**
	 * Updates a Garan24 order for Rest API
	 *
	 * @since  2.0
	 **/
	function update_order_rest( $orderid, $itemid = false ) {
		$order           = wc_get_order( $orderid );
		$garan24_settings = get_option( 'woocommerce_garan24_checkout_settings' );
		$billing_country = get_post_meta( $orderid, '_billing_country', true );

		$updated_order_lines = array();
		$updated_order_total = 0;
		$updated_tax_total   = 0;

		foreach ( $order->get_items() as $item_key => $order_item ) {
			if ( $order_item['qty'] && isset( $itemid ) && $item_key != $itemid ) {
				$_product = wc_get_product( $order_item['product_id'] );

				$item_name = $order_item['name'];
				// Append item meta to the title, if it exists
				if ( isset( $order_item['item_meta'] ) ) {
					$item_meta = new WC_Order_Item_Meta( $order_item['item_meta'] );
					if ( $meta = $item_meta->display( true, true ) ) {
						$item_name .= ' (' . $meta . ')';
					}
				}
				$item_reference = strval( $order_item['product_id'] );

				$item_price        = round( number_format( ( $order_item['line_subtotal'] + $order_item['line_subtotal_tax'] ) * 100, 0, '', '' ) / $order_item['qty'] );
				$item_quantity     = (int) $order_item['qty'];
				$item_total_amount = round( ( $order_item['line_total'] + $order_item['line_tax'] ) * 100 );

				if ( $order_item['line_subtotal'] > $order_item['line_total'] ) {
					$item_discount_amount = ( $order_item['line_subtotal'] + $order_item['line_subtotal_tax'] - $order_item['line_total'] - $order_item['line_tax'] ) * 100;
				} else {
					$item_discount_amount = 0;
				}

				$item_tax_amount = round( $order_item['line_tax'] * 100 );
				$item_tax_rate   = round( $order_item['line_subtotal_tax'] / $order_item['line_subtotal'], 2 ) * 100 * 100;

				$garan24_item = array(
					'reference'             => $item_reference,
					'name'                  => $item_name,
					'quantity'              => $item_quantity,
					'unit_price'            => $item_price,
					'tax_rate'              => $item_tax_rate,
					'total_amount'          => $item_total_amount,
					'total_tax_amount'      => $item_tax_amount,
					'total_discount_amount' => $item_discount_amount
				);

				$updated_order_lines[] = $garan24_item;
				$updated_order_total   = $updated_order_total + $item_total_amount;
				$updated_tax_total     = $updated_tax_total + $item_tax_amount;
			}
		}

		/**
		 * Need to send local order to constructor and Garan24 order to method
		 */
		if ( $garan24_settings['testmode'] == 'yes' ) {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_TEST_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_TEST_BASE_URL;
			}
		} else {
			if ( 'gb' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::EU_BASE_URL;
			} elseif ( 'us' == strtolower( $billing_country ) ) {
				$garan24_server_url = Garan24\Rest\Transport\ConnectorInterface::NA_BASE_URL;
			}
		}

		if ( 'gb' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_uk'], $garan24_settings['secret_uk'], $garan24_server_url );
		} elseif ( 'us' == strtolower( $billing_country ) ) {
			$connector = Garan24\Rest\Transport\Connector::create( $garan24_settings['eid_us'], $garan24_settings['secret_us'], $garan24_server_url );
		}

		$garan24_order_id = get_post_meta( $orderid, '_garan24_order_id', true );
		$k_order         = new Garan24\Rest\OrderManagement\Order( $connector, $garan24_order_id );
		$k_order->fetch();

		try {
			$k_order->updateAuthorization( array(
				'order_amount'     => $updated_order_total,
				'order_tax_amount' => $updated_tax_total,
				'description'      => 'Updating WooCommerce order',
				'order_lines'      => $updated_order_lines
			) );
			$order->add_order_note( sprintf( __( 'Garan24 order updated.', 'woocommerce-gateway-garan24' ) ) );
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Garan24 order update failed. Error code %s. Error message %s', 'woocommerce-gateway-garan24' ), $e->getCode(), utf8_encode( $e->getMessage() ) ) );
		}
	}


	/**
	 * Helper function, gets order payment method
	 *
	 * @since  2.0
	 *
	 * @param  $order  WooCoommerce order object
	 **/
	function get_order_payment_method( $order ) {
		$payment_method = $order->payment_method;

		return $payment_method;
	}

}

$wc_gateway_garan24_order = new WC_Gateway_Garan24_Order;