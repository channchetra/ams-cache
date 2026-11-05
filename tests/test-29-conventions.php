<?php
/**
 * Class ConventionsTest
 *
 * @package KHSE_Cache
 */

class ConventionsTest extends WP_UnitTestCase {

	public function test_benchmark_js_has_no_khmer_labels() {
		$script = khse_javascript();

		$this->assertDoesNotMatchRegularExpression( '/[\x{1780}-\x{17FF}]/u', $script );
		$this->assertStringContainsString( 'khse_text_cache_status = "No"', $script );
	}

	public function test_plugin_asset_handles_are_prefixed() {
		$core = file_get_contents( KHSE_PLUGIN_DIR . 'inc/class-khse-cache.php' );
		$menu = file_get_contents( KHSE_PLUGIN_DIR . 'inc/admin/menu.php' );

		$this->assertStringContainsString( "'kh-speed-engine-style'", $core );
		$this->assertStringNotContainsString( "'khse-style'", $core );

		$this->assertStringContainsString( "'kh-speed-engine-admin-built-'", $menu );
		$this->assertStringNotContainsString( "'admin-style.css'", $menu );
		$this->assertStringNotContainsString( "'kh-speed-engine-code-highlight'", $menu );
	}

	public function test_plugin_action_links_always_return_the_links_array() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$links = array( 'deactivate' => '<a>Deactivate</a>' );

		$this->assertSame( $links, khse_plugin_action_links( $links, 'other-plugin/other.php' ) );
		$this->assertNotSame( $links, khse_plugin_action_links( $links, KHSE_PLUGIN_NAME ) );
	}

	public function test_benchmark_widget_title_uses_text_domain() {
		$widget = file_get_contents( KHSE_PLUGIN_DIR . 'inc/class-khse-benchmark-widget.php' );

		$this->assertStringContainsString( "_e( 'Title:', 'kh-speed-engine' )", $widget );
	}
}
