<?php
/**
 * KH Speed Engine — optimizer/settings.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

/**
 * Return the optimization toggle keys shown in the dashboard.
 *
 * @return array
 */
function khse_get_page_optimization_toggle_keys() {
	return array(
		'status',
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
	);
}

/**
 * Return the optimization feature keys (everything except the master switch).
 *
 * @return array
 */
function khse_get_page_optimization_feature_keys() {
	return array_values( array_diff( khse_get_page_optimization_toggle_keys(), array( 'status' ) ) );
}

function khse_get_default_page_optimization_settings() {
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
		'bun_path'             => '',
		'purgecss_path'        => '',
		'ucss_safelist'        => implode(
			"\n",
			array_merge(
				khse_get_protected_ucss_safelist(),
				array( 'wp-block', 'alignwide', 'alignfull' )
			)
		),
		'critical_image_count' => 1,
		'external_ucss_max_file_size' => 307200,
		'local_ucss_max_file_size' => 524288,
		'js_analysis_max_script_bytes' => 262144,
		'js_analysis_max_total_bytes' => 1048576,
		'media_exclusions'     => "logo\navatar\ncaptcha",
		'js_exclusions'        => implode( "\n", khse_get_protected_js_exclusions() ),
	);
}

/**
 * Get page optimization settings.
 *
 * @return array
 */
function khse_get_page_optimization_settings() {
	$settings = (array) get_option( 'khse_option_page_optimization', array() );
	$settings = array_merge( khse_get_default_page_optimization_settings(), $settings );

	$settings['critical_image_count'] = max( 0, min( 5, (int) $settings['critical_image_count'] ) );
	$settings['bun_path']             = trim( (string) $settings['bun_path'] );
	$settings['purgecss_path']        = trim( (string) $settings['purgecss_path'] );
	$settings['external_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['external_ucss_max_file_size'] ) );
	$settings['local_ucss_max_file_size'] = max( 51200, min( 1048576, (int) $settings['local_ucss_max_file_size'] ) );
	$settings['js_analysis_max_script_bytes'] = max( 16384, min( 1048576, (int) $settings['js_analysis_max_script_bytes'] ) );
	$settings['js_analysis_max_total_bytes'] = max( 65536, min( 4194304, (int) $settings['js_analysis_max_total_bytes'] ) );
	$settings['ucss_safelist']        = khse_merge_textarea_lines( $settings['ucss_safelist'], khse_get_protected_ucss_safelist() );
	$settings['js_exclusions']        = khse_merge_textarea_lines( $settings['js_exclusions'], khse_get_protected_js_exclusions() );

	return $settings;
}

/**
 * Get CSS selectors/classes that stay protected for interactive frontends.
 *
 * @return array
 */
function khse_get_protected_ucss_safelist() {
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
function khse_get_protected_js_exclusions() {
	return array(
		'jquery',
		'wp-includes/js/jquery',
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
 * Check whether page optimization is enabled.
 *
 * @return bool
 */
function khse_is_page_optimization_enabled() {
	$settings = khse_get_page_optimization_settings();

	return 'yes' === $settings['status'];
}

/**
 * Get the optimization report directory.
 *
 * @return string
 */
function khse_get_page_optimization_report_dir() {
	return khse_get_upload_dir() . '/optimization_reports';
}

/**
 * Get local optimizer workspace root.
 *
 * @return string
 */
function khse_get_page_optimization_work_dir() {
	return khse_get_private_runtime_dir() . '/optimization_work';
}

/**
 * Ensure the local optimizer workspace exists.
 *
 * @return string Workspace path.
 */
function khse_ensure_page_optimization_workspace() {
	$dir = khse_get_page_optimization_work_dir();

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	return $dir;
}

/**
 * Get latest optimization runtime metadata.
 *
 * @return array
 */
function khse_get_page_optimization_runtime() {
	return isset( $GLOBALS['khse_page_optimization_runtime'] ) && is_array( $GLOBALS['khse_page_optimization_runtime'] )
		? $GLOBALS['khse_page_optimization_runtime']
		: array();
}

/**
 * Reset optimization runtime metadata.
 *
 * @return void
 */
function khse_reset_page_optimization_runtime() {
	$GLOBALS['khse_page_optimization_runtime'] = array();
}

/**
 * Store optimization runtime metadata.
 *
 * @param string $key   Runtime key.
 * @param array  $value Runtime payload.
 *
 * @return void
 */
function khse_set_page_optimization_runtime( $key, $value ) {
	if ( ! isset( $GLOBALS['khse_page_optimization_runtime'] ) || ! is_array( $GLOBALS['khse_page_optimization_runtime'] ) ) {
		$GLOBALS['khse_page_optimization_runtime'] = array();
	}

	$GLOBALS['khse_page_optimization_runtime'][ $key ] = $value;
}

/**
 * Create denied local optimizer workspace.
 *
 * @param string $prefix Workspace prefix.
 *
 * @return string
 */
function khse_create_page_optimization_workspace( $prefix ) {
	$root = khse_get_page_optimization_work_dir();

	if ( ! is_dir( $root ) ) {
		wp_mkdir_p( $root );
	}

	if ( is_dir( $root ) ) {
		$index = trailingslashit( $root ) . 'index.html';
		$deny  = trailingslashit( $root ) . '.htaccess';

		if ( ! file_exists( $index ) && false === @file_put_contents( $index, '' ) ) {
			error_log( '[KH Speed Engine] Could not protect the optimizer workspace: ' . $root );
			return '';
		}

		if ( ! file_exists( $deny ) && false === @file_put_contents( $deny, "Require all denied\nDeny from all" ) ) {
			error_log( '[KH Speed Engine] Could not protect the optimizer workspace: ' . $root );
			return '';
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
function khse_delete_page_optimization_workspace( $dir ) {
	$root = realpath( khse_get_page_optimization_work_dir() );
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
			@rmdir( $file->getPathname() );
		} else {
			@unlink( $file->getPathname() );
		}
	}

	@rmdir( $path );
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_init', 'khse_ensure_page_optimization_workspace' );
}
