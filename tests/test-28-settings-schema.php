<?php
/**
 * Class SettingsSchemaTest
 *
 * @package KHSE_Cache
 */

class SettingsSchemaTest extends WP_UnitTestCase {

	public function tear_down() {
		@unlink( khse_get_config_path() );
		delete_option( 'khse_config' );
		parent::tear_down();
	}

	public function test_schema_options_exist_in_lifecycle_registry() {
		$lifecycle = khse_get_option_schema();

		foreach ( khse_get_settings_schema() as $section => $fields ) {
			foreach ( $fields as $field => $rule ) {
				$key = substr( $rule['option'], strlen( 'khse_option_' ) );

				$this->assertArrayHasKey( $key, $lifecycle, $section . '.' . $field );

				if ( empty( $rule['dynamic_default'] ) ) {
					$this->assertSame( $rule['default'], $lifecycle[ $key ], $section . '.' . $field . ' default' );
				}
			}
		}
	}

	public function test_section_lookup_returns_expected_fields() {
		$this->assertArrayHasKey( 'cachingStatus', khse_get_settings_section_schema( 'cache' ) );
		$this->assertArrayHasKey( 'enabled', khse_get_settings_section_schema( 'preload' ) );
		$this->assertArrayHasKey( 'excludedList', khse_get_settings_section_schema( 'rules' ) );
		$this->assertArrayHasKey( 'enabled', khse_get_settings_section_schema( 'statistics' ) );
		$this->assertArrayHasKey( 'widgetDisplay', khse_get_settings_section_schema( 'benchmark' ) );
		$this->assertArrayHasKey( 'paymentComplete', khse_get_settings_section_schema( 'woocommerce' ) );
		$this->assertSame( array(), khse_get_settings_section_schema( 'missing' ) );
	}

	public function test_accessor_reads_boolean_and_int_values() {
		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_ttl', '600' );

		$this->assertTrue( khse_get_setting( 'cache', 'cachingStatus' ) );
		$this->assertSame( 600, khse_get_setting( 'cache', 'ttl' ) );
		$this->assertNull( khse_get_setting( 'cache', 'missing' ) );
	}

	public function test_ajax_schema_applies_central_cache_rules() {
		khse_ajax_dashboard_apply_option_schema(
			array(
				'cachingStatus'       => true,
				'driver'              => 'redis',
				'ttlMechanism'        => false,
				'ttl'                 => 600,
				'expertModeStatus'    => true,
				'nginxDirect'         => true,
				'debugComment'        => false,
				'visibilityLoginUser' => true,
				'visibilityGuest'     => false,
			),
			khse_get_settings_section_schema( 'cache' )
		);

		$this->assertSame( 'enable', get_option( 'khse_option_caching_status' ) );
		$this->assertSame( 'redis', get_option( 'khse_option_driver' ) );
		$this->assertSame( 'disable', get_option( 'khse_option_ttl_mechanism' ) );
		$this->assertSame( 600, get_option( 'khse_option_ttl' ) );
		$this->assertSame( 'enable', get_option( 'khse_option_expert_mode_status' ) );
		$this->assertSame( 'yes', get_option( 'khse_option_nginx_direct_cache_status' ) );
		$this->assertSame( 'no', get_option( 'khse_option_html_debug_comment' ) );
		$this->assertSame( 'yes', get_option( 'khse_option_visibility_login_user' ) );
		$this->assertSame( 'no', get_option( 'khse_option_visibility_guest' ) );
	}

	public function test_visibility_settings_round_trip_through_dto_and_ajax_schema() {
		update_option( 'khse_option_visibility_login_user', 'yes' );
		update_option( 'khse_option_visibility_guest', 'no' );

		$dto = khse_dashboard_get_settings_data();

		$this->assertTrue( $dto['cache']['visibilityLoginUser'] );
		$this->assertFalse( $dto['cache']['visibilityGuest'] );

		khse_ajax_dashboard_apply_option_schema( $dto['cache'], khse_get_settings_section_schema( 'cache' ) );

		$this->assertSame( 'yes', get_option( 'khse_option_visibility_login_user' ) );
		$this->assertSame( 'no', get_option( 'khse_option_visibility_guest' ) );
	}

	public function test_config_export_uses_schema_values() {
		update_option( 'khse_option_driver', 'redis' );
		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_preload_cache', 'yes' );
		update_option( 'khse_option_preload_limit', '75' );
		update_option( 'khse_option_preload_homepage_links', 'no' );
		update_option( 'khse_option_woocommerce_status', 'yes' );
		update_option( 'khse_option_html_debug_comment', 'no' );
		update_option( 'khse_option_nginx_direct_cache_status', 'yes' );
		update_option( 'khse_option_cache_max_entries', '25' );
		update_option( 'khse_option_cache_key_prefix', 'abc_' );

		$config = khse_get_config_from_schema();

		$this->assertSame( 'redis', $config['cache_driver'] );
		$this->assertTrue( $config['cache_enabled'] );
		$this->assertTrue( $config['preload']['enable'] );
		$this->assertSame( 75, $config['preload']['limit'] );
		$this->assertFalse( $config['preload']['crawl_homepage_links'] );
		$this->assertTrue( $config['woocommerce']['enable'] );
		$this->assertFalse( $config['html_debug_comment'] );
		$this->assertTrue( $config['nginx_direct_cache'] );
		$this->assertSame( 25, $config['cache_max_entries'] );
		$this->assertSame( 'abc_', $config['cache_key_prefix'] );
	}

	public function test_dashboard_dto_uses_schema_values() {
		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_ttl', '900' );
		update_option( 'khse_option_preload_limit', '80' );
		update_option( 'khse_option_exclusion_status', 'yes' );
		update_option( 'khse_option_statistics_status', 'enable' );
		update_option( 'khse_option_benchmark_widget_display', 'icon' );
		update_option( 'khse_option_woocommerce_status', 'yes' );

		$dto = khse_dashboard_get_settings_data();

		$this->assertTrue( $dto['cache']['cachingStatus'] );
		$this->assertSame( 900, $dto['cache']['ttl'] );
		$this->assertSame( 80, $dto['preload']['limit'] );
		$this->assertTrue( $dto['rules']['enabled'] );
		$this->assertTrue( $dto['statistics']['enabled'] );
		$this->assertSame( 'icon', $dto['benchmark']['widgetDisplay'] );
		$this->assertTrue( $dto['woocommerce']['enabled'] );
	}

	public function test_expert_sync_persists_schema_values() {
		@unlink( khse_get_config_path() );
		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_driver', 'file' );
		update_option( 'khse_option_preload_cache', 'yes' );
		update_option( 'khse_option_woocommerce_status', 'no' );
		update_option( 'khse_option_html_debug_comment', 'yes' );

		khse_sync_expert_mode_runtime();

		$config = khse_get_config_data();

		$this->assertTrue( $config['cache_enabled'] );
		$this->assertSame( 'file', $config['cache_driver'] );
		$this->assertTrue( $config['preload']['enable'] );
		$this->assertFalse( $config['woocommerce']['enable'] );
		$this->assertTrue( $config['html_debug_comment'] );
		$this->assertArrayHasKey( 'exclusion', $config );
	}

	public function test_legacy_advanced_tab_maps_to_cache_view() {
		$_GET['page'] = 'kh-speed-engine-settings';
		$_GET['tab']  = 'advanced';

		$this->assertSame( 'cache', khse_get_admin_console_view() );

		unset( $_GET['page'], $_GET['tab'] );
	}
}
