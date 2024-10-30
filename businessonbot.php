<?php // phpcs:ignore PSR1.Classes.ClassDeclaration
/**
 * Plugin Name: BusinessOnBot
 * Plugin URI: https://businessonbot.com/
 * Description: BusinessOnBot is the ultimate solution for D2C brands to acquire new users on WhatsApp & Instagram. Trusted by 500+ brands, we offer seamless integration, omni-channel support, ad integration, and smart automation.
 * Version: 1.0.2
 * Author: BusinessOnBot
 * Author URI: https://businessonbot.com
 * Text Domain: businessonbot
 * Domain Path: /languages
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * WC tested up to: 9.3
 * Requires at least: 6.2
 * WC requires at least: 8.2
 * Requires Plugins: woocommerce
 *
 * @package BusinessOnBot
 */

defined( 'ABSPATH' ) || exit;

const BUSINESSONBOT_VERSION     = '1.0.2';
const BUSINESSONBOT_FILE        = __FILE__;
const BUSINESSONBOT_PATH        = __DIR__;
const BUSINESSONBOT_MIN_PHP_VER = '7.4';
const BUSINESSONBOT_MIN_WP_VER  = '6.2';
const BUSINESSONBOT_MIN_WC_VER  = '8.2';

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Main class
 */
if ( ! class_exists( 'BusinessOnBot' ) ) {
	/**
	 * It will add the hooks, filters, menu and the variables and all the necessary actions for the plugins which will be used
	 * all over the plugin.
	 *
	 * @package BusinessOnBot/Core
	 */
	class BusinessOnBot {
		/**
		 * Contains load errors.
		 *
		 * @var array
		 */
		public static array $errors = array();

		/**
		 * The init will add the hooks, filters and the variable which will be used all over the plugin.
		 */
		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ), 8 );

			// Ensure core before BusinessOnBot.
			add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 8 );

			// Load translations even if plugin requirements aren't met.
			add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

			// Declare it compatible with High-Performance Order Storage (HPOS).
			add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_feature_compatibility' ) );

			// Initialize settings.
			register_activation_hook( BUSINESSONBOT_FILE, array( __CLASS__, 'businessonbot_install_defaults' ) );
			register_deactivation_hook( BUSINESSONBOT_FILE, array( __CLASS__, 'businessonbot_deactivate' ) );

			// Check if database tables needs to be upgraded.
			add_action( 'plugins_loaded', array( __CLASS__, 'businessonbot_update_db_check' ) );
		}

		/**
		 * Displays any errors as admin notices.
		 */
		public static function admin_notices() {
			if ( empty( self::$errors ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo wp_kses_post( implode( '<br>', self::$errors ) );
			echo '</p></div>';
		}

		/**
		 * Loads plugin.
		 */
		public static function load() {
			if ( self::check() ) {
				self::includes();

				add_action( 'init', array( __CLASS__, 'maybe_add_scheduled_action' ) );

				// Add "BusinessOnBot" menu to WordPress Administration Menu.
				add_action(
					'admin_menu',
					function (): void {
						add_menu_page(
							'Settings',
							'BusinessOnBot',
							'manage_options',
							'businessonbot',
							array(
								__CLASS__,
								'businessonbot_page_callback',
							),
							'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbDpzcGFjZT0icHJlc2VydmUiIHdpZHRoPSI2NTEiIGhlaWdodD0iNTgxIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgaW1hZ2UtcmVuZGVyaW5nPSJvcHRpbWl6ZVF1YWxpdHkiIHNoYXBlLXJlbmRlcmluZz0iZ2VvbWV0cmljUHJlY2lzaW9uIiB0ZXh0LXJlbmRlcmluZz0iZ2VvbWV0cmljUHJlY2lzaW9uIiB2aWV3Qm94PSIwIDAgNjUxIDU4MS4wNCI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0xNzguOTEgMjMyLjA4YzU2LjU3LS41MSAxMTMuNzkuMzMgMTcwLjUxLjMzIDU2LjYyLS4wMSAxMTMuMjgtLjI1IDE2OS44OS0uMDYgMTMuOTguMDQgMjguMDMtLjEyIDQxLjk5LjAxIDE1Ljk2LjE2IDIzLjY3IDEuNDcgMzIuODUgMTAuMzIgNS45NiA1Ljc0IDQuNjUgNi45NCA0LjU1IDE2Ljg2bC0uMSA2My45NmMuMDggNTYuNTItLjY4IDExMy40My4xMiAxNjkuODkuMjMgMTYuMDktNC4yNCAyMS41My0xMy41NiAyOC44OC05Ljc0IDcuNjktMjIuMjMgNS43OS0zNi44NyA1Ljc2LTI4LjMxLS4wNi01Ni42Mi0uMS04NC45NC0uMWwtMjExLjg5LjIyYy0xMi4zMy0uMDktMjEuNDctMy43Ny0yOC44LTEzLjkyLTUuNTQtNy42Ny0zLjA4LTI3LjQtMy4xLTM4LjgzLS4wNC0xNC4zMi0uMDItMjguNjUtLjAxLTQyLjk3LjA1LTU2LjIyLTEuMjctMTEzLjk4LS4wMi0xNjkuODlsLTM3LjM1LTIzLjU5Yy0zLjk0LTIuMjgtMy4yNy02Ljg3LTMuMjctNi44N3ptMjA2Ljg2LTEyNC44Ni0uMzUgNzMuOTYtMzg2LjI2LjA1YzEuMDkgMy4wMSA0LjgxIDQuOSA5LjQ5IDguMjcgNS45OSA0LjMgMjIuMjggMTIuNjUgMzIuMjEgMTkuODEgMTIuNzggOS4yMiAzNy42NyAyNC4wMSA1Mi40MiAzMy40OSA0LjAyIDIuNTggNS4zNyA0LjQgOS44MSA3LjEyIDguNzkgNS4zOSA1My40MyAzMi44OCA2MC43NyAzOS4ybDIuOSAyLjRjLjk2IDEzLjk5LjA0IDY4LjYyLjAyIDg2Ljk0LS4wMyAyOS4zMi4wNSA1OC42My4wMSA4Ny45NS0uMDQgMzAuNzYtMi41NCA1My4wNiAxMS43NSA3NC44OSAzMS45NCA0OC43OCA3OS4xOCAzOS4wMSAxMzUuOSAzOC45OCAyOS4zMi0uMDIgNTguNjMtLjAxIDg3Ljk1LS4wMSAxOC43Mi0uMDEgMTYwLjIxLjk4IDE3NC43Mi0uMzYgMzQuNC0zLjE4IDYwLjY1LTMwLjcyIDcwLjY2LTU5LjkyIDMuODYtMTEuMjUgMS44Ny0yOS41OCAxLjkxLTQyLjU5LjE4LTcxLjI5LTEuMDktMTQ3LjI4LjA1LTIxNy44Ni4yNy0xNy4xMy0uMjUtMjUuMDUtNy4wMi0zNy40Ni05LjcyLTE3LjgxLTI2LjI2LTMwLjU1LTQ5LjkyLTM4LjE2LTIyLjYxLTcuMjctMTQ5LjUzLjc0LTE3Mi45My0yLjkybC0uNDItNzMuMzdjNC40NC0zLjUgMTAuNDgtNS4yMiAxNS4yNS04LjY3IDE2LjEyLTExLjYzIDI0LjcxLTMwLjcgMjEuMzgtNTIuMTktMi43OS0xNy45OC01LjIyLTIwLjQ3LTE2LjI5LTMyLjQ5QzQzMi42MSA2LjQ5IDQxOS4wNi4zOSA0MDMuMzkuMDdjLTU3LjE4LTEuMTYtNzMuNjEgNzguNDUtMjUuNjkgMTAyLjMgMy43OCAxLjg4IDQuNTkgMi44MSA4LjA3IDQuODV6Ii8+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik01MTcuMjcgNDUxLjYzYzYuMzctLjM2IDcuNDgtNS4zMSA5LjQ3LTkuOTEgMy4zNC03Ljc0IDkuMzItMjEuMjkgNy4zNi0zMC4wMS0xLjI0LTUuNDktMi05LjEyLTguMzgtMTAuNDUtMTYuODYtMy41My01MS41MSA5LjYtNTQuMjQgMTkuMSA5LjA3IDEuNzkgMjUuMDItMi42NiAzMS45MiAxLjA4LS41NiAzLjYxLS4yMiAyLjU1LTQuNTYgNC40OWwtMTYuMzEgOC42NGEyMjIgMjIyIDAgMCAxLTIzLjcxIDEwLjM4Yy0xOC4zNCA2LjctMzUuODEgOS42MS01OC40MyA5LjM3LTMyLjE5LS4zNC01My42NC05LjcxLTc5Ljg5LTIxLjkxLTExLjEtNS4xNy0xNC4zNS0xMS40MS0yMi4wOS0xMi4xMSAxLjA3IDguMTEgMTEuMzMgMTYuMzMgMTMuMjEgMTguODcgMi41NiAzLjQ1IDEuMzkgMi4zIDQuODQgNS4yNyA0LjgzIDQuMTggNi40NyA1Ljg4IDExLjQ0IDkuNTUgNC4wNCAyLjk4IDguMjEgNS42OSAxMi44MSA4LjIxIDIxLjQ4IDExLjc2IDM2Ljg4IDE3LjAyIDY2LjY3IDE2Ljk3IDE1LjM5LS4wMyAyNC4zMy0uOTYgMzcuNjQtNS4wNiAxMi43NC0zLjkyIDE4LjMtNy4yMiAyOC44MS0xMy4yMiAxNy44NS0xMC4yIDI2Ljg4LTE5LjMxIDM3LjkyLTM1LjE1IDMuNTMtNS4wNSA2LjU0LTMuNSA2LjA3IDQuMzlsLS41NSAyMS41ek0zMzAuNjMgMjkxLjRjLTI0LjE5IDkuMzMtMjYuMTcgNDUuOTgtMTAuNDkgNjEuMzYgMjAuMzIgMTkuOTMgNDcuMjEtLjQxIDQzLjUzLTM0LjI3LTEuNjUtMTUuMTgtMTUuMzMtMzMuOTItMzMuMDQtMjcuMDl6bTEzNi4xNS4yN2MtMzUuMTMgMTEuNi0xOS41NSA4MS41IDE0LjM1IDY3LjYgMzIuODctMTMuNDcgMTkuODktNzguOTEtMTQuMzUtNjcuNnoiLz48L3N2Zz4=',
							900
						);
					}
				);
				add_action( 'admin_init', array( __CLASS__, 'businessonbot_settings_init' ) );

				// Actions to be done on cart update.
				add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'businessonbot_store_cart_timestamp' ), PHP_INT_MAX );
				add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'businessonbot_store_cart_timestamp' ), PHP_INT_MAX );
				add_action( 'woocommerce_cart_item_restored', array( __CLASS__, 'businessonbot_store_cart_timestamp' ), PHP_INT_MAX );
				add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'businessonbot_store_cart_timestamp' ), PHP_INT_MAX );
				add_action( 'woocommerce_calculate_totals', array( __CLASS__, 'businessonbot_store_cart_timestamp' ), PHP_INT_MAX );

				// Checkout links.
				add_filter( 'template_include', array( __CLASS__, 'businessonbot_checkout_links' ), 99 );

				/**
				 * It will add the js, ajax for capturing the guest cart.
				 * It will add an action for populating the guest data when user comes from the abandoned cart reminder emails.
				 */
				add_action( 'woocommerce_after_checkout_billing_form', 'businessonbot_user_side_js' );
				add_action(
					'init',
					function (): void {
						if ( ! is_user_logged_in() ) {
							add_action( 'wp_ajax_nopriv_businessonbot_save_guest_ab_cart', 'businessonbot_save_guest_ab_cart' );
						}
					}
				);
				add_action( 'wp_ajax_nopriv_businessonbot_gdpr_refused', array( 'BusinessOnBot_Common', 'businessonbot_gdpr_refused' ) );
				add_filter( 'woocommerce_checkout_fields', 'businessonbot_guest_checkout_fields' );

				add_action( 'woocommerce_coupon_error', array( 'BusinessOnBot_Common', 'businessonbot_capture_coupon_error' ), 15, 2 );
				add_action( 'woocommerce_applied_coupon', array( 'BusinessOnBot_Common', 'businessonbot_capture_applied_coupon' ), 15, 2 );

				add_action( 'woocommerce_before_cart_table', array( 'BusinessOnBot_Common', 'businessonbot_apply_direct_coupon_code' ) );
				// Add coupon when user views checkout page (would not be added otherwise, unless user views cart first).
				add_action( 'woocommerce_before_checkout_form', array( 'BusinessOnBot_Common', 'businessonbot_apply_direct_coupon_code' ) );

				// Enqueue scripts set from API.
				add_action( 'wp_head', array( __CLASS__, 'businessonbot_api_scripts' ) );

				// REST API Integration.
				add_action( 'rest_api_init', array( __CLASS__, 'businessonbot_rest_api' ) );

				// Load checkout from link.
				add_action( 'template_redirect', array( __CLASS__, 'load_checkout_from_link' ) );

				// Hook into WooCommerce product page visit.
				add_action( 'woocommerce_after_single_product', array( __CLASS__, 'track_product_visit_hook' ) );

				// Delete added temp fields after order is placed.
				add_filter( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'businessonbot_action_after_delivery_session' ) );
				add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'businessonbot_update_cart_details' ), 10, 3 );
				add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'businessonbot_order_placed' ) );
				add_filter( 'woocommerce_payment_complete_order_status', array( __CLASS__, 'businessonbot_order_complete_action' ), 10, 2 );

				// Setup webhooks.
				add_filter( 'woocommerce_webhook_topics', array( __CLASS__, 'businessonbot_add_new_webhook_topics' ) );
				add_filter( 'woocommerce_webhook_topic_hooks', array( __CLASS__, 'businessonbot_add_topic_hooks' ) );
				add_filter( 'woocommerce_valid_webhook_resources', array( __CLASS__, 'businessonbot_add_cart_resources' ) );
				add_filter( 'woocommerce_valid_webhook_events', array( __CLASS__, 'businessonbot_add_cart_events' ) );
				add_filter( 'woocommerce_webhook_payload', array( __CLASS__, 'businessonbot_generate_payload' ), 10, 4 );
				add_filter( 'woocommerce_webhook_deliver_async', array( __CLASS__, 'businessonbot_deliver_sync' ), 10, 3 );

				// Process Webhook actions.
				add_action( 'businessonbot_cart_recovered', array( __CLASS__, 'businessonbot_cart_recovered' ), 10, 2 );
				add_action( 'businessonbot_webhook_after_abancart', array( __CLASS__, 'businessonbot_cart_abancart_reached' ) );
				add_action( 'businessonbot_abandoned_products', array( __CLASS__, 'businessonbot_abandoned_cart_products' ) );

				// Populates cart on login.
				add_action( 'wp_login', array( __CLASS__, 'businessonbot_load_woo_session' ) );
				add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'businessonbot_redirect_login' ) );
			}
		}

		/**
		 * Includes required files.
		 *
		 * @return void
		 */
		public static function includes(): void {
			require_once 'includes/class-businessonbot-aes.php';
			require_once 'includes/class-businessonbot-aes-ctr.php';
			require_once 'includes/class-businessonbot-common.php';
			require_once 'includes/businessonbot-functions.php';
		}

		/**
		 * Checks if the plugin should load.
		 *
		 * @return bool
		 */
		public static function check(): bool {
			$passed = true;

			/* translators: Plugin name. */
			$inactive_text = '<strong>' . sprintf( __( '%s is inactive.', 'businessonbot' ), __( 'BusinessOnBot', 'businessonbot' ) ) . '</strong>';

			if ( version_compare( phpversion(), BUSINESSONBOT_MIN_PHP_VER, '<' ) ) {
				/* translators: %1$s inactive plugin text, %2$s minimum PHP version */
				self::$errors[] = sprintf( __( '%1$s The plugin requires PHP version %2$s or newer.', 'businessonbot' ), $inactive_text, BUSINESSONBOT_MIN_PHP_VER );
				$passed         = false;
			} elseif ( ! self::is_woocommerce_version_ok() ) {
				/* translators: %1$s inactive plugin text, %2$s minimum WooCommerce version */
				self::$errors[] = sprintf( __( '%1$s The plugin requires WooCommerce version %2$s or newer.', 'businessonbot' ), $inactive_text, BUSINESSONBOT_MIN_WC_VER );
				$passed         = false;
			} elseif ( ! self::is_wp_version_ok() ) {
				/* translators: %1$s inactive plugin text, %2$s minimum WordPress version */
				self::$errors[] = sprintf( __( '%1$s The plugin requires WordPress version %2$s or newer.', 'businessonbot' ), $inactive_text, BUSINESSONBOT_MIN_WP_VER );
				$passed         = false;
			}

			return $passed;
		}

		/**
		 * Checks if the installed WooCommerce version is ok.
		 *
		 * @return bool
		 */
		public static function is_woocommerce_version_ok() {
			if ( ! function_exists( 'WC' ) ) {
				return false;
			}
			if ( ! BUSINESSONBOT_MIN_WC_VER ) {
				return true;
			}
			return version_compare( WC()->version, BUSINESSONBOT_MIN_WC_VER, '>=' );
		}

		/**
		 * Checks if the installed WordPress version is ok.
		 *
		 * @return bool
		 */
		public static function is_wp_version_ok() {
			global $wp_version;
			if ( ! BUSINESSONBOT_MIN_WP_VER ) {
				return true;
			}
			return version_compare( $wp_version, BUSINESSONBOT_MIN_WP_VER, '>=' );
		}

		/**
		 * Loads plugin text-domain.
		 */
		public static function load_textdomain() {
			load_plugin_textdomain( 'businessonbot', false, 'businessonbot/languages' );
		}

		/**
		 * Check if database version is same as the plugin version.
		 *
		 * @return void
		 */
		public static function businessonbot_update_db_check() {
			$legacy_versions = array(
				'19.10',
				'19.11',
				'19.12',
				'19.13',
				'20.00',
				'20.01',
				'20.02',
				'21.00',
				'21.01',
				'21.02',
				'21.03',
				'22.01',
				'22.02',
				'22.03',
				'22.04',
				'22.05',
				'22.06',
				'22.07',
				'22.08',
				'22.09',
				'22.10',
				'1.0.0',
				'1.0.1',
			);
			$old_version     = get_option( 'businessonbot_db_version' );

			if ( false !== $old_version && in_array( $old_version, $legacy_versions, true ) ) {
				self::remove_legacy_data();
			}

			$current_version = get_option( 'businessonbot_db_version' );

			if ( BUSINESSONBOT_VERSION !== $current_version ) {
				self::businessonbot_install_defaults();
				self::check_auto_increment();
			}
		}

		/**
		 * Enqueues scripts set from API
		 *
		 * @return void
		 */
		public static function businessonbot_api_scripts(): void {
			$businessonbot_api_script = get_option( 'businessonbot_api_script' );

			if ( false !== $businessonbot_api_script ) {
				$scripts = maybe_unserialize( $businessonbot_api_script );

				foreach ( $scripts as $key => $value ) {
					wp_enqueue_script( $key, $value, array( 'jquery' ), time(), true );
				}
			}
		}

		/**
		 * Loads cart items from the given checkout hash
		 *
		 * @return void
		 * @throws Exception Plugins can throw an exception to prevent adding to cart.
		 */
		public static function load_checkout_from_link(): void {
			// Get the current URL.
			$checkout_hash = isset( $_GET['businessonbot_checkout'] ) ? sanitize_text_field( wp_unslash( $_GET['businessonbot_checkout'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Check if the URL contains the checkout_hash parameter.
			if ( preg_match( '/^[a-zA-Z0-9]{12}$/', $checkout_hash ) ) {
				$cart = WC()->cart;

				if ( ! $cart ) {
					wp_die( 'Unable to retrieve cart.', 'Error' );
				}

				WC()->cart->empty_cart();

				// Get the checkout data from the database table.
				global $wpdb;
				$checkout_data = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT checkout_value FROM {$wpdb->prefix}businessonbot_checkout_links WHERE checkout_hash = %s",
						sanitize_text_field( $checkout_hash )
					)
				);

				// Decode the checkout data from JSON format.
				$checkout_data = maybe_unserialize( $checkout_data );

				$cart_items = $checkout_data['cart'] ?? array();

				// Build the cart with the checkout data.
				foreach ( $cart_items as $cart_item ) {
					// Get the product ID and quantity.
					$product_id = $cart_item['id'] ? absint( $cart_item['id'] ) : 0;
					$is_product = wc_get_product( $product_id );

					$quantity = $cart_item['quantity'] ?? 0;

					// If there are no product ID or quantity, skip this cart item.
					if ( ! $is_product || ! $quantity ) {
						continue;
					}

					// Add the item to the cart.
					if ( $is_product->is_type( 'simple' ) ) {
						if ( $is_product->is_purchasable() ) {
							WC()->cart->add_to_cart( $product_id, $quantity );
						}
					} elseif ( $is_product->is_type( 'variation' ) ) {
						if ( $is_product->is_purchasable() ) {
							$variation_id = $is_product->get_id();
							$parent_id    = $is_product->get_parent_id();
							WC()->cart->add_to_cart( $parent_id, $quantity, $variation_id );
						}
					} elseif ( $is_product->is_type( 'variable' ) ) {
						if ( $is_product->is_purchasable() ) {
							$variations = array();
							if ( isset( $cart_item['variant'] ) && is_array( $cart_item['variant'] ) ) {
								foreach ( $cart_item['variant'] as $variant ) {
									$attribute = $variant['attribute'];
									$value     = $variant['value'];

									$variations[ $attribute ] = $value;
								}
							}

							$data_store   = $is_product->get_data_store();
							$variation_id = $data_store->find_matching_product_variation( $is_product, $variations );
							WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
						}
					} elseif ( $is_product->is_type( 'grouped' ) ) {
						// Get associated products (child products) of the grouped product.
						$associated_products = $is_product->get_children();

						// Add each associated product to the cart.
						foreach ( $associated_products as $associated_product_id ) {
							$associated_product = wc_get_product( $associated_product_id );
							if ( $associated_product && $associated_product->is_in_stock() ) {
								WC()->cart->add_to_cart( $associated_product_id, $quantity );
							}
						}
					}
				}

				if ( ! WC()->session->has_session() ) {
					WC()->session->set_customer_session_cookie( true );
				}

				if ( isset( $checkout_data['coupon_code'] ) ) {
					WC()->session->set( 'coupon_code', $checkout_data['coupon_code'] );
					WC()->cart->apply_coupon( $checkout_data['coupon_code'] );
					wc_print_notices();
				}

				// Set the billing address.
				if ( isset( $checkout_data['billingAddress'] ) ) {
					$billing_address = $checkout_data['billingAddress'];
					WC()->customer->set_billing_first_name( $billing_address['firstName'] );
					WC()->customer->set_billing_last_name( $billing_address['lastName'] );
					WC()->customer->set_billing_phone( $billing_address['phone'] );
					WC()->customer->set_billing_address( $billing_address['address1'] );
					WC()->customer->set_billing_address_2( $billing_address['address2'] );
					WC()->customer->set_billing_city( $billing_address['city'] );

					// Get the input values from wherever you're getting them (e.g. form input, API request, etc.).
					$country_value = $billing_address['country'];
					$state_value   = $billing_address['province'];

					// Get a list of valid countries and states.
					$countries = WC()->countries->get_countries();
					$states    = WC()->countries->get_states();

					// Check if the country input value is a valid country code.
					if ( in_array( $country_value, array_keys( $countries ), true ) ) {
						$country_code = $country_value;
					} elseif ( in_array( $country_value, array_values( $countries ), true ) ) {
						$country_code = array_search( $country_value, $countries, true );
					} else {
						$country_code = '';
					}

					// Check if the state input value is a valid state code.
					if ( in_array( $state_value, array_keys( $states[ $country_code ] ), true ) ) {
						$state_code = $state_value;
					} elseif ( in_array( $state_value, array_values( $states[ $country_code ] ), true ) ) {
						$state_code = array_search( $state_value, $states[ $country_code ], true );
					} else {
						$state_code = '';
					}

					// Set the customer's country and state.
					WC()->customer->set_billing_country( $country_code );
					WC()->customer->set_billing_state( $state_code );
					WC()->customer->set_billing_postcode( $billing_address['zip'] );
				}

				if ( isset( $checkout_data['isShippingAddressSameAsBilling'] ) && false === $checkout_data['isShippingAddressSameAsBilling'] ) {
					// Set a different shipping address.
					if ( isset( $checkout_data['shippingAddress'] ) ) {
						$shipping_address = $checkout_data['shippingAddress'];
						WC()->customer->set_shipping_first_name( $shipping_address['firstName'] );
						WC()->customer->set_shipping_last_name( $shipping_address['lastName'] );
						WC()->customer->set_shipping_phone( $shipping_address['phone'] );
						WC()->customer->set_shipping_address( $shipping_address['address1'] );
						WC()->customer->set_shipping_address_2( $shipping_address['address2'] );
						WC()->customer->set_shipping_city( $shipping_address['city'] );
						WC()->customer->set_shipping_state( $shipping_address['province'] );
						WC()->customer->set_shipping_country( $shipping_address['country'] );
						WC()->customer->set_shipping_postcode( $shipping_address['zip'] );
					}
				}

				if ( isset( $checkout_data['custom_fields'] ) ) {
					BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_checkout_hash', $checkout_hash );
					$custom_fields = maybe_serialize( $checkout_data['custom_fields'] );
					BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_custom_field', $custom_fields );
				}

				wp_safe_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
			}
		}

		/**
		 * Registers API for BusinessOnBot
		 *
		 * @return void
		 */
		public static function businessonbot_rest_api(): void {
			register_rest_route(
				'wc-bob/v1',
				'/add-script',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_add_script_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
				)
			);
			register_rest_route(
				'wc-bob/v1',
				'/del-script',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_del_script_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
				)
			);
			register_rest_route(
				'wc-bob/v1',
				'/list-script',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_list_script_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
				)
			);
			register_rest_route(
				'wc-bob/v1',
				'/manage-config',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_manage_config_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
				)
			);
			register_rest_route(
				'wc-bob/v1',
				'/create-checkout',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_create_checkout_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
				)
			);
			register_rest_route(
				'wc-bob/v1',
				'/list-checkout/(?P<checkouthash>[a-zA-Z0-9-]+)',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'wc_businessonbot_list_checkout_callback' ),
					'permission_callback' => array( __CLASS__, 'businessonbot_api_permission' ),
					'args'                => array(
						'checkouthash' => array(
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_text_field',
							'type'              => 'string',
						),
					),
				)
			);
		}

		/**
		 * Creates a checkout with the given product details and customer info.
		 *
		 * @param WP_REST_Request $request The request body.
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_create_checkout_callback( WP_REST_Request $request ) {
			// Get the JSON payload from the request body.
			$payload = json_decode( $request->get_body(), true );

			// Return an error if the request body is empty.
			if ( null === $payload || false === $payload ) {
				return new WP_Error( 400, 'Body should be a valid JSON.', array() );
			}

			$payload_serialized = maybe_serialize( $payload );

			$checkout_hash        = '';
			$checkout_hash_length = 12;

			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			for ( $i = 0; $i < $checkout_hash_length; $i++ ) {
				$checkout_hash .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
			}

			global $wpdb;

			// Insert the checkout data into the custom database table.
			$table_name = $wpdb->prefix . 'businessonbot_checkout_links';
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table_name,
				array(
					'checkout_hash'  => $checkout_hash,
					'checkout_value' => $payload_serialized,
				)
			);

			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}businessonbot_checkout_links WHERE checkout_hash = %s",
					$checkout_hash
				)
			);

			$result[0]->checkout_value = maybe_unserialize( $result[0]->checkout_value );
			$result[0]->checkout_link  = get_option( 'siteurl' ) . '/?businessonbot_checkout=' . $checkout_hash;

			if ( $wpdb->last_error ) {
				return new WP_Error( 400, $wpdb->last_error, array() );
			} else {
				$status = array(
					'code'    => 200,
					'message' => 'Success',
					'data'    => $result[0],
				);
			}

			// Return a success response.
			return rest_ensure_response( $status );
		}

		/**
		 * Lists existing checkouts if checkout ID is given
		 *
		 * @param WP_REST_Request $request The Request Body.
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_list_checkout_callback( WP_REST_Request $request ) {
			global $wpdb;

			// Get the JSON payload from the request body.
			$checkouthash = $request->get_param( 'checkouthash' );

			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT checkout_value FROM {$wpdb->prefix}businessonbot_checkout_links WHERE checkout_hash = %s",
					$checkouthash
				)
			);

			if ( $wpdb->last_error ) {
				return new WP_Error( 400, $wpdb->last_error, array() );
			} else {
				$status = array(
					'code'    => 200,
					'message' => 'Success',
					'data'    => maybe_unserialize( $result[0]->checkout_value ),
				);
			}

			// Return a success response.
			return rest_ensure_response( $status );
		}

		/**
		 * Checks if user has required permission
		 *
		 * @return boolean
		 */
		public static function businessonbot_api_permission(): bool {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Adds or overwrites scripts to database
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_add_script_callback( WP_REST_Request $request ) {
			// Get the new script data from the request body.
			$new_data = json_decode( $request->get_body(), true );

			// Return an error if the request body is empty.
			if ( null === $new_data || false === $new_data ) {
				return new WP_Error( 400, 'Body should not be empty', array() );
			}

			// Get the old setting already saved in the database. If not, set it to empty array.
			$old_setting = get_option( 'businessonbot_api_script', array() );

			// Delete old setting if it exists.
			if ( array() === $old_setting || false !== $old_setting ) {
				delete_option( 'businessonbot_api_script' );
			}

			// Update old setting with the new data.
			foreach ( $new_data as $key => $value ) {
				$old_setting[ $key ] = $value;
			}

			// Set updated old setting as new setting.
			$new_setting = $old_setting;

			$saved = update_option( 'businessonbot_api_script', $new_setting );
			if ( ! $saved ) {
				return new WP_Error( 400, 'Failed to update data', array() );
			} else {
				$status = array(
					'code'    => 200,
					'message' => 'Success',
					'data'    => $new_setting,
				);
			}

			return rest_ensure_response( $status );
		}

		/**
		 * Saves scripts to database
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_del_script_callback( WP_REST_Request $request ) {
			// Get the new script data from the request body.
			$data_to_delete = json_decode( $request->get_body(), true );

			// Return an error if the request body is empty.
			if ( null === $data_to_delete || false === $data_to_delete ) {
				return new WP_Error( 400, 'Body should not be empty', array() );
			}

			// Get the old setting already saved in the database. If not, set it to empty array.
			$old_setting = get_option( 'businessonbot_api_script' );

			// Delete old setting if it exists.
			if ( false !== $old_setting ) {
				delete_option( 'businessonbot_api_script' );
			}

			foreach ( $data_to_delete as $key => $value ) {
				if ( array_key_exists( $key, $old_setting ) ) {
					unset( $old_setting[ $key ] );
				}
			}

			$saved = update_option( 'businessonbot_api_script', $old_setting );
			if ( ! $saved ) {
				return new WP_Error( 400, 'Failed to update data', array() );
			} else {
				$status = array(
					'code'    => 200,
					'message' => 'Success',
					'data'    => $old_setting,
				);
			}

			return rest_ensure_response( $status );
		}

		/**
		 * List all the scripts stored in the database
		 *
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_list_script_callback() {
			$stored_script = get_option( 'businessonbot_api_script' );

			if ( ! $stored_script ) {
				$status = array(
					'code'    => 400,
					'message' => 'No scripts stored',
					'data'    => array(),
				);
			} else {
				$return_data = maybe_unserialize( $stored_script );
				$status      = array(
					'code'    => 200,
					'message' => '',
					'data'    => $return_data,
				);
			}

			return rest_ensure_response( $status );
		}

		/**
		 * Manage config options via API
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_Error|WP_REST_Response
		 */
		public static function wc_businessonbot_manage_config_callback( WP_REST_Request $request ) {
			$data           = json_decode( $request->get_body(), true );
			$return_option  = array();
			$return_code    = 200;
			$return_message = '';

			if ( null === $data || false === $data ) {
				return new WP_Error( 400, 'Body should not be empty', array() );
			}

			foreach ( $data as $key => $value ) {
				if ( ! str_starts_with( $key, 'businessonbot_' ) ) {
					$return_code    = 400;
					$return_message = 'Only keys starting with "businessonbot_" are allowed.';
					continue;
				}
				$return_option = array();
				$check_option  = get_option( $key );
				if ( $check_option ) {
					delete_option( $key );
				}

				$update_option = update_option( $key, $value );
				if ( ! $update_option ) {
					$return_code = 400;
				}
				$return_option[ $key ] = $value;
			}

			$status = array(
				'code'    => $return_code,
				'message' => $return_message,
				'data'    => $return_option,
			);

			return rest_ensure_response( $status );
		}

		/**
		 * Function to track and update product visits
		 *
		 * @param int $product_id The ID of the product.
		 *
		 * @return void
		 */
		private static function track_product_visits( int $product_id ): void {
			$user_id       = is_user_logged_in() ? get_current_user_id() : 0;
			$mobile_number = null;

			if ( 0 === $user_id && isset( $_COOKIE['businessonbot_mobile_number'] ) ) {
				$mobile_number = sanitize_text_field( wp_unslash( $_COOKIE['businessonbot_mobile_number'] ) );
			} elseif ( $user_id > 0 ) {
				$mobile_number = isset( $_COOKIE['businessonbot_mobile_number'] )
					? sanitize_text_field( wp_unslash( $_COOKIE['businessonbot_mobile_number'] ) )
					: get_user_meta( $user_id, 'billing_phone', true );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'businessonbot_visited_products_log';

			// Check if user has previously visited the same product.
			$existing_visit = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}businessonbot_visited_products_log WHERE user_id = %d AND product_id = %d",
					$user_id,
					$product_id
				)
			);

			// If exists, update last_active time.
			if ( $existing_visit ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table_name,
					array(
						'last_visited'  => current_time( 'mysql' ),
						'product_id'    => $product_id,
						'mobile_number' => $mobile_number,
					),
					array( 'user_id' => $existing_visit->user_id )
				);
			} else {
				// If not, insert new visit record.
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table_name,
					array(
						'user_id'       => $user_id,
						'product_id'    => $product_id,
						'last_visited'  => current_time( 'mysql' ),
						'mobile_number' => $mobile_number,
					)
				);
			}
		}

		/**
		 * The hook triggered when a product page is visited.
		 *
		 * @return void
		 */
		public static function track_product_visit_hook(): void {
			$timeout = get_option( 'businessonbot_abandoned_product_timeout', 0 );

			if ( '0' !== $timeout ) {
				global $product;
				self::track_product_visits( $product->get_id() );
			}
		}

		/**
		 * Declare it as HPOS Compatible.
		 *
		 * @return void
		 */
		public static function declare_feature_compatibility(): void {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', BUSINESSONBOT_FILE );
			}
		}

		/**
		 * It will create the plugin tables & the options required for plugin.
		 *
		 * @hook register_activation_hook
		 * @globals mixed $wpdb
		 */
		public static function businessonbot_install_defaults(): void {
			global $wpdb;

			$db_collate = $wpdb->get_charset_collate();

			$abandoned_cart_table = $wpdb->prefix . 'businessonbot_abandoned_cart';
			self::install(
				"CREATE TABLE $abandoned_cart_table (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					user_id bigint(20) NOT NULL,
					abandoned_cart_info longtext NOT NULL,
					abandoned_cart_time int(11) NULL,
					cart_ignored tinyint(1) NOT NULL,
					recovered_cart tinyint(1) NOT NULL,
					user_type enum('REGISTERED','GUEST') NOT NULL,
					session_id varchar(50) NOT NULL,
					checkout_link varchar(500) NOT NULL,
					PRIMARY KEY  (id),
					KEY user_id (user_id),
					KEY cart_ignored (cart_ignored),
					KEY recovered_cart (recovered_cart),
					KEY session_id (session_id)
				) $db_collate;"
			);

			$guest_abandoned_cart_table = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';
			self::install(
				"CREATE TABLE $guest_abandoned_cart_table (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					billing_first_name varchar(255) NOT NULL,
					billing_last_name varchar(255) NOT NULL,
					billing_company_name varchar(255) NOT NULL,
					billing_address_1 varchar(255) NOT NULL,
					billing_address_2 varchar(255) NOT NULL,
					billing_city varchar(100) NOT NULL,
					billing_country char(2) NOT NULL,
					billing_zipcode varchar(20) NOT NULL,
					email_id varchar(100) NOT NULL,
					phone varchar(20) NOT NULL,
					ship_to_billing tinyint(1) NOT NULL,
					order_notes longtext NOT NULL,
					shipping_first_name varchar(255) NOT NULL,
					shipping_last_name varchar(255) NOT NULL,
					shipping_company_name varchar(255) NOT NULL,
					shipping_address_1 varchar(255) NOT NULL,
					shipping_address_2 varchar(255) NOT NULL,
					shipping_city varchar(100) NOT NULL,
					shipping_country char(2) NOT NULL,
					shipping_zipcode varchar(20) NOT NULL,
					shipping_charges double DEFAULT 0 NOT NULL,
					PRIMARY KEY  (id),
					KEY email_id (email_id)
				) $db_collate;"
			);

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"ALTER TABLE {$wpdb->prefix}businessonbot_guest_abandoned_cart AUTO_INCREMENT = %d;", // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					63000000
				)
			);

			$checkout_links_table = $wpdb->prefix . 'businessonbot_checkout_links';
			self::install(
				"CREATE TABLE $checkout_links_table (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					checkout_hash varchar(12) NOT NULL,
					checkout_value longtext NOT NULL,
					PRIMARY KEY  (id),
					KEY checkout_hash (checkout_hash)
				) $db_collate;"
			);

			$visited_products_log_table = $wpdb->prefix . 'businessonbot_visited_products_log';
			self::install(
				"CREATE TABLE $visited_products_log_table (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					user_id int(11) NOT NULL,
					product_id int(11) NOT NULL,
					last_visited datetime NOT NULL,
					mobile_number varchar(20) NULL,
					PRIMARY KEY  (id),
					KEY user_id (user_id),
					KEY product_id (product_id)
				) $db_collate;"
			);

			if ( false === get_option( 'businessonbot_abandoned_cart_timeout' ) ) {
				self::businessonbot_set_setting( 'businessonbot_abandoned_cart_timeout', 10 );
			}

			if ( false === get_option( 'businessonbot_abandoned_product_timeout' ) ) {
				self::businessonbot_set_setting( 'businessonbot_abandoned_product_timeout', 0 );
			}

			/**
			 * Don't add new security key if it already exists.
			 */
			if ( false === get_option( 'businessonbot_security_key' ) ) {
				self::businessonbot_set_setting( 'businessonbot_security_key', self::businessonbot_get_crypt_key() );
			}

			if ( false === get_option( 'businessonbot_db_version' ) ) {
				self::businessonbot_set_setting( 'businessonbot_db_version', BUSINESSONBOT_VERSION );
			} elseif ( BUSINESSONBOT_VERSION !== get_option( 'businessonbot_db_version' ) ) {
				delete_option( 'businessonbot_db_version' );
				self::businessonbot_set_setting( 'businessonbot_db_version', BUSINESSONBOT_VERSION );
			}

			self::check_auto_increment();

			do_action( 'businessonbot_install_defaults' );
		}

		/**
		 * Return a Random key which can be used for encryption.
		 *
		 * @return string $crypt_key - Key to be used for encryption.
		 */
		public static function businessonbot_get_crypt_key(): string {
			$characters    = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$random_string = '';
			$n             = 16;
			for ( $i = 0; $i < $n; $i++ ) {
				$index          = wp_rand( 0, strlen( $characters ) - 1 );
				$random_string .= $characters[ $index ];
			}

			return $random_string;
		}

		/**
		 * Drops legacy tables & settings from database.
		 *
		 * @return void
		 */
		public static function remove_legacy_data(): void {
			global $wpdb;

			$legacy_tables = array(
				'bob_abandoned_cart',
				'bob_abandoned_cart_history_lite',
				'bob_guest_abandoned_cart',
				'bob_guest_abandoned_cart_history_lite',
				'bob_email_templates_lite',
				'bob_sent_history_lite',
				'bob_checkout_links',
				'bob_visited_products_log',
			);

			foreach ( $legacy_tables as $table ) {
				$table_name = $wpdb->prefix . $table;
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						'DROP TABLE IF EXISTS %s', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
						$table_name
					)
				);
			}

			$legacy_options = array(
				'bob_db_version',
				'bob_delete_abandoned_order_days',
				'bob_plugin_alter_table_queries',
				'bob_plugin_cart_abandoned_time',
				'bob_plugin_delete_redundant_queries',
				'bob_plugin_prod_abandoned_time',
				'bob_security_key',
			);

			foreach ( $legacy_options as $option ) {
				delete_option( $option );
			}
		}

		/**
		 * Sets the requested option from database. Handles multisite.
		 *
		 * @param string $option_name Option name.
		 * @param mixed  $value Default value if setting is not found.
		 *
		 * @return bool
		 */
		public static function businessonbot_set_setting( string $option_name, $value ) {
			$already_exists = get_option( $option_name );
			$return_value   = false;

			if ( false === $already_exists ) {
				// Option doesn't exists.
				$return_value = add_option( $option_name, $value );
			} elseif ( $already_exists !== $value ) {
				// New value is different.
				$return_value = update_option( $option_name, $value );
			}

			return $return_value;
		}

		/**
		 * Things to do when the plugin is deactivated.
		 */
		public static function businessonbot_deactivate(): void {
			if ( ! as_has_scheduled_action( 'businessonbot_abandoned_products' ) ) {
				as_unschedule_action( 'businessonbot_abandoned_products' );
			}

			if ( ! as_has_scheduled_action( 'businessonbot_webhook_after_abancart' ) ) {
				as_unschedule_all_actions( 'businessonbot_webhook_after_abancart' );
			}

			do_action( 'businessonbot_deactivate' );
		}

		/**
		 * Checks if AUTO_INCREMENT value for guest table is correct.
		 *
		 * @return void
		 */
		public static function check_auto_increment() {
			global $wpdb;
			$last_id = $wpdb->get_var( "SELECT max(id) FROM {$wpdb->prefix}businessonbot_guest_abandoned_cart" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( $last_id < 62999999 ) {
				// Reset the auto_increment value.
				$wpdb->get_var( "ALTER TABLE {$wpdb->prefix}businessonbot_guest_abandoned_cart AUTO_INCREMENT = 63000000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				// Insert sample data.
				self::insert_sample_data();
			}
		}

		/**
		 * Inserts sample data and then deletes it.
		 *
		 * @return int
		 */
		public static function insert_sample_data(): int {
			global $wpdb;
			$guest_abandoned_cart_table = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$guest_abandoned_cart_table,
				array(
					'billing_first_name'   => 'test',
					'billing_last_name'    => 'test',
					'billing_company_name' => 'BusinessOnBot',
					'billing_address_1'    => 'address_1',
					'billing_address_2'    => 'address_2',
					'billing_city'         => 'Bengaluru',
					'billing_country'      => 'IN',
					'billing_zipcode'      => '560001',
					'email_id'             => 'test@example.com',
					'phone'                => '+919876543210',
					'ship_to_billing'      => 1,
				)
			);

			return $wpdb->insert_id;
		}

		/**
		 * Deletes sample data.
		 *
		 * @return void
		 */
		public static function delete_sample_data() {
			global $wpdb;
			$guest_abandoned_cart_table = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';

			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$guest_abandoned_cart_table,
				array(
					'email_id' => 'test@example.com',
				)
			);
		}

		/**
		 * Create Tables using dbDelta function to offload heavy lifting.
		 *
		 * @param string $sql SQL query to execute.
		 */
		public static function install( string $sql ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Callback for BusinessOnBot Settings Page.
		 *
		 * @hook admin_menu
		 */
		public static function businessonbot_page_callback(): void {
			?>
			<div class="wrap">
				<h2>BusinessOnBot Settings</h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'businessonbot_settings' ); ?>
					<?php do_settings_sections( 'businessonbot' ); ?>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Add fields to settings page.
		 *
		 * @return void
		 */
		public static function businessonbot_settings_init(): void {
			add_settings_section(
				'businessonbot_settings',
				'Settings',
				function () {
					esc_html__( 'Configure timeout (in minutes) for cart and product to be considered abandoned.', 'businessonbot' );
				},
				'businessonbot'
			);

			add_settings_field(
				'businessonbot_abandoned_cart_timeout',
				'Abandoned cart timeout',
				function () {
					$businessonbot_cart_abandoned_time = get_option( 'businessonbot_abandoned_cart_timeout' );
					echo '<input type="number" name="businessonbot_abandoned_cart_timeout" value="' . esc_attr( $businessonbot_cart_abandoned_time ) . '" class="regular-text">';
				},
				'businessonbot',
				'businessonbot_settings',
				array( 'label_for' => 'businessonbot_abandoned_cart_timeout' )
			);

			add_settings_field(
				'businessonbot_abandoned_product_timeout',
				'Abandoned product timeout',
				function () {
					$businessonbot_prod_abandoned_time = get_option( 'businessonbot_abandoned_product_timeout' );
					echo '<input type="number" name="businessonbot_abandoned_product_timeout" value="' . esc_attr( $businessonbot_prod_abandoned_time ) . '" class="regular-text">';
				},
				'businessonbot',
				'businessonbot_settings',
				array( 'label_for' => 'businessonbot_abandoned_product_timeout' )
			);

			register_setting(
				'businessonbot_settings',
				'businessonbot_abandoned_cart_timeout',
				array(
					'sanitize_callback' => array( __CLASS__, 'businessonbot_settings_sanitize' ),
					'default'           => 5,
				)
			);

			register_setting(
				'businessonbot_settings',
				'businessonbot_abandoned_product_timeout',
				array(
					'sanitize_callback' => array( __CLASS__, 'businessonbot_settings_sanitize' ),
					'default'           => 0,
				)
			);
		}

		/**
		 * Sanitize the timeout settings to make sure it's always an integer.
		 *
		 * @param mixed $setting The setting send on POST.
		 * @return int
		 */
		public static function businessonbot_settings_sanitize( $setting ): int {
			if ( empty( $setting ) ) {
				$setting = 0;
			}

			return absint( $setting );
		}

		/**
		 * Capture the cart and insert the information of the cart into DataBase.
		 *
		 * @return void
		 */
		public static function businessonbot_store_cart_timestamp(): void {
			if ( get_transient( 'businessonbot_abandoned_id' ) !== false ) {
				BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', get_transient( 'businessonbot_abandoned_id' ) );
				delete_transient( 'businessonbot_abandoned_id' );
			}

			global $wpdb;
			$local_time                      = current_datetime();
			$current_time                    = $local_time->getTimestamp() + $local_time->getOffset();
			$cut_off_time                    = get_option( 'businessonbot_abandoned_cart_timeout' );
			$track_guest_cart_from_cart_page = get_option( 'businessonbot_plugin_track_guest_cart_from_cart_page' );
			$cart_ignored                    = 0;
			$recovered_cart                  = 0;

			$track_guest_user_cart_from_cart = '';
			if ( isset( $track_guest_cart_from_cart_page ) ) {
				$track_guest_user_cart_from_cart = $track_guest_cart_from_cart_page;
			}

			if ( isset( $cut_off_time ) ) {
				$cart_cut_off_time = intval( $cut_off_time ) * 60;
			} else {
				$cart_cut_off_time = 60 * 60;
			}
			$compare_time = $current_time - $cart_cut_off_time;

			if ( is_user_logged_in() ) {
				$user_id      = get_current_user_id();
				$gdpr_consent = get_user_meta( $user_id, 'businessonbot_gdpr_tracking_choice', true );

				if ( '' === $gdpr_consent ) {
					$gdpr_consent = true;
				}

				if ( $gdpr_consent ) {
					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %s AND recovered_cart = %s",
							$user_id,
							$cart_ignored,
							$recovered_cart
						)
					);
					if ( 0 === count( $results ) ) {
						$cart_info_meta         = array();
						$cart_info_meta['cart'] = WC()->cart->get_cart();
						$cart_info_meta         = wp_json_encode( $cart_info_meta );

						if ( '' !== $cart_info_meta && '{"cart":[]}' !== $cart_info_meta && '""' !== $cart_info_meta ) {
							$cart_info = $cart_info_meta;
							$user_type = 'REGISTERED';
							$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$wpdb->prepare(
									"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored, user_type ) VALUES ( %d, %s, %d, %s, %s )",
									$user_id,
									$cart_info,
									$current_time,
									$cart_ignored,
									$user_type
								)
							);
							$abandoned_cart_id = $wpdb->insert_id;
							BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_add_checkout_link( $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_run_webhook_after_abancart( $abandoned_cart_id );
						}
					} elseif ( isset( $results[0]->abandoned_cart_time ) && $compare_time > $results[0]->abandoned_cart_time ) {
						$updated_cart_info         = array();
						$updated_cart_info['cart'] = WC()->cart->get_cart();
						$updated_cart_info         = wp_json_encode( $updated_cart_info );

						if ( ! self::businessonbot_compare_carts( $user_id, $results[0]->abandoned_cart_info ) ) {
							$updated_cart_ignored = 1;
							$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$wpdb->prepare(
									"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET cart_ignored = %s WHERE user_id = %d",
									$updated_cart_ignored,
									$user_id
								)
							);
							$user_type = 'REGISTERED';
							$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$wpdb->prepare(
									"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart (user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored, user_type) VALUES (%d, %s, %d, %s, %s)",
									$user_id,
									$updated_cart_info,
									$current_time,
									$cart_ignored,
									$user_type
								)
							);
							update_user_meta( $user_id, '_businessonbot_modified_cart', md5( 'yes' ) );

							$abandoned_cart_id = $wpdb->insert_id;
							BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_add_checkout_link( $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_run_webhook_after_abancart( $abandoned_cart_id );
						} else {
							update_user_meta( $user_id, '_businessonbot_modified_cart', md5( 'no' ) );
						}
					} else {
						$updated_cart_info         = array();
						$updated_cart_info['cart'] = WC()->cart->get_cart();
						$updated_cart_info         = wp_json_encode( $updated_cart_info );

						$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->prepare(
								"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET abandoned_cart_info = %s, abandoned_cart_time = %d WHERE user_id = %d AND cart_ignored = %s",
								$updated_cart_info,
								$current_time,
								$user_id,
								$cart_ignored
							)
						);

						$get_abandoned_record = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->prepare(
								"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %s",
								$user_id,
								0
							)
						);

						if ( count( $get_abandoned_record ) > 0 ) {
							$abandoned_cart_id = $get_abandoned_record[0]->id;
							BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_add_checkout_link( $abandoned_cart_id );
							BusinessOnBot_Common::businessonbot_run_webhook_after_abancart( $abandoned_cart_id );
						}
					}
				}
			} else {
				// start here guest user.
				$user_id = BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_user_id' );

				// GDPR consent.
				$gdpr_consent  = true;
				$show_gdpr_msg = BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_cart_tracking_refused' );
				if ( 'yes' === $show_gdpr_msg ) {
					$gdpr_consent = false;
				}

				if ( $gdpr_consent ) {
					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d AND cart_ignored = %s AND recovered_cart = %s AND user_id != %s",
							$user_id,
							0,
							0,
							0
						)
					);
					$cart    = array();

					$get_cookie = WC()->session->get_customer_id();

					$cart['cart'] = WC()->cart->get_cart();

					$updated_cart_info = wp_json_encode( $cart );

					if ( count( $results ) > 0 && '{"cart":[]}' !== $updated_cart_info ) {
						if ( $compare_time > $results[0]->abandoned_cart_time ) {
							if ( ! self::businessonbot_compare_only_guest_carts( $updated_cart_info, $results[0]->abandoned_cart_info ) ) {
								$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$wpdb->prepare(
										"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET cart_ignored = %s WHERE user_id = %s",
										1,
										$user_id
									)
								);
								$user_type = 'GUEST';
								$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$wpdb->prepare(
										"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart (user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored, user_type, session_id, checkout_link) VALUES (%d, %s, %d, %s, %s, %s, %s)",
										$user_id,
										$updated_cart_info,
										$current_time,
										$cart_ignored,
										$user_type,
										$get_cookie,
										$results[0]->checkout_link
									)
								);
								update_user_meta( $user_id, '_businessonbot_modified_cart', md5( 'yes' ) );
							} else {
								update_user_meta( $user_id, '_businessonbot_modified_cart', md5( 'no' ) );
							}
						} else {
							$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$wpdb->prepare(
									"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET abandoned_cart_info = %s, abandoned_cart_time = %s WHERE user_id = %d AND cart_ignored = %s",
									$updated_cart_info,
									$current_time,
									$user_id,
									0
								)
							);
						}
					} elseif ( 'on' === $track_guest_user_cart_from_cart && ! empty( $get_cookie ) ) {
						$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->prepare(
								"SELECT * FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE session_id LIKE %s AND cart_ignored = %s AND recovered_cart = %s",
								$get_cookie,
								0,
								0
							)
						);
						if ( 0 === count( $results ) ) {
							$cart_info       = $updated_cart_info;
							$blank_cart_info = '[]';
							if ( $blank_cart_info !== $cart_info && '{"cart":[]}' !== $cart_info ) {
								$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$wpdb->prepare(
										"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart ( abandoned_cart_info , abandoned_cart_time , cart_ignored , recovered_cart, user_type, session_id  ) VALUES ( %s, %s, %s, %s, %s, %s )",
										$cart_info,
										$current_time,
										0,
										0,
										'GUEST',
										$get_cookie
									)
								);
								$abandoned_cart_id = $wpdb->insert_id;
							}
						} elseif ( $compare_time > $results[0]->abandoned_cart_time ) {
							$blank_cart_info = '[]';
							if ( $blank_cart_info !== $updated_cart_info && '{"cart":[]}' !== $updated_cart_info ) {
								if ( ! self::businessonbot_compare_only_guest_carts( $updated_cart_info, $results[0]->abandoned_cart_info ) ) {
									$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
										$wpdb->prepare(
											"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET cart_ignored = %s WHERE session_id = %s",
											1,
											$get_cookie
										)
									);
									$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
										$wpdb->prepare(
											"INSERT INTO {$wpdb->prefix}businessonbot_abandoned_cart ( abandoned_cart_info, abandoned_cart_time, cart_ignored, recovered_cart, user_type, session_id ) VALUES ( %s, %s, %s, %s, %s, %s )",
											$updated_cart_info,
											$current_time,
											0,
											0,
											'GUEST',
											$get_cookie
										)
									);
									$abandoned_cart_id = $wpdb->insert_id;
								}
							}
						} else {
							$blank_cart_info = '[]';
							if ( $blank_cart_info !== $updated_cart_info && '{"cart":[]}' !== $updated_cart_info ) {
								if ( ! self::businessonbot_compare_only_guest_carts( $updated_cart_info, $results[0]->abandoned_cart_info ) ) {
									$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
										$wpdb->prepare(
											"UPDATE {$wpdb->prefix}businessonbot_abandoned_cart SET abandoned_cart_info = %s, abandoned_cart_time  = %s WHERE session_id = %s AND cart_ignored = %s",
											$updated_cart_info,
											$current_time,
											$get_cookie,
											0
										)
									);
								}
							}
						}
						if ( isset( $abandoned_cart_id ) ) {
							// add the abandoned id in the session.
							BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', $abandoned_cart_id );
						}
					}
				}
			}
		}

		/**
		 * It will populate the logged-in users cart.
		 *
		 * @return void
		 */
		public static function businessonbot_load_woo_session(): void {
			$email_sent_id = get_transient( 'businessonbot_sent_id' );
			$user_id       = get_transient( 'businessonbot_user_id' );
			if ( $email_sent_id && $user_id && (int) $email_sent_id > 0 && (int) $user_id > 0 ) {
				BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_user_id', $user_id );
			}
		}

		/**
		 * Redirect to cart page after login.
		 *
		 * @param string $url User's current URL.
		 *
		 * @return string
		 */
		public static function businessonbot_redirect_login( $url ) {
			if ( get_transient( 'businessonbot_sent_id' ) ) {
				return wc_get_cart_url();
			}
			return $url;
		}

		/**
		 * It will populate the logged-in and guest users cart.
		 *
		 * @param string $template - Template name.
		 *
		 * @return string $template
		 */
		public static function businessonbot_checkout_links( string $template ): string {
			$is_businessonbot_link = isset( $_GET['businessonbot_action'] ) ? sanitize_text_field( wp_unslash( $_GET['businessonbot_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			if ( 'checkout_link' === $is_businessonbot_link ) {
				global $wpdb;
				$validate_server_string  = isset( $_GET ['validate'] ) ? sanitize_text_field( wp_unslash( $_GET ['validate'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$validate_server_string  = str_replace( ' ', '+', $validate_server_string );
				$validate_encoded_string = $validate_server_string;
				$crypt_key               = get_option( 'businessonbot_security_key' );
				$link_decode             = BusinessOnBot_Aes_Ctr::decrypt( $validate_encoded_string, $crypt_key );
				$sent_email_id_pos       = strpos( $link_decode, '&' );
				$email_sent_id           = substr( $link_decode, 0, $sent_email_id_pos );
				$abandoned_id_pos        = strpos( $link_decode, '&' );
				$abandoned_id            = substr( $link_decode, 0, $abandoned_id_pos );

				$url_pos = strpos( $link_decode, '=' );
				++$url_pos;
				$url = substr( $link_decode, $url_pos );

				BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_recovered_cart', true );
				BusinessOnBot_Common::businessonbot_set_cart_session( 'abandoned_cart_id', $abandoned_id );
				set_transient( 'businessonbot_abandoned_id', $abandoned_id, 5 );

				$get_user_results = array();
				if ( $abandoned_id > 0 ) {
					$get_user_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT user_id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
							$abandoned_id
						)
					);
				}
				$user_id = ( isset( $get_user_results ) && count( $get_user_results ) > 0 ) ? (int) $get_user_results[0]->user_id : 0;

				if ( 0 === $user_id ) {
					wp_die( 'Oops! The checkout links either does not exist or has already expired.' );
				}
				$user = wp_set_current_user( $user_id );
				if ( $user_id >= '63000000' ) {
					$results_guest = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT * from {$wpdb->prefix}businessonbot_guest_abandoned_cart WHERE id = %d",
							$user_id
						)
					);

					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT recovered_cart FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE user_id = %d",
							$user_id
						)
					);

					if ( $results_guest && '0' === $results[0]->recovered_cart ) {
						$guest_billing_first_name    = isset( $results_guest[0]->billing_first_name ) ? $results_guest[0]->billing_first_name : '';
						$guest_billing_last_name     = isset( $results_guest[0]->billing_last_name ) ? $results_guest[0]->billing_last_name : '';
						$guest_billing_company_name  = isset( $results_guest[0]->billing_company_name ) ? $results_guest[0]->billing_company_name : '';
						$guest_billing_address_1     = isset( $results_guest[0]->billing_address_1 ) ? $results_guest[0]->billing_address_1 : '';
						$guest_billing_address_2     = isset( $results_guest[0]->billing_address_2 ) ? $results_guest[0]->billing_address_2 : '';
						$guest_billing_city          = isset( $results_guest[0]->billing_city ) ? $results_guest[0]->billing_city : '';
						$guest_billing_country       = isset( $results_guest[0]->billing_country ) ? $results_guest[0]->billing_country : '';
						$guest_billing_zipcode       = isset( $results_guest[0]->billing_zipcode ) ? $results_guest[0]->billing_zipcode : '';
						$guest_email_id              = isset( $results_guest[0]->email_id ) ? $results_guest[0]->email_id : '';
						$guest_phone                 = isset( $results_guest[0]->phone ) ? $results_guest[0]->phone : '';
						$guest_ship_to_billing       = isset( $results_guest[0]->ship_to_billing ) ? $results_guest[0]->ship_to_billing : '';
						$guest_order_notes           = isset( $results_guest[0]->order_notes ) ? $results_guest[0]->order_notes : '';
						$guest_shipping_first_name   = isset( $results_guest[0]->shipping_first_name ) ? $results_guest[0]->shipping_first_name : '';
						$guest_shipping_last_name    = isset( $results_guest[0]->shipping_last_name ) ? $results_guest[0]->shipping_last_name : '';
						$guest_shipping_address_1    = isset( $results_guest[0]->shipping_company_name ) ? $results_guest[0]->shipping_company_name : '';
						$guest_shipping_address_2    = isset( $results_guest[0]->shipping_address_2 ) ? $results_guest[0]->shipping_address_2 : '';
						$guest_shipping_city         = isset( $results_guest[0]->shipping_city ) ? $results_guest[0]->shipping_city : '';
						$guest_shipping_country      = isset( $results_guest[0]->shipping_country ) ? $results_guest[0]->shipping_country : '';
						$guest_shipping_zipcode      = isset( $results_guest[0]->shipping_zipcode ) ? $results_guest[0]->shipping_zipcode : '';
						$guest_shipping_company_name = isset( $results_guest[0]->shipping_company_name ) ? $results_guest[0]->shipping_company_name : '';

						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_first_name', $guest_billing_first_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_last_name', $guest_billing_last_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_company_name', $guest_billing_company_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_address_1', $guest_billing_address_1 );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_address_2', $guest_billing_address_2 );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_city', $guest_billing_city );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_country', $guest_billing_country );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_zipcode', $guest_billing_zipcode );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_email', $guest_email_id );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_phone', $guest_phone );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_ship_to_billing', $guest_ship_to_billing );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_order_notes', $guest_order_notes );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_first_name', $guest_shipping_first_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_last_name', $guest_shipping_last_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_company_name', $guest_shipping_company_name );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_address_1', $guest_shipping_address_1 );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_address_2', $guest_shipping_address_2 );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_city', $guest_shipping_city );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_country', $guest_shipping_country );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'guest_shipping_zipcode', $guest_shipping_zipcode );
						BusinessOnBot_Common::businessonbot_set_cart_session( 'businessonbot_user_id', $user_id );
					} else {
						wp_safe_redirect( get_permalink( wc_get_page_id( 'shop' ) ) );
					}
				}

				if ( $user_id < '63000000' ) {
					$user_login = $user->data->user_login;
					wp_set_auth_cookie( $user_id, false, '', 'loggedout' );
					wc_load_persistent_cart( $user_login, $user );
					do_action( 'wp_login', $user_login, $user );
					set_transient( 'businessonbot_user_id', $user_id, 1800 );
					set_transient( 'businessonbot_sent_id', $email_sent_id, 1800 );
					$url = get_permalink( wc_get_page_id( 'myaccount' ) );
				} else {
					self::businessonbot_load_guest_persistent_cart();
				}

				if ( $email_sent_id > 0 && is_numeric( $email_sent_id ) ) {
					wp_safe_redirect( $url );
					exit;
				}
			}
			return $template;
		}

		/**
		 * When customer clicks on the abandoned cart link and that cart is for the guest users it will load the guest
		 * user's cart detail.
		 *
		 * @globals mixed $woocommerce
		 */
		public static function businessonbot_load_guest_persistent_cart(): void {
			if ( BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_user_id' ) !== '' ) {
				global $woocommerce;
				$saved_cart = json_decode( get_user_meta( BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_user_id' ), '_woocommerce_persistent_cart', true ), true );
				$c          = array();
				$value_new  = array();

				$cart_contents_total  = 0;
				$cart_contents_weight = 0;
				$cart_contents_count  = 0;
				$cart_contents_tax    = 0;
				$total                = 0;
				$subtotal             = 0;
				$subtotal_ex_tax      = 0;
				$tax_total            = 0;
				$saved_cart_data      = array();

				if ( ! is_null( $saved_cart ) && count( $saved_cart ) > 0 ) {
					foreach ( $saved_cart as $key => $value ) {
						if ( is_array( $value ) && count( $value ) > 0 ) {
							foreach ( $value as $a => $b ) {
								$c['product_id']        = $b['product_id'];
								$c['variation_id']      = $b['variation_id'];
								$c['variation']         = $b['variation'];
								$c['quantity']          = $b['quantity'];
								$product_id             = $b['product_id'];
								$c['data']              = wc_get_product( $product_id );
								$c['line_total']        = $b['line_total'];
								$c['line_tax']          = $cart_contents_tax;
								$c['line_subtotal']     = $b['line_subtotal'];
								$c['line_subtotal_tax'] = $cart_contents_tax;
								$value_new[ $a ]        = $c;
								$cart_contents_total    = $b['line_subtotal'] + $cart_contents_total;
								$cart_contents_count    = $cart_contents_count + $b['quantity'];
								$total                  = $total + $b['line_total'];
								$subtotal               = $subtotal + $b['line_subtotal'];
								$subtotal_ex_tax        = $subtotal_ex_tax + $b['line_subtotal'];
							}
							$saved_cart_data[ $key ] = $value_new;
						}
					}
				}

				if ( $saved_cart ) {
					if ( empty( $woocommerce->session->cart ) || ! is_array( $woocommerce->session->cart ) || 0 === count( $woocommerce->session->cart ) ) {
						$woocommerce->session->cart                 = $saved_cart['cart'];
						$woocommerce->session->cart_contents_total  = $cart_contents_total;
						$woocommerce->session->cart_contents_weight = $cart_contents_weight;
						$woocommerce->session->cart_contents_count  = $cart_contents_count;
						$woocommerce->session->cart_contents_tax    = $cart_contents_tax;
						$woocommerce->session->total                = $total;
						$woocommerce->session->subtotal             = $subtotal;
						$woocommerce->session->subtotal_ex_tax      = $subtotal_ex_tax;
						$woocommerce->session->tax_total            = $tax_total;
						$woocommerce->session->shipping_taxes       = array();
						$woocommerce->session->taxes                = array();
						$woocommerce->session->ac_customer          = array();
						$woocommerce->cart->cart_contents           = $saved_cart_data['cart'];
						$woocommerce->cart->cart_contents_total     = $cart_contents_total;
						$woocommerce->cart->cart_contents_weight    = $cart_contents_weight;
						$woocommerce->cart->cart_contents_count     = $cart_contents_count;
						$woocommerce->cart->cart_contents_tax       = $cart_contents_tax;
						$woocommerce->cart->total                   = $total;
						$woocommerce->cart->subtotal                = $subtotal;
						$woocommerce->cart->subtotal_ex_tax         = $subtotal_ex_tax;
						$woocommerce->cart->tax_total               = $tax_total;
					}
				}
			}
		}

		/**
		 * It will compare only guest users cart while capturing the cart.
		 *
		 * @param string $new_cart New abandoned cart details.
		 * @param string $last_abandoned_cart Old abandoned cart details.
		 *
		 * @return boolean
		 */
		public static function businessonbot_compare_only_guest_carts( string $new_cart, string $last_abandoned_cart ): bool {
			$current_woo_cart   = json_decode( stripslashes( $new_cart ), true );
			$abandoned_cart_arr = json_decode( $last_abandoned_cart, true );
			if ( isset( $current_woo_cart['cart'] ) && isset( $abandoned_cart_arr['cart'] ) ) {
				if ( count( $current_woo_cart['cart'] ) <= count( $abandoned_cart_arr['cart'] ) ) {
					$temp_variable      = $current_woo_cart;
					$current_woo_cart   = $abandoned_cart_arr;
					$abandoned_cart_arr = $temp_variable;
				}
				if ( is_array( $current_woo_cart ) || is_object( $current_woo_cart ) ) {
					foreach ( $current_woo_cart as $key => $value ) {
						foreach ( $value as $item_key => $item_value ) {
							$current_cart_product_id   = $item_value['product_id'];
							$current_cart_variation_id = $item_value['variation_id'];
							$current_cart_quantity     = $item_value['quantity'];

							$abandoned_cart_product_id   = $abandoned_cart_arr[ $key ][ $item_key ]['product_id'] ?? '';
							$abandoned_cart_variation_id = $abandoned_cart_arr[ $key ][ $item_key ]['variation_id'] ?? '';
							$abandoned_cart_quantity     = $abandoned_cart_arr[ $key ][ $item_key ]['quantity'] ?? '';

							if ( ( $current_cart_product_id !== $abandoned_cart_product_id ) || ( $current_cart_variation_id !== $abandoned_cart_variation_id ) || ( $current_cart_quantity !== $abandoned_cart_quantity ) ) {
								return false;
							}
						}
					}
				}
			}

			return true;
		}

		/**
		 * It will compare only logged-in users cart while capturing the cart.
		 *
		 * @param int    $user_id User id.
		 * @param string $last_abandoned_cart Old abandoned cart details.
		 *
		 * @return boolean
		 */
		public static function businessonbot_compare_carts( int $user_id, string $last_abandoned_cart ): bool {
			$businessonbot_woocommerce_persistent_cart = '_woocommerce_persistent_cart_' . get_current_blog_id();
			$current_woo_cart                          = get_user_meta( $user_id, $businessonbot_woocommerce_persistent_cart, true );
			$abandoned_cart_arr                        = json_decode( $last_abandoned_cart, true );
			if ( isset( $current_woo_cart['cart'] ) && isset( $abandoned_cart_arr['cart'] ) ) {
				if ( count( $current_woo_cart['cart'] ) <= count( $abandoned_cart_arr['cart'] ) ) {
					$temp_variable      = $current_woo_cart;
					$current_woo_cart   = $abandoned_cart_arr;
					$abandoned_cart_arr = $temp_variable;
				}
				if ( is_array( $current_woo_cart ) && is_array( $abandoned_cart_arr ) ) {
					foreach ( $current_woo_cart as $key => $value ) {
						foreach ( $value as $item_key => $item_value ) {
							$current_cart_product_id   = $item_value['product_id'];
							$current_cart_variation_id = $item_value['variation_id'];
							$current_cart_quantity     = $item_value['quantity'];

							$abandoned_cart_product_id   = $abandoned_cart_arr[ $key ][ $item_key ]['product_id'] ?? '';
							$abandoned_cart_variation_id = $abandoned_cart_arr[ $key ][ $item_key ]['variation_id'] ?? '';
							$abandoned_cart_quantity     = $abandoned_cart_arr[ $key ][ $item_key ]['quantity'] ?? '';

							if (
								( $current_cart_product_id !== $abandoned_cart_product_id ) ||
								( $current_cart_variation_id !== $abandoned_cart_variation_id ) ||
								( $current_cart_quantity !== $abandoned_cart_quantity )
							) {
								return false;
							}
						}
					}
				}
			}
			return true;
		}

		/**
		 * When user places the order and reach the order received page, then it will check if it is abandoned cart and subsequently
		 * recovered or not.
		 *
		 * @param array | object $order Order details.
		 *
		 * @return void
		 */
		public static function businessonbot_action_after_delivery_session( $order ): void {

			$order_id = $order->get_id();

			$businessonbot_get_order_status = $order->get_status();

			$get_abandoned_id_of_order  = $order->get_meta( 'businessonbot_recover_order_placed', true );
			$get_sent_email_id_of_order = $order->get_meta( 'businessonbot_recover_order_placed_sent_id', true );
			$recovered                  = BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_recovered_cart' );
			if ( ( isset( $get_sent_email_id_of_order ) && '' !== $get_sent_email_id_of_order ) || $recovered ) {

				// When Placed order button is clicked, we create post meta for that order, If that meta is found then update our plugin table for recovered cart.
				self::businessonbot_updated_recovered_cart( $get_abandoned_id_of_order, $order_id, $order );
			} elseif ( '' !== $get_abandoned_id_of_order && isset( $get_abandoned_id_of_order ) ) {

				// If order status is not pending or failed then, we will delete the abandoned cart record. Post meta will be created only if the cut off time has been reached.
				self::businessonbot_delete_abanadoned_data_on_order_status( $order_id, $get_abandoned_id_of_order, $businessonbot_get_order_status );
			}

			if ( '' !== BusinessOnBot_Common::businessonbot_get_cart_session( 'email_sent_id' ) ) {
				BusinessOnBot_Common::businessonbot_unset_cart_session( 'email_sent_id' );
			}
		}

		/**
		 * If customer had placed the order after cut off time and reached the order received page then,
		 * it will also delete the abandoned cart if the order status is not pending or failed.
		 *
		 * @param int | string $order_id Order id.
		 * @param int | string $get_abandoned_id_of_order Abandoned cart id.
		 * @param string       $businessonbot_get_order_status Order status.
		 *
		 * @return void
		 */
		public static function businessonbot_delete_abanadoned_data_on_order_status( $order_id, $get_abandoned_id_of_order, $businessonbot_get_order_status ): void {
			global $wpdb;

			$businessonbot_history_table_name = $wpdb->prefix . 'businessonbot_abandoned_cart';
			$businessonbot_guest_table_name   = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';

			if ( 'pending' !== $businessonbot_get_order_status || 'failed' !== $businessonbot_get_order_status ) {
				if ( '' !== $get_abandoned_id_of_order ) {
					$user_id_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT user_id FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
							$get_abandoned_id_of_order
						)
					);

					if ( count( $user_id_results ) > 0 ) {
						$businessonbot_user_id = $user_id_results[0]->user_id;

						if ( $businessonbot_user_id >= 63000000 ) {
							$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$businessonbot_guest_table_name,
								array( 'id' => $businessonbot_user_id )
							);
						}

						$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$businessonbot_history_table_name,
							array( 'id' => $get_abandoned_id_of_order )
						);
						$order = wc_get_order( $order_id );
						$order->delete_meta_data( 'businessonbot_recover_order_placed' );
						$order->save();
					}
				}
			}
		}

		/**
		 * Updates the Abandoned Cart History table as well as the
		 * Email Sent History table to indicate the order has been
		 * recovered
		 *
		 * @param integer      $cart_id - ID of the Abandoned Cart.
		 * @param integer      $order_id - Recovered Order ID.
		 * @param object|array $order - Order Details.
		 *
		 * @return void
		 */
		public static function businessonbot_updated_recovered_cart( int $cart_id, int $order_id, $order ): void {
			global $wpdb;

			$businessonbot_history_table_name = $wpdb->prefix . 'businessonbot_abandoned_cart';

			// Check & make sure that the recovered cart details are not already updated.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$get_status = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT recovered_cart FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
					$cart_id
				)
			);

			$recovered_status = $get_status[0] ?? '';

			if ( 0 === $recovered_status ) {

				// Update the cart history table.
				$update_details = array(
					'recovered_cart' => $order_id,
					'cart_ignored'   => '1',
				);

				$current_user_id = get_current_user_id();

				if ( (int) BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_user_id' ) !== $current_user_id && 0 !== $current_user_id ) {
					$update_details['user_id'] = $current_user_id;
				}

				if ( '' !== $order->get_meta( 'businessonbot_abandoned_timestamp' ) ) {
					$update_details['abandoned_cart_time'] = $order->get_meta( 'businessonbot_abandoned_timestamp' );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$businessonbot_history_table_name,
					$update_details,
					array(
						'id' => $cart_id,
					)
				);

				// Add Order Note.
				$order->add_order_note( __( 'This order was abandoned & subsequently recovered.', 'businessonbot' ) );
				$order->delete_meta_data( 'businessonbot_abandoned_cart_id' );
				$order->delete_meta_data( 'businessonbot_recover_order_placed' );
				$order->delete_meta_data( 'businessonbot_recover_order_placed_sent_id' );
				$order->delete_meta_data( 'businessonbot_recovered_email_sent' );
				$order->save();
				do_action( 'businessonbot_cart_recovered', $cart_id, $order_id );
			}
		}

		/**
		 * Send email to admin when cart is recovered only via PayPal.
		 *
		 * @param int|string $order_id Order id.
		 * @param string     $wc_old_status Old status.
		 * @param string     $wc_new_status New status.
		 *
		 * @return void
		 */
		public static function businessonbot_update_cart_details( $order_id, $wc_old_status, $wc_new_status ): void {
			if ( 'pending' !== $wc_new_status && 'failed' !== $wc_new_status && 'cancelled' !== $wc_new_status && 'trash' !== $wc_new_status ) {

				global $wpdb;

				$businessonbot_history_table_name = $wpdb->prefix . 'businessonbot_abandoned_cart';
				$businessonbot_guest_table_name   = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';
				$order                            = wc_get_order( $order_id );

				if ( $order_id > 0 ) {
					$get_abandoned_id_of_order = $order->get_meta( 'businessonbot_recover_order_placed' );

					if ( ! ( $get_abandoned_id_of_order > 0 ) ) {
						$businessonbot_abandoned_id = $order->get_meta( 'businessonbot_abandoned_cart_id' );
						// check if it's a guest cart.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$get_cart_data = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT user_id, user_type FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
								$businessonbot_abandoned_id
							)
						);

						if ( is_array( $get_cart_data ) && count( $get_cart_data ) > 0 ) {
							$user_type = $get_cart_data[0]->user_type;
							$user_id   = $get_cart_data[0]->user_id;

							if ( 'GUEST' === $user_type && $user_id >= 63000000 ) {
								$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$businessonbot_guest_table_name,
									array( 'id' => $user_id )
								);
							}
						}
						$wpdb->delete( $businessonbot_history_table_name, array( 'id' => $businessonbot_abandoned_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					}
				}
			} elseif ( 'pending' === $wc_old_status && 'cancelled' === $wc_new_status ) {
				global $wpdb;

				$businessonbot_history_table_name = $wpdb->prefix . 'businessonbot_abandoned_cart';
				$order                            = wc_get_order( $order_id );
				$businessonbot_abandoned_id       = $order->get_meta( 'businessonbot_abandoned_cart_id' );

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$businessonbot_history_table_name,
					array( 'cart_ignored' => '1' ),
					array( 'id' => $businessonbot_abandoned_id )
				);
			}
		}

		/**
		 * It will check the WooCommerce order status. If the order status is pending or failed the we will keep that cart record
		 * as an abandoned cart.
		 * It will be executed after order placed.
		 *
		 * @param string     $woo_order_status Order Status.
		 * @param int|string $order_id Order Id.
		 *
		 * @return string $order_status
		 */
		public static function businessonbot_order_complete_action( $woo_order_status, $order_id ): string {
			global $wpdb;

			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );

				$get_abandoned_id_of_order  = $order->get_meta( 'businessonbot_recover_order_placed' );
				$get_sent_email_id_of_order = $order->get_meta( 'businessonbot_recover_order_placed_sent_id' );

				// Order Status passed in the function is either 'processing' or 'complete' and may or may not reflect the actual order status.
				// Hence, always use the status fetched from the order object.
				$order_status = $order->get_status();

				$businessonbot_ac_table_name       = $wpdb->prefix . 'businessonbot_abandoned_cart';
				$businessonbot_guest_ac_table_name = $wpdb->prefix . 'businessonbot_guest_abandoned_cart';

				if ( 'pending' !== $order_status && 'failed' !== $order_status && 'cancelled' !== $order_status && 'trash' !== $order_status ) {
					global $wpdb;

					if ( isset( $get_abandoned_id_of_order ) && '' !== $get_abandoned_id_of_order ) {

						$ac_user_id_result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$wpdb->prepare(
								"SELECT user_id, abandoned_cart_time FROM {$wpdb->prefix}businessonbot_abandoned_cart WHERE id = %d",
								$get_abandoned_id_of_order
							)
						);

						if ( count( $ac_user_id_result ) > 0 ) {
							$businessonbot_user_id = $ac_user_id_result[0]->user_id;

							if ( $businessonbot_user_id >= 63000000 ) {
								$order->add_meta_data( 'businessonbot_abandoned_timestamp', $ac_user_id_result[0]->abandoned_cart_time );

								$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$businessonbot_guest_ac_table_name,
									array( 'id' => $businessonbot_user_id )
								);
							}

							$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
								$businessonbot_ac_table_name,
								array( 'id' => $get_abandoned_id_of_order )
							);
							$order->delete_meta_data( 'businessonbot_recover_order_placed' );
							$order->save();
						}
					}
				}
				// in admin, return if status is not processing or completed without updating further.
				if ( is_admin() && ! empty( $woo_order_status ) ) {
					if ( ! in_array( $woo_order_status, array( 'wc-processing', 'wc-completed' ), true ) ) {
						return $woo_order_status;
					}
				}

				if ( in_array( $woo_order_status, array( 'pending', 'failed', 'cancelled', 'trash' ), true ) ) {
					if ( isset( $get_sent_email_id_of_order ) && '' !== $get_sent_email_id_of_order ) {
						self::businessonbot_updated_recovered_cart( $get_abandoned_id_of_order, $order_id, $order );
					}
				}
			}

			return $woo_order_status;
		}

		/**
		 * When customer clicks on the "Place Order" button on the checkout page, it will identify if we need to keep that cart or
		 * delete it.
		 *
		 * @param int | string $order_id Order id.
		 *
		 * @return void
		 */
		public static function businessonbot_order_placed( $order_id ): void {
			$abandoned_order_id = BusinessOnBot_Common::businessonbot_get_cart_session( 'abandoned_cart_id' );
			$recovered          = BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_recovered_cart' );
			$order              = wc_get_order( $order_id );

			$abandoned_order_id_to_save = $abandoned_order_id;
			if ( $recovered ) {
				$order->add_meta_data( 'businessonbot_recover_order_placed', $abandoned_order_id );
			}

			if ( ! empty( BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_checkout_hash' ) ) ) {
				$custom_fields = maybe_unserialize( BusinessOnBot_Common::businessonbot_get_cart_session( 'businessonbot_custom_field' ) );
				foreach ( $custom_fields as $key => $value ) {
					$order->add_meta_data( $key, $value );
				}
			}

			$order->add_meta_data( 'businessonbot_abandoned_cart_id', $abandoned_order_id_to_save );
			$order->save();
		}

		/**
		 * Add new list of events for webhooks in WC->Settings->Advanced->Webhooks.
		 *
		 * @param array $topics - Topic Hooks.
		 *
		 * @return array $topics - Topic Hooks including the ones from our plugin.
		 */
		public static function businessonbot_add_new_webhook_topics( array $topics ): array {
			$new_topics = array(
				// Cut off reached.
				'businessonbot_cart.abancart'  => 'Cart Abandoned - BusinessOnBot',
				// Order Recovered.
				'businessonbot_cart.recovered' => 'Abandoned Order Recovered - BusinessOnBot',
				// Product Abandoned.
				'businessonbot_cart.abanprod'  => 'Abandoned Product - BusinessOnBot',
			);

			return array_merge( $topics, $new_topics );
		}

		/**
		 * Trigger hooks for the plugin topics.
		 *
		 * @param array $topic_hooks Topic Hooks.
		 *
		 * @return array Topic Hooks including the ones from our plugin.
		 */
		public static function businessonbot_add_topic_hooks( array $topic_hooks ): array {
			$new_hooks = array(
				'businessonbot_cart.abancart'  => array( 'businessonbot_abandoned_cart_abancart' ),
				'businessonbot_cart.recovered' => array( 'businessonbot_abandoned_cart_recovered' ),
				'businessonbot_cart.abanprod'  => array( 'businessonbot_abandoned_cart_products' ),
			);

			return array_merge( $new_hooks, $topic_hooks );
		}

		/**
		 * Add webhook resources.
		 *
		 * @param array $topic_resources - Webhook Resources.
		 *
		 * @return array $topic_resources - Webhook Resources including the ones from our plugin.
		 */
		public static function businessonbot_add_cart_resources( array $topic_resources ): array {

			// Webhook resources for businessonbot.
			$new_resources = array(
				'businessonbot_cart',
			);

			return array_merge( $new_resources, $topic_resources );
		}

		/**
		 * Add webhook events.
		 *
		 * @param array $topic_events - List of events.
		 *
		 * @return array $topic_events - List of events including the ones from the plugin.
		 */
		public static function businessonbot_add_cart_events( array $topic_events ): array {

			// Webhook events for businessonbot.
			$new_events = array(
				'abancart',
				'recovered',
				'abanprod',
			);

			return array_merge( $new_events, $topic_events );
		}

		/**
		 * Deliver the webhooks in background or realtime.
		 *
		 * @param bool       $value Deliver the webhook in background or deliver in realtime.
		 * @param WC_Webhook $webhook WC Webhook object.
		 * @param mixed      $arg Arguments.
		 *
		 * @return bool  $value - Return false causes the webhook to be delivered immediately.
		 */
		public static function businessonbot_deliver_sync( bool $value, WC_Webhook $webhook, $arg ): bool {
			unset( $arg );

			$businessonbot_webhook_topics = array(
				'businessonbot_cart.abancart',
				'businessonbot_cart.recovered',
				'businessonbot_cart.abanprod',
			);

			if ( in_array( $webhook->get_topic(), $businessonbot_webhook_topics, true ) ) {
				return false;
			}

			return $value;
		}

		/**
		 * Generate data for webhook delivery.
		 *
		 * @param array  $payload Array of Data.
		 * @param string $resources Resource.
		 * @param mixed  $resource_data Resource Data.
		 * @param mixed  $id Webhook ID.
		 *
		 * @return array $payload - Array of Data.
		 */
		public static function businessonbot_generate_payload( array $payload, string $resources, $resource_data, $id ): array {
			unset( $resources );
			unset( $id );

			if ( ! is_array( $resource_data ) || ! isset( $resource_data ) ) {
				return $payload;
			}

			switch ( $resource_data['action'] ) {
				case 'abancart':
				case 'recovered':
				case 'abanprod':
					$payload = $resource_data['data'];
					break;
			}

			return $payload;
		}

		/**
		 * Triggers a webhook when a cart is marked as recovered.
		 *
		 * @param int $abandoned_id Abandoned Cart ID.
		 * @param int $order_id Order ID.
		 */
		public static function businessonbot_cart_recovered( int $abandoned_id, int $order_id ): void {

			if ( $abandoned_id > 0 && $order_id > 0 ) {

				// Setup the data.
				$send_data = self::businessonbot_reminders_webhook_data( $abandoned_id );

				if ( is_array( $send_data ) ) {

					$order = wc_get_order( $order_id );

					$send_data['order_id']  = $order_id;
					$send_data['total']     = $order->get_total();
					$send_data['tax_total'] = $order->get_total_tax();
					$data                   = array(
						'id'     => $abandoned_id,
						'data'   => $send_data,
						'action' => 'recovered',
					);

					do_action( 'businessonbot_abandoned_cart_recovered', $data );
				}
			}
		}

		/**
		 * Send abandoned products to webhook.
		 *
		 * @return void
		 */
		public static function businessonbot_abandoned_cart_products(): void {
			global $wpdb;
			$table_name = $wpdb->prefix . 'businessonbot_visited_products_log';

			$threshold_time         = current_time( 'mysql' );
			$product_abandoned_time = get_option( 'businessonbot_abandoned_product_timeout', 60 );

			$cut_off_time = date( // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'Y-m-d H:i:s',
				strtotime( $threshold_time ) - ( $product_abandoned_time * MINUTE_IN_SECONDS )
			);

			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}businessonbot_visited_products_log WHERE last_visited <= %s",
					$cut_off_time
				),
				ARRAY_A
			);

			if ( ! empty( $results ) ) {
				$send_data = array();
				foreach ( $results as $result ) {
					$user_id       = $result['user_id'];
					$product_id    = $result['product_id'];
					$last_visited  = $result['last_visited'];
					$mobile_number = $result['mobile_number'];

					if ( ! isset( $send_data[ $user_id ] ) ) {
						$send_data[ $user_id ] = array();
					}

					if ( ! isset( $send_data[ $user_id ]['mobile_number'] ) ) {
						$send_data[ $user_id ]['mobile_number'] = $mobile_number;
					}

					$send_data[ $user_id ]['products'][ $product_id ] = array(
						'last_visited' => $last_visited,
					);

					// After reporting, delete the abandoned record.
					$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$table_name,
						array(
							'user_id'      => $user_id,
							'product_id'   => $product_id,
							'last_visited' => $last_visited,
						)
					);
				}

				$data = array(
					'id'     => 0,
					'data'   => $send_data,
					'action' => 'abanprod',
				);

				do_action( 'businessonbot_abandoned_cart_products', $data );
			}
		}

		/**
		 * Triggers a webhook once cart abandoned is reached.
		 *
		 * @param int $abandoned_cart_id - Abandoned Cart ID.
		 */
		public static function businessonbot_cart_abancart_reached( int $abandoned_cart_id ): void {
			if ( $abandoned_cart_id > 0 ) {

				$cart_data = businessonbot_get_data_cart_history( $abandoned_cart_id );

				if ( $cart_data ) {
					$user_id   = $cart_data->user_id;
					$user_type = $cart_data->user_type;

					$billing_first_name = '';
					$billing_last_name  = '';
					$email_id           = '';
					$phone              = '';
					$billing_address_1  = '';
					$billing_address_2  = '';
					$billing_city       = '';
					$billing_state      = '';
					$billing_country    = '';
					$billing_zipcode    = '';

					$shipping_first_name = '';
					$shipping_last_name  = '';
					$shipping_address_1  = '';
					$shipping_address_2  = '';
					$shipping_city       = '';
					$shipping_state      = '';
					$shipping_zipcode    = '';
					$shipping_country    = '';

					if ( 'GUEST' === $user_type && $user_id >= 63000000 ) {
						$guest_data = businessonbot_get_data_guest_history( $user_id );

						if ( $guest_data ) {
							$billing_first_name  = $guest_data->billing_first_name;
							$billing_last_name   = $guest_data->billing_last_name;
							$email_id            = $guest_data->email_id;
							$phone               = $guest_data->phone;
							$billing_zipcode     = $guest_data->billing_zipcode;
							$billing_address_1   = $guest_data->billing_address_1;
							$billing_address_2   = $guest_data->billing_address_2;
							$billing_city        = $guest_data->billing_city;
							$billing_country     = $guest_data->billing_country;
							$shipping_first_name = $guest_data->shipping_first_name;
							$shipping_last_name  = $guest_data->shipping_last_name;
							$shipping_address_1  = $guest_data->shipping_address_1;
							$shipping_address_2  = $guest_data->shipping_address_2;
							$shipping_city       = $guest_data->shipping_city;
							$shipping_country    = $guest_data->shipping_country;
						}
					} elseif ( 'REGISTERED' === $user_type && $user_id > 0 ) {
						$billing_first_name = get_user_meta( $user_id, 'billing_first_name', true );
						$billing_last_name  = get_user_meta( $user_id, 'billing_last_name', true );
						$email_id           = get_user_meta( $user_id, 'billing_email', true );
						$phone              = get_user_meta( $user_id, 'billing_phone', true );
						$billing_address_1  = get_user_meta( $user_id, 'billing_address_1', true );
						$billing_address_2  = get_user_meta( $user_id, 'billing_address_2', true );
						$billing_city       = get_user_meta( $user_id, 'billing_city', true );
						$billing_state      = get_user_meta( $user_id, 'billing_state', true );
						$billing_country    = get_user_meta( $user_id, 'billing_country', true );
						$billing_zipcode    = get_user_meta( $user_id, 'billing_zipcode', true );

						$shipping_first_name = get_user_meta( $user_id, 'shipping_first_name', true );
						$shipping_last_name  = get_user_meta( $user_id, 'shipping_last_name', true );
						$shipping_address_1  = get_user_meta( $user_id, 'shipping_address_1', true );
						$shipping_address_2  = get_user_meta( $user_id, 'shipping_address_2', true );
						$shipping_city       = get_user_meta( $user_id, 'shipping_city', true );
						$shipping_state      = get_user_meta( $user_id, 'shipping_state', true );
						$shipping_zipcode    = get_user_meta( $user_id, 'shipping_postcode', true );
						$shipping_country    = get_user_meta( $user_id, 'shipping_country', true );
					}

					$checkout_link = $cart_data->checkout_link;

					$send_data = array(
						array(
							'abandoned_cart_id'   => $abandoned_cart_id,
							'abandoned_cart_info' => $cart_data->abandoned_cart_info,
							'abandoned_cart_time' => $cart_data->abandoned_cart_time,
							'customer_email_id'   => $email_id,
							'customer_phone'      => $phone,
							'billing_first_name'  => $billing_first_name,
							'billing_last_name'   => $billing_last_name,
							'billing_address_1'   => $billing_address_1,
							'billing_address_2'   => $billing_address_2,
							'billing_city'        => $billing_city,
							'billing_state'       => $billing_state,
							'billing_zipcode'     => $billing_zipcode,
							'billing_country'     => $billing_country,
							'shipping_first_name' => $shipping_first_name,
							'shipping_last_name'  => $shipping_last_name,
							'shipping_address_1'  => $shipping_address_1,
							'shipping_address_2'  => $shipping_address_2,
							'shipping_city'       => $shipping_city,
							'shipping_state'      => $shipping_state,
							'shipping_zipcode'    => $shipping_zipcode,
							'shipping_country'    => $shipping_country,
							'user_type'           => $user_type,
							'recovery_url'        => $checkout_link,
						),
					);

					$data = array(
						'id'     => $abandoned_cart_id,
						'data'   => $send_data,
						'action' => 'abancart',
					);

					do_action( 'businessonbot_abandoned_cart_abancart', $data );
				}
			}
		}

		/**
		 * Returns an array of cart data.
		 *
		 * @param int $abandoned_id - Abandoned Cart ID.
		 *
		 * @return bool|array $send_data - Array of Cart Data.
		 */
		public static function businessonbot_reminders_webhook_data( int $abandoned_id ) {

			$cart_history = businessonbot_get_data_cart_history( $abandoned_id );

			if ( $cart_history ) {
				$user_id   = $cart_history->user_id;
				$user_type = $cart_history->user_type;

				$billing_first_name = '';
				$billing_last_name  = '';
				$email_id           = '';
				$phone              = '';

				if ( $user_id >= 63000000 && 'GUEST' === $user_type ) {
					$guest_data = businessonbot_get_data_guest_history( $user_id );

					if ( $guest_data ) {
						$billing_first_name = $guest_data->billing_first_name;
						$billing_last_name  = $guest_data->billing_last_name;
						$email_id           = $guest_data->email_id;
					}
				} elseif ( 'REGISTERED' === $user_type ) {

					$billing_first_name = get_user_meta( $user_id, 'billing_first_name', true );
					$billing_last_name  = get_user_meta( $user_id, 'billing_last_name', true );
					$email_id           = get_user_meta( $user_id, 'billing_email', true );
					$phone              = get_user_meta( $user_id, 'billing_phone', true );
				}

				$product_details = businessonbot_get_product_details( $cart_history->abandoned_cart_info );

				return array(
					'id'                 => $abandoned_id,
					'product_details'    => $product_details,
					'timestamp'          => $cart_history->abandoned_cart_time,
					'billing_first_name' => $billing_first_name,
					'billing_last_name'  => $billing_last_name,
					'email_id'           => $email_id,
					'phone'              => $phone,
					'user_type'          => $user_type,
				);
			}

			return false;
		}

		/**
		 * Setup scheduled actions in Action Scheduler.
		 *
		 * @return void
		 */
		public static function maybe_add_scheduled_action(): void {
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				$cut_off = intval( get_option( 'businessonbot_abandoned_product_timeout', 0 ) );
				if ( 0 !== $cut_off ) {
					$cut_off = $cut_off * MINUTE_IN_SECONDS;
					if ( ! as_has_scheduled_action( 'businessonbot_abandoned_products' ) ) {
						as_schedule_single_action( time() + $cut_off, 'businessonbot_abandoned_products' );
					}
				}
			}
		}
	}
	BusinessOnBot::init();
}
