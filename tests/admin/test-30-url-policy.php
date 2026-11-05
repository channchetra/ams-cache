<?php
/**
 * Class UrlPolicyTest
 *
 * @package KHSE_Cache
 */

class UrlPolicyTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Build a runtime config matching khse_get_config_from_schema() shape.
	 *
	 * @param array $exclusion Exclusion overrides.
	 *
	 * @return array
	 */
	private function config( $exclusion = array() ) {
		return array(
			'cache_enabled' => true,
			'cache_driver'  => 'file',
			'exclusion'     => array_merge(
				array(
					'enable'            => false,
					'excluded_list'     => array(),
					'excluded_get_vars' => array(),
				),
				$exclusion
			),
		);
	}

	/**
	 * Find one check entry by key.
	 *
	 * @param array  $result Policy result.
	 * @param string $key    Check key.
	 *
	 * @return array
	 */
	private function check( $result, $key ) {
		foreach ( $result['checks'] as $check ) {
			if ( $key === $check['key'] ) {
				return $check;
			}
		}

		$this->fail( 'Missing policy check: ' . $key );
	}

	public function test_relative_path_is_cacheable_when_clean() {
		$result = khse_evaluate_cache_url_policy( '/about/', $this->config() );

		$this->assertTrue( $result['cacheable'] );
		$this->assertSame( home_url( '/about/' ), $result['url'] );
		$this->assertSame( '/about/', $result['path'] );
		$this->assertSame( 'This URL will be served from cache.', $result['summary'] );
		$this->assertSame( 'pass', $this->check( $result, 'request' )['status'] );
		$this->assertSame( 'pass', $this->check( $result, 'caching' )['status'] );
		$this->assertSame( 'pass', $this->check( $result, 'permalink' )['status'] );
		$this->assertCount( 14, $result['checks'] );
	}

	public function test_fragment_url_is_rejected() {
		$result = khse_evaluate_cache_url_policy( home_url( '/about/#section' ), $this->config() );

		$this->assertFalse( $result['cacheable'] );
	}

	public function test_full_same_site_url_is_cacheable() {
		$url    = home_url( '/sample-page/' );
		$result = khse_evaluate_cache_url_policy( $url, $this->config() );

		$this->assertTrue( $result['cacheable'] );
		$this->assertSame( $url, $result['url'] );
		$this->assertSame( '/sample-page/', $result['path'] );
	}

	public function test_external_host_is_rejected() {
		$result = khse_evaluate_cache_url_policy( 'https://external.example.org/about/', $this->config() );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'request' )['status'] );
		$this->assertSame( 'This URL bypasses the page cache.', $result['summary'] );
	}

	public function test_unparseable_url_fails_closed() {
		$result = khse_evaluate_cache_url_policy( 'http://', $this->config() );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'request' )['status'] );
	}

	public function test_bypass_paths_are_rejected() {
		foreach ( array( '/wp-admin/', '/cart/', '/checkout/', '/my-account/' ) as $path ) {
			$result = khse_evaluate_cache_url_policy( $path, $this->config() );

			$this->assertFalse( $result['cacheable'], $path );
			$this->assertSame( 'fail', $this->check( $result, 'bypass_path' )['status'], $path );
		}
	}

	public function test_non_document_asset_path_is_rejected() {
		$result = khse_evaluate_cache_url_policy( '/assets/app.css', $this->config() );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'document_path' )['status'] );
	}

	public function test_non_tracking_query_var_fails() {
		$result = khse_evaluate_cache_url_policy( '/sample-page/?page=2', $this->config() );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'query' )['status'] );
		$this->assertSame( '/sample-page/?page=2', $result['path'] );
	}

	public function test_tracking_only_query_passes() {
		$result = khse_evaluate_cache_url_policy(
			'/sample-page/?utm_source=newsletter&fbclid=abc123',
			$this->config()
		);

		$this->assertTrue( $result['cacheable'] );
		$this->assertSame( 'pass', $this->check( $result, 'query' )['status'] );
		$this->assertSame( '/sample-page/?utm_source=newsletter&fbclid=abc123', $result['path'] );
	}

	public function test_exclusion_path_matches_when_rules_enabled() {
		$config = $this->config(
			array(
				'enable'        => true,
				'excluded_list' => array( '/private' ),
			)
		);
		$result = khse_evaluate_cache_url_policy( '/private/page/', $config );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'exclusion_path' )['status'] );
	}

	public function test_disabled_rules_ignore_exclusions() {
		$config = $this->config(
			array(
				'enable'        => false,
				'excluded_list' => array( '/private' ),
			)
		);
		$result = khse_evaluate_cache_url_policy( '/private/page/', $config );

		$this->assertTrue( $result['cacheable'] );
		$this->assertSame( 'note', $this->check( $result, 'exclusion_path' )['status'] );
	}

	public function test_excluded_get_var_fails() {
		$config = $this->config(
			array(
				'enable'            => true,
				'excluded_get_vars' => array( 'preview' ),
			)
		);
		$result = khse_evaluate_cache_url_policy( '/sample-page/?preview=1', $config );

		$this->assertFalse( $result['cacheable'] );
		$this->assertSame( 'fail', $this->check( $result, 'exclusion_get' )['status'] );
	}
}
