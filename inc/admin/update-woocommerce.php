<?php
/**
 * KH Speed Engine - WooCommerce hooks.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

add_action( 'woocommerce_payment_complete', 'khse_payment_complete' );

/**
 * Delete the cache of the post that is just updated.
 *
 * @return void
 */
function khse_payment_complete( $order_id ) {
	if ( 'yes' !== get_option( 'khse_option_woocommerce_event_payment_complete', 'no' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
		return;
	}

	$items = $order->get_items();
	$driver = khse_driver_factory( get_option( 'khse_option_driver' ) );

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
			continue;
		}

		$product_id = absint( $item->get_product_id() );
		$post_url   = $product_id ? get_permalink( $product_id ) : '';

		if ( false === $post_url || '' === $post_url ) {
			continue;
		}

		khse_purge_permalink( $post_url, $driver );
	}
}
