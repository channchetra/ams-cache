<?php
/**
 * AMS Cache — cache-utils.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_add_font_preconnects( $html ) {
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
function scm_defer_scripts( $html, $settings ) {
	// PHP analysis is the safe mode. Do not let the legacy broad pass defer the
	// scripts the analyzer deliberately rejected.
	if ( 'yes' === $settings['js_analysis'] ) {
		return $html;
	}

	$exclusions = scm_get_lines_from_textarea( $settings['js_exclusions'] );

	return preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*(["\']).*?\1[^>]*></script>#is',
		function ( $match ) use ( $exclusions ) {
			$tag = $match[0];

			if (
				scm_html_tag_is_excluded( $tag, $exclusions ) ||
				scm_html_has_attribute( $tag, 'defer' ) ||
				scm_html_has_attribute( $tag, 'async' ) ||
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
 * Build one PurgeCSS CLI command.
 *
 * @param string $purgecss   Escaped PurgeCSS executable.
 * @param array  $css_files  Relative CSS filenames.
 * @param array  $safelist   Safelisted selectors.
 * @param string $output_dir Relative output directory.
 *
 * @return string
 */
function scm_build_local_ucss_command( $purgecss, $css_files, $safelist, $output_dir ) {
	$command = $purgecss . ' --css';

	foreach ( $css_files as $css_file ) {
		$command .= ' ' . escapeshellarg( $css_file );
	}

	$command .= ' --content ' . escapeshellarg( 'content.html' );
	$command .= ' --output ' . escapeshellarg( $output_dir );
	$command .= ' --font-face --keyframes';

	if ( ! empty( $safelist ) ) {
		$command .= ' --safelist';

		foreach ( $safelist as $selector ) {
			$command .= ' ' . escapeshellarg( $selector );
		}
	}

	return $command;
}

/**
 * Pick useful one-line optimizer failure detail from noisy CLI output.
 *
 * @param string $output   CLI output.
 * @param string $fallback Fallback detail.
 *
 * @return string
 */
function scm_get_local_optimizer_error_detail( $output, $fallback ) {
	$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $output ) );

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' !== $line && preg_match( '/(?:CssSyntaxError|Unknown word|Unclosed block|Missed semicolon)/i', $line ) ) {
			return $line;
		}
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if (
			'' !== $line &&
			0 !== strpos( $line, 'at ' ) &&
			false === strpos( $line, '/node_modules/' ) &&
			false === strpos( $line, '\\node_modules\\' )
		) {
			return $line;
		}
	}

	return $fallback;
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
function scm_css_selector_is_used( $selector, $html, $safelist ) {
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
		if ( ! preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $html, $class_attributes ) ) {
			return false;
		}

		if ( ! preg_match( '/(?:^|\s)' . preg_quote( $class_name, '/' ) . '(?:\s|$)/', $class_attributes[2] ) ) {
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
function scm_purge_css_conservative( $css, $html, $safelist ) {
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

		if ( '' !== $header && ( '@' === $header[0] || scm_css_selector_is_used( $header, $html, $safelist ) ) ) {
			$output .= $rule;
		}

		$start = $i + 1;
	}

	if ( $start < $length ) {
		// Keep comments, declarations, and malformed trailing CSS untouched.
		$output .= substr( $css, $start );
	}

	$output = scm_minify_css( $output );

	return '' === $output ? $css : $output;
}

/**
 * Store optimized CSS as a content-addressed public asset.
 *
 * @param string $css CSS content.
 *
 * @return array
 */
