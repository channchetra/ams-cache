<?php
/**
 * AMS Cache - Setting page.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.5.2
 * @version 1.5.2
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

require_once SCM_PLUGIN_DIR . 'inc/helpers.php';
require_once SCM_PLUGIN_DIR . 'inc/admin/functions.php';
require_once SCM_PLUGIN_DIR . 'inc/lib/autoload.php';

add_action( 'wp_ajax_scm_action_clear_cache', 'scm_ajax_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_purge_current_page', 'scm_ajax_purge_current_page_callback' );
add_action( 'wp_ajax_scm_action_dashboard_status', 'scm_ajax_dashboard_status_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache', 'scm_ajax_dashboard_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache_type', 'scm_ajax_dashboard_clear_cache_type_callback' );
add_action( 'wp_ajax_scm_action_dashboard_reports', 'scm_ajax_dashboard_reports_callback' );
add_action( 'wp_ajax_scm_action_dashboard_preload', 'scm_ajax_dashboard_preload_callback' );
add_action( 'wp_ajax_scm_action_dashboard_purge_homepage', 'scm_ajax_dashboard_purge_homepage_callback' );
add_action( 'wp_ajax_scm_action_dashboard_test_driver', 'scm_ajax_dashboard_test_driver_callback' );
add_action( 'wp_ajax_scm_action_dashboard_save_settings', 'scm_ajax_dashboard_save_settings_callback' );

/**
 * Validate dashboard AJAX request.
 *
 * @return void
 */
function scm_ajax_dashboard_guard( $nonce_action = 'scm_dashboard', $capability = 'manage_options' ) {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, $nonce_action . '_' . scm_get_dir_hash() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Token has been rejected.', 'ams-cache' ),
			)
		);
	}

	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'ams-cache' ),
			)
		);
	}
}

/**
 * Send the shared clear-cache success payload.
 *
 * @return void
 */
function scm_ajax_clear_cache_response() {
	$rows = scm_clear_all_cache();
	scm_schedule_preload_cache( true );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of rows. */
				__( '%s rows deleted. Background preload queued.', 'ams-cache' ),
				$rows
			),
			'rows'    => $rows,
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Check whether the current user may purge the cache for a URL.
 *
 * Users with edit_posts may purge only the canonical path of a post they
 * can edit; every other same-site path requires manage_options.
 *
 * @param string $url Same-site URL.
 *
 * @return bool
 */
function scm_can_purge_url( $url ) {
	$post_id = url_to_postid( $url );

	if ( $post_id > 0 ) {
		$permalink = get_permalink( $post_id );

		if ( false !== $permalink ) {
			$requested_path = parse_url( $url, PHP_URL_PATH );
			$permalink_path = parse_url( $permalink, PHP_URL_PATH );
			$requested_path = empty( $requested_path ) ? '/' : $requested_path;
			$permalink_path = empty( $permalink_path ) ? '/' : $permalink_path;

			if ( scm_normalize_cache_uri( $requested_path ) === scm_normalize_cache_uri( $permalink_path ) ) {
				return current_user_can( 'edit_post', $post_id );
			}
		}
	}

	return current_user_can( 'manage_options' );
}

/**
 * AJAX callback.
 *
 * @return void
 */
function scm_ajax_clear_cache_callback() {
	scm_ajax_dashboard_guard( 'scm_clear_cache', 'manage_options' );
	scm_ajax_clear_cache_response();
}

/**
 * AJAX callback for purging one current page.
 *
 * @return void
 */
function scm_ajax_purge_current_page_callback() {
	scm_ajax_dashboard_guard( 'scm_purge_current_page', 'edit_posts' );

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $url ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Current page URL is empty.', 'ams-cache' ),
			)
		);
	}

	$home_host = parse_url( home_url( '/' ), PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );

	if ( ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $home_host ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Only same-site URLs can be purged.', 'ams-cache' ),
			)
		);
	}

	if ( ! scm_can_purge_url( $url ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'ams-cache' ),
			)
		);
	}

	$path = parse_url( $url, PHP_URL_PATH );
	$path = empty( $path ) ? '/' : $path;

	if ( ! scm_is_cacheable_document_path( $path ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'This URL is not a cacheable page.', 'ams-cache' ),
			)
		);
	}

	$result = scm_purge_cache_uri( $path );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the purged URL path. */
				__( 'Cache purged for current page: %s', 'ams-cache' ),
				$result['uri']
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard status callback.
 *
 * @return void
 */
