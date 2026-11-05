<?php
/**
 * KH Speed Engine — cache-purger.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Delete stale stats/cache rows that point to the same URI.
 *
 * @param string                         $type        Cache type.
 * @param string                         $uri         Request URI.
 * @param string                         $current_key Current cache key to keep.
 * @param \Shieldon\SimpleCache\Cache|null $driver    Active cache driver.
 *
 * @return int
 */
function khse_delete_duplicate_stats_for_uri( $type, $uri, $current_key = '', $driver = null ) {
	$dir = khse_get_stats_dir( $type );

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$target_uri = khse_normalize_cache_uri( $uri );
	$removed    = 0;

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$key = strstr( $file->getFilename(), '.', true );

		if ( '' !== $current_key && $key === $current_key ) {
			continue;
		}

		$stats = khse_read_stats_file( $file->getPathname() );

		if ( empty( $stats['uri'] ) || khse_normalize_cache_uri( $stats['uri'] ) !== $target_uri ) {
			continue;
		}

		if ( $driver ) {
			$driver->delete( $key );
		}

		if ( @unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	return $removed;
}

/**
 * Delete statistics rows by cache key.
 *
 * @param string $key Cache key.
 *
 * @return int
 */
function khse_delete_cache_stats_by_key( $key ) {
	$stats_root = khse_get_upload_dir() . '/stats';

	if ( empty( $key ) || ! is_dir( $stats_root ) ) {
		return 0;
	}

	$removed = 0;

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || $file->getFilename() !== $key . '.json' ) {
			continue;
		}

		if ( unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	return $removed;
}

/**
 * Purge cache for one normalized URI.
 *
 * @param string                         $uri    Request URI, URL, or path.
 * @param \Shieldon\SimpleCache\Cache|null $driver Active cache driver.
 *
 * @return array
 */
function khse_purge_cache_uri( $uri, $driver = null ) {
	$uri = khse_normalize_cache_uri( $uri );

	if ( ! khse_is_cacheable_document_path( $uri ) ) {
		return array(
			'key'     => '',
			'uri'     => $uri,
			'removed' => 0,
		);
	}

	if ( null === $driver ) {
		$driver = khse_driver_factory( get_option( 'khse_option_driver', 'file' ) );
	}

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	$key     = khse_get_cache_key( $uri );
	$removed = 0;

	if ( $driver ) {
		$driver->delete( $key );
		$removed++;
	}

	$removed += khse_delete_cache_stats_by_key( $key );

	// Remove stale entries written under an older cache key prefix too.
	$stats_root = khse_get_upload_dir() . '/stats';

	if ( is_dir( $stats_root ) ) {
		foreach ( new DirectoryIterator( $stats_root ) as $type_dir ) {
			if ( ! $type_dir->isDir() || $type_dir->isDot() ) {
				continue;
			}

			$removed += khse_delete_duplicate_stats_for_uri( $type_dir->getFilename(), $uri, $key, $driver );
		}
	}

	if ( khse_delete_nginx_static_cache( $uri ) ) {
		$removed++;
	}

	return array(
		'key'     => $key,
		'uri'     => $uri,
		'removed' => $removed,
	);
}

/**
 * Purge a permalink, aborting on a missing or unparseable URL.
 *
 * @param string|false|null $url    Permalink.
 * @param mixed             $driver Optional cache driver.
 *
 * @return array|false Purge result, or false when the URL is invalid.
 */
function khse_purge_permalink( $url, $driver = null ) {
	if ( false === $url || null === $url || '' === trim( (string) $url ) ) {
		return false;
	}

	$path = parse_url( (string) $url, PHP_URL_PATH );

	if ( false === $path ) {
		return false;
	}

	return khse_purge_cache_uri( $path, $driver );
}

/**
 * Clear all cache.
 *
 * @return int
 */
function khse_clear_all_cache() {
	khse_sync_expert_mode_runtime();

	$driver_type = get_option( 'khse_option_driver' );
	$driver      = khse_driver_factory( $driver_type );

	if ( ! $driver || is_wp_error( $driver ) ) {
		return 0;
	}

	$list = khse_get_cache_type_list( true );

	if ( in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
		$driver->clear();
	}

	$i = 0;

	foreach ( $list as $cache_type ) {
		$dir = khse_get_stats_dir( $cache_type );

		if ( is_dir( $dir ) ) {
			foreach ( new DirectoryIterator( $dir ) as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'json' ) {
					$filename = $file->getFilename();
					$key      = strstr( $filename, '.', true );

					$driver->delete( $key );

					if ( @unlink( $file->getPathname() ) ) {
						$i++;
					}
				}
			}
		}
	}

	khse_clear_nginx_static_cache();
	khse_schedule_preload_cache();

	return $i;
}

/**
 * Clear cache rows for one cache type.
 *
 * @param string $cache_type Cache type.
 *
 * @return int
 */
function khse_clear_cache_type( $cache_type ) {
	$driver = khse_driver_factory( get_option( 'khse_option_driver' ) );
	$list   = khse_get_cache_type_list( true );

	if ( ! $driver || is_wp_error( $driver ) || ! in_array( $cache_type, $list, true ) ) {
		return 0;
	}

	$rows = 0;
	$dir  = khse_get_stats_dir( $cache_type );

	if ( is_dir( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'json' ) {
				$filename = $file->getFilename();
				$key      = strstr( $filename, '.', true );

				$driver->delete( $key );

				if ( @unlink( $file->getPathname() ) ) {
					$rows++;
				}
			}
		}
	}

	khse_clear_nginx_static_cache();
	khse_schedule_preload_cache();

	return $rows;
}
