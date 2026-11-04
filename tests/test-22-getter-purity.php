<?php
/**
 * Class GetterPurityTest
 *
 * @package Cache_Master
 */

class GetterPurityTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'scm_dir_hash' );
		parent::tear_down();
	}

	public function test_dir_hash_getter_is_pure_and_ensure_persists() {
		delete_option( 'scm_dir_hash' );

		$hash = scm_get_dir_hash();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{8}$/', $hash );
		$this->assertFalse( get_option( 'scm_dir_hash' ) );

		$this->assertSame( $hash, scm_ensure_dir_hash() );
		$this->assertSame( $hash, get_option( 'scm_dir_hash' ) );
	}

	public function test_optimization_requirements_do_not_create_work_dir() {
		$dir    = scm_get_page_optimization_work_dir();
		$backup = $dir . '.bak-' . uniqid();
		$had_dir = is_dir( $dir );

		if ( $had_dir ) {
			$this->assertTrue( rename( $dir, $backup ) );
		}

		$requirements = scm_get_page_optimization_requirements();

		$this->assertFalse( is_dir( $dir ) );
		$this->assertFalse( $requirements['work_dir']['passed'] );

		if ( $had_dir ) {
			rename( $backup, $dir );
		}
	}

	public function test_plain_permalinks_disable_caching() {
		$this->set_permalink_structure( '' );
		update_option( 'scm_option_caching_status', 'enable' );

		scm_ensure_permalink_structure();

		$this->assertSame( 'disable', get_option( 'scm_option_caching_status' ) );
	}

	public function test_pretty_permalinks_leave_caching_unchanged() {
		$this->set_permalink_structure( '/%postname%/' );
		update_option( 'scm_option_caching_status', 'enable' );

		scm_ensure_permalink_structure();

		$this->assertSame( 'enable', get_option( 'scm_option_caching_status' ) );
	}

	public function test_driver_storage_is_ensured_explicitly() {
		$storage = scm_get_upload_dir() . '/file_driver';
		$backup  = $storage . '.bak-' . uniqid();
		$had_dir = is_dir( $storage );

		if ( $had_dir ) {
			$this->assertTrue( rename( $storage, $backup ) );
		}

		scm_ensure_driver_storage( 'file' );

		$this->assertDirectoryExists( $storage );

		if ( $had_dir ) {
			@rmdir( $storage );
			rename( $backup, $storage );
		} else {
			@rmdir( $storage );
		}
	}
}
