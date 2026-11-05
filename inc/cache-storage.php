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
 * Return the site directory hash.
 *
 * Pure: returns the persisted hash or a deterministic fallback without
 * writing. Use khse_ensure_dir_hash() when the value must be persisted.
 *
 * @return string
 */
function khse_get_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( ! empty( $hash ) ) {
		return $hash;
	}

	return khse_generate_dir_hash();
}

/**
 * Generate a deterministic directory hash for this site.
 *
 * @return string
 */

/**
 * Generate a deterministic directory hash for this site.
 *
 * @return string
 */
function khse_generate_dir_hash() {
	$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
	$site = defined( 'WP_HOME' ) ? WP_HOME : ( defined( 'ABSPATH' ) ? ABSPATH : __DIR__ );

	if ( function_exists( 'wp_hash' ) ) {
		return substr( wp_hash( $site . '|' . $salt ), 0, 8 );
	}

	return substr( hash( 'sha256', $site . '|' . $salt ), 0, 8 );
}

/**
 * Persist and return the site directory hash.
 *
 * @return string
 */

/**
 * Persist and return the site directory hash.
 *
 * @return string
 */
function khse_ensure_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( empty( $hash ) ) {
		$hash = khse_generate_dir_hash();
		update_option( 'khse_dir_hash', $hash );
	}

	return $hash;
}

/**
 * Check whether an option is enabled across yes/no and enable/disable vocabularies.
 *
 * @param string $key     Option name, with or without the khse_option_ prefix.
 * @param string $default Default when the option is missing.
 *
 * @return bool
 */

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
			@rename( $legacy_dir, $base_dir );
			break;
		}
	}

	if ( ! is_dir( $base_dir ) ) {
		wp_mkdir_p( $base_dir );
	}

	return khse_get_upload_dir();
}

/**
 * Get a private runtime directory for config, locks, and temporary workspaces.
 *
 * Never place control files below the public WordPress document root. Hosts may
 * not run Apache, so filesystem permissions alone are not a sufficient guard.
 *
 * @return string
 */

/**
 * Get a private runtime directory for config, locks, and temporary workspaces.
 *
 * Never place control files below the public WordPress document root. Hosts may
 * not run Apache, so filesystem permissions alone are not a sufficient guard.
 *
 * @return string
 */
function khse_get_private_runtime_dir() {
	$candidates = array();

	if ( defined( 'WP_TEMP_DIR' ) && WP_TEMP_DIR ) {
		$candidates[] = WP_TEMP_DIR;
	}

	if ( function_exists( 'sys_get_temp_dir' ) ) {
		$candidates[] = sys_get_temp_dir();
	}

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
			return $candidate . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
		}
	}

	return rtrim( sys_get_temp_dir(), '/\\' ) . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
}

/**
 * Get the private Expert Mode lock path.
 *
 * @return string
 */

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
 * Get configuration data.
 *
 * @return array
 */

/**
 * Get configuration data.
 *
 * @return array
 */
function khse_get_config_data() {
	$file = khse_get_config_path();

	if ( file_exists( $file ) ) {
		$content = file_get_contents( $file );
		$config = json_decode( $content, true );

		if ( is_array( $config ) ) {
			return khse_normalize_config( $config );
		}
	}

	$option_config = get_option( 'khse_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return khse_normalize_config( $option_config );
	}

	return khse_get_default_config();
}

/**
 * Check whether the current request is excluded from the page cache.
 *
 * @param array $config Runtime config.
 *
 * @return bool
 */

/**
 * Save the settings into the JSON runtime config.
 *
 * @param array $setting The setting.
 *
 * @return bool True when the runtime config file was written.
 */
