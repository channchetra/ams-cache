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
 * Resolve the initial Vue console view from the current admin URL.
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
 * Load specfic CSS file for the AMS Cache setting page.
 */
function scm_admin_enqueue_styles( $hook_suffix ) {

	if ( false === strpos( $hook_suffix, 'ams-cache' ) ) {
		return;
	}
	wp_enqueue_style( 'custom-wp-admin-css', SCM_PLUGIN_URL . 'inc/assets/css/admin-style.css', array(), SCM_PLUGIN_VERSION, 'all' );
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
		wp_enqueue_script( 'vue3', apply_filters( 'scm_vue_cdn_url', 'https://unpkg.com/vue@3/dist/vue.global.prod.js' ), array(), '3', true );
		wp_enqueue_script( 'ams-cache-dashboard', SCM_PLUGIN_URL . 'inc/assets/js/admin-dashboard.js', array( 'vue3' ), SCM_PLUGIN_VERSION, true );
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
					'vueFail' => __( 'AMS Cache dashboard could not load Vue 3. Check CDN access or override the scm_vue_cdn_url filter.', 'ams-cache' ),
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
	$git_url_core   = 'https://github.com/terrylinooo/simple-cache';
	$git_url_plugin = 'https://ams.com.kh/';

	echo '<div class="ams-cache-info-bar">';
	echo '	<div class="logo-info"><img src="' . SCM_PLUGIN_URL . 'inc/assets/images/logo.png" class="ams-cache-logo"><div><h1>AMS Cache</h1><span>' . esc_html__( 'WordPress cache console', 'ams-cache' ) . '</span></div></div>';
	echo '	<div class="version-info">';
	echo '    Core: <a href="' . $git_url_core . '" target="_blank">' . SCM_CORE_VERSION . '</a>  ';
	echo '    Plugin: <a href="' . $git_url_plugin . '" target="_blank">' . SCM_PLUGIN_VERSION . '</a>  ';
	echo '  </div>';
	echo '</div>';
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
