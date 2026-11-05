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

	public function test_runtime_modules_live_outside_the_admin_package() {
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/bootstrap.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/helpers.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/widgets.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/widgets.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/class-khse-benchmark-widget.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/class-khse-benchmark-widget.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/option-schema.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/option-schema.php' );
	}

	public function test_admin_support_modules_are_split() {
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/admin/dashboard-data.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/admin/driver-tools.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/admin/expert-mode-snippet.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/admin/functions.php' );
	}

	public function test_runtime_packages_own_preload_and_nginx_modules() {
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/preload/cache-preload.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/nginx/cache-nginx.php' );
		$this->assertFileExists( KHSE_PLUGIN_DIR . 'inc/nginx/cache-nginx-config.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/cache-preload.php' );
		$this->assertFileDoesNotExist( KHSE_PLUGIN_DIR . 'inc/cache-nginx.php' );
	}

	public function test_runtime_config_helpers_live_in_core() {
		foreach ( array( 'khse_get_runtime_exclusion_config', 'khse_update_config', 'khse_sync_expert_mode_runtime' ) as $function ) {
			$reflection = new ReflectionFunction( $function );

			$this->assertStringEndsWith( 'inc/cache-core.php', $reflection->getFileName(), $function );
		}
	}
}
