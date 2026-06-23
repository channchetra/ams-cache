<?php
/**
 * AMS Cache helper functions.
 *
 * @package   AMS Cache
 * @author    Terry Lin <terrylinooo>
 * @license   GPLv3 (or later)
 * @link      https://terryl.in
 * @copyright 2020 Terry Lin
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

/**
 * Load plugin textdomain.
 *
 * @return void
 */
function scm_load_textdomain() {
	load_plugin_textdomain( SCM_PLUGIN_TEXT_DOMAIN, false, SCM_PLUGIN_LANGUAGE_PACK );
}

/**
 * Get driver hash.
 *
 * @return string
 */
function scm_get_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( empty( $hash ) ) {
		return scm_set_dir_hash();
	}
	return $hash;
}

/**
 * Check driver hash exists or not.
 *
 * @return bool
 */
function scm_is_dir_hash() {
	$hash = get_option( 'scm_dir_hash' );

	if ( empty( $hash ) ) {
		return false;
	}
	return true;
}

/**
 * Set driver hash.
 *
 * @return string
 */
function scm_set_dir_hash() {
	$scm_dir_hash = wp_hash( gmdate( 'ymdhis' ) . wp_rand( 1, 86400 ) );
	$scm_dir_hash = substr( $scm_dir_hash, 0, 8 );

	update_option( 'scm_dir_hash', $scm_dir_hash );

	return $scm_dir_hash;
}

/**
 * Get upload dir.
 *
 * @return string
 */
function scm_get_upload_dir() {
	$base_dir   = WP_CONTENT_DIR . '/uploads/ams-cache';
	$legacy_dir = WP_CONTENT_DIR . '/uploads/' . 'cache' . '-master';

	if ( ! is_dir( $base_dir ) && is_dir( $legacy_dir ) ) {
		@rename( $legacy_dir, $base_dir );
	}

	return $base_dir . '/' . scm_get_blog_id() . '_' . scm_get_dir_hash();
}

/**
 * Get configuration file's path.
 *
 * @return string
 */
function scm_get_config_path() {
	return scm_get_upload_dir() . '/config.json';
}

/**
 * Get configuration data.
 *
 * @return array
 */
function scm_get_config_data() {
	$file = scm_get_config_path();

	if ( file_exists( $file ) ) {
		$content = file_get_contents( $file );
		return json_decode( $content, true );
	}
	return scm_get_default_config();
}

/**
 * Set channel Id.
 *
 * @return void
 */
function scm_set_blog_id() {
	update_option( 'scm_blog_id', get_current_blog_id() );
}

/**
 * Get channel Id.
 *
 * @return string
 */
function scm_get_blog_id() {
	return get_option( 'scm_blog_id', 1 );
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function scm_get_stats_dir( $cache_type = 'post' ) {
	return scm_get_upload_dir() . '/stats/' . $cache_type;
}

/**
 * Get the cache key prefix used to isolate shared cache stores.
 *
 * @return string
 */
function scm_get_cache_key_prefix() {
	$prefix = get_option( 'scm_option_cache_key_prefix', '' );

	if ( '' === $prefix ) {
		$prefix = 'scm_' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '_';
	}

	$prefix = preg_replace( '/[^A-Za-z0-9_.-]/', '_', $prefix );

	if ( '' === $prefix ) {
		$prefix = 'scm_' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '_';
	}

	return rtrim( $prefix, '._-' ) . '_';
}

/**
 * Normalize a request URI before using it as a cache identity.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return string
 */
function scm_normalize_cache_uri( $uri ) {
	$path = parse_url( (string) $uri, PHP_URL_PATH );

	if ( empty( $path ) ) {
		$path = '/';
	}

	$path = '/' . ltrim( $path, '/' );
	$path = preg_replace( '#/+#', '/', $path );

	if ( '/' !== $path ) {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		if ( '' === $extension && '/' !== substr( $path, -1 ) ) {
			$path .= '/';
		}
	}

	return $path;
}

/**
 * Check whether current request carries WordPress auth cookies.
 *
 * @return bool
 */
function scm_request_has_auth_cookie() {
	foreach ( $_COOKIE as $name => $value ) {
		if (
			0 === strpos( $name, 'wordpress_logged_in_' ) ||
			0 === strpos( $name, 'wordpress_sec_' ) ||
			0 === strpos( $name, 'wp-postpass_' )
		) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a path looks like a frontend HTML document.
 *
 * @param string $path Request path.
 *
 * @return bool
 */
function scm_is_cacheable_document_path( $path ) {
	$path = scm_normalize_cache_uri( $path );

	if ( preg_match( '#/(?:wp-admin|wp-json)(?:/|$)|/(?:wp-login\.php|xmlrpc\.php)$#i', $path ) ) {
		return false;
	}

	if ( preg_match( '#\.(?:css|js|map|json|xml|txt|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|otf|mp4|webm|mp3|m4a|pdf|zip)$#i', $path ) ) {
		return false;
	}

	return true;
}

/**
 * Check if a URI can be treated as a cacheable HTML document request.
 *
 * @param string|null $uri Request URI.
 *
 * @return bool
 */
function scm_is_cacheable_request_uri( $uri = null ) {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		return false;
	}

	if ( scm_request_has_auth_cookie() ) {
		return false;
	}

	if ( null === $uri ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	}

	$path = scm_normalize_cache_uri( $uri );

	return scm_is_cacheable_document_path( $path );
}

/**
 * Check if URI is the canonical homepage path.
 *
 * @param string $uri Request URI, URL, or path.
 *
 * @return bool
 */
function scm_is_homepage_uri( $uri ) {
	$home_path = '/';

	if ( function_exists( 'home_url' ) ) {
		$home_path = scm_normalize_cache_uri( home_url( '/' ) );
	}

	return scm_normalize_cache_uri( $uri ) === $home_path;
}

/**
 * Build the cache key for a request URI.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function scm_get_cache_key( $uri ) {
	return md5( scm_get_cache_key_prefix() . '|' . scm_normalize_cache_uri( $uri ) );
}

/**
 * Check whether Nginx direct static cache is enabled.
 *
 * @return bool
 */
function scm_is_nginx_direct_cache_enabled() {
	return 'yes' === get_option( 'scm_option_nginx_direct_cache_status', 'no' );
}

/**
 * Get Nginx direct cache relative directory.
 *
 * @return string
 */
function scm_get_nginx_static_cache_relative_dir() {
	return 'wp-content/uploads/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '/nginx';
}

/**
 * Get Nginx direct cache directory.
 *
 * @return string
 */
function scm_get_nginx_static_cache_dir() {
	return WP_CONTENT_DIR . '/uploads/ams-cache/' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '/nginx';
}

/**
 * Convert request URI into a static cache file path.
 *
 * @param string $uri Request URI or path.
 *
 * @return string
 */
function scm_get_nginx_static_cache_path( $uri ) {
	$path = parse_url( $uri, PHP_URL_PATH );

	if ( empty( $path ) ) {
		$path = '/';
	}

	$site_path = parse_url( home_url( '/' ), PHP_URL_PATH );

	if ( ! empty( $site_path ) && '/' !== $site_path && 0 === strpos( $path, $site_path ) ) {
		$path = substr( $path, strlen( rtrim( $site_path, '/' ) ) );
	}

	$path = '/' . trim( $path, '/' );

	if ( '/' === $path ) {
		return trailingslashit( scm_get_nginx_static_cache_dir() ) . 'index.html';
	}

	$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
	$safe     = array();

	foreach ( $segments as $segment ) {
		$segment = rawurldecode( $segment );

		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9._~%-]+$/', $segment ) ) {
			return '';
		}

		$safe[] = $segment;
	}

	if ( empty( $safe ) ) {
		return '';
	}

	return trailingslashit( scm_get_nginx_static_cache_dir() ) . implode( '/', $safe ) . '/index.html';
}

/**
 * Write static HTML mirror used by Nginx direct cache.
 *
 * @param string $uri     Request URI or path.
 * @param string $content Full HTML content.
 *
 * @return bool
 */
function scm_write_nginx_static_cache( $uri, $content ) {
	if (
		! scm_is_nginx_direct_cache_enabled() ||
		'file' !== get_option( 'scm_option_driver', 'file' ) ||
		empty( $content ) ||
		false === strpos( $content, '</body>' )
	) {
		return false;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		return false;
	}

	$file = scm_get_nginx_static_cache_path( $uri );

	if ( empty( $file ) ) {
		return false;
	}

	$dir = dirname( $file );

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return false;
	}

	$result = file_put_contents( $file, $content, LOCK_EX );

	if ( false === $result ) {
		return false;
	}

	chmod( $file, 0644 );

	return true;
}

/**
 * Delete one Nginx static cache file.
 *
 * @param string $uri Request URI or path.
 *
 * @return bool
 */
function scm_delete_nginx_static_cache( $uri ) {
	$file = scm_get_nginx_static_cache_path( $uri );

	if ( empty( $file ) || ! file_exists( $file ) ) {
		return false;
	}

	return unlink( $file );
}

/**
 * Clear all Nginx direct static cache files.
 *
 * @return int
 */
function scm_clear_nginx_static_cache() {
	$dir = scm_get_nginx_static_cache_dir();

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$removed = 0;
	$files   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
			continue;
		}

		if ( unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	rmdir( $dir );

	return $removed;
}

/**
 * Read a stats file in old and new formats.
 *
 * @param string $path Stats file path.
 *
 * @return array
 */
function scm_read_stats_file( $path ) {
	$content = file_exists( $path ) ? file_get_contents( $path ) : '';
	$data    = json_decode( $content, true );

	if ( is_array( $data ) ) {
		return array(
			'size' => isset( $data['size'] ) ? (int) $data['size'] : 0,
			'uri'  => isset( $data['uri'] ) ? (string) $data['uri'] : '',
		);
	}

	return array(
		'size' => (int) $content,
		'uri'  => '',
	);
}

/**
 * Delete stale stats/cache rows that point to the same URI.
 *
 * @param string                         $type        Cache type.
 * @param string                         $uri         Request URI.
 * @param string                         $current_key Current cache key to keep.
 * @param \Shieldon\SimpleCache\Cache|null $driver    Active cache driver.
 *
 * @return int
 */
function scm_delete_duplicate_stats_for_uri( $type, $uri, $current_key = '', $driver = null ) {
	$dir = scm_get_stats_dir( $type );

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$target_uri = scm_normalize_cache_uri( $uri );
	$removed    = 0;

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$key = strstr( $file->getFilename(), '.', true );

		if ( '' !== $current_key && $key === $current_key ) {
			continue;
		}

		$stats = scm_read_stats_file( $file->getPathname() );

		if ( empty( $stats['uri'] ) || scm_normalize_cache_uri( $stats['uri'] ) !== $target_uri ) {
			continue;
		}

		if ( $driver ) {
			$driver->delete( $key );
		}

		unlink( $file->getPathname() );
		$removed++;
	}

	return $removed;
}

/**
 * Delete statistics rows by cache key.
 *
 * @param string $key Cache key.
 *
 * @return int
 */
function scm_delete_cache_stats_by_key( $key ) {
	$stats_root = scm_get_upload_dir() . '/stats';

	if ( empty( $key ) || ! is_dir( $stats_root ) ) {
		return 0;
	}

	$removed = 0;

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || $file->getFilename() !== $key . '.json' ) {
			continue;
		}

		if ( unlink( $file->getPathname() ) ) {
			$removed++;
		}
	}

	return $removed;
}

/**
 * Purge cache for one normalized URI.
 *
 * @param string                         $uri    Request URI, URL, or path.
 * @param \Shieldon\SimpleCache\Cache|null $driver Active cache driver.
 *
 * @return array
 */
