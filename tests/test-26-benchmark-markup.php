<?php
/**
 * Class BenchmarkMarkupTest
 *
 * @package KHSE_Cache
 */

class BenchmarkMarkupTest extends WP_UnitTestCase {

	public function test_footer_renderer_owns_all_metric_fields() {
		$html = khse_render_benchmark_metrics( 'footer' );

		foreach ( array( 'cache-status', 'memory-usage', 'sql-queries', 'page-generation-time' ) as $field ) {
			$this->assertSame( 1, substr_count( $html, 'khse-field-' . $field ) );
		}

		$this->assertStringContainsString( 'khse-value', $html );
		$this->assertStringNotContainsString( 'khse-tr', $html );
	}

	public function test_widget_renderer_owns_all_metric_fields() {
		$html = khse_render_benchmark_metrics( 'widget' );

		foreach ( array( 'cache-status', 'memory-usage', 'sql-queries', 'page-generation-time' ) as $field ) {
			$this->assertSame( 1, substr_count( $html, 'khse-field-' . $field ) );
		}

		$this->assertStringContainsString( 'khse-tr', $html );
		$this->assertStringNotContainsString( 'khse-value', $html );
	}

	public function test_renderers_do_not_duplicate_field_classes() {
		foreach ( array( 'inc/class-khse-cache-debug.php', 'inc/admin/class-khse-benchmark-widget.php' ) as $file ) {
			$source = file_get_contents( KHSE_PLUGIN_DIR . $file );

			$this->assertStringNotContainsString( 'khse-field-', $source, $file );
		}
	}
}
