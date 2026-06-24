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
require_once SCM_PLUGIN_DIR . 'vendor/autoload.php';

add_action( 'wp_ajax_scm_action_clear_cache', 'scm_ajax_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_purge_current_page', 'scm_ajax_purge_current_page_callback' );
add_action( 'wp_ajax_scm_action_dashboard_status', 'scm_ajax_dashboard_status_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache', 'scm_ajax_dashboard_clear_cache_callback' );
add_action( 'wp_ajax_scm_action_dashboard_clear_cache_type', 'scm_ajax_dashboard_clear_cache_type_callback' );
add_action( 'wp_ajax_scm_action_dashboard_reports', 'scm_ajax_dashboard_reports_callback' );
add_action( 'wp_ajax_scm_action_dashboard_preload', 'scm_ajax_dashboard_preload_callback' );
add_action( 'wp_ajax_scm_action_dashboard_purge_homepage', 'scm_ajax_dashboard_purge_homepage_callback' );
add_action( 'wp_ajax_scm_action_dashboard_queue_images', 'scm_ajax_dashboard_queue_images_callback' );
add_action( 'wp_ajax_scm_action_dashboard_cancel_queue_images', 'scm_ajax_dashboard_cancel_queue_images_callback' );
add_action( 'wp_ajax_scm_action_dashboard_test_driver', 'scm_ajax_dashboard_test_driver_callback' );
add_action( 'wp_ajax_scm_action_dashboard_save_settings', 'scm_ajax_dashboard_save_settings_callback' );

/**
 * Validate dashboard AJAX request.
 *
 * @return void
 */
function scm_ajax_dashboard_guard() {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'scm_dashboard_' . scm_get_dir_hash() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Token has been rejected.', 'ams-cache' ),
			)
		);
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Access denied.', 'ams-cache' ),
			)
		);
	}
}

/**
 * AJAX callback.
 *
 * @return void
 */
function scm_ajax_clear_cache_callback() {

	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'scm_clear_cache_' . scm_get_dir_hash() ) ) {
		echo __( 'Token has been rejected.', 'ams-cache' );
		wp_die();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		echo __( 'Access denied.', 'ams-cache' );
		wp_die();
	}

	$rows = scm_clear_all_cache();

	if ( $rows > 0 ) {
		// translators: %s is the number of rows.
		echo sprintf( __( '%s rows has been deleted.', 'ams-cache' ), $rows );
	} else {
		echo __( 'There is no cache on your site currently.', 'ams-cache' );
	}

	wp_die();
}

/**
 * AJAX callback for purging one current page.
 *
 * @return void
 */
