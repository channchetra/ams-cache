<?php
/**
 * KH Speed Engine — optimizer/js.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
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
