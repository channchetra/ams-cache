<?php
/**
 * Class CachePolicyParityTest
 *
 * @package KHSE_Cache
 */

class CachePolicyParityTest extends WP_UnitTestCase {

	/**
	 * Original cookie superglobal.
	 *
	 * @var array
	 */
	private $original_cookie = array();

	/**
	 * Whether REQUEST_METHOD existed before set_up().
	 *
	 * @var bool
	 */
	private $had_request_method = false;

	/**
	 * Original REQUEST_METHOD value.
	 *
	 * @var string|null
	 */
	private $original_request_method = null;

	public function set_up() {
		parent::set_up();

		$this->original_cookie = $_COOKIE;

		$this->had_request_method      = array_key_exists( 'REQUEST_METHOD', $_SERVER );
		$this->original_request_method = $this->had_request_method ? $_SERVER['REQUEST_METHOD'] : null;
		$_SERVER['REQUEST_METHOD']     = 'GET';
	}

	public function tear_down() {
		$_COOKIE = $this->original_cookie;

		if ( $this->had_request_method ) {
			$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
		} else {
			unset( $_SERVER['REQUEST_METHOD'] );
		}

		parent::tear_down();
	}

	public function test_cookie_prefixes_bypass_the_cache() {
		foreach ( khse_get_cache_bypass_cookie_prefixes() as $prefix ) {
			$_COOKIE = array( $prefix . 'probe' => '1' );

			$this->assertTrue( khse_request_has_cache_bypass_cookie(), $prefix );
			$this->assertFalse( khse_is_cacheable_request_uri( '/' ), $prefix );
		}

		$_COOKIE = array( 'unrelated_cookie' => '1' );
		$this->assertFalse( khse_request_has_cache_bypass_cookie() );
	}

	public function test_runtime_and_admin_exclusions_agree_on_prefix_boundaries() {
		$config = array(
			'exclusion' => array(
				'enable'        => true,
				'excluded_list' => array( '/news/' ),
			),
		);

		$_SERVER['REQUEST_URI'] = '/newsletter/';

		$this->assertFalse( khse_is_cache_request_excluded( $config ) );
		$this->assertSame( '', khse_cache_policy_find_excluded_path( '/newsletter/', array( 'excluded_list' => array( '/news' ) ) ) );

		$_SERVER['REQUEST_URI'] = '/news/2026/';

		$this->assertTrue( khse_is_cache_request_excluded( $config ) );
		$this->assertSame( '/news/', khse_cache_policy_find_excluded_path( '/news/2026/', array( 'excluded_list' => array( '/news' ) ) ) );
	}

	public function test_bypass_paths_are_rejected() {
		foreach ( khse_get_cache_bypass_paths() as $path ) {
			$this->assertFalse( khse_is_cacheable_document_path( '/' . $path ), $path );
			$this->assertFalse( khse_is_cacheable_document_path( '/' . $path . '/' ), $path );
		}

		$this->assertTrue( khse_is_cacheable_document_path( '/sample-page/' ) );
		$this->assertTrue( khse_is_cacheable_document_path( '/' ) );
	}

	public function test_nginx_snippet_contains_the_shared_policy() {
		$snippet = khse_get_nginx_direct_cache_snippet();

		foreach ( khse_get_cache_bypass_cookie_prefixes() as $prefix ) {
			$this->assertStringContainsString( preg_quote( $prefix, '/' ), $snippet, $prefix );
		}

		foreach ( khse_get_cache_bypass_paths() as $path ) {
			$this->assertStringContainsString( preg_quote( $path, '/' ), $snippet, $path );
		}
	}
}
