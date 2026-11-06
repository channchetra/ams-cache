<?php
/**
 * Class PreloadSchedulingTest
 *
 * @package KHSE_Cache
 */

class PreloadSchedulingTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'khse_option_preload_cache', 'yes' );
		update_option( 'khse_option_caching_status', 'enable' );

		wp_clear_scheduled_hook( 'khse_preload_queue_event' );
		wp_clear_scheduled_hook( 'khse_preload_cache_event' );
		delete_transient( 'khse_preload_queue' );
	}

	public function test_preload_private_network_detection() {
		$this->assertTrue( khse_preload_host_is_private_network( '192.168.128.4' ) );
		$this->assertTrue( khse_preload_host_is_private_network( '10.0.0.5' ) );
		$this->assertFalse( khse_preload_host_is_private_network( '8.8.8.8' ) );
	}

	public function test_preload_blocking_is_scoped_to_public_hosts() {
		$this->assertTrue( khse_preload_should_block( 'https://example.com/' ) );
		$this->assertFalse( khse_preload_should_block( 'https://192.168.128.4/' ) );

		add_filter( 'khse_preload_treat_private_as_local', '__return_false' );
		$this->assertTrue( khse_preload_should_block( 'https://192.168.128.4/' ) );
		remove_filter( 'khse_preload_treat_private_as_local', '__return_false' );

		add_filter( 'khse_preload_blocking', '__return_false' );
		$this->assertFalse( khse_preload_should_block( 'https://example.com/' ) );
		remove_filter( 'khse_preload_blocking', '__return_false' );
	}

	public function test_preload_sslverify_accepts_private_network_hosts() {
		$this->assertFalse( khse_preload_should_verify_ssl( 'https://192.168.128.4/' ) );
		$this->assertFalse( khse_preload_should_verify_ssl( 'https://10.0.0.5/' ) );
		$this->assertFalse( khse_preload_should_verify_ssl( 'https://127.0.0.1/' ) );
		$this->assertTrue( khse_preload_should_verify_ssl( 'https://example.com/' ) );
	}

	public function test_private_tls_can_be_re_enabled_with_a_filter() {
		add_filter( 'khse_preload_skip_private_tls', '__return_false' );
		$this->assertTrue( khse_preload_should_verify_ssl( 'https://192.168.128.4/' ) );
		remove_filter( 'khse_preload_skip_private_tls', '__return_false' );
	}

	public function test_preload_cache_queues_and_schedules_without_running_a_batch() {
		khse_preload_cache( 1 );

		$this->assertNotFalse( wp_next_scheduled( 'khse_preload_queue_event' ) );
		$this->assertSame( 0, (int) get_option( 'khse_preload_queue_processed', 0 ) );
		$this->assertIsArray( get_transient( 'khse_preload_queue' ) );
	}
}
