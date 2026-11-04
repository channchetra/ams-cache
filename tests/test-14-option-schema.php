<?php
/**
 * Class OptionSchemaTest
 *
 * @package KHSE_Cache
 */

class OptionSchemaTest extends WP_UnitTestCase {

	public function test_runtime_keys_are_seeded() {
		$keys = khse_get_option_keys();

		foreach ( array( 'excluded_get_vars', 'excluded_post_vars', 'excluded_cookie_vars', 'exclusion_status', 'ttl_mechanism', 'woocommerce_post_archives' ) as $key ) {
			$this->assertContains( $key, $keys );
		}

		$this->assertNotContains( 'excluded_get_variables', $keys );
		$this->assertNotContains( 'excluded_post_variables', $keys );
		$this->assertNotContains( 'excluded_cookie_variables', $keys );
		$this->assertNotContains( 'woocommerce_taxonomies', $keys );
	}

	public function test_key_list_matches_schema() {
		$this->assertSame( array_keys( khse_get_option_schema() ), khse_get_option_keys() );
	}
}
