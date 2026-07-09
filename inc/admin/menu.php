<?php
/**
 * AMS Cache - Menu.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.2.1
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

add_action( 'admin_menu', 'scm_option' );
add_action( 'admin_enqueue_scripts', 'scm_admin_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'scm_admin_enqueue_styles' );
add_filter( 'plugin_action_links_' . SCM_PLUGIN_NAME, 'scm_plugin_action_links', 10, 5 );
add_filter( 'plugin_row_meta', 'scm_plugin_extend_links', 10, 2 );

/**
 * Register the plugin setting page.
 *
 * @return void
 */
function scm_option() {

	add_menu_page(
		__( 'AMS Cache', 'ams-cache' ),
		__( 'AMS Cache', 'ams-cache' ),
		'manage_options',
		'ams-cache-dashboard',
		'scm_dashboard_page',
		'dashicons-superhero'
	);

	add_submenu_page(
		'ams-cache-dashboard',
		__( 'Dashboard', 'ams-cache' ),
		__( 'Dashboard', 'ams-cache' ),
		'manage_options',
		'ams-cache-dashboard',
		'scm_dashboard_page'
	);

	add_submenu_page(
		'ams-cache-dashboard',
		__( 'Settings', 'ams-cache' ),
		__( 'Settings', 'ams-cache' ),
		'manage_options',
		'ams-cache-settings',
		'scm_options_page'
	);

	add_submenu_page(
		'ams-cache-dashboard',
		__( 'Expert Mode', 'ams-cache' ),
		__( 'Expert Mode', 'ams-cache' ),
		'manage_options',
		'ams-cache-expert-mode',
		'scm_expert_mode_page'
	);

	add_submenu_page(
		'ams-cache-dashboard',
		__( 'Statistics', 'ams-cache' ),
		__( 'Statistics', 'ams-cache' ),
		'manage_options',
		'ams-cache-statistics',
		'scm_stats_page'
	);

	add_submenu_page(
		'ams-cache-dashboard',
		__( 'About', 'ams-cache' ),
		__( 'About', 'ams-cache' ),
		'manage_options',
		'ams-cache-about',
		'scm_about_page'
	);
}

/**
 * Output the dashboard page.
 *
 * @return void
 */
function scm_dashboard_page() {
	scm_show_settings_header();
	echo scm_load_view( 'page_dashboard' );
	scm_show_settings_footer();
}

/**
 * Output the setting page.
 *
 * @return void
 */
function scm_options_page() {
	scm_dashboard_page();
}

/**
 * Output the expert mode page.
 *
 * @return void
 */
function scm_expert_mode_page() {
	scm_dashboard_page();
}

/**
 * Output the stats page.
 *
 * @return void
 */
function scm_stats_page() {
	scm_dashboard_page();
}

/**
 * Output the banchmark setting page.
 *
 * @return void
 */
function scm_benchmark_settings_page() {
	scm_show_settings_header();
	echo scm_load_view( 'page_benchmark_settings' );
	scm_show_settings_footer();
}

/**
 * Output the about page.
 *
 * @return void
 */
function scm_about_page() {
	scm_dashboard_page();
}

/**
 * Resolve the initial React console view from the current admin URL.
 *
 * @return string
 */
function scm_get_admin_console_view() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'ams-cache-dashboard';
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	$allowed_views = array(
		'overview',
		'cache',
		'preload',
		'performance',
		'advanced',
		'rules',
		'statistics',
		'expert',
		'benchmark',
		'woocommerce',
		'about',
	);

	if ( in_array( $view, $allowed_views, true ) ) {
		return $view;
	}

	if ( 'ams-cache-expert-mode' === $page ) {
		return 'expert';
	}

	if ( 'ams-cache-statistics' === $page ) {
		return 'statistics';
	}

	if ( 'ams-cache-about' === $page ) {
		return 'about';
	}

	if ( 'ams-cache-settings' === $page ) {
		switch ( $tab ) {
			case 'advanced':
				return 'advanced';

			case 'preferences':
				return 'preload';

			case 'optimization':
				return 'performance';

			case 'benchmark':
				return 'benchmark';

			case 'woocommerce':
				return 'woocommerce';

			case 'exclusion':
				return 'rules';

			default:
				return 'cache';
		}
	}

	return 'overview';
}

