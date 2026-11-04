<?php
/**
 * Class ConventionsTest
 *
 * @package Cache_Master
 */

class ConventionsTest extends WP_UnitTestCase {

	public function test_benchmark_js_has_no_khmer_labels() {
		$script = scm_javascript();

		$this->assertDoesNotMatchRegularExpression( '/[\x{1780}-\x{17FF}]/u', $script );
		$this->assertStringContainsString( 'scm_text_cache_status = "No"', $script );
	}

	public function test_plugin_asset_handles_are_prefixed() {
		$core = file_get_contents( SCM_PLUGIN_DIR . 'inc/class-cache-master.php' );
		$menu = file_get_contents( SCM_PLUGIN_DIR . 'inc/admin/menu.php' );

		$this->assertStringContainsString( "'ams-cache-style'", $core );
		$this->assertStringNotContainsString( "'scm-style'", $core );

		$this->assertStringContainsString( "'ams-cache-admin-css'", $menu );
		$this->assertStringNotContainsString( "'custom-wp-admin-css'", $menu );

		$this->assertStringContainsString( "'ams-cache-code-highlight'", $menu );
		$this->assertStringNotContainsString( "'code-highlight'", $menu );
	}

	public function test_benchmark_widget_title_uses_text_domain() {
		$widget = file_get_contents( SCM_PLUGIN_DIR . 'inc/admin/class-scm-benchmark-widget.php' );

		$this->assertStringContainsString( "_e( 'Title:', 'ams-cache' )", $widget );
	}
}
