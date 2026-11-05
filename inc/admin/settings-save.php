<?php
/**
 * KH Speed Engine — admin/settings-save.php
 *
 * Dashboard settings persistence, split from the AJAX transport layer.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Apply a dashboard settings payload to WordPress options.
 *
 * @param array $settings Settings payload.
 *
 * @return void
 */
function khse_apply_dashboard_settings( $settings ) {
	khse_settings_batch_active( true );

	if ( isset( $settings['cache'] ) && is_array( $settings['cache'] ) ) {
		$cache = $settings['cache'];

		if ( isset( $cache['driverAdvanced'] ) && is_array( $cache['driverAdvanced'] ) ) {
			$advanced = $cache['driverAdvanced'];
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
					'compress'           => khse_ajax_dashboard_yes_no( $file['compress'] ),
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
					'compress'           => khse_ajax_dashboard_yes_no( $redis['compress'], 'yes' ),
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
			update_option( 'khse_option_advanced_driver_redis_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['redis'] ) ? $conn['redis'] : 'tcp' ) );
			update_option( 'khse_option_advanced_driver_memcached_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['memcached'] ) ? $conn['memcached'] : ( isset( $conn['memcache'] ) ? $conn['memcache'] : 'tcp' ) ) );
			update_option( 'khse_option_advanced_driver_mongodb_connection_type', khse_ajax_dashboard_connection_type( isset( $conn['mongo'] ) ? $conn['mongo'] : ( isset( $conn['mongodb'] ) ? $conn['mongodb'] : 'tcp' ) ) );
		}	khse_ajax_dashboard_apply_option_schema( $cache, khse_get_settings_section_schema( 'cache' ) );


}

if ( isset( $settings['preload'] ) && is_array( $settings['preload'] ) ) {
	$preload = $settings['preload'];

	khse_ajax_dashboard_apply_option_schema( $preload, khse_get_settings_section_schema( 'preload' ) );
	khse_ajax_dashboard_save_toggle_map( isset( $preload['postTypes'] ) ? $preload['postTypes'] : array(), 'khse_option_post_types' );
	khse_ajax_dashboard_save_toggle_map( isset( $preload['archives'] ) ? $preload['archives'] : array(), 'khse_option_post_archives' );
}

if ( isset( $settings['performance'] ) && is_array( $settings['performance'] ) ) {
	$incoming = $settings['performance'];
	$current  = khse_get_page_optimization_settings();
	$defaults = khse_get_default_page_optimization_settings();
	$boolean_keys = khse_get_page_optimization_toggle_keys();

	foreach ( $boolean_keys as $key ) {
		if ( array_key_exists( $key, $incoming ) ) {
			$current[ $key ] = khse_ajax_dashboard_yes_no( $incoming[ $key ], isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'no' );
		}
	}

	$current['critical_image_count'] = max( 0, min( 5, absint( isset( $incoming['critical_image_count'] ) ? $incoming['critical_image_count'] : $current['critical_image_count'] ) ) );
	$current['external_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['external_ucss_max_file_size'] ) ? $incoming['external_ucss_max_file_size'] : $current['external_ucss_max_file_size'] ) ) );
	$current['local_ucss_max_file_size'] = max( 51200, min( 1048576, absint( isset( $incoming['local_ucss_max_file_size'] ) ? $incoming['local_ucss_max_file_size'] : $current['local_ucss_max_file_size'] ) ) );
	$current['js_analysis_max_script_bytes'] = max( 16384, min( 1048576, absint( isset( $incoming['js_analysis_max_script_bytes'] ) ? $incoming['js_analysis_max_script_bytes'] : $current['js_analysis_max_script_bytes'] ) ) );
	$current['js_analysis_max_total_bytes'] = max( 65536, min( 4194304, absint( isset( $incoming['js_analysis_max_total_bytes'] ) ? $incoming['js_analysis_max_total_bytes'] : $current['js_analysis_max_total_bytes'] ) ) );
	$current['ucss_safelist']        = sanitize_textarea_field( isset( $incoming['ucss_safelist'] ) ? $incoming['ucss_safelist'] : $current['ucss_safelist'] );
	$current['media_exclusions']     = sanitize_textarea_field( isset( $incoming['media_exclusions'] ) ? $incoming['media_exclusions'] : $current['media_exclusions'] );
	$current['js_exclusions']        = sanitize_textarea_field( isset( $incoming['js_exclusions'] ) ? $incoming['js_exclusions'] : $current['js_exclusions'] );

	update_option( 'khse_option_page_optimization', $current );
}

if ( isset( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
	$rules = $settings['rules'];

	khse_ajax_dashboard_apply_option_schema( $rules, khse_get_settings_section_schema( 'rules' ) );
}

if ( isset( $settings['statistics'] ) && is_array( $settings['statistics'] ) ) {
	khse_ajax_dashboard_apply_option_schema( $settings['statistics'], khse_get_settings_section_schema( 'statistics' ) );
}

if ( isset( $settings['benchmark'] ) && is_array( $settings['benchmark'] ) ) {
	$benchmark = $settings['benchmark'];

	khse_ajax_dashboard_apply_option_schema( $benchmark, khse_get_settings_section_schema( 'benchmark' ) );
}
}

/**
 * Normalize a yes/no setting value.
 *
 * @param mixed  $value   Incoming value.
 * @param string $default Default value.
 *
 * @return string
 */
function khse_ajax_dashboard_yes_no( $value, $default = 'no' ) {
	$value = is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : sanitize_key( (string) $value );

	return in_array( $value, array( 'yes', 'no' ), true ) ? $value : $default;
}

/**
 * Normalize an enable/disable setting value.
 *
 * @param mixed  $value   Incoming value.
 * @param string $default Default value.
 *
 * @return string
 */
function khse_ajax_dashboard_enable_disable( $value, $default = 'disable' ) {
	$value = is_bool( $value ) ? ( $value ? 'enable' : 'disable' ) : sanitize_key( (string) $value );

	return in_array( $value, array( 'enable', 'disable' ), true ) ? $value : $default;
}

/**
 * Apply a declarative option schema to an incoming settings section.
 *
 * @param array $incoming Incoming settings.
 * @param array $schema   Field => rule map.
 *
 * @return void
 */
function khse_ajax_dashboard_apply_option_schema( $incoming, $schema ) {
	foreach ( $schema as $field => $rule ) {
		if ( ! array_key_exists( $field, $incoming ) ) {
			continue;
		}

		$value = $incoming[ $field ];

		switch ( $rule['sanitize'] ) {
			case 'yes_no':
				$value = khse_ajax_dashboard_yes_no( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'no' );
				break;

			case 'enable_disable':
				$value = khse_ajax_dashboard_enable_disable( $value, isset( $rule['fallback'] ) ? $rule['fallback'] : 'disable' );
				break;

			case 'absint':
				$value = absint( $value );
				break;

			case 'key':
				$value = sanitize_key( (string) $value );
				break;

			case 'text':
				$value = sanitize_text_field( (string) $value );
				break;

			case 'textarea':
				$value = sanitize_textarea_field( (string) $value );
				break;

			case 'enum':
				$value = sanitize_key( (string) $value );
				$value = in_array( $value, $rule['values'], true ) ? $value : $rule['fallback'];
				break;
		}

		if ( isset( $rule['min'] ) ) {
			$value = max( $rule['min'], $value );
		}

		if ( isset( $rule['max'] ) ) {
			$value = min( $rule['max'], $value );
		}

		update_option( $rule['option'], $value );
	}
}
