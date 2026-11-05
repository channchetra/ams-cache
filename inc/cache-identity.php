<?php
/**
 * KH Speed Engine — cache identity.
 *
 * Site and directory identity used to namespace cache storage.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Return the site directory hash.
 *
 * Pure: returns the persisted hash or a deterministic fallback without
 * writing. Use khse_ensure_dir_hash() when the value must be persisted.
 *
 * @return string
 */
function khse_get_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( ! empty( $hash ) ) {
		return $hash;
	}

	return khse_generate_dir_hash();
}

/**
 * Generate a deterministic directory hash for this site.
 *
 * @return string
 */
function khse_generate_dir_hash() {
	$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
	$site = defined( 'WP_HOME' ) ? WP_HOME : ( defined( 'ABSPATH' ) ? ABSPATH : __DIR__ );

	if ( function_exists( 'wp_hash' ) ) {
		return substr( wp_hash( $site . '|' . $salt ), 0, 8 );
	}

	return substr( hash( 'sha256', $site . '|' . $salt ), 0, 8 );
}

/**
 * Persist and return the site directory hash.
 *
 * @return string
 */
function khse_ensure_dir_hash() {
	$hash = get_option( 'khse_dir_hash' );

	if ( empty( $hash ) ) {
		$hash = khse_generate_dir_hash();
		update_option( 'khse_dir_hash', $hash );
	}

	return $hash;
}

/**
 * Set channel Id.
 *
 * @return void
 */
function khse_set_blog_id() {
	update_option( 'khse_blog_id', get_current_blog_id() );
}

/**
 * Get channel Id.
 *
 * @return string
 */
function khse_get_blog_id() {
	return get_option( 'khse_blog_id', 1 );
}
