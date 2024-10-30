<?php
/**
 * Reusable functions
 *
 * @author  BusinessOnBot
 * @package BusinessOnBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the Cart History Data.
 *
 * @param int $cart_id - Abandoned Cart ID.
 *
 * @return object|bool $cart_history - From the Abandoned Cart History table.
 */
function businessonbot_get_data_cart_history( int $cart_id ) {
	global $wpdb;

	$cart_history = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT id, user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored, recovered_cart, user_type, checkout_link FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
			$cart_id
		)
	);

	if ( is_array( $cart_history ) && count( $cart_history ) > 0 ) {
		return $cart_history[0];
	} else {
		return false;
	}
}

/**
 * Returns the Guest Data.
 *
 * @param int $user_id - Guest User ID.
 *
 * @return object|bool $guest_data - From the Guest History table.
 */
function businessonbot_get_data_guest_history( int $user_id ) {

	global $wpdb;

	$guest_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT billing_first_name, billing_last_name, billing_zipcode, email_id, phone, shipping_zipcode, shipping_charges, billing_company_name, billing_address_1, billing_address_2, billing_city, billing_country, shipping_first_name, shipping_last_name, shipping_company_name, shipping_address_1, shipping_address_2, shipping_city, shipping_country FROM {$wpdb->prefix}businessonbot_guest_abandoned_cart WHERE id = %d",
			$user_id
		)
	);

	if ( is_array( $guest_data ) && count( $guest_data ) > 0 ) {
		return $guest_data[0];
	} else {
		return false;
	}
}

/**
 * Return an array of product details.
 *
 * @param string $cart_data - Abandoned Cart Data frm the Cart History table.
 *
 * @return array $product_details - Product Details.
 */
function businessonbot_get_product_details( string $cart_data ): array {

	$product_details = array();
	$cart_value      = json_decode( stripslashes( $cart_data ) );
	$item_count      = count( $cart_value->cart );

	if ( isset( $cart_value->cart ) && 0 !== $item_count ) {
		foreach ( $cart_value->cart as $product_data ) {
			$product_id = $product_data->variation_id > 0 ? $product_data->variation_id : $product_data->product_id;

			$details = (object) array(
				'product_id'    => $product_data->product_id,
				'variation_id'  => $product_data->variation_id,
				'product_name'  => get_the_title( $product_id ),
				'line_subtotal' => $product_data->line_subtotal,
			);

			$product_details[] = $details;
		}
	}

	return $product_details;
}

/**
 * It will add the js for capturing the guest cart.
 *
 * @hook woocommerce_after_checkout_billing_form
 */
function businessonbot_user_side_js(): void {

	if ( ! is_user_logged_in() ) {
		wp_nonce_field( 'businessonbot_save_guest_ab_cart', 'businessonbot_guest_capture_nonce' );

		wp_enqueue_script(
			'businessonbot_guest_capture',
			plugins_url( '../assets/js/businessonbot_guest_capture.min.js', __FILE__ ),
			array( 'jquery' ),
			BUSINESSONBOT_VERSION,
			true
		);
		$enable_gdpr = get_option( 'businessonbot_enable_gdpr_consent', '' );
		$guest_msg   = get_option( 'businessonbot_guest_cart_capture_msg' );

		$session_gdpr = BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_cart_tracking_refused' );
		$show_gdpr    = ! ( 'yes' === $session_gdpr );

		$vars = array();
		if ( 'on' === $enable_gdpr ) {
			$display_msg = isset( $guest_msg ) && '' !== $guest_msg ? $guest_msg : __( 'Saving your email and cart details helps us keep you up to date with this order.', 'businessonbot' );
			$display_msg = apply_filters( 'businessonbot_gdpr_email_consent_guest_users', $display_msg );

			$no_thanks = get_option( 'businessonbot_gdpr_allow_opt_out', '' );
			$no_thanks = apply_filters( 'businessonbot_gdpr_opt_out_text', $no_thanks );

			$opt_out_confirmation_msg = get_option( 'businessonbot_gdpr_opt_out_message', '' );
			$opt_out_confirmation_msg = apply_filters( 'businessonbot_gdpr_opt_out_confirmation_text', $opt_out_confirmation_msg );

			$vars = array(
				'_show_gdpr_message'        => $show_gdpr,
				'_gdpr_message'             => htmlspecialchars( $display_msg, ENT_QUOTES ),
				'_gdpr_nothanks_msg'        => htmlspecialchars( $no_thanks, ENT_QUOTES ),
				'_gdpr_after_no_thanks_msg' => htmlspecialchars( $opt_out_confirmation_msg, ENT_QUOTES ),
				'enable_ca_tracking'        => true,
				'ajax_nonce'                => wp_create_nonce( 'businessonbot_gdpr_nonce' ),
			);
		}

		$vars['ajax_url'] = admin_url( 'admin-ajax.php' );

		wp_localize_script(
			'businessonbot_guest_capture',
			'businessonbot_guest_capture_params',
			$vars
		);
	}
}

