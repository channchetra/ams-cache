<?php
/**
 * KH Speed Engine - Dashboard AJAX handlers.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

require_once KHSE_PLUGIN_DIR . 'inc/bootstrap.php';
require_once KHSE_PLUGIN_DIR . 'inc/admin/functions.php';
require_once KHSE_PLUGIN_DIR . 'inc/lib/autoload.php';

add_action( 'wp_ajax_khse_action_clear_cache', 'khse_ajax_clear_cache_callback' );
add_action( 'wp_ajax_khse_action_purge_current_page', 'khse_ajax_purge_current_page_callback' );
add_action( 'wp_ajax_khse_action_dashboard_status', 'khse_ajax_dashboard_status_callback' );
add_action( 'wp_ajax_khse_action_dashboard_clear_cache', 'khse_ajax_dashboard_clear_cache_callback' );
add_action( 'wp_ajax_khse_action_dashboard_clear_cache_type', 'khse_ajax_dashboard_clear_cache_type_callback' );
add_action( 'wp_ajax_khse_action_dashboard_reports', 'khse_ajax_dashboard_reports_callback' );
add_action( 'wp_ajax_khse_action_dashboard_preload', 'khse_ajax_dashboard_preload_callback' );
add_action( 'wp_ajax_khse_action_dashboard_purge_homepage', 'khse_ajax_dashboard_purge_homepage_callback' );
add_action( 'wp_ajax_khse_action_dashboard_test_driver', 'khse_ajax_dashboard_test_driver_callback' );
add_action( 'wp_ajax_khse_action_dashboard_test_url', 'khse_ajax_dashboard_test_url_callback' );
add_action( 'wp_ajax_khse_action_dashboard_nginx_snippet', 'khse_ajax_dashboard_nginx_snippet_callback' );
add_action( 'wp_ajax_khse_action_dashboard_save_settings', 'khse_ajax_dashboard_save_settings_callback' );

/**
 * Validate dashboard AJAX request.
 *
 * @return void
 */
function khse_ajax_dashboard_guard( $nonce_action = 'khse_dashboard', $capability = 'manage_options' ) {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, $nonce_action . '_' . khse_get_dir_hash() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Token has been rejected.', 'kh-speed-engine' ),
			)
		);
	}

	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'kh-speed-engine' ),
			)
		);
	}
}

/**
 * Send the shared clear-cache success payload.
 *
 * @return void
 */
function khse_ajax_clear_cache_response() {
	$rows = khse_clear_all_cache();
	khse_schedule_preload_cache( true );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of rows. */
				__( '%s rows deleted. Background preload queued.', 'kh-speed-engine' ),
				$rows
			),
			'rows'    => $rows,
			'status'  => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Check whether the current user may purge the cache for a URL.
 *
 * Users with edit_posts may purge only the canonical path of a publicly
 * viewable post they can edit, and only when the URL itself is cacheable;
 * every other path requires manage_options.
 *
 * @param string $url Same-site URL.
 *
 * @return bool
 */
