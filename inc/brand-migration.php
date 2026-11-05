<?php
/**
 * KH Speed Engine - brand migration.
 *
 * Loaded on every request so legacy data is migrated before the cache
 * runtime creates the new storage namespace.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

/**
 * Migrate AMS Cache options and runtime data to KH Speed Engine names.
 *
 * Runs once after the rebrand. Existing settings, cache files, and the
 * Expert Mode runtime directory keep working under the new names.
 *
 * @return void
 */
function khse_maybe_migrate_legacy_brand() {
	if ( get_option( 'khse_brand_migrated' ) ) {
		return;
	}

	global $wpdb;

	$legacy_options = $wpdb->get_results(
		"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'scm\\_%'",
		ARRAY_A
	);

	foreach ( $legacy_options as $row ) {
		$new_name = 'khse_' . substr( $row['option_name'], 4 );

		if ( false === get_option( $new_name, false ) ) {
			update_option( $new_name, maybe_unserialize( $row['option_value'] ) );
		}
	}

	if ( ! empty( $legacy_options ) ) {
		foreach ( $legacy_options as $row ) {
			delete_option( $row['option_name'] );
		}

		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_scm\\_%' OR option_name LIKE '_transient_timeout_scm\\_%'" );
	}

	$uploads     = WP_CONTENT_DIR . '/uploads';
	$new_upload  = $uploads . '/kh-speed-engine';
	$legacy_dirs = array( $uploads . '/ams-cache', $uploads . '/cache-master' );

	foreach ( $legacy_dirs as $legacy_dir ) {
		if ( ! is_dir( $new_upload ) && is_dir( $legacy_dir ) ) {
			@rename( $legacy_dir, $new_upload );
			break;
		}
	}

	$old_runtime = rtrim( sys_get_temp_dir(), '/\\' ) . '/ams-cache';
	$new_runtime = rtrim( sys_get_temp_dir(), '/\\' ) . '/kh-speed-engine';

	if ( ! is_dir( $new_runtime ) && is_dir( $old_runtime ) ) {
		@rename( $old_runtime, $new_runtime );
	}

	$prefix = get_option( 'khse_option_cache_key_prefix' );

	if ( is_string( $prefix ) && 0 === strpos( $prefix, 'scm_' ) ) {
		update_option( 'khse_option_cache_key_prefix', 'khse_' . substr( $prefix, 4 ) );
		if ( function_exists( 'khse_clear_all_cache' ) ) {
			khse_clear_all_cache();
		}
	}

	update_option( 'khse_version', KHSE_PLUGIN_VERSION );
	update_option( 'khse_brand_migrated', 1 );

	if ( 'enable' === get_option( 'khse_option_expert_mode_status' ) ) {
		khse_sync_expert_mode_runtime();
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'khse_maybe_migrate_legacy_brand', 5 );
}
