<?php
/**
 * KH Speed Engine — admin/driver-tools.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( "KHSE_INC" ) ) {
	die;
}

/**
 * Get dashboard-ready driver settings.
 *
 * @return array
 */
function khse_dashboard_get_driver_advanced_settings_data() {
	$file = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_file', array() ),
		array(
			'compress'           => 'no',
			'compress_threshold' => 4096,
			'compress_level'     => 1,
		)
	);

	$redis = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_redis', array() ),
		array(
			'host'               => '127.0.0.1',
			'port'               => 6379,
			'user'               => '',
			'pass'               => '',
			'database'           => 0,
			'unix_socket'        => '',
			'compress'           => 'yes',
			'compress_threshold' => 1024,
			'compress_level'     => 6,
		)
	);

	$memcached = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_memcached', array() ),
		array(
			'host'        => '127.0.0.1',
			'port'        => 11211,
			'unix_socket' => '',
		)
	);

	$mongodb = wp_parse_args(
		(array) get_option( 'khse_option_advanced_driver_mongodb', array() ),
		array(
			'host'        => '127.0.0.1',
			'port'        => 27017,
			'user'        => '',
			'pass'        => '',
			'dbname'      => 'test',
			'collection'  => 'cache_data',
			'unix_socket' => '',
		)
	);

	return array(
		'file'       => $file,
		'redis'      => $redis,
		'memcache'   => $memcached,
		'memcached'  => $memcached,
		'mongodb'    => $mongodb,
		'mongo'      => $mongodb,
		'connection' => array(
			'redis'     => get_option( 'khse_option_advanced_driver_redis_connection_type', 'tcp' ),
			'memcache'  => get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'memcached' => get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' ),
			'mongo'     => get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' ),
			'mongodb'   => get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' ),
		),
	);
}

/**
 * Ensure the storage directory exists for a cache driver.
 *
 * @param string $type Driver type.
 *
 * @return void
 */
function khse_ensure_driver_storage( $type ) {
	if ( ! in_array( $type, array( 'file', 'sqlite' ), true ) ) {
		return;
	}

	$resolved = khse_get_driver_settings( $type );
	$storage  = isset( $resolved['setting']['storage'] ) ? $resolved['setting']['storage'] : '';

	if ( '' !== $storage && ! is_dir( $storage ) ) {
		wp_mkdir_p( $storage );
	}
}

/**
 * Test if specific data driver is available or not.
 *
 * @param string $type Data driver.
 *
 * @return bool
 */
function khse_test_driver( $type = '' ) {

	if ( '' === (string) $type ) {
		return false;
	}

	$resolved = khse_get_driver_settings( $type );
	$setting  = $resolved['setting'];

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
		$driver->rebuild();
		$driver->set( 'foo', 'bar', 300 );

		if ( 'bar' === $driver->get( 'foo' ) ) {
			$driver->delete( 'foo' );
			return true;
		}
	} catch ( \Throwable $e ) {
		error_log(
			sprintf(
				'[KH Speed Engine] Driver %s connection test failed: %s',
				'' !== $type ? $type : 'unknown',
				$e->getMessage()
			)
		);
	}

	return false;
}

/**
 * Build a dashboard-safe driver connection test response.
 *
 * @param string $type Driver key.
 *
 * @return array
 */
function khse_dashboard_test_driver_connection( $type ) {
	$type = sanitize_key( (string) $type );

	if ( '' === $type ) {
		$type = get_option( 'khse_option_driver', 'file' );
	}

	$labels = khse_get_driver_catalog();

	if ( ! isset( $labels[ $type ] ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => __( 'Unknown cache driver.', 'kh-speed-engine' ),
		);
	}

	if ( ! khse_is_driver_available( $type ) ) {
		return array(
			'passed'  => false,
			'driver'  => $type,
			'message' => sprintf(
				/* translators: %s: cache driver label. */
				__( '%s PHP extension is not available.', 'kh-speed-engine' ),
				$labels[ $type ]
			),
		);
	}

	khse_ensure_driver_storage( $type );

	$passed = khse_test_driver( $type );

	return array(
		'passed'  => $passed,
		'driver'  => $type,
		'message' => $passed
			? sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test passed.', 'kh-speed-engine' ),
				$labels[ $type ]
			)
			: sprintf(
				/* translators: %s: cache driver label. */
				__( '%s connection test failed. Save settings, then verify host, port, database, and credentials.', 'kh-speed-engine' ),
				$labels[ $type ]
			),
	);
}