function scm_purge_cache_uri( $uri, $driver = null ) {
	$uri = scm_normalize_cache_uri( $uri );

	if ( ! scm_is_cacheable_document_path( $uri ) ) {
		return array(
			'key'     => '',
			'uri'     => $uri,
			'removed' => 0,
		);
	}

	if ( null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	$key     = scm_get_cache_key( $uri );
	$removed = 0;

	if ( $driver ) {
		$driver->delete( $key );
		$removed++;
	}

	$removed += scm_delete_cache_stats_by_key( $key );

	if ( scm_delete_nginx_static_cache( $uri ) ) {
		$removed++;
	}

	return array(
		'key'     => $key,
		'uri'     => $uri,
		'removed' => $removed,
	);
}

/**
 * Get Nginx direct cache environment checks.
 *
 * @return array
 */
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

	return trim(
		"# AMS Cache direct static cache.\n" .
		"# Put inside the Nginx server {} block. Merge the location / block with your existing WordPress config.\n" .
		"set \$ams_cache_file \"\";\n" .
		"set \$ams_cache_skip 0;\n\n" .
		"if (\$request_method !~ ^(GET|HEAD)$) { set \$ams_cache_skip 1; }\n" .
		"if (\$query_string != \"\") { set \$ams_cache_skip 1; }\n" .
		"if (\$http_cookie ~* \"wordpress_logged_in_|wordpress_sec_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_\") { set \$ams_cache_skip 1; }\n" .
		"if (\$request_uri ~* \"(/wp-admin/|/wp-login\\.php|/xmlrpc\\.php|/wp-json/|/cart/?|/checkout/?|/my-account/?)\") { set \$ams_cache_skip 1; }\n\n" .
		"if (\$ams_cache_skip = 0) { set \$ams_cache_file \"" . $relative_dir . "\$uri/index.html\"; }\n\n" .
		"gzip on;\n" .
		"gzip_vary on;\n" .
		"gzip_types text/plain text/css application/javascript application/json image/svg+xml application/xml;\n\n" .
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

/**
 * Default page optimization settings.
 *
 * @return array
 */
function scm_get_default_page_optimization_settings() {
	return array(
		'status'               => 'no',
		'minify_html'          => 'yes',
		'remove_comments'      => 'yes',
		'minify_inline_css'    => 'yes',
		'lazy_media'           => 'yes',
		'critical_images'      => 'yes',
		'preconnect_fonts'     => 'yes',
		'defer_js'             => 'no',
		'external_ucss'        => 'no',
		'local_ucss'           => 'no',
		'js_analysis'          => 'no',
		'bun_path'             => 'bun',
		'purgecss_path'        => 'purgecss',
		'ucss_safelist'        => "active\nopen\nshow\nis-active\ncurrent\nmenu-item\nmenu-item-has-children\ncurrent-menu-item\ncurrent-menu-parent\ncurrent-menu-ancestor\nsub-menu\ndropdown\ndropdown-menu\ndropdown-toggle\nsfHover\nis-open\nis-visible\nslick\nslick-active\nslick-current\nslick-initialized\nswiper\nswiper-slide\nswiper-wrapper\nswiper-button-next\nswiper-button-prev\nrevslider\nrev_slider\nrs-module\nrs-layer\nrs-slide\nsr7\ntp-caption\nwoocommerce\nwp-block\nalignwide\nalignfull",
		'critical_image_count' => 1,
		'external_ucss_max_file_size' => 307200,
		'image_optimization'  => 'no',
		'image_optimize_on_upload' => 'yes',
		'image_rewrite_html'  => 'yes',
		'image_placeholders'   => 'yes',
		'image_remote_rewrite' => 'no',
		'image_formats'       => array( 'webp' ),
		'image_primary_format' => 'webp',
		'image_quality'       => 82,
		'image_batch_size'    => 5,
		'media_exclusions'     => "logo\navatar\ncaptcha",
		'js_exclusions'        => "jquery\nwp-includes/js/jquery\nwoocommerce\ncart\ncheckout\ngoogletagmanager\ngtm\nrecaptcha\nstripe\npaypal\nrevslider\nrev_slider\nrs6\nsr7\nthemepunch\nrbtools\nslider-revolution\nrevolution\nslick\nswiper\nowl.carousel\nsmartmenus\nsuperfish\nbootstrap\ndropdown\nmenu-image\nmenu\nnavigation\nhoverIntent",
	);
}

/**
 * Get page optimization settings.
 *
 * @return array
 */
function scm_get_page_optimization_settings() {
	$settings = (array) get_option( 'scm_option_page_optimization', array() );
	$settings = array_merge( scm_get_default_page_optimization_settings(), $settings );

	$settings['critical_image_count'] = max( 0, min( 5, (int) $settings['critical_image_count'] ) );
	$settings['bun_path']             = trim( (string) $settings['bun_path'] );
	$settings['purgecss_path']        = trim( (string) $settings['purgecss_path'] );
	$settings['external_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['external_ucss_max_file_size'] ) );
	$settings['image_quality']        = max( 1, min( 100, (int) $settings['image_quality'] ) );
	$settings['image_batch_size']     = max( 1, min( 20, (int) $settings['image_batch_size'] ) );
	$settings['image_formats']        = scm_normalize_image_optimizer_formats( $settings['image_formats'] );
	$settings['image_primary_format'] = in_array( sanitize_key( (string) $settings['image_primary_format'] ), $settings['image_formats'], true ) ? sanitize_key( (string) $settings['image_primary_format'] ) : reset( $settings['image_formats'] );
	$settings['ucss_safelist']        = scm_merge_textarea_lines( $settings['ucss_safelist'], scm_get_protected_ucss_safelist() );
	$settings['js_exclusions']        = scm_merge_textarea_lines( $settings['js_exclusions'], scm_get_protected_js_exclusions() );

	return $settings;
}

/**
 * Get CSS selectors/classes that stay protected for interactive frontends.
 *
 * @return array
 */
function scm_get_protected_ucss_safelist() {
	return array(
		'active',
		'open',
		'show',
		'is-active',
		'is-open',
		'is-visible',
		'current',
		'menu-item',
		'menu-item-has-children',
		'current-menu-item',
		'current-menu-parent',
		'current-menu-ancestor',
		'sub-menu',
		'dropdown',
		'dropdown-menu',
		'dropdown-toggle',
		'sfHover',
		'slick',
		'slick-active',
		'slick-current',
		'slick-initialized',
		'slick-slide',
		'swiper',
		'swiper-slide',
		'swiper-wrapper',
		'swiper-button-next',
		'swiper-button-prev',
		'revslider',
		'rev_slider',
		'rs-module',
		'rs-layer',
		'rs-slide',
		'sr7',
		'tp-caption',
	);
}

/**
 * Get script keywords that should never be deferred automatically.
 *
 * @return array
 */
function scm_get_protected_js_exclusions() {
	return array(
		'jquery',
		'wp-includes/js/jquery',
		'woocommerce',
		'cart',
		'checkout',
		'googletagmanager',
		'gtm',
		'recaptcha',
		'stripe',
		'paypal',
		'revslider',
		'rev_slider',
		'rs6',
		'sr7',
		'themepunch',
		'rbtools',
		'slider-revolution',
		'revolution',
		'slick',
		'swiper',
		'owl.carousel',
		'smartmenus',
		'superfish',
		'bootstrap',
		'dropdown',
		'menu-image',
		'menu',
		'navigation',
		'hoverIntent',
	);
}

/**
 * Normalize configured image optimizer formats.
 *
 * @param array|string $formats Raw formats.
 *
 * @return array
 */
function scm_normalize_image_optimizer_formats( $formats ) {
	if ( ! is_array( $formats ) ) {
		$formats = preg_split( '/[\s,]+/', (string) $formats );
	}

	$allowed = array( 'webp' );
	$clean   = array();

	foreach ( $formats as $format ) {
		$format = strtolower( sanitize_key( (string) $format ) );

		if ( in_array( $format, $allowed, true ) && ! in_array( $format, $clean, true ) ) {
			$clean[] = $format;
		}
	}

	return ! empty( $clean ) ? $clean : array( 'webp' );
}

/**
 * Check whether page optimization is enabled.
 *
 * @return bool
 */
function scm_is_page_optimization_enabled() {
	$settings = scm_get_page_optimization_settings();

	return 'yes' === $settings['status'];
}

/**
 * Get the optimization report directory.
 *
 * @return string
 */
function scm_get_page_optimization_report_dir() {
	return scm_get_upload_dir() . '/optimization_reports';
}

/**
 * Get local optimizer workspace root.
 *
 * @return string
 */
function scm_get_page_optimization_work_dir() {
	return scm_get_upload_dir() . '/optimization_work';
}

/**
 * Get latest optimization runtime metadata.
 *
 * @return array
 */
function scm_get_page_optimization_runtime() {
	return isset( $GLOBALS['scm_page_optimization_runtime'] ) && is_array( $GLOBALS['scm_page_optimization_runtime'] )
		? $GLOBALS['scm_page_optimization_runtime']
		: array();
}

/**
 * Reset optimization runtime metadata.
 *
 * @return void
 */
function scm_reset_page_optimization_runtime() {
	$GLOBALS['scm_page_optimization_runtime'] = array();
}

/**
 * Store optimization runtime metadata.
 *
 * @param string $key   Runtime key.
 * @param array  $value Runtime payload.
 *
 * @return void
 */
function scm_set_page_optimization_runtime( $key, $value ) {
	if ( ! isset( $GLOBALS['scm_page_optimization_runtime'] ) || ! is_array( $GLOBALS['scm_page_optimization_runtime'] ) ) {
		$GLOBALS['scm_page_optimization_runtime'] = array();
	}

	$GLOBALS['scm_page_optimization_runtime'][ $key ] = $value;
}

/**
 * Count inline style bytes.
 *
 * @param string $html HTML content.
 *
 * @return int
 */
function scm_get_inline_style_bytes( $html ) {
	if ( ! preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', (string) $html, $matches ) ) {
		return 0;
	}

	return strlen( implode( '', $matches[1] ) );
}

/**
 * Count regex matches inside HTML.
 *
 * @param string $pattern Regex pattern.
 * @param string $html    HTML content.
 *
 * @return int
 */
function scm_count_html_matches( $pattern, $html ) {
	return (int) preg_match_all( $pattern, (string) $html, $matches );
}

/**
 * Build one feature report entry.
 *
 * @param bool   $enabled Whether the feature is enabled.
 * @param string $status  Feature status.
 * @param string $detail  Feature detail.
 * @param array  $metrics Feature metrics.
 *
 * @return array
 */
function scm_build_page_optimization_feature_report( $enabled, $status, $detail, $metrics = array() ) {
	return array(
		'enabled' => (bool) $enabled,
		'status'  => $status,
		'detail'  => $detail,
		'metrics' => is_array( $metrics ) ? $metrics : array(),
	);
}

/**
 * Build an optimization report for one cached page.
 *
 * @param string $before    HTML before optimization.
 * @param string $after     HTML after optimization.
 * @param array  $settings  Optimization settings.
 * @param string $uri       Request URI.
 * @param string $data_type Cache data type.
 *
 * @return array
 */
function scm_build_page_optimization_report( $before, $after, $settings, $uri = '', $data_type = '' ) {
	$before_bytes    = strlen( (string) $before );
	$after_bytes     = strlen( (string) $after );
	$comments_before = scm_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $before );
	$comments_after  = scm_count_html_matches( '/<!--(?!\s*\[if\b).*?-->/is', $after );
	$styles_before   = scm_get_inline_style_bytes( $before );
	$styles_after    = scm_get_inline_style_bytes( $after );
	$lazy_before     = scm_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $before );
	$lazy_after      = scm_count_html_matches( '/\bloading\s*=\s*(["\'])lazy\1/i', $after );
	$critical_before = scm_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $before );
	$critical_after  = scm_count_html_matches( '/\bfetchpriority\s*=\s*(["\'])high\1/i', $after );
	$fonts_before    = scm_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $before );
	$fonts_after     = scm_count_html_matches( '/<link\b[^>]*\brel\s*=\s*(["\'])(?:preconnect|dns-prefetch)\1[^>]*fonts\.(?:googleapis|gstatic)\.com/i', $after );
	$defer_before    = scm_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $before );
	$defer_after     = scm_count_html_matches( '/<script\b[^>]*\bdefer\b/i', $after );
	$features        = array();
	$runtime         = scm_get_page_optimization_runtime();

	$features['minify_html'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['minify_html'],
		'yes' !== $settings['minify_html'] ? 'disabled' : ( $after_bytes < $before_bytes ? 'applied' : 'no_change' ),
		'yes' !== $settings['minify_html']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before size, 2: after size. */
				__( '%1$s before, %2$s after.', 'ams-cache' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 )
			)
	);

	$features['remove_comments'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['remove_comments'],
		'yes' !== $settings['remove_comments'] ? 'disabled' : ( $comments_after < $comments_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['remove_comments']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before comments, 2: after comments. */
				__( '%1$s comments before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $comments_before ),
				number_format_i18n( $comments_after )
			)
	);

	$features['minify_inline_css'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['minify_inline_css'],
		'yes' !== $settings['minify_inline_css'] ? 'disabled' : ( $styles_after < $styles_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['minify_inline_css']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before size, 2: after size. */
				__( '%1$s inline CSS before, %2$s after.', 'ams-cache' ),
				size_format( $styles_before, 2 ),
				size_format( $styles_after, 2 )
			)
	);

	$features['lazy_media'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['lazy_media'],
		'yes' !== $settings['lazy_media'] ? 'disabled' : ( $lazy_after > $lazy_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['lazy_media']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before tags, 2: after tags. */
				__( '%1$s lazy tags before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $lazy_before ),
				number_format_i18n( $lazy_after )
			)
	);

	$features['critical_images'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['critical_images'],
		'yes' !== $settings['critical_images'] ? 'disabled' : ( $critical_after > $critical_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['critical_images']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before tags, 2: after tags. */
				__( '%1$s priority images before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $critical_before ),
				number_format_i18n( $critical_after )
			)
	);

	$features['preconnect_fonts'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['preconnect_fonts'],
		'yes' !== $settings['preconnect_fonts'] ? 'disabled' : ( $fonts_after > $fonts_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['preconnect_fonts']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before links, 2: after links. */
				__( '%1$s font hints before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $fonts_before ),
				number_format_i18n( $fonts_after )
			)
	);

	$features['defer_js'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['defer_js'],
		'yes' !== $settings['defer_js'] ? 'disabled' : ( $defer_after > $defer_before ? 'applied' : 'no_change' ),
		'yes' !== $settings['defer_js']
			? __( 'Disabled.', 'ams-cache' )
			: sprintf(
				/* translators: 1: before scripts, 2: after scripts. */
				__( '%1$s deferred scripts before, %2$s after.', 'ams-cache' ),
				number_format_i18n( $defer_before ),
				number_format_i18n( $defer_after )
			)
	);

	$features['external_ucss'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['external_ucss'],
		'yes' !== $settings['external_ucss']
			? 'disabled'
			: ( isset( $runtime['external_ucss']['status'] ) ? $runtime['external_ucss']['status'] : 'failed' ),
		'yes' === $settings['external_ucss']
			? ( isset( $runtime['external_ucss']['detail'] ) ? $runtime['external_ucss']['detail'] : __( 'External UCSS engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['external_ucss']['metrics'] ) ? $runtime['external_ucss']['metrics'] : array()
	);

	$features['local_ucss'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['local_ucss'],
		'yes' !== $settings['local_ucss']
			? 'disabled'
			: ( isset( $runtime['local_ucss']['status'] ) ? $runtime['local_ucss']['status'] : 'failed' ),
		'yes' === $settings['local_ucss']
			? ( isset( $runtime['local_ucss']['detail'] ) ? $runtime['local_ucss']['detail'] : __( 'Local UCSS engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['local_ucss']['metrics'] ) ? $runtime['local_ucss']['metrics'] : array()
	);

	$features['js_analysis'] = scm_build_page_optimization_feature_report(
		'yes' === $settings['js_analysis'],
		'yes' !== $settings['js_analysis']
			? 'disabled'
			: ( isset( $runtime['js_analysis']['status'] ) ? $runtime['js_analysis']['status'] : 'failed' ),
		'yes' === $settings['js_analysis']
			? ( isset( $runtime['js_analysis']['detail'] ) ? $runtime['js_analysis']['detail'] : __( 'JS analysis engine did not return a result.', 'ams-cache' ) )
			: __( 'Disabled.', 'ams-cache' ),
		isset( $runtime['js_analysis']['metrics'] ) ? $runtime['js_analysis']['metrics'] : array()
	);

	$applied_count = 0;

	foreach ( $features as $feature ) {
		if ( 'applied' === $feature['status'] ) {
			$applied_count++;
		}
	}

	$saved_bytes    = max( 0, $before_bytes - $after_bytes );
	$expanded_bytes = max( 0, $after_bytes - $before_bytes );

	return array(
		'uri'            => scm_normalize_cache_uri( $uri ),
		'dataType'       => $data_type,
		'generatedAt'    => current_time( 'mysql' ),
		'generatedUnix'  => time(),
		'beforeBytes'    => $before_bytes,
		'beforeLabel'    => size_format( $before_bytes, 2 ),
		'afterBytes'     => $after_bytes,
		'afterLabel'     => size_format( $after_bytes, 2 ),
		'savedBytes'     => $saved_bytes,
		'savedLabel'     => size_format( $saved_bytes, 2 ),
		'savedPercent'   => $before_bytes > 0 ? round( ( $saved_bytes / $before_bytes ) * 100, 2 ) : 0,
		'expandedBytes'  => $expanded_bytes,
		'expandedLabel'  => size_format( $expanded_bytes, 2 ),
		'expandedPercent' => $before_bytes > 0 ? round( ( $expanded_bytes / $before_bytes ) * 100, 2 ) : 0,
		'appliedCount'   => $applied_count,
		'overallStatus'  => ! scm_is_page_optimization_enabled() ? 'disabled' : ( $applied_count > 0 ? 'applied' : 'no_change' ),
		'features'       => $features,
	);
}

/**
 * Store one page optimization report.
 *
 * @param string $uri    Request URI.
 * @param array  $report Report payload.
 *
 * @return void
 */
function scm_write_page_optimization_report( $uri, $report ) {
	$dir = scm_get_page_optimization_report_dir();

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$key  = md5( scm_normalize_cache_uri( $uri ) );
	$file = trailingslashit( $dir ) . $key . '.json';

	file_put_contents( $file, wp_json_encode( $report ) );
	scm_prune_page_optimization_reports();
}

/**
 * Keep the report directory bounded for large sites.
 *
 * @param int $limit Maximum report files to keep.
 *
 * @return int Number of deleted reports.
 */
function scm_prune_page_optimization_reports( $limit = 200 ) {
	$dir     = scm_get_page_optimization_report_dir();
	$deleted = 0;
	$limit   = max( 20, min( 1000, (int) apply_filters( 'scm_page_optimization_report_limit', $limit ) ) );

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$files = array();

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( $file->isFile() && 'json' === $file->getExtension() ) {
			$files[] = array(
				'path'  => $file->getPathname(),
				'mtime' => $file->getMTime(),
			);
		}
	}

	if ( count( $files ) <= $limit ) {
		return 0;
	}

	usort(
		$files,
		function ( $a, $b ) {
			return (int) $a['mtime'] - (int) $b['mtime'];
		}
	);

	foreach ( array_slice( $files, 0, count( $files ) - $limit ) as $file ) {
		if ( unlink( $file['path'] ) ) {
			$deleted++;
		}
	}

	return $deleted;
}

/**
 * Get latest page optimization reports.
 *
 * @param int $limit Maximum reports.
 * @param int $offset Number of reports to skip.
 *
 * @return array
 */
function scm_get_page_optimization_reports( $limit = 20, $offset = 0 ) {
	$dir     = scm_get_page_optimization_report_dir();
	$reports = array();
	$limit   = max( 1, min( 100, (int) $limit ) );
	$offset  = max( 0, (int) $offset );

	if ( ! is_dir( $dir ) ) {
		return $reports;
	}

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$data = json_decode( file_get_contents( $file->getPathname() ), true );

		if ( ! is_array( $data ) ) {
			continue;
		}

		$data['_mtime'] = $file->getMTime();
		$reports[]      = $data;
	}

	usort(
		$reports,
		function ( $a, $b ) {
			return (int) $b['_mtime'] - (int) $a['_mtime'];
		}
	);

	return array_slice( $reports, $offset, $limit );
}

/**
 * Count stored page optimization reports.
 *
 * @return int
 */
function scm_count_page_optimization_reports() {
	$dir   = scm_get_page_optimization_report_dir();
	$count = 0;

	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	foreach ( new DirectoryIterator( $dir ) as $file ) {
		if ( $file->isFile() && 'json' === $file->getExtension() ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Get newline-separated option as list.
 *
 * @param string $value Raw textarea value.
 *
 * @return array
 */
function scm_get_lines_from_textarea( $value ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	$lines = array_map( 'trim', $lines );

	return array_values( array_filter( $lines ) );
}

/**
 * Merge textarea lines while preserving user order.
 *
 * @param string|array $value Existing value.
 * @param array        $extra Extra protected lines.
 *
 * @return string
 */
function scm_merge_textarea_lines( $value, $extra ) {
	$lines = is_array( $value ) ? $value : scm_get_lines_from_textarea( $value );

	foreach ( $extra as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			continue;
		}

		$exists = false;

		foreach ( $lines as $existing ) {
			if ( 0 === strcasecmp( (string) $existing, $line ) ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			$lines[] = $line;
		}
	}

	return implode( "\n", array_values( array_filter( array_map( 'trim', $lines ) ) ) );
}

/**
 * Create denied local optimizer workspace.
 *
 * @param string $prefix Workspace prefix.
 *
 * @return string
 */
function scm_create_page_optimization_workspace( $prefix ) {
	$root = scm_get_page_optimization_work_dir();

	if ( ! is_dir( $root ) ) {
		wp_mkdir_p( $root );
	}

	if ( is_dir( $root ) ) {
		if ( ! file_exists( trailingslashit( $root ) . 'index.html' ) ) {
			file_put_contents( trailingslashit( $root ) . 'index.html', '' );
		}

		if ( ! file_exists( trailingslashit( $root ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $root ) . '.htaccess', "Require all denied\nDeny from all" );
		}
	}

	$dir = trailingslashit( $root ) . sanitize_file_name( $prefix . '-' . wp_generate_uuid4() );

	if ( wp_mkdir_p( $dir ) ) {
		return $dir;
	}

	return '';
}

/**
 * Delete one local optimizer workspace.
 *
 * @param string $dir Workspace path.
 *
 * @return void
 */
function scm_delete_page_optimization_workspace( $dir ) {
	$root = realpath( scm_get_page_optimization_work_dir() );
	$path = realpath( $dir );

	if ( false === $root || false === $path || 0 !== strpos( $path, $root ) || ! is_dir( $path ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}

	rmdir( $path );
}

/**
 * Check if HTML tag matches exclusion keywords.
 *
 * @param string $tag      HTML tag.
 * @param array  $keywords Exclusion keywords.
 *
 * @return bool
 */
function scm_html_tag_is_excluded( $tag, $keywords ) {
	foreach ( $keywords as $keyword ) {
		if ( '' !== $keyword && false !== stripos( $tag, $keyword ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve one same-site asset URL to a readable local path.
 *
 * @param string $url Asset URL.
 *
 * @return string
 */
function scm_local_asset_url_to_path( $url ) {
	$url       = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	$site_url  = home_url( '/' );
	$site_host = parse_url( $site_url, PHP_URL_HOST );
	$url_host  = parse_url( $url, PHP_URL_HOST );
	$path      = parse_url( $url, PHP_URL_PATH );

	if (
		'' === $url ||
		(
			false !== strpos( $url, '//' ) &&
			! empty( $url_host ) &&
			strtolower( (string) $url_host ) !== strtolower( (string) $site_host )
		)
	) {
		return '';
	}

	if ( empty( $path ) ) {
		$path = $url;
	}

	$path = ltrim( wp_normalize_path( rawurldecode( $path ) ), '/' );

	if ( '' === $path || false !== strpos( $path, '..' ) ) {
		return '';
	}

	$full_path = wp_normalize_path( ABSPATH . $path );
	$root_path = wp_normalize_path( ABSPATH );

	if ( 0 !== strpos( $full_path, $root_path ) ) {
		return '';
	}

	return $full_path;
}

/**
 * Get HTML attribute value.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return string
 */
function scm_html_get_attribute( $tag, $attr ) {
	$attr = preg_quote( $attr, '/' );

	if ( preg_match( '/\s' . $attr . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match ) ) {
		return $match[2];
	}

	if ( preg_match( '/\s' . $attr . '\s*=\s*([^\s>]+)/is', $tag, $match ) ) {
		return $match[1];
	}

	return '';
}

/**
 * Check HTML attribute exists.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return bool
 */
function scm_html_has_attribute( $tag, $attr ) {
	return preg_match( '/\s' . preg_quote( $attr, '/' ) . '(?:\s*=|\s|\/?>)/i', $tag ) > 0;
}

/**
 * Set HTML attribute.
 *
 * @param string $tag   HTML tag.
 * @param string $attr  Attribute name.
 * @param string $value Attribute value.
 *
 * @return string
 */
function scm_html_set_attribute( $tag, $attr, $value ) {
	$escaped = htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	$pattern = '/\s' . preg_quote( $attr, '/' ) . '\s*=\s*(["\']).*?\1/is';

	if ( preg_match( $pattern, $tag ) ) {
		return preg_replace( $pattern, ' ' . $attr . '="' . $escaped . '"', $tag, 1 );
	}

	return preg_replace( '/\s*\/?>$/', ' ' . $attr . '="' . $escaped . '">', $tag, 1 );
}

/**
 * Add boolean HTML attribute.
 *
 * @param string $tag  HTML tag.
 * @param string $attr Attribute name.
 *
 * @return string
 */
function scm_html_add_boolean_attribute( $tag, $attr ) {
	if ( scm_html_has_attribute( $tag, $attr ) ) {
		return $tag;
	}

	return preg_replace( '/\s*\/?>$/', ' ' . $attr . '>', $tag, 1 );
}

/**
 * Minify CSS content.
 *
 * @param string $css CSS content.
 *
 * @return string
 */
function scm_minify_css( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $css );
	$css = str_replace( ';}', '}', $css );

	return trim( $css );
}

/**
 * Remove safe HTML comments.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function scm_remove_html_comments( $html ) {
	return preg_replace_callback(
		'/<!--(.*?)-->/s',
		function ( $match ) {
			$comment = trim( $match[1] );

			if (
				0 === stripos( $comment, '[if' ) ||
				0 === stripos( $comment, '<![endif' ) ||
				0 === strpos( $comment, 'wp:' ) ||
				0 === strpos( $comment, '/wp:' )
			) {
				return $match[0];
			}

			return '';
		},
		$html
	);
}

/**
 * Minify style blocks.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function scm_minify_inline_css_blocks( $html ) {
	return preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		function ( $match ) {
			$minified = scm_minify_css( $match[2] );

			if ( strlen( $minified ) >= strlen( (string) $match[2] ) ) {
				return $match[0];
			}

			return '<style' . $match[1] . '>' . $minified . '</style>';
		},
		$html
	);
}

/**
 * Minify HTML outside protected tags.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function scm_minify_html( $html ) {
	$original = (string) $html;
	$protected = array();

	$html = preg_replace_callback(
		'#<(script|style|pre|textarea)\b[^>]*>.*?</\1>#is',
		function ( $match ) use ( &$protected ) {
			$key = '%%SCM_PROTECTED_' . count( $protected ) . '%%';
			$protected[ $key ] = $match[0];

			return $key;
		},
		$html
	);

	$html = preg_replace( '/>\s+</', '><', $html );
	$html = preg_replace( '/[ \t]+/', ' ', $html );
	$html = trim( $html );

	$html = strtr( $html, $protected );

	return strlen( $html ) < strlen( $original ) ? $html : $original;
}

/**
 * Add media loading attributes and optional LCP preload.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_optimize_media_tags( $html, $settings ) {
	$image_index = 0;
	$preloads    = array();
	$exclusions  = scm_get_lines_from_textarea( $settings['media_exclusions'] );
	$lcp_count   = (int) $settings['critical_image_count'];

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $match ) use ( &$image_index, &$preloads, $exclusions, $settings, $lcp_count ) {
				$tag = $match[0];

				if ( scm_html_tag_is_excluded( $tag, $exclusions ) ) {
					return $tag;
				}

				$image_index++;

				if ( 'yes' === $settings['critical_images'] && $image_index <= $lcp_count ) {
					$tag = scm_html_set_attribute( $tag, 'loading', 'eager' );
					$tag = scm_html_set_attribute( $tag, 'fetchpriority', 'high' );

					$src    = scm_html_get_attribute( $tag, 'src' );
					$srcset = scm_html_get_attribute( $tag, 'srcset' );
					$sizes  = scm_html_get_attribute( $tag, 'sizes' );

					if ( '' !== $src ) {
						$preloads[ $src ] = array(
							'src'    => $src,
							'srcset' => $srcset,
							'sizes'  => $sizes,
						);
					}
				} elseif ( 'yes' === $settings['lazy_media'] && ! scm_html_has_attribute( $tag, 'loading' ) ) {
					$tag = scm_html_set_attribute( $tag, 'loading', 'lazy' );
				}

				if ( ! scm_html_has_attribute( $tag, 'decoding' ) ) {
					$tag = scm_html_set_attribute( $tag, 'decoding', 'async' );
				}

				return $tag;
			},
			$html
		);
	}

	if ( 'yes' === $settings['lazy_media'] ) {
		$html = preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			function ( $match ) use ( $exclusions ) {
				$tag = $match[0];

				if ( scm_html_tag_is_excluded( $tag, $exclusions ) || scm_html_has_attribute( $tag, 'loading' ) ) {
					return $tag;
				}

				return scm_html_set_attribute( $tag, 'loading', 'lazy' );
			},
			$html
		);
	}

	if ( ! empty( $preloads ) ) {
		$links = '';

		foreach ( $preloads as $preload ) {
			if ( false === strpos( $html, 'href="' . $preload['src'] . '"' ) ) {
				$link = '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url( $preload['src'] ) . '"';

				if ( '' !== $preload['srcset'] ) {
					$link .= ' imagesrcset="' . esc_attr( $preload['srcset'] ) . '"';
				}

				if ( '' !== $preload['sizes'] ) {
					$link .= ' imagesizes="' . esc_attr( $preload['sizes'] ) . '"';
				}

				$links .= $link . '>';
			}
		}

		if ( '' !== $links ) {
			$html = preg_replace( '#</head>#i', $links . '</head>', $html, 1 );
		}
	}

	return $html;
}

/**
 * Check if image optimization is enabled.
 *
 * @return bool
 */
function scm_is_image_optimization_enabled() {
	$settings = scm_get_page_optimization_settings();

	return 'yes' === $settings['image_optimization'];
}

/**
 * Get mime type for generated image format.
 *
 * @param string $format Image format.
 *
 * @return string
 */
function scm_get_image_optimizer_mime_type( $format ) {
	return 'image/webp';
}

/**
 * Validate a tiny inline image placeholder generated by the local optimizer.
 *
 * @param string $data_url Placeholder data URL.
 *
 * @return bool
 */
function scm_is_safe_image_placeholder_data_url( $data_url ) {
	$data_url = trim( (string) $data_url );

	if ( strlen( $data_url ) > 2048 ) {
		return false;
	}

	return 1 === preg_match( '#^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $data_url );
}

/**
 * Check if WordPress image editor can save a format.
 *
 * @param string $format Image format.
 *
 * @return bool
 */
function scm_image_editor_supports_format( $format ) {
	$mime = scm_get_image_optimizer_mime_type( $format );

	if ( function_exists( 'wp_image_editor_supports' ) ) {
		return wp_image_editor_supports(
			array(
				'mime_type' => $mime,
			)
		);
	}

	return true;
}

/**
 * Get optional Bun image optimizer script path.
 *
 * @return string
 */
function scm_get_bun_image_optimizer_script() {
	return SCM_PLUGIN_DIR . 'inc/assets/bun/image-optimizer.mjs';
}

/**
 * Check optional Bun image optimizer.
 *
 * @return array
 */
function scm_check_bun_image_optimizer() {
	static $check = null;

	if ( null !== $check ) {
		return $check;
	}

	$settings = scm_get_page_optimization_settings();
	$script   = scm_get_bun_image_optimizer_script();
	$bun      = scm_get_executable_command( $settings['bun_path'] );

	if ( '' === $bun || ! file_exists( $script ) ) {
		$check = array(
			'passed'  => false,
			'detail'  => __( 'Bun optimizer script is not available.', 'ams-cache' ),
			'formats' => array(),
		);
		return $check;
	}

	if ( ! scm_is_shell_exec_available() ) {
		$check = array(
			'passed'  => false,
			'detail'  => __( 'shell_exec is disabled.', 'ams-cache' ),
			'formats' => array(),
		);
		return $check;
	}

	$result = scm_run_local_optimizer_command( $bun . ' ' . escapeshellarg( $script ) . ' --check' );
	$data   = json_decode( trim( (string) $result['output'] ), true );

	if ( ! $result['passed'] || empty( $data['ok'] ) ) {
		$check = array(
			'passed'  => false,
			'detail'  => '' !== trim( (string) $result['output'] ) ? strtok( trim( (string) $result['output'] ), "\r\n" ) : __( 'Install Bun to enable the image optimizer.', 'ams-cache' ),
			'formats' => array(),
		);
		return $check;
	}

	$formats     = array();
	$format_keys = array();

	if ( ! empty( $data['webp'] ) ) {
		$formats[]     = 'WebP';
		$format_keys[] = 'webp';
	}

	$check = array(
		'passed'  => ! empty( $formats ),
		'detail'  => sprintf(
			/* translators: 1: Bun version, 2: formats, 3: placeholder support. */
			__( 'Bun Image %1$s; %2$s output; placeholders %3$s.', 'ams-cache' ),
			isset( $data['bun'] ) ? $data['bun'] : __( 'unknown', 'ams-cache' ),
			! empty( $formats ) ? implode( ', ', $formats ) : __( 'no supported', 'ams-cache' ),
			! empty( $data['placeholder'] ) ? __( 'enabled', 'ams-cache' ) : __( 'unavailable', 'ams-cache' )
		),
		'formats' => $format_keys,
	);

	return $check;
}

/**
 * Convert an uploads file path to a relative uploads path.
 *
 * @param string $path File path.
 *
 * @return string
 */
function scm_get_upload_relative_file_path( $path ) {
	$uploads = wp_get_upload_dir();
	$base    = wp_normalize_path( $uploads['basedir'] );
	$path    = wp_normalize_path( (string) $path );

	if ( '' === $base || '' === $path ) {
		return '';
	}

	$base = rtrim( $base, '/' ) . '/';

	if ( 0 !== strpos( $path, $base ) ) {
		return '';
	}

	return ltrim( substr( $path, strlen( $base ) ), '/' );
}

/**
 * Detect if attachment is offloaded to external storage (WP Offload Media, etc.).
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return array
 */
function scm_get_attachment_offload_info( $attachment_id ) {
	$info = array(
		'offloaded'  => false,
		'provider'   => '',
		'remote_url' => '',
		'can_download' => false,
	);

	// KH Offloader detection.
	$kho_offloaded = get_post_meta( $attachment_id, 'khs3data_offloaded', true );

	if ( $kho_offloaded ) {
		$info['offloaded']  = true;
		$info['provider']   = 'KH Offloader';
		$info['can_download'] = true;

		$kho_provider = get_post_meta( $attachment_id, 'khs3data_provider', true );
		$kho_bucket   = get_post_meta( $attachment_id, 'khs3data_bucket', true );

		if ( ! empty( $kho_provider ) ) {
			$info['provider'] = 'KH Offloader — ' . $kho_provider;
		}

		$info['remote_url'] = wp_get_attachment_url( $attachment_id );

		return $info;
	}

	// WP Offload Media (amazonS3_info).
	$s3_info = get_post_meta( $attachment_id, 'amazonS3_info', true );

	if ( ! empty( $s3_info['bucket'] ) ) {
		$info['offloaded'] = true;
		$info['provider']  = 'WP Offload Media';

		if ( ! empty( $s3_info['region'] ) && ! empty( $s3_info['key'] ) ) {
			$info['remote_url'] = 'https://s3.' . $s3_info['region'] . '.amazonaws.com/' . $s3_info['bucket'] . '/' . ltrim( $s3_info['key'], '/' );
		}
	}

	// WP Offload Media v3+ (as3cf_files).
	$as3cf_files = get_post_meta( $attachment_id, 'as3cf_files', true );

	if ( ! empty( $as3cf_files ) && is_array( $as3cf_files ) ) {
		$info['offloaded'] = true;
		$info['provider']  = '' !== $info['provider'] ? $info['provider'] : 'Offload Media';
	}

	// Fallback: missing local file with different URL host.
	if ( ! $info['offloaded'] ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $metadata['file'] ) ) {
			$local_path = trailingslashit( wp_get_upload_dir()['basedir'] ) . $metadata['file'];

			if ( ! is_file( $local_path ) ) {
				$source_url = wp_get_attachment_url( $attachment_id );

				if ( ! empty( $source_url ) ) {
					$upload_host = parse_url( wp_get_upload_dir()['baseurl'], PHP_URL_HOST );
					$source_host = parse_url( $source_url, PHP_URL_HOST );

					if ( $source_host && $upload_host && strtolower( $source_host ) !== strtolower( $upload_host ) ) {
						$info['offloaded']  = true;
						$info['provider']   = 'Remote CDN';
						$info['remote_url'] = $source_url;
						$info['can_download'] = ! empty( $source_url );
					}
				}
			}
		}
	}

	return $info;
}

/**
 * Check whether a file can be optimized safely.
 *
 * @param string $path File path.
 *
 * @return bool
 */
function scm_is_safe_optimizable_image_file( $path ) {
	$real_path = realpath( $path );
	$uploads   = wp_get_upload_dir();
	$base      = realpath( $uploads['basedir'] );

	if ( false === $base ) {
		return false;
	}

	$base = rtrim( wp_normalize_path( $base ), '/' ) . '/';

	if ( false === $real_path ) {
		$normalized = wp_normalize_path( $path );

		if ( 0 !== strpos( $normalized, $base ) ) {
			return false;
		}

		if ( ! is_file( $normalized ) || ! is_readable( $normalized ) ) {
			return false;
		}

		$real_path = $normalized;
	} else {
		$real_path = wp_normalize_path( $real_path );

		if ( 0 !== strpos( $real_path, $base ) ) {
			return false;
		}
	}

	if ( ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
		return false;
	}

	$extension = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );

	if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
		return false;
	}

	$filetype = wp_check_filetype( $real_path );

	return ! empty( $filetype['type'] ) && in_array( $filetype['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true );
}

/**
 * Download an offloaded attachment file from cloud to a local temp location.
 *
 * The downloaded file is stored inside the attachment's upload directory so it is
 * visible to the Bun image optimizer and KH Offloader's re-upload hooks.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $relative_file Relative file path from metadata (e.g. "2024/05/photo.jpg").
 * @param string $remote_url    Public cloud URL for the file.
 *
 * @return string|false Local file path on success, false on failure.
 */
function scm_download_offloaded_attachment_image( $attachment_id, $relative_file, $remote_url ) {
	if ( empty( $remote_url ) || empty( $relative_file ) ) {
		return false;
	}

	$uploads     = wp_get_upload_dir();
	$target_path = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . ltrim( $relative_file, '/' ) );

	// Already local.
	if ( is_file( $target_path ) && filesize( $target_path ) > 0 ) {
		return $target_path;
	}

	$target_dir = dirname( $target_path );

	if ( ! is_dir( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	// Include WordPress HTTP helpers.
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Use wp_remote_get for better error handling than download_url.
	$response = wp_remote_get(
		$remote_url,
		array(
			'timeout'     => 60,
			'redirection' => 5,
			'stream'      => true,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		error_log( sprintf(
			'[AMS Cache] Failed to download offloaded file for attachment %d from %s: %s',
			$attachment_id,
			$remote_url,
			is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response )
		) );
		return false;
	}

	$body = wp_remote_retrieve_body( $response );

	if ( '' === $body ) {
		// Fallback: try writing via stream copy if body is empty (stream mode).
		$tmp = download_url( $remote_url, 60 );

		if ( is_wp_error( $tmp ) ) {
			error_log( sprintf(
				'[AMS Cache] download_url fallback failed for attachment %d: %s',
				$attachment_id,
				$tmp->get_error_message()
			) );
			return false;
		}

		if ( ! copy( $tmp, $target_path ) ) {
			@unlink( $tmp );
			return false;
		}

		@unlink( $tmp );
	} else {
		$written = file_put_contents( $target_path, $body );

		if ( false === $written || 0 === $written ) {
			error_log( sprintf(
				'[AMS Cache] Could not write downloaded file for attachment %d to %s',
				$attachment_id,
				$target_path
			) );
			return false;
		}
	}

	if ( ! is_file( $target_path ) || 0 === filesize( $target_path ) ) {
		return false;
	}

	// Mark this file as a temporary download so KH Offloader can re-offload it.
	update_post_meta( $attachment_id, '_ams_cache_offload_temp_download', 1 );

	return $target_path;
}

/**
 * Build optimizer source list for an attachment.
 *
 * @param int   $attachment_id Attachment ID.
 * @param array $metadata      Attachment metadata.
 *
 * @return array
 */
function scm_get_attachment_image_optimizer_sources( $attachment_id, $metadata = array() ) {
	if ( empty( $metadata ) || ! is_array( $metadata ) ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
	}

	if ( empty( $metadata['file'] ) ) {
		return array();
	}

	$uploads     = wp_get_upload_dir();
	$base_dir    = trailingslashit( $uploads['basedir'] );
	$base_rel    = dirname( $metadata['file'] );
	$sources     = array();
	$seen        = array();
	$offload_info = scm_get_attachment_offload_info( $attachment_id );

	$items = array(
		'full' => array(
			'file'   => basename( $metadata['file'] ),
			'width'  => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
			'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		),
	);

	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size_key => $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$items[ $size_key ] = array(
				'file'   => $size['file'],
				'width'  => isset( $size['width'] ) ? (int) $size['width'] : 0,
				'height' => isset( $size['height'] ) ? (int) $size['height'] : 0,
			);
		}
	}

	foreach ( $items as $key => $item ) {
		$relative = ltrim( ( '.' === $base_rel ? '' : $base_rel . '/' ) . $item['file'], '/' );
		$path     = wp_normalize_path( $base_dir . $relative );

		if ( isset( $seen[ $relative ] ) ) {
			continue;
		}

		// If the file is missing and this attachment is offloaded, try to download it.
		if ( ! is_file( $path ) && $offload_info['offloaded'] && $offload_info['can_download'] && ! empty( $offload_info['remote_url'] ) ) {
			$remote_url = $offload_info['remote_url'];

			// For sized files, build the cloud URL by replacing the filename.
			if ( 'full' !== $key ) {
				$remote_url = str_replace(
					basename( $offload_info['remote_url'] ),
					$item['file'],
					$offload_info['remote_url']
				);
			}

			$downloaded = scm_download_offloaded_attachment_image( $attachment_id, $relative, $remote_url );

			if ( false === $downloaded ) {
				// Download failed — skip this size.
				continue;
			}

			$path = $downloaded;
		}

		if ( ! is_file( $path ) ) {
			continue;
		}

		if ( ! scm_is_safe_optimizable_image_file( $path ) ) {
			continue;
		}

		$seen[ $relative ] = true;
		$sources[]         = array(
			'key'      => $key,
			'file'     => $relative,
			'path'     => $path,
			'width'    => $item['width'],
			'height'   => $item['height'],
			'bytes'    => filesize( $path ),
			'variants' => array(),
		);
	}

	return $sources;
}

/**
 * Generate one image variant through the optional Bun Image engine.
 *
 * @param array  $source      Source item.
 * @param string $format      Target format.
 * @param int    $quality     Image quality.
 * @param string $target_path Target file path.
 * @param string $mime        Target mime type.
 *
 * @return array
 */
function scm_generate_bun_image_optimizer_variant( $source, $format, $quality, $target_path, $mime ) {
	$settings    = scm_get_page_optimization_settings();
	$bun         = scm_get_executable_command( $settings['bun_path'] );
	$script      = scm_get_bun_image_optimizer_script();
	$uploads     = wp_get_upload_dir();
	$uploads_dir = realpath( $uploads['basedir'] );
	$source_path = realpath( $source['path'] );

	if ( '' === $bun || ! file_exists( $script ) || false === $uploads_dir || false === $source_path ) {
		return array();
	}

	$target_dir  = dirname( $target_path );
	$uploads_dir_full = rtrim( wp_normalize_path( $uploads_dir ), '/' ) . '/';
	$source_path = wp_normalize_path( $source_path );

	if ( 0 !== strpos( wp_normalize_path( $target_dir ), $uploads_dir_full ) || 0 !== strpos( $source_path, $uploads_dir_full ) ) {
		return array();
	}

	if ( ! is_dir( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	$command = $bun . ' ' . escapeshellarg( $script )
		. ' --input ' . escapeshellarg( $source_path )
		. ' --output ' . escapeshellarg( $target_path )
		. ' --uploads ' . escapeshellarg( rtrim( $uploads_dir, '/' ) )
		. ' --format ' . escapeshellarg( $format )
		. ' --quality ' . (int) $quality
		. ( 'yes' === $settings['image_placeholders'] ? ' --placeholder' : ' --placeholder no' );

	$result = scm_run_local_optimizer_command( $command );
	$data   = json_decode( trim( (string) $result['output'] ), true );

	if ( ! $result['passed'] || empty( $data['ok'] ) || ! file_exists( $target_path ) ) {
		return array();
	}

	$target_bytes = filesize( $target_path );

	return array(
		'file'       => scm_get_upload_relative_file_path( $target_path ),
		'mime'       => $mime,
		'bytes'      => $target_bytes,
		'savedBytes' => max( 0, (int) $source['bytes'] - (int) $target_bytes ),
		'reused'     => false,
		'engine'     => 'bun-image',
		'placeholder' => ! empty( $data['placeholder'] ) && scm_is_safe_image_placeholder_data_url( $data['placeholder'] ) ? $data['placeholder'] : '',
	);
}

/**
 * Generate one WebP variant.
 *
 * @param array  $source Source item.
 * @param string $format Target format.
 * @param int    $quality Image quality.
 *
 * @return array
 */
function scm_generate_image_optimizer_variant( $source, $format, $quality ) {
	$extension = strtolower( pathinfo( $source['path'], PATHINFO_EXTENSION ) );

	if ( $extension === $format ) {
		return array();
	}

	$mime        = scm_get_image_optimizer_mime_type( $format );
	$target_path = preg_replace( '/\.[^.]+$/', '.' . $format, $source['path'] );
	$target_dir  = dirname( $target_path );
	$uploads     = wp_get_upload_dir();
	$uploads_dir = realpath( $uploads['basedir'] );

	if ( false === $uploads_dir ) {
		return array();
	}

	$uploads_dir_full = rtrim( wp_normalize_path( $uploads_dir ), '/' ) . '/';
	$target_dir       = wp_normalize_path( $target_dir );
	$source_path      = realpath( $source['path'] );

	if ( false === $source_path ) {
		return array();
	}

	$source_path = wp_normalize_path( $source_path );

	if ( 0 !== strpos( $target_dir, $uploads_dir_full ) || 0 !== strpos( $source_path, $uploads_dir_full ) ) {
		return array();
	}

	if ( ! is_dir( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	if ( file_exists( $target_path ) && filesize( $target_path ) > 0 && filemtime( $target_path ) >= filemtime( $source['path'] ) ) {
		$target_bytes = filesize( $target_path );

		return array(
			'file'       => scm_get_upload_relative_file_path( $target_path ),
			'mime'       => $mime,
			'bytes'      => $target_bytes,
			'savedBytes' => max( 0, (int) $source['bytes'] - (int) $target_bytes ),
			'reused'     => true,
			'engine'     => 'existing',
		);
	}

	$bun_variant = scm_generate_bun_image_optimizer_variant( $source, $format, $quality, $target_path, $mime );

	if ( ! empty( $bun_variant ) ) {
		return $bun_variant;
	}

	if ( ! scm_image_editor_supports_format( $format ) ) {
		return array();
	}

	$editor = wp_get_image_editor( $source['path'] );

	if ( is_wp_error( $editor ) ) {
		return array();
	}

	if ( method_exists( $editor, 'set_quality' ) ) {
		$editor->set_quality( $quality );
	}

	$saved = $editor->save( $target_path, $mime );

	if ( is_wp_error( $saved ) || ! file_exists( $target_path ) ) {
		return array();
	}

	$target_bytes = filesize( $target_path );

	return array(
		'file'       => scm_get_upload_relative_file_path( $target_path ),
		'mime'       => $mime,
		'bytes'      => $target_bytes,
		'savedBytes' => max( 0, (int) $source['bytes'] - (int) $target_bytes ),
		'reused'     => false,
		'engine'     => 'wordpress',
	);
}

/**
 * Add generated image variants to WordPress attachment metadata.
 *
 * @param array $metadata Attachment metadata.
 * @param array $summary  Optimizer summary.
 *
 * @return array
 */
function scm_apply_image_optimizer_summary_to_metadata( $metadata, $summary ) {
	if ( empty( $metadata ) || ! is_array( $metadata ) || empty( $summary['sources'] ) || ! is_array( $summary['sources'] ) ) {
		return is_array( $metadata ) ? $metadata : array();
	}

	foreach ( $summary['sources'] as $source ) {
		if ( empty( $source['key'] ) || empty( $source['variants'] ) || ! is_array( $source['variants'] ) ) {
			continue;
		}

		foreach ( $source['variants'] as $variant ) {
			if ( empty( $variant['file'] ) || empty( $variant['mime'] ) ) {
				continue;
			}

			$entry = array(
				'file'      => wp_basename( $variant['file'] ),
				'filesize'  => isset( $variant['bytes'] ) ? (int) $variant['bytes'] : 0,
				'mime-type' => $variant['mime'],
			);

			if ( 'full' === $source['key'] ) {
				if ( empty( $metadata['sources'] ) || ! is_array( $metadata['sources'] ) ) {
					$metadata['sources'] = array();
				}

				$metadata['sources'][ $variant['mime'] ] = $entry;
				continue;
			}

			if ( empty( $metadata['sizes'][ $source['key'] ] ) || ! is_array( $metadata['sizes'][ $source['key'] ] ) ) {
				continue;
			}

			if ( empty( $metadata['sizes'][ $source['key'] ]['sources'] ) || ! is_array( $metadata['sizes'][ $source['key'] ]['sources'] ) ) {
				$metadata['sizes'][ $source['key'] ]['sources'] = array();
			}

			$metadata['sizes'][ $source['key'] ]['sources'][ $variant['mime'] ] = $entry;
		}
	}

	return $metadata;
}

/**
 * Promote generated upload variants to WordPress attachment files.
 *
 * @param int    $attachment_id Attachment ID.
 * @param array  $metadata      Attachment metadata.
 * @param array  $summary       Optimizer summary.
 * @param string $format        Primary target format.
 *
 * @return array
 */
function scm_apply_image_optimizer_primary_upload_format( $attachment_id, $metadata, &$summary, $format ) {
	if ( empty( $metadata ) || ! is_array( $metadata ) || empty( $summary['sources'] ) || ! is_array( $summary['sources'] ) ) {
		return is_array( $metadata ) ? $metadata : array();
	}

	$format = sanitize_key( (string) $format );

	if ( 'webp' !== $format ) {
		return $metadata;
	}

	$original_metadata = $metadata;
	$original_file     = isset( $metadata['file'] ) ? $metadata['file'] : '';
	$converted         = false;

	foreach ( $summary['sources'] as $source ) {
		if ( empty( $source['key'] ) || empty( $source['variants'][ $format ]['file'] ) || empty( $source['variants'][ $format ]['mime'] ) ) {
			continue;
		}

		$variant = $source['variants'][ $format ];

		if ( 'full' === $source['key'] ) {
			$metadata['file'] = $variant['file'];
			update_attached_file( $attachment_id, $variant['file'] );
			wp_update_post(
				array(
					'ID'             => (int) $attachment_id,
					'post_mime_type' => $variant['mime'],
				)
			);
			$converted = true;
			continue;
		}

		if ( isset( $metadata['sizes'][ $source['key'] ] ) && is_array( $metadata['sizes'][ $source['key'] ] ) ) {
			$metadata['sizes'][ $source['key'] ]['file']      = wp_basename( $variant['file'] );
			$metadata['sizes'][ $source['key'] ]['mime-type'] = $variant['mime'];
			$metadata['sizes'][ $source['key'] ]['filesize']  = isset( $variant['bytes'] ) ? (int) $variant['bytes'] : 0;
		}
	}

	// Only store original file references when at least one source was actually converted.
	if ( $converted ) {
		if ( '' !== $original_file && ! metadata_exists( 'post', $attachment_id, '_ams_cache_image_original_file' ) ) {
			update_post_meta( $attachment_id, '_ams_cache_image_original_file', $original_file );
		}

		if ( ! metadata_exists( 'post', $attachment_id, '_ams_cache_image_original_metadata' ) ) {
			update_post_meta( $attachment_id, '_ams_cache_image_original_metadata', $original_metadata );
		}
	}

	$metadata['ams_cache_primary_format'] = $format;
	$metadata['ams_cache_original_file']  = $original_file;
	$summary['primaryFormat']            = $format;
	$summary['primaryConverted']         = $converted;

	return $metadata;
}

/**
 * Optimize one attachment's generated image files.
 *
 * @param int    $attachment_id Attachment ID.
 * @param array  $metadata      Optional fresh attachment metadata.
 * @param string $mode          Context label.
 *
 * @return array
 */
function scm_optimize_attachment_images( $attachment_id, $metadata = array(), $mode = 'queue' ) {
	$settings = scm_get_page_optimization_settings();
	$mode_key = sanitize_key( (string) $mode );

	if ( 'yes' !== $settings['image_optimization'] ) {
		return array();
	}

	if ( empty( $metadata ) || ! is_array( $metadata ) ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
	}

	$sources      = scm_get_attachment_image_optimizer_sources( $attachment_id, $metadata );
	$formats      = $settings['image_formats'];
	$offload_info = scm_get_attachment_offload_info( $attachment_id );

	$summary = array(
		'generatedAt'  => time(),
		'formats'      => $formats,
		'sources'      => array(),
		'sourceBytes'  => 0,
		'variantBytes' => 0,
		'savedBytes'   => 0,
		'generated'    => 0,
		'reused'       => 0,
		'failed'       => 0,
		'skipped'      => 0,
		'offloaded'    => $offload_info['offloaded'],
		'offloadProvider' => $offload_info['provider'],
		'mode'         => $mode_key,
	);

	if ( empty( $sources ) ) {
		if ( $offload_info['offloaded'] ) {
			$summary['skipped'] = 1;
			$summary['skipReason'] = sprintf(
				'Offloaded by %s. No local source files could be downloaded for optimization.',
				$offload_info['provider']
			);
		} else {
			$summary['skipped'] = 1;
			$summary['skipReason'] = 'No optimizable source files found for this attachment.';
		}

		update_post_meta( $attachment_id, '_ams_cache_image_optimization', $summary );

		return $summary;
	}

	foreach ( $sources as $source ) {
		$source_summary = $source;
		$source_summary['variants'] = array();
		$summary['sourceBytes'] += (int) $source['bytes'];

		foreach ( $formats as $format ) {
			$variant = scm_generate_image_optimizer_variant( $source, $format, $settings['image_quality'] );

			if ( empty( $variant['file'] ) ) {
				$summary['failed']++;
				continue;
			}

			$source_summary['variants'][ $format ] = $variant;

			if ( empty( $source_summary['placeholder'] ) && ! empty( $variant['placeholder'] ) && scm_is_safe_image_placeholder_data_url( $variant['placeholder'] ) ) {
				$source_summary['placeholder'] = $variant['placeholder'];
			}

			$summary['variantBytes'] += (int) $variant['bytes'];
			$summary['savedBytes'] += (int) $variant['savedBytes'];

			if ( ! empty( $variant['reused'] ) ) {
				$summary['reused']++;
			} else {
				$summary['generated']++;
			}
		}

		$summary['sources'][] = $source_summary;
	}

	$summary['metadata'] = scm_apply_image_optimizer_summary_to_metadata( $metadata, $summary );

	if ( in_array( $mode_key, array( 'upload', 'manual' ), true ) ) {
		$summary['metadata'] = scm_apply_image_optimizer_primary_upload_format( $attachment_id, $summary['metadata'], $summary, $settings['image_primary_format'] );
	}

	// For offloaded attachments that were downloaded and optimized,
	// trigger metadata update so the offload plugin can re-upload new variants.
	$was_offloaded = ! empty( $summary['offloaded'] );
	$did_generate  = $summary['generated'] > 0 || $summary['reused'] > 0;

	if ( $was_offloaded && $did_generate && ! empty( $summary['metadata'] ) && is_array( $summary['metadata'] ) ) {
		// Ensure offload plugins see the updated metadata with new variant sources.
		wp_update_attachment_metadata( $attachment_id, $summary['metadata'] );
		$summary['reoffloaded'] = true;

		// Clean up the temp download marker after successful re-offload.
		delete_post_meta( $attachment_id, '_ams_cache_offload_temp_download' );

		// For offloaded attachments, delete the original source files that were
		// downloaded from cloud for optimization. The WebP variants have been
		// re-uploaded, so the original JPG/PNG locals are no longer needed.
		$uploads  = wp_get_upload_dir();
		$base_dir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );

		foreach ( $summary['sources'] as $source ) {
			if ( empty( $source['path'] ) || ! is_string( $source['path'] ) ) {
				continue;
			}

			$source_path = wp_normalize_path( $source['path'] );

			// Safety: only delete files inside the uploads directory.
			if ( 0 !== strpos( $source_path, $base_dir ) ) {
				continue;
			}

			// Don't delete WebP variant files — KH Offloader handles their retention.
			$extension = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );

			if ( 'webp' === $extension ) {
				continue;
			}

			// Delete the original source file (JPG/PNG) that was downloaded from cloud.
			if ( is_file( $source_path ) ) {
				wp_delete_file( $source_path );
			}
		}

		// Robust sweep: also scan for leftover JPG/PNG files matching the
		// original stem pattern, catching any additional or differently-named
		// crops that sources didn't explicitly track.
		if ( ! empty( $summary['sources'][0]['file'] ) ) {
			$original_file_rel = $summary['sources'][0]['file'];
			$source_dir        = trailingslashit( dirname( wp_normalize_path( $base_dir . ltrim( $original_file_rel, '/' ) ) ) );
			$original_stem     = pathinfo( basename( $original_file_rel ), PATHINFO_FILENAME );

			if ( '' !== $original_stem && is_dir( $source_dir ) ) {
				$stem_pattern = preg_quote( $original_stem, '/' );

				foreach ( scandir( $source_dir ) as $entry ) {
					if ( '.' === $entry || '..' === $entry ) {
						continue;
					}

					$entry_path = wp_normalize_path( trailingslashit( $source_dir ) . $entry );

					if ( ! is_file( $entry_path ) ) {
						continue;
					}

					$entry_ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );

					if ( ! in_array( $entry_ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
						continue;
					}

					if ( 1 !== preg_match( '/^' . $stem_pattern . '(?:-|$|\.)/i', $entry ) ) {
						continue;
					}

					wp_delete_file( $entry_path );
				}
			}
		}
	} elseif ( 'upload' !== $mode_key && ! empty( $summary['metadata'] ) && is_array( $summary['metadata'] ) ) {
		wp_update_attachment_metadata( $attachment_id, $summary['metadata'] );
	}

	$stored_summary      = $summary;
	unset( $stored_summary['metadata'] );

	update_post_meta( $attachment_id, '_ams_cache_image_optimization', $stored_summary );

	// Clear retry counter on successful optimization.
	if ( $summary['generated'] > 0 || $summary['reused'] > 0 ) {
		delete_post_meta( $attachment_id, '_ams_cache_image_opt_retries' );
	}

	update_option(
		'scm_image_optimization_last',
		array(
			'attachmentId' => (int) $attachment_id,
			'generatedAt'  => current_time( 'mysql' ),
			'generated'    => $summary['generated'],
			'reused'       => $summary['reused'],
			'failed'       => $summary['failed'],
			'skipped'      => $summary['skipped'],
			'offloaded'    => $summary['offloaded'] ? 'yes' : 'no',
			'reoffloaded'  => ! empty( $summary['reoffloaded'] ) ? 'yes' : 'no',
			'savedBytes'   => $summary['savedBytes'],
			'savedLabel'   => size_format( $summary['savedBytes'], 2 ),
			'primaryFormat' => isset( $summary['primaryFormat'] ) ? $summary['primaryFormat'] : '',
			'primaryConverted' => ! empty( $summary['primaryConverted'] ) ? 'yes' : 'no',
		),
		false
	);

	return $summary;
}

/**
 * Add attachment to image optimization queue.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_enqueue_image_optimization( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return;
	}

	$queue = get_option( 'scm_image_optimization_queue', array() );
	$queue = is_array( $queue ) ? array_map( 'intval', $queue ) : array();

	if ( ! in_array( $attachment_id, $queue, true ) ) {
		$queue[] = $attachment_id;
	}

	$queue = array_slice( array_values( array_unique( $queue ) ), -500 );
	update_option( 'scm_image_optimization_queue', $queue, false );

	if ( ! wp_next_scheduled( 'scm_process_image_optimization_queue' ) ) {
		wp_schedule_single_event( time() + 30, 'scm_process_image_optimization_queue' );
	}
}

/**
 * Clear single-upload optimization retry state.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_clear_single_image_optimization_pending( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return;
	}

	delete_post_meta( $attachment_id, '_ams_cache_image_upload_retry_pending' );

	$queue = get_option( 'scm_image_upload_optimization_queue', array() );
	$queue = is_array( $queue ) ? array_map( 'intval', $queue ) : array();
	$queue = array_values( array_diff( $queue, array( $attachment_id ) ) );

	update_option( 'scm_image_upload_optimization_queue', $queue, false );
}

/**
 * Schedule a single-upload optimization retry without touching the manual batch queue.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_schedule_single_image_optimization( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return;
	}

	update_post_meta( $attachment_id, '_ams_cache_image_upload_retry_pending', 1 );

	$queue = get_option( 'scm_image_upload_optimization_queue', array() );
	$queue = is_array( $queue ) ? array_map( 'intval', $queue ) : array();

	if ( ! in_array( $attachment_id, $queue, true ) ) {
		$queue[] = $attachment_id;
	}

	$queue = array_slice( array_values( array_unique( $queue ) ), -200 );
	update_option( 'scm_image_upload_optimization_queue', $queue, false );

	if ( ! wp_next_scheduled( 'scm_process_single_image_optimization', array( $attachment_id ) ) ) {
		wp_schedule_single_event( time() + 30, 'scm_process_single_image_optimization', array( $attachment_id ) );
	}
}

/**
 * Queue fresh image uploads for background optimization.
 *
 * @param array $metadata      Attachment metadata.
 * @param int   $attachment_id Attachment ID.
 *
 * @return array
 */
function scm_queue_image_optimization_on_upload( $metadata, $attachment_id ) {
	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] || 'yes' !== $settings['image_optimize_on_upload'] ) {
		return $metadata;
	}

	// Skip non-image attachments and already-optimized formats (WebP, AVIF, SVG).
	$mime = get_post_mime_type( $attachment_id );

	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		// WebP, AVIF, GIF, SVG, PDF, audio, video etc. — do not optimize.
		return $metadata;
	}

	// Skip if the attachment file is already a WebP (re-upload of an existing WebP).
	$attached_file = get_attached_file( $attachment_id );

	if ( ! empty( $attached_file ) && 'webp' === strtolower( pathinfo( $attached_file, PATHINFO_EXTENSION ) ) ) {
		return $metadata;
	}

	$already_optimized = get_post_meta( $attachment_id, '_ams_cache_image_optimization', true );

	if (
		! empty( $already_optimized['primaryConverted'] ) ||
		! empty( $already_optimized['primaryFormat'] )
	) {
		return $metadata;
	}

	update_post_meta( $attachment_id, '_ams_cache_image_upload_retry_pending', 1 );

	$result = scm_optimize_attachment_images( $attachment_id, $metadata, 'upload' );

		if ( ! empty( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
			$metadata = $result['metadata'];

			// Advanced Media Offloader reads metadata from DB during its later
			// wp_generate_attachment_metadata callback, so persist variants early.
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		if ( empty( $result ) || ( empty( $result['generated'] ) && empty( $result['reused'] ) && empty( $result['offloaded'] ) ) ) {
			scm_schedule_single_image_optimization( $attachment_id );
		} else {
			scm_clear_single_image_optimization_pending( $attachment_id );
		}

	return $metadata;
}

/**
 * Fallback for upload paths that write metadata without the generation filter.
 *
 * Runs before offload plugins that listen late to wp_update_attachment_metadata,
 * so the selected primary WebP file is visible before remote sync starts.
 *
 * @param array $metadata      Attachment metadata.
 * @param int   $attachment_id Attachment ID.
 *
 * @return array
 */
function scm_optimize_image_metadata_on_update( $metadata, $attachment_id ) {
	static $processing = array();

	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 || empty( $metadata ) || ! is_array( $metadata ) || ! empty( $metadata['ams_cache_primary_format'] ) ) {
		return $metadata;
	}

	if ( ! empty( $processing[ $attachment_id ] ) ) {
		return $metadata;
	}

	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] || 'yes' !== $settings['image_optimize_on_upload'] ) {
		return $metadata;
	}

	$mime = get_post_mime_type( $attachment_id );

	// Only optimize JPEG and PNG — skip WebP, AVIF, GIF, SVG, PDF, etc.
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		return $metadata;
	}

	// Skip if the attached file is already a WebP.
	$attached_file = get_attached_file( $attachment_id );

	if ( ! empty( $attached_file ) && 'webp' === strtolower( pathinfo( $attached_file, PATHINFO_EXTENSION ) ) ) {
		return $metadata;
	}

	// Skip if this attachment was already optimized by AMS Cache.
	$already_optimized = get_post_meta( $attachment_id, '_ams_cache_image_optimization', true );

	if (
		! empty( $already_optimized['primaryConverted'] ) ||
		! empty( $already_optimized['primaryFormat'] )
	) {
		return $metadata;
	}

	$processing[ $attachment_id ] = true;
	update_post_meta( $attachment_id, '_ams_cache_image_upload_retry_pending', 1 );

	$result = scm_optimize_attachment_images( $attachment_id, $metadata, 'upload' );

	if ( ! empty( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
		$metadata = $result['metadata'];
	}

	if ( empty( $result ) || ( empty( $result['generated'] ) && empty( $result['reused'] ) && empty( $result['offloaded'] ) ) ) {
		scm_schedule_single_image_optimization( $attachment_id );
	} else {
		scm_clear_single_image_optimization_pending( $attachment_id );
	}

	unset( $processing[ $attachment_id ] );

	return $metadata;
}

/**
 * Clean up original image files after AMS Cache converts them to WebP
 * and KH Offloader finishes uploading the converted files.
 *
 * Hooks into khs3data_after_upload_to_cloud so the original JPEG/PNG
 * files referenced by _ams_cache_image_original_file are deleted
 * whenever KH Offloader completes an upload of a converted attachment.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_cleanup_original_files_after_kh_offload( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return;
	}

	$original_file = get_post_meta( $attachment_id, '_ams_cache_image_original_file', true );

	if ( empty( $original_file ) || ! is_string( $original_file ) ) {
		return;
	}

	$uploads   = wp_get_upload_dir();
	$base_dir  = trailingslashit( $uploads['basedir'] );
	$full_path = wp_normalize_path( $base_dir . ltrim( $original_file, '/' ) );
	$file_dir  = dirname( $full_path );

	// Derive the original basename without extension (e.g. "photo" from "photo.jpg").
	$original_basename = basename( $original_file );
	$original_stem     = pathinfo( $original_basename, PATHINFO_FILENAME );

	// Delete the original main file (e.g. photo.jpg).
	if ( is_file( $full_path ) ) {
		wp_delete_file( $full_path );
	}

	// Also delete original sized files listed in the stored original metadata.
	$original_metadata = get_post_meta( $attachment_id, '_ams_cache_image_original_metadata', true );

	if ( ! empty( $original_metadata['sizes'] ) && is_array( $original_metadata['sizes'] ) ) {
		$size_dir = trailingslashit( $file_dir );

		foreach ( $original_metadata['sizes'] as $size_name => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			$size_path = wp_normalize_path( $size_dir . $size_data['file'] );

			if ( is_file( $size_path ) ) {
				wp_delete_file( $size_path );
			}
		}
	}

	// Robust sweep: delete any leftover non-WebP files in the upload directory
	// that match the original filename stem. WordPress or plugins may generate
	// additional sizes (e.g. -scaled versions, custom crops) after AMS Cache runs,
	// and the metadata snapshot won't include those.
	if ( '' !== $original_stem && is_dir( $file_dir ) ) {
		$stem_pattern = preg_quote( $original_stem, '/' );

		foreach ( scandir( $file_dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$entry_path = wp_normalize_path( trailingslashit( $file_dir ) . $entry );

			if ( ! is_file( $entry_path ) ) {
				continue;
			}

			$entry_ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );

			// Only target JPG and PNG originals. Skip WebP (handled by KH Offloader)
			// and skip unrelated files.
			if ( ! in_array( $entry_ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				continue;
			}

			// Match files whose basename starts with the original stem.
			// E.g. "photo-scaled.jpg", "photo-150x150.jpg", "photo-768x576.png".
			if ( 1 !== preg_match( '/^' . $stem_pattern . '(?:-|$|\.)/i', $entry ) ) {
				continue;
			}

			wp_delete_file( $entry_path );
		}
	}

	// Clear the stored original references. They are no longer needed after cleanup.
	delete_post_meta( $attachment_id, '_ams_cache_image_original_file' );
	delete_post_meta( $attachment_id, '_ams_cache_image_original_metadata' );
}

/**
 * Keep local files when image optimization still needs them.
 *
 * @param int $delete_rule   Offloader local deletion rule.
 * @param int $attachment_id Attachment ID.
 *
 * @return int
 */
function scm_preserve_local_images_for_pending_optimization( $delete_rule, $attachment_id ) {
	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] ) {
		return $delete_rule;
	}

	$queue = get_option( 'scm_image_optimization_queue', array() );
	$queue = is_array( $queue ) ? array_map( 'intval', $queue ) : array();

	$upload_queue = get_option( 'scm_image_upload_optimization_queue', array() );
	$upload_queue = is_array( $upload_queue ) ? array_map( 'intval', $upload_queue ) : array();

	if ( in_array( (int) $attachment_id, $queue, true ) || in_array( (int) $attachment_id, $upload_queue, true ) || get_post_meta( $attachment_id, '_ams_cache_image_upload_retry_pending', true ) ) {
		return 0;
	}

	return $delete_rule;
}

/**
 * Delay Advanced Media Offloader while a new upload still needs a single retry.
 *
 * @param bool $should_offload Whether offloader should upload.
 * @param int  $attachment_id  Attachment ID.
 *
 * @return bool
 */
function scm_should_delay_advanced_media_offload_for_image_optimization( $should_offload, $attachment_id ) {
	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] || 'yes' !== $settings['image_optimize_on_upload'] ) {
		return $should_offload;
	}

	if ( get_post_meta( (int) $attachment_id, '_ams_cache_image_upload_retry_pending', true ) ) {
		return false;
	}

	return $should_offload;
}

/**
 * Start Advanced Media Offloader after a deferred upload optimization retry.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_maybe_start_advanced_media_offload_after_image_optimization( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 || ! function_exists( 'advmo' ) || ! class_exists( 'Advanced_Media_Offloader\\Services\\CloudAttachmentUploader' ) ) {
		return;
	}

	try {
		$advanced_media_offloader = advmo();

		if (
			empty( $advanced_media_offloader->container ) ||
			! method_exists( $advanced_media_offloader->container, 'has' ) ||
			! $advanced_media_offloader->container->has( 'cloud_provider' )
		) {
			return;
		}

		$cloud_provider = $advanced_media_offloader->container->get( 'cloud_provider' );

		if ( empty( $cloud_provider ) ) {
			return;
		}

		$uploader = new \Advanced_Media_Offloader\Services\CloudAttachmentUploader( $cloud_provider );
		$uploader->uploadAttachment( $attachment_id );
	} catch ( \Throwable $e ) {
		error_log( sprintf( '[AMS Cache] Advanced Media Offloader handoff failed for attachment %d: %s', $attachment_id, $e->getMessage() ) );
	}
}

/**
 * Process queued image optimization work.
 *
 * @return void
 */
function scm_process_image_optimization_queue() {
	$settings = scm_get_page_optimization_settings();

	if ( 'yes' !== $settings['image_optimization'] ) {
		return;
	}

	$queue = get_option( 'scm_image_optimization_queue', array() );
	$queue = is_array( $queue ) ? array_values( array_map( 'intval', $queue ) ) : array();

	if ( empty( $queue ) ) {
		return;
	}

	$batch     = array_splice( $queue, 0, $settings['image_batch_size'] );
	$remaining = array();

	// Store total queue size for dashboard progress display.
	$total_queued = count( $queue ) + count( $batch );
	update_option( 'scm_image_optimization_queue_total', $total_queued, false );

	foreach ( $batch as $attachment_id ) {
		// Pre-cleanup: for offloaded attachments that were already converted
		// before this fix, sweep leftover JPG/PNG files from the upload directory.
		$offload_info       = scm_get_attachment_offload_info( $attachment_id );
		$original_file_meta = get_post_meta( $attachment_id, '_ams_cache_image_original_file', true );

		if ( $offload_info['offloaded'] && ! empty( $original_file_meta ) && is_string( $original_file_meta ) ) {
			scm_cleanup_original_files_after_kh_offload( $attachment_id );
		}

		$result = scm_optimize_attachment_images( $attachment_id, array(), 'manual' );

		// Track offloaded-attachment outcomes.
		if ( ! empty( $result['offloaded'] ) ) {
			if ( $result['generated'] === 0 && $result['reused'] === 0 ) {
				// Offloaded but could not download or optimize — count and skip.
				$skip_count = (int) get_option( 'scm_image_optimization_offloaded_count', 0 );
				update_option( 'scm_image_optimization_offloaded_count', $skip_count + 1, false );

				if ( ! isset( $result['skipReason'] ) ) {
					$result['skipReason'] = 'Offloaded attachment — could not download or optimize.';
				}
			} elseif ( ! empty( $result['reoffloaded'] ) ) {
				// Successfully downloaded, optimized, and re-offloaded.
				$reoffload_count = (int) get_option( 'scm_image_optimization_reoffloaded_count', 0 );
				update_option( 'scm_image_optimization_reoffloaded_count', $reoffload_count + 1, false );
			}
		}

		// Re-queue failed items for retry (keep at most 3 retries).
		if ( ! empty( $result['failed'] ) && $result['generated'] === 0 && $result['reused'] === 0 ) {
			$retries = (int) get_post_meta( $attachment_id, '_ams_cache_image_opt_retries', true );

			if ( $retries < 3 ) {
				update_post_meta( $attachment_id, '_ams_cache_image_opt_retries', $retries + 1 );
				$remaining[] = $attachment_id;
			}
		}
	}

	$queue = array_merge( $remaining, $queue );
	update_option( 'scm_image_optimization_queue', $queue, false );

	if ( ! empty( $queue ) && ! wp_next_scheduled( 'scm_process_image_optimization_queue' ) ) {
		wp_schedule_single_event( time() + 60, 'scm_process_image_optimization_queue' );
	}
}

/**
 * Process one upload retry without using the manual batch queue.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function scm_process_single_image_optimization( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return;
	}

	$settings = scm_get_page_optimization_settings();
	$metadata = array();

	if ( 'yes' === $settings['image_optimization'] && 'yes' === $settings['image_optimize_on_upload'] ) {
		$result = scm_optimize_attachment_images( $attachment_id, array(), 'upload' );

		if ( ! empty( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
			$metadata = $result['metadata'];
		}
	}

	scm_clear_single_image_optimization_pending( $attachment_id );

	if ( ! empty( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	scm_maybe_start_advanced_media_offload_after_image_optimization( $attachment_id );
}

/**
 * Find a tiny placeholder for a rendered attachment image URL.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $url           Image URL.
 *
 * @return string
 */
function scm_find_image_placeholder_for_url( $attachment_id, $url ) {
	$summary = get_post_meta( $attachment_id, '_ams_cache_image_optimization', true );

	if ( empty( $summary['sources'] ) || ! is_array( $summary['sources'] ) ) {
		return '';
	}

	$path = parse_url( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_PATH );
	$name = basename( rawurldecode( (string) $path ) );

	if ( '' === $name ) {
		return '';
	}

	foreach ( $summary['sources'] as $source ) {
		if ( empty( $source['placeholder'] ) ) {
			continue;
		}

		$matches_source = ! empty( $source['file'] ) && basename( $source['file'] ) === $name;

		if ( ! $matches_source && ! empty( $source['variants'] ) && is_array( $source['variants'] ) ) {
			foreach ( $source['variants'] as $variant ) {
				if ( ! empty( $variant['file'] ) && basename( $variant['file'] ) === $name ) {
					$matches_source = true;
					break;
				}
			}
		}

		if ( $matches_source ) {
			return scm_is_safe_image_placeholder_data_url( $source['placeholder'] ) ? $source['placeholder'] : '';
		}
	}

	return '';
}

/**
 * Add a safe placeholder background to an image tag.
 *
 * @param string $tag         Image tag.
 * @param string $placeholder Placeholder data URL.
 *
 * @return string
 */
function scm_add_image_placeholder_to_tag( $tag, $placeholder ) {
	if ( '' === $placeholder || ! scm_is_safe_image_placeholder_data_url( $placeholder ) || false !== stripos( $tag, 'data-ams-cache-placeholder' ) ) {
		return $tag;
	}

	$style = trim( scm_html_get_attribute( $tag, 'style' ) );

	if ( false === stripos( $style, 'background-image' ) ) {
		$style = rtrim( $style, ';' );
		$style = '' !== $style ? $style . ';' : '';
		$style .= 'background-image:url("' . $placeholder . '");background-size:cover;background-position:center;background-color:#f3f4f6;';
		$tag = scm_html_set_attribute( $tag, 'style', $style );
	}

	return scm_html_set_attribute( $tag, 'data-ams-cache-placeholder', '1' );
}

/**
 * Find variant metadata for a rendered source URL.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $url           Image URL.
 * @param string $format        Target format.
 *
 * @return array
 */
function scm_find_image_variant_for_url( $attachment_id, $url, $format ) {
	$summary = get_post_meta( $attachment_id, '_ams_cache_image_optimization', true );

	if ( empty( $summary['sources'] ) || ! is_array( $summary['sources'] ) ) {
		return array();
	}

	$path = parse_url( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_PATH );
	$name = basename( rawurldecode( (string) $path ) );

	if ( '' === $name ) {
		return array();
	}

	foreach ( $summary['sources'] as $source ) {
		if ( empty( $source['file'] ) || basename( $source['file'] ) !== $name ) {
			continue;
		}

		if ( ! empty( $source['variants'][ $format ]['file'] ) ) {
			return $source['variants'][ $format ];
		}
	}

	return array();
}

/**
 * Build a public variant URL from a rendered image URL.
 *
 * @param string $source_url Source image URL.
 * @param array  $variant    Variant metadata.
 * @param array  $settings   Page optimization settings.
 *
 * @return string
 */
function scm_build_image_variant_url( $source_url, $variant, $settings ) {
	if ( empty( $variant['file'] ) ) {
		return '';
	}

	$uploads = wp_get_upload_dir();
	$baseurl = trailingslashit( $uploads['baseurl'] );
	$variant_file = wp_normalize_path( ltrim( $variant['file'], '/' ) );

	if ( '' === $variant_file || false !== strpos( $variant_file, '..' ) ) {
		return '';
	}

	$local_variant = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $variant_file );
	$source_host = parse_url( $source_url, PHP_URL_HOST );
	$upload_host = parse_url( $baseurl, PHP_URL_HOST );

	if ( ! is_file( $local_variant ) ) {
		return '';
	}

	if ( empty( $source_host ) || strtolower( (string) $source_host ) === strtolower( (string) $upload_host ) ) {
		return $baseurl . $variant_file;
	}

	if ( 'yes' !== $settings['image_remote_rewrite'] ) {
		return '';
	}

	$source_path = parse_url( $source_url, PHP_URL_PATH );

	if ( empty( $source_path ) ) {
		return '';
	}

	return str_replace( basename( $source_path ), basename( $variant['file'] ), $source_url );
}

/**
 * Convert one srcset to a variant srcset.
 *
 * @param string $srcset        Source srcset.
 * @param int    $attachment_id Attachment ID.
 * @param string $format        Target format.
 * @param array  $settings      Settings.
 *
 * @return string
 */
function scm_build_image_variant_srcset( $srcset, $attachment_id, $format, $settings ) {
	$items = array_filter( array_map( 'trim', explode( ',', (string) $srcset ) ) );
	$out   = array();

	foreach ( $items as $item ) {
		$parts = preg_split( '/\s+/', $item );
		$url   = isset( $parts[0] ) ? $parts[0] : '';

		if ( '' === $url ) {
			continue;
		}

		$variant_url = scm_build_image_variant_url( $url, scm_find_image_variant_for_url( $attachment_id, $url, $format ), $settings );

		if ( '' === $variant_url ) {
			continue;
		}

		$parts[0] = $variant_url;
		$out[]    = implode( ' ', $parts );
	}

	return implode( ', ', $out );
}

/**
 * Wrap WordPress attachment images with WebP sources when variants exist.
 *
 * @param string       $html          Image HTML.
 * @param int          $attachment_id Attachment ID.
 * @param string|array $size          Image size.
 * @param bool         $icon          Whether icon.
 * @param array        $attr          Attributes.
 *
 * @return string
 */
function scm_filter_attachment_image_html( $html, $attachment_id, $size, $icon, $attr ) {
	$settings = scm_get_page_optimization_settings();

	if ( is_admin() || 'yes' !== $settings['image_optimization'] || 'yes' !== $settings['image_rewrite_html'] || $icon || false !== strpos( $html, '<picture' ) ) {
		return $html;
	}

	$src    = scm_html_get_attribute( $html, 'src' );
	$srcset = scm_html_get_attribute( $html, 'srcset' );
	$sizes  = scm_html_get_attribute( $html, 'sizes' );

	if ( '' === $src ) {
		return $html;
	}

	if ( 'yes' === $settings['image_placeholders'] ) {
		$html = scm_add_image_placeholder_to_tag( $html, scm_find_image_placeholder_for_url( $attachment_id, $src ) );
	}

	$sources = '';

	foreach ( array( 'webp' ) as $format ) {
		if ( ! in_array( $format, $settings['image_formats'], true ) ) {
			continue;
		}

		$variant_srcset = '' !== $srcset ? scm_build_image_variant_srcset( $srcset, $attachment_id, $format, $settings ) : '';
		$variant_src    = scm_build_image_variant_url( $src, scm_find_image_variant_for_url( $attachment_id, $src, $format ), $settings );

		if ( '' === $variant_srcset && '' === $variant_src ) {
			continue;
		}

		$sources .= '<source type="' . esc_attr( scm_get_image_optimizer_mime_type( $format ) ) . '"';
		$sources .= ' srcset="' . esc_attr( '' !== $variant_srcset ? $variant_srcset : $variant_src ) . '"';

		if ( '' !== $sizes ) {
			$sources .= ' sizes="' . esc_attr( $sizes ) . '"';
		}

		$sources .= '>';
	}

	if ( '' === $sources ) {
		return $html;
	}

	return '<picture data-ams-cache-image-optimized="1">' . $sources . $html . '</picture>';
}

/**
 * Resolve an attachment ID from an image tag URL.
 *
 * @param string $tag Image tag.
 *
 * @return int
 */
function scm_get_attachment_id_from_image_tag( $tag ) {
	if ( ! function_exists( 'attachment_url_to_postid' ) ) {
		return 0;
	}

	$urls   = array();
	$src    = scm_html_get_attribute( $tag, 'src' );
	$srcset = scm_html_get_attribute( $tag, 'srcset' );

	if ( '' !== $src ) {
		$urls[] = $src;
	}

	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $srcset ) ) ) as $item ) {
		$parts = preg_split( '/\s+/', $item );

		if ( ! empty( $parts[0] ) ) {
			$urls[] = $parts[0];
		}
	}

	foreach ( array_unique( $urls ) as $url ) {
		$attachment_id = attachment_url_to_postid( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );

		if ( $attachment_id > 0 ) {
			return (int) $attachment_id;
		}
	}

	return 0;
}

