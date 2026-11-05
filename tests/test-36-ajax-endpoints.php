<?php
/**
 * Class AjaxEndpointsTest
 *
 * @package KHSE_Cache
 */

$khse_tests_dir = getenv( 'WP_TESTS_DIR' );
$khse_tests_dir = $khse_tests_dir ? $khse_tests_dir : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
require_once $khse_tests_dir . '/includes/testcase-ajax.php';

class AjaxEndpointsTest extends WP_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'khse_option_caching_status', 'enable' );
		update_option( 'khse_option_preload_cache', 'yes' );
	}

	public function tear_down() {
		delete_option( 'khse_option_ttl' );
		delete_option( 'khse_option_caching_status' );
		delete_option( 'khse_option_preload_cache' );
		parent::tear_down();
	}

	private function authenticate() {
		$this->_setRole( 'administrator' );
		$_POST['_wpnonce'] = wp_create_nonce( 'khse_dashboard_' . khse_get_dir_hash() );
	}

	private function dispatch( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_* ends the AJAX request.
		}

		return json_decode( $this->_last_response, true );
	}

	public function test_save_settings_persists_and_preserves_omitted_options() {
		update_option( 'khse_option_caching_status', 'enable' );
		$this->authenticate();
		$_POST['settings'] = wp_json_encode( array( 'cache' => array( 'ttl' => 7200 ) ) );

		$response = $this->dispatch( 'khse_action_dashboard_save_settings' );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['runtimeSynced'] );
		$this->assertSame( 7200, (int) get_option( 'khse_option_ttl' ) );
		$this->assertSame( 'enable', get_option( 'khse_option_caching_status' ) );
		$this->assertSame( 7200, (int) $response['data']['status']['settings']['cache']['ttl'] );
	}

	public function test_clear_all_cache_returns_a_row_count() {
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_clear_cache' );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'rows', $response['data'] );
		$this->assertArrayHasKey( 'status', $response['data'] );
	}

	public function test_status_endpoint_returns_cache_state() {
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_status' );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'cache', $response['data']['status'] );
		$this->assertArrayHasKey( 'environment', $response['data']['status'] );
	}

	public function test_file_driver_connection_test_passes() {
		$this->authenticate();
		$_POST['driver'] = 'file';

		$response = $this->dispatch( 'khse_action_dashboard_test_driver' );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['driverTest']['passed'] );
	}

	public function test_reports_endpoint_returns_pagination_fields() {
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_reports' );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'reports', $response['data'] );
		$this->assertArrayHasKey( 'loadedCount', $response['data'] );
		$this->assertArrayHasKey( 'totalReports', $response['data'] );
		$this->assertArrayHasKey( 'hasMore', $response['data'] );
	}

	public function test_preload_endpoint_returns_a_queue_count() {
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_preload' );

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'preload URLs queued', $response['data']['message'] );
		$this->assertArrayHasKey( 'status', $response['data'] );
	}

	public function test_preload_endpoint_rejects_when_caching_is_disabled() {
		update_option( 'khse_option_preload_cache', 'yes' );
		update_option( 'khse_option_caching_status', 'disable' );
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_preload' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Enable page caching', $response['data']['message'] );

		update_option( 'khse_option_caching_status', 'enable' );
	}

	public function test_purge_homepage_endpoint_returns_success() {
		$this->authenticate();

		$response = $this->dispatch( 'khse_action_dashboard_purge_homepage' );

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'Homepage cache purged', $response['data']['message'] );
		$this->assertArrayHasKey( 'status', $response['data'] );
	}

	public function test_clear_type_rejects_an_unknown_type() {
		$this->authenticate();
		$_POST['cacheType'] = 'not-a-cache-type';

		$response = $this->dispatch( 'khse_action_dashboard_clear_cache_type' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Unknown cache type', $response['data']['message'] );
	}
}
