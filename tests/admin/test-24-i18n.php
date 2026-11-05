<?php
/**
 * Class I18nConventionTest
 *
 * @package KHSE_Cache
 */

class I18nConventionTest extends WP_UnitTestCase {

	public function test_benchmark_strings_use_english_msgids() {
		$files = array(
			'inc/class-khse-benchmark-widget.php',
			'inc/cache/class-khse-cache-debug.php',
		);

		foreach ( $files as $file ) {
			$source = file_get_contents( KHSE_PLUGIN_DIR . $file );

			$this->assertDoesNotMatchRegularExpression( '/[\x{1780}-\x{17FF}]/u', $source, $file );
		}
	}

	public function test_permalink_notice_uses_the_plugin_domain() {
		$source = file_get_contents( KHSE_PLUGIN_DIR . 'inc/admin/menu.php' );

		$this->assertStringNotContainsString( 'seo-search-permalink', $source );
		$this->assertStringContainsString( "__( 'Permalink Setting', 'kh-speed-engine' )", $source );
	}
}