/**
 * Filters the action links displayed for each plugin in the Network Admin Plugins list table.
 *
 * @param array  $links Original links.
 * @param string $file  File position.
 *
 * @return array Combined links.
 */
function scm_plugin_action_links( $links, $file ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $links;
	}

	if ( SCM_PLUGIN_NAME === $file ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=ams-cache-settings' ) . '">' . __( 'Settings', 'ams-cache' ) . '</a>';
		return $links;
	}
}

/**
 * Add links to plugin meta information on plugin list page.
 *
 * @param array  $links Original links.
 * @param string $file  File position.
 *
 * @return array Combined links.
 */
function scm_plugin_extend_links( $links, $file ) {
	if ( ! current_user_can( 'install_plugins' ) ) {
		return $links;
	}

	if ( SCM_PLUGIN_NAME === $file ) {
		$links[] = '<a href="https://ams.com.kh/" target="_blank">' . __( 'Source code', 'ams-cache' ) . '</a>';
	}
	return $links;
}

/**
 * Get built Vite admin asset manifest entry.
 *
 * @return array
 */
function scm_get_admin_build_asset_entry() {
	static $entry = null;

	if ( null !== $entry ) {
		return $entry;
	}

	$entry = array();
	$manifest_path = SCM_PLUGIN_DIR . 'inc/assets/build/.vite/manifest.json';

	if ( ! file_exists( $manifest_path ) ) {
		return $entry;
	}

	$manifest = json_decode( file_get_contents( $manifest_path ), true );

	if ( ! is_array( $manifest ) ) {
		return $entry;
	}

	foreach ( array( 'assets/src/admin.jsx', 'assets/src/admin.js', 'admin.js' ) as $key ) {
		if ( ! empty( $manifest[ $key ] ) && is_array( $manifest[ $key ] ) ) {
			$entry = $manifest[ $key ];
			return $entry;
		}
	}

	return $entry;
}

/**
 * Build URL for a Vite output asset.
 *
 * @param string $file Asset path inside inc/assets/build.
 *
 * @return string
 */
function scm_get_admin_build_asset_url( $file ) {
	return SCM_PLUGIN_URL . 'inc/assets/build/' . ltrim( $file, '/' );
}

/**
 * Build cache-busting version for a Vite output asset.
 *
 * @param string $file Asset path inside inc/assets/build.
 *
 * @return string
 */
function scm_get_admin_build_asset_version( $file ) {
	$path = SCM_PLUGIN_DIR . 'inc/assets/build/' . ltrim( $file, '/' );

	return file_exists( $path ) ? (string) filemtime( $path ) : SCM_PLUGIN_VERSION;
}

/**
 * Load specfic CSS file for the AMS Cache setting page.
 */
function scm_admin_enqueue_styles( $hook_suffix ) {

	if ( false === strpos( $hook_suffix, 'ams-cache' ) ) {
		return;
	}

	$entry = scm_get_admin_build_asset_entry();
	$css_files = array();

	if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
		$css_files = $entry['css'];
	} elseif ( file_exists( SCM_PLUGIN_DIR . 'inc/assets/build/admin.css' ) ) {
		$css_files = array( 'admin.css' );
	}

	if ( ! empty( $css_files ) ) {
		foreach ( $css_files as $index => $css_file ) {
			wp_enqueue_style( 'ams-cache-admin-built-' . $index, scm_get_admin_build_asset_url( $css_file ), array(), scm_get_admin_build_asset_version( $css_file ), 'all' );
		}
	} else {
		wp_enqueue_style( 'custom-wp-admin-css', SCM_PLUGIN_URL . 'inc/assets/css/admin-style.css', array(), SCM_PLUGIN_VERSION, 'all' );
	}

	wp_enqueue_style( 'code-highlight', SCM_PLUGIN_URL . 'inc/assets/highlight/default.css', array(), SCM_PLUGIN_VERSION, 'all' );
	wp_enqueue_style( 'wp-jquery-ui-dialog' );
}

/**
 * Register JS files.
 */
