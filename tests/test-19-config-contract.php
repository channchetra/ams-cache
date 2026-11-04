<?php
/**
 * Class ConfigContractTest
 *
 * @package Cache_Master
 */

class ConfigContractTest extends WP_UnitTestCase {

	public function tear_down() {
		@unlink( scm_get_config_path() );
		delete_option( 'scm_config' );
		parent::tear_down();
	}

	public function test_default_config_includes_connection_type() {
		$defaults = scm_get_default_config();

		$this->assertArrayHasKey( 'driver_connection_type', $defaults );
		$this->assertSame( 'tcp', $defaults['driver_connection_type'] );
	}

	public function test_partial_config_is_normalized() {
		@unlink( scm_get_config_path() );
		update_option( 'scm_config', array( 'cache_driver' => 'redis' ) );

		$config = scm_get_config_data();

		$this->assertSame( 'redis', $config['cache_driver'] );
		$this->assertFalse( $config['exclusion']['enable'] );
		$this->assertSame( array(), $config['exclusion']['excluded_list'] );
		$this->assertFalse( $config['woocommerce']['enable'] );
		$this->assertSame( 50, $config['preload']['limit'] );
	}

	public function test_socket_connection_type_survives_expert_mode_sync() {
		@unlink( scm_get_config_path() );
		update_option( 'scm_option_driver', 'redis' );
		update_option( 'scm_option_advanced_driver_redis_connection_type', 'socket' );

		scm_sync_expert_mode_runtime();

		$config = scm_get_config_data();

		$this->assertSame( 'redis', $config['cache_driver'] );
		$this->assertSame( 'socket', $config['driver_connection_type'] );
	}

	public function test_html_debug_comment_defaults_to_enabled() {
		delete_option( 'scm_option_html_debug_comment' );
		@unlink( scm_get_config_path() );

		scm_update_scm_option_html_debug_comment();

		$config = scm_get_config_data();

		$this->assertTrue( $config['html_debug_comment'] );
	}

	public function test_cache_master_reads_partial_config() {
		@unlink( scm_get_config_path() );
		update_option( 'scm_config', array( 'cache_driver' => 'file' ) );
		update_option( 'scm_option_driver', 'file' );

		$cm = new Cache_Master();

		$this->assertFalse( $cm->config['exclusion']['enable'] );
		$this->assertSame( array(), $cm->config['exclusion']['excluded_list'] );
		$this->assertFalse( $cm->config['woocommerce']['enable'] );
	}

	public function test_expert_config_includes_read_switches() {
		update_option( 'scm_option_caching_status', 'enable' );

		scm_sync_expert_mode_runtime();

		$config = scm_get_config_data();

		$this->assertArrayHasKey( 'cache_enabled', $config );
		$this->assertTrue( $config['cache_enabled'] );
		$this->assertSame( rtrim( get_site_url(), '/' ), $config['site_url'] );
	}
}
