<?php
/* Garan24 AIM Payment Gateway Class */
class WC_Gateway_garan24_partpay extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "garan24_pp";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Garan24 Part Pay", 'garan24_pp' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Garan24 Payment Gateway Plug-in for WooCommerce", 'garan24_pp' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Garan24 Part pay", 'garan24_pp' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		//$this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'garan24' ),
				'label'		=> __( 'Enable this payment gateway', 'garan24' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'garan24' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'garan24' ),
				'default'	=> __( 'Credit card', 'garan24' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'garan24' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'garan24' ),
				'default'	=> __( 'Pay securely using your credit card.', 'garan24' ),
				'css'		=> 'max-width:350px;'
			),
			'garan24_key' => array(
				'title'		=> __( 'Garan API Key', 'garan24' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Login provided by Garan24 when you signed up for an account.', 'garan24' ),
			),
			'garan24_secret' => array(
				'title'		=> __( 'Garan24 API Secret', 'garan24' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Transaction Key provided by Garan24 when you signed up for an account.', 'garan24' ),
			),
			'environment' => array(
				'title'		=> __( 'Garan24 Test Mode', 'garan24' ),
				'label'		=> __( 'Enable Test Mode', 'garan24' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'garan24' ),
				'default'	=> 'no',
			)
		);
	}

	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;

		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );

		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment )
						   ? 'http://www.plugin.garan24.ru/processpay'
						   : 'http://www.plugin.garan24.ru/test/processpay';

		// This is where the fun stuff begins
		$payload = [
			// Garan24 Credentials and API Info
			"x_secret"           	=> $this->garan24_secret,
			"x_key"              	=> $this->garan24_key,
			"version"            	=> "1.0"
        ];
        //$data = json_decode(json_encode($customer_order), true);
        $payload["order"] = [
            "payment_details"=>[
                "method_id"=> $customer_order->payment_method,//"garan24",
                "method_title"=> $customer_order->payment_method_title,//"Garan24 Pay",
				// Credit Card Information
				//"cardnumber"=> str_replace( array(' ', '-' ), '', $_POST['garan24_pp-card-number'] ),
				//"cardexpire"=> ( isset( $_POST['garan24_pp-card-cvc'] ) ) ? $_POST['garan24_pp-card-cvc'] : '',
				//"cardcvc"=> str_replace( array( '/', ' '), '', $_POST['garan24_pp-card-expiry'] ),
                "paid"=> false
            ],
            "billing_address" =>[
				"first_name" => $customer_order->billing_first_name,
				"last_name" => $customer_order->billing_last_name,
				"address_1" => $customer_order->billing_address_1,
				"city" => $customer_order->billing_city,
				"state" => $customer_order->billing_state,
				"postcode" => $customer_order->billing_postcode,
				"country" => $customer_order->billing_country,
				"phone" => $customer_order->billing_phone,
				"email" => $customer_order->billing_email
            ],
            "line_items" =>$customer_order->get_items(),
            "order_total" => $customer_order->order_total,
            "order_currency" => $customer_order->order_currency,
            "customer_ip_address" => $customer_order->customer_ip_address,
            "customer_user_agent" => $customer_order->customer_user_agent
        ];
		// Send this payload to Garan24 for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => json_encode($payload),
			'timeout'   => 90,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) )
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'garan24' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'Garan24\'s Response was empty.', 'garan24' ) );

		// Retrieve the body's resopnse if no errors found
		$response_body = wp_remote_retrieve_body( $response );

		$r = json_decode($response_body);
		// Test the code to know if the transaction went through or not.
		// 1 or 4 means the transaction was a success
		if ( ( $r['code'] == 0 ) || ( $r['response_code'] == 4 ) ) {
			// Payment has been successful
			$customer_order->add_order_note( __( 'Garan24 payment completed.', 'garan24' ) );

			// Mark order as Paid
			$customer_order->payment_complete();

			// Empty the cart (Very important step)
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			// Transaction was not succesful
			// Add notice to the cart
			wc_add_notice( $r['message'], 'error' );
			// Add note to the order for your reference
			$customer_order->add_order_note( 'Error: '. $r['message'] );
		}

	}

	// Validate fields
	public function validate_fields() {
		return true;
	}

	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
			}
		}
	}

} // End of SPYR_AuthorizeNet_AIM
?>
