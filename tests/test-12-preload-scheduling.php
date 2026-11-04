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

	public function test_preload_cache_queues_and_schedules_without_running_a_batch() {
		khse_preload_cache( 1 );

		$this->assertNotFalse( wp_next_scheduled( 'khse_preload_queue_event' ) );
		$this->assertSame( 0, (int) get_option( 'khse_preload_queue_processed', 0 ) );
		$this->assertIsArray( get_transient( 'khse_preload_queue' ) );
	}
}
