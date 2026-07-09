<?php
/**
 * AMS Cache helper functions — bootstrap.
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

require_once __DIR__ . '/cache-core.php';
require_once __DIR__ . '/cache-nginx.php';
require_once __DIR__ . '/cache-purger.php';
require_once __DIR__ . '/cache-nginx-config.php';
require_once __DIR__ . '/page-optimizer.php';
require_once __DIR__ . '/cache-preload.php';
require_once __DIR__ . '/cache-utils.php';

function scm_register_wordpress_hooks() {
	static $registered = false;

	if ( $registered || ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
		return;
	}

	// Image optimization hooks removed — feature is discontinued.

	$registered = true;
}

scm_register_wordpress_hooks();
