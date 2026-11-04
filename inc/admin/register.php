<?php
/**
 * KH Speed Engine - Activation and lifecycle.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

require_once __DIR__ . '/option-schema.php';

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
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'scm\\_%'" );
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

	update_option( 'khse_brand_migrated', 1 );

	if ( 'enable' === get_option( 'khse_option_expert_mode_status' ) ) {
		khse_sync_expert_mode_runtime();
	}
}
add_action( 'admin_init', 'khse_maybe_migrate_legacy_brand' );

/**
 * Assign default setting values while activating this plugin.
 *
 * @return void
 */
function khse_activation() {

	$options = khse_get_option_schema();

	$options['cache_key_prefix']  = 'khse_' . get_current_blog_id() . '_' . khse_ensure_dir_hash() . '_';
	$options['page_optimization'] = khse_get_default_page_optimization_settings();

	foreach ( $options as $key => $value ) {
		add_option( 'khse_option_' . $key, $value );
	}

	khse_setup_security_files();
	khse_ensure_upload_dir();

	$file_dir = khse_get_upload_dir() . '/file_driver';

	if ( ! is_dir( $file_dir ) ) {
		wp_mkdir_p( $file_dir );
	}

	$sqlite_dir = khse_get_upload_dir() . '/sqlite_driver';

	if ( ! is_dir( $sqlite_dir ) ) {
		wp_mkdir_p( $sqlite_dir );
	}
}

/**
 * Protect the directory from browsing.
 * This function will create an index.html and .htaccess in the plugin's directory.
 *
 * @return void
 */
function khse_setup_security_files() {
	khse_set_blog_id();
	khse_ensure_dir_hash();
	khse_ensure_upload_dir();

	update_option( 'khse_last_reset_time', time() );
	update_option( 'khse_version', KHSE_PLUGIN_VERSION );

	// Migrate the legacy public config into the protected option, then remove it.
	$legacy_config_file = khse_get_upload_dir() . '/config.json';

	if ( file_exists( $legacy_config_file ) ) {
		$legacy_config = json_decode( file_get_contents( $legacy_config_file ), true );

		if ( is_array( $legacy_config ) && empty( get_option( 'khse_config', array() ) ) ) {
			update_option( 'khse_config', $legacy_config );
		}

		@unlink( $legacy_config_file );
	}

	$public_dirs = array_unique(
		array(
			WP_CONTENT_DIR . '/uploads/' . KHSE_PLUGIN_TEXT_DOMAIN,
			khse_get_upload_dir(),
		)
	);

	foreach ( $public_dirs as $dir ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			continue;
		}

		$files = array(
			'index.html' => '',
			'.htaccess'  => "Require all denied\nDeny from all\n",
		);

		foreach ( $files as $file => $content ) {
			$path = trailingslashit( $dir ) . $file;

			if ( ! file_exists( $path ) ) {
				// phpcs:ignore
				@file_put_contents( $path, $content, LOCK_EX );
			}
		}
	}
}

/**
 * Deactivation.
 *
 * @return void
 */
function khse_deactivation() {

	$option_uninstall = get_option( 'khse_option_uninstall' );
	delete_transient( 'khse_preload_queue' );

	if ( 'yes' === $option_uninstall ) {

		foreach ( khse_get_option_keys() as $key ) {
			delete_option( 'khse_option_' . $key );
		}

		// Legacy keys removed by the unified schema.
		foreach ( array( 'excluded_get_variables', 'excluded_post_variables', 'excluded_cookie_variables', 'woocommerce_taxonomies' ) as $legacy_key ) {
			delete_option( 'khse_option_' . $legacy_key );
		}

		// Queue state is stored as unprefixed options.
		foreach ( array( 'khse_preload_queue_total', 'khse_preload_queue_processed', 'khse_preload_queue_remaining', 'khse_preload_queue_started_time', 'khse_preload_queue_finished_time' ) as $queue_key ) {
			delete_option( $queue_key );
		}
	}
}
