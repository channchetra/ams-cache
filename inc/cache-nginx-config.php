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

/**
 * Generate Nginx server block snippet for direct static cache.
 *
 * @return string
 */
function scm_get_nginx_direct_cache_snippet() {
	$relative_dir = '/' . trim( scm_get_nginx_static_cache_relative_dir(), '/' );
	$relative_parent = dirname( $relative_dir );
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
		"location ~* " . $relative_parent . "/(?:config\\.json|expert\\.lock|optimization_work|optimization_reports)(?:/|$) {\n" .
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
