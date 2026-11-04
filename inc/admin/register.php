<?php
/**
 * AMS Cache - Activating plugin.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

require_once __DIR__ . '/option-schema.php';

/**
 * Assign default setting values while activating this plugin.
 *
 * @return void
 */
function scm_activation() {

	$options = scm_get_option_schema();

	$options['cache_key_prefix']  = 'scm_' . get_current_blog_id() . '_' . scm_ensure_dir_hash() . '_';
	$options['page_optimization'] = scm_get_default_page_optimization_settings();

	foreach ( $options as $key => $value ) {
		add_option( 'scm_option_' . $key, $value );
	}

	scm_setup_security_files();
	scm_ensure_upload_dir();

	$file_dir = scm_get_upload_dir() . '/file_driver';

	if ( ! is_dir( $file_dir ) ) {
		wp_mkdir_p( $file_dir );
	}

	$sqlite_dir = scm_get_upload_dir() . '/sqlite_driver';

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
function scm_setup_security_files() {
	scm_set_blog_id();
	scm_ensure_dir_hash();
	scm_ensure_upload_dir();

	update_option( 'scm_last_reset_time', time() );
	update_option( 'scm_version', SCM_PLUGIN_VERSION );

	// Migrate the legacy public config into the protected option, then remove it.
	$legacy_config_file = scm_get_upload_dir() . '/config.json';

	if ( file_exists( $legacy_config_file ) ) {
		$legacy_config = json_decode( file_get_contents( $legacy_config_file ), true );

		if ( is_array( $legacy_config ) && empty( get_option( 'scm_config', array() ) ) ) {
			update_option( 'scm_config', $legacy_config );
		}

		@unlink( $legacy_config_file );
	}

	$public_dirs = array_unique(
		array(
			WP_CONTENT_DIR . '/uploads/' . SCM_PLUGIN_TEXT_DOMAIN,
			scm_get_upload_dir(),
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
function scm_deactivation() {

	$option_uninstall = get_option( 'scm_option_uninstall' );
	delete_transient( 'scm_preload_queue' );

	if ( 'yes' === $option_uninstall ) {

		foreach ( scm_get_option_keys() as $key ) {
			delete_option( 'scm_option_' . $key );
		}

		// Legacy keys removed by the unified schema.
		foreach ( array( 'excluded_get_variables', 'excluded_post_variables', 'excluded_cookie_variables', 'woocommerce_taxonomies' ) as $legacy_key ) {
			delete_option( 'scm_option_' . $legacy_key );
		}

		// Queue state is stored as unprefixed options.
		foreach ( array( 'scm_preload_queue_total', 'scm_preload_queue_processed', 'scm_preload_queue_remaining', 'scm_preload_queue_started_time', 'scm_preload_queue_finished_time' ) as $queue_key ) {
			delete_option( $queue_key );
		}
	}
}
