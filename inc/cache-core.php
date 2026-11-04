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
 * Return the site directory hash.
 *
 * Pure: returns the persisted hash or a deterministic fallback without
 * writing. Use scm_ensure_dir_hash() when the value must be persisted.
 *
 * @return string
 */
function scm_get_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( ! empty( $hash ) ) {
		return $hash;
	}

	return scm_generate_dir_hash();
}

/**
 * Generate a deterministic directory hash for this site.
 *
 * @return string
 */
function scm_generate_dir_hash() {
	$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';

	return substr( wp_hash( get_site_url() . '|' . $salt ), 0, 8 );
}

/**
 * Persist and return the site directory hash.
 *
 * @return string
 */
function scm_ensure_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( empty( $hash ) ) {
		$hash = scm_generate_dir_hash();
		update_option( 'scm_dir_hash', $hash );
	}

	return $hash;
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
 * Ensure caching is disabled when permalinks are plain.
 *
 * @return void
 */
function scm_ensure_permalink_structure() {
	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'scm_option_caching_status', 'disable' );
	}
}

/**
 * Return the site upload directory.
 *
 * Pure: does not migrate or create directories. Use
 * scm_ensure_upload_dir() before writing.
 *
 * @return string
 */
function scm_get_upload_dir() {
	$base_dir = WP_CONTENT_DIR . '/uploads/ams-cache';

	return $base_dir . '/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
}

/**
 * Ensure the upload directory exists, migrating the legacy directory.
 *
 * @return string
 */
function scm_ensure_upload_dir() {
	$base_dir   = WP_CONTENT_DIR . '/uploads/ams-cache';
	$legacy_dir = WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master';

	if ( ! is_dir( $base_dir ) && is_dir( $legacy_dir ) ) {
		@rename( $legacy_dir, $base_dir );
	}

	if ( ! is_dir( $base_dir ) ) {
		wp_mkdir_p( $base_dir );
	}

	return scm_get_upload_dir();
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
 * Merge a stored config over the canonical defaults.
 *
 * @param array $config Raw config.
 *
 * @return array
 */
function scm_normalize_config( $config ) {
	$defaults = scm_get_default_config();
	$config   = is_array( $config ) ? $config : array();
	$config   = array_merge( $defaults, $config );

	foreach ( array( 'preload', 'woocommerce', 'exclusion' ) as $section ) {
		$config[ $section ] = array_merge(
			$defaults[ $section ],
			isset( $config[ $section ] ) && is_array( $config[ $section ] ) ? $config[ $section ] : array()
		);
	}

	return $config;
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
			return scm_normalize_config( $config );
		}
	}

	$option_config = get_option( 'scm_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return scm_normalize_config( $option_config );
	}

	return scm_get_default_config();
}

/**
 * Check whether the current request is excluded from the page cache.
 *
 * @param array $config Runtime config.
 *
 * @return bool
 */
