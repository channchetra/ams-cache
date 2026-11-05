<?php
/**
 * KH Speed Engine — cache/cache-driver-settings.php
 *
 * Driver configuration defaults and option mapping.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Return the advanced default settings for a driver.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function khse_get_driver_advanced_defaults( $type ) {
	switch ( $type ) {
		case 'file':
			return array( 'compress' => 'no', 'compress_threshold' => 4096, 'compress_level' => 1 );

		case 'redis':
			return array(
				'host'               => '127.0.0.1',
				'port'               => 6379,
				'user'               => '',
				'pass'               => '',
				'database'           => 0,
				'unix_socket'        => '',
				'compress'           => 'yes',
				'compress_threshold' => 1024,
				'compress_level'     => 6,
			);

		case 'memcache':
		case 'memcached':
			return array( 'host' => '127.0.0.1', 'port' => 11211, 'unix_socket' => '' );

		case 'mongo':
		case 'mongodb':
			return array(
				'host'        => '127.0.0.1',
				'port'        => 27017,
				'user'        => '',
				'pass'        => '',
				'dbname'      => 'test',
				'collection'  => 'cache_data',
				'unix_socket' => '',
			);
	}

	return array();
}

/**
 * Get advanced driver settings from WordPress options.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function khse_get_driver_advanced_settings( $type ) {
	if ( 'file' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_file', array() );
	}

	if ( 'memcache' === $type || 'memcached' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_memcached', array() );
	}

	if ( 'redis' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_redis', array() );
	}

	if ( 'mongo' === $type ) {
		return (array) get_option( 'khse_option_advanced_driver_mongodb', array() );
	}

	return array();
}

/**
 * Get advanced driver connection type.
 *
 * @param string $type Driver type.
 *
 * @return string
 */
function khse_get_driver_connection_type( $type ) {
	if ( 'memcache' === $type || 'memcached' === $type ) {
		return get_option( 'khse_option_advanced_driver_memcached_connection_type', 'tcp' );
	}

	if ( 'redis' === $type ) {
		return get_option( 'khse_option_advanced_driver_redis_connection_type', 'tcp' );
	}

	if ( 'mongo' === $type ) {
		return get_option( 'khse_option_advanced_driver_mongodb_connection_type', 'tcp' );
	}

	return 'tcp';
}

/**
 * Normalize driver settings before passing them to Simple Cache.
 *
 * @param array  $setting         Driver settings.
 * @param string $connection_type tcp|socket.
 *
 * @return array
 */
function khse_normalize_driver_settings( $setting, $connection_type = 'tcp' ) {
	foreach ( $setting as $k => $v ) {
		if ( is_string( $v ) ) {
			$v = trim( $v );
		}

		if ( '' === $v ) {
			unset( $setting[ $k ] );
			continue;
		}

		if ( is_numeric( $v ) ) {
			$v = (int) $v;
		}

		if ( 'compress' === $k ) {
			$v = in_array( $v, array( true, 1, '1', 'yes', 'on' ), true );
		}

		if ( 'compress_threshold' === $k ) {
			$v = max( 0, (int) $v );
		}

		if ( 'compress_level' === $k ) {
			$v = max( 1, min( 9, (int) $v ) );
		}

		$setting[ $k ] = $v;
	}

	if ( 'socket' !== $connection_type && isset( $setting['unix_socket'] ) ) {
		unset( $setting['unix_socket'] );
	}

	return $setting;
}

/**
 * Get the base connection settings for a cache driver.
 *
 * Pure defaults only; callers apply their own fallbacks and advanced settings.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function khse_get_driver_setting( $type ) {
	switch ( $type ) {
		case 'mysql':
			return array(
				'host'    => DB_HOST,
				'dbname'  => DB_NAME,
				'user'    => DB_USER,
				'pass'    => DB_PASSWORD,
				'charset' => DB_CHARSET,
			);

		case 'sqlite':
			return array(
				'storage' => khse_get_upload_dir() . '/sqlite_driver',
			);

		case 'redis':
			return array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'database' => 0,
			);

		case 'mongo':
			return array(
				'host' => '127.0.0.1',
				'port' => 27017,
			);

		case 'memcache':
		case 'memcached':
			return array(
				'host' => '127.0.0.1',
				'port' => 11211,
			);

		case 'apc':
		case 'apcu':
		case 'wincache':
			return array();

		case 'file':
		default:
			return array(
				'storage' => khse_get_upload_dir() . '/file_driver',
			);
	}
}

/**
 * Get the resolved driver settings and connection type.
 *
 * @param string      $type              Driver type.
 * @param array|null  $advanced_settings Optional advanced settings; defaults to the stored option.
 * @param string|null $connection_type   Optional connection type; defaults to the stored option.
 *
 * @return array
 */
