<?php
/**
 * Class BenchmarkMarkupTest
 *
 * @package Cache_Master
 */

class BenchmarkMarkupTest extends WP_UnitTestCase {

	public function test_footer_renderer_owns_all_metric_fields() {
		$html = scm_render_benchmark_metrics( 'footer' );

		foreach ( array( 'cache-status', 'memory-usage', 'sql-queries', 'page-generation-time' ) as $field ) {
			$this->assertSame( 1, substr_count( $html, 'scm-field-' . $field ) );
		}

		$this->assertStringContainsString( 'scm-value', $html );
		$this->assertStringNotContainsString( 'scm-tr', $html );
	}

	public function test_widget_renderer_owns_all_metric_fields() {
		$html = scm_render_benchmark_metrics( 'widget' );

		foreach ( array( 'cache-status', 'memory-usage', 'sql-queries', 'page-generation-time' ) as $field ) {
			$this->assertSame( 1, substr_count( $html, 'scm-field-' . $field ) );
		}

		$this->assertStringContainsString( 'scm-tr', $html );
		$this->assertStringNotContainsString( 'scm-value', $html );
	}

	public function test_renderers_do_not_duplicate_field_classes() {
		foreach ( array( 'inc/class-cache-master-debug.php', 'inc/admin/class-scm-benchmark-widget.php' ) as $file ) {
			$source = file_get_contents( SCM_PLUGIN_DIR . $file );

			$this->assertStringNotContainsString( 'scm-field-', $source, $file );
		}
	}
}
