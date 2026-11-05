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

	public function test_entry_limit_evicts_the_oldest_stats_row() {
		$stats_dir = khse_get_stats_dir( 'post' );
		wp_mkdir_p( $stats_dir );

		$old_key = khse_get_cache_key( '/evict-old/' );
		$new_key = khse_get_cache_key( '/evict-new/' );
		$old     = $stats_dir . '/' . $old_key . '.json';
		$new     = $stats_dir . '/' . $new_key . '.json';
		$driver  = new class() {
			public $deleted = array();

			public function delete( $key ) {
				$this->deleted[] = $key;
				return true;
			}
		};

		file_put_contents( $old, wp_json_encode( array( 'uri' => '/evict-old/', 'size' => 10 ) ) );
		file_put_contents( $new, wp_json_encode( array( 'uri' => '/evict-new/', 'size' => 10 ) ) );
		touch( $old, time() - 100 );

		update_option( 'khse_option_cache_max_entries', 1 );

		$removed = khse_enforce_cache_entry_limit( $driver );

		$this->assertSame( 1, $removed );
		$this->assertFileDoesNotExist( $old );
		$this->assertFileExists( $new );
		$this->assertContains( $old_key, $driver->deleted );

		delete_option( 'khse_option_cache_max_entries' );
	}

	public function test_corrupt_stats_file_is_ignored() {
		$stats_dir = khse_get_stats_dir( 'post' );
		wp_mkdir_p( $stats_dir );
		$file = $stats_dir . '/corrupt.json';
		file_put_contents( $file, '{broken' );

		$this->assertNull( khse_read_stats_file( $file ) );
		$this->assertSame( 0, khse_dashboard_get_stats_for_type( 'post' )['rows'] );

		@unlink( $file );
	}

	public function test_purge_does_not_count_failed_driver_deletes() {
		$result = khse_purge_cache_uri( '/failed-delete/', new class() {
			public function delete( $key ) {
				return false;
			}
		} );

		$this->assertSame( 0, $result['removed'] );
	}

	public function test_purge_rejects_unparseable_uris() {
		$result = khse_purge_cache_uri( '//?p=1' );

		$this->assertSame( '', $result['key'] );
		$this->assertSame( 0, $result['removed'] );
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
