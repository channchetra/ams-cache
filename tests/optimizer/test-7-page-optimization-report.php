<?php
/**
 * Class PageOptimizationReportTest
 *
 * @package KHSE_Cache
 */

/**
 * The report builder must keep every feature key and its status semantics.
 */
class PageOptimizationReportTest extends WP_UnitTestCase {

	/**
	 * The descriptor loops produce the same feature order as before.
	 *
	 * @return void
	 */
	public function test_report_has_all_features_in_order() {
		$settings = khse_get_default_page_optimization_settings();
		$report   = khse_build_page_optimization_report(
			'<html><body><p>before</p></body></html>',
			'<html><body><p>after</p></body></html>',
			$settings
		);

		$this->assertSame(
			array(
				'minify_html',
				'remove_comments',
				'minify_inline_css',
				'lazy_media',
				'critical_images',
				'preconnect_fonts',
				'defer_js',
				'external_ucss',
				'local_ucss',
				'js_analysis',
			),
			array_keys( $report['features'] )
		);

		$this->assertSame( 'disabled', $report['features']['external_ucss']['status'] );
		$this->assertSame( 'Disabled.', $report['features']['external_ucss']['detail'] );
	}

	/**
	 * A disabled metric feature short-circuits to the disabled detail.
	 *
	 * @return void
	 */
	public function test_disabled_metric_feature_reports_disabled() {
		$settings                = khse_get_default_page_optimization_settings();
		$settings['minify_html'] = 'no';

		$report = khse_build_page_optimization_report( 'a', 'b', $settings );

		$this->assertSame( 'disabled', $report['features']['minify_html']['status'] );
		$this->assertSame( 'Disabled.', $report['features']['minify_html']['detail'] );
	}
}
