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
 * Get all stats data for the Vue dashboard.
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
		$saved_bytes  = isset( $report['savedBytes'] ) ? (int) $report['savedBytes'] : 0;

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
			'savedLabel'    => isset( $report['savedLabel'] ) ? $report['savedLabel'] : size_format( $saved_bytes, 2 ),
			'savedPercent'  => isset( $report['savedPercent'] ) ? (float) $report['savedPercent'] : 0,
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
		'jsAnalyzed'    => $js_analyzed,
		'jsDeferred'    => $js_deferred,
	);
}

/**
 * Build status payload for the Vue dashboard.
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
	}

	return false;
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
	$result = scm_search_expert_mode_code_snippet( scm_get_upload_dir() );
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
