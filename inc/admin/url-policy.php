<?php
/**
 * KH Speed Engine — URL cache policy evaluator.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Build one policy check entry.
 *
 * @param string $key    Check key.
 * @param string $label  Human readable label.
 * @param string $status pass, fail, or note.
 * @param string $detail Explanation for the status.
 *
 * @return array
 */
function khse_cache_policy_check( $key, $label, $status, $detail ) {
	return array(
		'key'    => $key,
		'label'  => $label,
		'status' => $status,
		'detail' => $detail,
	);
}

/**
 * Build an unresolved policy result.
 *
 * @param string $detail Failure reason.
 *
 * @return array
 */
function khse_cache_policy_invalid_resolution( $detail ) {
	return array(
		'valid'  => false,
		'url'    => '',
		'path'   => '',
		'query'  => '',
		'detail' => $detail,
	);
}

/**
 * Resolve parsed URL parts into a same-site absolute URL.
 *
 * @param string $input Raw input.
 * @param array  $parts Parsed input parts.
 * @param array  $home  Parsed home URL parts.
 *
 * @return array
 */
function khse_cache_policy_resolve_parts( $input, $parts, $home ) {
	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
	$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';

	if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return khse_cache_policy_invalid_resolution( __( 'Only HTTP and HTTPS URLs can be tested.', 'kh-speed-engine' ) );
	}

	if ( '' === $host ) {
		if ( '' !== $scheme ) {
			return khse_cache_policy_invalid_resolution( __( 'This URL could not be parsed.', 'kh-speed-engine' ) );
		}

		return array(
			'valid'  => true,
			'url'    => home_url( '/' ) . ltrim( $input, '/' ),
			'detail' => '',
		);
	}

	if ( strtolower( $host ) !== strtolower( (string) $home['host'] ) ) {
		return khse_cache_policy_invalid_resolution( __( 'This URL points to an external host.', 'kh-speed-engine' ) );
	}

	return array(
		'valid'  => true,
		'url'    => ( '' === $scheme && isset( $home['scheme'] ) ? $home['scheme'] . ':' : '' ) . $input,
		'detail' => '',
	);
}

/**
 * Resolve a relative path or same-site URL against the site home URL.
 *
 * @param string $input Raw URL or path.
 *
 * @return array {
 *     @type bool   $valid  Whether the input resolved to a same-site URL.
 *     @type string $url    Resolved URL.
 *     @type string $path   Resolved path.
 *     @type string $query  Resolved query string.
 *     @type string $detail Failure reason when not valid.
 * }
 */
function khse_resolve_cache_policy_url( $input ) {
	$home = wp_parse_url( home_url( '/' ) );

	if ( ! is_array( $home ) || empty( $home['host'] ) ) {
		return khse_cache_policy_invalid_resolution( __( 'The site URL could not be resolved.', 'kh-speed-engine' ) );
	}

	$parts = wp_parse_url( $input );

	if ( ! is_array( $parts ) ) {
		return khse_cache_policy_invalid_resolution( __( 'This URL could not be parsed.', 'kh-speed-engine' ) );
	}

	$resolved = khse_cache_policy_resolve_parts( $input, $parts, $home );

	if ( empty( $resolved['valid'] ) ) {
		return $resolved;
	}

	$resolved_parts = wp_parse_url( $resolved['url'] );

	if ( ! is_array( $resolved_parts ) ) {
		return khse_cache_policy_invalid_resolution( __( 'This URL could not be parsed.', 'kh-speed-engine' ) );
	}

	return array(
		'valid'  => true,
		'url'    => esc_url_raw( $resolved['url'] ),
		'path'   => isset( $resolved_parts['path'] ) ? (string) $resolved_parts['path'] : '/',
		'query'  => isset( $resolved_parts['query'] ) ? (string) $resolved_parts['query'] : '',
		'detail' => '',
	);
}

/**
 * Request check for a resolved URL.
 *
 * @return array
 */
function khse_cache_policy_request_check() {
	return khse_cache_policy_check(
		'request',
		__( 'Request', 'kh-speed-engine' ),
		'pass',
		__( 'URL parsed successfully.', 'kh-speed-engine' )
	);
}

/**
 * Page caching enabled check.
 *
 * @param array $config Runtime config.
 *
 * @return array
 */
