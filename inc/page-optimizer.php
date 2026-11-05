<?php
/**
 * KH Speed Engine — page-optimizer.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

function khse_get_default_page_optimization_settings() {
	return array(
		'status'               => 'no',
		'minify_html'          => 'yes',
		'remove_comments'      => 'yes',
		'minify_inline_css'    => 'yes',
		'lazy_media'           => 'yes',
		'critical_images'      => 'yes',
		'preconnect_fonts'     => 'yes',
		'defer_js'             => 'no',
		'external_ucss'        => 'no',
		'local_ucss'           => 'no',
		'js_analysis'          => 'no',
		'bun_path'             => '',
		'purgecss_path'        => '',
		'ucss_safelist'        => implode(
			"\n",
			array_merge(
				khse_get_protected_ucss_safelist(),
				array( 'wp-block', 'alignwide', 'alignfull' )
			)
		),
		'critical_image_count' => 1,
		'external_ucss_max_file_size' => 307200,
		'local_ucss_max_file_size' => 524288,
		'js_analysis_max_script_bytes' => 262144,
		'js_analysis_max_total_bytes' => 1048576,
		'media_exclusions'     => "logo\navatar\ncaptcha",
		'js_exclusions'        => implode( "\n", khse_get_protected_js_exclusions() ),
	);
}

/**
 * Get page optimization settings.
 *
 * @return array
 */
