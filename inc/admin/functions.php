<?php
/**
 * AMS Cache - functions used in admin scope.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

/**
 * Load view files.
 *
 * @param string $template_path The specific template's path.
 * @param array  $data          Data is being passed to.
 * @return string
 */
function scm_load_view( $template_path, $data = array() ) {
	$template_path  = str_replace( '_', '-', $template_path );
	$view_file_path = SCM_PLUGIN_DIR . 'inc/admin/views/' . $template_path . '.php';

	if ( file_exists( $view_file_path ) ) {
		if ( ! empty( $data ) ) {
			extract( $data ); // phpcs:ignore
		}

		ob_start();
		include $view_file_path;
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}
	return '';
}

/**
 * Get the cache type list.
 *
 * @return array
 */
function scm_get_cache_type_list( $get_key = false ) {
	$archive_note     = ' <small class="scm-badge">' . __( 'Archive', 'ams-cache' ) . '</small>';
	$woocommerce_note = ' <small class="scm-badge">' . __( 'WooCommerce', 'ams-cache' ) . '</small>';

	$list = array(
		'homepage' => __( 'Homepage', 'ams-cache' ),
		'post'     => __( 'Post', 'ams-cache' ),
		'page'     => __( 'Page', 'ams-cache' ),
	);

	$custom_post_types = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects',
		'and'
	);

	foreach ( $custom_post_types as $post_type ) {
		$list[ $post_type->name ] = $post_type->labels->singular_name;
	}

	$list['category'] = __( 'Category', 'ams-cache' ) . $archive_note;
	$list['tag']      = __( 'Tag', 'ams-cache' ) . $archive_note;
	$list['date']     = __( 'Date', 'ams-cache' ) . $archive_note;
	$list['author']   = __( 'Author', 'ams-cache' ) . $archive_note;

	$custom_post_type_archives = get_post_types(
		array(
			'public'      => true,
			'has_archive' => true,
			'_builtin'    => false,
		),
		'objects',
		'and'
	);

	foreach ( $custom_post_type_archives as $post_type ) {
		$list[ 'archive_' . $post_type->name ] = sprintf(
			/* translators: %s is the post type singular label. */
			__( 'Archive for %s', 'ams-cache' ),
			$post_type->labels->singular_name
		) . $archive_note;
	}

	$list['product']       = __( 'Product', 'ams-cache' ) . $woocommerce_note;
	$list['product_tag']   = __( 'Product tag', 'ams-cache' ) . $woocommerce_note . $archive_note;
	$list['product_cat']   = __( 'Product category', 'ams-cache' ) . $woocommerce_note . $archive_note;
	$list['uncategorised'] = __( 'Uncategorised', 'ams-cache' );

	if ( $get_key ) {
		return array_keys( $list );
	}

	return $list;
}

/**
 * Format dashboard timestamps.
 *
 * @param int $timestamp Unix timestamp.
 *
 * @return string
 */
function scm_dashboard_format_time( $timestamp ) {
	$timestamp = (int) $timestamp;

	if ( $timestamp <= 0 ) {
		return __( 'Never', 'ams-cache' );
	}

	return sprintf(
		/* translators: %s is a human time difference. */
		__( '%s ago', 'ams-cache' ),
		human_time_diff( $timestamp, time() )
	);
}

/**
 * Get one cache type's stats.
 *
 * @param string $type Cache type.
 *
 * @return array
 */
function scm_dashboard_get_stats_for_type( $type ) {
	$dir  = scm_get_stats_dir( $type );
	$rows = 0;
	$size = 0;
	$seen = array();

	if ( is_dir( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
				continue;
			}

			$stats = scm_read_stats_file( $file->getPathname() );

			if ( 'homepage' === $type && ! empty( $stats['uri'] ) && ! scm_is_homepage_uri( $stats['uri'] ) ) {
				continue;
			}

			$identity = empty( $stats['uri'] ) ? $file->getFilename() : scm_normalize_cache_uri( $stats['uri'] );

			if ( isset( $seen[ $identity ] ) ) {
				continue;
			}

			$seen[ $identity ] = true;
			$rows++;
			$size += (int) $stats['size'];
		}
	}

	return array(
		'rows'      => $rows,
		'size'      => $size,
		'sizeLabel' => size_format( $size, 2 ),
	);
}

/**
 * Get all stats data for the React dashboard.
 *
 * @return array
 */
