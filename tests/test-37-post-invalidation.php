<?php
/**
 * Class PostInvalidationTest
 *
 * @package KHSE_Cache
 */

class PostInvalidationTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'khse_option_post_archives' );
		delete_option( 'khse_option_post_homepage' );
		delete_option( 'khse_option_caching_status' );
		parent::tear_down();
	}

	public function test_homepage_and_related_archives_are_always_invalidated() {
		update_option( 'khse_option_post_archives', array() );
		update_option( 'khse_option_post_homepage', 'no' );

		$category_id = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'News' ) );
		$tag_id      = self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Cache' ) );
		$post_id     = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $category_id ),
				'tags_input'    => array( $tag_id ),
			)
		);
		$post        = get_post( $post_id );
		$urls        = khse_get_post_cache_invalidation_urls( $post_id, $post );

		$this->assertContains( home_url( '/' ), $urls );
		$this->assertContains( get_permalink( $post_id ), $urls );
		$this->assertContains( get_category_link( $category_id ), $urls );
		$this->assertContains( get_tag_link( $tag_id ), $urls );
		$this->assertContains( get_author_posts_url( (int) $post->post_author ), $urls );

		$post_type_archive = get_post_type_archive_link( 'post' );

		if ( $post_type_archive ) {
			$this->assertContains( $post_type_archive, $urls );
		}
	}

	public function test_post_update_queues_preload_after_invalidating() {
		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_preload_cache', 'yes' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		delete_transient( 'khse_preload_queue' );
		wp_clear_scheduled_hook( 'khse_preload_queue_event' );
		unset( $GLOBALS['khse_recently_published_post_ids'][ $post_id ] );

		khse_update_post( $post_id, get_post( $post_id ), true );

		$queue = get_transient( 'khse_preload_queue' );

		$this->assertIsArray( $queue );
		$this->assertContains( home_url( '/' ), $queue );
		$this->assertContains( get_permalink( $post_id ), $queue );
		$this->assertNotFalse( wp_next_scheduled( 'khse_preload_queue_event' ) );

		delete_transient( 'khse_preload_queue' );
		wp_clear_scheduled_hook( 'khse_preload_queue_event' );
		delete_option( 'khse_option_preload_cache' );
	}

	public function test_purge_post_cache_urls_uses_the_driver_for_each_url() {
		update_option( 'khse_option_caching_status', 'enable' );

		$category_id = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Updates' ) );
		$post_id     = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $category_id ),
			)
		);
		$driver      = new class() {
			public $deleted = array();

			public function delete( $key ) {
				$this->deleted[] = $key;
				return true;
			}
		};

		$count = khse_purge_post_cache_urls( $post_id, get_post( $post_id ), $driver );

		$this->assertGreaterThan( 1, $count );
		$this->assertGreaterThan( 1, count( $driver->deleted ) );
	}
}