function khse_cache_policy_caching_check( $config ) {
	$enabled = ! empty( $config['cache_enabled'] );

	return khse_cache_policy_check(
		'caching',
		__( 'Page caching', 'kh-speed-engine' ),
		$enabled ? 'pass' : 'fail',
		$enabled
			? __( 'Page caching is enabled.', 'kh-speed-engine' )
			: __( 'Page caching is disabled.', 'kh-speed-engine' )
	);
}

/**
 * Permalink structure check.
 *
 * @return array
 */
function khse_cache_policy_permalink_check() {
	$ready = '' !== get_option( 'permalink_structure', '' );

	return khse_cache_policy_check(
		'permalink',
		__( 'Permalinks', 'kh-speed-engine' ),
		$ready ? 'pass' : 'fail',
		$ready
			? __( 'Pretty permalinks are enabled.', 'kh-speed-engine' )
			: __( 'Plain permalinks prevent page caching.', 'kh-speed-engine' )
	);
}

/**
 * Document-path check for the admin URL tester.
 *
 * @param string $path Normalized path.
 *
 * @return array
 */
function khse_cache_policy_document_check( $path ) {
	if ( khse_is_cacheable_document_path( $path ) ) {
		return khse_cache_policy_check(
			'document_path',
			__( 'Document path', 'kh-speed-engine' ),
			'pass',
			__( 'This path is an HTML document.', 'kh-speed-engine' )
		);
	}

	return khse_cache_policy_check(
		'document_path',
		__( 'Document path', 'kh-speed-engine' ),
		'fail',
		__( 'Asset, feed, and non-document paths are not page-cached.', 'kh-speed-engine' )
	);
}

/**
 * Cache driver availability check.
 *
 * @param array $config Runtime config.
 *
 * @return array
 */
function khse_cache_policy_driver_check( $config ) {
	$driver = isset( $config['cache_driver'] ) ? sanitize_key( (string) $config['cache_driver'] ) : '';
	$driver = '' !== $driver ? $driver : sanitize_key( (string) get_option( 'khse_option_driver', 'file' ) );

	$available = khse_is_driver_available( $driver );

	return khse_cache_policy_check(
		'driver',
		__( 'Cache driver', 'kh-speed-engine' ),
		$available ? 'pass' : 'fail',
		$available
			? sprintf(
				/* translators: %s is the cache driver name. */
				__( 'The %s driver is available.', 'kh-speed-engine' ),
				$driver
			)
			: sprintf(
				/* translators: %s is the cache driver name. */
				__( 'The %s driver is not available on this server.', 'kh-speed-engine' ),
				$driver
			)
	);
}

/**
 * Bypass path check.
 *
 * @param string $path Normalized path.
 *
 * @return array
 */
function khse_cache_policy_bypass_check( $path ) {
	$match = '';

	foreach ( khse_get_cache_bypass_paths() as $bypass_path ) {
		$prefix = '/' . trim( (string) $bypass_path, '/' );

		if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
			$match = $prefix;
			break;
		}
	}

	if ( '' === $match ) {
		return khse_cache_policy_check(
			'bypass_path',
			__( 'Bypass path', 'kh-speed-engine' ),
			'pass',
			__( 'This path is not in the bypass list.', 'kh-speed-engine' )
		);
	}

	return khse_cache_policy_check(
		'bypass_path',
		__( 'Bypass path', 'kh-speed-engine' ),
		'fail',
		sprintf(
			/* translators: %s is the bypass path. */
			__( 'This path matches the bypass rule "%s".', 'kh-speed-engine' ),
			$match
		)
	);
}

/**
 * Query string identity check.
 *
 * @param string $query      Query string.
 * @param array  $query_vars Parsed query variables.
 *
 * @return array
 */
