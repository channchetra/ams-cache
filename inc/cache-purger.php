<?php
/**
 * AMS Cache — cache-purger.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_read_stats_file( $path ) {
	$content = file_exists( $path ) ? file_get_contents( $path ) : '';
	$data    = json_decode( $content, true );

	if ( is_array( $data ) ) {
		return array(
			'size' => isset( $data['size'] ) ? (int) $data['size'] : 0,
			'uri'  => isset( $data['uri'] ) ? (string) $data['uri'] : '',
		);
	}

	return array(
		'size' => (int) $content,
		'uri'  => '',
	);
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
function scm_delete_duplicate_stats_for_uri( $type, $uri, $current_key = '', $driver = null ) {
	$dir = scm_get_stats_dir( $type );

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$target_uri = scm_normalize_cache_uri( $uri );
	$removed    = 0;

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$key = strstr( $file->getFilename(), '.', true );

		if ( '' !== $current_key && $key === $current_key ) {
			continue;
		}

		$stats = scm_read_stats_file( $file->getPathname() );

		if ( empty( $stats['uri'] ) || scm_normalize_cache_uri( $stats['uri'] ) !== $target_uri ) {
			continue;
		}

		if ( $driver ) {
			$driver->delete( $key );
		}

		unlink( $file->getPathname() );
		$removed++;
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
function scm_delete_cache_stats_by_key( $key ) {
	$stats_root = scm_get_upload_dir() . '/stats';

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
function scm_purge_cache_uri( $uri, $driver = null ) {
	$uri = scm_normalize_cache_uri( $uri );

	if ( ! scm_is_cacheable_document_path( $uri ) ) {
		return array(
			'key'     => '',
			'uri'     => $uri,
			'removed' => 0,
		);
	}

	if ( null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	$key     = scm_get_cache_key( $uri );
	$removed = 0;

	if ( $driver ) {
		$driver->delete( $key );
		$removed++;
	}

	$removed += scm_delete_cache_stats_by_key( $key );

	if ( scm_delete_nginx_static_cache( $uri ) ) {
		$removed++;
	}

	return array(
		'key'     => $key,
		'uri'     => $uri,
		'removed' => $removed,
	);
}
