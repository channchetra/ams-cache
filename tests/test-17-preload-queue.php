<?php
/**
 * Class PreloadQueueTest
 *
 * @package Cache_Master
 */

class PreloadQueueTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'scm_option_preload_cache', 'yes' );
		update_option( 'scm_option_caching_status', 'enable' );

		wp_clear_scheduled_hook( 'scm_preload_queue_event' );
		wp_clear_scheduled_hook( 'scm_preload_cache_event' );
		delete_transient( 'scm_preload_queue' );
	}

	public function test_queue_merges_deduplicates_and_schedules() {
		$first = scm_queue_preload_urls( array( home_url( '/' ), home_url( '/sample-page/' ) ) );
		$second = scm_queue_preload_urls( array( home_url( '/' ), home_url( '/other/' ) ) );

		$this->assertSame( 2, $first );
		$this->assertSame( 1, $second );
		$this->assertNotFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );

		$queue = get_transient( 'scm_preload_queue' );
		$this->assertCount( 3, $queue );
		$this->assertSame( 3, (int) get_option( 'scm_preload_queue_remaining' ) );
	}

	public function test_critical_helper_queues_the_post_permalink() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		// The publish transition already queues the same URLs; reset so the helper is exercised.
		delete_transient( 'scm_preload_queue' );
		wp_clear_scheduled_hook( 'scm_preload_queue_event' );

		$queued = scm_queue_critical_preload_urls( $post_id );

		$this->assertGreaterThan( 0, $queued );

		$queue = get_transient( 'scm_preload_queue' );
		$this->assertContains( get_permalink( $post_id ), $queue );
		$this->assertNotFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );
	}
}
