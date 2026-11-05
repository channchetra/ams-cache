<?php
/**
 * KH Speed Engine — optimizer/pipeline.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
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