/**
 * It will add the guest users data in the database.
 *
 * @hook wp_ajax_nopriv_businessonbot_save_guest_ab_cart
 * @globals mixed $wpdb
 * @globals mixed $woocommerce
 */
function businessonbot_save_guest_ab_cart(): void {
	if ( ! is_user_logged_in() ) {

		if ( ! isset( $_POST['businessonbot_guest_capture_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['businessonbot_guest_capture_nonce'] ) ), 'businessonbot_save_guest_ab_cart' ) ) {
			exit;
		}

		global $wpdb;

		$fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'billing_phone',
			'billing_email',
			'order_notes',
			'ship_to_billing',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
				if ( WC()->session ) {
					WC()->session->set( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
				}
			}
		}

		// If a record is present in the guest cart history table for the same email id, then delete the previous records.
		$results_guest = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}businessonbot_guest_abandoned_cart WHERE email_id = %s",
				BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_email' )
			)
		);

		if ( $results_guest ) {
			foreach ( $results_guest as $key => $value ) {
				$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND recovered_cart = %s",
						$value->id,
						0
					)
				);
				// update existing record and create new record if guest cart history table will have the same email id.

				if ( count( $result ) ) {
					$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"DELETE FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %s",
							$value->id
						)
					);
				}
			}
		}

		// Insert record in guest table.
		$billing_first_name = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_first_name' );

		$billing_last_name = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_last_name' );

		$shipping_zipcode = '';
		$billing_zipcode  = '';

		if ( '' !== BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_postcode' ) ) {
			$shipping_zipcode = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_postcode' );
		} elseif ( '' !== BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_postcode' ) ) {
			$billing_zipcode  = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_postcode' );
			$shipping_zipcode = $billing_zipcode;
		}
		$shipping_charges = WC()->cart->get_shipping_total();

		$billing_company     = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_company' );
		$billing_address_1   = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_address_1' );
		$billing_address_2   = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_address_2' );
		$billing_city        = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_city' );
		$billing_country     = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_country' );
		$billing_phone       = BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_phone' );
		$shipping_first_name = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_first_name' );
		$shipping_last_name  = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_last_name' );
		$shipping_company    = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_company' );
		$shipping_address_1  = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_address_1' );
		$shipping_address_2  = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_address_2' );
		$shipping_city       = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_city' );
		$shipping_country    = BusinessOnBot_Common::businessonbot_get_cart_session( 'shipping_country' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}businessonbot_guest_abandoned_cart ( billing_first_name, billing_last_name, email_id, billing_zipcode, shipping_zipcode, shipping_charges, billing_company_name, billing_address_1, billing_address_2, billing_city, billing_country, phone, shipping_first_name, shipping_last_name, shipping_company_name, shipping_address_1, shipping_address_2, shipping_city, shipping_country ) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
				$billing_first_name,
				$billing_last_name,
				BusinessOnBot_Common::businessonbot_get_cart_session( 'billing_email' ),
				$billing_zipcode,
				$shipping_zipcode,
				$shipping_charges,
				$billing_company,
				$billing_address_1,
				$billing_address_2,
				$billing_city,
				$billing_country,
				$billing_phone,
				$shipping_first_name,
				$shipping_last_name,
				$shipping_company,
				$shipping_address_1,
				$shipping_address_2,
				$shipping_city,
				$shipping_country
			)
		);

		// Insert record in abandoned cart table for the guest user.
		$user_id = $wpdb->insert_id;
		BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_user_id', $user_id );
		$local_time   = current_datetime();
		$current_time = $local_time->getTimestamp() + $local_time->getOffset();

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %s AND recovered_cart = %s AND user_type = %s",
				$user_id,
				0,
				0,
				'GUEST'
			)
		);

		$cart = array();

		$cart['cart'] = WC()->cart->get_cart();

		if ( 0 === count( $results ) ) {
			$get_cookie = WC()->session->get_session_cookie();

			$cart_info = wp_json_encode( $cart );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE session_id LIKE %s AND cart_ignored = %s AND recovered_cart = %s",
					$get_cookie[0],
					0,
					0
				)
			);
			if ( 0 === count( $results ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored, recovered_cart, user_type, session_id ) VALUES ( %s, %s, %s, %s, %s, %s, %s )",
						$user_id,
						$cart_info,
						$current_time,
						0,
						0,
						'GUEST',
						$get_cookie[0]
					)
				);

				$abandoned_cart_id = $wpdb->insert_id;
				BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_abandoned_cart_id', $abandoned_cart_id );
				BusinessOnBot_Common::businessonbot_add_checkout_link( $abandoned_cart_id );
				BusinessOnBot_Common::businessonbot_run_webhook_after_abancart( $abandoned_cart_id );
			} else {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET user_id = %s, abandoned_cart_info = %s, abandoned_cart_time = %s WHERE session_id = %s AND cart_ignored = %s",
						$user_id,
						$cart_info,
						$current_time,
						$get_cookie[0],
						0
					)
				);
				$get_abandoned_record = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %s AND session_id = %s",
						$user_id,
						0,
						$get_cookie[0]
					)
				);

				if ( count( $get_abandoned_record ) > 0 ) {
					$abandoned_cart_id = $get_abandoned_record[0]->id;
					BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_abandoned_cart_id', $abandoned_cart_id );
					BusinessOnBot_Common::businessonbot_add_checkout_link( $abandoned_cart_id );
					BusinessOnBot_Common::businessonbot_run_webhook_after_abancart( $abandoned_cart_id );
				}

				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"INSERT INTO $wpdb->usermeta ( user_id, meta_key, meta_value ) VALUES ( %s, %s, %s )",
						$user_id,
						'_woocommerce_persistent_cart',
						$cart_info
					)
				);
			}

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"INSERT INTO $wpdb->usermeta ( user_id, meta_key, meta_value ) VALUES ( %s, %s, %s )",
					$user_id,
					'_woocommerce_persistent_cart',
					$cart_info
				)
			);
		}
	}
}

