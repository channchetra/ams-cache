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

	public function test_widget_registration_runs_before_widgets_init() {
		$this->assertNotFalse( has_action( 'plugins_loaded', 'khse_add_benchmark_widget' ) );
	}

	public function test_cache_buffer_attaches_to_a_hook_after_runtime_boot() {
		$this->assertNotFalse( has_action( 'plugins_loaded', 'khse_boot_cache_runtime' ) );

		$_SERVER['REQUEST_URI'] = '/';

		$cache = new KHSE_Cache();
		$cache->init();

		$this->assertFalse( has_action( 'plugins_loaded', array( $cache, 'ob_start' ) ), 'The buffer must not attach to an already-fired hook.' );
		$this->assertNotFalse( has_action( 'template_redirect', array( $cache, 'ob_start' ) ) );
	}

	public function test_cache_foundation_modules_own_their_functions() {
		$expected = array(
			'khse_get_setting'                  => 'inc/cache/cache-settings.php',
			'khse_get_default_config'           => 'inc/cache/cache-settings.php',
			'khse_get_cache_key_prefix'         => 'inc/cache/cache-settings.php',
			'khse_get_blog_id'                  => 'inc/cache/cache-identity.php',
			'khse_get_dir_hash'                 => 'inc/cache/cache-identity.php',
			'khse_build_runtime_exclusion_config' => 'inc/cache/cache-storage.php',
			'khse_serve_cached_response'        => 'inc/cache/cache-policy.php',
			'khse_get_cache_key'                => 'inc/cache/cache-policy.php',
			'khse_update_config'                => 'inc/cache/cache-storage.php',
			'khse_sync_expert_mode_runtime'     => 'inc/cache/cache-storage.php',
			'khse_get_stats_dir'                => 'inc/cache/cache-storage.php',
			'khse_get_driver_settings'          => 'inc/cache/cache-storage.php',
		);

		foreach ( $expected as $function => $file ) {
			$reflection = new ReflectionFunction( $function );

			$this->assertStringEndsWith( $file, $reflection->getFileName(), $function );
		}

		$core = file_get_contents( KHSE_PLUGIN_DIR . 'inc/cache/cache-core.php' );

		foreach ( array( 'cache-identity.php', 'cache-settings.php', 'cache-storage.php', 'cache-policy.php' ) as $module ) {
			$this->assertStringContainsString( $module, $core, 'cache-core.php must load ' . $module );
		}
	}
}