/**
 * Rewrite raw cached image tags when they map to optimized attachment variants.
 *
 * @param string $html     HTML content.
 * @param array  $settings Page optimization settings.
 *
 * @return string
 */
function scm_rewrite_cached_attachment_images( $html, $settings ) {
	if (
		is_admin() ||
		'yes' !== $settings['image_optimization'] ||
		'yes' !== $settings['image_rewrite_html'] ||
		false === stripos( $html, '<img' ) ||
		false !== stripos( $html, '<picture' )
	) {
		return $html;
	}

	return preg_replace_callback(
		'#<img\b[^>]*>#i',
		function ( $match ) use ( $settings ) {
			$tag = $match[0];

			if ( false !== stripos( $tag, 'data-ams-cache-image-optimized' ) ) {
				return $tag;
			}

			$attachment_id = scm_get_attachment_id_from_image_tag( $tag );

			if ( $attachment_id <= 0 ) {
				return $tag;
			}

			$src     = scm_html_get_attribute( $tag, 'src' );
			$srcset  = scm_html_get_attribute( $tag, 'srcset' );
			$sizes   = scm_html_get_attribute( $tag, 'sizes' );
			$sources = '';

			if ( 'yes' === $settings['image_placeholders'] && '' !== $src ) {
				$tag = scm_add_image_placeholder_to_tag( $tag, scm_find_image_placeholder_for_url( $attachment_id, $src ) );
			}

			foreach ( array( 'webp' ) as $format ) {
				if ( ! in_array( $format, $settings['image_formats'], true ) ) {
					continue;
				}

				$variant_srcset = '' !== $srcset ? scm_build_image_variant_srcset( $srcset, $attachment_id, $format, $settings ) : '';
				$variant_src    = '' !== $src ? scm_build_image_variant_url( $src, scm_find_image_variant_for_url( $attachment_id, $src, $format ), $settings ) : '';

				if ( '' === $variant_srcset && '' === $variant_src ) {
					continue;
				}

				$sources .= '<source type="' . esc_attr( scm_get_image_optimizer_mime_type( $format ) ) . '"';
				$sources .= ' srcset="' . esc_attr( '' !== $variant_srcset ? $variant_srcset : $variant_src ) . '"';

				if ( '' !== $sizes ) {
					$sources .= ' sizes="' . esc_attr( $sizes ) . '"';
				}

				$sources .= '>';
			}

			if ( '' === $sources ) {
				return $tag;
			}

			return '<picture data-ams-cache-image-optimized="1">' . $sources . $tag . '</picture>';
		},
		$html
	);
}