function khse_cache_policy_query_check( $query, $query_vars ) {
	$blocked = array();

	foreach ( array_keys( $query_vars ) as $query_var ) {
		if ( ! khse_is_cache_tracking_query_var( $query_var ) ) {
			$blocked[] = sanitize_text_field( (string) $query_var );
		}
	}

	if ( '' === $query ) {
		return khse_cache_policy_check(
			'query',
			__( 'Query string', 'kh-speed-engine' ),
			'pass',
			__( 'No query string is present.', 'kh-speed-engine' )
		);
	}

	if ( empty( $blocked ) ) {
		return khse_cache_policy_check(
			'query',
			__( 'Query string', 'kh-speed-engine' ),
			'pass',
			__( 'Only tracking query variables are present.', 'kh-speed-engine' )
		);
	}

	return khse_cache_policy_check(
		'query',
		__( 'Query string', 'kh-speed-engine' ),
		'fail',
		sprintf(
			/* translators: %s is a comma separated list of query variable names. */
			__( 'Non-tracking query strings change the cache identity: %s', 'kh-speed-engine' ),
			implode( ', ', $blocked )
		)
	);
}

/**
 * Find the first excluded path that matches a normalized path.
 *
 * @param string $path      Normalized path.
 * @param array  $exclusion Exclusion config.
 *
 * @return string
 */
