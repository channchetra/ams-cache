<?php
/**
 * Class PageOptimizerTransformsTest
 *
 * @package KHSE_Cache
 */

class PageOptimizerTransformsTest extends WP_UnitTestCase {

	public function test_safe_comments_are_removed_and_conditional_comments_kept() {
		$html = '<p>a</p><!-- remove me --><!--[if IE]><b>ie</b><![endif]--><!-- wp:paragraph --><p>b</p>';

		$result = khse_remove_html_comments( $html );

		$this->assertStringNotContainsString( 'remove me', $result );
		$this->assertStringContainsString( '[if IE]', $result );
		$this->assertStringContainsString( 'wp:paragraph', $result );
	}

	public function test_html_minifier_preserves_protected_blocks() {
		$html = "<div>  <p>a</p>  </div>\n<pre>  keep  </pre>\n<script>  var x = 1;  </script>";

		$result = khse_minify_html( $html );

		$this->assertStringContainsString( '<div><p>a</p></div>', $result );
		$this->assertStringContainsString( '<pre>  keep  </pre>', $result );
		$this->assertStringContainsString( '<script>  var x = 1;  </script>', $result );
	}

	public function test_inline_css_blocks_are_minified() {
		$html = '<style> .card { color: red; padding: 0 10px; } </style>';

		$result = khse_minify_inline_css_blocks( $html );

		$this->assertStringContainsString( '.card{color:red;padding:0 10px}', $result );
	}

	public function test_media_tags_prioritize_the_first_image_and_lazy_load_the_rest() {
		$settings                        = khse_get_default_page_optimization_settings();
		$settings['lazy_media']          = 'yes';
		$settings['critical_images']     = 'yes';
		$settings['critical_image_count'] = 1;
		$settings['media_exclusions']    = 'logo';

		$html = '<html><head></head><body><img src="hero.jpg"><img src="logo.png"><img src="second.jpg"></body></html>';

		$result = khse_optimize_media_tags( $html, $settings );

		$this->assertMatchesRegularExpression( '/<img[^>]*src="hero\.jpg"[^>]*fetchpriority="high"/', $result );
		$this->assertMatchesRegularExpression( '/<img[^>]*src="hero\.jpg"[^>]*loading="eager"/', $result );
		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*src="logo\.png"[^>]*loading=/', $result );
		$this->assertMatchesRegularExpression( '/<img[^>]*src="second\.jpg"[^>]*loading="lazy"/', $result );
		$this->assertStringContainsString( '<link rel="preload" as="image"', $result );
	}

	public function test_defer_scripts_skips_analyzer_mode_and_exclusions() {
		$settings                 = khse_get_default_page_optimization_settings();
		$settings['js_analysis']  = 'no';
		$settings['js_exclusions'] = "excluded.js";

		$html = '<script src="app.js"></script><script src="excluded.js"></script><script src="module.js" type="module"></script>';

		$result = khse_defer_scripts( $html, $settings );

		$this->assertStringContainsString( '<script defer src="app.js">', $result );
		$this->assertStringNotContainsString( '<script defer src="excluded.js">', $result );
		$this->assertStringNotContainsString( '<script defer src="module.js"', $result );
	}

	public function test_javascript_analyzer_flags_unsafe_sources() {
		$this->assertTrue( khse_analyze_javascript_source( 'window.addEventListener("load", boot);' )['safeToDefer'] );
		$this->assertFalse( khse_analyze_javascript_source( 'document.write("unsafe");' )['safeToDefer'] );
		$this->assertFalse( khse_analyze_javascript_source( 'eval("unsafe");' )['safeToDefer'] );
	}

	public function test_css_purge_keeps_classes_used_on_later_elements() {
		$html = '<div class="first"></div><div class="later"></div>';
		$css  = '.later{color:red}.unused{display:none}';

		$result = khse_purge_css_conservative( $css, $html, array() );

		$this->assertStringContainsString( '.later{color:red}', $result );
		$this->assertStringNotContainsString( '.unused', $result );
	}

	public function test_css_purge_removes_truly_unused_selectors_and_keeps_dynamic_rules() {
		$html = '<body class="menu open"></body>';
		$css  = 'body{color:red}.menu:hover{color:blue}.unused{display:none}@font-face{font-family:test;src:url(test.woff2)}';

		$result = khse_purge_css_conservative( $css, $html, array( 'open' ) );

		$this->assertStringContainsString( 'body{color:red}', $result );
		$this->assertStringContainsString( '.menu:hover', $result );
		$this->assertStringContainsString( '@font-face', $result );
		$this->assertStringNotContainsString( '.unused', $result );
	}

	public function test_css_minifier_preserves_calc_spacing_and_descendant_pseudos() {
		$result = khse_minify_css( '.menu :hover { width: calc(100% + 10px); }' );

		$this->assertStringContainsString( 'calc(100% + 10px)', $result );
		$this->assertStringContainsString( '.menu :hover', $result );
	}

	public function test_external_ucss_helpers_rewrite_and_store_assets() {
		$tag = '<link rel="stylesheet" href="https://cdn.example.com/css/a.css">';

		$this->assertTrue( khse_is_external_ucss_candidate( $tag ) );
		$this->assertFalse( khse_is_external_ucss_candidate( '<link rel="stylesheet" href="/local.css" integrity="sha384-x">' ) );
		$this->assertSame(
			'https://cdn.example.com/img/a.png',
			khse_resolve_css_url( '../img/a.png', 'https://cdn.example.com/css/main.css' )
		);

		$rewritten = khse_rewrite_css_relative_urls( 'a{background:url(../img/a.png)}', 'https://cdn.example.com/css/main.css' );

		$this->assertStringContainsString( 'url("https://cdn.example.com/img/a.png")', $rewritten );

		$asset = khse_store_optimized_css_asset( '.a{color:red}' );

		$this->assertArrayHasKey( 'file', $asset );
		$this->assertFileExists( $asset['file'] );

		@unlink( $asset['file'] );
	}

	public function test_selector_list_is_kept_when_any_selector_is_used() {
		$html = '<div class="present"></div>';

		$this->assertTrue( khse_css_selector_is_used( '.present, .missing', $html, array() ) );
	}
}