function scm_ajax_dashboard_status_callback() {
	scm_ajax_dashboard_guard();

	wp_send_json_success(
		array(
			'status' => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Test the selected cache driver.
 *
 * @return void
 */
function scm_ajax_dashboard_test_driver_callback() {
	scm_ajax_dashboard_guard();

	$driver = isset( $_POST['driver'] ) ? sanitize_key( wp_unslash( $_POST['driver'] ) ) : get_option( 'scm_option_driver', 'file' );
	$result = scm_dashboard_test_driver_connection( $driver );

	$response = array(
		'message'    => $result['message'],
		'driverTest' => $result,
		'status'     => scm_dashboard_get_status_data(),
	);

	if ( ! empty( $result['passed'] ) ) {
		wp_send_json_success( $response );
	}

	wp_send_json_error( $response );
}

/**
 * Normalize a yes/no setting value.
 *
 * @param mixed  $value   Incoming value.
 * @param string $default Default value.
 *
 * @return string
 */
function scm_ajax_dashboard_yes_no( $value, $default = 'no' ) {
	$value = is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : sanitize_key( (string) $value );

	return in_array( $value, array( 'yes', 'no' ), true ) ? $value : $default;
}

/**
 * Normalize an enable/disable setting value.
 *
 * @param mixed  $value   Incoming value.
 * @param string $default Default value.
 *
 * @return string
 */
function scm_ajax_dashboard_enable_disable( $value, $default = 'disable' ) {
	$value = is_bool( $value ) ? ( $value ? 'enable' : 'disable' ) : sanitize_key( (string) $value );

	return in_array( $value, array( 'enable', 'disable' ), true ) ? $value : $default;
}

/**
 * Sanitize advanced driver connection type.
 *
 * @param mixed $value Incoming value.
 *
 * @return string
 */
function scm_ajax_dashboard_connection_type( $value ) {
	$value = sanitize_key( (string) $value );

	return in_array( $value, array( 'tcp', 'socket' ), true ) ? $value : 'tcp';
}

/**
 * Save yes/no toggle map from React list.
 *
 * @param array  $items      Toggle items.
 * @param string $option_key Option key.
 *
 * @return void
 */
function scm_ajax_dashboard_save_toggle_map( $items, $option_key ) {
	if ( ! is_array( $items ) ) {
		return;
	}

	$value = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['value'] ) ) {
			continue;
		}

		if ( 'yes' === scm_ajax_dashboard_yes_no( isset( $item['enabled'] ) ? $item['enabled'] : 'no' ) ) {
			$value[ sanitize_key( $item['value'] ) ] = 'yes';
		}
	}

	update_option( $option_key, $value );
}

/**
 * Apply a declarative option schema to an incoming settings section.
 *
 * @param array $incoming Incoming settings.
 * @param array $schema   Field => rule map.
 *
 * @return void
 */
function scm_ajax_dashboard_apply_option_schema( $incoming, $schema ) {
	foreach ( $schema as $field => $rule ) {
		$value = array_key_exists( $field, $incoming ) ? $incoming[ $field ] : $rule['default'];

		switch ( $rule['sanitize'] ) {
			case 'yes_no':
				$value = scm_ajax_dashboard_yes_no( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'no' );
				break;

			case 'enable_disable':
				$value = scm_ajax_dashboard_enable_disable( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'disable' );
				break;

			case 'absint':
				$value = absint( $value );
				break;

			case 'key':
				$value = sanitize_key( (string) $value );
				break;

			case 'text':
				$value = sanitize_text_field( (string) $value );
				break;

			case 'textarea':
				$value = sanitize_textarea_field( (string) $value );
				break;

			case 'enum':
				$value = sanitize_key( (string) $value );
				$value = in_array( $value, $rule['values'], true ) ? $value : $rule['fallback'];
				break;

			default:
				$value = $value;
		}

		if ( isset( $rule['min'] ) ) {
			$value = max( $rule['min'], $value );
		}

		if ( isset( $rule['max'] ) ) {
			$value = min( $rule['max'], $value );
		}

		update_option( $rule['option'], $value );
	}
}

/**
 * Save React dashboard settings.
 *
 * @return void
 */
