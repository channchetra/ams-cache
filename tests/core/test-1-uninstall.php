<?php
/**
 * Class ActivationTest
 *
 * @package KHSE_Cache
 */

/**
 * Uninstall test case.
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Uninstall plugin.
	 *
	 * @return void
	 */
	public function testUninstallPlugin() {

		// Activate plugin again, create folders, and then we test uninstalling.
		$plugin = basename( untrailingslashit( KHSE_PLUGIN_DIR ) ) . '/kh-speed-engine.php';

		do_action( 'activate_' . KHSE_PLUGIN_NAME );

		update_option( 'khse_option_uninstall', 'yes' );

		$found = uninstall_plugin( $plugin );

		$this->assertTrue( $found );

		$this->assertFalse( ( file_exists( khse_get_upload_dir() . '/file_driver' ) ) );
		$this->assertFalse( ( file_exists( khse_get_upload_dir() . '/sqlite_driver' ) ) );
		$this->assertFalse( ( file_exists( khse_get_upload_dir() ) ) );
	}
}
