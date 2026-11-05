<?php
/**
 * KH Speed Engine — cache-core.php
 *
 * Shared cache foundation. The implementation is split into settings,
 * storage, and request-policy modules; this file keeps the historical
 * include path stable for the boot manifest and uninstall routine.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

require_once __DIR__ . '/cache-identity.php';
require_once __DIR__ . '/cache-view.php';
require_once __DIR__ . '/cache-settings.php';
require_once __DIR__ . '/cache-storage.php';
require_once __DIR__ . '/cache-policy.php';