function scm_dashboard_get_stats_summary() {
	$rows       = array();
	$total_rows = 0;
	$total_size = 0;

	foreach ( scm_get_cache_type_list() as $type => $label ) {
		$stats = scm_dashboard_get_stats_for_type( $type );

		$total_rows += $stats['rows'];
		$total_size += $stats['size'];

		$rows[] = array(
			'type'      => $type,
			'label'     => wp_strip_all_tags( $label ),
			'rows'      => $stats['rows'],
			'size'      => $stats['size'],
			'sizeLabel' => $stats['sizeLabel'],
		);
	}

	return array(
		'rows'           => $rows,
		'totalRows'      => $total_rows,
		'totalSize'      => $total_size,
		'totalSizeLabel' => size_format( $total_size, 2 ),
	);
}

/**
 * Normalize optimization reports for dashboard output.
 *
 * @param array $reports Raw reports.
 *
 * @return array
 */
function scm_dashboard_normalize_optimization_reports( $reports ) {
	$latest = array();

	foreach ( $reports as $report ) {
		$features = array();

		if ( ! empty( $report['features'] ) && is_array( $report['features'] ) ) {
			foreach ( $report['features'] as $key => $feature ) {
				$features[] = array(
					'key'     => $key,
					'enabled' => ! empty( $feature['enabled'] ),
					'status'  => isset( $feature['status'] ) ? $feature['status'] : 'unknown',
					'detail'  => isset( $feature['detail'] ) ? scm_dashboard_humanize_report_detail( $feature['detail'] ) : '',
					'metrics' => ! empty( $feature['metrics'] ) && is_array( $feature['metrics'] ) ? $feature['metrics'] : array(),
				);
			}
		}

		$uri          = isset( $report['uri'] ) ? $report['uri'] : '/';
		$before_bytes = isset( $report['beforeBytes'] ) ? (int) $report['beforeBytes'] : 0;
		$after_bytes  = isset( $report['afterBytes'] ) ? (int) $report['afterBytes'] : 0;
		$saved_bytes  = max( 0, isset( $report['savedBytes'] ) ? (int) $report['savedBytes'] : ( $before_bytes - $after_bytes ) );
		$saved_percent = $before_bytes > 0 ? round( ( $saved_bytes / $before_bytes ) * 100, 2 ) : 0;
		$expanded_bytes = max( 0, isset( $report['expandedBytes'] ) ? (int) $report['expandedBytes'] : ( $after_bytes - $before_bytes ) );

		$latest[] = array(
			'uri'           => $uri,
			'displayUri'    => rawurldecode( $uri ),
			'dataType'      => isset( $report['dataType'] ) ? $report['dataType'] : '',
			'generatedAt'   => isset( $report['generatedAt'] ) ? $report['generatedAt'] : '',
			'beforeBytes'   => $before_bytes,
			'beforeLabel'   => isset( $report['beforeLabel'] ) ? $report['beforeLabel'] : size_format( $before_bytes, 2 ),
			'afterBytes'    => $after_bytes,
			'afterLabel'    => isset( $report['afterLabel'] ) ? $report['afterLabel'] : size_format( $after_bytes, 2 ),
			'savedBytes'    => $saved_bytes,
			'savedLabel'    => size_format( $saved_bytes, 2 ),
			'savedPercent'  => max( 0, $saved_percent ),
			'expandedBytes' => $expanded_bytes,
			'expandedLabel' => size_format( $expanded_bytes, 2 ),
			'expandedPercent' => $before_bytes > 0 ? round( ( $expanded_bytes / $before_bytes ) * 100, 2 ) : 0,
			'appliedCount'  => isset( $report['appliedCount'] ) ? (int) $report['appliedCount'] : 0,
			'overallStatus' => isset( $report['overallStatus'] ) ? $report['overallStatus'] : 'unknown',
			'features'      => $features,
		);
	}

	return $latest;
}

/**
 * Convert old raw-byte report details to readable units at render time.
 *
 * @param string $detail Stored report detail.
 *
 * @return string
 */
function scm_dashboard_humanize_report_detail( $detail ) {
	return preg_replace_callback(
		'/([0-9][0-9,.\s]*)\s+B\b/',
		function ( $matches ) {
			$bytes = (int) preg_replace( '/[^0-9]/', '', $matches[1] );

			return size_format( $bytes, 2 );
		},
		(string) $detail
	);
}

/**
 * Build recent optimization report summary.
 *
 * @return array
 */
