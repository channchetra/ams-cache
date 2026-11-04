<?php
/**
 * Class BootLayoutTest
 *
 * @package Cache_Master
 */

class BootLayoutTest extends WP_UnitTestCase {

	public function test_request_path_hooks_live_outside_admin() {
		$this->assertFileExists( SCM_PLUGIN_DIR . 'inc/update-post.php' );
		$this->assertFileExists( SCM_PLUGIN_DIR . 'inc/admin-bar.php' );
		$this->assertFileDoesNotExist( SCM_PLUGIN_DIR . 'inc/admin/update-post.php' );
		$this->assertFileDoesNotExist( SCM_PLUGIN_DIR . 'inc/admin/admin-bar.php' );
	}

	public function test_runtime_config_helpers_live_in_core() {
		foreach ( array( 'scm_get_runtime_exclusion_config', 'scm_update_config', 'scm_sync_expert_mode_runtime' ) as $function ) {
			$reflection = new ReflectionFunction( $function );

			$this->assertStringEndsWith( 'inc/cache-core.php', $reflection->getFileName(), $function );
		}
	}
}
