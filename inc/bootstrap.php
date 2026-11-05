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
require_once __DIR__ . '/brand-migration.php';
require_once __DIR__ . '/cache-driver.php';
require_once __DIR__ . '/cache-view.php';
require_once __DIR__ . '/cache-nginx.php';
require_once __DIR__ . '/cache-url-policy.php';
require_once __DIR__ . '/cache-purger.php';
require_once __DIR__ . '/cache-nginx-config.php';
require_once __DIR__ . '/page-optimizer.php';
require_once __DIR__ . '/cache-preload.php';

// Migrate legacy brand data before the cache runtime creates its storage tree.
if ( function_exists( 'khse_maybe_migrate_legacy_brand' ) && function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
	khse_maybe_migrate_legacy_brand();
}
