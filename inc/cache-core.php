<?php
/**
 * KH Speed Engine — cache-core.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

function khse_load_textdomain() {
	load_plugin_textdomain( KHSE_PLUGIN_TEXT_DOMAIN, false, KHSE_PLUGIN_LANGUAGE_PACK );
}

/**
 * Return the site directory hash.
 *
 * Pure: returns the persisted hash or a deterministic fallback without
 * writing. Use khse_ensure_dir_hash() when the value must be persisted.
 *
 * @return string
 */
function khse_get_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( ! empty( $hash ) ) {
		return $hash;
	}

	return khse_generate_dir_hash();
}

/**
 * Generate a deterministic directory hash for this site.
 *
 * @return string
 */
function khse_generate_dir_hash() {
	$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
	$site = defined( 'WP_HOME' ) ? WP_HOME : ( defined( 'ABSPATH' ) ? ABSPATH : __DIR__ );

	if ( function_exists( 'wp_hash' ) ) {
		return substr( wp_hash( $site . '|' . $salt ), 0, 8 );
	}

	return substr( hash( 'sha256', $site . '|' . $salt ), 0, 8 );
}

/**
 * Persist and return the site directory hash.
 *
 * @return string
 */
function khse_ensure_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( empty( $hash ) ) {
		$hash = khse_generate_dir_hash();
		update_option( 'khse_dir_hash', $hash );
	}

	return $hash;
}

/**
 * Check whether an option is enabled across yes/no and enable/disable vocabularies.
 *
 * @param string $key     Option name, with or without the khse_option_ prefix.
 * @param string $default Default when the option is missing.
 *
 * @return bool
 */
