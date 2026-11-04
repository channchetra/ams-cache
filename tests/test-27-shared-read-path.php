<?php
/**
 * Class SharedReadPathTest
 *
 * @package KHSE_Cache
 */

class SharedReadPathTest extends WP_UnitTestCase {

	private $storage;
	private $original_server;
	private $original_get;
	private $original_post;
	private $original_cookie;

	public function set_up() {
		parent::set_up();

		$this->original_server = $_SERVER;
		$this->original_get    = $_GET;
		$this->original_post   = $_POST;
		$this->original_cookie = $_COOKIE;

		$this->storage = sys_get_temp_dir() . '/khse-shared-read-' . uniqid();
		wp_mkdir_p( $this->storage );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/shared-read-test/';
		$_GET                      = array();
		$_POST                     = array();
		$_COOKIE                   = array();
	}

	public function tear_down() {
		$_SERVER = $this->original_server;
		$_GET    = $this->original_get;
		$_POST   = $this->original_post;
		$_COOKIE = $this->original_cookie;

		if ( is_dir( $this->storage ) ) {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $this->storage, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $files as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}

			rmdir( $this->storage );
		}

		parent::tear_down();
	}

	private function config( $overrides = array() ) {
		return array_merge(
			array(
				'cache_enabled'             => true,
				'cache_driver'              => 'file',
				'cache_key_prefix'          => 'test_prefix_',
				'driver_advanced_settings'  => array(),
				'driver_connection_type'    => 'tcp',
				'html_debug_comment'        => true,
				'exclusion'                 => array(
					'enable'               => false,
					'excluded_list'        => array(),
					'excluded_get_vars'    => array(),
					'excluded_post_vars'   => array(),
					'excluded_cookie_vars' => array(),
				),
				'woocommerce'               => array( 'enable' => false ),
			),
			$overrides
		);
	}

	private function seed( $content, $uri = '/shared-read-test/' ) {
		$driver = new \Shieldon\SimpleCache\Cache( 'file', array( 'storage' => $this->storage ) );
		$driver->set( khse_build_cache_key( 'test_prefix_', $uri ), $content, 300 );

		return $driver;
	}

	private function valid_content() {
		return '<html><body>' . str_repeat( 'x', 1200 ) . '</body></html>';
	}

	public function test_serves_valid_cache_entry_with_debug_and_js() {
		$driver = $this->seed( $this->valid_content() );

		$served = khse_serve_cached_response( $this->config(), $driver );

		$this->assertIsString( $served );
		$this->assertStringContainsString( 'kh-speed-engine-plugin', $served );
		$this->assertStringContainsString( 'After', $served );
	}

	public function test_skips_when_cache_disabled() {
		$driver = $this->seed( $this->valid_content() );

		$this->assertNull( khse_serve_cached_response( $this->config( array( 'cache_enabled' => false ) ), $driver ) );
	}

	public function test_skips_excluded_request() {
		$_SERVER['REQUEST_URI'] = '/blocked/page/';
		$driver = $this->seed( $this->valid_content(), '/blocked/page/' );
		$config = $this->config(
			array(
				'exclusion' => array(
					'enable'               => true,
					'excluded_list'        => array( '/blocked' ),
					'excluded_get_vars'    => array(),
					'excluded_post_vars'   => array(),
					'excluded_cookie_vars' => array(),
				),
			)
		);

		$this->assertNull( khse_serve_cached_response( $config, $driver ) );
	}

	public function test_skips_woocommerce_add_to_cart() {
		$_POST['add-to-cart'] = '1';
		$driver = $this->seed( $this->valid_content() );

		$this->assertNull( khse_serve_cached_response( $this->config( array( 'woocommerce' => array( 'enable' => true ) ) ), $driver ) );
	}

	public function test_skips_invalid_payload() {
		$driver = $this->seed( 'too short' );

		$this->assertNull( khse_serve_cached_response( $this->config(), $driver ) );
	}

	public function test_debug_comment_can_be_disabled() {
		$driver = $this->seed( $this->valid_content() );

		$served = khse_serve_cached_response( $this->config( array( 'html_debug_comment' => false ) ), $driver );

		$this->assertIsString( $served );
		$this->assertStringContainsString( 'kh-speed-engine-plugin', $served );
		$this->assertStringNotContainsString( 'After', $served );
	}

	public function test_expert_debug_marks_expert_mode() {
		$message = khse_build_served_debug_message( $this->config(), true, microtime( true ) );

		$this->assertStringContainsString( '(running in Expert Mode)', $message );
	}
}
