<?php
/**
 * AMS Cache - WooCommerce events.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

add_action( 'woocommerce_payment_complete', 'scm_payment_complete' );

/**
 * Delete the cache of the post that is just updated.
 *
 * @return void
 */
function scm_payment_complete( $order_id ) {
	if ( 'yes' !== get_option( 'scm_option_woocommerce_event_payment_complete', 'no' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
		return;
	}

	$items = $order->get_items();
	$driver = scm_driver_factory( get_option( 'scm_option_driver' ) );

	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
			continue;
		}

		$product_id = absint( $item->get_product_id() );
		$post_url   = $product_id ? get_permalink( $product_id ) : '';

		if ( '' === $post_url ) {
			continue;
		}

		scm_purge_cache_uri( parse_url( $post_url, PHP_URL_PATH ), $driver );
	}
}
