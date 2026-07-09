<?php
/**
 * AMS Cache — cache-nginx.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_is_nginx_direct_cache_enabled() {
	return 'yes' === get_option( 'scm_option_nginx_direct_cache_status', 'no' );
}

/**
 * Get Nginx direct cache relative directory.
 *
 * @return string
 */
function scm_get_nginx_static_cache_relative_dir() {
	return 'wp-content/uploads/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '/nginx';
}

/**
 * Get Nginx direct cache directory.
 *
 * @return string
 */
function scm_get_nginx_static_cache_dir() {
	return WP_CONTENT_DIR . '/uploads/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '/nginx';
}

/**
 * Convert request URI into a static cache file path.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function scm_get_nginx_static_cache_path( $uri ) {
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
		return trailingslashit( scm_get_nginx_static_cache_dir() ) . 'index.html';
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

	return trailingslashit( scm_get_nginx_static_cache_dir() ) . implode( '/', $safe ) . '/index.html';
}

/**
 * Write static HTML mirror used by Nginx direct cache.
 *
 * @param string $uri     Request URI or path.
 * @param string $content Full HTML content.
 *
 * @return bool
 */
function scm_write_nginx_static_cache( $uri, $content ) {
	if (
		! scm_is_nginx_direct_cache_enabled() ||
		'file' !== get_option( 'scm_option_driver', 'file' ) ||
		empty( $content ) ||
		false === strpos( $content, '</body>' )
	) {
		return false;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		return false;
	}

	$file = scm_get_nginx_static_cache_path( $uri );

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

	$result = file_put_contents( $file, $content, LOCK_EX );

	if ( false === $result ) {
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
function scm_delete_nginx_static_cache( $uri ) {
	$file = scm_get_nginx_static_cache_path( $uri );

	if ( empty( $file ) || ! file_exists( $file ) ) {
		return false;
	}

	return unlink( $file );
}

/**
 * Clear all Nginx direct static cache files.
 *
 * @return int
 */
function scm_clear_nginx_static_cache() {
	$dir = scm_get_nginx_static_cache_dir();

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
			rmdir( $file->getPathname() );
			continue;
		}

		if ( unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	rmdir( $dir );

	return $removed;
}