function khse_can_purge_url( $url ) {
	$parsed = parse_url( $url );

	if ( false === $parsed ) {
		return current_user_can( 'manage_options' );
	}

	$query = isset( $parsed['query'] ) ? $parsed['query'] : '';

	if ( ! khse_is_cacheable_query_string( $query ) ) {
		return current_user_can( 'manage_options' );
	}

	$post_id = url_to_postid( $url );

	if ( $post_id > 0 && is_post_publicly_viewable( $post_id ) ) {
		$permalink = get_permalink( $post_id );

		if ( false !== $permalink ) {
			$requested_path = isset( $parsed['path'] ) ? $parsed['path'] : '';
			$permalink_path = parse_url( $permalink, PHP_URL_PATH );
			$requested_path = empty( $requested_path ) ? '/' : $requested_path;
			$permalink_path = empty( $permalink_path ) ? '/' : $permalink_path;

			if ( khse_normalize_cache_uri( $requested_path ) === khse_normalize_cache_uri( $permalink_path ) ) {
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
function khse_ajax_clear_cache_callback() {
	khse_ajax_dashboard_guard( 'khse_clear_cache', 'manage_options' );
	khse_ajax_clear_cache_response();
}

/**
 * AJAX callback for purging one current page.
 *
 * @return void
 */
function khse_ajax_purge_current_page_callback() {
	khse_ajax_dashboard_guard( 'khse_purge_current_page', 'edit_posts' );

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $url ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Current page URL is empty.', 'kh-speed-engine' ),
			)
		);
	}

	$home_host = parse_url( home_url( '/' ), PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );

	if ( ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $home_host ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Only same-site URLs can be purged.', 'kh-speed-engine' ),
			)
		);
	}

	if ( ! khse_can_purge_url( $url ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'kh-speed-engine' ),
			)
		);
	}

	$parsed = parse_url( $url );

	if ( false === $parsed ) {
		wp_send_json_error(
			array(
				'message' => __( 'This URL is not a cacheable page.', 'kh-speed-engine' ),
			)
		);
	}

	$query = isset( $parsed['query'] ) ? $parsed['query'] : '';

	if ( ! khse_is_cacheable_query_string( $query ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'This URL is not a cacheable page.', 'kh-speed-engine' ),
			)
		);
	}

	$path = isset( $parsed['path'] ) ? $parsed['path'] : '';
	$path = empty( $path ) ? '/' : $path;

	if ( ! khse_is_cacheable_document_path( $path ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'This URL is not a cacheable page.', 'kh-speed-engine' ),
			)
		);
	}

	$result = khse_purge_cache_uri( $path );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the purged URL path. */
				__( 'Cache purged for current page: %s', 'kh-speed-engine' ),
				$result['uri']
			),
			'status'  => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard status callback.
 *
 * @return void
 */