function scm_dashboard_get_optimization_report_summary() {
	$summary_reports = scm_get_page_optimization_reports( 20 );
	$initial_reports = array_slice( $summary_reports, 0, 5 );
	$total_reports   = scm_count_page_optimization_reports();
	$applied_pages  = 0;
	$saved_bytes    = 0;
	$ucss_saved     = 0;
	$ucss_pages     = 0;
	$external_ucss_saved = 0;
	$external_ucss_pages = 0;
	$js_analyzed    = 0;
	$js_deferred    = 0;

	foreach ( $summary_reports as $report ) {
		if ( 'applied' === $report['overallStatus'] ) {
			$applied_pages++;
		}

		$saved_bytes += isset( $report['savedBytes'] ) ? (int) $report['savedBytes'] : 0;

		if ( ! empty( $report['features']['local_ucss']['metrics'] ) ) {
			$ucss_saved += isset( $report['features']['local_ucss']['metrics']['savedBytes'] ) ? (int) $report['features']['local_ucss']['metrics']['savedBytes'] : 0;

			if ( 'applied' === $report['features']['local_ucss']['status'] ) {
				$ucss_pages++;
			}
		}

		if ( ! empty( $report['features']['external_ucss']['metrics'] ) ) {
			$external_ucss_saved += isset( $report['features']['external_ucss']['metrics']['savedBytes'] ) ? (int) $report['features']['external_ucss']['metrics']['savedBytes'] : 0;

			if ( 'applied' === $report['features']['external_ucss']['status'] ) {
				$external_ucss_pages++;
			}
		}

		if ( ! empty( $report['features']['js_analysis']['metrics'] ) ) {
			$js_analyzed += isset( $report['features']['js_analysis']['metrics']['analyzed'] ) ? (int) $report['features']['js_analysis']['metrics']['analyzed'] : 0;
			$js_deferred += isset( $report['features']['js_analysis']['metrics']['deferred'] ) ? (int) $report['features']['js_analysis']['metrics']['deferred'] : 0;
		}
	}

	return array(
		'reports'       => scm_dashboard_normalize_optimization_reports( $initial_reports ),
		'reportCount'   => count( $summary_reports ),
		'totalReports'  => $total_reports,
		'loadedCount'   => count( $initial_reports ),
		'pageSize'      => 5,
		'hasMore'       => $total_reports > count( $initial_reports ),
		'appliedPages'  => $applied_pages,
		'savedBytes'    => $saved_bytes,
		'savedLabel'    => size_format( $saved_bytes, 2 ),
		'ucssSavedBytes' => $ucss_saved,
		'ucssSavedLabel' => size_format( $ucss_saved, 2 ),
		'ucssAppliedPages' => $ucss_pages,
		'externalUcssSavedBytes' => $external_ucss_saved,
		'externalUcssSavedLabel' => size_format( $external_ucss_saved, 2 ),
		'externalUcssAppliedPages' => $external_ucss_pages,
		'jsAnalyzed'    => $js_analyzed,
		'jsDeferred'    => $js_deferred,
	);
}

/**
 * Check if a cache driver looks available without opening external connections.
 *
 * @param string $driver Driver key.
 *
 * @return bool
 */
function scm_dashboard_is_driver_available( $driver ) {
	switch ( $driver ) {
		case 'file':
			return true;
		case 'redis':
			return extension_loaded( 'redis' );
		case 'memcache':
			return extension_loaded( 'memcache' );
		case 'memcached':
			return extension_loaded( 'memcached' );
		case 'apc':
			return extension_loaded( 'apc' );
		case 'apcu':
			return extension_loaded( 'apcu' );
		case 'wincache':
			return extension_loaded( 'wincache' );
		case 'mongo':
			return extension_loaded( 'mongodb' );
		case 'mysql':
			return extension_loaded( 'mysqli' ) || extension_loaded( 'pdo_mysql' );
		case 'sqlite':
			return extension_loaded( 'sqlite3' ) || extension_loaded( 'pdo_sqlite' );
		default:
			return false;
	}
}

/**
 * Get dashboard-ready driver settings.
 *
 * @return array
 */