function scm_admin_enqueue_scripts( $hook_suffix ) {

	if ( false === strpos( $hook_suffix, 'ams-cache' ) ) {
		return;
	}
	wp_enqueue_script( 'code-highlight', SCM_PLUGIN_URL . 'inc/assets/highlight/highlight.pack.js', array(), SCM_PLUGIN_VERSION, 'all' );
	wp_enqueue_script( 'jquery-ui-dialog' );

	if ( false !== strpos( $hook_suffix, 'ams-cache' ) ) {
		$entry = scm_get_admin_build_asset_entry();

		if ( empty( $entry['file'] ) ) {
			return;
		}

		wp_enqueue_script( 'ams-cache-dashboard', scm_get_admin_build_asset_url( $entry['file'] ), array(), scm_get_admin_build_asset_version( $entry['file'] ), true );

		wp_localize_script(
			'ams-cache-dashboard',
			'amsCacheDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'scm_dashboard_' . scm_get_dir_hash() ),
				'view'    => scm_get_admin_console_view(),
				'status'  => scm_dashboard_get_status_data(),
				'i18n'    => array(
					'loading' => __( 'Working...', 'ams-cache' ),
					'failed'  => __( 'Request failed.', 'ams-cache' ),
					'saving'  => __( 'Saving...', 'ams-cache' ),
					'saved'   => __( 'Settings saved.', 'ams-cache' ),
					'saveFail'=> __( 'Settings could not be saved.', 'ams-cache' ),
					'yes'     => __( 'Yes', 'ams-cache' ),
					'no'      => __( 'No', 'ams-cache' ),
					'views'   => array(
						'overview'    => __( 'Overview', 'ams-cache' ),
						'cache'       => __( 'Cache', 'ams-cache' ),
						'preload'     => __( 'Preload', 'ams-cache' ),
						'performance' => __( 'Performance', 'ams-cache' ),
						'advanced'    => __( 'Advanced', 'ams-cache' ),
						'rules'       => __( 'Rules', 'ams-cache' ),
						'statistics'  => __( 'Statistics', 'ams-cache' ),
						'expert'      => __( 'Expert Mode', 'ams-cache' ),
						'benchmark'   => __( 'Benchmark', 'ams-cache' ),
						'woocommerce' => __( 'WooCommerce', 'ams-cache' ),
						'about'       => __( 'About', 'ams-cache' ),
					),
					'features' => array(
						'minify_html'       => __( 'Minify HTML', 'ams-cache' ),
						'remove_comments'   => __( 'Remove comments', 'ams-cache' ),
						'minify_inline_css' => __( 'Minify inline CSS', 'ams-cache' ),
						'lazy_media'        => __( 'Lazy media', 'ams-cache' ),
						'critical_images'   => __( 'Critical images', 'ams-cache' ),
						'preconnect_fonts'  => __( 'Font preconnect', 'ams-cache' ),
						'defer_js'          => __( 'Defer JavaScript', 'ams-cache' ),
						'external_ucss'     => __( 'External UCSS', 'ams-cache' ),
						'local_ucss'        => __( 'Local UCSS', 'ams-cache' ),
						'js_analysis'       => __( 'JS analysis', 'ams-cache' ),
					),
					'statuses' => array(
						'applied'        => __( 'Applied', 'ams-cache' ),
						'no_change'      => __( 'No change', 'ams-cache' ),
						'disabled'       => __( 'Disabled', 'ams-cache' ),
						'pending_engine' => __( 'Pending engine', 'ams-cache' ),
						'failed'         => __( 'Failed', 'ams-cache' ),
						'unknown'        => __( 'Unknown', 'ams-cache' ),
					),
				),
			)
		);
	}
}

/**
 * Show header on setting pages.
 *
 * @return void
 */
function scm_show_settings_header() {
	echo '<div class="wrap scm-wrap">';

	if ( '' === get_option( 'permalink_structure' ) ) {
		$url_html = '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/options-permalink.php">' . __( 'Permalink Setting', 'seo-search-permalink' ) . '</a>';
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo __( 'AMS Cache supports only static URL structure.', 'ams-cache' ) . ' ';
		// translators: %s: permalink setting url.
		printf( __( 'You need to go to the %s page and change the permalink settings.', 'ams-cache' ), $url_html );
		echo '</p></div>';
	}
}

/**
 * Show footer on setting pages.
 *
 * @return void
 */
function scm_show_settings_footer() {
	echo '</div>';
}
