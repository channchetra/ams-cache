<?php
/**
 * KH Speed Engine — optimizer/css.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
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
