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
 * Check whether an option is enabled across yes/no and enable/disable vocabularies.
 *
 * @param string $key     Option name, with or without the scm_option_ prefix.
 * @param string $default Default when the option is missing.
 *
 * @return bool
 */
function scm_option_is_enabled( $key, $default = 'no' ) {
	$option = 0 === strpos( $key, 'scm_' ) ? $key : 'scm_option_' . $key;
	$value  = get_option( $option, $default );

	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( is_int( $value ) ) {
		return 1 === $value;
	}

	return in_array( strtolower( (string) $value ), array( 'yes', 'enable', 'on', '1', 'true' ), true );
}

/**
 * Set an option using its stored vocabulary.
 *
 * @param string $key     Option name, with or without the scm_option_ prefix.
 * @param bool   $enabled Whether the option is enabled.
 * @param string $format  yes_no|enable_disable.
 *
 * @return void
 */
function scm_option_set_enabled( $key, $enabled, $format = 'yes_no' ) {
	$option = 0 === strpos( $key, 'scm_' ) ? $key : 'scm_option_' . $key;
	$value  = 'enable_disable' === $format
		? ( $enabled ? 'enable' : 'disable' )
		: ( $enabled ? 'yes' : 'no' );

	update_option( $option, $value );
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
 * Cookie prefixes that bypass the page cache.
 *
 * @return array
 */
function scm_get_cache_bypass_cookie_prefixes() {
	return array(
		'wordpress_logged_in_',
		'wordpress_sec_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
	);
}

/**
 * Check whether the current request carries a cache-bypass cookie.
 *
 * @return bool
 */
function scm_request_has_cache_bypass_cookie() {
	foreach ( array_keys( $_COOKIE ) as $name ) {
		foreach ( scm_get_cache_bypass_cookie_prefixes() as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Path segments/prefixes that bypass the page cache.
 *
 * @return array
 */
function scm_get_cache_bypass_paths() {
	return array( 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'wp-json', 'cart', 'checkout', 'my-account' );
}

/**
 * Return query variables that only carry external campaign attribution.
 *
 * @return array
 */
function scm_get_cache_tracking_query_vars() {
	return array(
		'fbclid',
		'mibextid',
		'rdid',
		'shbid',
		'shbv',
		'fb_action_ids',
		'fb_action_types',
		'fb_source',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	);
}

/**
 * Check whether a query variable is safe to ignore for page-cache identity.
 *
 * @param string $name Query variable name.
 *
 * @return bool
 */
function scm_is_cache_tracking_query_var( $name ) {
	$name = strtolower( trim( (string) $name ) );

	return in_array( $name, scm_get_cache_tracking_query_vars(), true );
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

	foreach ( scm_get_cache_bypass_paths() as $bypass_path ) {
		if ( preg_match( '#(?:^|/)' . preg_quote( $bypass_path, '#' ) . '(?:/|$)#i', $path ) ) {
			return false;
		}
	}

	if ( preg_match( '#\.(?:css|js|map|json|xml|txt|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|otf|mp4|webm|mp3|m4a|pdf|zip)$#i', $path ) ) {
		return false;
	}

	return true;
}

/**
 * Check if a full request URI can be treated as a cacheable HTML document request.
 *
 * @param string|null $request_uri Full request URI including the query string.
 *                                  Pass null to read $_SERVER['REQUEST_URI'].
 *
 * @return bool
 */
function scm_is_cacheable_request_uri( $request_uri = null ) {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( scm_request_has_cache_bypass_cookie() ) {
		return false;
	}

	if ( null === $request_uri ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	}

	$request_uri = (string) $request_uri;

	// A raw fragment is never valid in an HTTP request target. Reject it so
	// parse_url() cannot truncate the query relative to PHP's $_GET parsing.
	if ( false !== strpos( $request_uri, '#' ) ) {
		return false;
	}

	$parsed = parse_url( $request_uri );

	if ( false === $parsed ) {
		return false;
	}

	$query = isset( $parsed['query'] ) ? $parsed['query'] : '';

	if ( '' !== $query ) {
		$query_vars = array();
		parse_str( $query, $query_vars );

		foreach ( array_keys( $query_vars ) as $query_var ) {
			if ( ! scm_is_cache_tracking_query_var( $query_var ) ) {
				return false;
			}
		}
	}

	$path = scm_normalize_cache_uri( $request_uri );

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

/**
 * Get default configuation.
 *
 * This function is also used in expert mode.
 *
 * @return array
 */
function scm_get_default_config() {

	return array(
		'cache_driver'             => 'file',
		'cache_key_prefix'         => '',
		'cache_max_entries'        => 0,
		'nginx_direct_cache'       => false,
		'html_debug_comment'       => true,
		'driver_advanced_settings' => array(),
		'site_url'                 => '',
		'preload'                  => array(
			'enable'               => false,
			'limit'                => 50,
			'crawl_homepage_links' => true,
		),
		'woocommerce'              => array(
			'enable' => false,
		),

		'exclusion'                => array(
			'enable'             => false,
			'excluded_list'      => array(),
			'excluded_get_vars'  => array(),
			'excluded_post_vars' => array(),
			'excluded_cookie_vars' => array(),
		),
	);
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
