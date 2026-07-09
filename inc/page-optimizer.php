<?php
/**
 * AMS Cache — page-optimizer.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_get_default_page_optimization_settings() {
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
		'bun_path'             => 'bun',
		'purgecss_path'        => 'purgecss',
		'ucss_safelist'        => "active\nopen\nshow\nis-active\ncurrent\nmenu-item\nmenu-item-has-children\ncurrent-menu-item\ncurrent-menu-parent\ncurrent-menu-ancestor\nsub-menu\ndropdown\ndropdown-menu\ndropdown-toggle\nsfHover\nis-open\nis-visible\nslick\nslick-active\nslick-current\nslick-initialized\nswiper\nswiper-slide\nswiper-wrapper\nswiper-button-next\nswiper-button-prev\nrevslider\nrev_slider\nrs-module\nrs-layer\nrs-slide\nsr7\ntp-caption\nwoocommerce\nwp-block\nalignwide\nalignfull",
		'critical_image_count' => 1,
		'external_ucss_max_file_size' => 307200,
		'media_exclusions'     => "logo\navatar\ncaptcha",
		'js_exclusions'        => "jquery\nwp-includes/js/jquery\nwoocommerce\ncart\ncheckout\ngoogletagmanager\ngtm\nrecaptcha\nstripe\npaypal\nrevslider\nrev_slider\nrs6\nsr7\nthemepunch\nrbtools\nslider-revolution\nrevolution\nslick\nswiper\nowl.carousel\nsmartmenus\nsuperfish\nbootstrap\ndropdown\nmenu-image\nmenu\nnavigation\nhoverIntent",
	);
}

/**
 * Get page optimization settings.
 *
 * @return array
 */
