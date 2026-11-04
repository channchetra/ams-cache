<?php
/**
 * Class CacheMasterDebugTest
 *
 * @package Cache_Master
 */

/**
 * Coverage for the SCM_Cache_Master_Debug trait's public output surface.
 */
class CacheMasterDebugTest extends WP_UnitTestCase {

	/**
	 * The inline benchmark CSS must be emitted when the footer report is on.
	 *
	 * @return void
	 */
	public function test_front_enqueue_styles_outputs_css_when_benchmark_enabled() {
		update_option( 'scm_option_benchmark_footer_text', 'yes' );

		$cm  = new Cache_Master();
		$css = $cm->get_front_enqueue_styles();

		$this->assertIsString( $css );
		$this->assertNotSame( '', trim( $css ) );
		$this->assertStringContainsString( '.scm-', $css );
	}
}
