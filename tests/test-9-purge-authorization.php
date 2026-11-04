<?php
/**
 * Class PurgeAuthorizationTest
 *
 * @package Cache_Master
 */

class PurgeAuthorizationTest extends WP_UnitTestCase {

	public function test_contributor_cannot_purge_another_users_post() {
		$this->set_permalink_structure( '/%postname%/' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create( array( 'post_author' => $admin_id, 'post_status' => 'publish' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'contributor' ) );

		wp_set_current_user( $user_id );

		$this->assertFalse( scm_can_purge_url( get_permalink( $post_id ) ) );
	}

	public function test_author_can_purge_own_post() {
		$this->set_permalink_structure( '/%postname%/' );

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id, 'post_status' => 'publish' ) );

		wp_set_current_user( $author_id );

		$this->assertTrue( scm_can_purge_url( get_permalink( $post_id ) ) );
	}

	public function test_editor_can_purge_another_users_post() {
		$this->set_permalink_structure( '/%postname%/' );

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id, 'post_status' => 'publish' ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor_id );

		$this->assertTrue( scm_can_purge_url( get_permalink( $post_id ) ) );
	}

	public function test_contributor_cannot_purge_homepage() {
		$user_id = self::factory()->user->create( array( 'role' => 'contributor' ) );

		wp_set_current_user( $user_id );

		$this->assertFalse( scm_can_purge_url( home_url( '/' ) ) );
	}

	public function test_admin_can_purge_homepage() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		$this->assertTrue( scm_can_purge_url( home_url( '/' ) ) );
	}

	public function test_author_cannot_purge_published_post_with_plain_permalink() {
		$this->set_permalink_structure( '' );

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id, 'post_status' => 'publish' ) );
		$url       = get_permalink( $post_id );

		wp_set_current_user( $author_id );

		$this->assertStringContainsString( '?p=', $url );
		$this->assertFalse( scm_can_purge_url( $url ) );
	}

	public function test_contributor_cannot_purge_own_draft_query_url() {
		$this->set_permalink_structure( '/%postname%/' );

		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$post_id        = self::factory()->post->create( array( 'post_author' => $contributor_id, 'post_status' => 'draft' ) );
		$url            = get_permalink( $post_id );

		wp_set_current_user( $contributor_id );

		$this->assertStringContainsString( '?p=', $url );
		$this->assertFalse( scm_can_purge_url( $url ) );
	}

	public function test_query_string_cache_identity() {
		$this->assertTrue( scm_is_cacheable_query_string( '' ) );
		$this->assertTrue( scm_is_cacheable_query_string( 'utm_source=news&fbclid=abc' ) );
		$this->assertFalse( scm_is_cacheable_query_string( 'p=1' ) );
		$this->assertFalse( scm_is_cacheable_query_string( 'utm_source=news&p=1' ) );
	}
}
