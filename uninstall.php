<?php
/**
 * BusinessOnBot Uninstall
 *
 * Uninstalling BusinessOnBot deletes tables, and options.
 *
 * @author      BusinessOnBot
 * @package     BusinessOnBot/Uninstaller
 * @version     1.0.1
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$options_to_delete = array(
	'businessonbot_db_version',
	'businessonbot_api_script',
	'businessonbot_abandoned_cart_timeout',
	'businessonbot_abandoned_product_timeout',
	'businessonbot_security_key',
);

$tables = array(
	'businessonbot_abandoned_cart',
	'businessonbot_guest_abandoned_cart',
	'businessonbot_checkout_links',
	'businessonbot_visited_products_log',
);

$db_prefix = $wpdb->prefix;

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

foreach ( $tables as $table ) {
	$table_name = $db_prefix . $table;
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
}

if ( get_current_user_id() ) {
	delete_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id() );
	delete_user_meta( get_current_user_id(), '_businessonbot_modified_cart' . get_current_blog_id() );
}
