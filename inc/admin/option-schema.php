<?php
/**
 * AMS Cache option schema — canonical lifecycle registry.
 *
 * @package AMS Cache
 * @license GPLv3 (or later)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Return the canonical option schema.
 *
 * Values are activation defaults; cache_key_prefix and page_optimization are
 * filled at activation because they depend on runtime context.
 *
 * @return array
 */
function scm_get_option_schema() {
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
		'woocommerce_post_types'                => array(
			'product' => 'no',
		),
		'woocommerce_post_archives'             => array(
			'product_tag' => 'no',
			'product_cat' => 'no',
		),
	);

	foreach ( scm_get_settings_schema() as $section ) {
		foreach ( $section as $rule ) {
			if ( empty( $rule['option'] ) || 0 !== strpos( $rule['option'], 'scm_option_' ) ) {
				continue;
			}

			$key            = substr( $rule['option'], strlen( 'scm_option_' ) );
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
function scm_get_option_keys() {
	return array_keys( scm_get_option_schema() );
}
