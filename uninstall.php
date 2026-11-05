<?php
/**
 * Uninstall KH Speed Engine plugin.
 *
 * @author Chetra Chann
 * @link https://khspeed.com/
 *
 * @package KH Speed Engine
 * @since 1.0.0
 * @version 1.3.0
 */

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

if ( ! defined( 'KHSE_INC' ) ) {
	define( 'KHSE_INC', true );
}

require_once __DIR__ . '/inc/cache-core.php';
require_once __DIR__ . '/inc/option-schema.php';

$option_uninstall = get_option( 'khse_option_uninstall' );
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

$runtime_hash     = get_option( 'khse_dir_hash', '' );
$runtime_blog     = get_option( 'khse_blog_id', 1 );

if ( 'yes' === $option_uninstall ) {

	global $wpdb;

	// Remove every plugin-owned option, including leftover keys not in the
	// current schema, plus the pre-rebrand scm_* options.
	$plugin_option_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'khse_' ) . '%'
		)
	);

	foreach ( $plugin_option_names as $plugin_option_name ) {
		delete_option( $plugin_option_name );
	}

	$legacy_option_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'scm_option_' ) . '%'
		)
	);

	foreach ( $legacy_option_names as $legacy_option_name ) {
		delete_option( $legacy_option_name );
	}

	// Remove plugin and legacy transients.
	$transient_patterns = array(
		$wpdb->esc_like( '_transient_khse_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_khse_' ) . '%',
		$wpdb->esc_like( '_transient_scm_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_scm_' ) . '%',
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$transient_patterns[0],
			$transient_patterns[1],
			$transient_patterns[2],
			$transient_patterns[3]
		)
	);

	$options = khse_get_option_keys();

	foreach ( $options as $option ) {
		delete_option( 'khse_option_' . $option );
	}

	// Legacy keys removed by the unified schema.
	delete_option( 'khse_option_excluded_get_variables' );
	delete_option( 'khse_option_excluded_post_variables' );
	delete_option( 'khse_option_excluded_cookie_variables' );
	delete_option( 'khse_option_woocommerce_status' );
	delete_option( 'khse_option_woocommerce_post_types' );
	delete_option( 'khse_option_woocommerce_post_archives' );
	delete_option( 'khse_option_woocommerce_event_payment_complete' );
	delete_option( 'khse_option_woocommerce_taxonomies' );
	delete_option( 'khse_woocommerce_removed' );

	delete_option( 'khse_last_reset_time' );
	delete_option( 'khse_version' );
	delete_option( 'khse_brand_migrated' );
	delete_option( 'khse_dir_hash' );
	delete_option( 'khse_blog_id' );
	delete_option( 'khse_last_preload_time' );
	delete_option( 'khse_last_preload_count' );
	delete_option( 'khse_last_preload_priority_count' );
	delete_option( 'khse_last_homepage_priority_preload_time' );
	delete_option( 'khse_last_homepage_priority_preload_count' );
	delete_option( 'khse_last_cache_prune_count' );
	delete_option( 'khse_last_critical_preload_time' );
	delete_option( 'khse_last_critical_preload_count' );
	delete_option( 'khse_preload_queue_total' );
	delete_option( 'khse_preload_queue_processed' );
	delete_option( 'khse_preload_queue_remaining' );
	delete_option( 'khse_preload_queue_started_time' );
	delete_option( 'khse_preload_queue_finished_time' );

	$dirs = array(
		WP_CONTENT_DIR . '/uploads/kh-speed-engine',
		WP_CONTENT_DIR . '/uploads/cache-master',
		WP_CONTENT_DIR . '/uploads/ams-cache',
	);

	$legacy_runtime = rtrim( sys_get_temp_dir(), '/\\' ) . '/ams-cache';

	if ( '' !== $legacy_runtime && 0 !== strncasecmp( $legacy_runtime . '/', $public_root, strlen( $public_root ) ) ) {
		$dirs[] = $legacy_runtime;
	}

	if ( '' !== $runtime_root && '' !== $runtime_hash ) {
		$runtime_dir = rtrim( str_replace( '\\', '/', $runtime_root ), '/' ) . '/kh-speed-engine/' . absint( $runtime_blog ) . '_' . sanitize_key( $runtime_hash );
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
