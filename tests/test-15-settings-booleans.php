<?php
/**
 * Class SettingsBooleansTest
 *
 * @package Cache_Master
 */

class SettingsBooleansTest extends WP_UnitTestCase {

	public function test_accessor_reads_both_vocabularies() {
		update_option( 'scm_test_bool_a', 'yes' );
		update_option( 'scm_test_bool_b', 'enable' );
		update_option( 'scm_test_bool_c', 'no' );
		update_option( 'scm_test_bool_d', 'disable' );
		update_option( 'scm_test_bool_e', true );

		$this->assertTrue( scm_option_is_enabled( 'scm_test_bool_a' ) );
		$this->assertTrue( scm_option_is_enabled( 'scm_test_bool_b' ) );
		$this->assertFalse( scm_option_is_enabled( 'scm_test_bool_c' ) );
		$this->assertFalse( scm_option_is_enabled( 'scm_test_bool_d' ) );
		$this->assertTrue( scm_option_is_enabled( 'scm_test_bool_e' ) );

		delete_option( 'scm_test_bool_missing' );
		$this->assertTrue( scm_option_is_enabled( 'scm_test_bool_missing', 'yes' ) );
		$this->assertFalse( scm_option_is_enabled( 'scm_test_bool_missing' ) );
	}

	public function test_accessor_writes_the_requested_vocabulary() {
		scm_option_set_enabled( 'scm_test_bool_f', true, 'enable_disable' );
		scm_option_set_enabled( 'scm_test_bool_g', false, 'yes_no' );

		$this->assertSame( 'enable', get_option( 'scm_test_bool_f' ) );
		$this->assertSame( 'no', get_option( 'scm_test_bool_g' ) );
	}

	public function test_dashboard_payload_toggles_are_booleans() {
		$data = scm_dashboard_get_settings_data();

		foreach ( array( 'cachingStatus', 'ttlMechanism', 'expertModeStatus', 'nginxDirect', 'debugComment' ) as $key ) {
			$this->assertIsBool( $data['cache'][ $key ] );
		}

		foreach ( array( 'enabled', 'crawlHomepage', 'homepage' ) as $key ) {
			$this->assertIsBool( $data['preload'][ $key ] );
		}

		$this->assertIsBool( $data['rules']['enabled'] );
		$this->assertIsBool( $data['statistics']['enabled'] );
		$this->assertIsBool( $data['benchmark']['widget'] );
		$this->assertIsBool( $data['benchmark']['footer'] );
		$this->assertIsBool( $data['woocommerce']['enabled'] );
		$this->assertIsBool( $data['woocommerce']['paymentComplete'] );

		foreach ( array( 'status', 'minify_html', 'remove_comments', 'minify_inline_css', 'lazy_media', 'critical_images', 'preconnect_fonts', 'defer_js', 'external_ucss', 'local_ucss', 'js_analysis' ) as $key ) {
			$this->assertIsBool( $data['performance'][ $key ] );
		}
	}

	public function test_runtime_exclusion_config_shape() {
		$config = scm_get_runtime_exclusion_config();

		foreach ( array( 'enable', 'excluded_list', 'excluded_get_vars', 'excluded_post_vars', 'excluded_cookie_vars' ) as $key ) {
			$this->assertArrayHasKey( $key, $config );
		}

		$this->assertIsBool( $config['enable'] );
		$this->assertIsArray( $config['excluded_list'] );
		$this->assertIsArray( $config['excluded_get_vars'] );
		$this->assertIsArray( $config['excluded_post_vars'] );
		$this->assertIsArray( $config['excluded_cookie_vars'] );
	}
}
