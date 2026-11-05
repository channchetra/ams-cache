<?php
/**
 * KH Speed Engine — cache storage.
 *
 * Site identity, storage paths, config persistence, and stats files.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Ensure caching is disabled when permalinks are plain.
 *
 * @return void
 */
function khse_ensure_permalink_structure() {
	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'khse_option_caching_status', 'disable' );
	}
}

/**
 * Return the site upload directory.
 *
 * Pure: does not migrate or create directories. Use
 * khse_ensure_upload_dir() before writing.
 *
 * @return string
 */
function khse_get_upload_dir() {
	$base_dir = WP_CONTENT_DIR . '/uploads/kh-speed-engine';

	return $base_dir . '/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
}

/**
 * Ensure the upload directory exists, migrating the legacy directory.
 *
 * @return string
 */
function khse_ensure_upload_dir() {
	$base_dir    = WP_CONTENT_DIR . '/uploads/kh-speed-engine';
	$legacy_dirs = array(
		WP_CONTENT_DIR . '/uploads/ams-cache',
		WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master',
	);

	foreach ( $legacy_dirs as $legacy_dir ) {
		if ( ! is_dir( $base_dir ) && is_dir( $legacy_dir ) ) {
			if ( ! @rename( $legacy_dir, $base_dir ) ) {
				error_log( '[KH Speed Engine] Could not move legacy upload directory: ' . $legacy_dir );
			}
			break;
		}
	}

	if ( ! is_dir( $base_dir ) && ! wp_mkdir_p( $base_dir ) ) {
		error_log( '[KH Speed Engine] Could not create the cache upload directory: ' . $base_dir );
	}

	return khse_get_upload_dir();
}

/**
 * Get the base directory for private runtime data.
 *
 * Never place control files below the public WordPress document root. Hosts may
 * not run Apache, so filesystem permissions alone are not a sufficient guard.
 *
 * @return string
 */
function khse_get_runtime_base_dir() {
	$candidates = array();

	if ( defined( 'WP_TEMP_DIR' ) && WP_TEMP_DIR ) {
		$candidates[] = WP_TEMP_DIR;
	}

	$candidates[] = sys_get_temp_dir();

	if ( defined( 'ABSPATH' ) ) {
		$candidates[] = dirname( ABSPATH ) . '/kh-speed-engine-runtime';
	}

	$public_roots = array_filter(
		array(
			defined( 'ABSPATH' ) ? ABSPATH : '',
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '',
		)
	);

	foreach ( $candidates as $candidate ) {
		$candidate = rtrim( str_replace( '\\', '/', (string) $candidate ), '/' );

		if ( '' === $candidate ) {
			continue;
		}

		$real_candidate = realpath( $candidate );
		$compare_path   = false !== $real_candidate
			? rtrim( str_replace( '\\', '/', $real_candidate ), '/' )
			: $candidate;

		$is_public = false;

		foreach ( $public_roots as $public_root ) {
			$public_root = rtrim( str_replace( '\\', '/', (string) $public_root ), '/' );

			if ( '' !== $public_root && 0 === strcasecmp( $compare_path, $public_root ) ) {
				$is_public = true;
				break;
			}

			if ( '' !== $public_root && 0 === strncasecmp( $compare_path . '/', $public_root . '/', strlen( $public_root ) + 1 ) ) {
				$is_public = true;
				break;
			}
		}

		if ( ! $is_public ) {
			return $candidate;
		}
	}

	return rtrim( sys_get_temp_dir(), '/\\' );
}

/**
 * Get a private runtime directory for config, locks, and temporary workspaces.
 *
 * @return string
 */
function khse_get_private_runtime_dir() {
	return khse_get_runtime_base_dir() . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
}

/**
 * Get the private Expert Mode lock path.
 *
 * @return string
 */
function khse_get_expert_lock_path() {
	return khse_get_private_runtime_dir() . '/expert.lock';
}

/**
 * Get configuration file's path.
 *
 * @return string
 */
function khse_get_config_path() {
	return khse_get_private_runtime_dir() . '/config.json';
}

/**
 * Merge a stored config over the canonical defaults.
 *
 * @param array $config Raw config.
 *
 * @return array
 */
function khse_normalize_config( $config ) {
	$defaults = khse_get_default_config();
	$config   = is_array( $config ) ? $config : array();
	$config   = array_merge( $defaults, $config );

	foreach ( array( 'preload', 'exclusion' ) as $section ) {
		$config[ $section ] = array_merge(
			$defaults[ $section ],
			isset( $config[ $section ] ) && is_array( $config[ $section ] ) ? $config[ $section ] : array()
		);
	}

	// Drop the retired WooCommerce section from stored configs.
	unset( $config['woocommerce'] );

	return $config;
}