function scm_dashboard_get_driver_advanced_settings_data() {
	$file = wp_parse_args(
		(array) get_option( 'scm_option_advanced_driver_file', array() ),
		array(
			'compress'           => 'no',
			'compress_threshold' => 4096,
			'compress_level'     => 1,
		)
	);

	$redis = wp_parse_args(
		(array) get_option( 'scm_option_advanced_driver_redis', array() ),
		array(
			'host'               => '127.0.0.1',
			'port'               => 6379,
			'user'               => '',
			'pass'               => '',
			'database'           => 0,
			'unix_socket'        => '',
			'compress'           => 'yes',
			'compress_threshold' => 1024,
			'compress_level'     => 6,
		)
	);

	$memcached = wp_parse_args(
		(array) get_option( 'scm_option_advanced_driver_memcached', array() ),
		array(
			'host'        => '127.0.0.1',
			'port'        => 11211,
			'unix_socket' => '',
		)
	);

	$mongodb = wp_parse_args(
		(array) get_option( 'scm_option_advanced_driver_mongodb', array() ),
		array(
			'host'        => '127.0.0.1',
			'port'        => 27017,
			'user'        => '',
			'pass'        => '',
			'dbname'      => 'test',
			'collection'  => 'cache_data',
			'unix_socket' => '',
		)
	);

	return array(
		'file'       => $file,
		'redis'      => $redis,
		'memcache'   => $memcached,
		'memcached'  => $memcached,
		'mongodb'    => $mongodb,
		'mongo'      => $mongodb,
		'connection' => array(
			'redis'     => get_option( 'scm_option_advanced_driver_redis_connection_type', 'tcp' ),
			'memcache'  => get_option( 'scm_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'memcached' => get_option( 'scm_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'mongo'     => get_option( 'scm_option_advanced_driver_mongodb_connection_type', 'tcp' ),
			'mongodb'   => get_option( 'scm_option_advanced_driver_mongodb_connection_type', 'tcp' ),
		),
	);
}

/**
 * Build toggle list data.
 *
 * @param array $items   value => label list.
 * @param array $enabled Enabled option map.
 *
 * @return array
 */
function scm_dashboard_build_toggle_items( $items, $enabled ) {
	$enabled = is_array( $enabled ) ? $enabled : array();
	$result  = array();

	foreach ( $items as $value => $label ) {
		$result[] = array(
			'value'   => (string) $value,
			'label'   => wp_strip_all_tags( $label ),
			'enabled' => isset( $enabled[ $value ] ) && 'yes' === $enabled[ $value ] ? 'yes' : 'no',
		);
	}

	return $result;
}

/**
 * Build preload option lists.
 *
 * @return array
 */
function scm_dashboard_get_preload_options_data() {
	$post_types = array(
		'post' => __( 'Post', 'ams-cache' ),
		'page' => __( 'Page', 'ams-cache' ),
	);

	$custom_post_types = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects'
	);

	foreach ( $custom_post_types as $post_type ) {
		$post_types[ $post_type->name ] = $post_type->label;
	}

	$archives = array(
		'category' => __( 'Category', 'ams-cache' ),
		'tag'      => __( 'Tag', 'ams-cache' ),
		'date'     => __( 'Date', 'ams-cache' ),
		'author'   => __( 'Author', 'ams-cache' ),
	);

	return array(
		'postTypes' => scm_dashboard_build_toggle_items( $post_types, get_option( 'scm_option_post_types', array() ) ),
		'homepage'  => get_option( 'scm_option_post_homepage', 'yes' ),
		'archives'  => scm_dashboard_build_toggle_items( $archives, get_option( 'scm_option_post_archives', array() ) ),
	);
}

/**
 * Build settings payload for the React dashboard.
 *
 * @return array
 */
