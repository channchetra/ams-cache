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
	return array(
		'driver'                             => 'file',
		'ttl'                                => '86400',
		'uninstall'                          => 'yes',
		'caching_status'                     => 'disable',
		'expert_mode_status'                 => 'disable',
		'post_homepage'                      => 'yes',
		'visibility_login_user'              => 'no',
		'visibility_guest'                   => 'yes',
		'statistics_status'                  => 'disable',
		'clear_cache'                        => 'no',
		'benchmark_widget'                   => 'no',
		'benchmark_footer_text'              => 'no',
		'benchmark_widget_display'           => 'both',
		'benchmark_footer_text_display'      => 'text',
		'exclusion_status'                   => 'no',
		'excluded_list'                      => '',
		'excluded_list_filtered'             => '',
		'excluded_get_vars'                  => '',
		'excluded_post_vars'                 => '',
		'excluded_cookie_vars'               => '',
		'advanced_driver_file'               => array(),
		'advanced_driver_memcached'          => array(),
		'advanced_driver_redis'              => array(),
		'advanced_driver_mongodb'            => array(),
		'advanced_driver_memcached_connection_type' => 'tcp',
		'advanced_driver_redis_connection_type'     => 'tcp',
		'advanced_driver_mongodb_connection_type'   => 'tcp',
		'cache_key_prefix'                   => '',
		'cache_max_entries'                  => 0,
		'ttl_mechanism'                      => 'enable',
		'nginx_direct_cache_status'          => 'no',
		'page_optimization'                  => array(),
		'preload_cache'                      => 'no',
		'preload_limit'                      => 50,
		'preload_homepage_links'             => 'yes',
		'html_debug_comment'                 => 'yes',
		'post_types'                         => array(
			'home' => 'yes',
			'post' => 'yes',
			'page' => 'yes',
		),
		'post_archives'                      => array(
			'category' => 'yes',
			'tag'      => 'yes',
			'date'     => 'yes',
			'author'   => 'yes',
		),
		'woocommerce_status'                 => 'no',
		'woocommerce_post_types'             => array(
			'product' => 'no',
		),
		'woocommerce_post_archives'          => array(
			'product_tag' => 'no',
			'product_cat' => 'no',
		),
		'woocommerce_event_payment_complete' => 'no',
	);
}

/**
 * Return the canonical option key list.
 *
 * @return array
 */
function scm_get_option_keys() {
	return array_keys( scm_get_option_schema() );
}