function scm_ajax_dashboard_save_settings_callback() {
	scm_ajax_dashboard_guard();

	$raw      = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$settings = json_decode( (string) $raw, true );

	if ( ! is_array( $settings ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Settings payload is invalid.', 'ams-cache' ),
			)
		);
	}

	if ( isset( $settings['cache'] ) && is_array( $settings['cache'] ) ) {
		$cache   = $settings['cache'];
		$drivers = array( 'file', 'redis', 'memcache', 'memcached', 'apc', 'apcu', 'wincache', 'mongo', 'mysql', 'sqlite' );

		scm_ajax_dashboard_apply_option_schema(
			$cache,
			array(
				'cachingStatus'       => array( 'option' => 'scm_option_caching_status', 'sanitize' => 'enable_disable', 'default' => get_option( 'scm_option_caching_status', 'disable' ), 'fallback' => 'disable' ),
				'driver'              => array( 'option' => 'scm_option_driver', 'sanitize' => 'enum', 'values' => $drivers, 'fallback' => 'file', 'default' => get_option( 'scm_option_driver', 'file' ) ),
				'ttlMechanism'        => array( 'option' => 'scm_option_ttl_mechanism', 'sanitize' => 'enable_disable', 'default' => get_option( 'scm_option_ttl_mechanism', 'enable' ), 'fallback' => 'enable' ),
				'ttl'                 => array( 'option' => 'scm_option_ttl', 'sanitize' => 'absint', 'default' => get_option( 'scm_option_ttl', 86400 ), 'min' => 300, 'max' => 2592000 ),
				'cacheKeyPrefix'      => array( 'option' => 'scm_option_cache_key_prefix', 'sanitize' => 'key', 'default' => scm_get_cache_key_prefix() ),
				'maxEntries'          => array( 'option' => 'scm_option_cache_max_entries', 'sanitize' => 'absint', 'default' => scm_get_cache_max_entries(), 'min' => 0 ),
				'expertModeStatus'    => array( 'option' => 'scm_option_expert_mode_status', 'sanitize' => 'enable_disable', 'default' => get_option( 'scm_option_expert_mode_status', 'disable' ), 'fallback' => 'disable' ),
				'nginxDirect'         => array( 'option' => 'scm_option_nginx_direct_cache_status', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_nginx_direct_cache_status', 'no' ), 'fallback' => 'no' ),
				'debugComment'        => array( 'option' => 'scm_option_html_debug_comment', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_html_debug_comment', 'yes' ), 'fallback' => 'yes' ),
				'visibilityLoginUser' => array( 'option' => 'scm_option_visibility_login_user', 'sanitize' => 'yes_no', 'default' => 'no', 'fallback' => 'no' ),
				'visibilityGuest'     => array( 'option' => 'scm_option_visibility_guest', 'sanitize' => 'yes_no', 'default' => 'yes', 'fallback' => 'yes' ),
			)
		);

		if ( isset( $cache['driverAdvanced'] ) && is_array( $cache['driverAdvanced'] ) ) {
			$advanced = $cache['driverAdvanced'];
			$file     = isset( $advanced['file'] ) && is_array( $advanced['file'] ) ? $advanced['file'] : array();
			$redis    = isset( $advanced['redis'] ) && is_array( $advanced['redis'] ) ? $advanced['redis'] : array();
			$memcache = isset( $advanced['memcached'] ) && is_array( $advanced['memcached'] ) ? $advanced['memcached'] : ( isset( $advanced['memcache'] ) && is_array( $advanced['memcache'] ) ? $advanced['memcache'] : array() );
			$mongodb  = isset( $advanced['mongodb'] ) && is_array( $advanced['mongodb'] ) ? $advanced['mongodb'] : ( isset( $advanced['mongo'] ) && is_array( $advanced['mongo'] ) ? $advanced['mongo'] : array() );
			$conn     = isset( $advanced['connection'] ) && is_array( $advanced['connection'] ) ? $advanced['connection'] : array();

			update_option(
				'scm_option_advanced_driver_file',
				array(
					'compress'           => scm_ajax_dashboard_yes_no( isset( $file['compress'] ) ? $file['compress'] : 'no' ),
					'compress_threshold' => max( 0, absint( isset( $file['compress_threshold'] ) ? $file['compress_threshold'] : 4096 ) ),
					'compress_level'     => max( 1, min( 9, absint( isset( $file['compress_level'] ) ? $file['compress_level'] : 1 ) ) ),
				)
			);
			update_option(
				'scm_option_advanced_driver_redis',
				array(
					'host'               => sanitize_text_field( isset( $redis['host'] ) ? $redis['host'] : '127.0.0.1' ),
					'port'               => max( 1, absint( isset( $redis['port'] ) ? $redis['port'] : 6379 ) ),
					'user'               => sanitize_text_field( isset( $redis['user'] ) ? $redis['user'] : '' ),
					'pass'               => sanitize_text_field( isset( $redis['pass'] ) ? $redis['pass'] : '' ),
					'database'           => max( 0, min( 15, absint( isset( $redis['database'] ) ? $redis['database'] : 0 ) ) ),
					'unix_socket'        => sanitize_text_field( isset( $redis['unix_socket'] ) ? $redis['unix_socket'] : '' ),
					'compress'           => scm_ajax_dashboard_yes_no( isset( $redis['compress'] ) ? $redis['compress'] : 'yes', 'yes' ),
					'compress_threshold' => max( 0, absint( isset( $redis['compress_threshold'] ) ? $redis['compress_threshold'] : 1024 ) ),
					'compress_level'     => max( 1, min( 9, absint( isset( $redis['compress_level'] ) ? $redis['compress_level'] : 6 ) ) ),
				)
			);
			update_option(
				'scm_option_advanced_driver_memcached',
				array(
					'host'        => sanitize_text_field( isset( $memcache['host'] ) ? $memcache['host'] : '127.0.0.1' ),
					'port'        => max( 1, absint( isset( $memcache['port'] ) ? $memcache['port'] : 11211 ) ),
					'unix_socket' => sanitize_text_field( isset( $memcache['unix_socket'] ) ? $memcache['unix_socket'] : '' ),
				)
			);
			update_option(
				'scm_option_advanced_driver_mongodb',
				array(
					'host'        => sanitize_text_field( isset( $mongodb['host'] ) ? $mongodb['host'] : '127.0.0.1' ),
					'port'        => max( 1, absint( isset( $mongodb['port'] ) ? $mongodb['port'] : 27017 ) ),
					'user'        => sanitize_text_field( isset( $mongodb['user'] ) ? $mongodb['user'] : '' ),
					'pass'        => sanitize_text_field( isset( $mongodb['pass'] ) ? $mongodb['pass'] : '' ),
					'dbname'      => sanitize_text_field( isset( $mongodb['dbname'] ) ? $mongodb['dbname'] : ( isset( $mongodb['database'] ) ? $mongodb['database'] : 'test' ) ),
					'collection'  => sanitize_text_field( isset( $mongodb['collection'] ) ? $mongodb['collection'] : 'cache_data' ),
					'unix_socket' => sanitize_text_field( isset( $mongodb['unix_socket'] ) ? $mongodb['unix_socket'] : '' ),
				)
			);
			update_option( 'scm_option_advanced_driver_redis_connection_type', scm_ajax_dashboard_connection_type( isset( $conn['redis'] ) ? $conn['redis'] : 'tcp' ) );
			update_option( 'scm_option_advanced_driver_memcached_connection_type', scm_ajax_dashboard_connection_type( isset( $conn['memcached'] ) ? $conn['memcached'] : ( isset( $conn['memcache'] ) ? $conn['memcache'] : 'tcp' ) ) );
			update_option( 'scm_option_advanced_driver_mongodb_connection_type', scm_ajax_dashboard_connection_type( isset( $conn['mongo'] ) ? $conn['mongo'] : ( isset( $conn['mongodb'] ) ? $conn['mongodb'] : 'tcp' ) ) );
		}
	}

	if ( isset( $settings['preload'] ) && is_array( $settings['preload'] ) ) {
		$preload     = $settings['preload'];
		$was_enabled = 'yes' === get_option( 'scm_option_preload_cache', 'no' );

		scm_ajax_dashboard_apply_option_schema(
			$preload,
			array(
				'enabled'       => array( 'option' => 'scm_option_preload_cache', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_preload_cache', 'no' ), 'fallback' => 'no' ),
				'limit'         => array( 'option' => 'scm_option_preload_limit', 'sanitize' => 'absint', 'default' => get_option( 'scm_option_preload_limit', 50 ), 'min' => 1, 'max' => 1000 ),
				'crawlHomepage' => array( 'option' => 'scm_option_preload_homepage_links', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_preload_homepage_links', 'yes' ), 'fallback' => 'yes' ),
				'homepage'      => array( 'option' => 'scm_option_post_homepage', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_post_homepage', 'yes' ), 'fallback' => 'yes' ),
			)
		);
		$now_enabled = get_option( 'scm_option_preload_cache' );
		scm_ajax_dashboard_save_toggle_map( isset( $preload['postTypes'] ) ? $preload['postTypes'] : array(), 'scm_option_post_types' );
		scm_ajax_dashboard_save_toggle_map( isset( $preload['archives'] ) ? $preload['archives'] : array(), 'scm_option_post_archives' );

		if ( ! $was_enabled && 'yes' === $now_enabled ) {
			scm_schedule_preload_cache( true );
			if ( function_exists( 'spawn_cron' ) ) {
				spawn_cron();
			}
		}
	}

	if ( isset( $settings['performance'] ) && is_array( $settings['performance'] ) ) {
		$incoming = $settings['performance'];
		$current  = scm_get_page_optimization_settings();
		$defaults = scm_get_default_page_optimization_settings();
		$boolean_keys = array(
			'status',
			'minify_html',
			'remove_comments',
			'minify_inline_css',
			'lazy_media',
			'critical_images',
			'preconnect_fonts',
			'defer_js',
			'external_ucss',
			'local_ucss',
			'js_analysis',
		);

		foreach ( $boolean_keys as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$current[ $key ] = scm_ajax_dashboard_yes_no( $incoming[ $key ], isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'no' );
			}
		}

		$current['critical_image_count'] = max( 0, min( 5, absint( isset( $incoming['critical_image_count'] ) ? $incoming['critical_image_count'] : $current['critical_image_count'] ) ) );
		$current['external_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['external_ucss_max_file_size'] ) ? $incoming['external_ucss_max_file_size'] : $current['external_ucss_max_file_size'] ) ) );
		$current['local_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['local_ucss_max_file_size'] ) ? $incoming['local_ucss_max_file_size'] : $current['local_ucss_max_file_size'] ) ) );
		$current['js_analysis_max_script_bytes'] = max( 16384, min( 1048576, absint( isset( $incoming['js_analysis_max_script_bytes'] ) ? $incoming['js_analysis_max_script_bytes'] : $current['js_analysis_max_script_bytes'] ) ) );
		$current['js_analysis_max_total_bytes'] = max( 65536, min( 4194304, absint( isset( $incoming['js_analysis_max_total_bytes'] ) ? $incoming['js_analysis_max_total_bytes'] : $current['js_analysis_max_total_bytes'] ) ) );
		$current['bun_path']             = sanitize_text_field( isset( $incoming['bun_path'] ) ? $incoming['bun_path'] : $current['bun_path'] );
		$current['purgecss_path']        = sanitize_text_field( isset( $incoming['purgecss_path'] ) ? $incoming['purgecss_path'] : $current['purgecss_path'] );
		$current['ucss_safelist']        = sanitize_textarea_field( isset( $incoming['ucss_safelist'] ) ? $incoming['ucss_safelist'] : $current['ucss_safelist'] );
		$current['media_exclusions']     = sanitize_textarea_field( isset( $incoming['media_exclusions'] ) ? $incoming['media_exclusions'] : $current['media_exclusions'] );
		$current['js_exclusions']        = sanitize_textarea_field( isset( $incoming['js_exclusions'] ) ? $incoming['js_exclusions'] : $current['js_exclusions'] );

		update_option( 'scm_option_page_optimization', $current );
	}

	if ( isset( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
		$rules = $settings['rules'];

		scm_ajax_dashboard_apply_option_schema(
			$rules,
			array(
				'enabled'      => array( 'option' => 'scm_option_exclusion_status', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_exclusion_status', 'no' ), 'fallback' => 'no' ),
				'excludedList' => array( 'option' => 'scm_option_excluded_list', 'sanitize' => 'textarea', 'default' => get_option( 'scm_option_excluded_list', '' ) ),
				'getVars'      => array( 'option' => 'scm_option_excluded_get_vars', 'sanitize' => 'textarea', 'default' => get_option( 'scm_option_excluded_get_vars', '' ) ),
				'postVars'     => array( 'option' => 'scm_option_excluded_post_vars', 'sanitize' => 'textarea', 'default' => get_option( 'scm_option_excluded_post_vars', '' ) ),
				'cookieVars'   => array( 'option' => 'scm_option_excluded_cookie_vars', 'sanitize' => 'textarea', 'default' => get_option( 'scm_option_excluded_cookie_vars', '' ) ),
			)
		);
	}

	if ( isset( $settings['statistics'] ) && is_array( $settings['statistics'] ) ) {
		scm_ajax_dashboard_apply_option_schema(
			$settings['statistics'],
			array(
				'enabled' => array( 'option' => 'scm_option_statistics_status', 'sanitize' => 'enable_disable', 'default' => get_option( 'scm_option_statistics_status', 'disable' ), 'fallback' => 'disable' ),
			)
		);
	}

	if ( isset( $settings['benchmark'] ) && is_array( $settings['benchmark'] ) ) {
		$benchmark = $settings['benchmark'];

		scm_ajax_dashboard_apply_option_schema(
			$benchmark,
			array(
				'widget'        => array( 'option' => 'scm_option_benchmark_widget', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_benchmark_widget', 'no' ), 'fallback' => 'no' ),
				'footer'        => array( 'option' => 'scm_option_benchmark_footer_text', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_benchmark_footer_text', 'no' ), 'fallback' => 'no' ),
				'widgetDisplay' => array( 'option' => 'scm_option_benchmark_widget_display', 'sanitize' => 'enum', 'values' => array( 'text', 'icon', 'both' ), 'fallback' => 'both', 'default' => '' ),
				'footerDisplay' => array( 'option' => 'scm_option_benchmark_footer_text_display', 'sanitize' => 'enum', 'values' => array( 'text', 'icon', 'both' ), 'fallback' => 'text', 'default' => '' ),
			)
		);
	}

	if ( isset( $settings['woocommerce'] ) && is_array( $settings['woocommerce'] ) ) {
		$woocommerce = $settings['woocommerce'];

		scm_ajax_dashboard_apply_option_schema(
			$woocommerce,
			array(
				'enabled'         => array( 'option' => 'scm_option_woocommerce_status', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_woocommerce_status', 'no' ), 'fallback' => 'no' ),
				'paymentComplete' => array( 'option' => 'scm_option_woocommerce_event_payment_complete', 'sanitize' => 'yes_no', 'default' => get_option( 'scm_option_woocommerce_event_payment_complete', 'no' ), 'fallback' => 'no' ),
			)
		);
	}

	scm_sync_expert_mode_runtime();
	scm_clear_all_cache();

	wp_send_json_success(
		array(
			'message' => __( 'Settings saved.', 'ams-cache' ),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard clear cache callback.
 *
 * @return void
 */
function scm_ajax_dashboard_clear_cache_callback() {
	scm_ajax_dashboard_guard();
	scm_ajax_clear_cache_response();
}

/**
 * Dashboard clear one cache type callback.
 *
 * @return void
 */
function scm_ajax_dashboard_clear_cache_type_callback() {
	scm_ajax_dashboard_guard();

	$cache_type = isset( $_POST['cacheType'] ) ? sanitize_key( wp_unslash( $_POST['cacheType'] ) ) : '';
	$labels     = scm_get_cache_type_list();
	$list       = array_keys( $labels );

	if ( ! in_array( $cache_type, $list, true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Unknown cache type.', 'ams-cache' ),
			)
		);
	}

	$rows = scm_clear_cache_type( $cache_type );
	$label = wp_strip_all_tags( $labels[ $cache_type ] );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: 1: cache type, 2: number of rows. */
				__( '%1$s cache cleared. %2$s rows deleted.', 'ams-cache' ),
				$label,
				$rows
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard lazy report page callback.
 *
 * @return void
 */
function scm_ajax_dashboard_reports_callback() {
	scm_ajax_dashboard_guard();

	$offset        = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
	$page_size     = 5;
	$total_reports = scm_count_page_optimization_reports();
	$reports       = scm_dashboard_normalize_optimization_reports(
		scm_get_page_optimization_reports( $page_size, $offset )
	);
	$loaded_count  = min( $total_reports, $offset + count( $reports ) );

	wp_send_json_success(
		array(
			'reports'     => $reports,
			'loadedCount' => $loaded_count,
			'totalReports'=> $total_reports,
			'hasMore'     => $loaded_count < $total_reports,
		)
	);
}

/**
 * Dashboard preload callback.
 *
 * @return void
 */
function scm_ajax_dashboard_preload_callback() {
	scm_ajax_dashboard_guard();

	$count = scm_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( '%s preload URLs queued for background processing.', 'ams-cache' ),
				$count
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard purge homepage callback.
 *
 * @return void
 */
function scm_ajax_dashboard_purge_homepage_callback() {
	scm_ajax_dashboard_guard();

	scm_purge_cache_uri( '/' );
	$count = scm_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( 'Homepage cache purged. %s priority preload URLs queued.', 'ams-cache' ),
				$count
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}
