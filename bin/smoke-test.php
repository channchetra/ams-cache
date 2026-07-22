<?php
/**
 * Dependency-free AMS Cache runtime smoke tests.
 *
 * Run with: php bin/smoke-test.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'SCM_INC', true );
define( 'SCM_PLUGIN_VERSION', 'smoke' );

$smoke_options = array(
	'scm_blog_id'  => 1,
	'scm_dir_hash' => 'smoke123',
);
$smoke_transients = array();
$smoke_scheduled  = array();
$smoke_post       = null;

function get_option( $key, $default = false ) {
	global $smoke_options;
	return array_key_exists( $key, $smoke_options ) ? $smoke_options[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $smoke_options;
	$smoke_options[ $key ] = $value;
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

function wp_hash( $value ) {
	return md5( (string) $value );
}

function wp_rand( $min, $max ) {
	return $min;
}

function get_current_blog_id() {
	return 1;
}

function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function __( $value ) {
	return $value;
}

function size_format( $value ) {
	return (string) $value . ' B';
}

function number_format_i18n( $value ) {
	return (string) $value;
}

function esc_url( $value ) {
	return (string) $value;
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function wp_remote_get( $url, $args = array() ) {
	return array( 'url' => $url, 'args' => $args );
}

function is_wp_error( $value ) {
	return false;
}

function wp_remote_retrieve_response_code( $response ) {
	return 200;
}

function wp_remote_retrieve_body( $response ) {
	return '<html><body>' . str_repeat( 'x', 1024 ) . '</body></html>';
}

function get_transient( $key ) {
	global $smoke_transients;
	return isset( $smoke_transients[ $key ] ) ? $smoke_transients[ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	global $smoke_transients;
	$smoke_transients[ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	global $smoke_transients;
	unset( $smoke_transients[ $key ] );
}

function wp_next_scheduled( $hook ) {
	global $smoke_scheduled;
	return in_array( $hook, $smoke_scheduled, true ) ? time() + 5 : false;
}

function wp_schedule_single_event( $timestamp, $hook ) {
	global $smoke_scheduled;
	$smoke_scheduled[] = $hook;
	return true;
}

function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

function get_post( $post_id ) {
	global $smoke_post;
	return $smoke_post && (int) $smoke_post->ID === (int) $post_id ? $smoke_post : null;
}

function wp_get_post_categories( $post_id ) {
	return array( 7 );
}

function get_category_link( $term_id ) {
	return home_url( '/category/news/' );
}

function wp_get_post_tags( $post_id, $args = array() ) {
	return array( 11 );
}

function get_tag_link( $term_id ) {
	return home_url( '/tag/cache/' );
}

function get_author_posts_url( $author_id ) {
	return home_url( '/author/editor/' );
}

function get_year_link( $year ) {
	return home_url( '/' . $year . '/' );
}

function get_month_link( $year, $month ) {
	return home_url( '/' . $year . '/' . str_pad( $month, 2, '0', STR_PAD_LEFT ) . '/' );
}

require_once dirname( __DIR__ ) . '/inc/cache-core.php';
require_once dirname( __DIR__ ) . '/inc/cache-nginx.php';
require_once dirname( __DIR__ ) . '/inc/cache-purger.php';
require_once dirname( __DIR__ ) . '/inc/cache-preload.php';
require_once dirname( __DIR__ ) . '/inc/page-optimizer.php';
require_once dirname( __DIR__ ) . '/inc/cache-utils.php';

function smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

smoke_assert( '/about/' === scm_normalize_cache_uri( '/about' ), 'cache URI normalization' );
smoke_assert( scm_is_cacheable_request_uri( '/about/' ), 'HTML request classification' );
smoke_assert( ! scm_is_cacheable_request_uri( '/assets/app.css' ), 'asset request classification' );
smoke_assert( '' !== scm_local_asset_url_to_path( '/inc/cache-core.php' ), 'same-site asset resolution' );
smoke_assert( '' === scm_local_asset_url_to_path( '/../cache-core.php' ), 'path traversal rejected' );

$analysis = scm_analyze_javascript_source( 'document.write("unsafe");' );
smoke_assert( empty( $analysis['safeToDefer'] ), 'unsafe JavaScript remains blocking' );
smoke_assert( ! empty( scm_analyze_javascript_source( 'window.addEventListener("load", boot);' )['safeToDefer'] ), 'safe JavaScript can defer' );

$html = '<html><body class="site"><div class="menu open"></div></body></html>';
$css  = 'body{color:red}.unused{display:none}.menu:hover{color:blue}@font-face{font-family:test;src:url(test.woff2)}';
$purged = scm_purge_css_conservative( $css, $html, array( 'open' ) );
smoke_assert( false === strpos( $purged, '.unused' ), 'unused simple selector removed' );
smoke_assert( false !== strpos( $purged, 'body{' ), 'used element selector preserved' );
smoke_assert( false !== strpos( $purged, '.menu:hover' ), 'dynamic selector preserved' );
smoke_assert( false !== strpos( $purged, '@font-face' ), 'font-face preserved' );

$optimized_inline = scm_apply_internal_local_ucss(
	'<style>' . $css . '</style>' . $html,
	array(
		'ucss_safelist'          => 'open',
		'local_ucss_max_file_size' => 524288,
	)
);
smoke_assert( false === strpos( $optimized_inline, '.unused' ), 'inline UCSS uses PHP engine' );

$stats_dir = scm_get_stats_dir( 'post' );
wp_mkdir_p( $stats_dir );
$old_key  = md5( 'old_prefix|/deleted/' );
$old_file = $stats_dir . '/' . $old_key . '.json';
file_put_contents( $old_file, json_encode( array( 'size' => 10, 'uri' => '/deleted/' ) ) );
$driver = new class() {
	public $deleted = array();

	public function delete( $key ) {
		$this->deleted[] = $key;
		return true;
	}
};
$purge = scm_purge_cache_uri( '/deleted/', $driver );
smoke_assert( ! file_exists( $old_file ), 'purge removes stale cache stats under old prefixes' );
smoke_assert( in_array( $old_key, $driver->deleted, true ), 'purge removes stale driver key under old prefixes' );
smoke_assert( null === scm_preload_url( 'https://attacker.test/' ), 'preload rejects external hosts' );
$preload_response = scm_preload_url( home_url( '/deleted/' ) );
smoke_assert( is_array( $preload_response ), 'preload keeps same-site URLs working' );
smoke_assert( true === $preload_response['args']['blocking'], 'preload waits for cache write' );
smoke_assert( 8 === $preload_response['args']['timeout'], 'preload allows page generation time' );
smoke_assert( false === scm_preload_sslverify( 'https://127.0.0.1/' ), 'local HTTPS preload permits loopback certificate' );
smoke_assert( true === scm_preload_sslverify( 'https://example.test/' ), 'external HTTPS preload keeps certificate verification' );

$smoke_post = (object) array(
	'ID'          => 321,
	'post_status' => 'trash',
	'post_type'   => 'post',
	'post_author' => 42,
	'post_date'   => '2026-07-20 12:00:00',
);
$smoke_options['scm_option_post_archives'] = array(
	'category' => 'yes',
	'tag'      => 'yes',
	'author'   => 'yes',
	'date'     => 'yes',
);
$critical_urls = scm_get_critical_preload_urls( 321, 25 );
smoke_assert( in_array( home_url( '/' ), $critical_urls, true ), 'critical preload includes homepage' );
smoke_assert( in_array( home_url( '/category/news/' ), $critical_urls, true ), 'critical preload keeps category after trash' );
smoke_assert( in_array( home_url( '/tag/cache/' ), $critical_urls, true ), 'critical preload keeps tag after trash' );
smoke_assert( in_array( home_url( '/author/editor/' ), $critical_urls, true ), 'critical preload keeps author after trash' );
smoke_assert( in_array( home_url( '/2026/' ), $critical_urls, true ), 'critical preload keeps date archive after trash' );

$smoke_options['scm_option_preload_cache']       = 'yes';
$smoke_options['scm_option_caching_status']      = 'enable';
$smoke_options['scm_preload_queue_processed']    = 0;
$smoke_options['scm_preload_queue_failed']       = 0;
$smoke_options['scm_preload_queue_remaining']    = 1;
$smoke_transients['scm_preload_queue']           = array( home_url( '/about/' ) );
scm_maybe_schedule_preload_cache();
smoke_assert( in_array( 'scm_preload_cache_event', $smoke_scheduled, true ), 'enabled preload schedules after deployment' );
scm_maybe_schedule_preload_cache();
smoke_assert( 1 === count( array_keys( $smoke_scheduled, 'scm_preload_cache_event', true ) ), 'deployment preload bootstrap schedules once' );
scm_process_preload_queue();
smoke_assert( 1 === (int) $smoke_options['scm_preload_queue_processed'], 'preload queue counts completed page' );
smoke_assert( 0 === (int) $smoke_options['scm_preload_queue_failed'], 'preload queue keeps successful page out of failures' );
@rmdir( $stats_dir );
@rmdir( dirname( $stats_dir ) );
@rmdir( scm_get_upload_dir() );

$runtime = wp_normalize_path( scm_get_private_runtime_dir() );
smoke_assert( 0 !== strncasecmp( $runtime . '/', wp_normalize_path( ABSPATH ) . '/', strlen( wp_normalize_path( ABSPATH ) ) + 1 ), 'runtime directory is outside public root' );

$requirements = scm_get_page_optimization_requirements();
smoke_assert( ! empty( $requirements['php_css']['passed'] ) && ! empty( $requirements['php_js']['passed'] ), 'dashboard reports PHP engines ready' );
smoke_assert( ! isset( $requirements['purgecss'] ) && ! isset( $requirements['bun'] ), 'dashboard does not require runtime CLI tools' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/cache-master.php' );
smoke_assert( false !== strpos( $plugin_source, "require_once SCM_PLUGIN_DIR . 'inc/admin/update-post.php';" ), 'post purge hooks load outside admin-only branch' );
$post_hooks_source = file_get_contents( dirname( __DIR__ ) . '/inc/admin/update-post.php' );
smoke_assert( false !== strpos( $post_hooks_source, "add_action( 'wp_trash_post', 'scm_purge_post_before_trash', 10, 2 );" ), 'trash purge hook runs before status change' );
smoke_assert( false !== strpos( $post_hooks_source, 'scm_preload_critical_urls( $post->ID, true, $driver );' ), 'status changes warm related post archives' );
smoke_assert( false !== strpos( $post_hooks_source, 'scm_preload_url( $post_url );' ), 'new and updated posts warm their own page' );
$cache_master_source = file_get_contents( dirname( __DIR__ ) . '/inc/class-cache-master.php' );
smoke_assert( false === strpos( $cache_master_source, 'if ( ! scm_is_homepage_uri( $this->get_request_uri() ) )' ), 'localized front pages can be cached by their own URI' );

$preload_source = file_get_contents( dirname( __DIR__ ) . '/inc/cache-preload.php' );
smoke_assert( false !== strpos( $preload_source, "add_action( 'init', 'scm_maybe_schedule_preload_cache', 20 );" ), 'enabled preload reschedules after deployment' );
$admin_source = file_get_contents( dirname( __DIR__ ) . '/inc/admin/functions.php' );
smoke_assert( false !== strpos( $admin_source, "scm_search_expert_mode_code_snippet( wp_normalize_path( scm_get_private_runtime_dir() ) )" ), 'Expert Mode readiness checks private runtime path' );

echo "AMS Cache smoke tests passed\n";