function scm_dashboard_get_settings_data() {
	$optimization = scm_get_page_optimization_settings();
	$drivers      = array(
		array( 'value' => 'file', 'label' => __( 'File', 'ams-cache' ) ),
		array( 'value' => 'redis', 'label' => __( 'Redis', 'ams-cache' ) ),
		array( 'value' => 'memcache', 'label' => __( 'Memcache', 'ams-cache' ) ),
		array( 'value' => 'memcached', 'label' => __( 'Memcached', 'ams-cache' ) ),
		array( 'value' => 'apc', 'label' => __( 'APC', 'ams-cache' ) ),
		array( 'value' => 'apcu', 'label' => __( 'APCu', 'ams-cache' ) ),
		array( 'value' => 'wincache', 'label' => __( 'WinCache', 'ams-cache' ) ),
		array( 'value' => 'mongo', 'label' => __( 'MongoDB', 'ams-cache' ) ),
		array( 'value' => 'mysql', 'label' => __( 'MySQL', 'ams-cache' ) ),
		array( 'value' => 'sqlite', 'label' => __( 'SQLite', 'ams-cache' ) ),
	);
	$drivers      = array_map(
		function ( $driver ) {
			$driver['available'] = scm_dashboard_is_driver_available( $driver['value'] );
			return $driver;
		},
		$drivers
	);
	$preload_options = scm_dashboard_get_preload_options_data();

	return array(
		'cache'       => array(
			'cachingStatus'    => get_option( 'scm_option_caching_status', 'disable' ),
			'driver'           => get_option( 'scm_option_driver', 'file' ),
			'ttl'              => (int) get_option( 'scm_option_ttl', 86400 ),
			'ttlMechanism'     => get_option( 'scm_option_ttl_mechanism', 'enable' ),
			'cacheKeyPrefix'   => scm_get_cache_key_prefix(),
			'maxEntries'       => scm_get_cache_max_entries(),
			'expertModeStatus' => get_option( 'scm_option_expert_mode_status', 'disable' ),
			'nginxDirect'      => get_option( 'scm_option_nginx_direct_cache_status', 'no' ),
			'debugComment'     => get_option( 'scm_option_html_debug_comment', 'yes' ),
			'drivers'          => $drivers,
			'driverAdvanced'   => scm_dashboard_get_driver_advanced_settings_data(),
			'expertModeCode'   => scm_expert_mode_code_template(),
			'expertModeReady'  => scm_is_expert_mode_code_ready(),
		),
		'preload'     => array(
			'enabled'       => get_option( 'scm_option_preload_cache', 'no' ),
			'limit'         => (int) get_option( 'scm_option_preload_limit', 50 ),
			'crawlHomepage' => get_option( 'scm_option_preload_homepage_links', 'yes' ),
			'postTypes'     => $preload_options['postTypes'],
			'homepage'      => $preload_options['homepage'],
			'archives'      => $preload_options['archives'],
		),
		'performance' => $optimization,
		'rules'       => array(
			'enabled'      => get_option( 'scm_option_exclusion_status', 'no' ),
			'excludedList' => get_option( 'scm_option_excluded_list_filtered', get_option( 'scm_option_excluded_list', '' ) ),
			'getVars'      => get_option( 'scm_option_excluded_get_vars', '' ),
			'postVars'     => get_option( 'scm_option_excluded_post_vars', '' ),
			'cookieVars'   => get_option( 'scm_option_excluded_cookie_vars', '' ),
		),
		'statistics'  => array(
			'enabled' => get_option( 'scm_option_statistics_status', 'disable' ),
		),
		'benchmark'   => array(
			'widget'        => get_option( 'scm_option_benchmark_widget', 'no' ),
			'footer'        => get_option( 'scm_option_benchmark_footer_text', 'no' ),
			'widgetDisplay' => get_option( 'scm_option_benchmark_widget_display', 'both' ),
			'footerDisplay' => get_option( 'scm_option_benchmark_footer_text_display', 'text' ),
		),
		'woocommerce' => array(
			'enabled'         => get_option( 'scm_option_woocommerce_status', 'no' ),
			'paymentComplete' => get_option( 'scm_option_woocommerce_event_payment_complete', 'no' ),
			'active'          => function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce/woocommerce.php' ),
		),
	);
}

/**
 * Build status payload for the React dashboard.
 *
 * @return array
 */
