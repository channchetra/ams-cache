<?php
/**
 * Class NginxMirrorTest
 *
 * @package KHSE_Cache
 */

class NginxMirrorTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'khse_option_driver', 'file' );
		update_option( 'khse_option_nginx_direct_cache_status', 'yes' );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/nginx-mirror-test/';
	}

	public function tear_down() {
		khse_clear_nginx_static_cache();
		delete_option( 'khse_option_nginx_direct_cache_status' );
		delete_option( 'khse_option_driver' );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/';
		parent::tear_down();
	}

	public function test_mirror_writes_and_deletes_the_static_file() {
		$html = '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>';
		$path = khse_get_nginx_static_cache_path( '/nginx-mirror-test/' );

		$this->assertNotSame( '', $path );
		$this->assertTrue( khse_write_nginx_static_cache( '/nginx-mirror-test/', $html ) );
		$this->assertFileExists( $path );
		$this->assertSame( $html, file_get_contents( $path ) );
		$this->assertTrue( khse_delete_nginx_static_cache( '/nginx-mirror-test/' ) );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_mirror_rejects_path_traversal() {
		$this->assertSame( '', khse_get_nginx_static_cache_path( '/../outside/' ) );
		$this->assertFalse( khse_write_nginx_static_cache( '/../outside/', '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>' ) );
	}

	public function test_mirror_is_disabled_when_direct_cache_is_off() {
		update_option( 'khse_option_nginx_direct_cache_status', 'no' );

		$this->assertFalse( khse_write_nginx_static_cache( '/nginx-mirror-test/', '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>' ) );
	}
}
