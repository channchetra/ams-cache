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

		if ( ! is_array( $stats ) || empty( $stats['uri'] ) || khse_normalize_cache_uri( $stats['uri'] ) !== $target_uri ) {
			continue;
		}

		if ( $driver ) {
			try {
				$driver->delete( $key );
			} catch ( \Throwable $e ) {
				error_log( '[KH Speed Engine] Could not delete cache entry ' . $key . ': ' . $e->getMessage() );
			}
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
	$stats_root = khse_get_stats_root();

	if ( empty( $key ) || ! is_dir( $stats_root ) || ! is_readable( $stats_root ) ) {
		return 0;
	}

	$removed = 0;

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || $file->getFilename() !== $key . '.json' ) {
			continue;
		}

		if ( @unlink( $file->getPathname() ) ) {
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
	$raw = trim( (string) $uri );

	if ( '' === $raw || false !== strpos( $raw, '#' ) || ! is_array( wp_parse_url( $raw ) ) ) {
		return array(
			'key'     => '',
			'uri'     => '',
			'removed' => 0,
		);
	}

	$uri = khse_normalize_cache_uri( $raw );

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
		try {
			if ( $driver->delete( $key ) ) {
				$removed++;
			}
		} catch ( \Throwable $e ) {
			error_log( '[KH Speed Engine] Could not delete cache entry ' . $key . ': ' . $e->getMessage() );
		}
	}

	$removed += khse_delete_cache_stats_by_key( $key );

	// Remove stale entries written under an older cache key prefix too.
	$stats_root = khse_get_stats_root();

	if ( is_dir( $stats_root ) && is_readable( $stats_root ) ) {
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
	if ( false === $url || null === $url ) {
		return false;
	}

	$url = trim( (string) $url );

	if ( '' === $url || false !== strpos( $url, '#' ) ) {
		return false;
	}

	$parts = wp_parse_url( $url );

	if ( ! is_array( $parts ) ) {
		return false;
	}

	$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';

	// Authority-form or malformed targets have no path to purge.
	if ( '' === $path ) {
		return false;
	}

	// A functional query such as ?p=123 is not cached, so purging its path
	// would remove an unrelated page such as the homepage.
	if ( '' !== $query && ! khse_is_cacheable_query_string( $query ) ) {
		return false;
	}

	return khse_purge_cache_uri( $path, $driver );
}

/**
 * Clear all cache.
 *
 * @param bool $sync Whether to refresh the Expert Mode runtime config first.
 *
 * @return int
 */
function khse_clear_all_cache( $sync = true ) {
	if ( khse_settings_batch_active() ) {
		return 0;
	}

	if ( $sync ) {
		khse_sync_expert_mode_runtime();
	}

	$driver_type = get_option( 'khse_option_driver' );
	$driver      = khse_driver_factory( $driver_type );

	if ( ! $driver || is_wp_error( $driver ) ) {
		return 0;
	}

	$list = khse_get_cache_type_list( true );

	if ( in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
		try {
			if ( ! $driver->clear() ) {
				error_log( '[KH Speed Engine] Driver clear failed for ' . $driver_type );
			}
		} catch ( \Throwable $e ) {
			error_log( '[KH Speed Engine] Driver clear failed for ' . $driver_type . ': ' . $e->getMessage() );
		}
	}

	$i = 0;

	foreach ( $list as $cache_type ) {
		$dir = khse_get_stats_dir( $cache_type );

		if ( is_dir( $dir ) && is_readable( $dir ) ) {
			foreach ( new DirectoryIterator( $dir ) as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'json' ) {
					$filename = $file->getFilename();
					$key      = strstr( $filename, '.', true );

					try {
						$driver->delete( $key );
					} catch ( \Throwable $e ) {
						error_log( '[KH Speed Engine] Could not delete cache entry ' . $key . ': ' . $e->getMessage() );
					}

					if ( @unlink( $file->getPathname() ) ) {
						$i++;
					}
				}
			}
		}
	}

	khse_clear_nginx_static_cache();
	do_action( 'khse_cache_cleared' );

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

	if ( is_dir( $dir ) && is_readable( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'json' ) {
				$filename = $file->getFilename();
				$key      = strstr( $filename, '.', true );

				try {
					$driver->delete( $key );
				} catch ( \Throwable $e ) {
					error_log( '[KH Speed Engine] Could not delete cache entry ' . $key . ': ' . $e->getMessage() );
				}

				if ( @unlink( $file->getPathname() ) ) {
					$rows++;
				}
			}
		}
	}

	khse_clear_nginx_static_cache();
	do_action( 'khse_cache_cleared' );

	return $rows;
}

/**
 * Build every cache URL affected by a post change.
 *
 * Invalidation is independent of preload settings: homepage, permalink,
 * post-type archive, author/date archives, and all public term archives are
 * always included so editing or deleting a post cannot leave stale pages.
 *
 * @param int          $post_id Post ID.
 * @param \WP_Post|null $post    Optional post object.
 *
 * @return array
 */
function khse_get_post_cache_invalidation_urls( $post_id, $post = null ) {
	$post = $post ? $post : get_post( $post_id );
	$urls = array( home_url( '/' ) );

	if ( ! $post ) {
		return $urls;
	}

	$add_url = function ( $url ) use ( &$urls ) {
		if ( ! empty( $url ) && ( ! function_exists( 'is_wp_error' ) || ! is_wp_error( $url ) ) ) {
			$urls[] = (string) $url;
		}
	};

	$add_url( get_permalink( $post_id ) );

	$post_type = get_post_type_object( $post->post_type );

	if ( $post_type && $post_type->public ) {
		$add_url( get_post_type_archive_link( $post->post_type ) );
		$add_url( get_author_posts_url( (int) $post->post_author ) );

		$timestamp = strtotime( $post->post_date );

		if ( $timestamp ) {
			$add_url( get_year_link( (int) gmdate( 'Y', $timestamp ) ) );
			$add_url( get_month_link( (int) gmdate( 'Y', $timestamp ), (int) gmdate( 'm', $timestamp ) ) );
		}

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( empty( $taxonomy->public ) && empty( $taxonomy->publicly_queryable ) ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy->name );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$add_url( get_term_link( $term ) );
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return apply_filters( 'khse_post_cache_invalidation_urls', $urls, $post_id, $post );
}

/**
 * Purge every cache URL affected by a post change.
 *
 * @param int            $post_id Post ID.
 * @param \WP_Post|null  $post    Optional post object.
 * @param \Shieldon\SimpleCache\Cache|WP_Error|null $driver Optional driver.
 *
 * @return int Number of URLs invalidated.
 */
function khse_purge_post_cache_urls( $post_id, $post = null, $driver = null ) {
	if ( 'enable' !== get_option( 'khse_option_caching_status', 'disable' ) ) {
		return 0;
	}

	if ( null === $driver ) {
		$driver = khse_driver_factory( get_option( 'khse_option_driver', 'file' ) );
	}

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	$urls     = khse_get_post_cache_invalidation_urls( $post_id, $post );
	$invalidated = 0;

	foreach ( $urls as $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) && '' !== $path ? $path : '/';

		$result = khse_purge_cache_uri( $path, $driver );

		if ( ! empty( $result['removed'] ) ) {
			$invalidated++;
		}
	}

	return $invalidated;
}
