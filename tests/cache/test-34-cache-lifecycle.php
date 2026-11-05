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

	public function test_cache_write_lifecycle_persists_payload_and_stats() {
		$_SERVER['REQUEST_URI'] = '/write-lifecycle/';
		update_option( 'khse_option_statistics_status', 'enable' );

		$driver = new class() {
			public $stored = array();

			public function set( $key, $value, $ttl = null ) {
				$this->stored[ $key ] = $value;
				return true;
			}

			public function get( $key ) {
				return isset( $this->stored[ $key ] ) ? $this->stored[ $key ] : false;
			}

			public function delete( $key ) {
				unset( $this->stored[ $key ] );
				return true;
			}
		};

		$cache     = new KHSE_Cache();
		$reflection = new ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'driver' );
		$property->setAccessible( true );
		$property->setValue( $cache, $driver );
		$resolved = $reflection->getProperty( 'driver_resolved' );
		$resolved->setAccessible( true );
		$resolved->setValue( $cache, true );

		$cache->is_cacheable  = true;
		$cache->cache_key = 'write-lifecycle';
		$cache->data_type = 'post';

		$payload = '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>';

		$this->assertTrue( $cache->persist_cache_payload( $payload ) );
		$this->assertSame( $payload, $driver->get( 'write-lifecycle' ) );

		$stats = khse_get_stats_dir( 'post' ) . '/write-lifecycle.json';
		$this->assertFileExists( $stats );

		@unlink( $stats );
		delete_option( 'khse_option_statistics_status' );
	}

	public function test_finalize_output_persists_and_returns_the_document() {
		$_SERVER['REQUEST_URI']    = '/finalize-output/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		update_option( 'khse_option_statistics_status', 'enable' );
		delete_option( 'khse_option_benchmark_footer_text' );

		$driver = new class() {
			public $stored = array();

			public function set( $key, $value, $ttl = null ) {
				$this->stored[ $key ] = $value;
				return true;
			}

			public function get( $key ) {
				return isset( $this->stored[ $key ] ) ? $this->stored[ $key ] : false;
			}

			public function delete( $key ) {
				unset( $this->stored[ $key ] );
				return true;
			}
		};

		$cache      = new KHSE_Cache();
		$reflection = new ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'driver' );
		$property->setAccessible( true );
		$property->setValue( $cache, $driver );
		$resolved = $reflection->getProperty( 'driver_resolved' );
		$resolved->setAccessible( true );
		$resolved->setValue( $cache, true );

		$cache->is_cacheable = true;
		$cache->cache_key    = 'finalize-output';
		$cache->data_type    = 'post';

		$payload = '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>';
		$result  = $cache->finalize_output( $payload );

		$this->assertStringContainsString( '</body>', $result );
		$this->assertArrayHasKey( 'finalize-output', $driver->stored );

		$stats = khse_get_stats_dir( 'post' ) . '/finalize-output.json';
		@unlink( $stats );
		delete_option( 'khse_option_statistics_status' );
	}

	public function test_cached_response_respects_guest_visibility() {
		$_SERVER['REQUEST_URI']    = '/gated-page/';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		update_option( 'khse_option_visibility_guest', 'no' );

		$driver = new class() {
			public function get( $key ) {
				return '<html><body>' . str_repeat( 'x', 1100 ) . '</body></html>';
			}
		};

		$config = array(
			'cache_enabled'    => true,
			'cache_key_prefix' => 'gate_',
		);

		$this->assertNull( khse_serve_cached_response( $config, $driver ) );

		update_option( 'khse_option_visibility_guest', 'yes' );

		$this->assertIsString( khse_serve_cached_response( $config, $driver ) );

		delete_option( 'khse_option_visibility_guest' );
	}

	public function test_clear_cache_type_removes_only_that_type() {
		update_option( 'khse_option_driver', 'file' );
		khse_ensure_upload_dir();

		$post_dir = khse_get_stats_dir( 'post' );
		$page_dir = khse_get_stats_dir( 'page' );
		wp_mkdir_p( $post_dir );
		wp_mkdir_p( $page_dir );

		$post_file = $post_dir . '/clear-type-post.json';
		$page_file = $page_dir . '/clear-type-page.json';

		file_put_contents( $post_file, wp_json_encode( array( 'uri' => '/clear-post/', 'size' => 10 ) ) );
		file_put_contents( $page_file, wp_json_encode( array( 'uri' => '/clear-page/', 'size' => 10 ) ) );

		khse_clear_cache_type( 'post' );

		$this->assertFileDoesNotExist( $post_file );
		$this->assertFileExists( $page_file );

		@unlink( $page_file );
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
