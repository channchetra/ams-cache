<?php
/**
 * Class ActivationTest
 *
 * @package KHSE_Cache
 */

/**
 * Activation test case.
 */
class ActivationTest extends WP_UnitTestCase {

	/**
	 * Activate plugin.
	 *
	 * Dirctories for File and SQLite driver are supposed to be created when
	 * activating plugin.
	 *
	 * @return void
	 */
	public function testActivateWithSupport() {

		do_action( 'activate_' . KHSE_PLUGIN_NAME );

		$this->assertTrue( ( file_exists( khse_get_upload_dir() . '/file_driver' ) ) );
		$this->assertTrue( ( file_exists( khse_get_upload_dir() . '/sqlite_driver' ) ) );
	}
}
