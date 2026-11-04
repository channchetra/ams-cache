<?php
/**
 * Class PurgePermalinkTest
 *
 * @package Cache_Master
 */

class PurgePermalinkTest extends WP_UnitTestCase {

	public function test_false_permalink_aborts() {
		$this->assertFalse( scm_purge_permalink( false ) );
	}

	public function test_empty_permalink_aborts() {
		$this->assertFalse( scm_purge_permalink( '' ) );
	}

	public function test_homepage_permalink_is_accepted() {
		$this->assertNotFalse( scm_purge_permalink( home_url( '/' ) ) );
	}
}