function scm_dashboard_get_status_data() {
	$driver_type       = get_option( 'scm_option_driver', 'file' );
	$optimization      = scm_get_page_optimization_settings();
	$optimization_reqs = scm_get_page_optimization_requirements();
	$preload_limit     = scm_get_preload_limit();
	$preload_count     = (int) get_option( 'scm_last_preload_count', 0 );
	$priority_count    = (int) get_option( 'scm_last_preload_priority_count', 0 );
	$critical_count    = (int) get_option( 'scm_last_critical_preload_count', 0 );
	$queue_total       = (int) get_option( 'scm_preload_queue_total', 0 );
	$queue_processed   = (int) get_option( 'scm_preload_queue_processed', 0 );
	$queue_remaining   = (int) get_option( 'scm_preload_queue_remaining', 0 );
	$planned_priority  = 0;

	if ( scm_is_preload_enabled() ) {
		$planned_priority = count( scm_get_homepage_priority_preload_urls( $preload_limit ) );
	}

	$feature_keys = array(
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

	$enabled_features = 0;

	foreach ( $feature_keys as $feature ) {
		if ( isset( $optimization[ $feature ] ) && 'yes' === $optimization[ $feature ] ) {
			$enabled_features++;
		}
	}

	$requirements = array();
	$passed_reqs  = 0;

	foreach ( $optimization_reqs as $key => $requirement ) {
		if ( ! empty( $requirement['passed'] ) ) {
			$passed_reqs++;
		}

		$requirements[] = array(
			'key'    => $key,
			'label'  => $requirement['label'],
			'passed' => ! empty( $requirement['passed'] ),
			'detail' => $requirement['detail'],
		);
	}

	$cache_checks = array(
		'enabled'   => 'enable' === get_option( 'scm_option_caching_status', 'disable' ),
		'permalink' => '' !== get_option( 'permalink_structure', '' ),
		'guestOnly' => 'no' === get_option( 'scm_option_visibility_login_user', 'no' ),
	);

	$cache_ready = count( array_filter( $cache_checks ) );

	return array(
		'generatedAt' => current_time( 'mysql' ),
		'cache'       => array(
			'enabled'       => $cache_checks['enabled'],
			'driver'        => $driver_type,
			'ttl'           => (int) get_option( 'scm_option_ttl', 0 ),
			'ttlMechanism'  => get_option( 'scm_option_ttl_mechanism', 'enable' ),
			'keyPrefix'     => scm_get_cache_key_prefix(),
			'maxEntries'    => scm_get_cache_max_entries(),
			'expertMode'    => 'enable' === get_option( 'scm_option_expert_mode_status', 'disable' ),
			'expertReady'   => scm_is_expert_mode_code_ready(),
			'nginxDirect'   => scm_is_nginx_direct_cache_enabled(),
			'progress'      => round( ( $cache_ready / count( $cache_checks ) ) * 100 ),
		),
		'optimization' => array(
			'enabled'       => 'yes' === $optimization['status'],
			'enabledCount'  => $enabled_features,
			'totalCount'    => count( $feature_keys ),
			'progress'      => round( ( $enabled_features / count( $feature_keys ) ) * 100 ),
			'reqPassed'     => $passed_reqs,
			'reqTotal'      => count( $requirements ),
			'reqProgress'   => count( $requirements ) > 0 ? round( ( $passed_reqs / count( $requirements ) ) * 100 ) : 0,
			'requirements'  => $requirements,
			'reports'       => scm_dashboard_get_optimization_report_summary(),
		),
		'preload'     => array(
			'enabled'        => scm_is_preload_enabled(),
			'limit'          => $preload_limit,
			'crawlHomepage'  => 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ),
			'lastCount'      => $preload_count,
			'priorityCount'  => $planned_priority,
			'lastPriorityCount' => $priority_count,
			'criticalCount'  => $critical_count,
			'queueTotal'     => $queue_total,
			'queueProcessed' => $queue_processed,
			'queueRemaining' => $queue_remaining,
			'lastRun'        => scm_dashboard_format_time( (int) get_option( 'scm_last_preload_time', 0 ) ),
			'lastPriority'   => scm_dashboard_format_time( (int) get_option( 'scm_last_homepage_priority_preload_time', 0 ) ),
			'progress'       => $queue_total > 0 ? min( 100, round( ( $queue_processed / $queue_total ) * 100 ) ) : min( 100, round( ( max( $preload_count, $priority_count ) / $preload_limit ) * 100 ) ),
		),
		'stats'       => scm_dashboard_get_stats_summary(),
		'settings'    => scm_dashboard_get_settings_data(),
	);
}

/**
 * Test if specific data driver is available or not.
 *
 * @param string $type Data driver.
 *
 * @return bool
 */
