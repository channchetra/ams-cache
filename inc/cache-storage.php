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
 * Get configuration data.
 *
 * @return array
 */
function khse_get_config_data() {
	$file = khse_get_config_path();

	if ( file_exists( $file ) ) {
		$content = @file_get_contents( $file );
		$config  = json_decode( (string) $content, true );

		if ( is_array( $config ) ) {
			return khse_normalize_config( $config );
		}

		error_log( '[KH Speed Engine] Ignoring unreadable runtime config: ' . $file );
	}

	$option_config = get_option( 'khse_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return khse_normalize_config( $option_config );
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
 * Get the cache stats root directory.
 *
 * @return string
 */
function khse_get_stats_root() {
	return khse_get_upload_dir() . '/stats';
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function khse_get_stats_dir( $cache_type = 'post' ) {
	return khse_get_stats_root() . '/' . $cache_type;
}

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
 * Get advanced driver settings from WordPress options.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function khse_get_driver_advanced_settings( $type ) {
	if ( 'file' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_file', array() );
	}

	if ( 'memcache' === $type || 'memcached' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_memcached', array() );
	}

	if ( 'redis' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_redis', array() );
	}

	if ( 'mongo' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_mongodb', array() );
	}

	return array();
}

/**
 * Get advanced driver connection type.
 *
 * @param string $type Driver type.
 *
 * @return string
 */
function khse_get_driver_connection_type( $type ) {
	if ( 'memcache' === $type || 'memcached' === $type ) {
		return get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' );
	}

	if ( 'redis' === $type ) {
		return get_option( 'khse_option_advanced_driver_redis_connection_type', 'tcp' );
	}

	if ( 'mongo' === $type ) {
		return get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' );
	}

	return 'tcp';
}

/**
 * Normalize driver settings before passing them to Simple Cache.
 *
 * @param array  $setting         Driver settings.
 * @param string $connection_type tcp|socket.
 *
 * @return array
 */
function khse_normalize_driver_settings( $setting, $connection_type = 'tcp' ) {
	foreach ( $setting as $k => $v ) {
		if ( is_string( $v ) ) {
			$v = trim( $v );
		}

		if ( '' === $v ) {
			unset( $setting[ $k ] );
			continue;
		}

		if ( is_numeric( $v ) ) {
			$v = (int) $v;
		}

		if ( 'compress' === $k ) {
			$v = in_array( $v, array( true, 1, '1', 'yes', 'on' ), true );
		}

		if ( 'compress_threshold' === $k ) {
			$v = max( 0, (int) $v );
		}

		if ( 'compress_level' === $k ) {
			$v = max( 1, min( 9, (int) $v ) );
		}

		$setting[ $k ] = $v;
	}

	if ( 'socket' !== $connection_type && isset( $setting['unix_socket'] ) ) {
		unset( $setting['unix_socket'] );
	}

	return $setting;
}

/**
 * Get the base connection settings for a cache driver.
 *
 * Pure defaults only; callers apply their own fallbacks and advanced settings.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function khse_get_driver_setting( $type ) {
	switch ( $type ) {
		case 'mysql':
			return array(
				'host'    => DB_HOST,
				'dbname'  => DB_NAME,
				'user'    => DB_USER,
				'pass'    => DB_PASSWORD,
				'charset' => DB_CHARSET,
			);

		case 'sqlite':
			return array(
				'storage' => khse_get_upload_dir() . '/sqlite_driver',
			);

		case 'redis':
			return array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'database' => 0,
			);

		case 'mongo':
			return array(
				'host' => '127.0.0.1',
				'port' => 27017,
			);

		case 'memcache':
		case 'memcached':
			return array(
				'host' => '127.0.0.1',
				'port' => 11211,
			);

		case 'apc':
		case 'apcu':
		case 'wincache':
			return array();

		case 'file':
		default:
			return array(
				'storage' => khse_get_upload_dir() . '/file_driver',
			);
	}
}

/**
 * Get the resolved driver settings and connection type.
 *
 * @param string      $type              Driver type.
 * @param array|null  $advanced_settings Optional advanced settings; defaults to the stored option.
 * @param string|null $connection_type   Optional connection type; defaults to the stored option.
 *
 * @return array
 */
function khse_get_driver_settings( $type, $advanced_settings = null, $connection_type = null ) {
	$setting = khse_get_driver_setting( $type );

	if ( null === $advanced_settings ) {
		$advanced_settings = khse_get_driver_advanced_settings( $type );
	}

	if ( null === $connection_type ) {
		$connection_type = khse_get_driver_connection_type( $type );
	}

	if ( ! empty( $advanced_settings ) ) {
		$setting = array_merge( $setting, khse_normalize_driver_settings( $advanced_settings, $connection_type ) );
	}

	return array(
		'setting'         => $setting,
		'connection_type' => $connection_type,
	);
}

/**
 * Build the runtime exclusion configuration from the stored settings.
 *
 * Persists the normalized path list to khse_option_excluded_list_filtered.
 *
 * @return array {
 *     @type bool  $enable               Whether exclusion rules are enabled.
 *     @type array $excluded_list        Normalized excluded paths.
 *     @type array $excluded_get_vars    Excluded GET variables.
 *     @type array $excluded_post_vars   Excluded POST variables.
 *     @type array $excluded_cookie_vars Excluded cookie variables.
 * }
 */
function khse_get_runtime_exclusion_config() {
	$list = array();

	foreach ( khse_get_lines_from_textarea( get_option( 'khse_option_excluded_list', '' ) ) as $item ) {
		$path = parse_url( $item, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path ) : '';

		if ( '' === $path ) {
			continue;
		}

		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		$list[] = '/' === $path ? '/' : untrailingslashit( $path );
	}

	$variables = array();

	foreach ( array( 'get', 'post', 'cookie' ) as $type ) {
		$variables[ $type ] = array();

		foreach ( khse_get_lines_from_textarea( get_option( 'khse_option_excluded_' . $type . '_vars', '' ) ) as $item ) {
			if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $item ) ) {
				$variables[ $type ][] = $item;
			}
		}
	}

	update_option( 'khse_option_excluded_list_filtered', implode( "\n", $list ) );

	return array(
		'enable'               => 'yes' === get_option( 'khse_option_exclusion_status', 'no' ),
		'excluded_list'        => $list,
		'excluded_get_vars'    => $variables['get'],
		'excluded_post_vars'   => $variables['post'],
		'excluded_cookie_vars' => $variables['cookie'],
	);
}
