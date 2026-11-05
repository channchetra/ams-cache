<?php
/**
 * Class CacheMasterDebugTest
 *
 * @package KHSE_Cache
 */

/**
 * Coverage for the KHSE_Cache_Debug trait's public output surface.
 */
class CacheMasterDebugTest extends WP_UnitTestCase {

	/**
	 * The inline benchmark CSS must be emitted when the footer report is on.
	 *
	 * @return void
	 */
	public function test_front_enqueue_styles_outputs_css_when_benchmark_enabled() {
		update_option( 'khse_option_benchmark_footer_text', 'yes' );

		$cm  = new KHSE_Cache();
		$css = $cm->get_front_inline_css();

		$this->assertIsString( $css );
		$this->assertNotSame( '', trim( $css ) );
		$this->assertStringContainsString( '.khse-', $css );
	}
}
