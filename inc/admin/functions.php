<?php
/**
 * KH Speed Engine - Admin helper functions.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

/**
 * Load view files.
 *
 * @param string $template_path The specific template's path.
 * @return string
 */
function khse_load_view( $template_path ) {
	$template_path  = str_replace( '_', '-', $template_path );
	$view_file_path = KHSE_PLUGIN_DIR . 'inc/admin/views/' . $template_path . '.php';

	if ( file_exists( $view_file_path ) ) {
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
function khse_get_cache_type_list( $get_key = false ) {
	$archive_note     = ' <small class="khse-badge">' . __( 'Archive', 'kh-speed-engine' ) . '</small>';

	$list = array(
		'homepage' => __( 'Homepage', 'kh-speed-engine' ),
		'post'     => __( 'Post', 'kh-speed-engine' ),
		'page'     => __( 'Page', 'kh-speed-engine' ),
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

	$list['category'] = __( 'Category', 'kh-speed-engine' ) . $archive_note;
	$list['tag']      = __( 'Tag', 'kh-speed-engine' ) . $archive_note;
	$list['date']     = __( 'Date', 'kh-speed-engine' ) . $archive_note;
	$list['author']   = __( 'Author', 'kh-speed-engine' ) . $archive_note;

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
			__( 'Archive for %s', 'kh-speed-engine' ),
			$post_type->labels->singular_name
		) . $archive_note;
	}

	$list['uncategorised'] = __( 'Uncategorised', 'kh-speed-engine' );

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
function khse_dashboard_format_time( $timestamp ) {
	$timestamp = (int) $timestamp;

	if ( $timestamp <= 0 ) {
		return __( 'Never', 'kh-speed-engine' );
	}

	return sprintf(
		/* translators: %s is a human time difference. */
		__( '%s ago', 'kh-speed-engine' ),
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
function khse_dashboard_get_stats_for_type( $type ) {
	$dir  = khse_get_stats_dir( $type );
	$rows = 0;
	$size = 0;
	$seen = array();

	if ( is_dir( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
				continue;
			}

			$stats = khse_read_stats_file( $file->getPathname() );

			if ( 'homepage' === $type && ! empty( $stats['uri'] ) && ! khse_is_homepage_uri( $stats['uri'] ) ) {
				continue;
			}

			$identity = empty( $stats['uri'] ) ? $file->getFilename() : khse_normalize_cache_uri( $stats['uri'] );

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
function khse_dashboard_get_stats_summary() {
	$rows       = array();
	$total_rows = 0;
	$total_size = 0;

	foreach ( khse_get_cache_type_list() as $type => $label ) {
		$stats = khse_dashboard_get_stats_for_type( $type );

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
function khse_dashboard_normalize_optimization_reports( $reports ) {
	$latest = array();

	foreach ( $reports as $report ) {
		$features = array();

		if ( ! empty( $report['features'] ) && is_array( $report['features'] ) ) {
			foreach ( $report['features'] as $key => $feature ) {
				$features[] = array(
					'key'     => $key,
					'enabled' => ! empty( $feature['enabled'] ),
					'status'  => isset( $feature['status'] ) ? $feature['status'] : 'unknown',
					'detail'  => isset( $feature['detail'] ) ? khse_dashboard_humanize_report_detail( $feature['detail'] ) : '',
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
function khse_dashboard_humanize_report_detail( $detail ) {
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
function khse_dashboard_get_optimization_report_summary() {
	$summary_reports = khse_get_page_optimization_reports( 20 );
	$initial_reports = array_slice( $summary_reports, 0, 5 );
	$total_reports   = khse_count_page_optimization_reports();
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
		'reports'       => khse_dashboard_normalize_optimization_reports( $initial_reports ),
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
function khse_dashboard_is_driver_available( $driver ) {
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
function khse_dashboard_get_driver_advanced_settings_data() {
	$file = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_file', array() ),
		array(
			'compress'           => 'no',
			'compress_threshold' => 4096,
			'compress_level'     => 1,
		)
	);

	$redis = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_redis', array() ),
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
		(array) get_option( 'khse_option_advanced_driver_memcached', array() ),
		array(
			'host'        => '127.0.0.1',
			'port'        => 11211,
			'unix_socket' => '',
		)
	);

	$mongodb = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_mongodb', array() ),
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
			'redis'     => get_option( 'khse_option_advanced_driver_redis_connection_type', 'tcp' ),
			'memcache'  => get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'memcached' => get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'mongo'     => get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' ),
			'mongodb'   => get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' ),
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
function khse_dashboard_build_toggle_items( $items, $enabled ) {
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
function khse_dashboard_get_preload_options_data() {
	$post_types = array(
		'post' => __( 'Post', 'kh-speed-engine' ),
		'page' => __( 'Page', 'kh-speed-engine' ),
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
		'category' => __( 'Category', 'kh-speed-engine' ),
		'tag'      => __( 'Tag', 'kh-speed-engine' ),
		'date'     => __( 'Date', 'kh-speed-engine' ),
		'author'   => __( 'Author', 'kh-speed-engine' ),
	);

	return array(
		'postTypes' => khse_dashboard_build_toggle_items( $post_types, get_option( 'khse_option_post_types', array() ) ),
		'homepage'  => get_option( 'khse_option_post_homepage', 'yes' ),
		'archives'  => khse_dashboard_build_toggle_items( $archives, get_option( 'khse_option_post_archives', array() ) ),
	);
}

/**
 * Build settings payload for the React dashboard.
 *
 * @return array
 */
function khse_dashboard_get_settings_data() {
	$optimization = khse_get_page_optimization_settings();

	foreach ( array( 'status', 'minify_html', 'remove_comments', 'minify_inline_css', 'lazy_media', 'critical_images', 'preconnect_fonts', 'defer_js', 'external_ucss', 'local_ucss', 'js_analysis' ) as $toggle_key ) {
		$optimization[ $toggle_key ] = 'yes' === $optimization[ $toggle_key ] || true === $optimization[ $toggle_key ] || 'enable' === $optimization[ $toggle_key ];
	}

	$drivers = array(
		array( 'value' => 'file', 'label' => __( 'File', 'kh-speed-engine' ) ),
		array( 'value' => 'redis', 'label' => __( 'Redis', 'kh-speed-engine' ) ),
		array( 'value' => 'memcache', 'label' => __( 'Memcache', 'kh-speed-engine' ) ),
		array( 'value' => 'memcached', 'label' => __( 'Memcached', 'kh-speed-engine' ) ),
		array( 'value' => 'apc', 'label' => __( 'APC', 'kh-speed-engine' ) ),
		array( 'value' => 'apcu', 'label' => __( 'APCu', 'kh-speed-engine' ) ),
		array( 'value' => 'wincache', 'label' => __( 'WinCache', 'kh-speed-engine' ) ),
		array( 'value' => 'mongo', 'label' => __( 'MongoDB', 'kh-speed-engine' ) ),
		array( 'value' => 'mysql', 'label' => __( 'MySQL', 'kh-speed-engine' ) ),
		array( 'value' => 'sqlite', 'label' => __( 'SQLite', 'kh-speed-engine' ) ),
	);
	$drivers      = array_map(
		function ( $driver ) {
			$driver['available'] = khse_dashboard_is_driver_available( $driver['value'] );
			return $driver;
		},
		$drivers
	);
	$preload_options = khse_dashboard_get_preload_options_data();

	return array(
		'cache'       => array(
			'cachingStatus'    => khse_get_setting( 'cache', 'cachingStatus' ),
			'driver'           => khse_get_setting( 'cache', 'driver' ),
			'ttl'              => khse_get_setting( 'cache', 'ttl' ),
			'ttlMechanism'     => khse_get_setting( 'cache', 'ttlMechanism' ),
			'cacheKeyPrefix'   => khse_get_setting( 'cache', 'cacheKeyPrefix' ),
			'maxEntries'       => khse_get_setting( 'cache', 'maxEntries' ),
			'expertModeStatus' => khse_get_setting( 'cache', 'expertModeStatus' ),
			'nginxDirect'      => khse_get_setting( 'cache', 'nginxDirect' ),
			'debugComment'     => khse_get_setting( 'cache', 'debugComment' ),
			'visibilityLoginUser' => khse_get_setting( 'cache', 'visibilityLoginUser' ),
			'visibilityGuest'     => khse_get_setting( 'cache', 'visibilityGuest' ),
			'drivers'          => $drivers,
			'driverAdvanced'   => khse_dashboard_get_driver_advanced_settings_data(),
			'expertModeCode'   => khse_expert_mode_code_template(),
			'expertModeCustomValue' => khse_expert_mode_custom_value(),
			'expertModeReady'  => khse_is_expert_mode_code_ready(),
		),
		'preload'     => array(
			'enabled'       => khse_get_setting( 'preload', 'enabled' ),
			'limit'         => khse_get_setting( 'preload', 'limit' ),
			'crawlHomepage' => khse_get_setting( 'preload', 'crawlHomepage' ),
			'postTypes'     => $preload_options['postTypes'],
			'homepage'      => khse_get_setting( 'preload', 'homepage' ),
			'archives'      => $preload_options['archives'],
		),
		'performance' => $optimization,
		'rules'       => array(
			'enabled'      => khse_get_setting( 'rules', 'enabled' ),
			'excludedList' => get_option( 'khse_option_excluded_list_filtered', get_option( 'khse_option_excluded_list', '' ) ),
			'getVars'      => khse_get_setting( 'rules', 'getVars' ),
			'postVars'     => khse_get_setting( 'rules', 'postVars' ),
			'cookieVars'   => khse_get_setting( 'rules', 'cookieVars' ),
		),
		'statistics'  => array(
			'enabled' => khse_get_setting( 'statistics', 'enabled' ),
		),
		'benchmark'   => array(
			'widget'        => khse_get_setting( 'benchmark', 'widget' ),
			'footer'        => khse_get_setting( 'benchmark', 'footer' ),
			'widgetDisplay' => khse_get_setting( 'benchmark', 'widgetDisplay' ),
			'footerDisplay' => khse_get_setting( 'benchmark', 'footerDisplay' ),
		),
	);
}

/**
 * Build status payload for the React dashboard.
 *
 * @return array
 */
function khse_dashboard_get_status_data() {
	$driver_type       = get_option( 'khse_option_driver', 'file' );
	$optimization      = khse_get_page_optimization_settings();
	$optimization_reqs = khse_get_page_optimization_requirements();
	$preload_limit     = khse_get_preload_limit();
	$preload_count     = (int) get_option( 'khse_last_preload_count', 0 );
	$priority_count    = (int) get_option( 'khse_last_preload_priority_count', 0 );
	$critical_count    = (int) get_option( 'khse_last_critical_preload_count', 0 );
	$queue_total       = (int) get_option( 'khse_preload_queue_total', 0 );
	$queue_processed   = (int) get_option( 'khse_preload_queue_processed', 0 );
	$queue_failed      = (int) get_option( 'khse_preload_queue_failed', 0 );
	$queue_remaining   = (int) get_option( 'khse_preload_queue_remaining', 0 );
	$planned_priority  = 0;

	if ( khse_is_preload_enabled() ) {
		$planned_priority = count( khse_get_homepage_priority_preload_urls( $preload_limit ) );
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
		'enabled'   => 'enable' === get_option( 'khse_option_caching_status', 'disable' ),
		'permalink' => '' !== get_option( 'permalink_structure', '' ),
		'guestOnly' => 'no' === get_option( 'khse_option_visibility_login_user', 'no' ),
	);

	$cache_ready = count( array_filter( $cache_checks ) );

	return array(
		'generatedAt' => current_time( 'mysql' ),
		'cache'       => array(
			'enabled'       => $cache_checks['enabled'],
			'driver'        => $driver_type,
			'ttl'           => (int) get_option( 'khse_option_ttl', 0 ),
			'ttlMechanism'  => get_option( 'khse_option_ttl_mechanism', 'enable' ),
			'keyPrefix'     => khse_get_cache_key_prefix(),
			'maxEntries'    => khse_get_cache_max_entries(),
			'expertMode'    => 'enable' === get_option( 'khse_option_expert_mode_status', 'disable' ),
			'expertReady'   => khse_is_expert_mode_code_ready(),
			'nginxDirect'   => khse_is_nginx_direct_cache_enabled(),
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
			'reports'       => khse_dashboard_get_optimization_report_summary(),
		),
		'preload'     => array(
			'enabled'        => khse_is_preload_enabled(),
			'limit'          => $preload_limit,
			'crawlHomepage'  => 'yes' === get_option( 'khse_option_preload_homepage_links', 'yes' ),
			'lastCount'      => $preload_count,
			'priorityCount'  => $planned_priority,
			'lastPriorityCount' => $priority_count,
			'criticalCount'  => $critical_count,
			'queueTotal'     => $queue_total,
			'queueProcessed' => $queue_processed,
			'queueFailed'    => $queue_failed,
			'queueRemaining' => $queue_remaining,
			'lastRun'        => khse_dashboard_format_time( (int) get_option( 'khse_last_preload_time', 0 ) ),
			'lastPriority'   => khse_dashboard_format_time( (int) get_option( 'khse_last_homepage_priority_preload_time', 0 ) ),
			'progress'       => $queue_total > 0 ? min( 100, round( ( $queue_processed / $queue_total ) * 100 ) ) : min( 100, round( ( max( $preload_count, $priority_count ) / $preload_limit ) * 100 ) ),
		),
		'stats'       => khse_dashboard_get_stats_summary(),
		'settings'    => khse_dashboard_get_settings_data(),
	);
}

/**
 * Ensure the storage directory exists for a cache driver.
 *
 * @param string $type Driver type.
 *
 * @return void
 */
function khse_ensure_driver_storage( $type ) {
	if ( ! in_array( $type, array( 'file', 'sqlite' ), true ) ) {
		return;
	}

	$resolved = khse_get_driver_settings( $type );
	$storage  = isset( $resolved['setting']['storage'] ) ? $resolved['setting']['storage'] : '';

	if ( '' !== $storage && ! is_dir( $storage ) ) {
		wp_mkdir_p( $storage );
	}
}

/**
 * Test if specific data driver is available or not.
 *
 * @param string $type Data driver.
 *
 * @return bool
 */
function khse_test_driver( $type = '' ) {

	if ( '' === (string) $type ) {
		return false;
	}

	$resolved = khse_get_driver_settings( $type );
	$setting  = $resolved['setting'];

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
		$driver->rebuild();
		$driver->set( 'foo', 'bar', 300 );

		if ( 'bar' === $driver->get( 'foo' ) ) {
			$driver->delete( 'foo' );
			return true;
		}
	} catch ( \Throwable $e ) {
		error_log(
			sprintf(
				'[KH Speed Engine] Driver %s connection test failed: %s',
				'' !== $type ? $type : 'unknown',
				$e->getMessage()
			)
		);
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
function khse_dashboard_test_driver_connection( $type ) {
	$type = sanitize_key( (string) $type );

	if ( '' === $type ) {
		$type = get_option( 'khse_option_driver', 'file' );
	}

	$labels = array(
		'file'      => __( 'File', 'kh-speed-engine' ),
		'redis'     => __( 'Redis', 'kh-speed-engine' ),
		'memcache'  => __( 'Memcache', 'kh-speed-engine' ),
		'memcached' => __( 'Memcached', 'kh-speed-engine' ),
		'apc'       => __( 'APC', 'kh-speed-engine' ),
		'apcu'      => __( 'APCu', 'kh-speed-engine' ),
		'wincache'  => __( 'WinCache', 'kh-speed-engine' ),
		'mongo'     => __( 'MongoDB', 'kh-speed-engine' ),
		'mysql'     => __( 'MySQL', 'kh-speed-engine' ),
		'sqlite'    => __( 'SQLite', 'kh-speed-engine' ),
	);

	if ( ! isset( $labels[ $type ] ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => __( 'Unknown cache driver.', 'kh-speed-engine' ),
		);
	}

	if ( ! khse_dashboard_is_driver_available( $type ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => sprintf(
				/* translators: %s: cache driver label. */
				__( '%s PHP extension is not available.', 'kh-speed-engine' ),
				$labels[ $type ]
			),
		);
	}

	khse_ensure_driver_storage( $type );

	$passed = khse_test_driver( $type );

	return array(
		'passed'  => $passed,
		'driver'  => $type,
		'message' => $passed
			? sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test passed.', 'kh-speed-engine' ),
				$labels[ $type ]
			)
			: sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test failed. Save settings, then verify host, port, database, and credentials.', 'kh-speed-engine' ),
				$labels[ $type ]
			),
	);
}

/**
 * Get the Expert Mode code snippet.
 *
 * @return string
 */
function khse_expert_mode_code_template() {
	$plugin_dir        = wp_normalize_path( rtrim( KHSE_PLUGIN_DIR, '/\\' ) );
	$plugin_upload_dir = wp_normalize_path( rtrim( khse_get_upload_dir(), '/\\' ) );
	$runtime_dir       = wp_normalize_path( rtrim( khse_get_private_runtime_dir(), '/\\' ) );
	$expert_mode_file  = $plugin_dir . '/inc/expert-mode.php';
	$config_file       = $runtime_dir . '/config.json';

	ob_start();

	?>
// BEGIN - KH Speed Engine
// Settings are read from the private runtime file <?php echo $config_file; ?>.
// Update Redis DB, key prefix, driver, and preload options in KH Speed Engine admin.
//
// Raise memory early — Expert Mode runs before WP_MEMORY_LIMIT is applied
// and large cache pages can exhaust FPM's default 128M.
if ( function_exists( 'ini_set' ) ) {
	@ini_set( 'memory_limit', '512M' );
}

if ( file_exists( <?php echo var_export( $expert_mode_file, true ); ?> ) ) {

	include_once( <?php echo var_export( $expert_mode_file, true ); ?> );

	/* BEGIN - Blog ID: <?php echo get_current_blog_id(); ?> */

	khse_run_expert_mode( array(
		'plugin_dir'        => <?php echo var_export( $plugin_dir, true ); ?>,
		'plugin_upload_dir' => <?php echo var_export( $plugin_upload_dir, true ); ?>,
		'runtime_dir'       => <?php echo var_export( $runtime_dir, true ); ?>,
	) );

	/* END - Blog ID: <?php echo get_current_blog_id(); ?> */
}

// END - KH Speed Engine
	<?php
	return ob_get_clean();
}

/**
 * Get the single-line Expert Mode value for Docker or custom wp-config fields.
 *
 * @return string
 */
function khse_expert_mode_custom_value() {
	$plugin_dir        = wp_normalize_path( rtrim( KHSE_PLUGIN_DIR, '/\\' ) );
	$plugin_upload_dir = wp_normalize_path( rtrim( khse_get_upload_dir(), '/\\' ) );
	$runtime_dir       = wp_normalize_path( rtrim( khse_get_private_runtime_dir(), '/\\' ) );
	$expert_mode_file  = $plugin_dir . '/inc/expert-mode.php';

	return 'if ( file_exists( ' . var_export( $expert_mode_file, true ) . ' ) ) { require_once ' . var_export( $expert_mode_file, true ) . '; khse_run_expert_mode( array( '
		. "'plugin_dir' => " . var_export( $plugin_dir, true ) . ', '
		. "'plugin_upload_dir' => " . var_export( $plugin_upload_dir, true ) . ', '
		. "'runtime_dir' => " . var_export( $runtime_dir, true )
		. ' ) ); }';
}

/**
 * Read wp-config.php contents, including the parent-directory fallback.
 *
 * @return string
 */
function khse_get_wp_config_contents() {
	$wp_config_file = ABSPATH . 'wp-config.php';

	if ( ! file_exists( $wp_config_file ) && file_exists( ABSPATH . '../wp-config.php' ) ) {
		$wp_config_file = ABSPATH . '../wp-config.php';
	}

	return file_exists( $wp_config_file ) ? (string) @file_get_contents( $wp_config_file ) : '';
}

/**
 * Check whether a config source contains the Expert Mode loader and paths.
 *
 * @param string $source      Config source.
 * @param string $upload_dir  Plugin upload dir.
 * @param string $runtime_dir Private runtime dir.
 *
 * @return bool
 */
function khse_expert_mode_snippet_matches( $source, $upload_dir, $runtime_dir ) {
	$source = (string) $source;

	return false !== strpos( $source, 'expert-mode.php' )
		&& false !== strpos( $source, wp_normalize_path( $upload_dir ) )
		&& false !== strpos( $source, wp_normalize_path( $runtime_dir ) );
}

/**
 * Check if PHP code for Expert Mode is ready or not.
 *
 * @return bool
 */
function khse_is_expert_mode_code_ready() {
	$upload_dir  = wp_normalize_path( khse_get_upload_dir() );
	$runtime_dir = wp_normalize_path( khse_get_private_runtime_dir() );

	if ( khse_expert_mode_snippet_matches( khse_get_wp_config_contents(), $upload_dir, $runtime_dir ) ) {
		return true;
	}

	foreach ( array( 'WORDPRESS_CONFIG_EXTRA', 'WP_CONFIG_EXTRA' ) as $env_key ) {
		$value = getenv( $env_key );

		if ( false === $value && isset( $_ENV[ $env_key ] ) ) {
			$value = $_ENV[ $env_key ];
		}

		if ( false === $value && isset( $_SERVER[ $env_key ] ) ) {
			$value = $_SERVER[ $env_key ];
		}

		if ( is_string( $value ) && khse_expert_mode_snippet_matches( $value, $upload_dir, $runtime_dir ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Clear all cache.
 *
 * @return int
 */
function khse_clear_all_cache() {
	khse_sync_expert_mode_runtime();

	$driver_type = get_option( 'khse_option_driver' );
	$driver      = khse_driver_factory( $driver_type );

	if ( ! $driver || is_wp_error( $driver ) ) {
		return 0;
	}

	$list = khse_get_cache_type_list( true );

	if ( in_array( $driver_type, array( 'file', 'sqlite' ), true ) ) {
		$driver->clear();
	}

	$i = 0;

	foreach ( $list as $cache_type ) {
		$dir = khse_get_stats_dir( $cache_type );

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

	khse_clear_nginx_static_cache();
	khse_schedule_preload_cache();

	return $i;
}

/**
 * Clear cache rows for one cache type.
 *
 * @param string $cache_type Cache type.
 *
 * @return int
 */
function khse_clear_cache_type( $cache_type ) {
	$driver = khse_driver_factory( get_option( 'khse_option_driver' ) );
	$list   = khse_get_cache_type_list( true );

	if ( ! $driver || is_wp_error( $driver ) || ! in_array( $cache_type, $list, true ) ) {
		return 0;
	}

	$rows = 0;
	$dir  = khse_get_stats_dir( $cache_type );

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

	khse_clear_nginx_static_cache();
	khse_schedule_preload_cache();

	return $rows;
}
