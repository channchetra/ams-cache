<?php
/**
 * AMS Cache — cache-nginx-config.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_get_nginx_direct_cache_requirements() {
	$static_dir = scm_get_nginx_static_cache_dir();

	if ( ! is_dir( $static_dir ) ) {
		wp_mkdir_p( $static_dir );
	}

	return array(
		'server'    => array(
			'label'  => __( 'Nginx detected', 'ams-cache' ),
			'passed' => isset( $_SERVER['SERVER_SOFTWARE'] ) && false !== stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ),
			'detail' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : __( 'Unknown server software', 'ams-cache' ),
		),
		'driver'    => array(
			'label'  => __( 'File cache driver selected', 'ams-cache' ),
			'passed' => 'file' === get_option( 'scm_option_driver', 'file' ),
			'detail' => get_option( 'scm_option_driver', 'file' ),
		),
		'permalink' => array(
			'label'  => __( 'Pretty permalinks enabled', 'ams-cache' ),
			'passed' => '' !== get_option( 'permalink_structure', '' ),
			'detail' => '' !== get_option( 'permalink_structure', '' ) ? get_option( 'permalink_structure' ) : __( 'Plain permalinks', 'ams-cache' ),
		),
		'writable'  => array(
			'label'  => __( 'Static cache directory writable', 'ams-cache' ),
			'passed' => is_dir( $static_dir ) && is_writable( $static_dir ),
			'detail' => $static_dir,
		),
		'config'    => array(
			'label'  => __( 'Nginx snippet installed manually', 'ams-cache' ),
			'passed' => false,
			'detail' => __( 'Copy the generated snippet into your server block, then run nginx -t and reload Nginx.', 'ams-cache' ),
		),
	);
}

/**
 * Generate Nginx server block snippet for direct static cache.
 *
 * @return string
 */
function scm_get_nginx_direct_cache_snippet() {
	$relative_dir = '/' . trim( scm_get_nginx_static_cache_relative_dir(), '/' );
	$tracking_keys = array_map( 'preg_quote', scm_get_cache_tracking_query_vars() );
	$tracking_keys = '(?:' . implode( '|', $tracking_keys ) . ')';
	$tracking_query_pattern = '^' . $tracking_keys . '(?:=[^&]*)?(?:&' . $tracking_keys . '(?:=[^&]*)?)*$';

	$bypass_cookie_pattern = implode( '|', array_map( 'preg_quote', scm_get_cache_bypass_cookie_prefixes() ) );
	$bypass_paths          = array_map(
		function ( $path ) {
			return '/' . preg_quote( $path, '/' ) . '(?:/|$|\\?)';
		},
		scm_get_cache_bypass_paths()
	);
	$bypass_path_pattern   = '(?:' . implode( '|', $bypass_paths ) . ')';

	return trim(
		"# AMS Cache direct static cache.\n" .
		"# Put inside the Nginx server {} block. Merge the location / block with your existing WordPress config.\n" .
		"set \$ams_cache_file \"\";\n" .
		"set \$ams_cache_skip 0;\n\n" .
		"set \$ams_cache_query_safe 0;\n" .
		"if (\$request_method !~ ^(GET|HEAD)$) { set \$ams_cache_skip 1; }\n" .
		"if (\$args = \"\") { set \$ams_cache_query_safe 1; }\n" .
		"if (\$args ~* \"" . $tracking_query_pattern . "\") { set \$ams_cache_query_safe 1; }\n" .
		"if (\$ams_cache_query_safe = 0) { set \$ams_cache_skip 1; }\n" .
		"if (\$http_cookie ~* \"" . $bypass_cookie_pattern . "\") { set \$ams_cache_skip 1; }\n" .
		"if (\$request_uri ~* \"" . $bypass_path_pattern . "\") { set \$ams_cache_skip 1; }\n\n" .
		"if (\$ams_cache_skip = 0) { set \$ams_cache_file \"" . $relative_dir . "\$uri/index.html\"; }\n\n" .
		"gzip on;\n" .
		"gzip_vary on;\n" .
		"gzip_types text/plain text/css application/javascript application/json image/svg+xml application/xml;\n\n" .
		"location ~* /wp-content/uploads/ams-cache/[^/]+/(?:config\\.json|expert\\.lock|optimization_work|optimization_reports)(?:/|$) {\n" .
		"    deny all;\n" .
		"    return 404;\n" .
		"}\n\n" .
		"location ^~ " . $relative_dir . "/ {\n" .
		"    internal;\n" .
		"    add_header X-AMS-Cache-Static HIT always;\n" .
		"}\n\n" .
		"location ~* \\.(?:css|js|jpg|jpeg|gif|png|webp|svg|ico|woff2?)$ {\n" .
		"    expires 1y;\n" .
		"    add_header Cache-Control \"public, immutable\";\n" .
		"    try_files \$uri =404;\n" .
		"}\n\n" .
		"location / {\n" .
		"    try_files \$ams_cache_file \$uri \$uri/ /index.php?\$args;\n" .
		"}"
	);
}
