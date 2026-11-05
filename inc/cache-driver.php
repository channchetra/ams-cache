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

	$stats_root = khse_get_stats_root();

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

		if ( ! is_array( $stats ) ) {
			continue;
		}

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
			try {
				$driver->delete( $entries[ $i ]['key'] );
			} catch ( \Throwable $e ) {
				error_log( '[KH Speed Engine] Could not evict cache entry ' . $entries[ $i ]['key'] . ': ' . $e->getMessage() );
			}
		}

		if ( ! empty( $entries[ $i ]['uri'] ) ) {
			khse_delete_nginx_static_cache( $entries[ $i ]['uri'] );
		}

		// Only count rows whose stats file was actually removed.
		if ( file_exists( $entries[ $i ]['path'] ) && @unlink( $entries[ $i ]['path'] ) ) {
			$removed++;
		}
	}

	update_option( 'khse_last_cache_prune_count', $removed );

	return $removed;
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
