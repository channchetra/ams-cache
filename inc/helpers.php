<?php
/**
 * KH Speed Engine helper functions — bootstrap.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

require_once __DIR__ . '/cache-core.php';
require_once __DIR__ . '/cache-driver.php';
require_once __DIR__ . '/cache-view.php';
require_once __DIR__ . '/cache-nginx.php';
require_once __DIR__ . '/cache-url-policy.php';
require_once __DIR__ . '/cache-purger.php';
require_once __DIR__ . '/cache-nginx-config.php';
require_once __DIR__ . '/page-optimizer.php';
require_once __DIR__ . '/cache-preload.php';

function khse_register_wordpress_hooks() {
	static $registered = false;

	if ( $registered || ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
		return;
	}

	// Image optimization hooks removed — feature is discontinued.

	$registered = true;
}

khse_register_wordpress_hooks();
