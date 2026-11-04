<?php
/**
 * Class PurgeAuthorizationTest
 *
 * @package Cache_Master
 */

class PurgeAuthorizationTest extends WP_UnitTestCase {

	public function test_contributor_cannot_purge_another_users_post() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create( array( 'post_author' => $admin_id, 'post_status' => 'publish' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'contributor' ) );

		wp_set_current_user( $user_id );

		$this->assertFalse( scm_can_purge_url( get_permalink( $post_id ) ) );
	}

	public function test_author_can_purge_own_post() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id, 'post_status' => 'publish' ) );

		wp_set_current_user( $author_id );

		$this->assertTrue( scm_can_purge_url( get_permalink( $post_id ) ) );
	}

	public function test_editor_can_purge_another_users_post() {
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
}
