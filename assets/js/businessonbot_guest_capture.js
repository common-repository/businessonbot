/**
 * Captures Guest Cart Information
 *
 * @package BusinessOnBot
 */

let gdpr_consent = true;
jQuery( 'input#billing_phone' ).on(
	'change',
	function () {
		if ( typeof businessonbot_guest_capture_params === 'undefined' ) {
			return false;
		}

		const params = businessonbot_guest_capture_params;

		let message_data = params._show_gdpr_message ? params._show_gdpr_message : true;

		if ( gdpr_consent && message_data ) {
			let data = {
				billing_first_name	: jQuery( '#billing_first_name' ).val(),
				billing_last_name	: jQuery( '#billing_last_name' ).val(),
				billing_company		: jQuery( '#billing_company' ).val(),
				billing_address_1	: jQuery( '#billing_address_1' ).val(),
				billing_address_2	: jQuery( '#billing_address_2' ).val(),
				billing_city		: jQuery( '#billing_city' ).val(),
				billing_state		: jQuery( '#billing_state' ).val(),
				billing_postcode	: jQuery( '#billing_postcode' ).val(),
				billing_country		: jQuery( '#billing_country' ).val(),
				billing_phone		: jQuery( '#billing_phone' ).val(),
				billing_email		: jQuery( '#billing_email' ).val(),
				order_notes			: jQuery( '#order_comments' ).val(),
				shipping_first_name	: jQuery( '#shipping_first_name' ).val(),
				shipping_last_name	: jQuery( '#shipping_last_name' ).val(),
				shipping_company	: jQuery( '#shipping_company' ).val(),
				shipping_address_1	: jQuery( '#shipping_address_1' ).val(),
				shipping_address_2	: jQuery( '#shipping_address_2' ).val(),
				shipping_city		: jQuery( '#shipping_city' ).val(),
				shipping_state		: jQuery( '#shipping_state' ).val(),
				shipping_postcode	: jQuery( '#shipping_postcode' ).val(),
				shipping_country	: jQuery( '#shipping_country' ).val(),
				ship_to_billing		: jQuery( '#shiptobilling-checkbox' ).val(),
				businessonbot_guest_capture_nonce: jQuery( '#businessonbot_guest_capture_nonce' ).val(),
				action: 'businessonbot_save_guest_ab_cart'
			};
			jQuery.post( params.ajax_url, data, function (response) {} );
		}
	}
);

jQuery( document ).ready(
	function () {
		if ( typeof businessonbot_guest_capture_params === 'undefined' ) {
			return false;
		}

		const params = businessonbot_guest_capture_params;

		if ( params._show_gdpr_message && ! jQuery( '#businessonbot_gdpr_message_block' ).length && gdpr_consent ) {
			jQuery( '#billing_email' ).after(
				'<span id=businessonbot_gdpr_message_block><span style="font-size: small">'
				+ params._gdpr_message
				+ '<a style="cursor:pointer;text-decoration:none;" id=businessonbot_gdpr_no_thanks>'
				+ params._gdpr_nothanks_msg
				+ '</a></span></span>'
			);
		}
		jQuery( '#businessonbot_gdpr_no_thanks' ).click(
			function () {
				params._show_gdpr_message = false;
				gdpr_consent              = false;
				// Run an ajax call and save the data that user did not give consent.
				let data = {
					action : 'businessonbot_gdpr_refused',
					ajax_nonce: params.ajax_nonce,
				};
				jQuery.post(
					params.ajax_url,
					data,
					function () {
						jQuery( '#businessonbot_gdpr_message_block' ).empty().append(
							'<span style="font-size:small">' + params._gdpr_after_no_thanks_msg + '</span>'
						).delay( 5000 ).fadeOut();
					}
				);
			}
		);
	}
);
