<?php
/**
 * KH Speed Engine — cache-nginx.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

function khse_is_nginx_direct_cache_enabled() {
	return 'yes' === get_option( 'khse_option_nginx_direct_cache_status', 'no' );
}

/**
 * Get Nginx direct cache relative directory.
 *
 * Derived from the same upload base as the absolute directory so the
 * generated snippet and the writer cannot diverge.
 *
 * @return string
 */
function khse_get_nginx_static_cache_relative_dir() {
	$base_path = wp_parse_url( content_url( '/uploads' ), PHP_URL_PATH );
	$base_path = is_string( $base_path ) && '' !== $base_path ? $base_path : '/wp-content/uploads';

	return trim( $base_path, '/' ) . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '/nginx';
}

/**
 * Get Nginx direct cache directory.
 *
 * @return string
 */
function khse_get_nginx_static_cache_dir() {
	return khse_get_upload_dir() . '/nginx';
}

/**
 * Convert request URI into a static cache file path.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function khse_get_nginx_static_cache_path( $uri ) {
	$path = parse_url( $uri, PHP_URL_PATH );

	if ( empty( $path ) ) {
		$path = '/';
	}

	$site_path = parse_url( home_url( '/' ), PHP_URL_PATH );

	if ( ! empty( $site_path ) && '/' !== $site_path && 0 === strpos( $path, $site_path ) ) {
		$path = substr( $path, strlen( rtrim( $site_path, '/' ) ) );
	}

	$path = '/' . trim( $path, '/' );

	if ( '/' === $path ) {
		return trailingslashit( khse_get_nginx_static_cache_dir() ) . 'index.html';
	}

	$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
	$safe     = array();

	foreach ( $segments as $segment ) {
		$segment = rawurldecode( $segment );

		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9._~%-]+$/', $segment ) ) {
			return '';
		}

		$safe[] = $segment;
	}

	if ( empty( $safe ) ) {
		return '';
	}

	return trailingslashit( khse_get_nginx_static_cache_dir() ) . implode( '/', $safe ) . '/index.html';
}

/**
 * Write static HTML mirror used by Nginx direct cache.
 *
 * @param string $uri     Request URI or path.
 * @param string $content Full HTML content.
 *
 * @return bool
 */
function khse_write_nginx_static_cache( $uri, $content ) {
	if (
		! khse_is_nginx_direct_cache_enabled() ||
		'file' !== get_option( 'khse_option_driver', 'file' ) ||
		empty( $content ) ||
		false === strpos( $content, '</body>' )
	) {
		return false;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( isset( $_SERVER['REQUEST_URI'] ) && ! khse_is_cacheable_request_uri( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$file = khse_get_nginx_static_cache_path( $uri );

	if ( empty( $file ) ) {
		return false;
	}

	$dir = dirname( $file );

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return false;
	}

	$result = @file_put_contents( $file, $content, LOCK_EX );

	if ( false === $result ) {
		error_log( '[KH Speed Engine] Could not write Nginx static cache file: ' . $file );
		return false;
	}

	chmod( $file, 0644 );

	return true;
}

/**
 * Delete one Nginx static cache file.
 *
 * @param string $uri Request URI or path.
 *
 * @return bool
 */
function khse_delete_nginx_static_cache( $uri ) {
	$file = khse_get_nginx_static_cache_path( $uri );

	if ( empty( $file ) || ! file_exists( $file ) ) {
		return false;
	}

	return @unlink( $file );
}

/**
 * Clear all Nginx direct static cache files.
 *
 * @return int
 */
function khse_clear_nginx_static_cache() {
	$dir = khse_get_nginx_static_cache_dir();

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$removed = 0;
	$files   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			@rmdir( $file->getPathname() );
			continue;
		}

		if ( @unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	@rmdir( $dir );

	return $removed;
}
