<?php
/**
 * AMS Cache — cache-core.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_load_textdomain() {
	load_plugin_textdomain( SCM_PLUGIN_TEXT_DOMAIN, false, SCM_PLUGIN_LANGUAGE_PACK );
}

/**
 * Get driver hash.
 *
 * @return string
 */
function scm_get_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( empty( $hash ) ) {
		return scm_set_dir_hash();
	}
	return $hash;
}

/**
 * Check driver hash exists or not.
 *
 * @return bool
 */
function scm_is_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( empty( $hash ) ) {
		return false;
	}
	return true;
}

/**
 * Set driver hash.
 *
 * @return string
 */
function scm_set_dir_hash() {
	$scm_dir_hash = wp_hash( gmdate( 'ymdhis' ) . wp_rand( 1, 86400 ) );
	$scm_dir_hash = substr( $scm_dir_hash, 0, 8 );

	update_option( 'scm_dir_hash', $scm_dir_hash );

	return $scm_dir_hash;
}

/**
 * Get upload dir.
 *
 * @return string
 */
function scm_get_upload_dir() {
	$base_dir   = WP_CONTENT_DIR . '/uploads/ams-cache';
	$legacy_dir = WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master';

	if ( ! is_dir( $base_dir ) && is_dir( $legacy_dir ) ) {
		@rename( $legacy_dir, $base_dir );
	}

	return $base_dir . '/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
}

/**
 * Get a private runtime directory for config, locks, and temporary workspaces.
 *
 * Never place control files below the public WordPress document root. Hosts may
 * not run Apache, so filesystem permissions alone are not a sufficient guard.
 *
 * @return string
 */
function scm_get_private_runtime_dir() {
	$candidates = array();

	if ( defined( 'WP_TEMP_DIR' ) && WP_TEMP_DIR ) {
		$candidates[] = WP_TEMP_DIR;
	}

	if ( function_exists( 'sys_get_temp_dir' ) ) {
		$candidates[] = sys_get_temp_dir();
	}

	if ( defined( 'ABSPATH' ) ) {
		$candidates[] = dirname( ABSPATH ) . '/ams-cache-runtime';
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
			return $candidate . '/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
		}
	}

	return rtrim( sys_get_temp_dir(), '/\\' ) . '/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
}

/**
 * Get the private Expert Mode lock path.
 *
 * @return string
 */
function scm_get_expert_lock_path() {
	return scm_get_private_runtime_dir() . '/expert.lock';
}

/**
 * Get configuration file's path.
 *
 * @return string
 */
function scm_get_config_path() {
	return scm_get_private_runtime_dir() . '/config.json';
}

/**
 * Get configuration data.
 *
 * @return array
 */
function scm_get_config_data() {
	$file = scm_get_config_path();

	if ( file_exists( $file ) ) {
		$content = file_get_contents( $file );
		$config = json_decode( $content, true );

		if ( is_array( $config ) ) {
			return $config;
		}
	}

	$option_config = get_option( 'scm_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return $option_config;
	}

	return scm_get_default_config();
}

/**
 * Set channel Id.
 *
 * @return void
 */
function scm_set_blog_id() {
	update_option( 'scm_blog_id', get_current_blog_id() );
}

/**
 * Get channel Id.
 *
 * @return string
 */
function scm_get_blog_id() {
	return get_option( 'scm_blog_id', 1 );
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function scm_get_stats_dir( $cache_type = 'post' ) {
	return scm_get_upload_dir() . '/stats/' . $cache_type;
}

/**
 * Get the cache key prefix used to isolate shared cache stores.
 *
 * @return string
 */
function scm_get_cache_key_prefix() {
	$prefix = get_option( 'scm_option_cache_key_prefix', '' );

	if ( '' === $prefix ) {
		$prefix = 'scm_' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '_';
	}

	$prefix = preg_replace( '/[^A-Za-z0-9_.-]/', '_', $prefix );

	if ( '' === $prefix ) {
		$prefix = 'scm_' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '_';
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
function scm_normalize_cache_uri( $uri ) {
	$path = parse_url( (string) $uri, PHP_URL_PATH );

	if ( empty( $path ) ) {
		$path = '/';
	}

	$path = '/' . ltrim( $path, '/' );
	$path = preg_replace( '#/+#', '/', $path );

	if ( '/' !== $path ) {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		if ( '' === $extension && '/' !== substr( $path, -1 ) ) {
			$path .= '/';
		}
	}

	return $path;
}

/**
 * Check whether current request carries WordPress auth cookies.
 *
 * @return bool
 */
function scm_request_has_auth_cookie() {
	foreach ( $_COOKIE as $name => $value ) {
		if (
			0 === strpos( $name, 'wordpress_logged_in_' ) ||
			0 === strpos( $name, 'wordpress_sec_' ) ||
			0 === strpos( $name, 'wp-postpass_' )
		) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a path looks like a frontend HTML document.
 *
 * @param string $path Request path.
 *
 * @return bool
 */
function scm_is_cacheable_document_path( $path ) {
	$path = scm_normalize_cache_uri( $path );

	if ( preg_match( '#/(?:wp-admin|wp-json)(?:/|$)|/(?:wp-login\.php|xmlrpc\.php)$#i', $path ) ) {
		return false;
	}

	if ( preg_match( '#\.(?:css|js|map|json|xml|txt|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|otf|mp4|webm|mp3|m4a|pdf|zip)$#i', $path ) ) {
		return false;
	}

	return true;
}

/**
 * Check if a URI can be treated as a cacheable HTML document request.
 *
 * @param string|null $uri Request URI.
 *
 * @return bool
 */
function scm_is_cacheable_request_uri( $uri = null ) {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		return false;
	}

	if ( scm_request_has_auth_cookie() ) {
		return false;
	}

	if ( null === $uri ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	}

	$path = scm_normalize_cache_uri( $uri );

	return scm_is_cacheable_document_path( $path );
}

/**
 * Check if URI is the canonical homepage path.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return bool
 */
function scm_is_homepage_uri( $uri ) {
	$home_path = '/';

	if ( function_exists( 'home_url' ) ) {
		$home_path = scm_normalize_cache_uri( home_url( '/' ) );
	}

	return scm_normalize_cache_uri( $uri ) === $home_path;
}

/**
 * Build the cache key for a request URI.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function scm_get_cache_key( $uri ) {
	return md5( scm_get_cache_key_prefix() . '|' . scm_normalize_cache_uri( $uri ) );
}
