<?php
/**
 * KH Speed Engine — optimizer/dom.php
 *
 * Shared HTML attribute helpers used by the HTML, CSS, and JS optimizers.
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
