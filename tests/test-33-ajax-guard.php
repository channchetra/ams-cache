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
		$this->assertCount( 11, $response['data']['checks'] );
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

class PurgeCurrentPagePrivacyTest extends WP_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function test_author_purge_response_does_not_expose_admin_status() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $author_id );
		$_POST['_wpnonce'] = wp_create_nonce( 'khse_purge_current_page_' . khse_get_dir_hash() );
		$_POST['url']      = get_permalink( $post_id );

		try {
			$this->_handleAjax( 'khse_action_purge_current_page' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayNotHasKey( 'status', $response['data'] );
		$this->assertArrayHasKey( 'removed', $response['data'] );
	}
}
