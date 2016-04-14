jQuery(document).ready(function ($) {

	// Update country
	$(document).on('change', 'select#garan24-checkout-euro-country', function (event) {
		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		new_country = $(this).val();

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_country_callback',
					new_country: new_country,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'success' );
					// console.log( response.data );

					/*
					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
					*/

					document.location.assign(response.data.new_url);
				},
				error: function (response) {
					// console.log('error');
					// console.log(response);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);

	});

	// Update order note
	$(document).on('change', 'textarea#garan24-checkout-order-note, #kco_order_note', function (event) {
		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		order_note = $(this).val();

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_order_note_callback',
					order_note: order_note,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'success' );
					// console.log( response.data );

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				},
				error: function (response) {
					// console.log('error');
					// console.log(response);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);

	});

	// Old order note shortcode
	/*
	 $('#kco_order_note').blur(function () {
	 var kco_order_note = '';

	 if( $('#kco_order_note').val() != '' ) {
	 var kco_order_note = $('#kco_order_note').val();
	 }

	 if(kco_order_note == '') {

	 } else {

	 $.post(
	 kcoAjax.ajaxurl,
	 {
	 action			: 'customer_update_kco_order_note',
	 kco_order_note	: kco_order_note,
	 kco_order_id	: '<?php echo WC()->session->order_awaiting_payment;?>',
	 _wpnonce		: kcoAjax.garan24_checkout_nonce,
	 },
	 function( response ) {
	 console.log( response );
	 }
	 );

	 }
	 });
	 */

	// Update shipping (v2)
	$(document).on('change', 'table#kco-totals #kco-page-shipping input[type="radio"]', function (event) {
		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		new_method = $(this).val();
		kco_widget = $('#garan24-checkout-widget');

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_shipping_callback',
					new_method: new_method,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'success' );
					// console.log( response.data );

					$(kco_widget).html(response.data.widget_html);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				},
				error: function (response) {
					// console.log('error');
					// console.log(response);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);

	});

	// Update cart (v2)
	$(document).on('change', 'td.product-quantity input[type=number]', function (event) {
		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		ancestor = $(this).closest('td.product-quantity');
		cart_item_key = $(ancestor).data('cart_item_key');
		new_quantity = $(this).val();
		kco_widget = $('#garan24-checkout-widget');

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_cart_callback_update',
					cart_item_key: cart_item_key,
					new_quantity: new_quantity,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'success' );
					// console.log( response.data );

					$(kco_widget).html(response.data.widget_html);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				},
				error: function (response) {
					// console.log('error');
					// console.log(response);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);
	});

	// Remove cart item (v2)
	$(document).on('click', 'td.kco-product-remove a', function (event) {
		event.preventDefault();

		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		ancestor = $(this).closest('tr').find('td.product-quantity');
		item_row = $(this).closest('tr');
		kco_widget = $('#garan24-checkout-widget');
		cart_item_key_remove = $(ancestor).data('cart_item_key');

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_cart_callback_remove',
					cart_item_key_remove: cart_item_key_remove,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					if (0 == response.data.item_count) {
						// window.location.href = response.data.cart_url;
						location.reload();
					} else {
						$(kco_widget).html(response.data.widget_html);
						$(item_row).remove();

						if (typeof window._garan24Checkout == 'function') {
							window._garan24Checkout(function (api) {
								api.resume();
							});
						} else {
							location.reload();
						}
					}
				},
				error: function (response) {
					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);
	});

	// Add coupon (v2)
	$('#garan24-checkout-widget .checkout_coupon').on('submit', function (event) {
		event.preventDefault();

		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		coupon = $('#garan24-checkout-widget #coupon_code').val();
		kco_widget = $('#garan24-checkout-widget');
		input_field = $(this).find('#coupon_code');

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_coupons_callback',
					coupon: coupon,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'success' );
					// console.log( response.data );

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}

					if (response.data.coupon_success) {
						$('#garan24_checkout_coupon_result').html('<p>Coupon added.</p>');

						html_string = '<tr class="kco-applied-coupon"><td class="kco-rightalign">Coupon: ' + response.data.coupon + ' <a class="kco-remove-coupon" data-coupon="' + response.data.coupon + '" href="#">(remove)</a></td><td class="kco-rightalign">-' + response.data.amount + '</td></tr>';

						$('tr#kco-page-total').before(html_string);
						$(input_field).val('');
						$(kco_widget).html(response.data.widget_html);
					}
					else {
						$('#garan24_checkout_coupon_result').html('<p>Coupon could not be added.</p>');
					}
				},
				error: function (response) {
					$('#garan24_checkout_coupon_result').html('<p>Coupon could not be added.</p>');

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);

	});


	// Remove coupon (v2)
	$(document).on('click', 'table#kco-totals .kco-remove-coupon', function (event) {
		event.preventDefault();

		if (typeof window._garan24Checkout == 'function') {
			window._garan24Checkout(function (api) {
				api.suspend();
			});
		}

		remove_coupon = $(this).data('coupon');
		clicked_el = $(this);
		kco_widget = $('#garan24-checkout-widget');

		$.ajax(
			kcoAjax.ajaxurl,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'garan24_checkout_remove_coupon_callback',
					remove_coupon: remove_coupon,
					nonce: kcoAjax.garan24_checkout_nonce
				},
				success: function (response) {
					// console.log( 'remove-success' );
					// console.log( response.data );

					$(clicked_el).closest('tr').remove();
					$(kco_widget).html(response.data.widget_html);

					// Remove WooCommerce notification
					$('#garan24-checkout-widget .woocommerce-info + .woocommerce-message').remove();

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				},
				error: function (response) {
					// console.log('remove-error');
					// console.log(response);

					if (typeof window._garan24Checkout == 'function') {
						window._garan24Checkout(function (api) {
							api.resume();
						});
					}
				}
			}
		);
	});

	// End KCO widget

	// Address change (email, postal code) v2
	if (typeof window._garan24Checkout == 'function') {
		window._garan24Checkout(function (api) {
			// For v2 use 'change' JS event to capture
			if ('v2' == kcoAjax.version) {
				var customerEmail = '';
				var customerPostal = '';

				api.on({
					'change': function (data) {
						// console.log(data);

						if ('' != data.email && '' != data.postal_code) {
							// Check if email and postal code have changed since last 'change' event
							if ( customerEmail != data.email || customerPostal != data.postal_code ) {
								customerEmail  = data.email;
								customerPostal = data.postal_code;

								window._garan24Checkout(function (api) {
									api.suspend();
								});

								// console.log('V2');

								// Check if email is not defined (AT and DE only) and set it to this value
								// For AT and DE, email field is not captured inside data object
								if (data.email === undefined) {
									data.email = 'guest_checkout@garan24.com';
								}

								if ('' != data.email) {
									kco_widget = $('#garan24-checkout-widget');

									$.ajax(
										kcoAjax.ajaxurl,
										{
											type: 'POST',
											dataType: 'json',
											data: {
												action: 'kco_iframe_change_cb',
												email: data.email,
												postal_code: data.postal_code,
												nonce: kcoAjax.garan24_checkout_nonce
											},
											success: function (response) {
												// Check if a product is out of stock
												if (false === response.success) {
													// console.log('false');
													location.reload();
													return;
												}

												$(kco_widget).html(response.data.widget_html);
											},
											error: function (response) {}
										}
									);

								}

								window._garan24Checkout(function (api) {
									api.resume();
								});

							}

						}
					}
				});
			}

			// Address change (postal code, region) v3
			if ('v3' == kcoAjax.version) {
				api.on({
					'shipping_address_change': function (data) {
						// console.log('****** Parent Page Received shipping_address_change DATA ******');
						// console.log('V3');

						if ('' != data.postal_code || '' != data.region) {
							kco_widget = $('#garan24-checkout-widget');

							$.ajax(
								kcoAjax.ajaxurl,
								{
									type: 'POST',
									dataType: 'json',
									data: {
										action: 'kco_iframe_shipping_address_change_cb',
										region: data.region,
										postal_code: data.postal_code,
										nonce: kcoAjax.garan24_checkout_nonce
									},
									success: function (response) {
										// console.log( response );
										$(kco_widget).html(response.data.widget_html);

										window._garan24Checkout(function (api) {
											api.resume();
										});
									},
									error: function (response) {
										window._garan24Checkout(function (api) {
											api.resume();
										});
									}
								}
							);
						}
					}
				});
			}


			api.on({
				'shipping_option_change': function (data) {
					new_method = data.id;
					kco_widget = $('#garan24-checkout-widget');

					$.ajax(
						kcoAjax.ajaxurl,
						{
							type: 'POST',
							dataType: 'json',
							data: {
								action: 'kco_iframe_shipping_option_change_cb',
								new_method: new_method,
								nonce: kcoAjax.garan24_checkout_nonce
							},
							success: function (response) {
								// console.log( 'success' );
								// console.log( response );

								$(kco_widget).html(response.data.widget_html);
							},
							error: function (response) {
								// console.log('error');
								// console.log(response);
							}
						}
					);
				}
			});

		});
	}

});