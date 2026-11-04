<?php
/**
 * Class ExpertModeCustomValueTest
 *
 * @package KHSE_Cache
 */

class ExpertModeCustomValueTest extends WP_UnitTestCase {

	public function tear_down() {
		putenv( 'WORDPRESS_CONFIG_EXTRA' );
		putenv( 'WP_CONFIG_EXTRA' );
		unset( $_ENV['WORDPRESS_CONFIG_EXTRA'], $_ENV['WP_CONFIG_EXTRA'] );
		unset( $_SERVER['WORDPRESS_CONFIG_EXTRA'], $_SERVER['WP_CONFIG_EXTRA'] );
		parent::tear_down();
	}

	public function test_custom_value_is_single_line_and_contains_loader() {
		$value = khse_expert_mode_custom_value();

		$this->assertStringContainsString( 'expert-mode.php', $value );
		$this->assertStringContainsString( 'khse_run_expert_mode', $value );
		$this->assertStringContainsString( wp_normalize_path( khse_get_upload_dir() ), $value );
		$this->assertStringContainsString( wp_normalize_path( khse_get_private_runtime_dir() ), $value );
		$this->assertStringNotContainsString( "\n", $value );
	}

	public function test_custom_value_is_valid_php() {
		$parsed = true;

		try {
			eval( 'if ( false ) { ' . khse_expert_mode_custom_value() . ' }' );
		} catch ( ParseError $error ) {
			$parsed = false;
		}

		$this->assertTrue( $parsed );
	}

	public function test_ready_detects_wordpress_config_extra() {
		putenv( 'WORDPRESS_CONFIG_EXTRA=' . khse_expert_mode_custom_value() );

		$this->assertTrue( khse_is_expert_mode_code_ready() );
	}

	public function test_ready_rejects_unrelated_config_extra() {
		putenv( 'WORDPRESS_CONFIG_EXTRA=define( "UNRELATED", true );' );

		$this->assertFalse( khse_is_expert_mode_code_ready() );
	}
}