/**
 * Add font preconnect hints.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function scm_add_font_preconnects( $html ) {
	$links = '';

	if ( false !== strpos( $html, 'fonts.googleapis.com' ) && false === strpos( $html, 'href="https://fonts.googleapis.com"' ) ) {
		$links .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
	}

	if ( false !== strpos( $html, 'fonts.gstatic.com' ) && false === strpos( $html, 'href="https://fonts.gstatic.com"' ) ) {
		$links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
	}

	if ( '' !== $links ) {
		$html = preg_replace( '#</head>#i', $links . '</head>', $html, 1 );
	}

	return $html;
}

/**
 * Defer external scripts.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_defer_scripts( $html, $settings ) {
	$exclusions = scm_get_lines_from_textarea( $settings['js_exclusions'] );

	return preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*(["\']).*?\1[^>]*></script>#is',
		function ( $match ) use ( $exclusions ) {
			$tag = $match[0];

			if (
				scm_html_tag_is_excluded( $tag, $exclusions ) ||
				scm_html_has_attribute( $tag, 'defer' ) ||
				scm_html_has_attribute( $tag, 'async' ) ||
				false !== stripos( $tag, 'type="module"' ) ||
				false !== stripos( $tag, "type='module'" )
			) {
				return $tag;
			}

			return preg_replace( '/^<script\b/i', '<script defer', $tag, 1 );
		},
		$html
	);
}

/**
 * Build one PurgeCSS CLI command.
 *
 * @param string $purgecss   Escaped PurgeCSS executable.
 * @param array  $css_files  Relative CSS filenames.
 * @param array  $safelist   Safelisted selectors.
 * @param string $output_dir Relative output directory.
 *
 * @return string
 */
