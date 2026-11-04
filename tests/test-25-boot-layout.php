<?php
/**
 * Class BootLayoutTest
 *
 * @package KHSE_Cache
 */

class BootLayoutTest extends WP_UnitTestCase {

	public function test_request_path_hooks_live_outside_admin() {
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/update-post.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/admin-bar.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/update-post.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/admin-bar.php' );
	}

	public function test_runtime_config_helpers_live_in_core() {
		foreach ( array( 'khse_get_runtime_exclusion_config', 'khse_update_config', 'khse_sync_expert_mode_runtime' ) as $function ) {
			$reflection = new ReflectionFunction( $function );

			$this->assertStringEndsWith( 'inc/cache-core.php', $reflection->getFileName(), $function );
		}
	}
}
