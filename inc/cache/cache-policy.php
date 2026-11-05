<?php
/**
 * KH Speed Engine — cache policy.
 *
 * Request cacheability, bypass rules, cache keys, and the read path.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Find the first exclusion path matching a normalized request path.
 *
 * Matching is boundary-aware: `/news` matches `/news/2026/` but never
 * `/newsletter/`. Both the runtime and the admin URL tester use this helper.
 *
 * @param string $path          Normalized request path.
 * @param array  $excluded_list Exclusion entries.
 *
 * @return string Matched normalized entry, or '' when nothing matches.
 */
function khse_find_excluded_path( $path, $excluded_list ) {
	$path = khse_normalize_cache_uri( $path );

	foreach ( (array) $excluded_list as $entry ) {
		$entry = is_string( $entry ) ? trim( $entry ) : '';

		if ( '' === $entry ) {
			continue;
		}

		$normalized = khse_normalize_cache_uri( $entry );

		if ( '/' === $normalized || 0 === strpos( $path, $normalized ) ) {
			return $normalized;
		}
	}

	return '';
}

/**
 * Build the runtime config used by the cache reader and writer.
 *
 * @return array
 */
function khse_get_runtime_config() {
	$config = array_merge(
		khse_get_default_config(),
		khse_get_config_data(),
		khse_build_runtime_config()
	);

	$config['cache_enabled']      = 'enable' === get_option( 'khse_option_caching_status', 'disable' );
	$config['cache_key_prefix']   = khse_get_cache_key_prefix();
	$config['html_debug_comment'] = khse_option_is_enabled( 'khse_option_html_debug_comment', 'yes' );

	return khse_normalize_config( $config );
}

/**
 * Check whether the current request is excluded from the page cache.
 *
 * @param array $config Runtime config.
 *
 * @return bool
 */
