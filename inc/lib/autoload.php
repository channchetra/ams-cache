<?php
/**
 * Local autoloader for the vendored PSR-16 cache library.
 *
 * @package AMS Cache
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefixes = array(
			'Shieldon\\SimpleCache\\' => __DIR__ . '/simple-cache/',
			'Psr\\SimpleCache\\'      => __DIR__ . '/psr-simple-cache/',
		);

		foreach ( $prefixes as $prefix => $dir ) {
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				continue;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = $dir . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require $file;
			}

			return;
		}
	},
	true,
	true
);
