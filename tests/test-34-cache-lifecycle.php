<?php
/**
 * Class CacheLifecycleTest
 *
 * @package KHSE_Cache
 */

class CacheLifecycleTest extends WP_UnitTestCase {

	public function tear_down() {
		$stats_dir = khse_get_stats_dir( 'post' );

		if ( is_dir( $stats_dir ) ) {
			foreach ( glob( $stats_dir . '/*.json' ) as $file ) {
				@unlink( $file );
			}
		}

		parent::tear_down();
	}

	public function test_purge_removes_the_driver_entry_and_stats_row() {
		$stats_dir = khse_get_stats_dir( 'post' );
		wp_mkdir_p( $stats_dir );

		$key      = khse_get_cache_key( '/lifecycle-test/' );
		$stats    = $stats_dir . '/' . $key . '.json';
		$driver   = new class() {
			public $deleted = array();

			public function delete( $key ) {
				$this->deleted[] = $key;
				return true;
			}
		};

		file_put_contents(
			$stats,
			wp_json_encode(
				array(
					'uri'  => '/lifecycle-test/',
					'size' => 128,
				)
			)
		);

		$result = khse_purge_cache_uri( '/lifecycle-test/', $driver );

		$this->assertSame( $key, $result['key'] );
		$this->assertContains( $key, $driver->deleted );
		$this->assertFileDoesNotExist( $stats );
		$this->assertGreaterThanOrEqual( 1, $result['removed'] );
	}

	public function test_purge_skips_non_document_paths() {
		$result = khse_purge_cache_uri( '/assets/app.css', new class() {
			public function delete( $key ) {
				return true;
			}
		} );

		$this->assertSame( 0, $result['removed'] );
		$this->assertSame( '', $result['key'] );
	}
}