function khse_is_cache_request_excluded( $config ) {
	$exclusion = isset( $config['exclusion'] ) && is_array( $config['exclusion'] ) ? $config['exclusion'] : array();

	if ( ! empty( $exclusion['enable'] ) ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? khse_normalize_cache_uri( $_SERVER['REQUEST_URI'] ) : '/';

		if ( '' !== khse_find_excluded_path( $path, isset( $exclusion['excluded_list'] ) ? $exclusion['excluded_list'] : array() ) ) {
			return true;
		}

		foreach ( array( 'get', 'post', 'cookie' ) as $type ) {
			$key    = 'excluded_' . $type . '_vars';
			$source = 'get' === $type ? $_GET : ( 'post' === $type ? $_POST : $_COOKIE );

			foreach ( isset( $exclusion[ $key ] ) ? $exclusion[ $key ] : array() as $var ) {
				if ( isset( $source[ $var ] ) ) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * Build the debug block appended to a cached response.
 *
 * @param array      $config     Runtime config.
 * @param bool       $expert     Whether Expert Mode served the response.
 * @param float|null $start_time Request start time for pre-WP contexts.
 *
 * @return string
 */
function khse_build_served_debug_message( $config, $expert = false, $start_time = null ) {
	$translate = function ( $text ) {
		return function_exists( '__' ) ? __( $text, 'kh-speed-engine' ) : $text;
	};

	$date         = date( 'Y-m-d H:i:s' );
	$memory_usage = round( memory_get_usage() / ( 1024 * 1024 ), 4 );
	$sql_queries  = function_exists( 'get_num_queries' ) ? get_num_queries() : 0;
	$page_speed   = function_exists( 'timer_stop' ) ? timer_stop() : ( null !== $start_time ? round( microtime( true ) - $start_time, 3 ) : 0 );

	khse_set_view_variable( 'now', $date, 'after' );
	khse_set_view_variable( 'memory_usage', $memory_usage, 'after' );
	khse_set_view_variable( 'sql_queries', $sql_queries, 'after' );
	khse_set_view_variable( 'page_generation_time', $page_speed, 'after' );

	if ( empty( $config['html_debug_comment'] ) ) {
		return '';
	}

	$message  = "\n....... " . $translate( 'After' ) . " .......\n\n";
	$message .= sprintf( $translate( 'Now: %s' ), $date ) . "\n";
	$message .= sprintf( $translate( 'Memory usage: %s MB' ), $memory_usage ) . "\n";
	$message .= sprintf( $translate( 'SQL queries: %s' ), $sql_queries ) . "\n";
	$message .= sprintf( $translate( 'Page generated in %s seconds.' ), $page_speed ) . "\n";

	if ( $expert ) {
		$message .= "\n" . $translate( '(running in Expert Mode)' ) . "\n";
	}

	$message .= "\n//-->\n";

	return $message;
}

/**
 * Whether a string is a complete, cacheable HTML payload.
 *
 * @param string $content Payload.
 * @param int    $minimum Minimum bytes.
 *
 * @return bool
 */
function khse_is_valid_cache_payload( $content, $minimum = 1024 ) {
	return ! empty( $content ) && false !== strpos( $content, '</body>' ) && strlen( $content ) >= (int) $minimum;
}

/**
 * Log a cache read failure once per request without leaking credentials.
 *
 * @param string     $driver_type Cache driver key.
 * @param \Throwable $error       Caught exception.
 *
 * @return void
 */
function khse_log_cache_read_failure( $driver_type, $error ) {
	static $logged = false;

	if ( $logged || ! function_exists( 'error_log' ) ) {
		return;
	}

	$logged = true;
	$label   = function_exists( 'sanitize_key' )
		? sanitize_key( (string) $driver_type )
		: preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $driver_type ) );

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log(
		sprintf(
			'[KH Speed Engine] Cache read failed (%s): %s',
			$label,
			$error->getMessage()
		)
	);
}

/**
 * Serve a cached response for the current request.
 *
 * @param array                            $config       Runtime config.
 * @param \Shieldon\SimpleCache\Cache|null $driver       Existing driver, or null to create one.
 * @param string                           $storage_base Optional file/sqlite storage base.
 * @param bool                             $expert       Whether this is the pre-WP Expert Mode path.
 * @param float|null                       $start_time   Request start time for pre-WP metrics.
 *
 * @return string|null Cached HTML, or null when no valid cache entry should be served.
 */
function khse_serve_cached_response( $config, $driver = null, $storage_base = '', $expert = false, $start_time = null ) {
	if ( isset( $config['cache_enabled'] ) && ! $config['cache_enabled'] ) {
		return null;
	}

	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return null;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

	if ( ! khse_is_cacheable_request_uri( $request_uri ) ) {
		return null;
	}

	if ( khse_is_cache_request_excluded( $config ) ) {
		return null;
	}

	$path = khse_normalize_cache_uri( $request_uri );
	$key  = khse_build_cache_key( isset( $config['cache_key_prefix'] ) ? $config['cache_key_prefix'] : '', $path );

	$driver_type = isset( $config['cache_driver'] ) ? $config['cache_driver'] : 'file';

	if ( ! $driver ) {
		$advanced    = isset( $config['driver_advanced_settings'] ) ? $config['driver_advanced_settings'] : array();
		$connection  = isset( $config['driver_connection_type'] ) ? $config['driver_connection_type'] : 'tcp';

		if ( '' !== $storage_base && in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
			// Expert Mode runs before WP_CONTENT_DIR exists; build storage directly.
			$setting = array(
				'storage' => rtrim( $storage_base, '/\\' ) . '/' . $driver_type . '_driver',
			);

			if ( ! empty( $advanced ) ) {
				$setting = array_merge( $setting, khse_normalize_driver_settings( $advanced, $connection ) );
			}
		} else {
			$resolved = khse_get_driver_settings( $driver_type, $advanced, $connection );
			$setting  = $resolved['setting'];
		}

		try {
			$driver = new \Shieldon\SimpleCache\Cache( $driver_type, $setting );
		} catch ( \Throwable $e ) {
			khse_log_cache_read_failure( $driver_type, $e );
			return null;
		}
	}

	try {
		$cached_content = $driver->get( $key );
	} catch ( \Throwable $e ) {
		khse_log_cache_read_failure( $driver_type, $e );
		return null;
	}

	if ( ! khse_is_valid_cache_payload( $cached_content ) ) {
		return null;
	}

	$cached_content .= khse_build_served_debug_message( $config, $expert, $start_time );
	$cached_content  = str_replace( '</body>', "\n" . khse_javascript() . "\n" . '</body>', $cached_content );

	return $cached_content;
}

/**
 * Normalize a request URI before using it as a cache identity.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return string
 */
function khse_normalize_cache_uri( $uri ) {
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
function khse_get_cache_bypass_cookie_prefixes() {
	return array(
		'wordpress_logged_in_',
		'wordpress_sec_',
		'wp-postpass_',
		'comment_author_',
	);
}

/**
 * Check whether the current request carries a cache-bypass cookie.
 *
 * @return bool
 */
function khse_request_has_cache_bypass_cookie() {
	foreach ( array_keys( $_COOKIE ) as $name ) {
		foreach ( khse_get_cache_bypass_cookie_prefixes() as $prefix ) {
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
function khse_get_cache_bypass_paths() {
	return array( 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'wp-json', 'cart', 'checkout', 'my-account' );
}

/**
 * Return query variables that only carry external campaign attribution.
 *
 * @return array
 */
function khse_get_cache_tracking_query_vars() {
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
function khse_is_cache_tracking_query_var( $name ) {
	$name = strtolower( trim( (string) $name ) );

	return in_array( $name, khse_get_cache_tracking_query_vars(), true );
}

/**
 * Check whether a query string can share a page-cache identity.
 *
 * Only tracking variables are ignored by the cache key; any other query
 * variable changes the response and must not be treated as the same page.
 *
 * @param string $query Query string without the leading "?".
 *
 * @return bool
 */
function khse_is_cacheable_query_string( $query ) {
	if ( '' === (string) $query ) {
		return true;
	}

	$query_vars = array();
	parse_str( (string) $query, $query_vars );

	foreach ( array_keys( $query_vars ) as $query_var ) {
		if ( ! khse_is_cache_tracking_query_var( $query_var ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Check if a path looks like a frontend HTML document.
 *
 * @param string $path Request path.
 *
 * @return bool
 */
function khse_is_cacheable_document_path( $path ) {
	$path = khse_normalize_cache_uri( $path );

	if ( '' !== khse_find_bypass_path( $path ) ) {
		return false;
	}

	if ( preg_match( '#\.(?:css|js|map|json|xml|txt|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|otf|mp4|webm|mp3|m4a|pdf|zip)$#i', $path ) ) {
		return false;
	}

	return true;
}

/**
 * Find the first bypass path matching a normalized request path.
 *
 * Bypass entries match as path segments anywhere in the request path, so
 * `/blog/wp-admin/` is rejected just like `/wp-admin/`.
 *
 * @param string $path Normalized request path.
 *
 * @return string Matched bypass entry, or '' when nothing matches.
 */
function khse_find_bypass_path( $path ) {
	$path = khse_normalize_cache_uri( $path );

	foreach ( khse_get_cache_bypass_paths() as $bypass_path ) {
		if ( preg_match( '#(?:^|/)' . preg_quote( (string) $bypass_path, '#' ) . '(?:/|$)#i', $path ) ) {
			return (string) $bypass_path;
		}
	}

	return '';
}

/**
 * Check if a full request URI can be treated as a cacheable HTML document request.
 *
 * @param string|null $request_uri Full request URI including the query string.
 *                                  Pass null to read $_SERVER['REQUEST_URI'].
 *
 * @return bool
 */
function khse_is_cacheable_request_uri( $request_uri = null ) {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( khse_request_has_cache_bypass_cookie() ) {
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

	if ( ! khse_is_cacheable_query_string( $query ) ) {
		return false;
	}

	$path = khse_normalize_cache_uri( $request_uri );

	return khse_is_cacheable_document_path( $path );
}

/**
 * Check if URI is the canonical homepage path.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return bool
 */
function khse_is_homepage_uri( $uri ) {
	$home_path = '/';

	if ( function_exists( 'home_url' ) ) {
		$home_path = khse_normalize_cache_uri( home_url( '/' ) );
	}

	return khse_normalize_cache_uri( $uri ) === $home_path;
}

/**
 * Build a cache key from an explicit prefix and URI.
 *
 * @param string $prefix Cache key prefix.
 * @param string $uri    Request URI or path.
 *
 * @return string
 */
function khse_build_cache_key( $prefix, $uri ) {
	return md5( (string) $prefix . '|' . khse_normalize_cache_uri( $uri ) );
}

/**
 * Build the cache key for a request URI using the stored prefix.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function khse_get_cache_key( $uri ) {
	return khse_build_cache_key( khse_get_cache_key_prefix(), $uri );
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
function khse_build_runtime_exclusion_config() {
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

	return array(
		'enable'               => 'yes' === get_option( 'khse_option_exclusion_status', 'no' ),
		'excluded_list'        => $list,
		'excluded_get_vars'    => $variables['get'],
		'excluded_post_vars'   => $variables['post'],
		'excluded_cookie_vars' => $variables['cookie'],
	);
}