function scm_ajax_purge_current_page_callback() {

	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'scm_purge_current_page_' . scm_get_dir_hash() ) ) {
		echo __( 'Token has been rejected.', 'ams-cache' );
		wp_die();
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		echo __( 'Access denied.', 'ams-cache' );
		wp_die();
	}

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $url ) ) {
		echo __( 'Current page URL is empty.', 'ams-cache' );
		wp_die();
	}

	$home_host = parse_url( home_url( '/' ), PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );

	if ( ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $home_host ) ) {
		echo __( 'Only same-site URLs can be purged.', 'ams-cache' );
		wp_die();
	}

	$path = parse_url( $url, PHP_URL_PATH );
	$path = empty( $path ) ? '/' : $path;

	if ( ! scm_is_cacheable_document_path( $path ) ) {
		echo __( 'This URL is not a cacheable page.', 'ams-cache' );
		wp_die();
	}

	$result = scm_purge_cache_uri( $path );

	echo sprintf(
		/* translators: %s is the purged URL path. */
		__( 'Cache purged for current page: %s', 'ams-cache' ),
		$result['uri']
	);

	wp_die();
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
		$driver  = isset( $cache['driver'] ) ? sanitize_key( $cache['driver'] ) : get_option( 'scm_option_driver', 'file' );

		update_option( 'scm_option_caching_status', scm_ajax_dashboard_enable_disable( isset( $cache['cachingStatus'] ) ? $cache['cachingStatus'] : get_option( 'scm_option_caching_status', 'disable' ) ) );
		update_option( 'scm_option_driver', in_array( $driver, $drivers, true ) ? $driver : 'file' );
		update_option( 'scm_option_ttl_mechanism', scm_ajax_dashboard_enable_disable( isset( $cache['ttlMechanism'] ) ? $cache['ttlMechanism'] : get_option( 'scm_option_ttl_mechanism', 'enable' ), 'enable' ) );
		update_option( 'scm_option_ttl', max( 300, min( 2592000, absint( isset( $cache['ttl'] ) ? $cache['ttl'] : get_option( 'scm_option_ttl', 86400 ) ) ) ) );
		update_option( 'scm_option_cache_key_prefix', sanitize_key( isset( $cache['cacheKeyPrefix'] ) ? $cache['cacheKeyPrefix'] : scm_get_cache_key_prefix() ) );
		update_option( 'scm_option_cache_max_entries', max( 0, absint( isset( $cache['maxEntries'] ) ? $cache['maxEntries'] : scm_get_cache_max_entries() ) ) );
		update_option( 'scm_option_expert_mode_status', scm_ajax_dashboard_enable_disable( isset( $cache['expertModeStatus'] ) ? $cache['expertModeStatus'] : get_option( 'scm_option_expert_mode_status', 'disable' ) ) );
		update_option( 'scm_option_nginx_direct_cache_status', scm_ajax_dashboard_yes_no( isset( $cache['nginxDirect'] ) ? $cache['nginxDirect'] : get_option( 'scm_option_nginx_direct_cache_status', 'no' ) ) );
		update_option( 'scm_option_html_debug_comment', scm_ajax_dashboard_yes_no( isset( $cache['debugComment'] ) ? $cache['debugComment'] : get_option( 'scm_option_html_debug_comment', 'yes' ), 'yes' ) );
		update_option( 'scm_option_visibility_login_user', 'no' );
		update_option( 'scm_option_visibility_guest', 'yes' );

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
		$preload = $settings['preload'];

		update_option( 'scm_option_preload_cache', scm_ajax_dashboard_yes_no( isset( $preload['enabled'] ) ? $preload['enabled'] : get_option( 'scm_option_preload_cache', 'no' ) ) );
		update_option( 'scm_option_preload_limit', max( 1, min( 1000, absint( isset( $preload['limit'] ) ? $preload['limit'] : get_option( 'scm_option_preload_limit', 50 ) ) ) ) );
		update_option( 'scm_option_preload_homepage_links', scm_ajax_dashboard_yes_no( isset( $preload['crawlHomepage'] ) ? $preload['crawlHomepage'] : get_option( 'scm_option_preload_homepage_links', 'yes' ), 'yes' ) );
		update_option( 'scm_option_post_homepage', scm_ajax_dashboard_yes_no( isset( $preload['homepage'] ) ? $preload['homepage'] : get_option( 'scm_option_post_homepage', 'yes' ), 'yes' ) );
		scm_ajax_dashboard_save_toggle_map( isset( $preload['postTypes'] ) ? $preload['postTypes'] : array(), 'scm_option_post_types' );
		scm_ajax_dashboard_save_toggle_map( isset( $preload['archives'] ) ? $preload['archives'] : array(), 'scm_option_post_archives' );
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
			'image_optimization',
			'image_optimize_on_upload',
			'image_rewrite_html',
			'image_placeholders',
			'image_remote_rewrite',
		);

		foreach ( $boolean_keys as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$current[ $key ] = scm_ajax_dashboard_yes_no( $incoming[ $key ], isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'no' );
			}
		}

		$current['critical_image_count'] = max( 0, min( 5, absint( isset( $incoming['critical_image_count'] ) ? $incoming['critical_image_count'] : $current['critical_image_count'] ) ) );
		$current['external_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['external_ucss_max_file_size'] ) ? $incoming['external_ucss_max_file_size'] : $current['external_ucss_max_file_size'] ) ) );
		$current['bun_path']             = sanitize_text_field( isset( $incoming['bun_path'] ) ? $incoming['bun_path'] : $current['bun_path'] );
		$current['purgecss_path']        = sanitize_text_field( isset( $incoming['purgecss_path'] ) ? $incoming['purgecss_path'] : $current['purgecss_path'] );
		$current['ucss_safelist']        = sanitize_textarea_field( isset( $incoming['ucss_safelist'] ) ? $incoming['ucss_safelist'] : $current['ucss_safelist'] );
		$current['media_exclusions']     = sanitize_textarea_field( isset( $incoming['media_exclusions'] ) ? $incoming['media_exclusions'] : $current['media_exclusions'] );
		$current['js_exclusions']        = sanitize_textarea_field( isset( $incoming['js_exclusions'] ) ? $incoming['js_exclusions'] : $current['js_exclusions'] );
		$current['image_quality']        = max( 1, min( 100, absint( isset( $incoming['image_quality'] ) ? $incoming['image_quality'] : $current['image_quality'] ) ) );
		$current['image_batch_size']     = max( 1, min( 20, absint( isset( $incoming['image_batch_size'] ) ? $incoming['image_batch_size'] : $current['image_batch_size'] ) ) );
		$current['image_formats']        = array( 'webp' );
		$current['image_primary_format'] = 'webp';

		update_option( 'scm_option_page_optimization', $current );
	}

	if ( isset( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
		$rules = $settings['rules'];

		update_option( 'scm_option_exclusion_status', scm_ajax_dashboard_yes_no( isset( $rules['enabled'] ) ? $rules['enabled'] : get_option( 'scm_option_exclusion_status', 'no' ) ) );
		update_option( 'scm_option_excluded_list', sanitize_textarea_field( isset( $rules['excludedList'] ) ? $rules['excludedList'] : get_option( 'scm_option_excluded_list', '' ) ) );
		update_option( 'scm_option_excluded_get_vars', sanitize_textarea_field( isset( $rules['getVars'] ) ? $rules['getVars'] : get_option( 'scm_option_excluded_get_vars', '' ) ) );
		update_option( 'scm_option_excluded_post_vars', sanitize_textarea_field( isset( $rules['postVars'] ) ? $rules['postVars'] : get_option( 'scm_option_excluded_post_vars', '' ) ) );
		update_option( 'scm_option_excluded_cookie_vars', sanitize_textarea_field( isset( $rules['cookieVars'] ) ? $rules['cookieVars'] : get_option( 'scm_option_excluded_cookie_vars', '' ) ) );
	}

	if ( isset( $settings['statistics'] ) && is_array( $settings['statistics'] ) ) {
		update_option( 'scm_option_statistics_status', scm_ajax_dashboard_enable_disable( isset( $settings['statistics']['enabled'] ) ? $settings['statistics']['enabled'] : get_option( 'scm_option_statistics_status', 'disable' ) ) );
	}

	if ( isset( $settings['benchmark'] ) && is_array( $settings['benchmark'] ) ) {
		$benchmark = $settings['benchmark'];
		$display   = array( 'text', 'icon', 'both' );
		$widget_display = isset( $benchmark['widgetDisplay'] ) ? sanitize_key( $benchmark['widgetDisplay'] ) : '';
		$footer_display = isset( $benchmark['footerDisplay'] ) ? sanitize_key( $benchmark['footerDisplay'] ) : '';

		update_option( 'scm_option_benchmark_widget', scm_ajax_dashboard_yes_no( isset( $benchmark['widget'] ) ? $benchmark['widget'] : get_option( 'scm_option_benchmark_widget', 'no' ) ) );
		update_option( 'scm_option_benchmark_footer_text', scm_ajax_dashboard_yes_no( isset( $benchmark['footer'] ) ? $benchmark['footer'] : get_option( 'scm_option_benchmark_footer_text', 'no' ) ) );
		update_option( 'scm_option_benchmark_widget_display', in_array( $widget_display, $display, true ) ? $widget_display : 'both' );
		update_option( 'scm_option_benchmark_footer_text_display', in_array( $footer_display, $display, true ) ? $footer_display : 'text' );
	}

	if ( isset( $settings['woocommerce'] ) && is_array( $settings['woocommerce'] ) ) {
		$woocommerce = $settings['woocommerce'];

		update_option( 'scm_option_woocommerce_status', scm_ajax_dashboard_yes_no( isset( $woocommerce['enabled'] ) ? $woocommerce['enabled'] : get_option( 'scm_option_woocommerce_status', 'no' ) ) );
		update_option( 'scm_option_woocommerce_event_payment_complete', scm_ajax_dashboard_yes_no( isset( $woocommerce['paymentComplete'] ) ? $woocommerce['paymentComplete'] : get_option( 'scm_option_woocommerce_event_payment_complete', 'no' ) ) );
	}

	scm_sync_expert_mode_runtime();

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

	$rows = scm_clear_all_cache();
	scm_schedule_preload_cache( true );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the number of rows. */
				__( '%s rows deleted. Background preload queued.', 'ams-cache' ),
				$rows
			),
			'status'  => scm_dashboard_get_status_data(),
		)
	);
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
				__( '%s preload URLs queued. First batch started.', 'ams-cache' ),
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