function scm_get_page_optimization_settings() {
	$settings = (array) get_option( 'scm_option_page_optimization', array() );
	$settings = array_merge( scm_get_default_page_optimization_settings(), $settings );

	$settings['critical_image_count'] = max( 0, min( 5, (int) $settings['critical_image_count'] ) );
	$settings['bun_path']             = trim( (string) $settings['bun_path'] );
	$settings['purgecss_path']        = trim( (string) $settings['purgecss_path'] );
	$settings['external_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['external_ucss_max_file_size'] ) );
	$settings['ucss_safelist']        = scm_merge_textarea_lines( $settings['ucss_safelist'], scm_get_protected_ucss_safelist() );
	$settings['js_exclusions']        = scm_merge_textarea_lines( $settings['js_exclusions'], scm_get_protected_js_exclusions() );

	return $settings;
}

/**
 * Get CSS selectors/classes that stay protected for interactive frontends.
 *
 * @return array
 */
function scm_get_protected_ucss_safelist() {
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
function scm_get_protected_js_exclusions() {
	return array(
		'jquery',
		'wp-includes/js/jquery',
		'woocommerce',
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
function scm_is_page_optimization_enabled() {
	$settings = scm_get_page_optimization_settings();

	return 'yes' === $settings['status'];
}

/**
 * Get the optimization report directory.
 *
 * @return string
 */
function scm_get_page_optimization_report_dir() {
	return scm_get_upload_dir() . '/optimization_reports';
}

/**
 * Get local optimizer workspace root.
 *
 * @return string
 */
function scm_get_page_optimization_work_dir() {
	return scm_get_upload_dir() . '/optimization_work';
}

/**
 * Get latest optimization runtime metadata.
 *
 * @return array
 */
function scm_get_page_optimization_runtime() {
	return isset( $GLOBALS['scm_page_optimization_runtime'] ) && is_array( $GLOBALS['scm_page_optimization_runtime'] )
		? $GLOBALS['scm_page_optimization_runtime']
		: array();
}

/**
 * Reset optimization runtime metadata.
 *
 * @return void
 */
function scm_reset_page_optimization_runtime() {
	$GLOBALS['scm_page_optimization_runtime'] = array();
}

/**
 * Store optimization runtime metadata.
 *
 * @param string $key   Runtime key.
 * @param array  $value Runtime payload.
 *
 * @return void
 */
function scm_set_page_optimization_runtime( $key, $value ) {
	if ( ! isset( $GLOBALS['scm_page_optimization_runtime'] ) || ! is_array( $GLOBALS['scm_page_optimization_runtime'] ) ) {
		$GLOBALS['scm_page_optimization_runtime'] = array();
	}

	$GLOBALS['scm_page_optimization_runtime'][ $key ] = $value;
}

/**
 * Count inline style bytes.
 *
 * @param string $html HTML content.
 *
 * @return int
 */
function scm_get_inline_style_bytes( $html ) {
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
function scm_count_html_matches( $pattern, $html ) {
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
function scm_build_page_optimization_feature_report( $enabled, $status, $detail, $metrics = array() ) {
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
function scm_build_page_optimization_report( $before, $after, $settings, $uri = '', $data_type = '' ) {
	$before_bytes    = strlen( (string) $before );
	$after_bytes     = strlen( (string) $after );
	$comments_before = scm_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $before );
	$comments_after  = scm_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $after );
	$styles_before   = scm_get_inline_style_bytes( $before );
	$styles_after    = scm_get_inline_style_bytes( $after );
	$lazy_before     = scm_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $before );
	$lazy_after      = scm_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $after );
	$critical_before = scm_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $before );
	$critical_after  = scm_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $after );
	$fonts_before    = scm_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $before );
	$fonts_after     = scm_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $after );
	$defer_before    = scm_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $before );
	$defer_after     = scm_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $after );
	$features        = array();
	$runtime         = scm_get_page_optimization_runtime();

	$features['minify_html'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['minify_html'],
		'yes' !== $settings['minify_html'] ? 'disabled' : ( $after_bytes < $before_bytes ? 'applied' : 'no_change' ),
		'yes' !== $settings['minify_html']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before size, 2: after size. */
				__( '%1$s before, %2$s after.', 'ams-cache' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 )
			)
	);

	$features['remove_comments'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['remove_comments'],
		'yes' !== $settings['remove_comments'] ? 'disabled' : ( $comments_after < $comments_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['remove_comments']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before comments, 2: after comments. */
				__( '%1$s comments before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $comments_before ),
				number_format_i18n( $comments_after )
			)
	);

	$features['minify_inline_css'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['minify_inline_css'],
		'yes' !== $settings['minify_inline_css'] ? 'disabled' : ( $styles_after < $styles_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['minify_inline_css']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before size, 2: after size. */
				__( '%1$s inline CSS before, %2$s after.', 'ams-cache' ),
				size_format( $styles_before, 2 ),
				size_format( $styles_after, 2 )
			)
	);

	$features['lazy_media'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['lazy_media'],
		'yes' !== $settings['lazy_media'] ? 'disabled' : ( $lazy_after > $lazy_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['lazy_media']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before tags, 2: after tags. */
				__( '%1$s lazy tags before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $lazy_before ),
				number_format_i18n( $lazy_after )
			)
	);

	$features['critical_images'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['critical_images'],
		'yes' !== $settings['critical_images'] ? 'disabled' : ( $critical_after > $critical_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['critical_images']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before tags, 2: after tags. */
				__( '%1$s priority images before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $critical_before ),
				number_format_i18n( $critical_after )
			)
	);

	$features['preconnect_fonts'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['preconnect_fonts'],
		'yes' !== $settings['preconnect_fonts'] ? 'disabled' : ( $fonts_after > $fonts_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['preconnect_fonts']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before links, 2: after links. */
				__( '%1$s font hints before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $fonts_before ),
				number_format_i18n( $fonts_after )
			)
	);

	$features['defer_js'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['defer_js'],
		'yes' !== $settings['defer_js'] ? 'disabled' : ( $defer_after > $defer_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['defer_js']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before scripts, 2: after scripts. */
				__( '%1$s deferred scripts before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $defer_before ),
				number_format_i18n( $defer_after )
			)
	);

	$features['external_ucss'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['external_ucss'],
		'yes' !== $settings['external_ucss']
			? 'disabled'
			: ( isset( $runtime['external_ucss']['status'] ) ? $runtime['external_ucss']['status'] : 'failed' ),
		'yes' === $settings['external_ucss']
			? ( isset( $runtime['external_ucss']['detail'] ) ? $runtime['external_ucss']['detail'] : __( 'External UCSS engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['external_ucss']['metrics'] ) ? $runtime['external_ucss']['metrics'] : array()
	);

	$features['local_ucss'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['local_ucss'],
		'yes' !== $settings['local_ucss']
			? 'disabled'
			: ( isset( $runtime['local_ucss']['status'] ) ? $runtime['local_ucss']['status'] : 'failed' ),
		'yes' === $settings['local_ucss']
			? ( isset( $runtime['local_ucss']['detail'] ) ? $runtime['local_ucss']['detail'] : __( 'Local UCSS engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['local_ucss']['metrics'] ) ? $runtime['local_ucss']['metrics'] : array()
	);

	$features['js_analysis'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['js_analysis'],
		'yes' !== $settings['js_analysis']
			? 'disabled'
			: ( isset( $runtime['js_analysis']['status'] ) ? $runtime['js_analysis']['status'] : 'failed' ),
		'yes' === $settings['js_analysis']
			? ( isset( $runtime['js_analysis']['detail'] ) ? $runtime['js_analysis']['detail'] : __( 'JS analysis engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['js_analysis']['metrics'] ) ? $runtime['js_analysis']['metrics'] : array()
	);

	$applied_count = 0;

	foreach ( $features as $feature ) {
		if ( 'applied' === $feature['status'] ) {
			$applied_count++;
		}
	}

	$saved_bytes    = max( 0, $before_bytes - $after_bytes );
	$expanded_bytes = max( 0, $after_bytes - $before_bytes );

	return array(
		'uri'            => scm_normalize_cache_uri( $uri ),
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
		'overallStatus'  => ! scm_is_page_optimization_enabled() ? 'disabled' : ( $applied_count > 0 ? 'applied' : 'no_change' ),
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
function scm_write_page_optimization_report( $uri, $report ) {
	$dir = scm_get_page_optimization_report_dir();

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$key  = md5( scm_normalize_cache_uri( $uri ) );
	$file = trailingslashit( $dir ) . $key . '.json';

	file_put_contents( $file, wp_json_encode( $report ) );
	scm_prune_page_optimization_reports();
}

/**
 * Keep the report directory bounded for large sites.
 *
 * @param int $limit Maximum report files to keep.
 *
 * @return int Number of deleted reports.
 */
function scm_prune_page_optimization_reports( $limit = 200 ) {
	$dir     = scm_get_page_optimization_report_dir();
	$deleted = 0;
	$limit   = max( 20, min( 1000, (int) apply_filters( 'scm_page_optimization_report_limit', $limit ) ) );

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
function scm_get_page_optimization_reports( $limit = 20, $offset = 0 ) {
	$dir     = scm_get_page_optimization_report_dir();
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
function scm_count_page_optimization_reports() {
	$dir   = scm_get_page_optimization_report_dir();
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
function scm_get_lines_from_textarea( $value ) {
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
function scm_merge_textarea_lines( $value, $extra ) {
	$lines = is_array( $value ) ? $value : scm_get_lines_from_textarea( $value );

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
function scm_create_page_optimization_workspace( $prefix ) {
	$root = scm_get_page_optimization_work_dir();

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
function scm_delete_page_optimization_workspace( $dir ) {
	$root = realpath( scm_get_page_optimization_work_dir() );
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
function scm_html_tag_is_excluded( $tag, $keywords ) {
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
function scm_local_asset_url_to_path( $url ) {
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

	if ( '' === $path || false !== strpos( $path, '..' ) ) {
		return '';
	}

	$full_path = wp_normalize_path( ABSPATH . $path );
	$root_path = wp_normalize_path( ABSPATH );

	if ( 0 !== strpos( $full_path, $root_path ) ) {
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
function scm_html_get_attribute( $tag, $attr ) {
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
function scm_html_has_attribute( $tag, $attr ) {
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
function scm_html_set_attribute( $tag, $attr, $value ) {
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
function scm_html_add_boolean_attribute( $tag, $attr ) {
	if ( scm_html_has_attribute( $tag, $attr ) ) {
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
function scm_minify_css( $css ) {
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
function scm_remove_html_comments( $html ) {
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
function scm_minify_inline_css_blocks( $html ) {
	return preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		function ( $match ) {
			$minified = scm_minify_css( $match[2] );

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
function scm_minify_html( $html ) {
	$original = (string) $html;
	$protected = array();

	$html = preg_replace_callback(
		'#<(script|style|pre|textarea)\b[^>]*>.*?</\1>#is',
		function ( $match ) use ( &$protected ) {
			$key = '%%SCM_PROTECTED_' . count( $protected ) . '%%';
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
function scm_optimize_media_tags( $html, $settings ) {
	$image_index = 0;
	$preloads    = array();
	$exclusions  = scm_get_lines_from_textarea( $settings['media_exclusions'] );
	$lcp_count   = (int) $settings['critical_image_count'];

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $match ) use ( &$image_index, &$preloads, $exclusions, $settings, $lcp_count ) {
				$tag = $match[0];

				if ( scm_html_tag_is_excluded( $tag, $exclusions ) ) {
					return $tag;
				}

				$image_index++;

				if ( 'yes' === $settings['critical_images'] && $image_index <= $lcp_count ) {
					$tag = scm_html_set_attribute( $tag, 'loading', 'eager' );
					$tag = scm_html_set_attribute( $tag, 'fetchpriority', 'high' );

					$src    = scm_html_get_attribute( $tag, 'src' );
					$srcset = scm_html_get_attribute( $tag, 'srcset' );
					$sizes  = scm_html_get_attribute( $tag, 'sizes' );

					if ( '' !== $src ) {
						$preloads[ $src ] = array(
							'src'    => $src,
							'srcset' => $srcset,
							'sizes'  => $sizes,
						);
					}
				} elseif ( 'yes' === $settings['lazy_media'] && ! scm_html_has_attribute( $tag, 'loading' ) ) {
					$tag = scm_html_set_attribute( $tag, 'loading', 'lazy' );
				}

				if ( ! scm_html_has_attribute( $tag, 'decoding' ) ) {
					$tag = scm_html_set_attribute( $tag, 'decoding', 'async' );
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

				if ( scm_html_tag_is_excluded( $tag, $exclusions ) || scm_html_has_attribute( $tag, 'loading' ) ) {
					return $tag;
				}

				return scm_html_set_attribute( $tag, 'loading', 'lazy' );
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
