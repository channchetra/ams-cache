<?php
/**
 * Uninstall AMS Cache plugin.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 *
 * @package AMS Cache
 * @since 1.0.0
 * @version 1.3.0
 */

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$option_uninstall = get_option( 'scm_option_uninstall' );
$runtime_root     = '';
$runtime_candidates = array();

if ( defined( 'WP_TEMP_DIR' ) && WP_TEMP_DIR ) {
	$runtime_candidates[] = WP_TEMP_DIR;
}

if ( function_exists( 'sys_get_temp_dir' ) ) {
	$runtime_candidates[] = sys_get_temp_dir();
}

$public_root = rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/';

foreach ( $runtime_candidates as $candidate ) {
	$candidate = rtrim( str_replace( '\\', '/', $candidate ), '/' ) . '/';

	if ( 0 !== strncasecmp( $candidate, $public_root, strlen( $public_root ) ) ) {
		$runtime_root = rtrim( $candidate, '/' );
		break;
	}
}

$runtime_hash     = get_option( 'scm_dir_hash', '' );
$runtime_blog     = get_option( 'scm_blog_id', 1 );

if ( 'yes' === $option_uninstall ) {

	$options = array(
		'driver',
		'ttl',
		'uninstall',
		'caching_status',
		'expert_mode_status',
		'post_homepage',
		'visibility_login_user',
		'visibility_guest',
		'statistics_status',
		'clear_cache',
		'benchmark_widget',
		'benchmark_footer_text',
		'benchmark_widget_display',
		'benchmark_footer_text_display',
		'exclusion_status',
		'excluded_list',
		'excluded_list_filtered',
		'excluded_get_vars',
		'excluded_post_vars',
		'excluded_cookie_vars',
		'advanced_driver_file',
		'advanced_driver_memcached',
		'advanced_driver_redis',
		'advanced_driver_mongodb',
		'advanced_driver_mongodb_connection_type',
		'advanced_driver_redis_connection_type',
		'advanced_driver_memcached_connection_type',
		'cache_key_prefix',
		'cache_max_entries',
		'ttl_mechanism',
		'config',
		'nginx_direct_cache_status',
		'page_optimization',
		'preload_cache',
		'preload_limit',
		'preload_homepage_links',
		'html_debug_comment',
		'post_types',
		'post_archives',
		'woocommerce_status',
		'woocommerce_post_types',
		'woocommerce_taxonomies',
		'woocommerce_event_payment_complete',
	);

	foreach ( $options as $option ) {
		delete_option( 'scm_option_' . $option );
	}

	delete_option( 'scm_last_reset_time' );
	delete_option( 'scm_version' );
	delete_option( 'scm_dir_hash' );
	delete_option( 'scm_blog_id' );
	delete_option( 'scm_last_preload_time' );
	delete_option( 'scm_last_preload_count' );
	delete_option( 'scm_last_preload_priority_count' );
	delete_option( 'scm_last_homepage_priority_preload_time' );
	delete_option( 'scm_last_homepage_priority_preload_count' );
	delete_option( 'scm_last_cache_prune_count' );
	delete_option( 'scm_last_critical_preload_time' );
	delete_option( 'scm_last_critical_preload_count' );
	delete_option( 'scm_preload_queue_total' );
	delete_option( 'scm_preload_queue_processed' );
	delete_option( 'scm_preload_queue_remaining' );
	delete_option( 'scm_preload_queue_started_time' );
	delete_option( 'scm_preload_queue_finished_time' );

	$dirs = array(
		WP_CONTENT_DIR . '/uploads/ams-cache',
		WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master',
	);

	if ( '' !== $runtime_root && '' !== $runtime_hash ) {
		$runtime_dir = rtrim( str_replace( '\\', '/', $runtime_root ), '/' ) . '/ams-cache/' . absint( $runtime_blog ) . '_' . sanitize_key( $runtime_hash );
		$public_root = rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/';

		if ( 0 !== strncasecmp( $runtime_dir . '/', $public_root, strlen( $public_root ) ) ) {
			$dirs[] = $runtime_dir;
		}
	}

	foreach ( $dirs as $dir ) {
		if ( ! is_dir( $dir ) ) {
			continue;
		}

		$it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );

		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getRealPath() );
			} else {
				unlink( $file->getRealPath() );
			}
		}
		unset( $it, $files );

		rmdir( $dir );
	}
}
