<?php
/**
 * KH Speed Engine — optimizer/html.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
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