/**
 * Decode and normalize a runtime config JSON payload.
 *
 * @param string $content Raw JSON.
 *
 * @return array Normalized config, or an empty array when unreadable.
 */
function khse_decode_config_json( $content ) {
	$data = json_decode( (string) $content, true );

	return is_array( $data ) ? khse_normalize_config( $data ) : array();
}

/**
 * Get configuration data.
 *
 * @return array
 */
function khse_get_config_data() {
	$option_config = get_option( 'khse_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return khse_normalize_config( $option_config );
	}

	$file = khse_get_config_path();

	if ( file_exists( $file ) ) {
		$content = @file_get_contents( $file );
		$config  = json_decode( (string) $content, true );

		if ( is_array( $config ) ) {
			return khse_normalize_config( $config );
		}

		error_log( '[KH Speed Engine] Ignoring unreadable runtime config: ' . $file );
	}

	return khse_get_default_config();
}



/**
 * Save the settings into the JSON runtime config.
 *
 * @param array $setting The setting.
 *
 * @return bool True when the runtime config file was written.
 */
function khse_update_config( $setting ) {
	if ( khse_settings_batch_active() ) {
		return true;
	}

	$config = khse_get_config_data();

	if ( is_array( $setting ) ) {
		$config = array_merge( $config, $setting );
	}

	$config = khse_normalize_config( $config );

	if ( empty( $config['site_url'] ) ) {
		$config['site_url'] = rtrim( get_site_url(), '/' );
	}

	if ( empty( $config['cache_key_prefix'] ) ) {
		$config['cache_key_prefix'] = khse_get_cache_key_prefix();
	}

	$content = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	if ( false === $content ) {
		return false;
	}

	$file = khse_get_config_path();
	$dir  = dirname( $file );

	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	$temp_file = $file . '.tmp';

	// phpcs:ignore
	$written = @file_put_contents( $temp_file, $content, LOCK_EX );

	if ( false === $written || strlen( $content ) !== $written ) {
		@unlink( $temp_file );
		return false;
	}

	if ( ! @rename( $temp_file, $file ) ) {
		// Windows cannot always replace an existing file with rename().
		$written = @file_put_contents( $file, $content, LOCK_EX );
		@unlink( $temp_file );

		if ( false === $written || strlen( $content ) !== $written ) {
			return false;
		}
	}

	update_option( 'khse_config', $config );

	if ( function_exists( 'chmod' ) ) {
		@chmod( $file, 0600 );
	}

	$legacy_file = khse_get_upload_dir() . '/config.json';

	if ( file_exists( $legacy_file ) && $legacy_file !== $file ) {
		@unlink( $legacy_file );
	}

	return true;
}

/**
 * Build the complete runtime config from live settings.
 *
 * @return array
 */
function khse_build_runtime_config() {
	$driver_type     = khse_get_setting( 'cache', 'driver' );
	$connection_type = khse_get_driver_connection_type( $driver_type );

	$config = khse_get_config_from_schema();

	$config['site_url']                 = rtrim( get_site_url(), '/' );
	$config['driver_advanced_settings'] = khse_normalize_driver_settings(
		khse_get_driver_advanced_settings( $driver_type ),
		$connection_type
	);
	$config['driver_connection_type']   = $connection_type;
	$config['exclusion']                = khse_build_runtime_exclusion_config();

	return khse_normalize_config( $config );
}

/**
 * Sync runtime config and Expert Mode lock file.
 *
 * @return bool True when config (and the lock, if enabled) was written.
 */
function khse_sync_expert_mode_runtime() {
	if ( khse_settings_batch_active() ) {
		return true;
	}

	$config_written = khse_update_config( khse_build_runtime_config() );
	$checkpoint     = khse_get_expert_lock_path();

	if ( 'enable' === get_option( 'khse_option_expert_mode_status' ) ) {
		if ( ! $config_written || ! wp_mkdir_p( dirname( $checkpoint ) ) ) {
			return false;
		}

		// phpcs:ignore
		$lock_written = @file_put_contents( $checkpoint, 'VOTE!', LOCK_EX );

		if ( false === $lock_written ) {
			return false;
		}

		@chmod( $checkpoint, 0600 );
		return true;
	}

	if ( file_exists( $checkpoint ) ) {
		@unlink( $checkpoint );
	}

	return $config_written;
}