function khse_get_page_optimization_settings() {
	$settings = (array) get_option( 'khse_option_page_optimization', array() );
	$settings = array_merge( khse_get_default_page_optimization_settings(), $settings );

	$settings['critical_image_count'] = max( 0, min( 5, (int) $settings['critical_image_count'] ) );
	$settings['bun_path']             = trim( (string) $settings['bun_path'] );
	$settings['purgecss_path']        = trim( (string) $settings['purgecss_path'] );
	$settings['external_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['external_ucss_max_file_size'] ) );
	$settings['local_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['local_ucss_max_file_size'] ) );
	$settings['js_analysis_max_script_bytes'] = max( 16384, min( 1048576, (int) $settings['js_analysis_max_script_bytes'] ) );
	$settings['js_analysis_max_total_bytes'] = max( 65536, min( 4194304, (int) $settings['js_analysis_max_total_bytes'] ) );
	$settings['ucss_safelist']        = khse_merge_textarea_lines( $settings['ucss_safelist'], khse_get_protected_ucss_safelist() );
	$settings['js_exclusions']        = khse_merge_textarea_lines( $settings['js_exclusions'], khse_get_protected_js_exclusions() );

	return $settings;
}

/**
 * Get CSS selectors/classes that stay protected for interactive frontends.
 *
 * @return array
 */
function khse_get_protected_ucss_safelist() {
	return array(
		'active',
		'open',
		'show',
		'is-active',
		'is-open',
		'is-visible',
		'current',
		'menu-item',
		'menu-item-has-children',
		'current-menu-item',
		'current-menu-parent',
		'current-menu-ancestor',
		'sub-menu',
		'dropdown',
		'dropdown-menu',
		'dropdown-toggle',
		'sfHover',
		'slick',
		'slick-active',
		'slick-current',
		'slick-initialized',
		'slick-slide',
		'swiper',
		'swiper-slide',
		'swiper-wrapper',
		'swiper-button-next',
		'swiper-button-prev',
		'revslider',
		'rev_slider',
		'rs-module',
		'rs-layer',
		'rs-slide',
		'sr7',
		'tp-caption',
	);
}

/**
 * Get script keywords that should never be deferred automatically.
 *
 * @return array
 */
function khse_get_protected_js_exclusions() {
	return array(
		'jquery',
		'wp-includes/js/jquery',
		'cart',
		'checkout',
		'googletagmanager',
		'gtm',
		'recaptcha',
		'stripe',
		'paypal',
		'revslider',
		'rev_slider',
		'rs6',
		'sr7',
		'themepunch',
		'rbtools',
		'slider-revolution',
		'revolution',
		'slick',
		'swiper',
		'owl.carousel',
		'smartmenus',
		'superfish',
		'bootstrap',
		'dropdown',
		'menu-image',
		'menu',
		'navigation',
		'hoverIntent',
	);
}

/**
 * Check whether page optimization is enabled.
 *
 * @return bool
 */
function khse_is_page_optimization_enabled() {
	$settings = khse_get_page_optimization_settings();

	return 'yes' === $settings['status'];
}

/**
 * Get the optimization report directory.
 *
 * @return string
 */
function khse_get_page_optimization_report_dir() {
	return khse_get_upload_dir() . '/optimization_reports';
}

/**
 * Get local optimizer workspace root.
 *
 * @return string
 */
function khse_get_page_optimization_work_dir() {
	return khse_get_private_runtime_dir() . '/optimization_work';
}

/**
 * Get latest optimization runtime metadata.
 *
 * @return array
 */
function khse_get_page_optimization_runtime() {
	return isset( $GLOBALS['khse_page_optimization_runtime'] ) && is_array( $GLOBALS['khse_page_optimization_runtime'] )
		? $GLOBALS['khse_page_optimization_runtime']
		: array();
}

/**
 * Reset optimization runtime metadata.
 *
 * @return void
 */
function khse_reset_page_optimization_runtime() {
	$GLOBALS['khse_page_optimization_runtime'] = array();
}

/**
 * Store optimization runtime metadata.
 *
 * @param string $key   Runtime key.
 * @param array  $value Runtime payload.
 *
 * @return void
 */
function khse_set_page_optimization_runtime( $key, $value ) {
	if ( ! isset( $GLOBALS['khse_page_optimization_runtime'] ) || ! is_array( $GLOBALS['khse_page_optimization_runtime'] ) ) {
		$GLOBALS['khse_page_optimization_runtime'] = array();
	}

	$GLOBALS['khse_page_optimization_runtime'][ $key ] = $value;
}

/**
 * Count inline style bytes.
 *
 * @param string $html HTML content.
 *
 * @return int
 */
function khse_get_inline_style_bytes( $html ) {
	if ( ! preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', (string) $html, $matches ) ) {
		return 0;
	}

	return strlen( implode( '', $matches[1] ) );
}

/**
 * Count regex matches inside HTML.
 *
 * @param string $pattern Regex pattern.
 * @param string $html    HTML content.
 *
 * @return int
 */
function khse_count_html_matches( $pattern, $html ) {
	return (int) preg_match_all( $pattern, (string) $html, $matches );
}

/**
 * Build one feature report entry.
 *
 * @param bool   $enabled Whether the feature is enabled.
 * @param string $status  Feature status.
 * @param string $detail  Feature detail.
 * @param array  $metrics Feature metrics.
 *
 * @return array
 */
function khse_build_page_optimization_feature_report( $enabled, $status, $detail, $metrics = array() ) {
	return array(
		'enabled' => (bool) $enabled,
		'status'  => $status,
		'detail'  => $detail,
		'metrics' => is_array( $metrics ) ? $metrics : array(),
	);
}

/**
 * Build an optimization report for one cached page.
 *
 * @param string $before    HTML before optimization.
 * @param string $after     HTML after optimization.
 * @param array  $settings  Optimization settings.
 * @param string $uri       Request URI.
 * @param string $data_type Cache data type.
 *
 * @return array
 */
function khse_build_page_optimization_report( $before, $after, $settings, $uri = '', $data_type = '' ) {
	$before_bytes    = strlen( (string) $before );
	$after_bytes     = strlen( (string) $after );
	$comments_before = khse_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $before );
	$comments_after  = khse_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $after );
	$styles_before   = khse_get_inline_style_bytes( $before );
	$styles_after    = khse_get_inline_style_bytes( $after );
	$lazy_before     = khse_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $before );
	$lazy_after      = khse_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $after );
	$critical_before = khse_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $before );
	$critical_after  = khse_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $after );
	$fonts_before    = khse_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $before );
	$fonts_after     = khse_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $after );
	$defer_before    = khse_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $before );
	$defer_after     = khse_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $after );
	$features        = array();
	$runtime         = khse_get_page_optimization_runtime();
	$metric_features = array(
		'minify_html'        => array(
			'setting' => 'minify_html',
			'before'  => $before_bytes,
			'after'   => $after_bytes,
			'compare' => 'lt',
			'format'  => 'size',
			'label'   => __( '%1$s before, %2$s after.', 'kh-speed-engine' ),
		),
		'remove_comments'    => array(
			'setting' => 'remove_comments',
			'before'  => $comments_before,
			'after'   => $comments_after,
			'compare' => 'lt',
			'format'  => 'count',
			'label'   => __( '%1$s comments before, %2$s after.', 'kh-speed-engine' ),
		),
		'minify_inline_css'  => array(
			'setting' => 'minify_inline_css',
			'before'  => $styles_before,
			'after'   => $styles_after,
			'compare' => 'lt',
			'format'  => 'size',
			'label'   => __( '%1$s inline CSS before, %2$s after.', 'kh-speed-engine' ),
		),
		'lazy_media'         => array(
			'setting' => 'lazy_media',
			'before'  => $lazy_before,
			'after'   => $lazy_after,
			'compare' => 'gt',
			'format'  => 'count',
			'label'   => __( '%1$s lazy tags before, %2$s after.', 'kh-speed-engine' ),
		),
		'critical_images'    => array(
			'setting' => 'critical_images',
			'before'  => $critical_before,
			'after'   => $critical_after,
			'compare' => 'gt',
			'format'  => 'count',
			'label'   => __( '%1$s priority images before, %2$s after.', 'kh-speed-engine' ),
		),
		'preconnect_fonts'   => array(
			'setting' => 'preconnect_fonts',
			'before'  => $fonts_before,
			'after'   => $fonts_after,
			'compare' => 'gt',
			'format'  => 'count',
			'label'   => __( '%1$s font hints before, %2$s after.', 'kh-speed-engine' ),
		),
		'defer_js'           => array(
			'setting' => 'defer_js',
			'before'  => $defer_before,
			'after'   => $defer_after,
			'compare' => 'gt',
			'format'  => 'count',
			'label'   => __( '%1$s deferred scripts before, %2$s after.', 'kh-speed-engine' ),
		),
	);

	foreach ( $metric_features as $key => $descriptor ) {
		$enabled = 'yes' === $settings[ $descriptor['setting'] ];

		if ( 'size' === $descriptor['format'] ) {
			$before_label = size_format( $descriptor['before'], 2 );
			$after_label  = size_format( $descriptor['after'], 2 );
		} else {
			$before_label = number_format_i18n( $descriptor['before'] );
			$after_label  = number_format_i18n( $descriptor['after'] );
		}

		$changed = 'lt' === $descriptor['compare']
			? $descriptor['after'] < $descriptor['before']
			: $descriptor['after'] > $descriptor['before'];

		$features[ $key ] = khse_build_page_optimization_feature_report(
			$enabled,
			! $enabled ? 'disabled' : ( $changed ? 'applied' : 'no_change' ),
			! $enabled ? __( 'Disabled.', 'kh-speed-engine' ) : sprintf( $descriptor['label'], $before_label, $after_label )
		);
	}

	$runtime_features = array(
		'external_ucss' => array(
			'setting' => 'external_ucss',
			'missing' => __( 'External UCSS engine did not return a result.', 'kh-speed-engine' ),
		),
		'local_ucss'    => array(
			'setting' => 'local_ucss',
			'missing' => __( 'Local UCSS engine did not return a result.', 'kh-speed-engine' ),
		),
		'js_analysis'   => array(
			'setting' => 'js_analysis',
			'missing' => __( 'JS analysis engine did not return a result.', 'kh-speed-engine' ),
		),
	);

	foreach ( $runtime_features as $key => $descriptor ) {
		$enabled         = 'yes' === $settings[ $descriptor['setting'] ];
		$runtime_feature = isset( $runtime[ $key ] ) ? $runtime[ $key ] : array();

		$features[ $key ] = khse_build_page_optimization_feature_report(
			$enabled,
			! $enabled ? 'disabled' : ( isset( $runtime_feature['status'] ) ? $runtime_feature['status'] : 'failed' ),
			$enabled
				? ( isset( $runtime_feature['detail'] ) ? $runtime_feature['detail'] : $descriptor['missing'] )
				: __( 'Disabled.', 'kh-speed-engine' ),
			isset( $runtime_feature['metrics'] ) ? $runtime_feature['metrics'] : array()
		);
	}

	$applied_count = 0;

	foreach ( $features as $feature ) {
		if ( 'applied' === $feature['status'] ) {
			$applied_count++;
		}
	}

	$saved_bytes    = max( 0, $before_bytes - $after_bytes );
	$expanded_bytes = max( 0, $after_bytes - $before_bytes );

	return array(
		'uri'            => khse_normalize_cache_uri( $uri ),
		'dataType'       => $data_type,
		'generatedAt'    => current_time( 'mysql' ),
		'generatedUnix'  => time(),
		'beforeBytes'    => $before_bytes,
		'beforeLabel'    => size_format( $before_bytes, 2 ),
		'afterBytes'     => $after_bytes,
		'afterLabel'     => size_format( $after_bytes, 2 ),
		'savedBytes'     => $saved_bytes,
		'savedLabel'     => size_format( $saved_bytes, 2 ),
		'savedPercent'   => $before_bytes > 0 ? round( ( $saved_bytes / $before_bytes ) * 100, 2 ) : 0,
		'expandedBytes'  => $expanded_bytes,
		'expandedLabel'  => size_format( $expanded_bytes, 2 ),
		'expandedPercent' => $before_bytes > 0 ? round( ( $expanded_bytes / $before_bytes ) * 100, 2 ) : 0,
		'appliedCount'   => $applied_count,
		'overallStatus'  => ! khse_is_page_optimization_enabled() ? 'disabled' : ( $applied_count > 0 ? 'applied' : 'no_change' ),
		'features'       => $features,
	);
}

