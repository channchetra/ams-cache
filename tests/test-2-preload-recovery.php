<?php
/**
 * Class PreloadRecoveryTest
 *
 * @package Cache_Master
 */

/**
 * Preload queue stall-recovery test case.
 *
 * Covers scm_maybe_schedule_preload_cache() resuming a stalled preload queue
 * when the scm_preload_queue_event cron event was lost.
 */
class PreloadRecoveryTest extends WP_UnitTestCase {

	/**
	 * Enable preload and caching, reset cron and queue state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'scm_option_preload_cache', 'yes' );
		update_option( 'scm_option_caching_status', 'enable' );

		wp_clear_scheduled_hook( 'scm_preload_queue_event' );
		wp_clear_scheduled_hook( 'scm_preload_cache_event' );
		delete_transient( 'scm_preload_queue' );

		// Keep the bootstrap branch quiet so tests only exercise queue recovery.
		update_option( 'scm_preload_bootstrap_version', (string) SCM_PLUGIN_VERSION );
	}

	/**
	 * Queue exists and no event scheduled: recovery must schedule the event.
	 *
	 * @return void
	 */
	public function testStalledQueueReschedulesEvent() {
		set_transient( 'scm_preload_queue', array( home_url( '/' ), home_url( '/sample-page/' ) ), HOUR_IN_SECONDS );

		$this->assertFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );

		scm_maybe_schedule_preload_cache();

		$this->assertNotFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );
	}

	/**
	 * Queue exists and event already scheduled: recovery must not reschedule.
	 *
	 * @return void
	 */
	public function testHealthyQueueIsNoOp() {
		set_transient( 'scm_preload_queue', array( home_url( '/' ) ), HOUR_IN_SECONDS );

		$timestamp = time() + 300;
		wp_schedule_single_event( $timestamp, 'scm_preload_queue_event' );

		scm_maybe_schedule_preload_cache();

		$this->assertSame( $timestamp, wp_next_scheduled( 'scm_preload_queue_event' ) );
	}

	/**
	 * Queue empty or invalid: recovery must not schedule anything.
	 *
	 * @return void
	 */
	public function testEmptyQueueDoesNotSchedule() {
		delete_transient( 'scm_preload_queue' );

		scm_maybe_schedule_preload_cache();

		$this->assertFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );

		// Invalid (non-array) queue payload must also be ignored.
		set_transient( 'scm_preload_queue', 'corrupted', HOUR_IN_SECONDS );

		scm_maybe_schedule_preload_cache();

		$this->assertFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );
	}

	/**
	 * Preload disabled: recovery must not run even with a stalled queue.
	 *
	 * @return void
	 */
	public function testDisabledPreloadSkipsRecovery() {
		update_option( 'scm_option_preload_cache', 'no' );
		set_transient( 'scm_preload_queue', array( home_url( '/' ) ), HOUR_IN_SECONDS );

		scm_maybe_schedule_preload_cache();

		$this->assertFalse( wp_next_scheduled( 'scm_preload_queue_event' ) );
	}
}