function scm_build_local_ucss_command( $purgecss, $css_files, $safelist, $output_dir ) {
	$command = $purgecss . ' --css';

	foreach ( $css_files as $css_file ) {
		$command .= ' ' . escapeshellarg( $css_file );
	}

	$command .= ' --content ' . escapeshellarg( 'content.html' );
	$command .= ' --output ' . escapeshellarg( $output_dir );
	$command .= ' --font-face --keyframes';

	if ( ! empty( $safelist ) ) {
		$command .= ' --safelist';

		foreach ( $safelist as $selector ) {
			$command .= ' ' . escapeshellarg( $selector );
		}
	}

	return $command;
}

/**
 * Pick useful one-line optimizer failure detail from noisy CLI output.
 *
 * @param string $output   CLI output.
 * @param string $fallback Fallback detail.
 *
 * @return string
 */
function scm_get_local_optimizer_error_detail( $output, $fallback ) {
	$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $output ) );

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' !== $line && preg_match( '/(?:CssSyntaxError|Unknown word|Unclosed block|Missed semicolon)/i', $line ) ) {
			return $line;
		}
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if (
			'' !== $line &&
			0 !== strpos( $line, 'at ' ) &&
			false === strpos( $line, '/node_modules/' ) &&
			false === strpos( $line, '\\node_modules\\' )
		) {
			return $line;
		}
	}

	return $fallback;
}

