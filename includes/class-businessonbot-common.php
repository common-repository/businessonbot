<?php
/**
 * BusinessOnBot
 *
 * @author  BusinessOnBot
 * @package BusinessOnBot/Common
 */

defined( 'ABSPATH' ) || exit;

/**
 * It will have all the common functions for the plugin.
 */
class BusinessOnBot_Common {

	/**
	 * Set Cart Session variables
	 *
	 * TODO: Pass empty string when the variable is called in the source so that we can add type check in the function variable name.
	 *
	 * @param string $session_key Key of the session.
	 * @param string $session_value Value of the session.
	 */
	public static function businessonbot_set_cart_session( string $session_key, $session_value ): void {
		if ( WC()->session ) {
			WC()->session->set( $session_key, $session_value );
		}
	}

	/**
	 * Get Cart Session variables
	 *
	 * @param string $session_key Key of the session.
	 *
	 * @return array|string Value of the session.
	 */
	public static function businessonbot_get_cart_session( string $session_key ) {
		if ( ! is_object( WC()->session ) ) {
			return false;
		}

		return WC()->session->get( $session_key, '' );
	}

	/**
	 * Delete Cart Session variables
	 *
	 * @param string $session_key Key of the session.
	 */
	public static function businessonbot_unset_cart_session( string $session_key ): void {
		WC()->session->__unset( $session_key );
	}

	/**
	 * Delete the AC record and create a user meta record (for registered users)
	 * when the user chooses to opt out of cart tracking.
	 */
	public static function businessonbot_gdpr_refused(): void {

		if ( ! isset( $_POST['ajax_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['ajax_nonce'] ), 'businessonbot_gdpr_nonce' ) ) {
			wp_send_json( 'Nonce check failed' );
		}

		global $wpdb;
		$abandoned_cart_id = self::businessonbot_get_cart_session( 'abandoned_cart_id' );

		if ( $abandoned_cart_id > 0 ) {
			// Fetch the user ID - if greater than 0, we need to check & delete guest table record is applicable.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$user_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
					$abandoned_cart_id
				)
			);

			if ( $user_id >= 63000000 ) { // Guest user.
				// Delete the guest record.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->delete( "{$wpdb->prefix}businessonbot_guest_abandoned_cart", array( 'id' => $user_id ) );
			} else { // Registered cart.
				// Save the user choice of not being tracked.
				add_user_meta( $user_id, 'businessonbot_gdpr_tracking_choice', 0 );
			}
			// add in the session, that the user has refused tracking.
			self::businessonbot_set_cart_session( 'businessonbot_cart_tracking_refused', 'yes' );

			// Finally delete the cart history record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'businessonbot_abandoned_cart', array( 'id' => $abandoned_cart_id ) );
		}
	}