function khse_cache_policy_find_excluded_path( $path, $exclusion ) {
	$excluded_list = isset( $exclusion['excluded_list'] ) && is_array( $exclusion['excluded_list'] ) ? $exclusion['excluded_list'] : array();

	foreach ( $excluded_list as $entry ) {
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
 * Excluded path check.
 *
 * @param string $path          Normalized path.
 * @param array  $exclusion     Exclusion config.
 * @param bool   $rules_enabled Whether exclusion rules are enabled.
 *
 * @return array
 */
function khse_cache_policy_exclusion_path_check( $path, $exclusion, $rules_enabled ) {
	if ( ! $rules_enabled ) {
		return khse_cache_policy_check(
			'exclusion_path',
			__( 'Path exclusions', 'kh-speed-engine' ),
			'note',
			__( 'Exclusion rules are disabled.', 'kh-speed-engine' )
		);
	}

	$match = khse_cache_policy_find_excluded_path( $path, $exclusion );

	if ( '' === $match ) {
		return khse_cache_policy_check(
			'exclusion_path',
			__( 'Path exclusions', 'kh-speed-engine' ),
			'pass',
			__( 'This path does not match an exclusion rule.', 'kh-speed-engine' )
		);
	}

	return khse_cache_policy_check(
		'exclusion_path',
		__( 'Path exclusions', 'kh-speed-engine' ),
		'fail',
		sprintf(
			/* translators: %s is the excluded path. */
			__( 'This path matches the exclusion rule "%s".', 'kh-speed-engine' ),
			$match
		)
	);
}

/**
 * Find the first excluded GET variable present in the query.
 *
 * @param array $query_vars Parsed query variables.
 * @param array $exclusion  Exclusion config.
 *
 * @return string
 */
function khse_cache_policy_find_excluded_get( $query_vars, $exclusion ) {
	$excluded_vars = isset( $exclusion['excluded_get_vars'] ) && is_array( $exclusion['excluded_get_vars'] ) ? $exclusion['excluded_get_vars'] : array();

	foreach ( $excluded_vars as $var ) {
		$var = sanitize_text_field( (string) $var );

		if ( '' !== $var && array_key_exists( $var, $query_vars ) ) {
			return $var;
		}
	}

	return '';
}

/**
 * Excluded GET variable check.
 *
 * @param array $query_vars    Parsed query variables.
 * @param array $exclusion     Exclusion config.
 * @param bool  $rules_enabled Whether exclusion rules are enabled.
 *
 * @return array
 */
function khse_cache_policy_exclusion_get_check( $query_vars, $exclusion, $rules_enabled ) {
	if ( ! $rules_enabled ) {
		return khse_cache_policy_check(
			'exclusion_get',
			__( 'GET exclusions', 'kh-speed-engine' ),
			'note',
			__( 'Exclusion rules are disabled.', 'kh-speed-engine' )
		);
	}

	$match = khse_cache_policy_find_excluded_get( $query_vars, $exclusion );

	if ( '' === $match ) {
		return khse_cache_policy_check(
			'exclusion_get',
			__( 'GET exclusions', 'kh-speed-engine' ),
			'pass',
			__( 'No excluded GET variables are present.', 'kh-speed-engine' )
		);
	}

	return khse_cache_policy_check(
		'exclusion_get',
		__( 'GET exclusions', 'kh-speed-engine' ),
		'fail',
		sprintf(
			/* translators: %s is the excluded query variable name. */
			__( 'The query variable "%s" triggers an exclusion rule.', 'kh-speed-engine' ),
			$match
		)
	);
}

/**
 * Cookie note check.
 *
 * @return array
 */
function khse_cache_policy_cookie_note() {
	return khse_cache_policy_check(
		'cookies',
		__( 'Cookies', 'kh-speed-engine' ),
		'note',
		__( 'Cookies cannot be evaluated from a URL. Requests carrying bypass cookies (for example wordpress_logged_in_) are not cached.', 'kh-speed-engine' )
	);
}

/**
 * Logged-in request note check.
 *
 * @return array
 */
function khse_cache_policy_login_note() {
	return khse_cache_policy_check(
		'login',
		__( 'Logged-in users', 'kh-speed-engine' ),
		'note',
		__( 'Logged-in requests bypass the page cache.', 'kh-speed-engine' )
	);
}

/**
 * Whether any check failed.
 *
 * @param array $checks Policy checks.
 *
 * @return bool
 */
function khse_cache_policy_has_failure( $checks ) {
	foreach ( $checks as $check ) {
		if ( 'fail' === $check['status'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Build a failed policy result.
 *
 * @param string $detail Failure reason.
 * @param string $url    Resolved URL, when available.
 *
 * @return array
 */
function khse_cache_policy_failure_result( $detail, $url = '' ) {
	return array(
		'cacheable' => false,
		'url'       => $url,
		'path'      => '',
		'summary'   => __( 'This URL bypasses the page cache.', 'kh-speed-engine' ),
		'checks'    => array(
			khse_cache_policy_check(
				'request',
				__( 'Request', 'kh-speed-engine' ),
				'fail',
				$detail
			),
		),
	);
}

/**
 * Evaluate whether a URL or path can be served by the page cache.
 *
 * @param string $url    Relative path or same-site URL.
 * @param array  $config Runtime config.
 *
 * @return array {
 *     @type bool   $cacheable Whether the URL can be served from cache.
 *     @type string $url       Resolved URL.
 *     @type string $path      Normalized path including the query string.
 *     @type string $summary   Human readable outcome.
 *     @type array  $checks    Policy check entries.
 * }
 */
function khse_evaluate_cache_url_policy( $url, $config = array() ) {
	$config = is_array( $config ) ? $config : array();
	$input  = sanitize_text_field( (string) $url );

	if ( '' === $input ) {
		return khse_cache_policy_failure_result( __( 'Enter a URL or path to test.', 'kh-speed-engine' ) );
	}

	$resolved = khse_resolve_cache_policy_url( $input );

	if ( empty( $resolved['valid'] ) ) {
		return khse_cache_policy_failure_result( $resolved['detail'], esc_url_raw( $input ) );
	}

	$path       = khse_normalize_cache_uri( $resolved['path'] );
	$query      = $resolved['query'];
	$query_vars = array();

	if ( '' !== $query ) {
		parse_str( $query, $query_vars );
	}

	$exclusion     = isset( $config['exclusion'] ) && is_array( $config['exclusion'] ) ? $config['exclusion'] : array();
	$rules_enabled = ! empty( $exclusion['enable'] );

	$checks = array(
		khse_cache_policy_request_check(),
		khse_cache_policy_caching_check( $config ),
		khse_cache_policy_permalink_check(),
		khse_cache_policy_driver_check( $config ),
		khse_cache_policy_bypass_check( $path ),
		khse_cache_policy_document_check( $path ),
		khse_cache_policy_query_check( $query, $query_vars ),
		khse_cache_policy_exclusion_path_check( $path, $exclusion, $rules_enabled ),
		khse_cache_policy_exclusion_get_check( $query_vars, $exclusion, $rules_enabled ),
		khse_cache_policy_cookie_note(),
		khse_cache_policy_login_note(),
	);

	$cacheable = ! khse_cache_policy_has_failure( $checks );

	return array(
		'cacheable' => $cacheable,
		'url'       => $resolved['url'],
		'path'      => $path . ( '' !== $query ? '?' . $query : '' ),
		'summary'   => $cacheable
			? __( 'This URL will be served from cache.', 'kh-speed-engine' )
			: __( 'This URL bypasses the page cache.', 'kh-speed-engine' ),
		'checks'    => $checks,
	);
}