/**
 * Store one page optimization report.
 *
 * @param string $uri    Request URI.
 * @param array  $report Report payload.
 *
 * @return void
 */
function khse_write_page_optimization_report( $uri, $report ) {
	$dir = khse_get_page_optimization_report_dir();

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$key  = md5( khse_normalize_cache_uri( $uri ) );
	$file = trailingslashit( $dir ) . $key . '.json';

	file_put_contents( $file, wp_json_encode( $report ) );
	khse_prune_page_optimization_reports();
}

/**
 * Keep the report directory bounded for large sites.
 *
 * @param int $limit Maximum report files to keep.
 *
 * @return int Number of deleted reports.
 */
function khse_prune_page_optimization_reports( $limit = 200 ) {
	$dir     = khse_get_page_optimization_report_dir();
	$deleted = 0;
	$limit   = max( 20, min( 1000, (int) apply_filters( 'khse_page_optimization_report_limit', $limit ) ) );

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$files = array();

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( $file->isFile() && 'json' === $file->getExtension() ) {
			$files[] = array(
				'path'  => $file->getPathname(),
				'mtime' => $file->getMTime(),
			);
		}
	}

	if ( count( $files ) <= $limit ) {
		return 0;
	}

	usort(
		$files,
		function ( $a, $b ) {
			return (int) $a['mtime'] - (int) $b['mtime'];
		}
	);

	foreach ( array_slice( $files, 0, count( $files ) - $limit ) as $file ) {
		if ( unlink( $file['path'] ) ) {
			$deleted++;
		}
	}

	return $deleted;
}

/**
 * Get latest page optimization reports.
 *
 * @param int $limit Maximum reports.
 * @param int $offset Number of reports to skip.
 *
 * @return array
 */
function khse_get_page_optimization_reports( $limit = 20, $offset = 0 ) {
	$dir     = khse_get_page_optimization_report_dir();
	$reports = array();
	$limit   = max( 1, min( 100, (int) $limit ) );
	$offset  = max( 0, (int) $offset );

	if ( ! is_dir( $dir ) ) {
		return $reports;
	}

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$data = json_decode( file_get_contents( $file->getPathname() ), true );

		if ( ! is_array( $data ) ) {
			continue;
		}

		$data['_mtime'] = $file->getMTime();
		$reports[]      = $data;
	}

	usort(
		$reports,
		function ( $a, $b ) {
			return (int) $b['_mtime'] - (int) $a['_mtime'];
		}
	);

	return array_slice( $reports, $offset, $limit );
}

/**
 * Count stored page optimization reports.
 *
 * @return int
 */
