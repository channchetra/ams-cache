<?php
/**
 * Class PreloadSchedulingTest
 *
 * @package Cache_Master
 */

class PreloadSchedulingTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'scm_option_preload_cache', 'yes' );
		update_option( 'scm_option_caching_status', 'enable' );

		wp_clear_scheduled_hook( 'scm_preload_queue_event' );
		wp_clear_scheduled_hook( 'scm_preload_cache_event' );
		delete_transient( 'scm_preload_queue' );
	}

	public function test_preload_cache_queues_and_schedules_without_running_a_batch() {
		scm_preload_cache( 1 );

		$this->assertNotFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );
		$this->assertSame( 0, (int) get_option( 'scm_preload_queue_processed', 0 ) );
		$this->assertIsArray( get_transient( 'scm_preload_queue' ) );
	}
}
