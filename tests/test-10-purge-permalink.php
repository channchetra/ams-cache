<?php
/**
 * Class PurgePermalinkTest
 *
 * @package KHSE_Cache
 */

class PurgePermalinkTest extends WP_UnitTestCase {

	public function test_false_permalink_aborts() {
		$this->assertFalse( khse_purge_permalink( false ) );
	}

	public function test_empty_permalink_aborts() {
		$this->assertFalse( khse_purge_permalink( '' ) );
	}

	public function test_homepage_permalink_is_accepted() {
		$this->assertNotFalse( khse_purge_permalink( home_url( '/' ) ) );
	}
}