function khse_update_config( $setting ) {
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
 * Sync runtime config and Expert Mode lock file.
 *
 * @return bool True when config (and the lock, if enabled) was written.
 */

/**
 * Sync runtime config and Expert Mode lock file.
 *
 * @return bool True when config (and the lock, if enabled) was written.
 */
function khse_sync_expert_mode_runtime() {
	if ( khse_settings_batch_active() ) {
		return true;
	}

	$driver_type      = khse_get_setting( 'cache', 'driver' );
	$connection_type  = khse_get_driver_connection_type( $driver_type );
	$advanced_setting = khse_normalize_driver_settings(
		khse_get_driver_advanced_settings( $driver_type ),
		$connection_type
	);

	$setting = khse_get_config_from_schema();

	$setting['site_url']                 = rtrim( get_site_url(), '/' );
	$setting['driver_advanced_settings'] = $advanced_setting;
	$setting['driver_connection_type']   = $connection_type;
	$setting['exclusion']                = khse_get_runtime_exclusion_config();

	$config_written = khse_update_config( $setting );
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

/**
 * Set channel Id.
 *
 * @return void
 */

/**
 * Set channel Id.
 *
 * @return void
 */
function khse_set_blog_id() {
	update_option( 'khse_blog_id', get_current_blog_id() );
}

/**
 * Get channel Id.
 *
 * @return string
 */

/**
 * Get channel Id.
 *
 * @return string
 */
function khse_get_blog_id() {
	return get_option( 'khse_blog_id', 1 );
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function khse_get_stats_dir( $cache_type = 'post' ) {
	return khse_get_upload_dir() . '/stats/' . $cache_type;
}

/**
 * Get the cache key prefix used to isolate shared cache stores.
 *
 * @return string
 */

/**
 * Get the cache key prefix used to isolate shared cache stores.
 *
 * @return string
 */
function khse_get_cache_key_prefix() {
	$prefix = get_option( 'khse_option_cache_key_prefix', '' );

	if ( '' === $prefix ) {
		$prefix = 'khse_' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '_';
	}

	$prefix = preg_replace( '/[^A-Za-z0-9_.-]/', '_', $prefix );

	if ( '' === $prefix ) {
		$prefix = 'khse_' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '_';
	}

	return rtrim( $prefix, '._-' ) . '_';
}

/**
 * Normalize a request URI before using it as a cache identity.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return string
 */

/**
 * Read one stats row.
 *
 * @param string $path Stats JSON path.
 *
 * @return array{size:int,uri:string}|null Null when the file is missing or corrupt.
 */
function khse_read_stats_file( $path ) {
	$content = file_exists( $path ) ? file_get_contents( $path ) : '';
	$data    = json_decode( (string) $content, true );

	if ( ! is_array( $data ) ) {
		return null;
	}

	return array(
		'size' => isset( $data['size'] ) ? (int) $data['size'] : 0,
		'uri'  => isset( $data['uri'] ) ? (string) $data['uri'] : '',
	);
}

/**
 * Get the cache type list.
 *
 * @return array
 */

/**
 * Get the cache type list.
 *
 * @return array
 */
function khse_get_cache_type_list( $get_key = false ) {
	$archive_note     = ' <small class="khse-badge">' . __( 'Archive', 'kh-speed-engine' ) . '</small>';

	$list = array(
		'homepage' => __( 'Homepage', 'kh-speed-engine' ),
		'post'     => __( 'Post', 'kh-speed-engine' ),
		'page'     => __( 'Page', 'kh-speed-engine' ),
	);

	$custom_post_types = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects',
		'and'
	);

	foreach ( $custom_post_types as $post_type ) {
		$list[ $post_type->name ] = $post_type->labels->singular_name;
	}

	$list['category'] = __( 'Category', 'kh-speed-engine' ) . $archive_note;
	$list['tag']      = __( 'Tag', 'kh-speed-engine' ) . $archive_note;
	$list['date']     = __( 'Date', 'kh-speed-engine' ) . $archive_note;
	$list['author']   = __( 'Author', 'kh-speed-engine' ) . $archive_note;

	$custom_post_type_archives = get_post_types(
		array(
			'public'      => true,
			'has_archive' => true,
			'_builtin'    => false,
		),
		'objects',
		'and'
	);

	foreach ( $custom_post_type_archives as $post_type ) {
		$list[ 'archive_' . $post_type->name ] = sprintf(
			/* translators: %s is the post type singular label. */
			__( 'Archive for %s', 'kh-speed-engine' ),
			$post_type->labels->singular_name
		) . $archive_note;
	}

	$list['uncategorised'] = __( 'Uncategorised', 'kh-speed-engine' );

	if ( $get_key ) {
		return array_keys( $list );
	}

	return $list;
}

/**
 * Get newline-separated option as list.
 *
 * @param string $value Raw textarea value.
 *
 * @return array
 */