/**
 * Run PurgeCSS on inline style blocks.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_local_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<style\b([^>]*)>(.*?)</style>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No inline CSS blocks found.', 'ams-cache' ),
				'metrics' => array(
					'beforeBytes'   => 0,
					'afterBytes'    => 0,
					'savedBytes'    => 0,
					'blocks'        => 0,
					'skippedBlocks' => 0,
				),
			)
		);
		return $html;
	}

	$purgecss = scm_get_executable_command( $settings['purgecss_path'] );
	$workspace = scm_create_page_optimization_workspace( 'ucss' );

	if ( '' === $purgecss || '' === $workspace ) {
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => 'failed',
				'detail' => __( 'Local UCSS engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$content_file = trailingslashit( $workspace ) . 'content.html';
	$output_dir   = trailingslashit( $workspace ) . 'output';
	$css_files    = array();
	$before_bytes = 0;

	wp_mkdir_p( $output_dir );
	file_put_contents( $content_file, $html );

	foreach ( $matches as $index => $match ) {
		$css_file = 'style-' . $index . '.css';
		$css      = (string) $match[2];

		$before_bytes += strlen( $css );
		file_put_contents( trailingslashit( $workspace ) . $css_file, $css );
		$css_files[] = $css_file;
	}

	$safelist        = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
	$command         = scm_build_local_ucss_command( $purgecss, $css_files, $safelist, 'output' );
	$result          = scm_run_local_optimizer_command( $command, $workspace );
	$replacements    = array();
	$after_bytes     = 0;
	$skipped_blocks  = 0;
	$failure_detail  = '';
	$batch_succeeded = $result['passed'];

	foreach ( $matches as $index => $match ) {
		$css_file    = 'style-' . $index . '.css';
		$output_file = trailingslashit( $output_dir ) . $css_file;

		if ( ! $batch_succeeded ) {
			$block_output_dir = 'output-' . $index;
			wp_mkdir_p( trailingslashit( $workspace ) . $block_output_dir );

			$block_command = scm_build_local_ucss_command( $purgecss, array( $css_file ), $safelist, $block_output_dir );
			$block_result  = scm_run_local_optimizer_command( $block_command, $workspace );
			$output_file   = trailingslashit( $workspace ) . $block_output_dir . '/' . $css_file;

			if ( ! $block_result['passed'] ) {
				$skipped_blocks++;
				$after_bytes += strlen( (string) $match[2] );
				$replacements[ $index ] = $match[0];

				if ( '' === $failure_detail ) {
					$failure_detail = scm_get_local_optimizer_error_detail(
						$block_result['output'],
						__( 'PurgeCSS could not parse one inline CSS block.', 'ams-cache' )
					);
				}

				continue;
			}
		}

		if ( ! file_exists( $output_file ) ) {
			$skipped_blocks++;
			$after_bytes += strlen( (string) $match[2] );
			$replacements[ $index ] = $match[0];

			if ( '' === $failure_detail ) {
				$failure_detail = __( 'PurgeCSS output was incomplete.', 'ams-cache' );
			}

			continue;
		}

		$original_css = (string) $match[2];
		$purged_css   = (string) file_get_contents( $output_file );

		if ( '' === $purged_css || strlen( $purged_css ) >= strlen( $original_css ) ) {
			$skipped_blocks++;
			$after_bytes += strlen( $original_css );
			$replacements[ $index ] = $match[0];
			continue;
		}

		$after_bytes += strlen( $purged_css );
		$replacements[ $index ] = '<style' . $match[1] . '>' . $purged_css . '</style>';
	}

	if ( count( $matches ) === $skipped_blocks ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'local_ucss',
			array(
				'status' => '' !== $failure_detail ? 'failed' : 'no_change',
				'detail' => '' !== $failure_detail
					? $failure_detail
					: __( 'Local UCSS found no smaller inline CSS output.', 'ams-cache' ),
				'metrics' => array(
					'beforeBytes'   => $before_bytes,
					'afterBytes'    => $before_bytes,
					'savedBytes'    => 0,
					'blocks'        => count( $matches ),
					'skippedBlocks' => $skipped_blocks,
				),
			)
		);
		return $html;
	}

	$replacement_index = 0;
	$html = preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		function ( $match ) use ( &$replacement_index, $replacements ) {
			$replacement = isset( $replacements[ $replacement_index ] ) ? $replacements[ $replacement_index ] : $match[0];
			$replacement_index++;

			return $replacement;
		},
		$html
	);
	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'local_ucss',
		array(
			'status' => $after_bytes < $before_bytes ? 'applied' : 'no_change',
			'detail' => $skipped_blocks > 0
				? sprintf(
					/* translators: 1: before size, 2: after size, 3: block count, 4: skipped block count. */
					__( '%1$s inline CSS before, %2$s after across %3$s blocks; %4$s malformed blocks kept raw.', 'ams-cache' ),
					size_format( $before_bytes, 2 ),
					size_format( $after_bytes, 2 ),
					number_format_i18n( count( $matches ) ),
					number_format_i18n( $skipped_blocks )
				)
				: sprintf(
					/* translators: 1: before size, 2: after size, 3: block count. */
					__( '%1$s inline CSS before, %2$s after across %3$s blocks.', 'ams-cache' ),
					size_format( $before_bytes, 2 ),
					size_format( $after_bytes, 2 ),
					number_format_i18n( count( $matches ) )
				),
			'metrics' => array(
				'beforeBytes'   => $before_bytes,
				'afterBytes'    => $after_bytes,
				'savedBytes'    => max( 0, $before_bytes - $after_bytes ),
				'blocks'        => count( $matches ),
				'skippedBlocks' => $skipped_blocks,
			),
		)
	);

	return $html;
}

/**
 * Check if a stylesheet link is safe for external UCSS.
 *
 * @param string $tag HTML link tag.
 *
 * @return bool
 */
function scm_is_external_ucss_candidate( $tag ) {
	$rel = strtolower( scm_html_get_attribute( $tag, 'rel' ) );

	if ( '' === $rel || false === strpos( $rel, 'stylesheet' ) ) {
		return false;
	}

	if (
		false !== strpos( $rel, 'alternate' ) ||
		false !== strpos( $rel, 'preload' ) ||
		scm_html_has_attribute( $tag, 'disabled' ) ||
		scm_html_has_attribute( $tag, 'integrity' ) ||
		scm_html_has_attribute( $tag, 'crossorigin' )
	) {
		return false;
	}

	return true;
}

/**
 * Normalize URL path segments.
 *
 * @param string $path URL path.
 *
 * @return string
 */
function scm_normalize_url_path_segments( $path ) {
	$parts = explode( '/', (string) $path );
	$out   = array();

	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}

		if ( '..' === $part ) {
			array_pop( $out );
			continue;
		}

		$out[] = $part;
	}

	return '/' . implode( '/', $out );
}

/**
 * Resolve a CSS url() value against a stylesheet URL.
 *
 * @param string $url           Raw CSS URL.
 * @param string $stylesheet_url Stylesheet URL.
 *
 * @return string
 */
function scm_resolve_css_url( $url, $stylesheet_url ) {
	$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );

	if (
		'' === $url ||
		0 === strpos( $url, '#' ) ||
		0 === stripos( $url, 'data:' ) ||
		0 === stripos( $url, 'http:' ) ||
		0 === stripos( $url, 'https:' ) ||
		0 === strpos( $url, '//' )
	) {
		return $url;
	}

	if ( 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}

	$base = html_entity_decode( (string) $stylesheet_url, ENT_QUOTES, 'UTF-8' );

	if ( false === strpos( $base, '//' ) ) {
		$base = home_url( '/' . ltrim( $base, '/' ) );
	}

	$parts = parse_url( $base );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $url;
	}

	$base_path = isset( $parts['path'] ) ? $parts['path'] : '/';
	$base_dir  = preg_replace( '#/[^/]*$#', '/', $base_path );
	$path      = scm_normalize_url_path_segments( $base_dir . $url );
	$port      = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

	return $parts['scheme'] . '://' . $parts['host'] . $port . $path;
}

/**
 * Rewrite relative CSS url() values before external CSS is inlined.
 *
 * @param string $css           CSS content.
 * @param string $stylesheet_url Stylesheet URL.
 *
 * @return string
 */
function scm_rewrite_css_relative_urls( $css, $stylesheet_url ) {
	return preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $match ) use ( $stylesheet_url ) {
			$resolved = scm_resolve_css_url( $match[2], $stylesheet_url );

			return 'url("' . esc_url_raw( $resolved ) . '")';
		},
		$css
	);
}

/**
 * Run PurgeCSS on same-site stylesheet files and inline the optimized output.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_external_ucss( $html, $settings ) {
	if ( ! preg_match_all( '#<link\b[^>]*>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No stylesheet links found.', 'ams-cache' ),
				'metrics' => array(
					'files'          => 0,
					'optimizedFiles' => 0,
					'skippedFiles'   => 0,
					'beforeBytes'    => 0,
					'afterBytes'     => 0,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
	}

	$purgecss = scm_get_executable_command( $settings['purgecss_path'] );
	$workspace = scm_create_page_optimization_workspace( 'external-ucss' );

	if ( '' === $purgecss || '' === $workspace ) {
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status'  => 'failed',
				'detail'  => __( 'External UCSS engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);

		return $html;
	}

	$content_file = trailingslashit( $workspace ) . 'content.html';
	$output_dir   = trailingslashit( $workspace ) . 'output';
	$max_size     = (int) $settings['external_ucss_max_file_size'];
	$css_files    = array();
	$entries      = array();
	$before_bytes = 0;
	$after_bytes  = 0;
	$skipped      = 0;

	wp_mkdir_p( $output_dir );
	file_put_contents( $content_file, $html );

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];

		if ( ! scm_is_external_ucss_candidate( $tag ) ) {
			continue;
		}

		$href = html_entity_decode( scm_html_get_attribute( $tag, 'href' ), ENT_QUOTES, 'UTF-8' );
		$path = scm_local_asset_url_to_path( $href );

		if (
			'' === $href ||
			'' === $path ||
			! is_file( $path ) ||
			! is_readable( $path ) ||
			'css' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) )
		) {
			$skipped++;
			continue;
		}

		$file_size = filesize( $path );

		if ( false === $file_size || $file_size <= 0 || $file_size > $max_size ) {
			$skipped++;
			continue;
		}

		$css = file_get_contents( $path );

		if ( false === $css || '' === trim( $css ) ) {
			$skipped++;
			continue;
		}

		if ( preg_match( '/@import\b/i', $css ) ) {
			$skipped++;
			continue;
		}

		$css_file = 'stylesheet-' . count( $css_files ) . '.css';
		file_put_contents( trailingslashit( $workspace ) . $css_file, $css );

		$css_files[] = $css_file;
		$entries[]   = array(
			'index' => $index,
			'tag'   => $tag,
			'href'  => $href,
			'media' => scm_html_get_attribute( $tag, 'media' ),
			'file'  => $css_file,
			'bytes' => strlen( $css ),
		);
		$before_bytes += strlen( $css );
	}

	if ( empty( $entries ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => 'no_change',
				'detail' => __( 'No eligible same-site stylesheet files found.', 'ams-cache' ),
				'metrics' => array(
					'files'          => 0,
					'optimizedFiles' => 0,
					'skippedFiles'   => $skipped,
					'beforeBytes'    => 0,
					'afterBytes'     => 0,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
	}

	$safelist        = scm_get_lines_from_textarea( $settings['ucss_safelist'] );
	$command         = scm_build_local_ucss_command( $purgecss, $css_files, $safelist, 'output' );
	$result          = scm_run_local_optimizer_command( $command, $workspace );
	$batch_succeeded = $result['passed'];
	$replacements    = array();
	$optimized       = 0;
	$failure_detail  = '';

	foreach ( $entries as $entry ) {
		$output_file = trailingslashit( $output_dir ) . $entry['file'];

		if ( ! $batch_succeeded ) {
			$block_output_dir = 'output-' . $entry['index'];
			wp_mkdir_p( trailingslashit( $workspace ) . $block_output_dir );

			$block_command = scm_build_local_ucss_command( $purgecss, array( $entry['file'] ), $safelist, $block_output_dir );
			$block_result  = scm_run_local_optimizer_command( $block_command, $workspace );
			$output_file   = trailingslashit( $workspace ) . $block_output_dir . '/' . $entry['file'];

			if ( ! $block_result['passed'] ) {
				$skipped++;
				$after_bytes += $entry['bytes'];

				if ( '' === $failure_detail ) {
					$failure_detail = scm_get_local_optimizer_error_detail(
						$block_result['output'],
						__( 'PurgeCSS could not parse one stylesheet.', 'ams-cache' )
					);
				}

				continue;
			}
		}

		if ( ! file_exists( $output_file ) ) {
			$skipped++;
			$after_bytes += $entry['bytes'];

			if ( '' === $failure_detail ) {
				$failure_detail = __( 'PurgeCSS output was incomplete.', 'ams-cache' );
			}

			continue;
		}

		$purged_css = scm_rewrite_css_relative_urls( file_get_contents( $output_file ), $entry['href'] );
		$purged_css = scm_minify_css( $purged_css );
		$purged_len = strlen( $purged_css );

		if ( '' === $purged_css || $purged_len >= $entry['bytes'] ) {
			$skipped++;
			$after_bytes += $entry['bytes'];
			continue;
		}

		$style = '<style data-ams-cache-external-ucss="' . esc_attr( $entry['href'] ) . '"';

		if ( '' !== $entry['media'] ) {
			$style .= ' media="' . esc_attr( $entry['media'] ) . '"';
		}

		$style .= '>' . $purged_css . '</style>';

		$replacements[ $entry['index'] ] = $style;
		$after_bytes += $purged_len;
		$optimized++;
	}

	if ( empty( $replacements ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'external_ucss',
			array(
				'status' => '' !== $failure_detail ? 'failed' : 'no_change',
				'detail' => '' !== $failure_detail
					? $failure_detail
					: __( 'External UCSS found no smaller stylesheet output.', 'ams-cache' ),
				'metrics' => array(
					'files'          => count( $entries ),
					'optimizedFiles' => 0,
					'skippedFiles'   => $skipped,
					'beforeBytes'    => $before_bytes,
					'afterBytes'     => $before_bytes,
					'savedBytes'     => 0,
				),
			)
		);

		return $html;
	}

	$link_index = 0;
	$html = preg_replace_callback(
		'#<link\b[^>]*>#is',
		function ( $match ) use ( &$link_index, $replacements ) {
			$replacement = isset( $replacements[ $link_index ] ) ? $replacements[ $link_index ] : $match[0];
			$link_index++;

			return $replacement;
		},
		$html
	);

	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'external_ucss',
		array(
			'status' => 'applied',
			'detail' => sprintf(
				/* translators: 1: before size, 2: after size, 3: optimized files, 4: skipped files. */
				__( '%1$s external CSS before, %2$s after; %3$s files optimized, %4$s skipped.', 'ams-cache' ),
				size_format( $before_bytes, 2 ),
				size_format( $after_bytes, 2 ),
				number_format_i18n( $optimized ),
				number_format_i18n( $skipped )
			),
			'metrics' => array(
				'files'          => count( $entries ),
				'optimizedFiles' => $optimized,
				'skippedFiles'   => $skipped,
				'beforeBytes'    => $before_bytes,
				'afterBytes'     => $after_bytes,
				'savedBytes'     => max( 0, $before_bytes - $after_bytes ),
			),
		)
	);

	return $html;
}

/**
 * Run local Bun script analysis and defer safe local scripts.
 *
 * @param string $html     HTML content.
 * @param array  $settings Optimization settings.
 *
 * @return string
 */
function scm_apply_local_js_analysis( $html, $settings ) {
	if ( ! preg_match_all( '#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*></script>#is', $html, $matches, PREG_SET_ORDER ) ) {
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'no_change',
				'detail' => __( 'No external scripts found.', 'ams-cache' ),
				'metrics' => array(
					'analyzed' => 0,
					'deferred' => 0,
				),
			)
		);
		return $html;
	}

	$bun       = scm_get_executable_command( $settings['bun_path'] );
	$analyzer  = SCM_PLUGIN_DIR . 'inc/assets/js/local-js-analyzer.js';
	$workspace = scm_create_page_optimization_workspace( 'js-analysis' );

	if ( '' === $bun || '' === $workspace || ! file_exists( $analyzer ) ) {
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => __( 'JS analysis engine could not start.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$exclusions = scm_get_lines_from_textarea( $settings['js_exclusions'] );
	$scripts    = array();
	$indexes    = array();

	foreach ( $matches as $index => $match ) {
		$tag = $match[0];
		$src = html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' );

		if (
			scm_html_tag_is_excluded( $tag, $exclusions ) ||
			scm_html_has_attribute( $tag, 'defer' ) ||
			scm_html_has_attribute( $tag, 'async' ) ||
			false !== stripos( $tag, 'type="module"' ) ||
			false !== stripos( $tag, "type='module'" )
		) {
			continue;
		}

		$path = scm_local_asset_url_to_path( $src );

		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			continue;
		}

		$scripts[] = array(
			'src'     => $src,
			'content' => file_get_contents( $path ),
		);
		$indexes[] = $index;
	}

	if ( empty( $scripts ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'no_change',
				'detail' => __( 'No readable same-site scripts available for analysis.', 'ams-cache' ),
				'metrics' => array(
					'analyzed' => 0,
					'deferred' => 0,
				),
			)
		);
		return $html;
	}

	$input_file  = trailingslashit( $workspace ) . 'scripts.json';
	$output_file = trailingslashit( $workspace ) . 'analysis.json';
	file_put_contents( $input_file, wp_json_encode( array( 'scripts' => $scripts ) ) );

	$command = $bun . ' ' . escapeshellarg( $analyzer ) . ' ' . escapeshellarg( $input_file ) . ' ' . escapeshellarg( $output_file );
	$result  = scm_run_local_optimizer_command( $command );

	if ( ! $result['passed'] || ! file_exists( $output_file ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => '' !== $result['output'] ? strtok( $result['output'], "\r\n" ) : __( 'JS analysis failed.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$analysis = json_decode( file_get_contents( $output_file ), true );

	if ( ! is_array( $analysis ) || empty( $analysis['scripts'] ) || ! is_array( $analysis['scripts'] ) ) {
		scm_delete_page_optimization_workspace( $workspace );
		scm_set_page_optimization_runtime(
			'js_analysis',
			array(
				'status' => 'failed',
				'detail' => __( 'JS analysis output was invalid.', 'ams-cache' ),
				'metrics' => array(),
			)
		);
		return $html;
	}

	$deferred = 0;

	foreach ( $analysis['scripts'] as $analysis_index => $script ) {
		if ( empty( $script['safeToDefer'] ) || ! isset( $indexes[ $analysis_index ] ) ) {
			continue;
		}

		$match_index = $indexes[ $analysis_index ];
		$old_tag     = $matches[ $match_index ][0];
		$new_tag     = preg_replace( '/^<script\b/i', '<script defer', $old_tag, 1 );

		$html = preg_replace( '#' . preg_quote( $old_tag, '#' ) . '#', $new_tag, $html, 1 );
		$deferred++;
	}

	scm_delete_page_optimization_workspace( $workspace );
	scm_set_page_optimization_runtime(
		'js_analysis',
		array(
			'status' => $deferred > 0 ? 'applied' : 'no_change',
			'detail' => sprintf(
				/* translators: 1: analyzed scripts, 2: deferred scripts. */
				__( '%1$s local scripts analyzed; %2$s safely deferred.', 'ams-cache' ),
				number_format_i18n( count( $scripts ) ),
				number_format_i18n( $deferred )
			),
			'metrics' => array(
				'analyzed' => count( $scripts ),
				'deferred' => $deferred,
			),
		)
	);

	return $html;
}

/**
 * Optimize generated HTML before writing to cache.
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function scm_optimize_html( $html ) {
	if ( ! scm_is_page_optimization_enabled() || empty( $html ) ) {
		return $html;
	}

	$settings = scm_get_page_optimization_settings();
	$html     = apply_filters( 'scm_before_page_optimization', $html, $settings );
	scm_reset_page_optimization_runtime();

	if ( 'yes' === $settings['remove_comments'] ) {
		$html = scm_remove_html_comments( $html );
	}

	if ( 'yes' === $settings['minify_inline_css'] ) {
		$html = scm_minify_inline_css_blocks( $html );
	}

	if ( 'yes' === $settings['lazy_media'] || 'yes' === $settings['critical_images'] ) {
		$html = scm_optimize_media_tags( $html, $settings );
	}

	$html = scm_rewrite_cached_attachment_images( $html, $settings );

	if ( 'yes' === $settings['preconnect_fonts'] ) {
		$html = scm_add_font_preconnects( $html );
	}

	if ( 'yes' === $settings['external_ucss'] ) {
		$html = scm_apply_external_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['local_ucss'] ) {
		$html = scm_apply_local_ucss( $html, $settings );
	}

	if ( 'yes' === $settings['js_analysis'] ) {
		$html = scm_apply_local_js_analysis( $html, $settings );
	}

	if ( 'yes' === $settings['defer_js'] ) {
		$html = scm_defer_scripts( $html, $settings );
	}

	if ( 'yes' === $settings['minify_html'] ) {
		$html = scm_minify_html( $html );
	}

	return apply_filters( 'scm_after_page_optimization', $html, $settings );
}

/**
 * Check shell_exec availability.
 *
 * @return bool
 */
function scm_is_shell_exec_available() {
	if ( ! function_exists( 'shell_exec' ) ) {
		return false;
	}

	$disabled = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ) );

	return ! in_array( 'shell_exec', $disabled, true );
}

/**
 * Build one escaped executable command token.
 *
 * @param string $path Executable path or command.
 *
 * @return string
 */
function scm_get_executable_command( $path ) {
	$path = trim( (string) $path );

	if ( '' === $path ) {
		return '';
	}

	if ( preg_match( '/[\/\\\\]/', $path ) ) {
		if ( ! preg_match( '/^[A-Za-z0-9_ .:\\\\\/-]+$/', $path ) ) {
			return '';
		}

		return escapeshellarg( $path );
	}

	if ( preg_match( '/^[A-Za-z0-9_.-]+$/', $path ) ) {
		return escapeshellcmd( $path );
	}

	return '';
}

/**
 * Run local optimizer command and capture exit code.
 *
 * @param string $command Shell command.
 * @param string $cwd     Optional working directory.
 *
 * @return array
 */