/**
 * Dashboard image optimizer queue callback.
 *
 * @return void
 */
function scm_ajax_dashboard_cancel_queue_images_callback() {
	scm_ajax_dashboard_guard();
	update_option( 'scm_image_optimization_queue_cancelled', time() );
	update_option( 'scm_image_optimization_queue_total', 0 );
	delete_option( 'scm_image_optimization_queue' );
	delete_option( 'scm_image_optimization_offloaded_count' );
	delete_option( 'scm_image_optimization_reoffloaded_count' );
	wp_clear_scheduled_hook( 'scm_process_image_optimization_queue' );
	wp_send_json_success( array(
		'message' => __( 'Image optimization queue cancelled.', 'ams-cache' ),
		'status'  => scm_dashboard_get_status_data(),
	) );
}

function scm_ajax_dashboard_queue_images_callback() {
	scm_ajax_dashboard_guard();

	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] ) {
		wp_send_json_error(
			array(
				'message' => __( 'Image optimization is disabled.', 'ams-cache' ),
			)
		);
	}

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$offloaded_count = 0;
	$kho_count       = 0;
	$skipped_optimized = 0;
	$to_enqueue       = array();

	foreach ( $attachments as $attachment_id ) {
		$already_opt = get_post_meta( $attachment_id, '_ams_cache_image_optimization', true );
		if ( ! empty( $already_opt['generated'] ) || ! empty( $already_opt['primaryConverted'] ) ) {
			$skipped_optimized++;
			continue;
		}
		$offload_info = scm_get_attachment_offload_info( $attachment_id );

		if ( $offload_info['offloaded'] ) {
			$offloaded_count++;

			if ( false !== stripos( $offload_info['provider'], 'KH Offloader' ) ) {
				$kho_count++;
			}
		}

		$to_enqueue[] = (int) $attachment_id;
	}

	// Batch-enqueue: one read + one write instead of 200+200.
	scm_enqueue_image_optimization_batch( $to_enqueue );

	scm_process_image_optimization_queue();

	$message = sprintf(
		/* translators: %s is the number of queued attachments. */
		__( '%s recent image attachments queued for optimization. First batch of %s processed.', 'ams-cache' ),
		number_format_i18n( count( $attachments ) ),
		number_format_i18n( scm_get_page_optimization_settings()['image_batch_size'] )
	);

	$queue_total   = (int) get_option( 'scm_image_optimization_queue_total', 0 );
	$queue_remaining = (int) count( get_option( 'scm_image_optimization_queue', array() ) );
	$queue_completed = max( 0, $queue_total - $queue_remaining );

	if ( $queue_total > 0 ) {
		$message .= ' ' . sprintf(
			/* translators: %1$s completed, %2$s total, %3$s remaining. */
			__( 'Progress: %1$s of %2$s completed, %3$s remaining.', 'ams-cache' ),
			number_format_i18n( $queue_completed ),
			number_format_i18n( $queue_total ),
			number_format_i18n( $queue_remaining )
		);
	}

	if ( $skipped_optimized > 0 ) {
		$message .= ' ' . sprintf(
			/* translators: %s is the number of already-optimized attachments. */
			__( '%s already optimized — skipped.', 'ams-cache' ),
			number_format_i18n( $skipped_optimized )
		);
	}
	if ( $offloaded_count > 0 ) {
		if ( $kho_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %1$s is the total offloaded count, %2$s is the KH Offloader count. */
				__( '%1$s are offloaded (%2$s via KH Offloader). AMS Cache will download, optimize to WebP, and re-offload these automatically.', 'ams-cache' ),
				number_format_i18n( $offloaded_count ),
				number_format_i18n( $kho_count )
			);
		} else {
			$message .= ' ' . sprintf(
				/* translators: %s is the number of offloaded attachments. */
				__( '%s are offloaded. AMS Cache will attempt to download and optimize these.', 'ams-cache' ),
				number_format_i18n( $offloaded_count )
			);
		}
	}

	wp_send_json_success(
		array(
			'message' => $message,
			'status'  => scm_dashboard_get_status_data(),
		)
	);
}
