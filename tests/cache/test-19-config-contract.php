<?php
/**
 * Class ConfigContractTest
 *
 * @package KHSE_Cache
 */

class ConfigContractTest extends WP_UnitTestCase {

	public function tear_down() {
		@unlink( khse_get_config_path() );
		delete_option( 'khse_config' );
		parent::tear_down();
	}

	public function test_default_config_includes_connection_type() {
		$defaults = khse_get_default_config();

		$this->assertArrayHasKey( 'driver_connection_type', $defaults );
		$this->assertSame( 'tcp', $defaults['driver_connection_type'] );
	}

	public function test_partial_config_is_normalized() {
		@unlink( khse_get_config_path() );
		update_option( 'khse_config', array( 'cache_driver' => 'redis' ) );

		$config = khse_get_config_data();

		$this->assertSame( 'redis', $config['cache_driver'] );
		$this->assertFalse( $config['exclusion']['enable'] );
		$this->assertSame( array(), $config['exclusion']['excluded_list'] );
		$this->assertSame( 50, $config['preload']['limit'] );
	}

	public function test_socket_connection_type_survives_expert_mode_sync() {
		@unlink( khse_get_config_path() );
		update_option( 'khse_option_driver', 'redis' );
		update_option( 'khse_option_advanced_driver_redis_connection_type', 'socket' );

		$this->assertTrue( khse_sync_expert_mode_runtime() );

		$config = khse_get_config_data();

		$this->assertSame( 'redis', $config['cache_driver'] );
		$this->assertSame( 'socket', $config['driver_connection_type'] );
	}

	public function test_html_debug_comment_defaults_to_enabled() {
		delete_option( 'khse_option_html_debug_comment' );
		@unlink( khse_get_config_path() );

		khse_update_khse_option_html_debug_comment();

		$config = khse_get_config_data();

		$this->assertTrue( $config['html_debug_comment'] );
	}

	public function test_cache_master_reads_partial_config() {
		@unlink( khse_get_config_path() );
		update_option( 'khse_config', array( 'cache_driver' => 'file' ) );
		update_option( 'khse_option_driver', 'file' );

		$cm = new KHSE_Cache();

		$this->assertFalse( $cm->config['exclusion']['enable'] );
		$this->assertSame( array(), $cm->config['exclusion']['excluded_list'] );
	}

	public function test_settings_batch_defers_clear_and_sync() {
		khse_settings_batch_active( true );

		$this->assertSame( 0, khse_clear_all_cache() );
		$this->assertTrue( khse_sync_expert_mode_runtime() );

		khse_settings_batch_active( false );
	}

	public function test_config_write_reports_success() {
		@unlink( khse_get_config_path() );

		$this->assertTrue( khse_update_config( array( 'cache_enabled' => true ) ) );
		$this->assertFileExists( khse_get_config_path() );
	}

	public function test_expert_config_includes_read_switches() {
		update_option( 'khse_option_caching_status', 'enable' );

		khse_sync_expert_mode_runtime();

		$config = khse_get_config_data();

		$this->assertArrayHasKey( 'cache_enabled', $config );
		$this->assertTrue( $config['cache_enabled'] );
		$this->assertSame( rtrim( get_site_url(), '/' ), $config['site_url'] );
	}
}