function scm_test_driver( $type = '' ) {

	$advanced_settings        = array();
	$advanced_connection_type = 'tcp';
	$setting                  = array();

	switch ( $type ) {
		case 'mysql':
			$setting = array(
				'host'    => DB_HOST,
				'dbname'  => DB_NAME,
				'user'    => DB_USER,
				'pass'    => DB_PASSWORD,
				'charset' => DB_CHARSET,
			);
			break;

		case 'file':
			$file_dir = scm_get_upload_dir() . '/file_driver';

			if ( ! is_dir( $file_dir ) ) {
				wp_mkdir_p( $file_dir );
			}

			$setting['storage'] = $file_dir;
			break;

		case 'sqlite':
			$sqlite_dir       = scm_get_upload_dir() . '/sqlite_driver';
			$sqlite_file_path = $sqlite_dir . '/cache.sqlite3';

			if ( ! file_exists( $sqlite_file_path ) ) {
				if ( ! is_dir( $sqlite_dir ) ) {
					wp_mkdir_p( $sqlite_dir );
				}
			}

			$setting['storage'] = $sqlite_dir;
			break;

		case 'redis':
			$setting = array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'database' => 0,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'mongo':
			$setting = array(
				'host' => '127.0.0.1',
				'port' => 27017,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'memcache':
		case 'memcached':
			$setting = array(
				'host' => '127.0.0.1',
				'port' => 11211,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'apc':
		case 'apcu':
		case 'wincache':
			$setting = array();
			break;
	}

	if ( ! empty( $advanced_settings ) ) {
		$setting = array_merge( $setting, scm_normalize_driver_settings( $advanced_settings, $advanced_connection_type ) );
	}

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
		$driver->rebuild();
		$driver->set( 'foo', 'bar', 300 );

		if ( 'bar' === $driver->get( 'foo' ) ) {
			$driver->delete( 'foo' );
			return true;
		}
	} catch ( \Exception $e ) {
		// Nothing here.
	} catch ( \Throwable $e ) {
		// Nothing here.
	}

	return false;
}

/**
 * Build a dashboard-safe driver connection test response.
 *
 * @param string $type Driver key.
 *
 * @return array
 */
function scm_dashboard_test_driver_connection( $type ) {
	$type = sanitize_key( (string) $type );

	if ( '' === $type ) {
		$type = get_option( 'scm_option_driver', 'file' );
	}

	$labels = array(
		'file'      => __( 'File', 'ams-cache' ),
		'redis'     => __( 'Redis', 'ams-cache' ),
		'memcache'  => __( 'Memcache', 'ams-cache' ),
		'memcached' => __( 'Memcached', 'ams-cache' ),
		'apc'       => __( 'APC', 'ams-cache' ),
		'apcu'      => __( 'APCu', 'ams-cache' ),
		'wincache'  => __( 'WinCache', 'ams-cache' ),
		'mongo'     => __( 'MongoDB', 'ams-cache' ),
		'mysql'     => __( 'MySQL', 'ams-cache' ),
		'sqlite'    => __( 'SQLite', 'ams-cache' ),
	);

	if ( ! isset( $labels[ $type ] ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => __( 'Unknown cache driver.', 'ams-cache' ),
		);
	}

	if ( ! scm_dashboard_is_driver_available( $type ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => sprintf(
				/* translators: %s: cache driver label. */
				__( '%s PHP extension is not available.', 'ams-cache' ),
				$labels[ $type ]
			),
		);
	}

	$passed = scm_test_driver( $type );

	return array(
		'passed'  => $passed,
		'driver'  => $type,
		'message' => $passed
			? sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test passed.', 'ams-cache' ),
				$labels[ $type ]
			)
			: sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test failed. Save settings, then verify host, port, database, and credentials.', 'ams-cache' ),
				$labels[ $type ]
			),
	);
}

/**
 * Get the Expert Mode code snippet.
 *
 * @return string
 */
function scm_expert_mode_code_template() {
	$plugin_dir        = wp_normalize_path( rtrim( SCM_PLUGIN_DIR, '/\\' ) );
	$plugin_upload_dir = wp_normalize_path( rtrim( scm_get_upload_dir(), '/\\' ) );
	$expert_mode_file  = $plugin_dir . '/inc/expert-mode.php';
	$config_file       = $plugin_upload_dir . '/config.json';

	ob_start();

	?>
// BEGIN - AMS Cache
// Settings are read from <?php echo $config_file; ?>.
// Update Redis DB, key prefix, driver, and preload options in AMS Cache admin.
//
// Raise memory early — Expert Mode runs before WP_MEMORY_LIMIT is applied
// and large cache pages can exhaust FPM's default 128M.
if ( function_exists( 'ini_set' ) ) {
	@ini_set( 'memory_limit', '512M' );
}

if ( file_exists( <?php echo var_export( $expert_mode_file, true ); ?> ) ) {

	include_once( <?php echo var_export( $expert_mode_file, true ); ?> );

	/* BEGIN - Blog ID: <?php echo get_current_blog_id(); ?> */

	scm_run_expert_mode( array(
		'plugin_dir'        => <?php echo var_export( $plugin_dir, true ); ?>,
		'plugin_upload_dir' => <?php echo var_export( $plugin_upload_dir, true ); ?>,
	) );

	/* END - Blog ID: <?php echo get_current_blog_id(); ?> */
}

// END - AMS Cache
	<?php
	return ob_get_clean();
}

/**
 * Check if the Expert Mode code snippet exists or not.
 *
 * @param string $string The string that must be found.
 *
 * @return array
 */
function scm_search_expert_mode_code_snippet( $string ) {
	$wp_config_file = ABSPATH . 'wp-config.php';

	if ( ! file_exists( $wp_config_file ) ) {

		// For some users put the wp-config.php in parent folder...
		if ( file_exists( ABSPATH . '../wp-config.php' ) ) {
			$wp_config_file = ABSPATH . '../wp-config.php';
		}
	}

	$found1 = false;
	$found2 = false;

	if ( file_exists( $wp_config_file ) ) {
		$file    = @fopen( $wp_config_file, 'r' );
		$target1 = 'expert-mode.php';
		$target2 = $string;

		if ( $file ) {
			while ( $line = fgets( $file ) ) {
				if ( strpos( $line, $target1 ) !== false ) {
					$found1 = true;
				}
				if ( strpos( $line, $target2 ) !== false ) {
					$found2 = true;
				}
			}
			fclose( $file );
		}
	}

	$result = array( $found1, $found2 );

	return $result;
}