function scm_is_cache_request_excluded( $config ) {
	$exclusion = isset( $config['exclusion'] ) && is_array( $config['exclusion'] ) ? $config['exclusion'] : array();

	if ( ! empty( $exclusion['enable'] ) ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? scm_normalize_cache_uri( $_SERVER['REQUEST_URI'] ) : '/';

		foreach ( isset( $exclusion['excluded_list'] ) ? $exclusion['excluded_list'] : array() as $ignored_url ) {
			if ( '' !== (string) $ignored_url && 0 === strpos( $path, $ignored_url ) ) {
				return true;
			}
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

	$woocommerce = isset( $config['woocommerce'] ) && is_array( $config['woocommerce'] ) ? $config['woocommerce'] : array();

	if ( ! empty( $woocommerce['enable'] ) ) {
		if ( isset( $_POST['add-to-cart'] ) && is_numeric( $_POST['add-to-cart'] ) ) {
			return true;
		}
		if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) {
			return true;
		}
		if ( isset( $_GET['wc-ajax'] ) ) {
			return true;
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
function scm_build_served_debug_message( $config, $expert = false, $start_time = null ) {
	$translate = function ( $text ) {
		return function_exists( '__' ) ? __( $text, 'ams-cache' ) : $text;
	};

	$date         = date( 'Y-m-d H:i:s' );
	$memory_usage = round( memory_get_usage() / ( 1024 * 1024 ), 4 );
	$sql_queries  = function_exists( 'get_num_queries' ) ? get_num_queries() : 0;
	$page_speed   = function_exists( 'timer_stop' ) ? timer_stop() : ( null !== $start_time ? round( microtime( true ) - $start_time, 3 ) : 0 );

	scm_set_view_variable( 'now', $date, 'after' );
	scm_set_view_variable( 'memory_usage', $memory_usage, 'after' );
	scm_set_view_variable( 'sql_queries', $sql_queries, 'after' );
	scm_set_view_variable( 'page_generation_time', $page_speed, 'after' );

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
function scm_serve_cached_response( $config, $driver = null, $storage_base = '', $expert = false, $start_time = null ) {
	if ( isset( $config['cache_enabled'] ) && ! $config['cache_enabled'] ) {
		return null;
	}

	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return null;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

	if ( ! scm_is_cacheable_request_uri( $request_uri ) ) {
		return null;
	}

	if ( scm_is_cache_request_excluded( $config ) ) {
		return null;
	}

	$path = scm_normalize_cache_uri( $request_uri );
	$key  = scm_build_cache_key( isset( $config['cache_key_prefix'] ) ? $config['cache_key_prefix'] : '', $path );

	if ( ! $driver ) {
		$driver_type = isset( $config['cache_driver'] ) ? $config['cache_driver'] : 'file';
		$advanced    = isset( $config['driver_advanced_settings'] ) ? $config['driver_advanced_settings'] : array();
		$connection  = isset( $config['driver_connection_type'] ) ? $config['driver_connection_type'] : 'tcp';

		if ( '' !== $storage_base && in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
			// Expert Mode runs before WP_CONTENT_DIR exists; build storage directly.
			$setting = array(
				'storage' => rtrim( $storage_base, '/\\' ) . '/' . $driver_type . '_driver',
			);

			if ( ! empty( $advanced ) ) {
				$setting = array_merge( $setting, scm_normalize_driver_settings( $advanced, $connection ) );
			}
		} else {
			$resolved = scm_get_driver_settings( $driver_type, $advanced, $connection );
			$setting  = $resolved['setting'];
		}

		try {
			$driver = new \Shieldon\SimpleCache\Cache( $driver_type, $setting );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	try {
		$cached_content = $driver->get( $key );
	} catch ( \Throwable $e ) {
		return null;
	}

	if ( empty( $cached_content ) || false === strpos( $cached_content, '</body>' ) || strlen( $cached_content ) < 1024 ) {
		return null;
	}

	$cached_content .= scm_build_served_debug_message( $config, $expert, $start_time );
	$cached_content  = str_replace( '</body>', "\n" . scm_javascript() . "\n" . '</body>', $cached_content );

	return $cached_content;
}

/**
 * Build the runtime exclusion configuration from the stored settings.
 *
 * Persists the normalized path list to scm_option_excluded_list_filtered.
 *
 * @return array {
 *     @type bool  $enable               Whether exclusion rules are enabled.
 *     @type array $excluded_list        Normalized excluded paths.
 *     @type array $excluded_get_vars    Excluded GET variables.
 *     @type array $excluded_post_vars   Excluded POST variables.
 *     @type array $excluded_cookie_vars Excluded cookie variables.
 * }
 */
function scm_get_runtime_exclusion_config() {
	$list = array();

	foreach ( scm_get_lines_from_textarea( get_option( 'scm_option_excluded_list', '' ) ) as $item ) {
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

		foreach ( scm_get_lines_from_textarea( get_option( 'scm_option_excluded_' . $type . '_vars', '' ) ) as $item ) {
			if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $item ) ) {
				$variables[ $type ][] = $item;
			}
		}
	}

	update_option( 'scm_option_excluded_list_filtered', implode( "\n", $list ) );

	return array(
		'enable'               => 'yes' === get_option( 'scm_option_exclusion_status', 'no' ),
		'excluded_list'        => $list,
		'excluded_get_vars'    => $variables['get'],
		'excluded_post_vars'   => $variables['post'],
		'excluded_cookie_vars' => $variables['cookie'],
	);
}

/**
 * Save the settings into the JSON runtime config.
 *
 * @param array $setting The setting.
 *
 * @return void
 */
function scm_update_config( $setting ) {
	$config = scm_get_config_data();

	if ( is_array( $setting ) ) {
		$config = array_merge( $config, $setting );
	}

	$config = scm_normalize_config( $config );

	if ( empty( $config['site_url'] ) ) {
		$config['site_url'] = rtrim( get_site_url(), '/' );
	}

	if ( empty( $config['cache_key_prefix'] ) ) {
		$config['cache_key_prefix'] = scm_get_cache_key_prefix();
	}

	update_option( 'scm_config', $config );

	$file    = scm_get_config_path();
	$content = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	wp_mkdir_p( dirname( $file ) );

	// phpcs:ignore
	@file_put_contents( $file, $content, LOCK_EX );

	if ( function_exists( 'chmod' ) ) {
		@chmod( $file, 0600 );
	}

	$legacy_file = scm_get_upload_dir() . '/config.json';

	if ( file_exists( $legacy_file ) && $legacy_file !== $file ) {
		@unlink( $legacy_file );
	}
}

/**
 * Sync runtime config and Expert Mode lock file.
 *
 * @return void
 */
function scm_sync_expert_mode_runtime() {
	$driver_type      = get_option( 'scm_option_driver', 'file' );
	$connection_type  = scm_get_driver_connection_type( $driver_type );
	$advanced_setting = scm_normalize_driver_settings(
		scm_get_driver_advanced_settings( $driver_type ),
		$connection_type
	);

	$setting['cache_driver']             = $driver_type;
	$setting['cache_key_prefix']         = scm_get_cache_key_prefix();
	$setting['site_url']      = rtrim( get_site_url(), '/' );
	$setting['cache_enabled'] = 'enable' === get_option( 'scm_option_caching_status', 'disable' );
	$setting['driver_advanced_settings'] = $advanced_setting;
	$setting['driver_connection_type']   = $connection_type;
	$setting['nginx_direct_cache']       = scm_is_nginx_direct_cache_enabled();
	$setting['html_debug_comment']       = 'yes' === get_option( 'scm_option_html_debug_comment', 'yes' );
	$setting['woocommerce']              = array(
		'enable' => 'yes' === get_option( 'scm_option_woocommerce_status', 'no' ),
	);
	$setting['exclusion']                = scm_get_runtime_exclusion_config();
	$setting['preload']                  = array(
		'enable'               => scm_is_preload_enabled(),
		'limit'                => (int) get_option( 'scm_option_preload_limit', 50 ),
		'crawl_homepage_links' => 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ),
	);
	$setting['cache_max_entries']        = scm_get_cache_max_entries();

	scm_update_config( $setting );

	$checkpoint = scm_get_expert_lock_path();

	if ( 'enable' === get_option( 'scm_option_expert_mode_status' ) ) {
		wp_mkdir_p( dirname( $checkpoint ) );
		file_put_contents( $checkpoint, 'VOTE!' );
		@chmod( $checkpoint, 0600 );
		return;
	}

	if ( file_exists( $checkpoint ) ) {
		unlink( $checkpoint );
	}
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
 * Check whether a query string can share a page-cache identity.
 *
 * Only tracking variables are ignored by the cache key; any other query
 * variable changes the response and must not be treated as the same page.
 *
 * @param string $query Query string without the leading "?".
 *
 * @return bool
 */
function scm_is_cacheable_query_string( $query ) {
	if ( '' === (string) $query ) {
		return true;
	}

	$query_vars = array();
	parse_str( (string) $query, $query_vars );

	foreach ( array_keys( $query_vars ) as $query_var ) {
		if ( ! scm_is_cache_tracking_query_var( $query_var ) ) {
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

	if ( ! scm_is_cacheable_query_string( $query ) ) {
		return false;
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
 * Build a cache key from an explicit prefix and URI.
 *
 * @param string $prefix Cache key prefix.
 * @param string $uri    Request URI or path.
 *
 * @return string
 */
function scm_build_cache_key( $prefix, $uri ) {
	return md5( (string) $prefix . '|' . scm_normalize_cache_uri( $uri ) );
}

/**
 * Build the cache key for a request URI using the stored prefix.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function scm_get_cache_key( $uri ) {
	return scm_build_cache_key( scm_get_cache_key_prefix(), $uri );
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
		'cache_enabled'            => true,
		'cache_key_prefix'         => '',
		'cache_max_entries'        => 0,
		'nginx_direct_cache'       => false,
		'html_debug_comment'       => true,
		'driver_advanced_settings' => array(),
		'driver_connection_type'   => 'tcp',
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
