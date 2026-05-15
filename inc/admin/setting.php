<?php
/**
 * AMS Cache - Setting page.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

add_action( 'admin_init', 'scm_settings' );

/**
 * Add settings.
 *
 * @return void
 */
function scm_settings() {

	$register_groups = array(

		// Settings - Basic (Page 1)
		1 => array(
			'caching_status',
			'driver',
			'ttl',
			'ttl_mechanism',
			'visibility_login_user',
			'visibility_guest',
			'html_debug_comment',
			'uninstall',
		),

		// Expert mode (Page 2)
		2 => array(
			'expert_mode_status',
		),

		// Statistics (Page 3, 4 mixed)
		3 => array(
			'statistics_status',
		),

		4 => array(
			'clear_cache',
		),

		// Benchmark (Page 5)
		5 => array(
			'benchmark_widget',
			'benchmark_footer_text',
			'benchmark_widget_display',
			'benchmark_footer_text_display',
		),

		// Settings - Perferences (Page 6)
		6 => array(
			'post_types',
			'post_homepage',
			'post_archives',
			'preload_cache',
			'preload_limit',
			'preload_homepage_links',
		),

		// Settings - Advanced (Page 7)
		7 => array(
			'advanced_driver_file',
			'advanced_driver_memcached',
			'advanced_driver_redis',
			'advanced_driver_mongodb',
			'advanced_driver_memcached_connection_type',
			'advanced_driver_redis_connection_type',
			'advanced_driver_mongodb_connection_type',
			'cache_key_prefix',
			'cache_max_entries',
			'nginx_direct_cache_status',
		),

		// Settings - WooCommerce (Page 8)
		8 => array(
			'woocommerce_status',
			'woocommerce_post_types',
			'woocommerce_post_archives',
			'woocommerce_event_payment_complete',
		),

		// Settings - Exclusion (Page 9)
		9 => array(
			'exclusion_status',
			'excluded_list',
			'excluded_get_vars',
			'excluded_post_vars',
			'excluded_cookie_vars',
		),

		// Settings - Optimization (Page 10)
		10 => array(
			'page_optimization',
		),
	);

	foreach ( $register_groups as $index => $options ) {
		foreach ( $options as $option ) {
			register_setting( 'scm_setting_group_' . $index, 'scm_option_' . $option );
		}
	}

	$register_sections = array(

		// Settings - Basic
		array(
			'title'    => __( 'Driver', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 1,
			'settings' => array(
				array(
					'title'    => __( 'Caching Status', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_caching_status' );
					},
				),
				array(
					'title'    => __( 'Cache Driver', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_driver' );
					},
				),
				array(
					'title'    => __( 'Time to Live', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_ttl' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Visibilty', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 1,
			'settings' => array(
				array(
					'title'    => __( 'Guests', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_visibility_guest' );
					},
				),
				array(
					'title'    => __( 'Logged-in Users', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_visibility_login_user' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Others', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 1,
			'settings' => array(
				array(
					'title'    => __( 'Debug Comment', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_html_debug_comment' );
					},
				),
				array(
					'title'    => __( 'Uninstall', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_uninstall' );
					},
				),
			),
		),

		// Settings - Perferences

		array(
			'title'    => __( 'Pages', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 6,
			'settings' => array(
				array(
					'title'    => __( 'Post Types', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_post_types' );
					},
				),
				array(
					'title'    => __( 'Homepage', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_post_homepage' );
					},
				),
				array(
					'title'    => __( 'Archive Pages', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_post_archives' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Preload', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 6,
			'settings' => array(
				array(
					'title'    => __( 'Cache Preload', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_preload_cache' );
					},
				),
			),
		),

		// Settings - Advanced
		array(
			'title'    => __( 'Driver', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 7,
			'settings' => array(
				array(
					'title'    => __( 'File', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_advanced_cache_driver_file' );
					},
				),
				array(
					'title'    => __( 'Redis', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_advanced_cache_driver_redis' );
					},
				),
				array(
					'title'    => __( 'Memcached', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_advanced_cache_driver_memcached' );
					},
				),
				array(
					'title'    => __( 'MongoDB', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_advanced_cache_driver_mongodb' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Shared Cache Stores', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 7,
			'settings' => array(
				array(
					'title'    => __( 'Key Prefix', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_cache_key_prefix' );
					},
				),
				array(
					'title'    => __( 'Max Cache Entries', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_cache_max_entries' );
					},
				),
				array(
					'title'    => __( 'Nginx Direct Cache', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_nginx_direct_cache' );
					},
				),
			),
		),

		// Settings - Optimization
		array(
			'title'    => __( 'Page Optimization', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 10,
			'settings' => array(
				array(
					'title'    => __( 'Options', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_page_optimization' );
					},
				),
			),
		),

		// Settings - WooCommerce
		array(
			'title'    => __( 'Support', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 8,
			'settings' => array(
				array(
					'title'    => __( 'Enable', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_woocommerce_status' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Pages', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 8,
			'settings' => array(
				array(
					'title'    => __( 'Post Types', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_woocommerce_post_types' );
					},
				),
				array(
					'title'    => __( 'Archive Pages', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_woocommerce_post_archives' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Events', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 8,
			'settings' => array(
				array(
					'title'    => __( 'Payment Complete', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_woocommerce_event_payment_complete' );
					},
				),
			),
		),

		// Settings - Exclusion
		array(
			'title'    => '',
			'callback' => 'scm_cb_setting_section',
			'group_id' => 9,
			'settings' => array(
				array(
					'title'    => __( 'Enable', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_exclusion_status' );
					},
				),
				array(
					'title'    => __( 'Excluded URL Path List', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_excluded_list' );
					},
				),
				array(
					'title'    => __( 'Excluded $_GET Variables', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_excluded_get_vars' );
					},
				),
				array(
					'title'    => __( 'Excluded $_POST Variables', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_excluded_post_vars' );
					},
				),
				array(
					'title'    => __( 'Excluded $_COOKIE Variables', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_excluded_cookie_vars' );
					},
				),
			),
		),

		// Expert mode.
		array(
			'title'    => __( 'Expert Mode', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 2,
			'settings' => array(
				array(
					'title'    => __( 'Status', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_expert_mode_status' );
					},
				),
			),
		),

		// Statistics
		array(
			'title'    => __( 'Statistics', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 3,
			'settings' => array(
				array(
					'title'    => __( 'Enable', 'ams-cache' ),
					'callback' => 'scm_cb_statistics_status',
					'callback' => function() {
						echo scm_load_view( 'option_statistics_status' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Clear Cache', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 4,
			'settings' => array(
				array(
					'title'    => '',
					'callback' => function() {
						echo scm_load_view( 'option_clear_cache' );
					},
				),
			),
		),

		// Benchmark settings.
		array(
			'title'    => __( 'Footer Text', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 5,
			'settings' => array(
				array(
					'title'    => __( 'Enable', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_benchmark_footer_text' );
					},
				),
				array(
					'title'    => __( 'Display', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_benchmark_footer_text_display' );
					},
				),
			),
		),

		array(
			'title'    => __( 'Widget', 'ams-cache' ),
			'callback' => 'scm_cb_setting_section',
			'group_id' => 5,
			'settings' => array(
				array(
					'title'    => __( 'Enable', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_benchmark_widget' );
					},
				),
				array(
					'title'    => __( 'Display', 'ams-cache' ),
					'callback' => function() {
						echo scm_load_view( 'option_benchmark_widget_display' );
					},
				),
			),
		),
	);

	$section_id = 0;
	$setting_id = 0;

	foreach ( $register_sections as $section ) {
		$section_id++;
		add_settings_section(
			'scm_setting_section_' . $section_id,
			$section['title'],
			$section['callback'],
			'scm_setting_page_' . $section['group_id']
		);

		foreach ( $section['settings'] as $setting ) {
			$setting_id++;
			add_settings_field(
				'scm_option_id_' . $setting_id,
				$setting['title'],
				$setting['callback'],
				'scm_setting_page_' . $section['group_id'],
				'scm_setting_section_' . $section_id
			);
		}
	}
}

function scm_cb_setting_section() {
	echo '';
}