/**
 * Check if PHP code for Expert Mode is ready or not.
 *
 * @return bool
 */
function scm_is_expert_mode_code_ready() {
	$result = scm_search_expert_mode_code_snippet( wp_normalize_path( scm_get_upload_dir() ) );
	if ( $result[0] && $result[1] ) {
		return true;
	}
	return false;
}

/**
 * Clear all cache.
 *
 * @return int
 */
function scm_clear_all_cache() {
	scm_sync_expert_mode_runtime();

	$driver_type = get_option( 'scm_option_driver' );
	$driver      = scm_driver_factory( $driver_type );

	if ( ! $driver ) {
		return 0;
	}

	$list = scm_get_cache_type_list( true );

	if ( in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
		$driver->clear();
	}

	$i = 0;

	foreach ( $list as $cache_type ) {
		$dir = scm_get_stats_dir( $cache_type );

		if ( is_dir( $dir ) ) {
			foreach ( new DirectoryIterator( $dir ) as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'json' ) {
					$filename = $file->getFilename();
					$key      = strstr( $filename, '.', true );

					$driver->delete( $key );
					unlink( $file->getPathname() );
					$i++;
				}
			}
		}
	}

	scm_clear_nginx_static_cache();
	scm_preload_critical_urls( 0, true, $driver );
	scm_preload_homepage_priority_urls();
	scm_schedule_preload_cache();

	return $i;
}

/**
 * Clear cache rows for one cache type.
 *
 * @param string $cache_type Cache type.
 *
 * @return int
 */
function scm_clear_cache_type( $cache_type ) {
	$driver = scm_driver_factory( get_option( 'scm_option_driver' ) );
	$list   = scm_get_cache_type_list( true );

	if ( ! $driver || ! in_array( $cache_type, $list, true ) ) {
		return 0;
	}

	$rows = 0;
	$dir  = scm_get_stats_dir( $cache_type );

	if ( is_dir( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'json' ) {
				$filename = $file->getFilename();
				$key      = strstr( $filename, '.', true );

				$driver->delete( $key );
				unlink( $file->getPathname() );
				$rows++;
			}
		}
	}

	scm_clear_nginx_static_cache();
	scm_preload_critical_urls( 0, true, $driver );
	scm_preload_homepage_priority_urls();
	scm_schedule_preload_cache();

	return $rows;
}

/**
 * Save the settings into a JSON file.
 *
 * @param array $settings The setting.
 *
 * @return void
 */
function scm_update_config( $setting ) {

	$config  = get_option( 'scm_config', array() );
	$default = scm_get_default_config();

	foreach ( $default as $key => $value ) {
		if ( isset( $setting[ $key ] ) ) {
			$config[ $key ] = $setting[ $key ];
		}

		if ( ! isset( $config[ $key ] ) ) {
			$config[ $key ] = $value;
		}
	}

	if ( empty( $config['site_url'] ) ) {
		$config['site_url'] = rtrim( get_site_url(), '/' );
	}

	if ( empty( $config['cache_key_prefix'] ) ) {
		$config['cache_key_prefix'] = scm_get_cache_key_prefix();
	}

	if ( empty( $config['preload'] ) ) {
		$config['preload'] = array(
			'enable'               => scm_is_preload_enabled(),
			'limit'                => (int) get_option( 'scm_option_preload_limit', 50 ),
			'crawl_homepage_links' => 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ),
		);
	}

	update_option( 'scm_config', $config );

	$file    = scm_get_config_path();
	$content = json_encode( $config, JSON_PRETTY_PRINT );

	// phpcs:ignore
	@file_put_contents( $file, $content );
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
	$setting['driver_advanced_settings'] = $advanced_setting;
	$setting['driver_connection_type']   = $connection_type;
	$setting['nginx_direct_cache']       = scm_is_nginx_direct_cache_enabled();
	$setting['preload']                  = array(
		'enable'               => scm_is_preload_enabled(),
		'limit'                => (int) get_option( 'scm_option_preload_limit', 50 ),
		'crawl_homepage_links' => 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ),
	);
	$setting['cache_max_entries']        = scm_get_cache_max_entries();

	scm_update_config( $setting );

	$checkpoint = scm_get_upload_dir() . '/expert.lock';

	if ( 'enable' === get_option( 'scm_option_expert_mode_status' ) ) {
		file_put_contents( $checkpoint, 'VOTE!' );
		return;
	}

	if ( file_exists( $checkpoint ) ) {
		unlink( $checkpoint );
	}
}
