<?php
/**
 * Class AjaxGuardTest
 *
 * @package KHSE_Cache
 */

$khse_tests_dir = getenv( 'WP_TESTS_DIR' );
$khse_tests_dir = $khse_tests_dir ? $khse_tests_dir : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
require_once $khse_tests_dir . '/includes/testcase-ajax.php';

class AjaxGuardTest extends WP_Ajax_UnitTestCase {

	private function nonce() {
		return wp_create_nonce( 'khse_dashboard_' . khse_get_dir_hash() );
	}

	private function response() {
		return json_decode( $this->_last_response, true );
	}

	public function test_test_url_rejects_a_missing_nonce() {
		$this->_setRole( 'administrator' );
		$_POST['url'] = '/';

		try {
			$this->_handleAjax( 'khse_action_dashboard_test_url' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_error ends the AJAX request.
		}

		$response = $this->response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Token has been rejected', $response['data']['message'] );
	}

	public function test_test_url_returns_policy_for_an_administrator() {
		$this->_setRole( 'administrator' );
		$_POST['_wpnonce'] = $this->nonce();
		$_POST['url']      = '/';

		try {
			$this->_handleAjax( 'khse_action_dashboard_test_url' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success ends the AJAX request.
		}

		$response = $this->response();

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'cacheable', $response['data'] );
		$this->assertArrayHasKey( 'checks', $response['data'] );
		$this->assertCount( 10, $response['data']['checks'] );
	}

	public function test_test_url_rejects_a_subscriber() {
		$this->_setRole( 'subscriber' );
		$_POST['_wpnonce'] = $this->nonce();
		$_POST['url']      = '/';

		try {
			$this->_handleAjax( 'khse_action_dashboard_test_url' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_error ends the AJAX request.
		}

		$response = $this->response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Access denied', $response['data']['message'] );
	}

	public function test_test_url_rejects_empty_input() {
		$this->_setRole( 'administrator' );
		$_POST['_wpnonce'] = $this->nonce();
		$_POST['url']      = '';

		try {
			$this->_handleAjax( 'khse_action_dashboard_test_url' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_error ends the AJAX request.
		}

		$response = $this->response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Enter a URL or path', $response['data']['message'] );
	}

	public function test_nginx_snippet_returns_the_generated_block() {
		$this->_setRole( 'administrator' );
		$_POST['_wpnonce'] = $this->nonce();

		try {
			$this->_handleAjax( 'khse_action_dashboard_nginx_snippet' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success ends the AJAX request.
		}

		$response = $this->response();

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'khse_cache_file', $response['data']['snippet'] );
		$this->assertArrayHasKey( 'enabled', $response['data'] );
	}
}