function scm_run_local_optimizer_command( $command, $cwd = '' ) {
	if ( ! scm_is_shell_exec_available() ) {
		return array(
			'passed' => false,
			'output' => __( 'shell_exec is disabled.', 'ams-cache' ),
		);
	}

	if ( '' !== $cwd ) {
		$command = '\\' === DIRECTORY_SEPARATOR
			? 'cd /d ' . escapeshellarg( $cwd ) . ' && ' . $command
			: 'cd ' . escapeshellarg( $cwd ) . ' && ' . $command;
	}

	if ( '\\' === DIRECTORY_SEPARATOR ) {
		$output = @shell_exec( $command . ' 2>&1 & echo SCM_EXIT:%ERRORLEVEL%' );
	} else {
		$path_prefix = 'PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin:$PATH; ';
		$output      = @shell_exec( $path_prefix . $command . ' 2>&1; printf "\nSCM_EXIT:%s" "$?"' );
	}

	$exit = 1;

	if ( preg_match( '/SCM_EXIT:(\d+)/', (string) $output, $match ) ) {
		$exit   = (int) $match[1];
		$output = preg_replace( '/\s*SCM_EXIT:\d+\s*$/', '', (string) $output );
	}

	return array(
		'passed' => 0 === $exit,
		'output' => trim( (string) $output ),
	);
}

/**
 * Check if a configured executable responds to --version.
 *
 * @param string $path Executable path or command.
 *
 * @return array
 */
function scm_check_executable_version( $path ) {
	$path = trim( (string) $path );

	if ( '' === $path ) {
		return array(
			'passed' => false,
			'detail' => __( 'Path is empty.', 'ams-cache' ),
		);
	}

	$command = scm_get_executable_command( $path );

	if ( '' === $command ) {
		return array(
			'passed' => false,
			'detail' => __( 'Path contains unsupported characters.', 'ams-cache' ),
		);
	}

	if ( ! scm_is_shell_exec_available() ) {
		return array(
			'passed' => false,
			'detail' => __( 'shell_exec is disabled.', 'ams-cache' ),
		);
	}

	$result = scm_run_local_optimizer_command( $command . ' --version' );
	$output = trim( (string) $result['output'] );

	if ( '' === $output || ! $result['passed'] || false !== stripos( $output, 'not found' ) || false !== stripos( $output, 'not recognized' ) ) {
		return array(
			'passed' => false,
			'detail' => '' === $output ? sprintf(
					/* translators: %s is executable path. */
					__( 'No response from %s --version.', 'ams-cache' ),
					$path
				) : strtok( $output, "\r\n" ),
		);
	}

	return array(
		'passed' => true,
		'detail' => strtok( $output, "\r\n" ),
	);
}

/**
 * Get page optimization requirement checks.
 *
 * @return array
 */
function scm_get_page_optimization_requirements() {
	$settings       = scm_get_page_optimization_settings();
	$purgecss_check = scm_check_executable_version( $settings['purgecss_path'] );
	$work_dir       = scm_get_page_optimization_work_dir();
	$work_dir_ready = is_dir( $work_dir ) ? is_writable( $work_dir ) : wp_mkdir_p( $work_dir );
	$image_support   = array();
	$image_ready     = true;
	$bun_check       = scm_check_executable_version( $settings['bun_path'] );
	$bun_image_check = scm_check_bun_image_optimizer();
	$bun_formats     = ! empty( $bun_image_check['formats'] ) ? array_map( 'strtolower', (array) $bun_image_check['formats'] ) : array();

	foreach ( $settings['image_formats'] as $format ) {
		$editor_supported = scm_image_editor_supports_format( $format );
		$bun_supported    = in_array( strtolower( (string) $format ), $bun_formats, true );
		$supported        = $editor_supported || $bun_supported;
		$image_ready      = $image_ready && $supported;
		$image_support[]  = strtoupper( $format ) . ': ' . ( $supported ? __( 'supported', 'ams-cache' ) : __( 'not supported', 'ams-cache' ) );
	}

	return array(
		'cache_status' => array(
			'label'  => __( 'Page cache enabled', 'ams-cache' ),
			'passed' => 'enable' === get_option( 'scm_option_caching_status', 'disable' ),
			'detail' => get_option( 'scm_option_caching_status', 'disable' ),
		),
		'permalink'    => array(
			'label'  => __( 'Pretty permalinks enabled', 'ams-cache' ),
			'passed' => '' !== get_option( 'permalink_structure', '' ),
			'detail' => '' !== get_option( 'permalink_structure', '' ) ? get_option( 'permalink_structure' ) : __( 'Plain permalinks', 'ams-cache' ),
		),
		'html_parser'  => array(
			'label'  => __( 'HTML rewrite engine available', 'ams-cache' ),
			'passed' => true,
			'detail' => __( 'Built-in PHP regex processor', 'ams-cache' ),
		),
		'server_gzip'  => array(
			'label'  => __( 'Text compression', 'ams-cache' ),
			'passed' => extension_loaded( 'zlib' ),
			'detail' => extension_loaded( 'zlib' ) ? __( 'PHP zlib available; enable gzip/Brotli on your web server for best results.', 'ams-cache' ) : __( 'Enable gzip/Brotli on your web server.', 'ams-cache' ),
		),
		'shell_exec'   => array(
			'label'  => __( 'Local optimizer command execution', 'ams-cache' ),
			'passed' => scm_is_shell_exec_available(),
			'detail' => scm_is_shell_exec_available() ? __( 'shell_exec is available.', 'ams-cache' ) : __( 'shell_exec is disabled by PHP.', 'ams-cache' ),
		),
		'purgecss'     => array(
			'label'  => __( 'PurgeCSS CLI for Local and External UCSS', 'ams-cache' ),
			'passed' => $purgecss_check['passed'],
			'detail' => $purgecss_check['detail'],
		),
		'bun'          => array(
			'label'  => __( 'Bun for JS Analysis and image optimizer', 'ams-cache' ),
			'passed' => $bun_check['passed'],
			'detail' => $bun_check['detail'],
		),
		'work_dir'     => array(
			'label'  => __( 'Local optimizer workspace', 'ams-cache' ),
			'passed' => $work_dir_ready,
			'detail' => $work_dir_ready ? __( 'Workspace is writable.', 'ams-cache' ) : __( 'Workspace is not writable.', 'ams-cache' ),
		),
		'image_editor' => array(
			'label'  => __( 'Image editor for WebP', 'ams-cache' ),
			'passed' => $image_ready,
			'detail' => implode( ', ', $image_support ),
		),
		'bun_image_optimizer' => array(
			'label'  => __( 'Bun image optimizer', 'ams-cache' ),
			'passed' => $bun_image_check['passed'],
			'detail' => $bun_image_check['detail'],
		),
		'uploads_writable' => array(
			'label'  => __( 'Uploads directory for image variants', 'ams-cache' ),
			'passed' => is_writable( wp_get_upload_dir()['basedir'] ),
			'detail' => is_writable( wp_get_upload_dir()['basedir'] ) ? __( 'Uploads directory is writable.', 'ams-cache' ) : __( 'Uploads directory is not writable.', 'ams-cache' ),
		),
	);
}

/**
 * Get the maximum number of cached entries.
 *
 * A value of 0 disables pruning.
 *
 * @return int
 */
function scm_get_cache_max_entries() {
	$limit = (int) get_option( 'scm_option_cache_max_entries', 0 );

	return max( 0, $limit );
}

/**
 * Prune oldest known cache entries when the cache index grows too large.
 *
 * This only deletes entries recorded in the statistics index, so shared Redis or
 * Memcached stores are not flushed globally.
 *
 * @param \Shieldon\SimpleCache\Cache|null $driver Active cache driver.
 *
 * @return int Removed entry count.
 */
function scm_enforce_cache_entry_limit( $driver = null ) {
	$limit = scm_get_cache_max_entries();

	if ( $limit <= 0 ) {
		return 0;
	}

	$stats_root = scm_get_upload_dir() . '/stats';

	if ( ! is_dir( $stats_root ) ) {
		return 0;
	}

	$entries = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stats_root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( ! $file->isFile() || 'json' !== $file->getExtension() ) {
			continue;
		}

		$filename = $file->getFilename();
		$key      = strstr( $filename, '.', true );

		if ( false === $key || '' === $key ) {
			continue;
		}

		$stats = scm_read_stats_file( $file->getPathname() );

		$entries[] = array(
			'key'  => $key,
			'path' => $file->getPathname(),
			'time' => $file->getMTime(),
			'uri'  => $stats['uri'],
		);
	}

	if ( count( $entries ) <= $limit ) {
		return 0;
	}

	usort(
		$entries,
		function ( $a, $b ) {
			return $a['time'] - $b['time'];
		}
	);

	if ( null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	$remove_count = count( $entries ) - $limit;
	$removed      = 0;

	for ( $i = 0; $i < $remove_count; $i++ ) {
		if ( $driver ) {
			$driver->delete( $entries[ $i ]['key'] );
		}

		if ( ! empty( $entries[ $i ]['uri'] ) ) {
			scm_delete_nginx_static_cache( $entries[ $i ]['uri'] );
		}

		if ( file_exists( $entries[ $i ]['path'] ) ) {
			unlink( $entries[ $i ]['path'] );
		}

		$removed++;
	}

	update_option( 'scm_last_cache_prune_count', $removed );

	return $removed;
}

/**
 * Get advanced driver settings from WordPress options.
 *
 * @param string $type Driver type.
 *
 * @return array
 */
function scm_get_driver_advanced_settings( $type ) {
	if ( 'file' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_file', array() );
	}

	if ( 'memcache' === $type || 'memcached' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_memcached', array() );
	}

	if ( 'redis' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_redis', array() );
	}

	if ( 'mongo' === $type ) {
		return (array) get_option( 'scm_option_advanced_driver_mongodb', array() );
	}

	return array();
}

/**
 * Get advanced driver connection type.
 *
 * @param string $type Driver type.
 *
 * @return string
 */
function scm_get_driver_connection_type( $type ) {
	if ( 'memcache' === $type || 'memcached' === $type ) {
		return get_option( 'scm_option_advanced_driver_memcached_connection_type', 'tcp' );
	}

	if ( 'redis' === $type ) {
		return get_option( 'scm_option_advanced_driver_redis_connection_type', 'tcp' );
	}

	if ( 'mongo' === $type ) {
		return get_option( 'scm_option_advanced_driver_mongodb_connection_type', 'tcp' );
	}

	return 'tcp';
}

/**
 * Normalize driver settings before passing them to Simple Cache.
 *
 * @param array  $setting         Driver settings.
 * @param string $connection_type tcp|socket.
 *
 * @return array
 */
function scm_normalize_driver_settings( $setting, $connection_type = 'tcp' ) {
	foreach ( $setting as $k => $v ) {
		if ( is_string( $v ) ) {
			$v = trim( $v );
		}

		if ( '' === $v ) {
			unset( $setting[ $k ] );
			continue;
		}

		if ( is_numeric( $v ) ) {
			$v = (int) $v;
		}

		if ( 'compress' === $k ) {
			$v = in_array( $v, array( true, 1, '1', 'yes', 'on' ), true );
		}

		if ( 'compress_threshold' === $k ) {
			$v = max( 0, (int) $v );
		}

		if ( 'compress_level' === $k ) {
			$v = max( 1, min( 9, (int) $v ) );
		}

		$setting[ $k ] = $v;
	}

	if ( 'socket' !== $connection_type && isset( $setting['unix_socket'] ) ) {
		unset( $setting['unix_socket'] );
	}

	return $setting;
}

/**
 * Get the cache driver instance.
 *
 * @param string $type The type of the driver.
 *
 * @return \Shieldon\SimpleCache\Cache
 */
function scm_driver_factory( $type ) {

	$advanced_settings        = array();
	$advanced_connection_type = 'tcp';
	$setting                  = array();

	switch ( $type ) {
		case 'mysql':
			$setting = array(
				'host'    => DB_HOST,
				'dbname'  => DB_NAME,
				'user'    => DB_USER,
				'pass'    => DB_PASSWORD,
				'charset' => DB_CHARSET,
			);
			break;

		case 'sqlite':
			$setting = array(
				'storage' => scm_get_upload_dir() . '/sqlite_driver',
			);
			break;

		case 'redis':
			$setting = array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'database' => 0,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'mongo':
			$setting = array(
				'host' => '127.0.0.1',
				'port' => 27017,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'memcache':
		case 'memcached':
			$setting = array(
				'host' => '127.0.0.1',
				'port' => 11211,
			);

			$advanced_settings        = scm_get_driver_advanced_settings( $type );
			$advanced_connection_type = scm_get_driver_connection_type( $type );
			break;

		case 'apc':
		case 'apcu':
		case 'wincache':
			$setting = array();
			break;

		case 'file':
		default:
			$type    = 'file';
			$setting = array(
				'storage' => scm_get_upload_dir() . '/file_driver',
			);

			$advanced_settings = scm_get_driver_advanced_settings( $type );
			break;
	}

	if ( ! empty( $advanced_settings ) ) {
		$setting = array_merge( $setting, scm_normalize_driver_settings( $advanced_settings, $advanced_connection_type ) );
	}

	try {

		$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );

	} catch ( \Exception $e ) {

		if ( in_array( $type, array( 'file', 'sqlite' ), true ) && ! file_exists( $setting['storage'] ) ) {
			wp_mkdir_p( $setting['storage'] );

			// Let's try again.
			$driver = new \Shieldon\SimpleCache\Cache( $type, $setting );
		} else {

			error_log( sprintf( '[AMS Cache] Driver %s is not supported, fallback to use File driver.', $type ) );

			$driver = new \Shieldon\SimpleCache\Cache(
				'file',
				array(
					'storage' => scm_get_upload_dir() . '/file_driver',
				)
			);
		}
	}

	return $driver;
}

/**
 * Get a SVG icon.
 *
 * @param string $type The icon type.
 *
 * Font Awesome Free 5.15.1
 *
 * @return string
 */
function scm_get_svg_icon( $type ) {
	switch ( $type ) {
		case 'status':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 520 562'><path d='M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zM262.655 90c-54.497 0-89.255 22.957-116.549 63.758-3.536 5.286-2.353 12.415 2.715 16.258l34.699 26.31c5.205 3.947 12.621 3.008 16.665-2.122 17.864-22.658 30.113-35.797 57.303-35.797 20.429 0 45.698 13.148 45.698 32.958 0 14.976-12.363 22.667-32.534 33.976C247.128 238.528 216 254.941 216 296v4c0 6.627 5.373 12 12 12h56c6.627 0 12-5.373 12-12v-1.333c0-28.462 83.186-29.647 83.186-106.667 0-58.002-60.165-102-116.531-102zM256 338c-25.365 0-46 20.635-46 46 0 25.364 20.635 46 46 46s46-20.636 46-46c0-25.365-20.635-46-46-46z'/></svg>";
			break;

		case 'memory':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 582 552'><path d='M640 130.94V96c0-17.67-14.33-32-32-32H32C14.33 64 0 78.33 0 96v34.94c18.6 6.61 32 24.19 32 45.06s-13.4 38.45-32 45.06V320h640v-98.94c-18.6-6.61-32-24.19-32-45.06s13.4-38.45 32-45.06zM224 256h-64V128h64v128zm128 0h-64V128h64v128zm128 0h-64V128h64v128zM0 448h64v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h64v-96H0v96z'/></svg>";
			break;

		case 'database':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 482 572'><path d='M448 73.143v45.714C448 159.143 347.667 192 224 192S0 159.143 0 118.857V73.143C0 32.857 100.333 0 224 0s224 32.857 224 73.143zM448 176v102.857C448 319.143 347.667 352 224 352S0 319.143 0 278.857V176c48.125 33.143 136.208 48.572 224 48.572S399.874 209.143 448 176zm0 160v102.857C448 479.143 347.667 512 224 512S0 479.143 0 438.857V336c48.125 33.143 136.208 48.572 224 48.572S399.874 369.143 448 336z'/></svg>";
			break;

		case 'speed':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M296 160H180.6l42.6-129.8C227.2 15 215.7 0 200 0H56C44 0 33.8 8.9 32.2 20.8l-32 240C-1.7 275.2 9.5 288 24 288h118.7L96.6 482.5c-3.6 15.2 8 29.5 23.3 29.5 8.4 0 16.4-4.4 20.8-12l176-304c9.3-15.9-2.2-36-20.7-36z'/></svg>";
			break;
	}

	return $svg;
}

/**
 * Get default configuation.
 *
 * This function is also used in expert mode.
 *
 * @return array
 */
function scm_get_default_config() {

	return array(
		'cache_driver'             => 'file',
		'cache_key_prefix'         => '',
		'cache_max_entries'        => 0,
		'nginx_direct_cache'       => false,
		'html_debug_comment'       => true,
		'driver_advanced_settings' => array(),
		'site_url'                 => '',
		'preload'                  => array(
			'enable'               => false,
			'limit'                => 50,
			'crawl_homepage_links' => true,
		),
		'woocommerce'              => array(
			'enable' => false,
		),

		'exclusion'                => array(
			'enable'             => false,
			'excluded_list'      => array(),
			'excluded_get_vars'  => array(),
			'excluded_post_vars' => array(),
			'excluded_cookie_vars' => array(),
		),
	);
}

/**
 * The variable stack for JavaScript snippet.
 *
 * This function is also used in expert mode.
 *
 * @param string      $key      The key of the field.
 * @param string|int  $value    The value of the field.
 * @param string      $poistion The position.
 *
 * @return void
 */
function scm_variable_stack( $key, $value = '', $poistion = 'before' ) {
	static $vars = array();

	if ( is_null( $key ) ) {
		$output = $vars;
		$vars   = array();

		return json_encode( $output );
	}

	$vars[ $poistion ][ $key ] = $value;
}

/**
 * Create JavaScript snippet used for performance report.
 *
 * This function is also used in expert mode.
 *
 * @return void
 */
function scm_javascript() {
	$script = '
		<script id="ams-cache-plugin">
			var cache_master = \'' . scm_variable_stack( null ) . '\';
			var scm_report   = JSON.parse(cache_master);

			var scm_text_cache_status = "";
			var scm_text_memory_usage = "";
			var scm_text_sql_queries  = "";
			var scm_text_page_generation_time = "";

			if ("before" in scm_report) {
				scm_text_cache_status = "ទេ";
				scm_text_memory_usage = scm_report["before"]["memory_usage"];
				scm_text_sql_queries = scm_report["before"]["sql_queries"];
				scm_text_page_generation_time = scm_report["before"]["page_generation_time"];
			}
			if ("after" in scm_report) {
				scm_text_cache_status = "មាន";
				scm_text_memory_usage = scm_report["after"]["memory_usage"];
				scm_text_sql_queries = scm_report["after"]["sql_queries"];
				scm_text_page_generation_time = scm_report["after"]["page_generation_time"];
			}
			var scm_field_cache_status = document.querySelector(".scm-field-cache-status");
			if (null !== scm_field_cache_status) { scm_field_cache_status.textContent = scm_text_cache_status; }
			var scm_field_memory_usage = document.querySelector(".scm-field-memory-usage");
			if (null !== scm_field_memory_usage) { scm_field_memory_usage.textContent = scm_text_memory_usage; }
			var scm_field_sql_queries = document.querySelector(".scm-field-sql-queries");
			if (null !== scm_field_sql_queries) { scm_field_sql_queries.textContent = scm_text_sql_queries; }
			var scm_field_page_generation_time = document.querySelector(".scm-field-page-generation-time");
			if (null !== scm_field_page_generation_time) { scm_field_page_generation_time.textContent = scm_text_page_generation_time; }
			var cache_master_benchmark_report = document.querySelector(".ams-cache-benchmark-report");
			if (null !== cache_master_benchmark_report) { cache_master_benchmark_report.setAttribute("style", ""); }
			var cache_master_plugin_widget_wrapper = document.querySelector(".ams-cache-plugin-widget-wrapper");
			if (null !== cache_master_plugin_widget_wrapper) { cache_master_plugin_widget_wrapper.setAttribute("style", ""); }
		</script>
	';

	return preg_replace( '/\s+/', ' ', $script );
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'scm_preload_cache_event', 'scm_preload_cache' );
	add_action( 'scm_preload_queue_event', 'scm_process_preload_queue' );
}

/**
 * Check whether cache preloading is enabled.
 *
 * @return bool
 */
function scm_is_preload_enabled() {
	return 'yes' === get_option( 'scm_option_preload_cache', 'no' );
}

/**
 * Get the configured preload URL limit.
 *
 * @param int|null $limit Optional limit override.
 *
 * @return int
 */
function scm_get_preload_limit( $limit = null ) {
	$limit     = is_null( $limit ) ? (int) get_option( 'scm_option_preload_limit', 50 ) : (int) $limit;
	$max_limit = (int) apply_filters( 'scm_preload_max_limit', 1000 );
	$max_limit = max( 1, min( 5000, $max_limit ) );

	return max( 1, min( $max_limit, $limit ) );
}

/**
 * Get the number of URLs to dispatch per preload batch.
 *
 * @return int
 */
function scm_get_preload_batch_size() {
	$batch_size = (int) apply_filters( 'scm_preload_batch_size', 25 );

	return max( 1, min( 100, $batch_size ) );
}

/**
 * Schedule a background cache preload.
 *
 * @param bool $force Replace an existing scheduled preload.
 *
 * @return void
 */
