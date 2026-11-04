<?php
/**
 * KH Speed Engine helper functions.
 *
 * @package   KH Speed Engine
 * @author    Chetra Chann
 * @license   GPLv3 (or later)
 * @link      https://khspeed.com
 * @copyright 2020 Chetra Chann
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'KHSE_INC', true );

/**
 * Use the "Expert Mode", see explanation in setting page.
 *
 * @param array $args
 * 
 * @return void
 */
function khse_run_expert_mode( $args ) {

	// Raise memory early (Expert Mode runs before WP_MEMORY_LIMIT is applied).
	// Default FPM 128M can be exhausted reading multi-MB cache files from the file driver.
	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'memory_limit', '512M' );
	}

	$microtime_before = microtime( true );

	// Prevent CLI conficts
	if ( ! isset( $_SERVER['REQUEST_URI' ] ) ) {
		return;
	}

	$plugin_dir        = rtrim( $args['plugin_dir'], '/' );
	$plugin_upload_dir = rtrim( $args['plugin_upload_dir'], '/' );
	$runtime_dir       = isset( $args['runtime_dir'] ) ? rtrim( $args['runtime_dir'], '/' ) : '';

	// Make sure that KH Speed Engine exists.
	if ( ! file_exists( $plugin_dir . '/kh-speed-engine.php' ) ) {
		return;
	}

	// New snippets use a private runtime directory. Legacy snippets that point at
	// a public upload directory are rejected below and must be regenerated from
	// the dashboard.
	if ( '' === $runtime_dir ) {
		return;
	}

	$lock_file   = $runtime_dir . '/expert.lock';
	$config_file = $runtime_dir . '/config.json';

	// Never read control files from the public document root.
	$runtime_compare = realpath( $runtime_dir );
	$runtime_compare = false !== $runtime_compare ? $runtime_compare : $runtime_dir;
	$runtime_compare = rtrim( str_replace( '\\', '/', $runtime_compare ), '/' );
	$public_root     = defined( 'ABSPATH' ) ? rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) : '';

	if (
		'' !== $public_root &&
		( 0 === strcasecmp( $runtime_compare, $public_root ) || 0 === strncasecmp( $runtime_compare . '/', $public_root . '/', strlen( $public_root ) + 1 ) )
	) {
		return;
	}

	if ( ! file_exists( $lock_file ) || ! file_exists( $config_file ) ) {
		return;
	}

	// Read configuration data.
	$content = file_get_contents( $config_file );
	$config  = json_decode( $content, true );

	if ( ! is_array( $config ) ) {
		return;
	}

	include_once( $plugin_dir . '/inc/lib/autoload.php' );
	include_once( $plugin_dir . '/inc/helpers.php' );

	if ( ! isset( $config['cache_enabled'] ) ) {
		$config['cache_enabled'] = true;
	}

	$cached_content = khse_serve_cached_response( $config, null, $plugin_upload_dir, true, $microtime_before );

	if ( null !== $cached_content ) {
		echo $cached_content;
		exit;
	}
}