function khse_count_page_optimization_reports() {
	$dir   = khse_get_page_optimization_report_dir();
	$count = 0;

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( $file->isFile() && 'json' === $file->getExtension() ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Get newline-separated option as list.
 *
 * @param string $value Raw textarea value.
 *
 * @return array
 */
function khse_get_lines_from_textarea( $value ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	$lines = array_map( 'trim', $lines );

	return array_values( array_filter( $lines ) );
}

/**
 * Merge textarea lines while preserving user order.
 *
 * @param string|array $value Existing value.
 * @param array        $extra Extra protected lines.
 *
 * @return string
 */
function khse_merge_textarea_lines( $value, $extra ) {
	$lines = is_array( $value ) ? $value : khse_get_lines_from_textarea( $value );

	foreach ( $extra as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			continue;
		}

		$exists = false;

		foreach ( $lines as $existing ) {
			if ( 0 === strcasecmp( (string) $existing, $line ) ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			$lines[] = $line;
		}
	}

	return implode( "\n", array_values( array_filter( array_map( 'trim', $lines ) ) ) );
}

/**
 * Create denied local optimizer workspace.
 *
 * @param string $prefix Workspace prefix.
 *
 * @return string
 */
function khse_create_page_optimization_workspace( $prefix ) {
	$root = khse_get_page_optimization_work_dir();

	if ( ! is_dir( $root ) ) {
		wp_mkdir_p( $root );
	}

	if ( is_dir( $root ) ) {
		if ( ! file_exists( trailingslashit( $root ) . 'index.html' ) ) {
			file_put_contents( trailingslashit( $root ) . 'index.html', '' );
		}

		if ( ! file_exists( trailingslashit( $root ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $root ) . '.htaccess', "Require all denied\nDeny from all" );
		}
	}

	$dir = trailingslashit( $root ) . sanitize_file_name( $prefix . '-' . wp_generate_uuid4() );

	if ( wp_mkdir_p( $dir ) ) {
		return $dir;
	}

	return '';
}

/**
 * Delete one local optimizer workspace.
 *
 * @param string $dir Workspace path.
 *
 * @return void
 */
function khse_delete_page_optimization_workspace( $dir ) {
	$root = realpath( khse_get_page_optimization_work_dir() );
	$path = realpath( $dir );

	if ( false === $root || false === $path || 0 !== strpos( $path, $root ) || ! is_dir( $path ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}

	rmdir( $path );
}

/**
 * Check if HTML tag matches exclusion keywords.
 *
 * @param string $tag      HTML tag.
 * @param array  $keywords Exclusion keywords.
 *
 * @return bool
 */
function khse_html_tag_is_excluded( $tag, $keywords ) {
	foreach ( $keywords as $keyword ) {
		if ( '' !== $keyword && false !== stripos( $tag, $keyword ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve one same-site asset URL to a readable local path.
 *
 * @param string $url Asset URL.
 *
 * @return string
 */
function khse_local_asset_url_to_path( $url ) {
	$url       = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	$site_url  = home_url( '/' );
	$site_host = parse_url( $site_url, PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );
	$path      = parse_url( $url, PHP_URL_PATH );

	if (
		'' === $url ||
		(
			false !== strpos( $url, '//' ) &&
			! empty( $url_host ) &&
			strtolower( (string) $url_host ) !== strtolower( (string) $site_host )
		)
	) {
		return '';
	}

	if ( empty( $path ) ) {
		$path = $url;
	}

	$path = ltrim( wp_normalize_path( rawurldecode( $path ) ), '/' );

	if ( '' === $path ) {
		return '';
	}

	$root_path = realpath( ABSPATH );
	$full_path = realpath( ABSPATH . $path );

	if ( false === $root_path || false === $full_path ) {
		return '';
	}

	$root_path = rtrim( wp_normalize_path( $root_path ), '/' ) . '/';
	$full_path = wp_normalize_path( $full_path );

	if ( 0 !== strncasecmp( $full_path . '/', $root_path, strlen( $root_path ) ) ) {
		return '';
	}

	return $full_path;
}

/**
 * Get HTML attribute value.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return string
 */
function khse_html_get_attribute( $tag, $attr ) {
	$attr = preg_quote( $attr, '/' );

	if ( preg_match( '/\s' . $attr . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match ) ) {
		return $match[2];
	}

	if ( preg_match( '/\s' . $attr . '\s*=\s*([^\s>]+)/is', $tag, $match ) ) {
		return $match[1];
	}

	return '';
}

/**
 * Check HTML attribute exists.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return bool
 */
function khse_html_has_attribute( $tag, $attr ) {
	return preg_match( '/\s' . preg_quote( $attr, '/' ) . '(?:\s*=|\s|\/?>)/i', $tag ) > 0;
}

/**
 * Set HTML attribute.
 *
 * @param string $tag   HTML tag.
 * @param string $attr  Attribute name.
 * @param string $value Attribute value.
 *
 * @return string
 */
function khse_html_set_attribute( $tag, $attr, $value ) {
	$escaped = htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	$pattern = '/\s' . preg_quote( $attr, '/' ) . '\s*=\s*(["\']).*?\1/is';

	if ( preg_match( $pattern, $tag ) ) {
		return preg_replace( $pattern, ' ' . $attr . '="' . $escaped . '"', $tag, 1 );
	}

	return preg_replace( '/\s*\/?>$/', ' ' . $attr . '="' . $escaped . '">', $tag, 1 );
}

/**
 * Add boolean HTML attribute.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return string
 */
function khse_html_add_boolean_attribute( $tag, $attr ) {
	if ( khse_html_has_attribute( $tag, $attr ) ) {
		return $tag;
	}

	return preg_replace( '/\s*\/?>$/', ' ' . $attr . '>', $tag, 1 );
}

/**
 * Minify CSS content.
 *
 * @param string $css CSS content.
 *
 * @return string
 */
function khse_minify_css( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $css );
	$css = str_replace( ';}', '}', $css );

	return trim( $css );
}

/**
 * Remove safe HTML comments.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function khse_remove_html_comments( $html ) {
	return preg_replace_callback(
		'/<!--(.*?)-->/s',
		function ( $match ) {
			$comment = trim( $match[1] );

			if (
				0 === stripos( $comment, '[if' ) ||
				0 === stripos( $comment, '<![endif' ) ||
				0 === strpos( $comment, 'wp:' ) ||
				0 === strpos( $comment, '/wp:' )
			) {
				return $match[0];
			}

			return '';
		},
		$html
	);
}

/**
 * Minify style blocks.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function khse_minify_inline_css_blocks( $html ) {
	return preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		function ( $match ) {
			$minified = khse_minify_css( $match[2] );

			if ( strlen( $minified ) >= strlen( (string) $match[2] ) ) {
				return $match[0];
			}

			return '<style' . $match[1] . '>' . $minified . '</style>';
		},
		$html
	);
}

/**
 * Minify HTML outside protected tags.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function khse_minify_html( $html ) {
	$original = (string) $html;
	$protected = array();

	$html = preg_replace_callback(
		'#<(script|style|pre|textarea)\b[^>]*>.*?</\1>#is',
		function ( $match ) use ( &$protected ) {
			$key = '%%KHSE_PROTECTED_' . count( $protected ) . '%%';
			$protected[ $key ] = $match[0];

			return $key;
		},
		$html
	);

	$html = preg_replace( '/>\s+</', '><', $html );
	$html = preg_replace( '/[ \t]+/', ' ', $html );
	$html = trim( $html );

	$html = strtr( $html, $protected );

	return strlen( $html ) < strlen( $original ) ? $html : $original;
}

/**
 * Add media loading attributes and optional LCP preload.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function khse_optimize_media_tags( $html, $settings ) {
	$image_index = 0;
	$preloads    = array();
	$exclusions  = khse_get_lines_from_textarea( $settings['media_exclusions'] );
	$lcp_count   = (int) $settings['critical_image_count'];

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $match ) use ( &$image_index, &$preloads, $exclusions, $settings, $lcp_count ) {
				$tag = $match[0];

				if ( khse_html_tag_is_excluded( $tag, $exclusions ) ) {
					return $tag;
				}

				$image_index++;

				if ( 'yes' === $settings['critical_images'] && $image_index <= $lcp_count ) {
					if ( ! khse_html_has_attribute( $tag, 'loading' ) ) {
						$tag = khse_html_set_attribute( $tag, 'loading', 'eager' );
					}

					if ( ! khse_html_has_attribute( $tag, 'fetchpriority' ) ) {
						$tag = khse_html_set_attribute( $tag, 'fetchpriority', 'high' );
					}

					$src    = khse_html_get_attribute( $tag, 'src' );
					$srcset = khse_html_get_attribute( $tag, 'srcset' );
					$sizes  = khse_html_get_attribute( $tag, 'sizes' );

					if ( '' !== $src ) {
						$preloads[ $src ] = array(
							'src'    => $src,
							'srcset' => $srcset,
							'sizes'  => $sizes,
						);
					}
				} elseif ( 'yes' === $settings['lazy_media'] && ! khse_html_has_attribute( $tag, 'loading' ) ) {
					$tag = khse_html_set_attribute( $tag, 'loading', 'lazy' );
				}

				if ( ! khse_html_has_attribute( $tag, 'decoding' ) ) {
					$tag = khse_html_set_attribute( $tag, 'decoding', 'async' );
				}

				return $tag;
			},
			$html
		);
	}

	if ( 'yes' === $settings['lazy_media'] ) {
		$html = preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			function ( $match ) use ( $exclusions ) {
				$tag = $match[0];

				if ( khse_html_tag_is_excluded( $tag, $exclusions ) || khse_html_has_attribute( $tag, 'loading' ) ) {
					return $tag;
				}

				return khse_html_set_attribute( $tag, 'loading', 'lazy' );
			},
			$html
		);
	}

	if ( ! empty( $preloads ) ) {
		$links = '';

		foreach ( $preloads as $preload ) {
			if ( false === strpos( $html, 'href="' . $preload['src'] . '"' ) ) {
				$link = '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url( $preload['src'] ) . '"';

				if ( '' !== $preload['srcset'] ) {
					$link .= ' imagesrcset="' . esc_attr( $preload['srcset'] ) . '"';
				}

				if ( '' !== $preload['sizes'] ) {
					$link .= ' imagesizes="' . esc_attr( $preload['sizes'] ) . '"';
				}

				$links .= $link . '>';
			}
		}

		if ( '' !== $links ) {
			$html = preg_replace( '#</head>#i', $links . '</head>', $html, 1 );
		}
	}

	return $html;
}

/**
 * Page optimization: HTML, CSS, and JavaScript asset helpers.
 */

function khse_add_font_preconnects( $html ) {
	$links = '';

	if ( false !== strpos( $html, 'fonts.googleapis.com' ) && false === strpos( $html, 'href="https://fonts.googleapis.com"' ) ) {
		$links .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
	}

	if ( false !== strpos( $html, 'fonts.gstatic.com' ) && false === strpos( $html, 'href="https://fonts.gstatic.com"' ) ) {
		$links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
	}

	if ( '' !== $links ) {
		$html = preg_replace( '#</head>#i', $links . '</head>', $html, 1 );
	}

	return $html;
}

/**
 * Defer external scripts.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function khse_defer_scripts( $html, $settings ) {
	// PHP analysis is the safe mode. Do not let the legacy broad pass defer the
	// scripts the analyzer deliberately rejected.
	if ( 'yes' === $settings['js_analysis'] ) {
		return $html;
	}

	$exclusions = khse_get_lines_from_textarea( $settings['js_exclusions'] );

	return preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*(["\']).*?\1[^>]*></script>#is',
		function ( $match ) use ( $exclusions ) {
			$tag = $match[0];

			if (
				khse_html_tag_is_excluded( $tag, $exclusions ) ||
				khse_html_has_attribute( $tag, 'defer' ) ||
				khse_html_has_attribute( $tag, 'async' ) ||
				false !== stripos( $tag, 'type="module"' ) ||
				false !== stripos( $tag, "type='module'" )
			) {
				return $tag;
			}

			return preg_replace( '/^<script\b/i', '<script defer', $tag, 1 );
		},
		$html
	);
}

/**
 * Decide whether a CSS selector is safe to remove.
 *
 * Dynamic and unsupported selectors are always kept. This is intentionally
 * conservative because a false negative costs bytes while a false positive
 * can break an interactive frontend.
 *
 * @param string $selector Selector text.
 * @param string $html     Page HTML.
 * @param array  $safelist Protected selector fragments.
 *
 * @return bool
 */
function khse_css_selector_is_used( $selector, $html, $safelist ) {
	$selector = trim( preg_replace( '/\s+/', ' ', (string) $selector ) );

	if ( '' === $selector ) {
		return false;
	}

	foreach ( $safelist as $safe ) {
		$safe = trim( (string) $safe );

		if ( '' !== $safe && false !== stripos( $selector, $safe ) ) {
			return true;
		}
	}

	foreach ( array( ':', '[', ']', '>', '+', '~', '*', '{', '}', '\\' ) as $dynamic_character ) {
		if ( false !== strpos( $selector, $dynamic_character ) ) {
			return true;
		}
	}

	$class_names = array();
	$id_names    = array();
	preg_match_all( '/\.([a-zA-Z_][a-zA-Z0-9_-]*)/', $selector, $class_matches );
	preg_match_all( '/#([a-zA-Z_][a-zA-Z0-9_-]*)/', $selector, $id_matches );
	$class_names = isset( $class_matches[1] ) ? $class_matches[1] : array();
	$id_names    = isset( $id_matches[1] ) ? $id_matches[1] : array();

	foreach ( $class_names as $class_name ) {
		if ( ! preg_match_all( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $html, $class_attributes ) ) {
			return false;
		}

		$class_found = false;

		foreach ( $class_attributes[2] as $class_attribute ) {
			if ( preg_match( '/(?:^|\s)' . preg_quote( $class_name, '/' ) . '(?:\s|$)/', $class_attribute ) ) {
				$class_found = true;
				break;
			}
		}

		if ( ! $class_found ) {
			return false;
		}
	}

	foreach ( $id_names as $id_name ) {
		if ( ! preg_match( '/\bid\s*=\s*(["\'])' . preg_quote( $id_name, '/' ) . '\1/is', $html ) ) {
			return false;
		}
	}

	if ( empty( $class_names ) && empty( $id_names ) && preg_match( '/^([a-z][a-z0-9-]*)$/i', $selector, $tag_match ) ) {
		return false !== stripos( $html, '<' . strtolower( $tag_match[1] ) );
	}

	// Complex selectors we cannot prove are unused remain in the stylesheet.
	return true;
}

/**
 * Conservatively remove unused top-level CSS rules without external tools.
 * Nested at-rules are preserved as complete blocks.
 *
 * @param string $css      CSS source.
 * @param string $html     Page HTML.
 * @param array  $safelist Protected selector fragments.
 *
 * @return string
 */
function khse_purge_css_conservative( $css, $html, $safelist ) {
	$css = (string) $css;

	if ( '' === trim( $css ) || preg_match( '/@import\b/i', $css ) ) {
		return $css;
	}

	$output = '';
	$start  = 0;
	$depth  = 0;
	$quote  = '';
	$comment = false;
	$length = strlen( $css );

	for ( $i = 0; $i < $length; $i++ ) {
		$char = $css[ $i ];
		$next = $i + 1 < $length ? $css[ $i + 1 ] : '';

		if ( $comment ) {
			if ( '*' === $char && '/' === $next ) {
				$comment = false;
				$i++;
			}
			continue;
		}

		if ( '' !== $quote ) {
			if ( '\\' === $char ) {
				$i++;
			} elseif ( $char === $quote ) {
				$quote = '';
			}
			continue;
		}

		if ( '/' === $char && '*' === $next ) {
			$comment = true;
			$i++;
			continue;
		}

		if ( '"' === $char || "'" === $char ) {
			$quote = $char;
			continue;
		}

		if ( '{' === $char ) {
			$depth++;
			continue;
		}

		if ( '}' !== $char ) {
			continue;
		}

		$depth--;

		if ( 0 !== $depth ) {
			continue;
		}

		$rule    = substr( $css, $start, $i - $start + 1 );
		$opening = strpos( $rule, '{' );
		$header  = false === $opening ? '' : trim( substr( $rule, 0, $opening ) );

		if ( '' !== $header && ( '@' === $header[0] || khse_css_selector_is_used( $header, $html, $safelist ) ) ) {
			$output .= $rule;
		}

		$start = $i + 1;
	}

	if ( $start < $length ) {
		// Keep comments, declarations, and malformed trailing CSS untouched.
		$output .= substr( $css, $start );
	}

	$output = khse_minify_css( $output );

	return '' === $output ? $css : $output;
}

/**
 * Store optimized CSS as a content-addressed public asset.
 *
 * @param string $css CSS content.
 *
 * @return array
 */
function khse_store_optimized_css_asset( $css ) {
	if ( ! function_exists( 'wp_upload_dir' ) ) {
		return array();
	}

	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'kh-speed-engine-assets/' . khse_get_blog_id() . '_' . khse_get_dir_hash();
	$file    = $dir . '/' . hash( 'sha256', (string) $css ) . '.css';

	if ( ! wp_mkdir_p( $dir ) || false === file_put_contents( $file, (string) $css, LOCK_EX ) ) {
		return array();
	}

	static $pruned = false;

	if ( ! $pruned ) {
		$pruned = true;
		$stale_before = time() - ( 14 * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 ) );

		foreach ( (array) glob( $dir . '/*.css' ) as $asset_file ) {
			if ( is_file( $asset_file ) && (int) filemtime( $asset_file ) < $stale_before ) {
				@unlink( $asset_file );
			}
		}
	}

	$base_dir = rtrim( wp_normalize_path( $uploads['basedir'] ), '/' );
	$normalized_file = wp_normalize_path( $file );

	if ( 0 !== strncasecmp( $normalized_file . '/', $base_dir . '/', strlen( $base_dir ) + 1 ) ) {
		return array();
	}

	$relative = ltrim( substr( $normalized_file, strlen( $base_dir ) ), '/' );

	return array(
		'file' => $file,
		'url'  => trailingslashit( $uploads['baseurl'] ) . str_replace( '\\', '/', $relative ),
	);
}

/**
 * Apply the built-in optimizer to inline style blocks.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function khse_apply_local_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<style\b([^>]*)>(.*?)</style>#is', $html, $matches, PREG_SET_ORDER ) ) {
		return $html;
	}

	$safelist       = khse_get_lines_from_textarea( $settings['ucss_safelist'] );
	$max_size       = (int) $settings['local_ucss_max_file_size'];
	$before_bytes   = 0;
	$after_bytes    = 0;
	$optimized      = 0;
	$skipped_blocks = 0;
	$replacements   = array();

	foreach ( $matches as $index => $match ) {
		$original_css = (string) $match[2];
		$before_bytes += strlen( $original_css );

		if ( strlen( $original_css ) > $max_size ) {
			$skipped_blocks++;
			$after_bytes += strlen( $original_css );
			$replacements[ $index ] = $match[0];
			continue;
		}

		$optimized_css = khse_purge_css_conservative( $original_css, $html, $safelist );

		if ( '' === trim( $optimized_css ) || strlen( $optimized_css ) >= strlen( $original_css ) ) {
			$skipped_blocks++;
			$after_bytes += strlen( $original_css );
			$replacements[ $index ] = $match[0];
			continue;
		}

		$optimized++;
		$after_bytes += strlen( $optimized_css );
		$replacements[ $index ] = '<style' . $match[1] . '>' . $optimized_css . '</style>';
	}

	$replacement_index = 0;
	$html = preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		function ( $match ) use ( &$replacement_index, $replacements ) {
			$replacement = isset( $replacements[ $replacement_index ] ) ? $replacements[ $replacement_index ] : $match[0];
			$replacement_index++;
			return $replacement;
		},
		$html
	);

	khse_set_page_optimization_runtime(
		'local_ucss',
		array(
			'status'  => $optimized > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s inline CSS before, %2$s after; %3$s optimized, %4$s kept unchanged.', 'kh-speed-engine' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 ),
				number_format_i18n( $optimized ),
				number_format_i18n( $skipped_blocks )
			),
			'metrics' => array(
				'beforeBytes'   => $before_bytes,
				'afterBytes'    => $after_bytes,
				'savedBytes'    => max( 0, $before_bytes - $after_bytes ),
				'blocks'        => count( $matches ),
				'skippedBlocks' => $skipped_blocks,
			),
		)
	);

	return $html;
}

/**
 * Apply the built-in optimizer to same-site stylesheet files.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function khse_apply_external_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<link\b[^>]*>#is', $html, $matches, PREG_SET_ORDER ) ) {
		return $html;
	}

	$safelist     = khse_get_lines_from_textarea( $settings['ucss_safelist'] );
	$max_size     = (int) $settings['external_ucss_max_file_size'];
	$before_bytes = 0;
	$after_bytes  = 0;
	$optimized    = 0;
	$skipped      = 0;
	$replacements = array();

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];

		if ( ! khse_is_external_ucss_candidate( $tag ) ) {
			continue;
		}

		$href = html_entity_decode( khse_html_get_attribute( $tag, 'href' ), ENT_QUOTES, 'UTF-8' );
		$path = khse_local_asset_url_to_path( $href );
		$size = '' !== $path && is_file( $path ) ? filesize( $path ) : false;

		if ( '' === $href || '' === $path || false === $size || $size <= 0 || $size > $max_size || ! is_readable( $path ) ) {
			$skipped++;
			continue;
		}

		$css = file_get_contents( $path );

		if ( false === $css || '' === trim( $css ) || preg_match( '/@import\b/i', $css ) ) {
			$skipped++;
			continue;
		}

		$before_bytes += strlen( $css );
		$optimized_css = khse_purge_css_conservative( $css, $html, $safelist );
		$optimized_css = khse_minify_css( khse_rewrite_css_relative_urls( $optimized_css, $href ) );

		if ( '' === trim( $optimized_css ) || strlen( $optimized_css ) >= strlen( $css ) ) {
			$skipped++;
			$after_bytes += strlen( $css );
			continue;
		}

		$asset = khse_store_optimized_css_asset( $optimized_css );

		if ( empty( $asset['url'] ) ) {
			$skipped++;
			$after_bytes += strlen( $css );
			continue;
		}

		$replacement = preg_replace_callback(
			"/\\bhref\\s*=\\s*(?:([\"']).*?\\1|[^\\s>]+)/is",
			function ( $href_match ) use ( $asset ) {
				$quote = isset( $href_match[1] ) && '' !== $href_match[1] ? $href_match[1] : '"';
				return 'href=' . $quote . esc_url( $asset['url'] ) . $quote;
			},
			$tag,
			1
		);

		$replacements[ $index ] = false !== $replacement ? $replacement : $tag;
		$after_bytes += strlen( $optimized_css );
		$optimized++;
	}

	$link_index = 0;
	$html = preg_replace_callback(
		'#<link\b[^>]*>#is',
		function ( $match ) use ( &$link_index, $replacements ) {
			$replacement = isset( $replacements[ $link_index ] ) ? $replacements[ $link_index ] : $match[0];
			$link_index++;
			return $replacement;
		},
		$html
	);

	khse_set_page_optimization_runtime(
		'external_ucss',
		array(
			'status'  => $optimized > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s external CSS before, %2$s after; %3$s files optimized, %4$s skipped.', 'kh-speed-engine' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 ),
				number_format_i18n( $optimized ),
				number_format_i18n( $skipped )
			),
			'metrics' => array(
				'files'          => $optimized + $skipped,
				'optimizedFiles' => $optimized,
				'skippedFiles'   => $skipped,
				'beforeBytes'    => $before_bytes,
				'afterBytes'     => $after_bytes,
				'savedBytes'     => max( 0, $before_bytes - $after_bytes ),
			),
		)
	);

	return $html;
}

/**
 * Check if a stylesheet link is safe for external UCSS.
 *
 * @param string $tag HTML link tag.
 *
 * @return bool
 */
function khse_is_external_ucss_candidate( $tag ) {
	$rel = strtolower( khse_html_get_attribute( $tag, 'rel' ) );

	if ( '' === $rel || false === strpos( $rel, 'stylesheet' ) ) {
		return false;
	}

	if (
		false !== strpos( $rel, 'alternate' ) ||
		false !== strpos( $rel, 'preload' ) ||
		khse_html_has_attribute( $tag, 'disabled' ) ||
		khse_html_has_attribute( $tag, 'integrity' ) ||
		khse_html_has_attribute( $tag, 'crossorigin' )
	) {
		return false;
	}

	return true;
}

/**
 * Normalize URL path segments.
 *
 * @param string $path URL path.
 *
 * @return string
 */
function khse_normalize_url_path_segments( $path ) {
	$parts = explode( '/', (string) $path );
	$out   = array();

	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}

		if ( '..' === $part ) {
			array_pop( $out );
			continue;
		}

		$out[] = $part;
	}

	return '/' . implode( '/', $out );
}

/**
 * Resolve a CSS url() value against a stylesheet URL.
 *
 * @param string $url           Raw CSS URL.
 * @param string $stylesheet_url Stylesheet URL.
 *
 * @return string
 */
function khse_resolve_css_url( $url, $stylesheet_url ) {
	$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );

	if (
		'' === $url ||
		0 === strpos( $url, '#' ) ||
		0 === stripos( $url, 'data:' ) ||
		0 === stripos( $url, 'http:' ) ||
		0 === stripos( $url, 'https:' ) ||
		0 === strpos( $url, '//' )
	) {
		return $url;
	}

	if ( 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}

	$base = html_entity_decode( (string) $stylesheet_url, ENT_QUOTES, 'UTF-8' );

	if ( false === strpos( $base, '//' ) ) {
		$base = home_url( '/' . ltrim( $base, '/' ) );
	}

	$parts = parse_url( $base );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $url;
	}

	$base_path = isset( $parts['path'] ) ? $parts['path'] : '/';
	$base_dir  = preg_replace( '#/[^/]*$#', '/', $base_path );
	$path      = khse_normalize_url_path_segments( $base_dir . $url );
	$port      = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

	return $parts['scheme'] . '://' . $parts['host'] . $port . $path;
}

/**
 * Rewrite relative CSS url() values before external CSS is inlined.
 *
 * @param string $css           CSS content.
 * @param string $stylesheet_url Stylesheet URL.
 *
 * @return string
 */
function khse_rewrite_css_relative_urls( $css, $stylesheet_url ) {
	return preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $match ) use ( $stylesheet_url ) {
			$resolved = khse_resolve_css_url( $match[2], $stylesheet_url );

			return 'url("' . esc_url_raw( $resolved ) . '")';
		},
		$css
	);
}