function scm_store_optimized_css_asset( $css ) {
	if ( ! function_exists( 'wp_upload_dir' ) ) {
		return array();
	}

	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'ams-cache-assets/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
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
function scm_apply_internal_local_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<style\b([^>]*)>(.*?)</style>#is', $html, $matches, PREG_SET_ORDER ) ) {
		return $html;
	}

	$safelist       = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
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

		$optimized_css = scm_purge_css_conservative( $original_css, $html, $safelist );

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

	scm_set_page_optimization_runtime(
		'local_ucss',
		array(
			'status'  => $optimized > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s inline CSS before, %2$s after; %3$s optimized, %4$s kept unchanged.', 'ams-cache' ),
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
function scm_apply_internal_external_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<link\b[^>]*>#is', $html, $matches, PREG_SET_ORDER ) ) {
		return $html;
	}

	$safelist     = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
	$max_size     = (int) $settings['external_ucss_max_file_size'];
	$before_bytes = 0;
	$after_bytes  = 0;
	$optimized    = 0;
	$skipped      = 0;
	$replacements = array();

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];

		if ( ! scm_is_external_ucss_candidate( $tag ) ) {
			continue;
		}

		$href = html_entity_decode( scm_html_get_attribute( $tag, 'href' ), ENT_QUOTES, 'UTF-8' );
		$path = scm_local_asset_url_to_path( $href );
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
		$optimized_css = scm_purge_css_conservative( $css, $html, $safelist );
		$optimized_css = scm_minify_css( scm_rewrite_css_relative_urls( $optimized_css, $href ) );

		if ( '' === trim( $optimized_css ) || strlen( $optimized_css ) >= strlen( $css ) ) {
			$skipped++;
			$after_bytes += strlen( $css );
			continue;
		}

		$asset = scm_store_optimized_css_asset( $optimized_css );

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

	scm_set_page_optimization_runtime(
		'external_ucss',
		array(
			'status'  => $optimized > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s external CSS before, %2$s after; %3$s files optimized, %4$s skipped.', 'ams-cache' ),
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
 * Run PurgeCSS on inline style blocks.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_local_ucss( $html, $settings ) {
	return scm_apply_internal_local_ucss( $html, $settings );

	if ( ! preg_match_all( '#<style\b([^>]*)>(.*?)</style>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No inline CSS blocks found.', 'ams-cache' ),
				'metrics' => array(
					'beforeBytes'   => 0,
					'afterBytes'    => 0,
					'savedBytes'    => 0,
					'blocks'        => 0,
					'skippedBlocks' => 0,
				),
			)
		);
		return $html;
	}

	$purgecss = scm_get_executable_command( $settings['purgecss_path'] );
	$workspace = scm_create_page_optimization_workspace( 'ucss' );

	if ( '' === $purgecss || '' === $workspace ) {
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => 'failed',
				'detail' => __( 'Local UCSS engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$content_file = trailingslashit( $workspace ) . 'content.html';
	$output_dir   = trailingslashit( $workspace ) . 'output';
	$css_files    = array();
	$before_bytes = 0;

	wp_mkdir_p( $output_dir );
	file_put_contents( $content_file, $html );

	foreach ( $matches as $index => $match ) {
		$css_file = 'style-' . $index . '.css';
		$css      = (string) $match[2];

		$before_bytes += strlen( $css );
		file_put_contents( trailingslashit( $workspace ) . $css_file, $css );
		$css_files[] = $css_file;
	}

	$safelist        = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
	$command         = scm_build_local_ucss_command( $purgecss, $css_files, $safelist, 'output' );
	$result          = scm_run_local_optimizer_command( $command, $workspace );
	$replacements    = array();
	$after_bytes     = 0;
	$skipped_blocks  = 0;
	$failure_detail  = '';
	$batch_succeeded = $result['passed'];

	foreach ( $matches as $index => $match ) {
		$css_file    = 'style-' . $index . '.css';
		$output_file = trailingslashit( $output_dir ) . $css_file;

		if ( ! $batch_succeeded ) {
			$block_output_dir = 'output-' . $index;
			wp_mkdir_p( trailingslashit( $workspace ) . $block_output_dir );

			$block_command = scm_build_local_ucss_command( $purgecss, array( $css_file ), $safelist, $block_output_dir );
			$block_result  = scm_run_local_optimizer_command( $block_command, $workspace );
			$output_file   = trailingslashit( $workspace ) . $block_output_dir . '/' . $css_file;

			if ( ! $block_result['passed'] ) {
				$skipped_blocks++;
				$after_bytes += strlen( (string) $match[2] );
				$replacements[ $index ] = $match[0];

				if ( '' === $failure_detail ) {
					$failure_detail = scm_get_local_optimizer_error_detail(
						$block_result['output'],
						__( 'PurgeCSS could not parse one inline CSS block.', 'ams-cache' )
					);
				}

				continue;
			}
		}

		if ( ! file_exists( $output_file ) ) {
			$skipped_blocks++;
			$after_bytes += strlen( (string) $match[2] );
			$replacements[ $index ] = $match[0];

			if ( '' === $failure_detail ) {
				$failure_detail = __( 'PurgeCSS output was incomplete.', 'ams-cache' );
			}

			continue;
		}

		$original_css = (string) $match[2];
		$purged_css   = (string) file_get_contents( $output_file );

		if ( '' === $purged_css || strlen( $purged_css ) >= strlen( $original_css ) ) {
			$skipped_blocks++;
			$after_bytes += strlen( $original_css );
			$replacements[ $index ] = $match[0];
			continue;
		}

		$after_bytes += strlen( $purged_css );
		$replacements[ $index ] = '<style' . $match[1] . '>' . $purged_css . '</style>';
	}

	if ( count( $matches ) === $skipped_blocks ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => '' !== $failure_detail ? 'failed' : 'no_change',
				'detail' => '' !== $failure_detail
					? $failure_detail
					: __( 'Local UCSS found no smaller inline CSS output.', 'ams-cache' ),
				'metrics' => array(
					'beforeBytes'   => $before_bytes,
					'afterBytes'    => $before_bytes,
					'savedBytes'    => 0,
					'blocks'        => count( $matches ),
					'skippedBlocks' => $skipped_blocks,
				),
			)
		);
		return $html;
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
	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'local_ucss',
		array(
			'status' => $after_bytes < $before_bytes ? 'applied' : 'no_change',
			'detail' => $skipped_blocks > 0
				? sprintf(
					/* translators: 1: before size, 2: after size, 3: block count, 4: skipped block count. */
					__( '%1$s inline CSS before, %2$s after across %3$s blocks; %4$s malformed blocks kept raw.', 'ams-cache' ),
					size_format( $before_bytes, 2 ),
					size_format( $after_bytes, 2 ),
					number_format_i18n( count( $matches ) ),
					number_format_i18n( $skipped_blocks )
				)
				: sprintf(
					/* translators: 1: before size, 2: after size, 3: block count. */
					__( '%1$s inline CSS before, %2$s after across %3$s blocks.', 'ams-cache' ),
					size_format( $before_bytes, 2 ),
					size_format( $after_bytes, 2 ),
					number_format_i18n( count( $matches ) )
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
 * Check if a stylesheet link is safe for external UCSS.
 *
 * @param string $tag HTML link tag.
 *
 * @return bool
 */
function scm_is_external_ucss_candidate( $tag ) {
	$rel = strtolower( scm_html_get_attribute( $tag, 'rel' ) );

	if ( '' === $rel || false === strpos( $rel, 'stylesheet' ) ) {
		return false;
	}

	if (
		false !== strpos( $rel, 'alternate' ) ||
		false !== strpos( $rel, 'preload' ) ||
		scm_html_has_attribute( $tag, 'disabled' ) ||
		scm_html_has_attribute( $tag, 'integrity' ) ||
		scm_html_has_attribute( $tag, 'crossorigin' )
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
function scm_normalize_url_path_segments( $path ) {
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
function scm_resolve_css_url( $url, $stylesheet_url ) {
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
	$path      = scm_normalize_url_path_segments( $base_dir . $url );
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
function scm_rewrite_css_relative_urls( $css, $stylesheet_url ) {
	return preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $match ) use ( $stylesheet_url ) {
			$resolved = scm_resolve_css_url( $match[2], $stylesheet_url );

			return 'url("' . esc_url_raw( $resolved ) . '")';
		},
		$css
	);
}

/**
 * Run PurgeCSS on same-site stylesheet files and inline the optimized output.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_external_ucss( $html, $settings ) {
	return scm_apply_internal_external_ucss( $html, $settings );

	if ( ! preg_match_all( '#<link\b[^>]*>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No stylesheet links found.', 'ams-cache' ),
				'metrics' => array(
					'files'          => 0,
					'optimizedFiles' => 0,
					'skippedFiles'   => 0,
					'beforeBytes'    => 0,
					'afterBytes'     => 0,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
	}

	$purgecss = scm_get_executable_command( $settings['purgecss_path'] );
	$workspace = scm_create_page_optimization_workspace( 'external-ucss' );

	if ( '' === $purgecss || '' === $workspace ) {
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status'  => 'failed',
				'detail'  => __( 'External UCSS engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);

		return $html;
	}

	$content_file = trailingslashit( $workspace ) . 'content.html';
	$output_dir   = trailingslashit( $workspace ) . 'output';
	$max_size     = (int) $settings['external_ucss_max_file_size'];
	$css_files    = array();
	$entries      = array();
	$before_bytes = 0;
	$after_bytes  = 0;
	$skipped      = 0;

	wp_mkdir_p( $output_dir );
	file_put_contents( $content_file, $html );

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];

		if ( ! scm_is_external_ucss_candidate( $tag ) ) {
			continue;
		}

		$href = html_entity_decode( scm_html_get_attribute( $tag, 'href' ), ENT_QUOTES, 'UTF-8' );
		$path = scm_local_asset_url_to_path( $href );

		if (
			'' === $href ||
			'' === $path ||
			! is_file( $path ) ||
			! is_readable( $path ) ||
			'css' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) )
		) {
			$skipped++;
			continue;
		}

		$file_size = filesize( $path );

		if ( false === $file_size || $file_size <= 0 || $file_size > $max_size ) {
			$skipped++;
			continue;
		}

		$css = file_get_contents( $path );

		if ( false === $css || '' === trim( $css ) ) {
			$skipped++;
			continue;
		}

		if ( preg_match( '/@import\b/i', $css ) ) {
			$skipped++;
			continue;
		}

		$css_file = 'stylesheet-' . count( $css_files ) . '.css';
		file_put_contents( trailingslashit( $workspace ) . $css_file, $css );

		$css_files[] = $css_file;
		$entries[]   = array(
			'index' => $index,
			'tag'   => $tag,
			'href'  => $href,
			'media' => scm_html_get_attribute( $tag, 'media' ),
			'file'  => $css_file,
			'bytes' => strlen( $css ),
		);
		$before_bytes += strlen( $css );
	}

	if ( empty( $entries ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No eligible same-site stylesheet files found.', 'ams-cache' ),
				'metrics' => array(
					'files'          => 0,
					'optimizedFiles' => 0,
					'skippedFiles'   => $skipped,
					'beforeBytes'    => 0,
					'afterBytes'     => 0,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
	}

	$safelist        = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
	$command         = scm_build_local_ucss_command( $purgecss, $css_files, $safelist, 'output' );
	$result          = scm_run_local_optimizer_command( $command, $workspace );
	$batch_succeeded = $result['passed'];
	$replacements    = array();
	$optimized       = 0;
	$failure_detail  = '';

	foreach ( $entries as $entry ) {
		$output_file = trailingslashit( $output_dir ) . $entry['file'];

		if ( ! $batch_succeeded ) {
			$block_output_dir = 'output-' . $entry['index'];
			wp_mkdir_p( trailingslashit( $workspace ) . $block_output_dir );

			$block_command = scm_build_local_ucss_command( $purgecss, array( $entry['file'] ), $safelist, $block_output_dir );
			$block_result  = scm_run_local_optimizer_command( $block_command, $workspace );
			$output_file   = trailingslashit( $workspace ) . $block_output_dir . '/' . $entry['file'];

			if ( ! $block_result['passed'] ) {
				$skipped++;
				$after_bytes += $entry['bytes'];

				if ( '' === $failure_detail ) {
					$failure_detail = scm_get_local_optimizer_error_detail(
						$block_result['output'],
						__( 'PurgeCSS could not parse one stylesheet.', 'ams-cache' )
					);
				}

				continue;
			}
		}

		if ( ! file_exists( $output_file ) ) {
			$skipped++;
			$after_bytes += $entry['bytes'];

			if ( '' === $failure_detail ) {
				$failure_detail = __( 'PurgeCSS output was incomplete.', 'ams-cache' );
			}

			continue;
		}

		$purged_css = scm_rewrite_css_relative_urls( file_get_contents( $output_file ), $entry['href'] );
		$purged_css = scm_minify_css( $purged_css );
		$purged_len = strlen( $purged_css );

		if ( '' === $purged_css || $purged_len >= $entry['bytes'] ) {
			$skipped++;
			$after_bytes += $entry['bytes'];
			continue;
		}

		$style = '<style data-ams-cache-external-ucss="' . esc_attr( $entry['href'] ) . '"';

		if ( '' !== $entry['media'] ) {
			$style .= ' media="' . esc_attr( $entry['media'] ) . '"';
		}

		$style .= '>' . $purged_css . '</style>';

		$replacements[ $entry['index'] ] = $style;
		$after_bytes += $purged_len;
		$optimized++;
	}

	if ( empty( $replacements ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => '' !== $failure_detail ? 'failed' : 'no_change',
				'detail' => '' !== $failure_detail
					? $failure_detail
					: __( 'External UCSS found no smaller stylesheet output.', 'ams-cache' ),
				'metrics' => array(
					'files'          => count( $entries ),
					'optimizedFiles' => 0,
					'skippedFiles'   => $skipped,
					'beforeBytes'    => $before_bytes,
					'afterBytes'     => $before_bytes,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
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

	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'external_ucss',
		array(
			'status' => 'applied',
			'detail' => sprintf(
				/* translators: 1: before size, 2: after size, 3: optimized files, 4: skipped files. */
				__( '%1$s external CSS before, %2$s after; %3$s files optimized, %4$s skipped.', 'ams-cache' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 ),
				number_format_i18n( $optimized ),
				number_format_i18n( $skipped )
			),
			'metrics' => array(
				'files'          => count( $entries ),
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
 * Analyze JavaScript source with conservative PHP heuristics.
 *
 * @param string $source JavaScript source.
 *
 * @return array
 */
function scm_analyze_javascript_source( $source ) {
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
function scm_apply_internal_js_analysis( $html, $settings ) {
	if ( ! preg_match( '#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*></script>#is', $html ) ) {
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status'  => 'no_change',
				'detail'  => __( 'No external scripts found.', 'ams-cache' ),
				'metrics' => array( 'analyzed' => 0, 'deferred' => 0, 'skipped' => 0 ),
			)
		);
		return $html;
	}

	$exclusions   = scm_get_lines_from_textarea( isset( $settings['js_exclusions'] ) ? $settings['js_exclusions'] : '' );
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
				scm_html_tag_is_excluded( $tag, $exclusions ) ||
				scm_html_has_attribute( $tag, 'defer' ) ||
				scm_html_has_attribute( $tag, 'async' ) ||
				false !== stripos( $tag, 'type="module"' ) ||
				false !== stripos( $tag, "type='module'" )
			) {
				$skipped++;
				return $tag;
			}

			$path = scm_local_asset_url_to_path( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );

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

		if ( empty( scm_analyze_javascript_source( $source )['safeToDefer'] ) ) {
			return $tag;
		}

		$deferred++;
		return preg_replace( '/^<script\b/i', '<script defer', $tag, 1 );
		},
		$html
	);

	scm_set_page_optimization_runtime(
		'js_analysis',
		array(
			'status'  => $deferred > 0 ? 'applied' : 'no_change',
			'detail'  => sprintf(
				__( '%1$s local scripts analyzed; %2$s safely deferred; %3$s skipped by exclusions or limits.', 'ams-cache' ),
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
 * Run local PHP script analysis and defer safe local scripts.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_local_js_analysis( $html, $settings ) {
	return scm_apply_internal_js_analysis( $html, $settings );

	if ( ! preg_match_all( '#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*></script>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'no_change',
				'detail' => __( 'No external scripts found.', 'ams-cache' ),
				'metrics' => array(
					'analyzed' => 0,
					'deferred' => 0,
				),
			)
		);
		return $html;
	}

	$bun       = scm_get_executable_command( $settings['bun_path'] );
	$analyzer  = SCM_PLUGIN_DIR . 'inc/assets/js/local-js-analyzer.js';
	$workspace = scm_create_page_optimization_workspace( 'js-analysis' );

	if ( '' === $bun || '' === $workspace || ! file_exists( $analyzer ) ) {
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => __( 'JS analysis engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$exclusions = scm_get_lines_from_textarea( $settings['js_exclusions'] );
	$scripts    = array();
	$indexes    = array();

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];
		$src = html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' );

		if (
			scm_html_tag_is_excluded( $tag, $exclusions ) ||
			scm_html_has_attribute( $tag, 'defer' ) ||
			scm_html_has_attribute( $tag, 'async' ) ||
			false !== stripos( $tag, 'type="module"' ) ||
			false !== stripos( $tag, "type='module'" )
		) {
			continue;
		}

		$path = scm_local_asset_url_to_path( $src );

		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			continue;
		}

		$scripts[] = array(
			'src'     => $src,
			'content' => file_get_contents( $path ),
		);
		$indexes[] = $index;
	}

	if ( empty( $scripts ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'no_change',
				'detail' => __( 'No readable same-site scripts available for analysis.', 'ams-cache' ),
				'metrics' => array(
					'analyzed' => 0,
					'deferred' => 0,
				),
			)
		);
		return $html;
	}

	$input_file  = trailingslashit( $workspace ) . 'scripts.json';
	$output_file = trailingslashit( $workspace ) . 'analysis.json';
	file_put_contents( $input_file, wp_json_encode( array( 'scripts' => $scripts ) ) );

	$command = $bun . ' ' . escapeshellarg( $analyzer ) . ' ' . escapeshellarg( $input_file ) . ' ' . escapeshellarg( $output_file );
	$result  = scm_run_local_optimizer_command( $command );

	if ( ! $result['passed'] || ! file_exists( $output_file ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => '' !== $result['output'] ? strtok( $result['output'], "\r\n" ) : __( 'JS analysis failed.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$analysis = json_decode( file_get_contents( $output_file ), true );

	if ( ! is_array( $analysis ) || empty( $analysis['scripts'] ) || ! is_array( $analysis['scripts'] ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => __( 'JS analysis output was invalid.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$deferred = 0;

	foreach ( $analysis['scripts'] as $analysis_index => $script ) {
		if ( empty( $script['safeToDefer'] ) || ! isset( $indexes[ $analysis_index ] ) ) {
			continue;
		}

		$match_index = $indexes[ $analysis_index ];
		$old_tag     = $matches[ $match_index ][0];
		$new_tag     = preg_replace( '/^<script\b/i', '<script defer', $old_tag, 1 );

		$html = preg_replace( '#' . preg_quote( $old_tag, '#' ) . '#', $new_tag, $html, 1 );
		$deferred++;
	}

	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'js_analysis',
		array(
			'status' => $deferred > 0 ? 'applied' : 'no_change',
			'detail' => sprintf(
				/* translators: 1: analyzed scripts, 2: deferred scripts. */
				__( '%1$s local scripts analyzed; %2$s safely deferred.', 'ams-cache' ),
				number_format_i18n( count( $scripts ) ),
				number_format_i18n( $deferred )
			),
			'metrics' => array(
				'analyzed' => count( $scripts ),
				'deferred' => $deferred,
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
function scm_optimize_html( $html ) {
	if ( ! scm_is_page_optimization_enabled() || empty( $html ) ) {
		return $html;
	}

	$settings = scm_get_page_optimization_settings();
	$html     = apply_filters( 'scm_before_page_optimization', $html, $settings );
	scm_reset_page_optimization_runtime();

	if ( 'yes' === $settings['remove_comments'] ) {
		$html = scm_remove_html_comments( $html );
	}

	if ( 'yes' === $settings['minify_inline_css'] ) {
		$html = scm_minify_inline_css_blocks( $html );
	}

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = scm_optimize_media_tags( $html, $settings );
	}

	if ( 'yes' === $settings['preconnect_fonts'] ) {
		$html = scm_add_font_preconnects( $html );
	}

	if ( 'yes' === $settings['external_ucss'] ) {
		$html = scm_apply_external_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['local_ucss'] ) {
		$html = scm_apply_local_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['js_analysis'] ) {
		$html = scm_apply_local_js_analysis( $html, $settings );
	}

	if ( 'yes' === $settings['defer_js'] ) {
		$html = scm_defer_scripts( $html, $settings );
	}

	if ( 'yes' === $settings['minify_html'] ) {
		$html = scm_minify_html( $html );
	}

	return apply_filters( 'scm_after_page_optimization', $html, $settings );
}

/**
 * Check shell_exec availability.
 *
 * @return bool
 */
function scm_is_shell_exec_available() {
	if ( ! function_exists( 'shell_exec' ) ) {
		return false;
	}

	$disabled = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ) );

	return ! in_array( 'shell_exec', $disabled, true );
}

/**
 * Build one escaped executable command token.
 *
 * @param string $path Executable path or command.
 *
 * @return string
 */
function scm_get_executable_command( $path ) {
	$path = trim( (string) $path );

	if ( '' === $path ) {
		return '';
	}

	if ( preg_match( '/[\/\\\\]/', $path ) ) {
		if ( ! preg_match( '/^[A-Za-z0-9_ .:\\\\\/-]+$/', $path ) ) {
			return '';
		}

		return escapeshellarg( $path );
	}

	if ( preg_match( '/^[A-Za-z0-9_.-]+$/', $path ) ) {
		return escapeshellcmd( $path );
	}

	return '';
}

/**
 * Run local optimizer command and capture exit code.
 *
 * @param string $command Shell command.
 * @param string $cwd     Optional working directory.
 *
 * @return array
 */
function scm_run_local_optimizer_command( $command, $cwd = '' ) {
	if ( ! scm_is_shell_exec_available() ) {
		return array(
			'passed' => false,
			'output' => __( 'shell_exec is disabled.', 'ams-cache' ),
		);
	}

	if ( '' !== $cwd ) {
		$command = '\\' === DIRECTORY_SEPARATOR
			? 'cd /d ' . escapeshellarg( $cwd ) . ' && ' . $command
			: 'cd ' . escapeshellarg( $cwd ) . ' && ' . $command;
	}

	if ( '\\' === DIRECTORY_SEPARATOR ) {
		$output = @shell_exec( $command . ' 2>&1 & echo SCM_EXIT:%ERRORLEVEL%' );
	} else {
		$path_prefix = 'PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin:$PATH; ';
		$output      = @shell_exec( $path_prefix . $command . ' 2>&1; printf "\nSCM_EXIT:%s" "$?"' );
	}

	$exit = 1;

	if ( preg_match( '/SCM_EXIT:(\d+)/', (string) $output, $match ) ) {
		$exit   = (int) $match[1];
		$output = preg_replace( '/\s*SCM_EXIT:\d+\s*$/', '', (string) $output );
	}

	return array(
		'passed' => 0 === $exit,
		'output' => trim( (string) $output ),
	);
}

/**
 * Check if a configured executable responds to --version.
 *
 * @param string $path Executable path or command.
 *
 * @return array
 */
function scm_check_executable_version( $path ) {
	$path = trim( (string) $path );

	if ( '' === $path ) {
		return array(
			'passed' => false,
			'detail' => __( 'Path is empty.', 'ams-cache' ),
		);
	}

	$command = scm_get_executable_command( $path );

	if ( '' === $command ) {
		return array(
			'passed' => false,
			'detail' => __( 'Path contains unsupported characters.', 'ams-cache' ),
		);
	}

	if ( ! scm_is_shell_exec_available() ) {
		return array(
			'passed' => false,
			'detail' => __( 'shell_exec is disabled.', 'ams-cache' ),
		);
	}

	$result = scm_run_local_optimizer_command( $command . ' --version' );
	$output = trim( (string) $result['output'] );

	if ( '' === $output || ! $result['passed'] || false !== stripos( $output, 'not found' ) || false !== stripos( $output, 'not recognized' ) ) {
		return array(
			'passed' => false,
			'detail' => '' === $output ? sprintf(
					/* translators: %s is executable path. */
					__( 'No response from %s --version.', 'ams-cache' ),
					$path
				) : strtok( $output, "\r\n" ),
		);
	}

	return array(
		'passed' => true,
		'detail' => strtok( $output, "\r\n" ),
	);
}

/**
 * Get page optimization requirement checks.
 *
 * @return array
 */
function scm_get_page_optimization_requirements() {
	$work_dir       = scm_get_page_optimization_work_dir();
	$work_dir_ready = is_dir( $work_dir ) ? is_writable( $work_dir ) : wp_mkdir_p( $work_dir );

	return array(
		'cache_status' => array(
			'label'  => __( 'Page cache enabled', 'ams-cache' ),
			'passed' => 'enable' === get_option( 'scm_option_caching_status', 'disable' ),
			'detail' => get_option( 'scm_option_caching_status', 'disable' ),
		),
		'permalink'    => array(
			'label'  => __( 'Pretty permalinks enabled', 'ams-cache' ),
			'passed' => '' !== get_option( 'permalink_structure', '' ),
			'detail' => '' !== get_option( 'permalink_structure', '' ) ? get_option( 'permalink_structure' ) : __( 'Plain permalinks', 'ams-cache' ),
		),
		'html_parser'  => array(
			'label'  => __( 'HTML rewrite engine available', 'ams-cache' ),
			'passed' => true,
			'detail' => __( 'Built-in PHP regex processor', 'ams-cache' ),
		),
		'server_gzip'  => array(
			'label'  => __( 'Text compression', 'ams-cache' ),
			'passed' => extension_loaded( 'zlib' ),
			'detail' => extension_loaded( 'zlib' ) ? __( 'PHP zlib available; enable gzip/Brotli on your web server for best results.', 'ams-cache' ) : __( 'Enable gzip/Brotli on your web server.', 'ams-cache' ),
		),
		'php_css'      => array(
			'label'  => __( 'Built-in PHP CSS optimizer', 'ams-cache' ),
			'passed' => true,
			'detail' => __( 'Conservative selector matching; unsupported and dynamic rules are preserved.', 'ams-cache' ),
		),
		'php_js'       => array(
			'label'  => __( 'Built-in PHP JavaScript analyzer', 'ams-cache' ),
			'passed' => true,
			'detail' => __( 'Bounded same-site analysis; unsafe scripts remain blocking.', 'ams-cache' ),
		),
		'legacy_cli'   => array(
			'label'  => __( 'Legacy CLI optimizer settings', 'ams-cache' ),
			'passed' => true,
			'detail' => __( 'Optional Bun and PurgeCSS fields remain for compatibility; runtime optimization does not require them.', 'ams-cache' ),
		),
		'work_dir'     => array(
			'label'  => __( 'Local optimizer workspace', 'ams-cache' ),
			'passed' => $work_dir_ready,
			'detail' => $work_dir_ready ? __( 'Workspace is writable.', 'ams-cache' ) : __( 'Workspace is not writable.', 'ams-cache' ),
		),
	);
}

/**
 * Get the maximum number of cached entries.
 *
 * A value of 0 disables pruning.
 *
 * @return int
 */
function scm_get_cache_max_entries() {
	$limit = (int) get_option( 'scm_option_cache_max_entries', 0 );

	return max( 0, $limit );
}

/**
 * Prune oldest known cache entries when the cache index grows too large.
 *
 * This only deletes entries recorded in the statistics index, so shared Redis or
 * Memcached stores are not flushed globally.
 *
 * @param \Shieldon\SimpleCache\Cache|null $driver Active cache driver.
 *
 * @return int Removed entry count.
 */
function scm_enforce_cache_entry_limit( $driver = null ) {
	$limit = scm_get_cache_max_entries();

	if ( $limit <= 0 ) {
		return 0;
	}

	$stats_root = scm_get_upload_dir() . '/stats';

	if ( ! is_dir( $stats_root ) ) {
		return 0;
	}

	$entries = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$filename = $file->getFilename();
		$key      = strstr( $filename, '.', true );

		if ( false === $key || '' === $key ) {
			continue;
		}

		$stats = scm_read_stats_file( $file->getPathname() );

		$entries[] = array(
			'key'  => $key,
			'path' => $file->getPathname(),
			'time' => $file->getMTime(),
			'uri'  => $stats['uri'],
		);
	}

	if ( count( $entries ) <= $limit ) {
		return 0;
	}

	usort(
		$entries,
		function ( $a, $b ) {
			return $a['time'] - $b['time'];
		}
	);

	if ( null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	$remove_count = count( $entries ) - $limit;
	$removed      = 0;

	for ( $i = 0; $i < $remove_count; $i++ ) {
		if ( $driver ) {
			$driver->delete( $entries[ $i ]['key'] );
		}

		if ( ! empty( $entries[ $i ]['uri'] ) ) {
			scm_delete_nginx_static_cache( $entries[ $i ]['uri'] );
		}

		if ( file_exists( $entries[ $i ]['path'] ) ) {
			unlink( $entries[ $i ]['path'] );
		}

		$removed++;
	}

	update_option( 'scm_last_cache_prune_count', $removed );

	return $removed;
}

/**
 * Get advanced driver settings from WordPress options.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function scm_get_driver_advanced_settings( $type ) {
	if ( 'file' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_file', array() );
	}

	if ( 'memcache' === $type || 'memcached' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_memcached', array() );
	}

	if ( 'redis' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_redis', array() );
	}

	if ( 'mongo' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_mongodb', array() );
	}

	return array();
}

/**
 * Get advanced driver connection type.
 *
 * @param string $type Driver type.
 *
 * @return string
 */
function scm_get_driver_connection_type( $type ) {
	if ( 'memcache' === $type || 'memcached' === $type ) {
		return get_option( 'scm_option_advanced_driver_memcached_connection_type', 'tcp' );
	}

	if ( 'redis' === $type ) {
		return get_option( 'scm_option_advanced_driver_redis_connection_type', 'tcp' );
	}

	if ( 'mongo' === $type ) {
		return get_option( 'scm_option_advanced_driver_mongodb_connection_type', 'tcp' );
	}

	return 'tcp';
}

/**
 * Normalize driver settings before passing them to Simple Cache.
 *
 * @param array  $setting         Driver settings.
 * @param string $connection_type tcp|socket.
 *
 * @return array
 */
function scm_normalize_driver_settings( $setting, $connection_type = 'tcp' ) {
	foreach ( $setting as $k => $v ) {
		if ( is_string( $v ) ) {
			$v = trim( $v );
		}

		if ( '' === $v ) {
			unset( $setting[ $k ] );
			continue;
		}

		if ( is_numeric( $v ) ) {
			$v = (int) $v;
		}

		if ( 'compress' === $k ) {
			$v = in_array( $v, array( true, 1, '1', 'yes', 'on' ), true );
		}

		if ( 'compress_threshold' === $k ) {
			$v = max( 0, (int) $v );
		}

		if ( 'compress_level' === $k ) {
			$v = max( 1, min( 9, (int) $v ) );
		}

		$setting[ $k ] = $v;
	}

	if ( 'socket' !== $connection_type && isset( $setting['unix_socket'] ) ) {
		unset( $setting['unix_socket'] );
	}

	return $setting;
}

/**
 * Get the cache driver instance.
 *
 * @param string $type The type of the driver.
 *
 * @return \Shieldon\SimpleCache\Cache
 */
function scm_driver_factory( $type ) {

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

		case 'sqlite':
			$setting = array(
				'storage' => scm_get_upload_dir() . '/sqlite_driver',
			);
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

		case 'file':
		default:
			$type    = 'file';
			$setting = array(
				'storage' => scm_get_upload_dir() . '/file_driver',
			);

			$advanced_settings = scm_get_driver_advanced_settings( $type );
			break;
	}

	if ( ! empty( $advanced_settings ) ) {
		$setting = array_merge( $setting, scm_normalize_driver_settings( $advanced_settings, $advanced_connection_type ) );
	}

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );

	} catch ( \Exception $e ) {

		if ( in_array( $type, array( 'file', 'sqlite' ), true ) && ! file_exists( $setting['storage'] ) ) {
			wp_mkdir_p( $setting['storage'] );

			// Let's try again.
			$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
		} else {

			error_log( sprintf( '[AMS Cache] Driver %s is not supported, fallback to use File driver.', $type ) );

			$driver = new \Shieldon\SimpleCache\Cache(
				'file',
				array(
					'storage' => scm_get_upload_dir() . '/file_driver',
				)
			);
		}
	}

	return $driver;
}

/**
 * Get a SVG icon.
 *
 * @param string $type The icon type.
 *
 * Font Awesome Free 5.15.1
 *
 * @return string
 */
function scm_get_svg_icon( $type ) {
	switch ( $type ) {
		case 'status':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 520 562'><path d='M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zM262.655 90c-54.497 0-89.255 22.957-116.549 63.758-3.536 5.286-2.353 12.415 2.715 16.258l34.699 26.31c5.205 3.947 12.621 3.008 16.665-2.122 17.864-22.658 30.113-35.797 57.303-35.797 20.429 0 45.698 13.148 45.698 32.958 0 14.976-12.363 22.667-32.534 33.976C247.128 238.528 216 254.941 216 296v4c0 6.627 5.373 12 12 12h56c6.627 0 12-5.373 12-12v-1.333c0-28.462 83.186-29.647 83.186-106.667 0-58.002-60.165-102-116.531-102zM256 338c-25.365 0-46 20.635-46 46 0 25.364 20.635 46 46 46s46-20.636 46-46c0-25.365-20.635-46-46-46z'/></svg>";
			break;

		case 'memory':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 582 552'><path d='M640 130.94V96c0-17.67-14.33-32-32-32H32C14.33 64 0 78.33 0 96v34.94c18.6 6.61 32 24.19 32 45.06s-13.4 38.45-32 45.06V320h640v-98.94c-18.6-6.61-32-24.19-32-45.06s13.4-38.45 32-45.06zM224 256h-64V128h64v128zm128 0h-64V128h64v128zm128 0h-64V128h64v128zM0 448h64v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h64v-96H0v96z'/></svg>";
			break;

		case 'database':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 482 572'><path d='M448 73.143v45.714C448 159.143 347.667 192 224 192S0 159.143 0 118.857V73.143C0 32.857 100.333 0 224 0s224 32.857 224 73.143zM448 176v102.857C448 319.143 347.667 352 224 352S0 319.143 0 278.857V176c48.125 33.143 136.208 48.572 224 48.572S399.874 209.143 448 176zm0 160v102.857C448 479.143 347.667 512 224 512S0 479.143 0 438.857V336c48.125 33.143 136.208 48.572 224 48.572S399.874 369.143 448 336z'/></svg>";
			break;

		case 'speed':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M296 160H180.6l42.6-129.8C227.2 15 215.7 0 200 0H56C44 0 33.8 8.9 32.2 20.8l-32 240C-1.7 275.2 9.5 288 24 288h118.7L96.6 482.5c-3.6 15.2 8 29.5 23.3 29.5 8.4 0 16.4-4.4 20.8-12l176-304c9.3-15.9-2.2-36-20.7-36z'/></svg>";
			break;
	}

	return $svg;
}

/**
 * Get default configuation.
 *
 * This function is also used in expert mode.
 *
 * @return array
 */
function scm_get_default_config() {

	return array(
		'cache_driver'             => 'file',
		'cache_key_prefix'         => '',
		'cache_max_entries'        => 0,
		'nginx_direct_cache'       => false,
		'html_debug_comment'       => true,
		'driver_advanced_settings' => array(),
		'site_url'                 => '',
		'preload'                  => array(
			'enable'               => false,
			'limit'                => 50,
			'crawl_homepage_links' => true,
		),
		'woocommerce'              => array(
			'enable' => false,
		),

		'exclusion'                => array(
			'enable'             => false,
			'excluded_list'      => array(),
			'excluded_get_vars'  => array(),
			'excluded_post_vars' => array(),
			'excluded_cookie_vars' => array(),
		),
	);
}

/**
 * The variable stack for JavaScript snippet.
 *
 * This function is also used in expert mode.
 *
 * @param string      $key      The key of the field.
 * @param string|int  $value    The value of the field.
 * @param string      $poistion The position.
 *
 * @return void
 */
function scm_variable_stack( $key, $value = '', $poistion = 'before' ) {
	static $vars = array();

	if ( is_null( $key ) ) {
		$output = $vars;
		$vars   = array();

		return json_encode( $output );
	}

	$vars[ $poistion ][ $key ] = $value;
}

/**
 * Create JavaScript snippet used for performance report.
 *
 * This function is also used in expert mode.
 *
 * @return void
 */
function scm_javascript() {
	$script = '
		<script id="ams-cache-plugin">
			var cache_master = \'' . scm_variable_stack( null ) . '\';
			var scm_report   = JSON.parse(cache_master);

			var scm_text_cache_status = "";
			var scm_text_memory_usage = "";
			var scm_text_sql_queries  = "";
			var scm_text_page_generation_time = "";

			if ("before" in scm_report) {
				scm_text_cache_status = "ទេ";
				scm_text_memory_usage = scm_report["before"]["memory_usage"];
				scm_text_sql_queries = scm_report["before"]["sql_queries"];
				scm_text_page_generation_time = scm_report["before"]["page_generation_time"];
			}
			if ("after" in scm_report) {
				scm_text_cache_status = "មាន";
				scm_text_memory_usage = scm_report["after"]["memory_usage"];
				scm_text_sql_queries = scm_report["after"]["sql_queries"];
				scm_text_page_generation_time = scm_report["after"]["page_generation_time"];
			}
			var scm_field_cache_status = document.querySelector(".scm-field-cache-status");
			if (null !== scm_field_cache_status) { scm_field_cache_status.textContent = scm_text_cache_status; }
			var scm_field_memory_usage = document.querySelector(".scm-field-memory-usage");
			if (null !== scm_field_memory_usage) { scm_field_memory_usage.textContent = scm_text_memory_usage; }
			var scm_field_sql_queries = document.querySelector(".scm-field-sql-queries");
			if (null !== scm_field_sql_queries) { scm_field_sql_queries.textContent = scm_text_sql_queries; }
			var scm_field_page_generation_time = document.querySelector(".scm-field-page-generation-time");
			if (null !== scm_field_page_generation_time) { scm_field_page_generation_time.textContent = scm_text_page_generation_time; }
			var cache_master_benchmark_report = document.querySelector(".ams-cache-benchmark-report");
			if (null !== cache_master_benchmark_report) { cache_master_benchmark_report.setAttribute("style", ""); }
			var cache_master_plugin_widget_wrapper = document.querySelector(".ams-cache-plugin-widget-wrapper");
			if (null !== cache_master_plugin_widget_wrapper) { cache_master_plugin_widget_wrapper.setAttribute("style", ""); }
		</script>
	';

	return preg_replace( '/\s+/', ' ', $script );
}