function khse_option_is_enabled( $key, $default = 'no' ) {
	$option = 0 === strpos( $key, 'khse_' ) ? $key : 'khse_option_' . $key;
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
 * @param string $key     Option name, with or without the khse_option_ prefix.
 * @param bool   $enabled Whether the option is enabled.
 * @param string $format  yes_no|enable_disable.
 *
 * @return void
 */
function khse_option_set_enabled( $key, $enabled, $format = 'yes_no' ) {
	$option = 0 === strpos( $key, 'khse_' ) ? $key : 'khse_option_' . $key;
	$value  = 'enable_disable' === $format
		? ( $enabled ? 'enable' : 'disable' )
		: ( $enabled ? 'yes' : 'no' );

	update_option( $option, $value );
}

/**
 * Return the canonical settings schema.
 *
 * Each field maps a dashboard payload key to its option, default, sanitizer,
 * read shape, and optional Expert Mode config mapping.
 *
 * @return array
 */
function khse_get_settings_schema() {
	return array(
		'cache'       => array(
			'cachingStatus'       => array( 'option' => 'khse_option_caching_status', 'default' => 'disable', 'sanitize' => 'enable_disable', 'fallback' => 'disable', 'read' => 'enabled', 'config' => 'cache_enabled', 'config_type' => 'enabled' ),
			'driver'              => array( 'option' => 'khse_option_driver', 'default' => 'file', 'sanitize' => 'enum', 'values' => array( 'file', 'redis', 'memcache', 'memcached', 'apc', 'apcu', 'wincache', 'mongo', 'mysql', 'sqlite' ), 'fallback' => 'file', 'read' => 'raw', 'config' => 'cache_driver', 'config_type' => 'raw' ),
			'ttlMechanism'        => array( 'option' => 'khse_option_ttl_mechanism', 'default' => 'enable', 'sanitize' => 'enable_disable', 'fallback' => 'enable', 'read' => 'enabled' ),
			'ttl'                 => array( 'option' => 'khse_option_ttl', 'default' => 86400, 'sanitize' => 'absint', 'min' => 300, 'max' => 2592000, 'read' => 'int' ),
			'cacheKeyPrefix'      => array( 'option' => 'khse_option_cache_key_prefix', 'default' => '', 'sanitize' => 'key', 'read' => 'cache_prefix', 'dynamic_default' => true, 'config' => 'cache_key_prefix', 'config_type' => 'raw' ),
			'maxEntries'          => array( 'option' => 'khse_option_cache_max_entries', 'default' => 0, 'sanitize' => 'absint', 'min' => 0, 'read' => 'cache_max_entries', 'dynamic_default' => true, 'config' => 'cache_max_entries', 'config_type' => 'int' ),
			'expertModeStatus'    => array( 'option' => 'khse_option_expert_mode_status', 'default' => 'disable', 'sanitize' => 'enable_disable', 'fallback' => 'disable', 'read' => 'enabled' ),
			'nginxDirect'         => array( 'option' => 'khse_option_nginx_direct_cache_status', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled', 'config' => 'nginx_direct_cache', 'config_type' => 'enabled' ),
			'debugComment'        => array( 'option' => 'khse_option_html_debug_comment', 'default' => 'yes', 'sanitize' => 'yes_no', 'fallback' => 'yes', 'read' => 'enabled', 'config' => 'html_debug_comment', 'config_type' => 'enabled' ),
			'visibilityLoginUser' => array( 'option' => 'khse_option_visibility_login_user', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled' ),
			'visibilityGuest'     => array( 'option' => 'khse_option_visibility_guest', 'default' => 'yes', 'sanitize' => 'yes_no', 'fallback' => 'yes', 'read' => 'enabled' ),
		),
		'preload'     => array(
			'enabled'       => array( 'option' => 'khse_option_preload_cache', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled', 'config' => 'preload.enable', 'config_type' => 'enabled' ),
			'limit'         => array( 'option' => 'khse_option_preload_limit', 'default' => 50, 'sanitize' => 'absint', 'min' => 1, 'max' => 1000, 'read' => 'int', 'config' => 'preload.limit', 'config_type' => 'int' ),
			'crawlHomepage' => array( 'option' => 'khse_option_preload_homepage_links', 'default' => 'yes', 'sanitize' => 'yes_no', 'fallback' => 'yes', 'read' => 'enabled', 'config' => 'preload.crawl_homepage_links', 'config_type' => 'enabled' ),
			'homepage'      => array( 'option' => 'khse_option_post_homepage', 'default' => 'yes', 'sanitize' => 'yes_no', 'fallback' => 'yes', 'read' => 'enabled' ),
		),
		'rules'       => array(
			'enabled'      => array( 'option' => 'khse_option_exclusion_status', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled' ),
			'excludedList' => array( 'option' => 'khse_option_excluded_list', 'default' => '', 'sanitize' => 'textarea', 'read' => 'raw' ),
			'getVars'      => array( 'option' => 'khse_option_excluded_get_vars', 'default' => '', 'sanitize' => 'textarea', 'read' => 'raw' ),
			'postVars'     => array( 'option' => 'khse_option_excluded_post_vars', 'default' => '', 'sanitize' => 'textarea', 'read' => 'raw' ),
			'cookieVars'   => array( 'option' => 'khse_option_excluded_cookie_vars', 'default' => '', 'sanitize' => 'textarea', 'read' => 'raw' ),
		),
		'statistics'  => array(
			'enabled' => array( 'option' => 'khse_option_statistics_status', 'default' => 'disable', 'sanitize' => 'enable_disable', 'fallback' => 'disable', 'read' => 'enabled' ),
		),
		'benchmark'   => array(
			'widget'        => array( 'option' => 'khse_option_benchmark_widget', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled' ),
			'footer'        => array( 'option' => 'khse_option_benchmark_footer_text', 'default' => 'no', 'sanitize' => 'yes_no', 'fallback' => 'no', 'read' => 'enabled' ),
			'widgetDisplay' => array( 'option' => 'khse_option_benchmark_widget_display', 'default' => 'both', 'sanitize' => 'enum', 'values' => array( 'text', 'icon', 'both' ), 'fallback' => 'both', 'read' => 'raw' ),
			'footerDisplay' => array( 'option' => 'khse_option_benchmark_footer_text_display', 'default' => 'text', 'sanitize' => 'enum', 'values' => array( 'text', 'icon', 'both' ), 'fallback' => 'text', 'read' => 'raw' ),
		),
	);
}

/**
 * Return one settings schema section.
 *
 * @param string $section Section name.
 *
 * @return array
 */
function khse_get_settings_section_schema( $section ) {
	$schema = khse_get_settings_schema();

	return isset( $schema[ $section ] ) && is_array( $schema[ $section ] ) ? $schema[ $section ] : array();
}

/**
 * Read one setting using the canonical schema.
 *
 * @param string $section Section name.
 * @param string $field   Field name.
 *
 * @return mixed
 */
function khse_get_setting( $section, $field ) {
	$schema = khse_get_settings_section_schema( $section );

	if ( ! isset( $schema[ $field ] ) ) {
		return null;
	}

	$rule  = $schema[ $field ];
	$value = get_option( $rule['option'], isset( $rule['default'] ) ? $rule['default'] : '' );
	$read  = isset( $rule['read'] ) ? $rule['read'] : 'raw';

	switch ( $read ) {
		case 'enabled':
			return khse_option_is_enabled( $rule['option'], isset( $rule['default'] ) ? $rule['default'] : 'no' );

		case 'int':
			return (int) $value;

		case 'cache_prefix':
			return khse_get_cache_key_prefix();

		case 'cache_max_entries':
			return khse_get_cache_max_entries();

		default:
			return $value;
	}
}

/**
 * Build the runtime config from the canonical settings schema.
 *
 * @return array
 */
function khse_get_config_from_schema() {
	$config = array();

	foreach ( khse_get_settings_schema() as $section => $fields ) {
		foreach ( $fields as $field => $rule ) {
			if ( empty( $rule['config'] ) ) {
				continue;
			}

			$value = khse_get_setting( $section, $field );

			if ( isset( $rule['config_type'] ) && 'enabled' === $rule['config_type'] ) {
				$value = (bool) $value;
			} elseif ( isset( $rule['config_type'] ) && 'int' === $rule['config_type'] ) {
				$value = (int) $value;
			}

			$path = explode( '.', $rule['config'] );
			$ref  = &$config;

			foreach ( $path as $key ) {
				if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
					$ref[ $key ] = array();
				}

				$ref = &$ref[ $key ];
			}

			$ref = $value;
			unset( $ref );
		}
	}

	return $config;
}

/**
 * Ensure caching is disabled when permalinks are plain.
 *
 * @return void
 */
function khse_ensure_permalink_structure() {
	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'khse_option_caching_status', 'disable' );
	}
}

/**
 * Return the site upload directory.
 *
 * Pure: does not migrate or create directories. Use
 * khse_ensure_upload_dir() before writing.
 *
 * @return string
 */
function khse_get_upload_dir() {
	$base_dir = WP_CONTENT_DIR . '/uploads/kh-speed-engine';

	return $base_dir . '/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
}

/**
 * Ensure the upload directory exists, migrating the legacy directory.
 *
 * @return string
 */
function khse_ensure_upload_dir() {
	$base_dir    = WP_CONTENT_DIR . '/uploads/kh-speed-engine';
	$legacy_dirs = array(
		WP_CONTENT_DIR . '/uploads/ams-cache',
		WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master',
	);

	foreach ( $legacy_dirs as $legacy_dir ) {
		if ( ! is_dir( $base_dir ) && is_dir( $legacy_dir ) ) {
			@rename( $legacy_dir, $base_dir );
			break;
		}
	}

	if ( ! is_dir( $base_dir ) ) {
		wp_mkdir_p( $base_dir );
	}

	return khse_get_upload_dir();
}

/**
 * Get a private runtime directory for config, locks, and temporary workspaces.
 *
 * Never place control files below the public WordPress document root. Hosts may
 * not run Apache, so filesystem permissions alone are not a sufficient guard.
 *
 * @return string
 */
function khse_get_private_runtime_dir() {
	$candidates = array();

	if ( defined( 'WP_TEMP_DIR' ) && WP_TEMP_DIR ) {
		$candidates[] = WP_TEMP_DIR;
	}

	if ( function_exists( 'sys_get_temp_dir' ) ) {
		$candidates[] = sys_get_temp_dir();
	}

	if ( defined( 'ABSPATH' ) ) {
		$candidates[] = dirname( ABSPATH ) . '/kh-speed-engine-runtime';
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
			return $candidate . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
		}
	}

	return rtrim( sys_get_temp_dir(), '/\\' ) . '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
}

/**
 * Get the private Expert Mode lock path.
 *
 * @return string
 */
function khse_get_expert_lock_path() {
	return khse_get_private_runtime_dir() . '/expert.lock';
}

/**
 * Get configuration file's path.
 *
 * @return string
 */
function khse_get_config_path() {
	return khse_get_private_runtime_dir() . '/config.json';
}

/**
 * Merge a stored config over the canonical defaults.
 *
 * @param array $config Raw config.
 *
 * @return array
 */
function khse_normalize_config( $config ) {
	$defaults = khse_get_default_config();
	$config   = is_array( $config ) ? $config : array();
	$config   = array_merge( $defaults, $config );

	foreach ( array( 'preload', 'exclusion' ) as $section ) {
		$config[ $section ] = array_merge(
			$defaults[ $section ],
			isset( $config[ $section ] ) && is_array( $config[ $section ] ) ? $config[ $section ] : array()
		);
	}

	// Drop the retired WooCommerce section from stored configs.
	unset( $config['woocommerce'] );

	return $config;
}

/**
 * Get configuration data.
 *
 * @return array
 */
function khse_get_config_data() {
	$file = khse_get_config_path();

	if ( file_exists( $file ) ) {
		$content = file_get_contents( $file );
		$config = json_decode( $content, true );

		if ( is_array( $config ) ) {
			return khse_normalize_config( $config );
		}
	}

	$option_config = get_option( 'khse_config', array() );

	if ( is_array( $option_config ) && ! empty( $option_config ) ) {
		return khse_normalize_config( $option_config );
	}

	return khse_get_default_config();
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
				$setting = array_merge( $setting, khse_normalize_driver_settings( $advanced, $connection ) );
			}
		} else {
			$resolved = khse_get_driver_settings( $driver_type, $advanced, $connection );
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

	$cached_content .= khse_build_served_debug_message( $config, $expert, $start_time );
	$cached_content  = str_replace( '</body>', "\n" . khse_javascript() . "\n" . '</body>', $cached_content );

	return $cached_content;
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
function khse_get_runtime_exclusion_config() {
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

	update_option( 'khse_option_excluded_list_filtered', implode( "\n", $list ) );

	return array(
		'enable'               => 'yes' === get_option( 'khse_option_exclusion_status', 'no' ),
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
 * @return bool True when the runtime config file was written.
 */
function khse_update_config( $setting ) {
	$config = khse_get_config_data();

	if ( is_array( $setting ) ) {
		$config = array_merge( $config, $setting );
	}

	$config = khse_normalize_config( $config );

	if ( empty( $config['site_url'] ) ) {
		$config['site_url'] = rtrim( get_site_url(), '/' );
	}

	if ( empty( $config['cache_key_prefix'] ) ) {
		$config['cache_key_prefix'] = khse_get_cache_key_prefix();
	}

	$content = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	if ( false === $content ) {
		return false;
	}

	$file = khse_get_config_path();
	$dir  = dirname( $file );

	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	$temp_file = $file . '.tmp';

	// phpcs:ignore
	$written = @file_put_contents( $temp_file, $content, LOCK_EX );

	if ( false === $written || strlen( $content ) !== $written ) {
		@unlink( $temp_file );
		return false;
	}

	if ( ! @rename( $temp_file, $file ) ) {
		// Windows cannot always replace an existing file with rename().
		$written = @file_put_contents( $file, $content, LOCK_EX );
		@unlink( $temp_file );

		if ( false === $written || strlen( $content ) !== $written ) {
			return false;
		}
	}

	update_option( 'khse_config', $config );

	if ( function_exists( 'chmod' ) ) {
		@chmod( $file, 0600 );
	}

	$legacy_file = khse_get_upload_dir() . '/config.json';

	if ( file_exists( $legacy_file ) && $legacy_file !== $file ) {
		@unlink( $legacy_file );
	}

	return true;
}

/**
 * Sync runtime config and Expert Mode lock file.
 *
 * @return bool True when config (and the lock, if enabled) was written.
 */
function khse_sync_expert_mode_runtime() {
	$driver_type      = khse_get_setting( 'cache', 'driver' );
	$connection_type  = khse_get_driver_connection_type( $driver_type );
	$advanced_setting = khse_normalize_driver_settings(
		khse_get_driver_advanced_settings( $driver_type ),
		$connection_type
	);

	$setting = khse_get_config_from_schema();

	$setting['site_url']                 = rtrim( get_site_url(), '/' );
	$setting['driver_advanced_settings'] = $advanced_setting;
	$setting['driver_connection_type']   = $connection_type;
	$setting['exclusion']                = khse_get_runtime_exclusion_config();

	$config_written = khse_update_config( $setting );
	$checkpoint     = khse_get_expert_lock_path();

	if ( 'enable' === get_option( 'khse_option_expert_mode_status' ) ) {
		if ( ! $config_written || ! wp_mkdir_p( dirname( $checkpoint ) ) ) {
			return false;
		}

		// phpcs:ignore
		$lock_written = @file_put_contents( $checkpoint, 'VOTE!', LOCK_EX );

		if ( false === $lock_written ) {
			return false;
		}

		@chmod( $checkpoint, 0600 );
		return true;
	}

	if ( file_exists( $checkpoint ) ) {
		@unlink( $checkpoint );
	}

	return $config_written;
}

/**
 * Set channel Id.
 *
 * @return void
 */
function khse_set_blog_id() {
	update_option( 'khse_blog_id', get_current_blog_id() );
}

/**
 * Get channel Id.
 *
 * @return string
 */
function khse_get_blog_id() {
	return get_option( 'khse_blog_id', 1 );
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function khse_get_stats_dir( $cache_type = 'post' ) {
	return khse_get_upload_dir() . '/stats/' . $cache_type;
}

/**
 * Get the cache key prefix used to isolate shared cache stores.
 *
 * @return string
 */
function khse_get_cache_key_prefix() {
	$prefix = get_option( 'khse_option_cache_key_prefix', '' );

	if ( '' === $prefix ) {
		$prefix = 'khse_' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '_';
	}

	$prefix = preg_replace( '/[^A-Za-z0-9_.-]/', '_', $prefix );

	if ( '' === $prefix ) {
		$prefix = 'khse_' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '_';
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

	foreach ( khse_get_cache_bypass_paths() as $bypass_path ) {
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
 * Get default configuation.
 *
 * This function is also used in expert mode.
 *
 * @return array
 */
function khse_get_default_config() {

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

		'exclusion'                => array(
			'enable'             => false,
			'excluded_list'      => array(),
			'excluded_get_vars'  => array(),
			'excluded_post_vars' => array(),
			'excluded_cookie_vars' => array(),
		),
	);
}

function khse_read_stats_file( $path ) {
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