	/**
	 * It will captures the coupon code used by the customers.
	 * It will store the coupon code for the specific abandoned cart.
	 *
	 * @hook woocommerce_applied_coupon
	 *
	 * @param string $applied_coupon_code Coupon code.
	 *
	 * @return string $applied_coupon_code Coupon code.
	 * @globals mixed $wpdb
	 */
	public static function businessonbot_capture_applied_coupon( string $applied_coupon_code ): string {
		global $wpdb;

		$coupon_code = self::businessonbot_get_cart_session( '_businessonbot_coupon' );

		if ( empty( $coupon_code ) || empty( $applied_coupon_code ) ) {
			return $applied_coupon_code;
		}

		$user_id     = self::businessonbot_get_cart_session( '_businessonbot_user_id' );
		$user_id     = '' !== $user_id ? $user_id : get_current_user_id();
		$coupon_code = $applied_coupon_code;

		if ( is_user_logged_in() ) {
			$abandoned_cart_id_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = 0 AND recovered_cart = 0",
					$user_id
				)
			);
		} elseif ( ! is_user_logged_in() ) {
			$abandoned_cart_id_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = 0 AND recovered_cart = 0 ORDER BY id DESC LIMIT 1",
					$user_id
				)
			);
		}

		$abandoned_cart_id = '0';

		if ( ! empty( $abandoned_cart_id_results ) ) {
			$abandoned_cart_id = $abandoned_cart_id_results[0]->id;
		}

		$existing_coupon = ( get_user_meta( $user_id, '_businessonbot_coupon', true ) );
		$applied         = self::businessonbot_update_coupon_post_meta( $abandoned_cart_id, $coupon_code );

		if ( $applied ) {
			return $applied_coupon_code;
		}
		if ( is_array( $existing_coupon ) && count( $existing_coupon ) > 0 ) {
			foreach ( $existing_coupon as $key => $value ) {
				if ( isset( $value['coupon_code'] ) && $value['coupon_code'] !== $coupon_code ) {
					$existing_coupon[] = array(
						'coupon_code'    => $coupon_code,
						'coupon_message' => __(
							'Discount code applied successfully.',
							'businessonbot'
						),
					);
					update_user_meta(
						$user_id,
						'_businessonbot_coupon',
						$existing_coupon
					);

					return $applied_coupon_code;
				}
			}
		} else {
			$coupon_details[] = array(
				'coupon_code'    => $coupon_code,
				'coupon_message' => esc_html__(
					'Discount code applied successfully.',
					'businessonbot'
				),
			);
			update_user_meta( $user_id, '_businessonbot_coupon', $coupon_details );

			return $applied_coupon_code;
		}

		return $applied_coupon_code;
	}

	/**
	 * Update the Coupon data in post meta table.
	 *
	 * @param int    $cart_id - Abandoned Cart ID.
	 * @param string $coupon_code - Coupon code to be updated.
	 * @param string $msg - Msg to be added for the coupon.
	 */
	public static function businessonbot_update_coupon_post_meta( int $cart_id, string $coupon_code, string $msg = '' ): bool {

		// Set default.
		$msg = '' !== $msg ? $msg : __( 'Discount code applied successfully.', 'woocommerce-ac' );
		// Fetch the record from the DB.
		$get_coupons = get_post_meta( $cart_id, '_businessonbot_coupon', true );

		// If any coupon have been applied, populate them in the return array.
		if ( is_array( $get_coupons ) && count( $get_coupons ) > 0 ) {
			$exists = false;
			foreach ( $get_coupons as $coupon_data ) {
				if ( isset( $coupon_data['coupon_code'] ) && $coupon_code === $coupon_data['coupon_code'] ) {
					$exists = true;
				}
			}

			if ( ! $exists ) {
				$get_coupons[] = array(
					'coupon_code'    => $coupon_code,
					'coupon_message' => $msg,
				);
				update_post_meta( $cart_id, '_businessonbot_coupon', $get_coupons );

				return true;
			}
		} else {
			$get_coupons   = array();
			$get_coupons[] = array(
				'coupon_code'    => $coupon_code,
				'coupon_message' => $msg,
			);
			update_post_meta( $cart_id, '_businessonbot_coupon', $get_coupons );

			return true;
		}

		return false;
	}

	/**
	 * It will captures the coupon code errors specific to the abandoned carts.
	 *
	 * @hook woocommerce_coupon_error.
	 *
	 * @param string $error Error.
	 * @param string $error_code Error code.
	 *
	 * @globals mixed $wpdb .
	 * @return string $error Error.
	 */
	public static function businessonbot_capture_coupon_error( string $error, string $error_code ): string {
		unset( $error_code );
		global $wpdb;

		$coupon_code = self::businessonbot_get_cart_session( '_businessonbot_coupon' );
		$user_id     = self::businessonbot_get_cart_session( '_businessonbot_user_id' );

		if ( empty( $coupon_code ) ) {
			return $error;
		}

		$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

		$abandoned_cart_id_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %d AND recovered_cart = %d ORDER BY id DESC LIMIT 1",
				$user_id,
				0,
				0
			)
		);
		$abandoned_cart_id         = '0';

		if ( isset( $abandoned_cart_id_results ) && count( $abandoned_cart_id_results ) > 0 ) {
			$abandoned_cart_id = $abandoned_cart_id_results[0]->id;
		}

		if ( '' !== $coupon_code ) {
			$existing_coupon   = get_user_meta( $user_id, '_businessonbot_coupon' );
			$existing_coupon[] = array(
				'coupon_code'    => $coupon_code,
				'coupon_message' => $error,
			);
			if ( $user_id > 0 ) {
				self::businessonbot_update_coupon_post_meta( $abandoned_cart_id, $coupon_code, $error );
			}
			update_user_meta( $user_id, '_businessonbot_coupon', $existing_coupon );
		}

		return $error;
	}

	/**
	 * It will directly apply the coupon code if the coupon code present in the abandoned cart reminder email link.
	 * It will apply direct coupon on cart and checkout page.
	 *
	 * @hook woocommerce_before_cart_table
	 * @hook woocommerce_before_checkout_form
	 */
	public static function businessonbot_apply_direct_coupon_code(): void {
		global $businessonbot_abandon_cart;
		remove_action( 'woocommerce_cart_updated', array( $businessonbot_abandon_cart, 'businessonbot_store_cart_timestamp' ) );

		$coupon_code = self::businessonbot_get_cart_session( '_businessonbot_coupon' );

		if ( '' !== $coupon_code ) {
			// Add coupon.
			WC()->cart->apply_coupon( sanitize_text_field( $coupon_code ) );
			wc_print_notices();
			// Manually recalculate totals.  If you do not do this, a refresh is required before user will see updated totals when discount is removed.
			WC()->cart->calculate_totals();
			// need to clear the coupon code from session.
			self::businessonbot_unset_cart_session( '_businessonbot_coupon' );
		}
	}

	/**
	 * Add a scheduled action for the webhook to be delivered once the cart cut off is reached.
	 *
	 * @param int $cart_id - Abandoned Cart ID.
	 */
	public static function businessonbot_run_webhook_after_abancart( int $cart_id ): void {
		// check if the Webhook is present & active.
		global $wpdb;

		$get_webhook_status = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT status FROM `' . $wpdb->prefix . 'wc_webhooks` WHERE topic = %s',
				'businessonbot_cart.abancart'
			)
		);

		if ( isset( $get_webhook_status ) && 'active' === $get_webhook_status ) {
			// Reconfirm that the cart is either a registered user cart or a guest cart. The webhook will not be run for visitor carts.
			$cart_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT user_id, user_type, cart_ignored, recovered_cart FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
					$cart_id
				)
			);

			$user_id   = $cart_data[0]->user_id ?? 0;
			$user_type = $cart_data[0]->user_type ?? '';

			if ( $user_id > 0 && '' !== $user_type && '0' === $cart_data[0]->cart_ignored && $cart_data[0]->recovered_cart <= 0 ) {
				$cut_off = is_numeric( get_option( 'businessonbot_abandoned_cart_timeout', 10 ) ) ? get_option( 'businessonbot_plugin_cart_abandoned_time', 10 ) * 60 : 10 * 60;

				if ( $cut_off > 0 && ! as_has_scheduled_action( 'businessonbot_webhook_after_abancart', array( 'id' => $cart_id ) ) ) {
					// run the hook.
					as_schedule_single_action( time() + $cut_off, 'businessonbot_webhook_after_abancart', array( 'id' => $cart_id ) );
				}
			}
		}
	}

	/**
	 * Update Checkout Link in cart history table.
	 *
	 * @param int $cart_id - Cart ID.
	 */
	public static function businessonbot_add_checkout_link( int $cart_id ): void {
		global $wpdb;
		$checkout_page_id   = wc_get_page_id( 'checkout' );
		$checkout_page_link = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';

		// Force SSL if needed.
		$ssl_is_used = is_ssl();

		if ( true === $ssl_is_used || 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
			$checkout_page_link = str_ireplace( 'http:', 'https:', $checkout_page_link );
		}

		$encoding_checkout = $cart_id . '&url=' . $checkout_page_link;
		$validate_checkout = self::businessonbot_encrypt_validate( $encoding_checkout );

		$checkout_link = get_option( 'siteurl' ) . '/?businessonbot_action=checkout_link&validate=' . $validate_checkout;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'businessonbot_abandoned_cart',
			array( 'checkout_link' => $checkout_link ),
			array( 'id' => $cart_id )
		);
	}

	/**
	 * This function is used to encode the string.
	 *
	 * @param string $validate String need to encrypt.
	 *
	 * @return string $validate_encoded Encrypted string.
	 */
	public static function businessonbot_encrypt_validate( string $validate ): string {
		$crypt_key        = get_option( 'businessonbot_security_key' );
		$validate_encoded = BusinessOnBot_Aes_Ctr::encrypt( $validate, $crypt_key );

		return ( $validate_encoded );
	}
}
