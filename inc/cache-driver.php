<?php
/**
 * KH Speed Engine — cache-driver.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Get the maximum number of cached entries.
 *
 * A value of 0 disables pruning.
 *
 * @return int
 */
function khse_get_cache_max_entries() {
	$limit = (int) get_option( 'khse_option_cache_max_entries', 0 );

	return max( 0, $limit );
}

/**
 * Prune oldest known cache entries when the cache index grows too large.
 *
 * This only deletes entries recorded in the statistics index, so shared Redis or
 * Memcached stores are not flushed globally.
 *
 * @param \Shieldon\SimpleCache\Cache|null $driver Active cache driver.
 *
 * @return int Removed entry count.
 */
function khse_enforce_cache_entry_limit( $driver = null ) {
	$limit = khse_get_cache_max_entries();

	if ( $limit <= 0 ) {
		return 0;
	}

	$stats_root = khse_get_upload_dir() . '/stats';

	if ( ! is_dir( $stats_root ) ) {
		return 0;
	}

	$entries = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$filename = $file->getFilename();
		$key      = strstr( $filename, '.', true );

		if ( false === $key || '' === $key ) {
			continue;
		}

		$stats = khse_read_stats_file( $file->getPathname() );

		$entries[] = array(
			'key'  => $key,
			'path' => $file->getPathname(),
			'time' => $file->getMTime(),
			'uri'  => $stats['uri'],
		);
	}

	if ( count( $entries ) <= $limit ) {
		return 0;
	}

	usort(
		$entries,
		function ( $a, $b ) {
			return $a['time'] - $b['time'];
		}
	);

	if ( null === $driver ) {
		$driver = khse_driver_factory( get_option( 'khse_option_driver', 'file' ) );
	}

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	$remove_count = count( $entries ) - $limit;
	$removed      = 0;

	for ( $i = 0; $i < $remove_count; $i++ ) {
		if ( $driver ) {
			$driver->delete( $entries[ $i ]['key'] );
		}

		if ( ! empty( $entries[ $i ]['uri'] ) ) {
			khse_delete_nginx_static_cache( $entries[ $i ]['uri'] );
		}

		if ( file_exists( $entries[ $i ]['path'] ) ) {
			unlink( $entries[ $i ]['path'] );
		}

		$removed++;
	}

	update_option( 'khse_last_cache_prune_count', $removed );

	return $removed;
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
 * Get the cache driver instance.
 *
 * @param string $type The type of the driver.
 *
 * @return \Shieldon\SimpleCache\Cache|\WP_Error
 */
function khse_driver_factory( $type ) {

	$known_types = array( 'mysql', 'sqlite', 'redis', 'mongo', 'memcache', 'memcached', 'apc', 'apcu', 'wincache', 'file' );

	if ( ! in_array( $type, $known_types, true ) ) {
		$type = 'file';
	}

	$resolved = khse_get_driver_settings( $type );
	$setting  = $resolved['setting'];

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );

	} catch ( \Throwable $e ) {

		if ( in_array( $type, array( 'file', 'sqlite' ), true ) && ! file_exists( $setting['storage'] ) ) {
			wp_mkdir_p( $setting['storage'] );

			// Let's try again.
			try {
				$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
			} catch ( \Throwable $exception ) {
				return new \WP_Error( 'khse_driver_unavailable', $exception->getMessage() );
			}
		} else {

			error_log( sprintf( '[KH Speed Engine] Driver %s is not supported, fallback to use File driver.', $type ) );

			try {
				$driver = new \Shieldon\SimpleCache\Cache(
					'file',
					array(
						'storage' => khse_get_upload_dir() . '/file_driver',
					)
				);
			} catch ( \Throwable $exception ) {
				return new \WP_Error( 'khse_driver_unavailable', $exception->getMessage() );
			}
		}
	}

	return $driver;
}

/**
 * Check whether a cache driver's PHP extension is available.
 *
 * This is runtime policy, not dashboard presentation, so admin and the URL
 * evaluator can share it without a reverse package dependency.
 *
 * @param string $driver Driver key.
 *
 * @return bool
 */
function khse_is_driver_available( $driver ) {
	switch ( sanitize_key( (string) $driver ) ) {
		case 'file':
			return true;
		case 'redis':
			return extension_loaded( 'redis' );
		case 'memcache':
			return extension_loaded( 'memcache' );
		case 'memcached':
			return extension_loaded( 'memcached' );
		case 'apc':
			return extension_loaded( 'apc' );
		case 'apcu':
			return extension_loaded( 'apcu' );
		case 'wincache':
			return extension_loaded( 'wincache' );
		case 'mongo':
			return extension_loaded( 'mongodb' );
		case 'mysql':
			return extension_loaded( 'mysqli' ) || extension_loaded( 'pdo_mysql' );
		case 'sqlite':
			return extension_loaded( 'sqlite3' ) || extension_loaded( 'pdo_sqlite' );
		default:
			return false;
	}
}
