<?php
/* Garan24 AIM Payment Gateway Class */
class WC_Gateway_garan24 extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {
		// The global ID for this Payment method
		$this->id = "garan24";
		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Garan24 pay", 'garan24' );
		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Garan24 Payment Gateway Plug-in for WooCommerce", 'garan24' );
		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Garan24", 'garan24' );
		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;
		// Bool. Can be set to true if you want payment fields to show on the checkout
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = false;
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
		//add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		add_action('init', array(&$this, 'check_response'));
		add_action('woocommerce_receipt_garan24', array(&$this, 'receipt_page'));
		// register scripts
		wp_register_script( 'jquery-redirect-js', 'https://garan24.ru/service/public/js/jquery.redirect.js', array( 'jquery' ), '1.0', false );
		wp_register_script( 'garan24-core-js', 'https://garan24.ru/service/public/js/api/1.0/garan24.core.js', array( 'jquery' ), '1.0', false );
		wp_enqueue_script( 'jquery-redirect-js' );
		wp_enqueue_script( 'garan24-core-js' );

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
		$order = new WC_Order( $order_id );
        return [
			'result' => 'success',
			'redirect' => add_query_arg('order',$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
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
	public function receipt_page($order_id){
		global $woocommerce;
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );

		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment )
						   ? 'https://garan24.ru/service/public/processpay'
						   : 'https://garan24.ru/service/public/processpay';

		// This is where the fun stuff begins
		$payload = [
			// Garan24 Credentials and API Info
			"x_secret"           	=> $this->garan24_secret,
			"x_key"              	=> $this->garan24_key,
			"version"            	=> "1.0"
        ];
        //$data = json_decode(json_encode($customer_order), true);
        $payload["order"] = [
			"order_id"=>$order_id,
			"order_url"=>$customer_order->get_view_order_url(),
            "payment_details"=>[
                "method_id"=> $customer_order->payment_method,//"garan24",
                "method_title"=> $customer_order->payment_method_title,//"Garan24 Pay",
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
		$json_data = json_encode($payload);
		$redirect_url = $environment_url."#".$json_data;
		echo '<p>pay by garan24</p>';
		echo '<script type="text/javascript">
			jQuery(function(){
				jQuery("body").block({
            			message: "<img src=\"/wp-content/plugins/woocommerce-gateway-garan24/assets/loader.gif\" alt=\"Redirecting…\" style=\"width:48px;float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Garan24 to make payment.', 'garan24').'",
                		overlayCSS:{
            				background: "#fff",
                			opacity: 0.6
    					},
    					css: {
        					padding:        20,
            				textAlign:      "center",
            				color:          "#555",
            				border:         "3px solid #aaa",
            				backgroundColor:"#fff",
            				cursor:         "wait",
            				lineHeight:"32px"
    					}
				});
				//console.debug("redirect to '.$environment_url.' with data "+JSON.stringify('.$json_data.'));
				var jdata='.$json_data.';
				//jQuery.redirect("'.$environment_url.'",JSON.stringify(jdata));
				jQuery.redirect("'.$environment_url.'",jdata);
				//jQuery.post({url:"'.$environment_url.'",data:jdata});
			});
		</script>';

	}
	public function check_response(){
        global $woocommerce;
        if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
            $order_id_time = $_REQUEST['txnid'];
            $order_id = explode('_', $_REQUEST['txnid']);
            $order_id = (int)$order_id[0];
            if($order_id != ''){
                try{
                    $order = new WC_Order( $order_id );
                    $merchant_id = $_REQUEST['key'];
                    $amount = $_REQUEST['Amount'];
                    $hash = $_REQUEST['hash'];

                    $status = $_REQUEST['status'];
                    $productinfo = "Order $order_id";
                    echo $hash;
                    echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
                    $checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
                    $transauthorised = false;
                    if($order -> status !=='completed'){
                        if($hash == $checkhash)
                        {

                          $status = strtolower($status);

                            if($status=="success"){
                                $transauthorised = true;
                                $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                $this -> msg['class'] = 'woocommerce_message';
                                if($order -> status == 'processing'){

                                }else{
                                    $order -> payment_complete();
                                    $order -> add_order_note('PayU payment successful<br/>Unnique Id from PayU: '.$_REQUEST['mihpayid']);
                                    $order -> add_order_note($this->msg['message']);
                                    $woocommerce -> cart -> empty_cart();
                                }
                            }else if($status=="pending"){
                                $this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                                $this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
                                $order -> add_order_note('PayU payment status is pending<br/>Unnique Id from PayU: '.$_REQUEST['mihpayid']);
                                $order -> add_order_note($this->msg['message']);
                                $order -> update_status('on-hold');
                                $woocommerce -> cart -> empty_cart();
                            }
                            else{
                                $this -> msg['class'] = 'woocommerce_error';
                                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
                                //Here you need to put in the routines for a failed
                                //transaction such as sending an email to customer
                                //setting database status etc etc
                            }
                        }else{
                            $this -> msg['class'] = 'error';
                            $this -> msg['message'] = "Security Error. Illegal access detected";

                            //Here you need to simply ignore this and dont need
                            //to perform any operation in this condition
                        }
                        if($transauthorised==false){
                            $order -> update_status('failed');
                            $order -> add_order_note('Failed');
                            $order -> add_order_note($this->msg['message']);
                        }
                        add_action('the_content', array(&$this, 'showMessage'));
                    }}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
            }
        }
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
