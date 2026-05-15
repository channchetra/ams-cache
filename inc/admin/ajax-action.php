<?php
/**
 * AMS Cache - Setting page.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.5.2
 * @version 1.5.2
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

require_once SCM_PLUGIN_DIR . 'inc/helpers.php';
require_once SCM_PLUGIN_DIR . 'inc/admin/functions.php';
require_once SCM_PLUGIN_DIR . 'vendor/autoload.php';

add_action( 'wp_ajax_scm_action_clear_cache', 'scm_ajax_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_purge_current_page', 'scm_ajax_purge_current_page_callback' );
add_action( 'wp_ajax_scm_action_dashboard_status', 'scm_ajax_dashboard_status_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache', 'scm_ajax_dashboard_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache_type', 'scm_ajax_dashboard_clear_cache_type_callback' );
add_action( 'wp_ajax_scm_action_dashboard_reports', 'scm_ajax_dashboard_reports_callback' );
add_action( 'wp_ajax_scm_action_dashboard_preload', 'scm_ajax_dashboard_preload_callback' );
add_action( 'wp_ajax_scm_action_dashboard_purge_homepage', 'scm_ajax_dashboard_purge_homepage_callback' );

/**
 * Validate dashboard AJAX request.
 *
 * @return void
 */
function scm_ajax_dashboard_guard() {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'scm_dashboard_' . scm_get_dir_hash() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Token has been rejected.', 'ams-cache' ),
			)
		);
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'ams-cache' ),
			)
		);
	}
}

/**
 * AJAX callback.
 *
 * @return void
 */
function scm_ajax_clear_cache_callback() {

	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'scm_clear_cache_' . scm_get_dir_hash() ) ) {
		echo __( 'Token has been rejected.', 'ams-cache' );
		wp_die();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		echo __( 'Access denied.', 'ams-cache' );
		wp_die();
	}

	$rows = scm_clear_all_cache();

	if ( $rows > 0 ) {
		// translators: %s is the number of rows.
		echo sprintf( __( '%s rows has been deleted.', 'ams-cache' ), $rows );
	} else {
		echo __( 'There is no cache on your site currently.', 'ams-cache' );
	}

	wp_die();
}

/**
 * AJAX callback for purging one current page.
 *
 * @return void
 */
function scm_ajax_purge_current_page_callback() {

	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'scm_purge_current_page_' . scm_get_dir_hash() ) ) {
		echo __( 'Token has been rejected.', 'ams-cache' );
		wp_die();
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		echo __( 'Access denied.', 'ams-cache' );
		wp_die();
	}

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $url ) ) {
		echo __( 'Current page URL is empty.', 'ams-cache' );
		wp_die();
	}

	$home_host = parse_url( home_url( '/' ), PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );

	if ( ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $home_host ) ) {
		echo __( 'Only same-site URLs can be purged.', 'ams-cache' );
		wp_die();
	}

	$path = parse_url( $url, PHP_URL_PATH );
	$path = empty( $path ) ? '/' : $path;

	if ( ! scm_is_cacheable_document_path( $path ) ) {
		echo __( 'This URL is not a cacheable page.', 'ams-cache' );
		wp_die();
	}

	$result = scm_purge_cache_uri( $path );

	echo sprintf(
		/* translators: %s is the purged URL path. */
		__( 'Cache purged for current page: %s', 'ams-cache' ),
		$result['uri']
	);

	wp_die();
}

/**
 * Dashboard status callback.
 *
 * @return void
 */
function scm_ajax_dashboard_status_callback() {
	scm_ajax_dashboard_guard();

	wp_send_json_success(
		array(
			'status' => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard clear cache callback.
 *
 * @return void
 */
function scm_ajax_dashboard_clear_cache_callback() {
	scm_ajax_dashboard_guard();

	$rows = scm_clear_all_cache();
	scm_schedule_preload_cache( true );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of rows. */
				__( '%s rows deleted. Background preload queued.', 'ams-cache' ),
				$rows
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard clear one cache type callback.
 *
 * @return void
 */
function scm_ajax_dashboard_clear_cache_type_callback() {
	scm_ajax_dashboard_guard();

	$cache_type = isset( $_POST['cacheType'] ) ? sanitize_key( wp_unslash( $_POST['cacheType'] ) ) : '';
	$labels     = scm_get_cache_type_list();
	$list       = array_keys( $labels );

	if ( ! in_array( $cache_type, $list, true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Unknown cache type.', 'ams-cache' ),
			)
		);
	}

	$rows = scm_clear_cache_type( $cache_type );
	$label = wp_strip_all_tags( $labels[ $cache_type ] );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: 1: cache type, 2: number of rows. */
				__( '%1$s cache cleared. %2$s rows deleted.', 'ams-cache' ),
				$label,
				$rows
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard lazy report page callback.
 *
 * @return void
 */
function scm_ajax_dashboard_reports_callback() {
	scm_ajax_dashboard_guard();

	$offset        = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
	$page_size     = 5;
	$total_reports = scm_count_page_optimization_reports();
	$reports       = scm_dashboard_normalize_optimization_reports(
		scm_get_page_optimization_reports( $page_size, $offset )
	);
	$loaded_count  = min( $total_reports, $offset + count( $reports ) );

	wp_send_json_success(
		array(
			'reports'     => $reports,
			'loadedCount' => $loaded_count,
			'totalReports'=> $total_reports,
			'hasMore'     => $loaded_count < $total_reports,
		)
	);
}

/**
 * Dashboard preload callback.
 *
 * @return void
 */
function scm_ajax_dashboard_preload_callback() {
	scm_ajax_dashboard_guard();

	$count = scm_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( '%s preload URLs queued. First batch started.', 'ams-cache' ),
				$count
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard purge homepage callback.
 *
 * @return void
 */
function scm_ajax_dashboard_purge_homepage_callback() {
	scm_ajax_dashboard_guard();

	scm_purge_cache_uri( '/' );
	$count = scm_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( 'Homepage cache purged. %s priority preload URLs queued.', 'ams-cache' ),
				$count
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}
