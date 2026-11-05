<?php
/**
 * KH Speed Engine option schema — canonical lifecycle registry.
 *
 * @package KH Speed Engine
 * @license GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

require_once __DIR__ . '/cache/cache-core.php';

/**
 * Return the canonical option schema.
 *
 * Values are activation defaults; cache_key_prefix and page_optimization are
 * filled at activation because they depend on runtime context.
 *
 * @return array
 */
function khse_get_option_schema() {
	$schema = array(
		'uninstall'                             => 'yes',
		'clear_cache'                           => 'no',
		'excluded_list_filtered'                => '',
		'advanced_driver_file'                  => array(),
		'advanced_driver_memcached'             => array(),
		'advanced_driver_redis'                 => array(),
		'advanced_driver_mongodb'               => array(),
		'advanced_driver_memcached_connection_type' => 'tcp',
		'advanced_driver_redis_connection_type'     => 'tcp',
		'advanced_driver_mongodb_connection_type'   => 'tcp',
		'page_optimization'                     => array(),
		'post_types'                            => array(
			'home' => 'yes',
			'post' => 'yes',
			'page' => 'yes',
		),
		'post_archives'                         => array(
			'category' => 'yes',
			'tag'      => 'yes',
			'date'     => 'yes',
			'author'   => 'yes',
		),
	);

	foreach ( khse_get_settings_schema() as $section ) {
		foreach ( $section as $rule ) {
			if ( empty( $rule['option'] ) || 0 !== strpos( $rule['option'], 'khse_option_' ) ) {
				continue;
			}

			$key            = substr( $rule['option'], strlen( 'khse_option_' ) );
			$schema[ $key ] = $rule['default'];
		}
	}

	return $schema;
}

/**
 * Return the canonical option key list.
 *
 * @return array
 */
function khse_get_option_keys() {
	return array_keys( khse_get_option_schema() );
}