function scm_schedule_preload_cache( $force = false ) {
	if ( ! function_exists( 'wp_schedule_single_event' ) || ! scm_is_preload_enabled() ) {
		return;
	}

	if ( $force && function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'scm_preload_cache_event' );
	}

	if ( $force || ! wp_next_scheduled( 'scm_preload_cache_event' ) ) {
		wp_schedule_single_event( time() + 5, 'scm_preload_cache_event' );
	}
}

/**
 * Schedule the next preload queue batch.
 *
 * @param int $delay Delay in seconds.
 *
 * @return void
 */
function scm_schedule_preload_queue( $delay = 15 ) {
	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		return;
	}

	if ( ! wp_next_scheduled( 'scm_preload_queue_event' ) ) {
		wp_schedule_single_event( time() + max( 1, (int) $delay ), 'scm_preload_queue_event' );
	}
}

/**
 * Preload a single URL with an unauthenticated loopback request.
 *
 * @param string $url URL to preload.
 *
 * @return void
 */
function scm_preload_url( $url, $blocking = false, $timeout = 1 ) {
	if ( empty( $url ) || ! function_exists( 'wp_remote_get' ) ) {
		return null;
	}

	return wp_remote_get(
		esc_url_raw( $url ),
		array(
			'blocking'    => (bool) $blocking,
			'timeout'     => max( 1, (int) $timeout ),
			'redirection' => 2,
			'user-agent'  => 'AMS Cache Preloader/' . SCM_PLUGIN_VERSION,
		)
	);
}

/**
 * Get post types enabled for preload.
 *
 * @return array
 */
function scm_get_preload_post_types() {
	$post_types = (array) get_option( 'scm_option_post_types', array() );
	$post_types = array_keys(
		array_filter(
			$post_types,
			function ( $enabled ) {
				return 'yes' === $enabled;
			}
		)
	);

	$post_types = array_values( array_diff( $post_types, array( 'home' ) ) );

	if ( empty( $post_types ) ) {
		$post_types = array( 'post' );
	}

	return apply_filters( 'scm_preload_post_types', $post_types );
}

/**
 * Convert a link from homepage HTML into a same-site absolute URL.
 *
 * @param string $href     Raw href value.
 * @param string $base_url Base URL.
 *
 * @return string
 */
function scm_normalize_homepage_link_url( $href, $base_url ) {
	$href = trim( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ) );

	if ( '' === $href || '#' === $href || preg_match( '#^(?:javascript|mailto|tel|data):#i', $href ) ) {
		return '';
	}

	$href = strtok( $href, '#' );

	if ( false === $href || '' === $href ) {
		return '';
	}

	$home_parts = parse_url( home_url( '/' ) );
	$base_parts = parse_url( $base_url );
	$href_parts = parse_url( $href );
	$scheme     = isset( $home_parts['scheme'] ) ? $home_parts['scheme'] : 'https';
	$host       = isset( $home_parts['host'] ) ? $home_parts['host'] : '';
	$port       = isset( $home_parts['port'] ) ? ':' . $home_parts['port'] : '';

	if ( empty( $host ) ) {
		return '';
	}

	if ( 0 === strpos( $href, '//' ) ) {
		$href = $scheme . ':' . $href;
		$href_parts = parse_url( $href );
	}

	if ( ! empty( $href_parts['scheme'] ) && ! empty( $href_parts['host'] ) ) {
		if ( strtolower( $href_parts['host'] ) !== strtolower( $host ) ) {
			return '';
		}

		$path = isset( $href_parts['path'] ) ? $href_parts['path'] : '/';
	} elseif ( 0 === strpos( $href, '/' ) ) {
		$path = $href;
	} else {
		$base_path = isset( $base_parts['path'] ) ? $base_parts['path'] : '/';
		$base_dir  = rtrim( dirname( $base_path ), '\\/' );
		$path      = ( '/' === $base_dir ? '' : $base_dir ) . '/' . $href;
	}

	$path = scm_normalize_cache_uri( $path );

	if ( ! scm_is_cacheable_document_path( $path ) ) {
		return '';
	}

	return $scheme . '://' . $host . $port . $path;
}

/**
 * Crawl homepage HTML and return internal URLs in document order.
 *
 * @param int $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_homepage_discovered_urls( $limit = 100 ) {
	if ( ! function_exists( 'wp_remote_get' ) ) {
		return array();
	}

	$limit  = scm_get_preload_limit( $limit );
	$cached = get_transient( 'scm_homepage_discovered_urls' );

	if ( is_array( $cached ) && isset( $cached['urls'], $cached['limit'] ) && (int) $cached['limit'] >= $limit ) {
		return array_slice( (array) $cached['urls'], 0, $limit );
	}

	$home_url = home_url( '/' );
	$result   = wp_remote_get(
		$home_url,
		array(
			'blocking'    => true,
			'timeout'     => 8,
			'redirection' => 3,
			'user-agent'  => 'AMS Cache Preload Crawler/' . SCM_PLUGIN_VERSION,
		)
	);

	if ( is_wp_error( $result ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$status = (int) wp_remote_retrieve_response_code( $result );

	if ( $status < 200 || $status >= 400 ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$html = wp_remote_retrieve_body( $result );

	if ( empty( $html ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	preg_match_all( '/<a\b[^>]*\shref\s*=\s*(["\'])(.*?)\1/i', $html, $matches );

	if ( empty( $matches[2] ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$urls = array();

	foreach ( $matches[2] as $href ) {
		$url = scm_normalize_homepage_link_url( $href, $home_url );

		if ( '' === $url || in_array( $url, $urls, true ) ) {
			continue;
		}

		$urls[] = $url;

		if ( count( $urls ) >= $limit ) {
			break;
		}
	}

	set_transient(
		'scm_homepage_discovered_urls',
		array(
			'urls'  => $urls,
			'limit' => $limit,
		),
		5 * MINUTE_IN_SECONDS
	);

	return apply_filters( 'scm_homepage_discovered_preload_urls', $urls, $limit );
}

/**
 * Fallback priority URLs when the homepage crawler cannot see internal links.
 *
 * @param int   $limit         Maximum URLs.
 * @param array $existing_urls URLs already selected.
 *
 * @return array
 */
function scm_get_homepage_priority_fallback_urls( $limit = 100, $existing_urls = array() ) {
	$limit = scm_get_preload_limit( $limit );

	if ( $limit <= 0 ) {
		return array();
	}

	$existing_map = array();

	foreach ( (array) $existing_urls as $url ) {
		$existing_map[ scm_normalize_cache_uri( $url ) ] = true;
	}

	$urls  = array();
	$posts = get_posts(
		array(
			'post_type'           => scm_get_preload_post_types(),
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => false,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);

	foreach ( $posts as $post_id ) {
		$url = get_permalink( $post_id );
		$key = scm_normalize_cache_uri( $url );

		if ( empty( $url ) || isset( $existing_map[ $key ] ) ) {
			continue;
		}

		$existing_map[ $key ] = true;
		$urls[]              = $url;

		if ( count( $urls ) >= $limit ) {
			break;
		}
	}

	return apply_filters( 'scm_homepage_priority_fallback_urls', $urls, $limit, $existing_urls );
}

/**
 * Get homepage and same-site links discovered from homepage.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_homepage_priority_preload_urls( $limit = null ) {
	$limit = scm_get_preload_limit( $limit );
	$urls  = array();

	if ( 'yes' === get_option( 'scm_option_post_homepage', 'yes' ) ) {
		$urls[] = home_url( '/' );
	}

	if ( 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ) && count( $urls ) < $limit ) {
		$discovered_urls = scm_get_homepage_discovered_urls( $limit - count( $urls ) );

		foreach ( $discovered_urls as $url ) {
			$urls[] = $url;

			if ( count( $urls ) >= $limit ) {
				break;
			}
		}

		$minimum_discovered = (int) apply_filters( 'scm_homepage_priority_min_discovered_urls', 25 );

		if ( count( $discovered_urls ) < $minimum_discovered && count( $urls ) < $limit ) {
			foreach ( scm_get_homepage_priority_fallback_urls( $limit - count( $urls ), $urls ) as $url ) {
				$urls[] = $url;

				if ( count( $urls ) >= $limit ) {
					break;
				}
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_homepage_priority_preload_urls', $urls, $limit ), 0, $limit );
}

/**
 * Warm homepage and homepage-discovered links first.
 *
 * @param int|null $limit    Maximum URLs.
 * @param bool     $blocking Use blocking requests.
 *
 * @return int
 */
function scm_preload_homepage_priority_urls( $limit = null, $blocking = true ) {
	if ( ! scm_is_preload_enabled() ) {
		return 0;
	}

	$urls           = scm_get_homepage_priority_preload_urls( $limit );
	$dispatch_limit = count( $urls );

	if ( $blocking ) {
		$dispatch_limit = min( $dispatch_limit, (int) apply_filters( 'scm_homepage_priority_blocking_limit', 1 ) );
	}

	foreach ( array_slice( $urls, 0, $dispatch_limit ) as $url ) {
		scm_preload_url( $url, $blocking, $blocking ? 8 : 1 );
	}

	update_option( 'scm_last_homepage_priority_preload_time', time() );
	update_option( 'scm_last_homepage_priority_preload_count', count( $urls ) );

	return $dispatch_limit;
}

/**
 * Build a short list of homepage and archive URLs to warm immediately.
 *
 * @param int $post_id Optional published post ID for affected archives.
 * @param int $limit   Maximum URLs.
 *
 * @return array
 */
function scm_get_critical_preload_urls( $post_id = 0, $limit = 25 ) {
	$limit = max( 1, min( 100, (int) $limit ) );
	$urls  = array();
	$post  = $post_id ? get_post( $post_id ) : null;

	$add_url = function ( $url ) use ( &$urls, $limit ) {
		if ( count( $urls ) >= $limit || empty( $url ) ) {
			return;
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $url ) ) {
			return;
		}

		$urls[] = (string) $url;
	};

	if ( 'yes' === get_option( 'scm_option_post_homepage', 'yes' ) ) {
		$add_url( home_url( '/' ) );
	}

	$post_archives = (array) get_option( 'scm_option_post_archives', array() );

	if ( $post && 'publish' === $post->post_status ) {
		if ( isset( $post_archives['category'] ) && 'yes' === $post_archives['category'] ) {
			foreach ( wp_get_post_categories( $post->ID ) as $term_id ) {
				$add_url( get_category_link( (int) $term_id ) );
			}
		}

		if ( isset( $post_archives['tag'] ) && 'yes' === $post_archives['tag'] ) {
			foreach ( wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) ) as $term_id ) {
				$add_url( get_tag_link( (int) $term_id ) );
			}
		}

		if ( isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
			$add_url( get_author_posts_url( (int) $post->post_author ) );
		}

		if ( isset( $post_archives['date'] ) && 'yes' === $post_archives['date'] ) {
			$timestamp = strtotime( $post->post_date );

			if ( $timestamp ) {
				$add_url( get_year_link( (int) gmdate( 'Y', $timestamp ) ) );
				$add_url( get_month_link( (int) gmdate( 'Y', $timestamp ), (int) gmdate( 'm', $timestamp ) ) );
			}
		}

		$archive_key = 'archive_' . $post->post_type;

		if ( isset( $post_archives[ $archive_key ] ) && 'yes' === $post_archives[ $archive_key ] ) {
			$add_url( get_post_type_archive_link( $post->post_type ) );
		}

		if ( 'yes' === get_option( 'scm_option_woocommerce_status', 'no' ) && 'product' === $post->post_type ) {
			$woocommerce_archives = (array) get_option( 'scm_option_woocommerce_post_archives', array() );

			foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
				if ( isset( $woocommerce_archives[ $taxonomy ] ) && 'yes' === $woocommerce_archives[ $taxonomy ] ) {
					foreach ( wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) ) as $term_id ) {
						$add_url( get_term_link( (int) $term_id, $taxonomy ) );
					}
				}
			}
		}
	} else {
		foreach ( array( 'category' => 'get_category_link', 'tag' => 'get_tag_link' ) as $archive => $link_function ) {
			if ( isset( $post_archives[ $archive ] ) && 'yes' === $post_archives[ $archive ] ) {
				$taxonomy = ( 'tag' === $archive ) ? 'post_tag' : 'category';
				$terms    = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => true,
						'fields'     => 'ids',
						'number'     => max( 1, $limit - count( $urls ) ),
					)
				);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term_id ) {
						$add_url( $link_function( (int) $term_id ) );
					}
				}
			}
		}

		if ( isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
			$authors = get_users(
				array(
					'fields'              => 'ID',
					'has_published_posts' => true,
					'number'              => max( 1, $limit - count( $urls ) ),
				)
			);

			foreach ( $authors as $author_id ) {
				$add_url( get_author_posts_url( (int) $author_id ) );
			}
		}

		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects', 'and' ) as $post_type ) {
			$archive_key = 'archive_' . $post_type->name;

			if ( isset( $post_archives[ $archive_key ] ) && 'yes' === $post_archives[ $archive_key ] ) {
				$add_url( get_post_type_archive_link( $post_type->name ) );
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_critical_preload_urls', $urls, $post_id, $limit ), 0, $limit );
}

/**
 * Immediately warm homepage and archive URLs.
 *
 * @param int                            $post_id         Optional published post ID.
 * @param bool                           $delete_existing Delete matching cache keys first.
 * @param \Shieldon\SimpleCache\Cache|null $driver        Cache driver.
 *
 * @return int Number of dispatched URLs.
 */
function scm_preload_critical_urls( $post_id = 0, $delete_existing = true, $driver = null ) {
	if ( 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return 0;
	}

	$limit = (int) apply_filters( 'scm_critical_preload_limit', 25, $post_id );
	$urls  = scm_get_critical_preload_urls( $post_id, $limit );

	if ( empty( $urls ) ) {
		return 0;
	}

	if ( $delete_existing && null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	foreach ( $urls as $url ) {
		if ( $delete_existing && $driver ) {
			$path = parse_url( $url, PHP_URL_PATH );
			$path = empty( $path ) ? '/' : $path;

			scm_purge_cache_uri( $path, $driver );
		}

		$path        = parse_url( $url, PHP_URL_PATH );
		$is_homepage = scm_is_homepage_uri( empty( $path ) ? '/' : $path );

		scm_preload_url( $url, $is_homepage, $is_homepage ? 8 : 1 );
	}

	update_option( 'scm_last_critical_preload_time', time() );
	update_option( 'scm_last_critical_preload_count', count( $urls ) );

	return count( $urls );
}

/**
 * Build URL list for preload.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_preload_urls( $limit = null, $priority_urls = null ) {
	$limit = scm_get_preload_limit( $limit );
	$urls  = is_array( $priority_urls ) ? $priority_urls : scm_get_homepage_priority_preload_urls( $limit );

	$post_types = scm_get_preload_post_types();

	if ( ! empty( $post_types ) && count( $urls ) < $limit ) {
		$remaining = $limit - count( $urls );
		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $remaining,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $posts as $post_id ) {
			$urls[] = get_permalink( $post_id );

			if ( count( $urls ) >= $limit ) {
				break;
			}
		}
	}

	$post_archives = (array) get_option( 'scm_option_post_archives', array() );

	if ( count( $urls ) < $limit && isset( $post_archives['category'] ) && 'yes' === $post_archives['category'] ) {
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$urls[] = get_category_link( $term_id );
			}
		}
	}

	if ( count( $urls ) < $limit && isset( $post_archives['tag'] ) && 'yes' === $post_archives['tag'] ) {
		$terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$urls[] = get_tag_link( $term_id );
			}
		}
	}

	if ( count( $urls ) < $limit && isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
		$authors = get_users( array( 'has_published_posts' => true, 'number' => $limit - count( $urls ) ) );

		foreach ( $authors as $author ) {
			$urls[] = get_author_posts_url( $author->ID );
		}
	}

	foreach ( $post_archives as $archive => $enabled ) {
		if ( count( $urls ) >= $limit ) {
			break;
		}

		if ( 'yes' === $enabled && 0 === strpos( $archive, 'archive_' ) ) {
			$post_type = substr( $archive, 8 );
			$url       = get_post_type_archive_link( $post_type );

			if ( $url ) {
				$urls[] = $url;
			}
		}
	}

	$woocommerce_archives = (array) get_option( 'scm_option_woocommerce_post_archives', array() );

	foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
		if ( count( $urls ) >= $limit ) {
			break;
		}

		if ( isset( $woocommerce_archives[ $taxonomy ] ) && 'yes' === $woocommerce_archives[ $taxonomy ] ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					$url = get_term_link( (int) $term_id, $taxonomy );

					if ( ! is_wp_error( $url ) ) {
						$urls[] = $url;
					}
				}
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_preload_urls', $urls, $limit ), 0, $limit );
}

/**
 * Dispatch preload requests.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return int Number of queued URLs.
 */
function scm_preload_cache( $limit = null ) {
	if ( ! scm_is_preload_enabled() ) {
		return 0;
	}

	$limit         = scm_get_preload_limit( $limit );
	delete_transient( 'scm_homepage_discovered_urls' );
	$priority_urls = scm_get_homepage_priority_preload_urls( $limit );
	$urls          = scm_get_preload_urls( $limit, $priority_urls );

	update_option( 'scm_last_preload_time', time() );
	update_option( 'scm_last_preload_count', count( $urls ) );
	update_option( 'scm_last_preload_priority_count', count( $priority_urls ) );
	update_option( 'scm_preload_queue_total', count( $urls ) );
	update_option( 'scm_preload_queue_processed', 0 );
	update_option( 'scm_preload_queue_remaining', count( $urls ) );
	update_option( 'scm_preload_queue_started_time', time() );
	update_option( 'scm_preload_queue_finished_time', 0 );

	set_transient( 'scm_preload_queue', $urls, 12 * HOUR_IN_SECONDS );

	scm_process_preload_queue();

	return count( $urls );
}

/**
 * Process one preload queue batch.
 *
 * @return int Number of dispatched URLs.
 */
function scm_process_preload_queue() {
	$queue = get_transient( 'scm_preload_queue' );

	if ( empty( $queue ) || ! is_array( $queue ) ) {
		delete_transient( 'scm_preload_queue' );
		update_option( 'scm_preload_queue_remaining', 0 );
		return 0;
	}

	$queue      = array_values( array_unique( array_filter( $queue ) ) );
	$batch_size = scm_get_preload_batch_size();
	$batch      = array_slice( $queue, 0, $batch_size );
	$remaining  = array_slice( $queue, count( $batch ) );
	$processed  = (int) get_option( 'scm_preload_queue_processed', 0 );

	foreach ( $batch as $index => $url ) {
		$path        = parse_url( $url, PHP_URL_PATH );
		$is_homepage = scm_is_homepage_uri( empty( $path ) ? '/' : $path );
		$blocking    = $is_homepage && 0 === $processed && 0 === $index;

		scm_preload_url( $url, $blocking, $blocking ? 8 : 1 );
	}

	$processed += count( $batch );

	update_option( 'scm_preload_queue_processed', $processed );
	update_option( 'scm_preload_queue_remaining', count( $remaining ) );

	if ( empty( $remaining ) ) {
		delete_transient( 'scm_preload_queue' );
		update_option( 'scm_preload_queue_finished_time', time() );
		return count( $batch );
	}

	set_transient( 'scm_preload_queue', $remaining, 12 * HOUR_IN_SECONDS );
	scm_schedule_preload_queue();

	return count( $batch );
}

/**
 * Register WordPress hooks that are unsafe before WordPress core loads.
 *
 * Expert Mode includes helpers.php from wp-config.php before add_filter() exists,
 * so hook registration must be delayed until the plugin runs inside WordPress.
 *
 * @return void
 */
function scm_register_wordpress_hooks() {
	static $registered = false;

	if ( $registered || ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
		return;
	}

	add_filter( 'wp_generate_attachment_metadata', 'scm_queue_image_optimization_on_upload', 1, 2 );
	add_filter( 'wp_update_attachment_metadata', 'scm_optimize_image_metadata_on_update', 5, 2 );
	add_action( 'scm_process_image_optimization_queue', 'scm_process_image_optimization_queue' );
	add_action( 'scm_process_single_image_optimization', 'scm_process_single_image_optimization', 10, 1 );
	add_filter( 'wp_get_attachment_image', 'scm_filter_attachment_image_html', 20, 5 );
	add_filter( 'khs3data_local_deletion_rule', 'scm_preserve_local_images_for_pending_optimization', 20, 2 );
	add_filter( 'khs3data_should_offload_attachment', 'scm_should_delay_advanced_media_offload_for_image_optimization', 5, 2 );
	add_action( 'khs3data_after_upload_to_cloud', 'scm_cleanup_original_files_after_kh_offload', 10, 1 );
	add_action( 'khs3data_after_delete_regenerated_local_thumbnails', 'scm_cleanup_original_files_after_kh_offload', 10, 1 );
	add_action( 'khs3data_after_cleanup_local_files', 'scm_cleanup_original_files_after_kh_offload', 10, 1 );

	$registered = true;
}

scm_register_wordpress_hooks();