/**
 * Analyze JavaScript source with conservative PHP heuristics.
 *
 * @param string $source JavaScript source.
 *
 * @return array
 */
function khse_analyze_javascript_source( $source ) {
	$source = (string) $source;
	$unsafe = array(
		'/\bdocument\s*\.\s*(?:write|writeln|currentScript)\b/i',
		'/\b(?:window|document)\s*\.\s*on(?:load|beforeunload|unload)\b/i',
		'/\b(?:eval|Function)\s*\(/i',
		'/\b(?:setTimeout|setInterval)\s*\(\s*["\']/i',
		'/\bwindow\s*\[\s*["\']/i',
	);

	$reasons = array();

	foreach ( $unsafe as $pattern ) {
		if ( preg_match( $pattern, $source ) ) {
			$reasons[] = $pattern;
		}
	}

	return array(
		'safeToDefer' => empty( $reasons ),
		'reasons'     => $reasons,
	);
}

/**
 * Run bounded PHP script analysis and defer only proven-safe local scripts.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function khse_apply_local_js_analysis( $html, $settings ) {
	if ( ! preg_match( '#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*></script>#is', $html ) ) {
		khse_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status'  => 'no_change',
				'detail'  => __( 'No external scripts found.', 'kh-speed-engine' ),
				'metrics' => array( 'analyzed' => 0, 'deferred' => 0, 'skipped' => 0 ),
			)
		);
		return $html;
	}

	$exclusions   = khse_get_lines_from_textarea( isset( $settings['js_exclusions'] ) ? $settings['js_exclusions'] : '' );
	$max_script   = isset( $settings['js_analysis_max_script_bytes'] ) ? (int) $settings['js_analysis_max_script_bytes'] : 262144;
	$max_total    = isset( $settings['js_analysis_max_total_bytes'] ) ? (int) $settings['js_analysis_max_total_bytes'] : 1048576;
	$total_bytes  = 0;
	$analyzed     = 0;
	$deferred     = 0;
	$skipped      = 0;

	$html = preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*></script>#is',
		function ( $match ) use ( $exclusions, $max_script, $max_total, &$total_bytes, &$analyzed, &$deferred, &$skipped ) {
			$tag = $match[0];

			if (
				khse_html_tag_is_excluded( $tag, $exclusions ) ||
				khse_html_has_attribute( $tag, 'defer' ) ||
				khse_html_has_attribute( $tag, 'async' ) ||
				false !== stripos( $tag, 'type="module"' ) ||
				false !== stripos( $tag, "type='module'" )
			) {
				$skipped++;
				return $tag;
			}

			$path = khse_local_asset_url_to_path( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );

			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
				$skipped++;
				return $tag;
			}

			$size = filesize( $path );

			if ( false === $size || $size <= 0 || $size > $max_script || ( $total_bytes + $size ) > $max_total ) {
				$skipped++;
				return $tag;
			}

			$source = file_get_contents( $path );

		if ( false === $source ) {
			$skipped++;
			return $tag;
		}

		$total_bytes += $size;
		$analyzed++;

		if ( empty( khse_analyze_javascript_source( $source )['safeToDefer'] ) ) {
			return $tag;
		}

		$deferred++;
		return preg_replace( '/^<script\b/i', '<script defer', $tag, 1 );
		},
		$html
	);

	khse_set_page_optimization_runtime(
		'js_analysis',
		array(
			'status'  => $deferred > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s local scripts analyzed; %2$s safely deferred; %3$s skipped by exclusions or limits.', 'kh-speed-engine' ),
				number_format_i18n( $analyzed ),
				number_format_i18n( $deferred ),
				number_format_i18n( $skipped )
			),
			'metrics' => array(
				'analyzed'    => $analyzed,
				'deferred'    => $deferred,
				'skipped'     => $skipped,
				'totalBytes'  => $total_bytes,
			),
		)
	);

	return $html;
}

/**
 * Optimize generated HTML before writing to cache.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function khse_optimize_html( $html ) {
	if ( ! khse_is_page_optimization_enabled() || empty( $html ) ) {
		return $html;
	}

	$settings = khse_get_page_optimization_settings();
	$html     = apply_filters( 'khse_before_page_optimization', $html, $settings );
	khse_reset_page_optimization_runtime();

	if ( 'yes' === $settings['remove_comments'] ) {
		$html = khse_remove_html_comments( $html );
	}

	if ( 'yes' === $settings['minify_inline_css'] ) {
		$html = khse_minify_inline_css_blocks( $html );
	}

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = khse_optimize_media_tags( $html, $settings );
	}

	if ( 'yes' === $settings['preconnect_fonts'] ) {
		$html = khse_add_font_preconnects( $html );
	}

	if ( 'yes' === $settings['external_ucss'] ) {
		$html = khse_apply_external_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['local_ucss'] ) {
		$html = khse_apply_local_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['js_analysis'] ) {
		$html = khse_apply_local_js_analysis( $html, $settings );
	}

	if ( 'yes' === $settings['defer_js'] ) {
		$html = khse_defer_scripts( $html, $settings );
	}

	if ( 'yes' === $settings['minify_html'] ) {
		$html = khse_minify_html( $html );
	}

	return apply_filters( 'khse_after_page_optimization', $html, $settings );
}

/**
 * Get page optimization requirement checks.
 *
 * @return array
 */