function khse_get_driver_settings( $type, $advanced_settings = null, $connection_type = null ) {
	$setting = khse_get_driver_setting( $type );

	if ( null === $advanced_settings ) {
		$advanced_settings = khse_get_driver_advanced_settings( $type );
	}

	if ( null === $connection_type ) {
		$connection_type = khse_get_driver_connection_type( $type );
	}

	if ( ! empty( $advanced_settings ) ) {
		$setting = array_merge( $setting, khse_normalize_driver_settings( $advanced_settings, $connection_type ) );
	}

	return array(
		'setting'         => $setting,
		'connection_type' => $connection_type,
	);
}

/**
 * Persist advanced driver connection settings.
 *
 * @param array $advanced Driver advanced payload.
 *
 * @return void
 */
function khse_save_driver_advanced_settings( $advanced ) {
	$file     = isset( $advanced['file'] ) && is_array( $advanced['file'] ) ? $advanced['file'] : array();
	$redis    = isset( $advanced['redis'] ) && is_array( $advanced['redis'] ) ? $advanced['redis'] : array();
	$memcache = isset( $advanced['memcached'] ) && is_array( $advanced['memcached'] ) ? $advanced['memcached'] : ( isset( $advanced['memcache'] ) && is_array( $advanced['memcache'] ) ? $advanced['memcache'] : array() );
	$mongodb  = isset( $advanced['mongodb'] ) && is_array( $advanced['mongodb'] ) ? $advanced['mongodb'] : ( isset( $advanced['mongo'] ) && is_array( $advanced['mongo'] ) ? $advanced['mongo'] : array() );
	$conn     = isset( $advanced['connection'] ) && is_array( $advanced['connection'] ) ? $advanced['connection'] : array();

	$file     = array_merge( khse_get_driver_advanced_defaults( 'file' ), $file );
	$redis    = array_merge( khse_get_driver_advanced_defaults( 'redis' ), $redis );
	$memcache = array_merge( khse_get_driver_advanced_defaults( 'memcached' ), $memcache );
	$mongodb  = array_merge( khse_get_driver_advanced_defaults( 'mongodb' ), $mongodb );

	update_option(
		'khse_option_advanced_driver_file',
		array(
			'compress'           => khse_settings_yes_no( $file['compress'] ),
			'compress_threshold' => max( 0, absint( $file['compress_threshold'] ) ),
			'compress_level'     => max( 1, min( 9, absint( $file['compress_level'] ) ) ),
		)
	);
	update_option(
		'khse_option_advanced_driver_redis',
		array(
			'host'               => sanitize_text_field( $redis['host'] ),
			'port'               => max( 1, absint( $redis['port'] ) ),
			'user'               => sanitize_text_field( $redis['user'] ),
			'pass'               => sanitize_text_field( $redis['pass'] ),
			'database'           => max( 0, min( 15, absint( $redis['database'] ) ) ),
			'unix_socket'        => sanitize_text_field( $redis['unix_socket'] ),
			'compress'           => khse_settings_yes_no( $redis['compress'], 'yes' ),
			'compress_threshold' => max( 0, absint( $redis['compress_threshold'] ) ),
			'compress_level'     => max( 1, min( 9, absint( $redis['compress_level'] ) ) ),
		)
	);
	update_option(
		'khse_option_advanced_driver_memcached',
		array(
			'host'        => sanitize_text_field( $memcache['host'] ),
			'port'        => max( 1, absint( $memcache['port'] ) ),
			'unix_socket' => sanitize_text_field( $memcache['unix_socket'] ),
		)
	);
	update_option(
		'khse_option_advanced_driver_mongodb',
		array(
			'host'        => sanitize_text_field( $mongodb['host'] ),
			'port'        => max( 1, absint( $mongodb['port'] ) ),
			'user'        => sanitize_text_field( $mongodb['user'] ),
			'pass'        => sanitize_text_field( $mongodb['pass'] ),
			'dbname'      => sanitize_text_field( $mongodb['dbname'] ),
			'collection'  => sanitize_text_field( $mongodb['collection'] ),
			'unix_socket' => sanitize_text_field( $mongodb['unix_socket'] ),
		)
	);
	update_option( 'khse_option_advanced_driver_redis_connection_type', khse_settings_connection_type( isset( $conn['redis'] ) ? $conn['redis'] : 'tcp' ) );
	update_option( 'khse_option_advanced_driver_memcached_connection_type', khse_settings_connection_type( isset( $conn['memcached'] ) ? $conn['memcached'] : ( isset( $conn['memcache'] ) ? $conn['memcache'] : 'tcp' ) ) );
	update_option( 'khse_option_advanced_driver_mongodb_connection_type', khse_settings_connection_type( isset( $conn['mongo'] ) ? $conn['mongo'] : ( isset( $conn['mongodb'] ) ? $conn['mongodb'] : 'tcp' ) ) );
	
}