function khse_ajax_dashboard_status_callback() {
	khse_ajax_dashboard_guard();

	wp_send_json_success(
		array(
			'status' => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Test the selected cache driver.
 *
 * @return void
 */
function khse_ajax_dashboard_test_driver_callback() {
	khse_ajax_dashboard_guard();

	$driver = isset( $_POST['driver'] ) ? sanitize_key( wp_unslash( $_POST['driver'] ) ) : get_option( 'khse_option_driver', 'file' );
	$result = khse_dashboard_test_driver_connection( $driver );

	$response = array(
		'message'    => $result['message'],
		'driverTest' => $result,
		'status'     => khse_dashboard_get_status_data(),
	);

	if ( ! empty( $result['passed'] ) ) {
		wp_send_json_success( $response );
	}

	wp_send_json_error( $response );
}

/**
 * Test a URL against the page cache policy.
 *
 * @return void
 */
function khse_ajax_dashboard_test_url_callback() {
	khse_ajax_dashboard_guard();

	$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';

	if ( '' === $url ) {
		wp_send_json_error(
			array(
				'message' => __( 'Enter a URL or path to test.', 'kh-speed-engine' ),
			)
		);
	}

	$config              = khse_get_config_from_schema();
	$config['exclusion'] = khse_get_runtime_exclusion_config();

	wp_send_json_success( khse_evaluate_cache_url_policy( $url, $config ) );
}

/**
 * Return the Nginx direct cache snippet and target directory.
 *
 * @return void
 */
function khse_ajax_dashboard_nginx_snippet_callback() {
	khse_ajax_dashboard_guard();

	if (
		! function_exists( 'khse_get_nginx_direct_cache_snippet' ) ||
		! function_exists( 'khse_is_nginx_direct_cache_enabled' ) ||
		! function_exists( 'khse_get_nginx_static_cache_relative_dir' )
	) {
		wp_send_json_error(
			array(
				'message' => __( 'Nginx cache configuration is unavailable.', 'kh-speed-engine' ),
			)
		);
	}

	wp_send_json_success(
		array(
			'snippet'   => khse_get_nginx_direct_cache_snippet(),
			'enabled'   => khse_is_nginx_direct_cache_enabled(),
			'directory' => khse_get_nginx_static_cache_relative_dir(),
		)
	);
}

/**
 * Normalize a yes/no setting value.
 *
 * @param mixed  $value   Incoming value.
 * @param string $default Default value.
 *
 * @return string
 */
function khse_ajax_dashboard_yes_no( $value, $default = 'no' ) {
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
function khse_ajax_dashboard_enable_disable( $value, $default = 'disable' ) {
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
function khse_ajax_dashboard_connection_type( $value ) {
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
function khse_ajax_dashboard_save_toggle_map( $items, $option_key ) {
	if ( ! is_array( $items ) ) {
		return;
	}

	$value = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['value'] ) ) {
			continue;
		}

		if ( 'yes' === khse_ajax_dashboard_yes_no( isset( $item['enabled'] ) ? $item['enabled'] : 'no' ) ) {
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
function khse_ajax_dashboard_apply_option_schema( $incoming, $schema ) {
	foreach ( $schema as $field => $rule ) {
		$value = array_key_exists( $field, $incoming ) ? $incoming[ $field ] : $rule['default'];

		switch ( $rule['sanitize'] ) {
			case 'yes_no':
				$value = khse_ajax_dashboard_yes_no( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'no' );
				break;

			case 'enable_disable':
				$value = khse_ajax_dashboard_enable_disable( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'disable' );
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
function khse_ajax_dashboard_save_settings_callback() {
	khse_ajax_dashboard_guard();

	$raw      = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$settings = json_decode( (string) $raw, true );

	if ( ! is_array( $settings ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Settings payload is invalid.', 'kh-speed-engine' ),
			)
		);
	}

	if ( isset( $settings['cache'] ) && is_array( $settings['cache'] ) ) {
		$cache = $settings['cache'];

		khse_ajax_dashboard_apply_option_schema( $cache, khse_get_settings_section_schema( 'cache' ) );

		if ( isset( $cache['driverAdvanced'] ) && is_array( $cache['driverAdvanced'] ) ) {
			$advanced = $cache['driverAdvanced'];
			$file     = isset( $advanced['file'] ) && is_array( $advanced['file'] ) ? $advanced['file'] : array();
			$redis    = isset( $advanced['redis'] ) && is_array( $advanced['redis'] ) ? $advanced['redis'] : array();
			$memcache = isset( $advanced['memcached'] ) && is_array( $advanced['memcached'] ) ? $advanced['memcached'] : ( isset( $advanced['memcache'] ) && is_array( $advanced['memcache'] ) ? $advanced['memcache'] : array() );
			$mongodb  = isset( $advanced['mongodb'] ) && is_array( $advanced['mongodb'] ) ? $advanced['mongodb'] : ( isset( $advanced['mongo'] ) && is_array( $advanced['mongo'] ) ? $advanced['mongo'] : array() );
			$conn     = isset( $advanced['connection'] ) && is_array( $advanced['connection'] ) ? $advanced['connection'] : array();

			update_option(
				'khse_option_advanced_driver_file',
				array(
					'compress'           => khse_ajax_dashboard_yes_no( isset( $file['compress'] ) ? $file['compress'] : 'no' ),
					'compress_threshold' => max( 0, absint( isset( $file['compress_threshold'] ) ? $file['compress_threshold'] : 4096 ) ),
					'compress_level'     => max( 1, min( 9, absint( isset( $file['compress_level'] ) ? $file['compress_level'] : 1 ) ) ),
				)
			);
			update_option(
				'khse_option_advanced_driver_redis',
				array(
					'host'               => sanitize_text_field( isset( $redis['host'] ) ? $redis['host'] : '127.0.0.1' ),
					'port'               => max( 1, absint( isset( $redis['port'] ) ? $redis['port'] : 6379 ) ),
					'user'               => sanitize_text_field( isset( $redis['user'] ) ? $redis['user'] : '' ),
					'pass'               => sanitize_text_field( isset( $redis['pass'] ) ? $redis['pass'] : '' ),
					'database'           => max( 0, min( 15, absint( isset( $redis['database'] ) ? $redis['database'] : 0 ) ) ),
					'unix_socket'        => sanitize_text_field( isset( $redis['unix_socket'] ) ? $redis['unix_socket'] : '' ),
					'compress'           => khse_ajax_dashboard_yes_no( isset( $redis['compress'] ) ? $redis['compress'] : 'yes', 'yes' ),
					'compress_threshold' => max( 0, absint( isset( $redis['compress_threshold'] ) ? $redis['compress_threshold'] : 1024 ) ),
					'compress_level'     => max( 1, min( 9, absint( isset( $redis['compress_level'] ) ? $redis['compress_level'] : 6 ) ) ),
				)
			);
			update_option(
				'khse_option_advanced_driver_memcached',
				array(
					'host'        => sanitize_text_field( isset( $memcache['host'] ) ? $memcache['host'] : '127.0.0.1' ),
					'port'        => max( 1, absint( isset( $memcache['port'] ) ? $memcache['port'] : 11211 ) ),
					'unix_socket' => sanitize_text_field( isset( $memcache['unix_socket'] ) ? $memcache['unix_socket'] : '' ),
				)
			);
			update_option(
				'khse_option_advanced_driver_mongodb',
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
			update_option( 'khse_option_advanced_driver_redis_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['redis'] ) ? $conn['redis'] : 'tcp' ) );
			update_option( 'khse_option_advanced_driver_memcached_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['memcached'] ) ? $conn['memcached'] : ( isset( $conn['memcache'] ) ? $conn['memcache'] : 'tcp' ) ) );
			update_option( 'khse_option_advanced_driver_mongodb_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['mongo'] ) ? $conn['mongo'] : ( isset( $conn['mongodb'] ) ? $conn['mongodb'] : 'tcp' ) ) );
		}
	}

	if ( isset( $settings['preload'] ) && is_array( $settings['preload'] ) ) {
		$preload = $settings['preload'];

		khse_ajax_dashboard_apply_option_schema( $preload, khse_get_settings_section_schema( 'preload' ) );
		khse_ajax_dashboard_save_toggle_map( isset( $preload['postTypes'] ) ? $preload['postTypes'] : array(), 'khse_option_post_types' );
		khse_ajax_dashboard_save_toggle_map( isset( $preload['archives'] ) ? $preload['archives'] : array(), 'khse_option_post_archives' );
	}

	if ( isset( $settings['performance'] ) && is_array( $settings['performance'] ) ) {
		$incoming = $settings['performance'];
		$current  = khse_get_page_optimization_settings();
		$defaults = khse_get_default_page_optimization_settings();
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
				$current[ $key ] = khse_ajax_dashboard_yes_no( $incoming[ $key ], isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'no' );
			}
		}

		$current['critical_image_count'] = max( 0, min( 5, absint( isset( $incoming['critical_image_count'] ) ? $incoming['critical_image_count'] : $current['critical_image_count'] ) ) );
		$current['external_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['external_ucss_max_file_size'] ) ? $incoming['external_ucss_max_file_size'] : $current['external_ucss_max_file_size'] ) ) );
		$current['local_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['local_ucss_max_file_size'] ) ? $incoming['local_ucss_max_file_size'] : $current['local_ucss_max_file_size'] ) ) );
		$current['js_analysis_max_script_bytes'] = max( 16384, min( 1048576, absint( isset( $incoming['js_analysis_max_script_bytes'] ) ? $incoming['js_analysis_max_script_bytes'] : $current['js_analysis_max_script_bytes'] ) ) );
		$current['js_analysis_max_total_bytes'] = max( 65536, min( 4194304, absint( isset( $incoming['js_analysis_max_total_bytes'] ) ? $incoming['js_analysis_max_total_bytes'] : $current['js_analysis_max_total_bytes'] ) ) );
		$current['ucss_safelist']        = sanitize_textarea_field( isset( $incoming['ucss_safelist'] ) ? $incoming['ucss_safelist'] : $current['ucss_safelist'] );
		$current['media_exclusions']     = sanitize_textarea_field( isset( $incoming['media_exclusions'] ) ? $incoming['media_exclusions'] : $current['media_exclusions'] );
		$current['js_exclusions']        = sanitize_textarea_field( isset( $incoming['js_exclusions'] ) ? $incoming['js_exclusions'] : $current['js_exclusions'] );

		update_option( 'khse_option_page_optimization', $current );
	}

	if ( isset( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
		$rules = $settings['rules'];

		khse_ajax_dashboard_apply_option_schema( $rules, khse_get_settings_section_schema( 'rules' ) );
	}

	if ( isset( $settings['statistics'] ) && is_array( $settings['statistics'] ) ) {
		khse_ajax_dashboard_apply_option_schema( $settings['statistics'], khse_get_settings_section_schema( 'statistics' ) );
	}

	if ( isset( $settings['benchmark'] ) && is_array( $settings['benchmark'] ) ) {
		$benchmark = $settings['benchmark'];

		khse_ajax_dashboard_apply_option_schema( $benchmark, khse_get_settings_section_schema( 'benchmark' ) );
	}

	khse_ensure_permalink_structure();
	$runtime_synced = khse_sync_expert_mode_runtime();
	khse_clear_all_cache();

	$message = $runtime_synced
		? __( 'Settings saved.', 'kh-speed-engine' )
		: __( 'Settings saved, but the Expert Mode runtime file could not be written. Check the runtime directory permissions.', 'kh-speed-engine' );

	wp_send_json_success(
		array(
			'message'       => $message,
			'runtimeSynced' => (bool) $runtime_synced,
			'status'        => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard clear cache callback.
 *
 * @return void
 */
function khse_ajax_dashboard_clear_cache_callback() {
	khse_ajax_dashboard_guard();
	khse_ajax_clear_cache_response();
}

/**
 * Dashboard clear one cache type callback.
 *
 * @return void
 */
function khse_ajax_dashboard_clear_cache_type_callback() {
	khse_ajax_dashboard_guard();

	$cache_type = isset( $_POST['cacheType'] ) ? sanitize_key( wp_unslash( $_POST['cacheType'] ) ) : '';
	$labels     = khse_get_cache_type_list();
	$list       = array_keys( $labels );

	if ( ! in_array( $cache_type, $list, true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Unknown cache type.', 'kh-speed-engine' ),
			)
		);
	}

	$rows = khse_clear_cache_type( $cache_type );
	$label = wp_strip_all_tags( $labels[ $cache_type ] );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: 1: cache type, 2: number of rows. */
				__( '%1$s cache cleared. %2$s rows deleted.', 'kh-speed-engine' ),
				$label,
				$rows
			),
			'status'  => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard lazy report page callback.
 *
 * @return void
 */
function khse_ajax_dashboard_reports_callback() {
	khse_ajax_dashboard_guard();

	$offset        = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
	$page_size     = 5;
	$total_reports = khse_count_page_optimization_reports();
	$reports       = khse_dashboard_normalize_optimization_reports(
		khse_get_page_optimization_reports( $page_size, $offset )
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
function khse_ajax_dashboard_preload_callback() {
	khse_ajax_dashboard_guard();

	$count = khse_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( '%s preload URLs queued for background processing.', 'kh-speed-engine' ),
				$count
			),
			'status'  => khse_dashboard_get_status_data(),
		)
	);
}

/**
 * Dashboard purge homepage callback.
 *
 * @return void
 */
function khse_ajax_dashboard_purge_homepage_callback() {
	khse_ajax_dashboard_guard();

	khse_purge_cache_uri( '/' );
	$count = khse_preload_cache();

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of queued URLs. */
				__( 'Homepage cache purged. %s priority preload URLs queued.', 'kh-speed-engine' ),
				$count
			),
			'status'  => khse_dashboard_get_status_data(),
		)
	);
}