function khse_get_page_optimization_requirements() {
	$work_dir       = khse_get_page_optimization_work_dir();
	$work_dir_ready = is_dir( $work_dir ) && is_writable( $work_dir );

	return array(
		'cache_status' => array(
			'label'  => __( 'Page cache enabled', 'kh-speed-engine' ),
			'passed' => 'enable' === get_option( 'khse_option_caching_status', 'disable' ),
			'detail' => get_option( 'khse_option_caching_status', 'disable' ),
		),
		'permalink'    => array(
			'label'  => __( 'Pretty permalinks enabled', 'kh-speed-engine' ),
			'passed' => '' !== get_option( 'permalink_structure', '' ),
			'detail' => '' !== get_option( 'permalink_structure', '' ) ? get_option( 'permalink_structure' ) : __( 'Plain permalinks', 'kh-speed-engine' ),
		),
		'server_gzip'  => array(
			'label'  => __( 'Text compression', 'kh-speed-engine' ),
			'passed' => extension_loaded( 'zlib' ),
			'detail' => extension_loaded( 'zlib' ) ? __( 'PHP zlib available; enable gzip/Brotli on your web server for best results.', 'kh-speed-engine' ) : __( 'Enable gzip/Brotli on your web server.', 'kh-speed-engine' ),
		),
		'work_dir'     => array(
			'label'  => __( 'Local optimizer workspace', 'kh-speed-engine' ),
			'passed' => $work_dir_ready,
			'detail' => $work_dir_ready ? __( 'Workspace is writable.', 'kh-speed-engine' ) : __( 'Workspace is not writable.', 'kh-speed-engine' ),
		),
	);
}
