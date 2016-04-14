<?php
/**
 * Garan24 get_addresses
 *
 * The Garan24 get_addresses class displays a form field above the billing form on the WooCommerce Checkout page.
 * The Get Addresses form only displays if Garan24 Account or Invoice Payment are enabled and active.
 * The customer enters their personal identity number/organisation number and then retrieves a getAddresses response from Garan24.
 * The response from Garan24 contains the registered address for the individual/orgnaisation.
 * If a company uses the Get Addresses function the answer could contain several addresses. The customer can then select wich one to use.
 * When a retrieved address is selected, several checkout form fields are being changed to readonly and can after this not be edited.
 *
 *
 * @class        WC_Garan24_Get_Address
 * @version        1.0
 * @category    Class
 * @author        Krokedil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Garan24_Get_Address {

	public function __construct() {
		$invo_settings          = get_option( 'woocommerce_garan24_invoice_settings' );
		$this->invo_eid         = $invo_settings['eid_se'];
		$this->invo_secret      = $invo_settings['secret_se'];
		$this->invo_testmode    = $invo_settings['testmode'];
		$this->invo_enabled     = $invo_settings['enabled'];
		$this->invo_dob_display = 'description_box';

		$partpay_settings          = get_option( 'woocommerce_garan24_part_payment_settings' );
		$this->partpay_eid         = $partpay_settings['eid_se'];
		$this->partpay_secret      = $partpay_settings['secret_se'];
		$this->partpay_testmode    = $partpay_settings['testmode'];
		$this->partpay_enabled     = $partpay_settings['enabled'];
		$this->partpay_dob_display = 'description_box';

		add_action( 'wp_ajax_ajax_request', array( $this, 'ajax_request' ) );
		add_action( 'wp_ajax_nopriv_ajax_request', array( $this, 'ajax_request' ) );

		add_action( 'wp_footer', array( $this, 'js' ) );
		add_action( 'wp_footer', array( $this, 'checkout_restore_customer_defaults' ) );

		// GetAddresses response above the checkout billing form
		add_action( 'woocommerce_before_checkout_form', array( $this, 'get_address_response' ) );
	} // End constructor


	/**
	 * JS restoring the default checkout field values if user switch from
	 * Garan24 (invoice, account or campaign) to another payment method.
	 *
	 * This is to prevent that customers use Garan24s Get Address feature
	 * and in the end use another payment method than Garan24.
	 */
	public function checkout_restore_customer_defaults() {
		if ( is_checkout() && 'SE' == $this->get_shop_country() && ( $this->partpay_enabled || $this->invo_enabled ) ) {

			if ( defined( 'WOOCOMMERCE_GARAN24_CHECKOUT' ) ) {
				return;
			}

			global $woocommerce, $current_user;

			$original_customer = array();
			$original_customer = $woocommerce->session->get( 'customer' );

			$original_billing_first_name  = '';
			$original_billing_last_name   = '';
			$original_shipping_first_name = '';
			$original_shipping_last_name  = '';
			$original_billing_company     = '';
			$original_shipping_company    = '';

			$original_billing_first_name  = $current_user->billing_first_name;
			$original_billing_last_name   = $current_user->billing_last_name;
			$original_shipping_first_name = $current_user->shipping_first_name;
			$original_shipping_last_name  = $current_user->shipping_last_name;
			$original_billing_company     = $current_user->billing_company;
			$original_shipping_company    = $current_user->shipping_company;
			?>

			<script type="text/javascript">
				var getAddressCompleted = 'no';

				jQuery(document).ajaxComplete(function () {

					// On switch of payment method
					jQuery('input[name="payment_method"]').on('change', function () {
						if ('yes' == getAddressCompleted) {
							var selected_paytype = jQuery('input[name=payment_method]:checked').val();
							if (selected_paytype !== 'garan24_invoice' && selected_paytype !== 'garan24_part_payment') {

								jQuery(".garan24-response").hide();

								// Replace fetched customer values from Garan24 with the original customer values
								jQuery("#billing_first_name").val('<?php echo $original_billing_first_name;?>');
								jQuery("#billing_last_name").val('<?php echo $original_billing_last_name;?>');
								jQuery("#billing_company").val('<?php echo $original_billing_company;?>');
								jQuery("#billing_address_1").val('<?php echo $original_customer['address_1'];?>');
								jQuery("#billing_address_2").val('<?php echo $original_customer['address_2'];?>');
								jQuery("#billing_postcode").val('<?php echo $original_customer['postcode'];?>');
								jQuery("#billing_city").val('<?php echo $original_customer['city'];?>');

								jQuery("#shipping_first_name").val('<?php echo $original_shipping_first_name;?>');
								jQuery("#shipping_last_name").val('<?php echo $original_shipping_last_name;?>');
								jQuery("#shipping_company").val('<?php echo $original_shipping_company;?>');
								jQuery("#shipping_address_1").val('<?php echo $original_customer['shipping_address_1'];?>');
								jQuery("#shipping_address_2").val('<?php echo $original_customer['shipping_address_2'];?>');
								jQuery("#shipping_postcode").val('<?php echo $original_customer['shipping_postcode'];?>');
								jQuery("#shipping_city").val('<?php echo $original_customer['shipping_city'];?>');


							}
						}
						// console.log( getAddressCompleted );
					});
				});
			</script>
			<?php
		}
	} // End function


	/**
	 * JS for fetching the personal identity number before the call to Garan24
	 * and populating the checkout fields after the call to Garan24.
	 */
	function js() {
		if ( is_checkout() && $this->get_shop_country() == 'SE' && ( $this->partpay_enabled || $this->invo_enabled ) ) {

			if ( defined( 'WOOCOMMERCE_GARAN24_CHECKOUT' ) ) {
				return;
			}
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {

					$(document).on('click', '.compadress', function () {
						var value = $(this).attr("id");

						var json = $("#h" + value).val();
						var info = JSON.parse(json);

						garan24info("company", info, value);
					});

					function garan24info(type, info, value) {

						if (type == 'company') {
							var adress = info[0][value];
							var orgno_getadress = "";
							/*
							 if(jQuery('#garan24_pno').val() != ''){
							 orgno_getadress = jQuery('#garan24_pno').val();
							 }
							 */
							jQuery("#billing_first_name").val(adress['fname']);
							jQuery("#billing_last_name").val(adress['lname']);
							jQuery("#billing_company").val(adress['company']); //.prop( "readonly", true );
							jQuery("#billing_address_1").val(adress['street']); //.prop( "readonly", true );
							jQuery("#billing_address_2").val(adress['careof']); //.prop( "readonly", true );
							jQuery("#billing_postcode").val(adress['zip']); //.prop( "readonly", true );
							jQuery("#billing_city").val(adress['city']); //.prop( "readonly", true );

							jQuery("#shipping_first_name").val(adress['fname']);
							jQuery("#shipping_last_name").val(adress['lname']);
							jQuery("#shipping_company").val(adress['company']); //.prop( "readonly", true );
							jQuery("#shipping_address_1").val(adress['street']); //.prop( "readonly", true );
							jQuery("#shipping_address_2").val(adress['careof']); //.prop( "readonly", true );
							jQuery("#shipping_postcode").val(adress['zip']); //.prop( "readonly", true );
							jQuery("#shipping_city").val(adress['city']); //.prop( "readonly", true );

							jQuery("#phone_number").val(adress['cellno']);
							// jQuery("#garan24_pno").val(orgno_getadress);
							getAddressCompleted = 'yes';
						}

						if (type == 'private') {
							if (value == 0) {

								var adress = info[0][value];
								var pno_getadress = "";

								/*
								 if(jQuery('#garan24_pno').val() != ''){
								 pno_getadress = jQuery('#garan24_pno').val();
								 }
								 */
								jQuery("#billing_first_name").val(adress['fname']); //.prop( "readonly", true );
								jQuery("#billing_last_name").val(adress['lname']); //.prop( "readonly", true );
								jQuery("#billing_address_1").val(adress['street']); //.prop( "readonly", true );
								jQuery("#billing_address_2").val(adress['careof']);
								jQuery("#billing_postcode").val(adress['zip']); //.prop( "readonly", true );
								jQuery("#billing_city").val(adress['city']); //.prop( "readonly", true );

								jQuery("#shipping_first_name").val(adress['fname']); //.prop( "readonly", true );
								jQuery("#shipping_last_name").val(adress['lname']); //.prop( "readonly", true );
								jQuery("#shipping_address_1").val(adress['street']); //.prop( "readonly", true );
								jQuery("#shipping_address_2").val(adress['careof']);
								jQuery("#shipping_postcode").val(adress['zip']); //.prop( "readonly", true );
								jQuery("#shipping_city").val(adress['city']); //.prop( "readonly", true );

								jQuery("#phone_number").val(adress['cellno']);
								// jQuery("#garan24_pno").val(pno_getadress);
								getAddressCompleted = 'yes';
							}
						}
					}


					jQuery(document).on('click', '.garan24-push-pno', function () {
						pno_getadress = '';

						if (jQuery('#garan24_invoice_pno').length && jQuery('#garan24_invoice_pno').val() != '') {
							pno_getadress = jQuery('#garan24_invoice_pno').val();
						} else if (jQuery('#garan24_part_payment_pno').length && jQuery('#garan24_part_payment_pno').val() != '') {
							pno_getadress = jQuery('#garan24_part_payment_pno').val();
						}

						if (pno_getadress == '') {
							$(".garan24-get-address-message").show();
							$(".garan24-get-address-message").html('<span style="clear:both; margin: 5px 2px; padding: 4px 8px; background:#ffecec"><?php _e( 'Be kind and enter a date of birth!', 'woocommerce-gateway-garan24' );?></span>');
						} else {

							jQuery.post(
								'<?php echo site_url() . '/wp-admin/admin-ajax.php' ?>',
								{
									action: 'ajax_request',
									pno_getadress: pno_getadress,
									_wpnonce: '<?php echo wp_create_nonce( 'nonce-register_like' ); ?>',
								},
								function (response) {
									// console.log(response);

									if (response.get_address_message == "" || (typeof response.get_address_message === 'undefined')) {
										$(".garan24-get-address-message").hide();

										//if(garan24_client_type == "company"){
										var adresses = new Array();
										adresses.push(response);

										var res = "";
										//console.log(adresses[0].length);

										if (adresses[0].length < 2) {

											// One address found
											$(".garan24-response").show();
											res += '<ul class="woocommerce-message garan24-get-address-found"><li><?php _e( 'Address found and added to the checkout form.', 'woocommerce-gateway-garan24' );?></li></ul>';
											garan24info('private', adresses, 0);

										} else {

											// Multiple addresses found
											$(".garan24-response").show();

											res += '<ul class="woocommerce-message garan24-get-address-found multiple"><li><?php _e( 'Multiple addresses found. Select one address to add it to the checkout form.', 'woocommerce-gateway-garan24' );?></li><li>';
											for (var a = 0; a <= adresses.length; a++) {

												res += '<div id="adress' + a + '" class="adressescompanies">' +
													'<input type="radio" id="' + a + '" name="garan24-selected-company" value="garan24-selected-company' + a + '" class="compadress"  /><label for="garan24-selected-company' + a + '">' + adresses[0][a]['company'];
												if (adresses[0][a]['street'] != null) {
													res += ', ' + adresses[0][a]['street'];
												}

												if (adresses[0][a]['careof'] != '') {
													res += ', ' + adresses[0][a]['careof'];
												}

												res += ', ' + adresses[0][a]['zip'] + ' ' + adresses[0][a]['city'] + '</label>';
												res += "<input type='hidden' id='h" + a + "' value='" + JSON.stringify(adresses) + "' />";
												res += '</div>';

											}

											res += '</li></ul>';

										}

										jQuery(".garan24-response").html(res);

										// Scroll to .garan24-response
										$("html, body").animate({
												scrollTop: $(".garan24-response").offset().top
											},
											'slow');

										/*}
										 else{
										 garan24info(garan24_client_type, response, 0);
										 }*/
									}
									else {
										$(".garan24-get-address-message").show();
										$(".garan24-response").hide();

										jQuery(".garan24-get-address-message").html('<span style="clear:both;margin:5px 2px;padding:4px 8px;background:#ffecec">' + response.get_address_message + '</span>');

										$(".checkout .input-text").each(function (index) {
											$(this).val("");
											$(this).prop("readonly", false);
										});
									}
								}
							);
						}
					});
				});
			</script>
			<?php
		}
	} // End function


	/**
	 * Display the GetAddress fields
	 */
	public function get_address_button( $country ) {
		if ( ( $this->invo_enabled && $this->invo_dob_display == 'description_box' ) || ( $this->partpay_enabled && $this->partpay_dob_display == 'description_box' ) ) {
			ob_start();

			// Only display GetAddress button for Sweden
			if ( $country == 'SE' ) { ?>
				<button type="button" style="margin-top:0.5em" class="garan24-push-pno get-address-button button alt"><?php _e( 'Fetch', 'woocommerce-gateway-garan24' ); ?></button>
				<p class="form-row">
				<div class="garan24-get-address-message"></div>
				</p>
				<?php
			}

			return ob_get_clean();
		}
	} // End function


	/**
	 * Display the GetAddress response above the billing form on checkout
	 */
	public function get_address_response() {
		if ( ( $this->invo_enabled && $this->invo_dob_display == 'description_box' ) || ( $this->partpay_enabled && $this->partpay_dob_display == 'description_box' ) ) {

			?>
			<div class="garan24-response"></div>
			<?php
		}
	} // End function


	/**
	 * Ajax request callback function
	 */
	function ajax_request() {
		// The $_REQUEST contains all the data sent via ajax
		if ( isset( $_REQUEST ) ) {
			if ( '' != $this->partpay_eid && '' != $this->partpay_secret ) {
				$garan24_eid      = $this->partpay_eid;
				$garan24_secret   = $this->partpay_secret;
				$garan24_testmode = $this->partpay_testmode;
			} elseif ( '' != $this->invo_eid && '' != $this->invo_secret ) {
				$garan24_eid      = $this->invo_eid;
				$garan24_secret   = $this->invo_secret;
				$garan24_testmode = $this->invo_testmode;
			}

			// Test mode or Live mode
			if ( $garan24_testmode == 'yes' ) {
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

			$k = new Garan24();

			$k->config( $garan24_eid,                                            // EID
				$garan24_secret,                                        // Secret
				'SE',                                                    // Country
				'SE',                                                    // Language
				get_woocommerce_currency(),                            // Currency
				$garan24_mode,                                            // Live or test
				$pcStorage = 'json',                                    // PClass storage
				$pcURI = '/srv/pclasses.json'                            // PClass storage URI path
			);

			$pno_getadress = $_REQUEST['pno_getadress'];
			$return        = array();

			$k->setCountry( 'SE' ); // Sweden only
			try {
				$addrs = $k->getAddresses( $pno_getadress );

				foreach ( $addrs as $addr ) {

					//$return[] = $addr->toArray();
					$return[] = array(
						'email'   => utf8_encode( $addr->getEmail() ),
						'telno'   => utf8_encode( $addr->getTelno() ),
						'cellno'  => utf8_encode( $addr->getCellno() ),
						'fname'   => utf8_encode( $addr->getFirstName() ),
						'lname'   => utf8_encode( $addr->getLastName() ),
						'company' => utf8_encode( $addr->getCompanyName() ),
						'careof'  => utf8_encode( $addr->getCareof() ),
						'street'  => utf8_encode( $addr->getStreet() ),
						'zip'     => utf8_encode( $addr->getZipCode() ),
						'city'    => utf8_encode( $addr->getCity() ),
						'country' => utf8_encode( $addr->getCountry() ),
					);

				}

			} catch ( Exception $e ) {
				// $message = "{$e->getMessage()} (#{$e->getCode()})\n";
				$return = $e;
				$return = array(
					'get_address_message' => __( 'No address found', 'woocommerce-gateway-garan24' )
				);

			}

			wp_send_json( $return );

			// If you're debugging, it might be useful to see what was sent in the $_REQUEST
			// print_r($_REQUEST);
		} else {
			echo '';
			die();
		}

		die();
	} // End function

	// Helper function - get_shop_country
	public function get_shop_country() {
		$garan24_default_country = get_option( 'woocommerce_default_country' );

		// Check if woocommerce_default_country includes state as well. If it does, remove state
		if ( strstr( $garan24_default_country, ':' ) ) {
			$garan24_shop_country = current( explode( ':', $garan24_default_country ) );
		} else {
			$garan24_shop_country = $garan24_default_country;
		}

		return apply_filters( 'garan24_shop_country', $garan24_shop_country );
	}

} // End Class
$wc_garan24_get_address = new WC_Garan24_Get_Address;
