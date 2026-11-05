<?php
/**
 * Class NginxPathsTest
 *
 * @package KHSE_Cache
 */

class NginxPathsTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'content_url' );
		parent::tear_down();
	}

	public function test_absolute_dir_is_built_from_the_plugin_upload_dir() {
		$this->assertSame( khse_get_upload_dir() . '/nginx', khse_get_nginx_static_cache_dir() );
	}

	public function test_absolute_and_relative_dirs_share_the_same_suffix() {
		$suffix = '/kh-speed-engine/' . khse_get_blog_id() . '_' . khse_get_dir_hash() . '/nginx';

		$this->assertStringEndsWith( $suffix, khse_get_nginx_static_cache_dir() );
		$this->assertStringEndsWith( $suffix, '/' . khse_get_nginx_static_cache_relative_dir() );
	}

	public function test_relative_dir_uses_content_url_path() {
		add_filter(
			'content_url',
			function ( $url, $path ) {
				return 'https://example.org/custom-content/' . ltrim( $path, '/' );
			},
			10,
			2
		);

		$relative = khse_get_nginx_static_cache_relative_dir();

		$this->assertStringStartsWith( 'custom-content/uploads/kh-speed-engine/', $relative );
	}

	public function test_snippet_deny_rule_uses_the_derived_parent() {
		$parent  = dirname( '/' . khse_get_nginx_static_cache_relative_dir() );
		$snippet = khse_get_nginx_direct_cache_snippet();

		$this->assertStringContainsString( 'location ~* ' . $parent . '/', $snippet );
		$this->assertStringNotContainsString( 'location ~* /wp-content/uploads/kh-speed-engine/[^/]+/', $snippet );
	}
}
