<?php
/**
 * Dependency-free AMS Cache runtime smoke tests.
 *
 * Run with: php bin/smoke-test.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'SCM_INC', true );

$smoke_options = array(
	'scm_blog_id'  => 1,
	'scm_dir_hash' => 'smoke123',
);

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

function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

require_once dirname( __DIR__ ) . '/inc/cache-core.php';
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

$runtime = wp_normalize_path( scm_get_private_runtime_dir() );
smoke_assert( 0 !== strncasecmp( $runtime . '/', wp_normalize_path( ABSPATH ) . '/', strlen( wp_normalize_path( ABSPATH ) ) + 1 ), 'runtime directory is outside public root' );

$requirements = scm_get_page_optimization_requirements();
smoke_assert( ! empty( $requirements['php_css']['passed'] ) && ! empty( $requirements['php_js']['passed'] ), 'dashboard reports PHP engines ready' );
smoke_assert( ! isset( $requirements['purgecss'] ) && ! isset( $requirements['bun'] ), 'dashboard does not require runtime CLI tools' );

echo "AMS Cache smoke tests passed\n";