/**
 * It will populate the data on the checkout field if user comes from the abandoned cart reminder emails.
 *
 * @hook woocommerce_checkout_fields
 *
 * @param array $fields All fields of checkout page.
 *
 * @return array $fields
 */
function businessonbot_guest_checkout_fields( array $fields ): array {

	if ( ! is_object( WC()->session ) ) {
		return $fields;
	}

	$session_fields = array(
		'guest_first_name'            => 'billing_first_name',
		'guest_last_name'             => 'billing_last_name',
		'guest_company_name'          => 'billing_company',
		'guest_address_1'             => 'billing_address_1',
		'guest_address_2'             => 'billing_address_2',
		'guest_city'                  => 'billing_city',
		'guest_country'               => 'billing_country',
		'guest_zipcode'               => 'billing_postcode',
		'guest_email'                 => 'billing_email',
		'guest_phone'                 => 'billing_phone',
		'guest_ship_to_billing'       => 'ship_to_billing',
		'guest_order_notes'           => 'order_comments',
		'guest_shipping_first_name'   => 'shipping_first_name',
		'guest_shipping_last_name'    => 'shipping_last_name',
		'guest_shipping_company_name' => 'shipping_company',
		'guest_shipping_address_1'    => 'shipping_address_1',
		'guest_shipping_address_2'    => 'shipping_address_2',
		'guest_shipping_city'         => 'shipping_city',
		'guest_shipping_country'      => 'shipping_country',
		'guest_shipping_zipcode'      => 'shipping_postcode',
	);

	foreach ( $session_fields as $session_key => $post_key ) {
		$session_value = WC()->session->get( $session_key, '' );
		if ( ! empty( $session_value ) ) {
			$_POST[ $post_key ] = $session_value;
		}
	}

	return $fields;
}
