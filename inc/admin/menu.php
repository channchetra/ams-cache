<?php
/**
 * KH Speed Engine - Admin menu and assets.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

add_filter( 'admin_body_class', 'khse_admin_body_class' );

/**
 * Add a full-width app class on KH Speed Engine admin pages.
 *
 * @param string $classes Body classes.
 *
 * @return string
 */
function khse_admin_body_class( $classes ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( 0 === strpos( $page, 'kh-speed-engine' ) ) {
		$classes .= ' khse-admin-app';
	}

	return $classes;
}

add_action( 'admin_menu', 'khse_option' );
add_action( 'admin_enqueue_scripts', 'khse_admin_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'khse_admin_enqueue_styles' );
add_filter( 'plugin_action_links_' . KHSE_PLUGIN_NAME, 'khse_plugin_action_links', 10, 5 );
add_filter( 'plugin_row_meta', 'khse_plugin_extend_links', 10, 2 );

/**
 * Register the plugin setting page.
 *
 * @return void
 */
function khse_option() {

	add_menu_page(
		__( 'KH Speed Engine', 'kh-speed-engine' ),
		__( 'KH Speed', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-dashboard',
		'khse_dashboard_page',
		'dashicons-superhero'
	);

	add_submenu_page(
		'kh-speed-engine-dashboard',
		__( 'Dashboard', 'kh-speed-engine' ),
		__( 'Dashboard', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-dashboard',
		'khse_dashboard_page'
	);

	add_submenu_page(
		'kh-speed-engine-dashboard',
		__( 'Settings', 'kh-speed-engine' ),
		__( 'Settings', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-settings',
		'khse_dashboard_page'
	);

	add_submenu_page(
		'kh-speed-engine-dashboard',
		__( 'Expert Mode', 'kh-speed-engine' ),
		__( 'Expert Mode', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-expert-mode',
		'khse_dashboard_page'
	);

	add_submenu_page(
		'kh-speed-engine-dashboard',
		__( 'Statistics', 'kh-speed-engine' ),
		__( 'Statistics', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-statistics',
		'khse_dashboard_page'
	);

	add_submenu_page(
		'kh-speed-engine-dashboard',
		__( 'About', 'kh-speed-engine' ),
		__( 'About', 'kh-speed-engine' ),
		'manage_options',
		'kh-speed-engine-about',
		'khse_dashboard_page'
	);
}

/**
 * Output the dashboard page.
 *
 * @return void
 */
function khse_dashboard_page() {
	khse_show_settings_header();
	echo khse_load_view( 'page_dashboard' );
	khse_show_settings_footer();
}

/**
 * Resolve the initial React console view from the current admin URL.
 *
 * @return string
 */
function khse_get_admin_console_view() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kh-speed-engine-dashboard';
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	$allowed_views = array(
		'overview',
		'cache',
		'preload',
		'performance',
		'rules',
		'statistics',
		'expert',
		'benchmark',
		'about',
	);

	if ( in_array( $view, $allowed_views, true ) ) {
		return $view;
	}

	if ( 'kh-speed-engine-expert-mode' === $page ) {
		return 'expert';
	}

	if ( 'kh-speed-engine-statistics' === $page ) {
		return 'statistics';
	}

	if ( 'kh-speed-engine-about' === $page ) {
		return 'about';
	}

	if ( 'kh-speed-engine-settings' === $page ) {
		switch ( $tab ) {
			case 'advanced':
				return 'cache';

			case 'preferences':
				return 'preload';

			case 'optimization':
				return 'performance';

			case 'benchmark':
				return 'benchmark';

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
function khse_plugin_action_links( $links, $file ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $links;
	}

	if ( KHSE_PLUGIN_NAME === $file ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=kh-speed-engine-settings' ) . '">' . __( 'Settings', 'kh-speed-engine' ) . '</a>';
	}

	return $links;
}

/**
 * Add links to plugin meta information on plugin list page.
 *
 * @param array  $links Original links.
 * @param string $file  File position.
 *
 * @return array Combined links.
 */
function khse_plugin_extend_links( $links, $file ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $links;
	}

	if ( KHSE_PLUGIN_NAME === $file ) {
		$links[] = '<a href="https://khspeed.com/" target="_blank">' . __( 'Source code', 'kh-speed-engine' ) . '</a>';
	}
	return $links;
}

/**
 * Get built Vite admin asset manifest entry.
 *
 * @return array
 */
function khse_get_admin_build_asset_entry() {
	static $entry = null;

	if ( null !== $entry ) {
		return $entry;
	}

	$entry = array();
	$manifest_path = KHSE_PLUGIN_DIR . 'inc/assets/build/.vite/manifest.json';

	if ( ! file_exists( $manifest_path ) ) {
		return $entry;
	}

	$manifest = json_decode( (string) @file_get_contents( $manifest_path ), true );

	if ( ! is_array( $manifest ) ) {
		error_log( '[KH Speed Engine] Ignoring unreadable admin asset manifest: ' . $manifest_path );
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
function khse_get_admin_build_asset_url( $file ) {
	return KHSE_PLUGIN_URL . 'inc/assets/build/' . ltrim( $file, '/' );
}

/**
 * Build cache-busting version for a Vite output asset.
 *
 * @param string $file Asset path inside inc/assets/build.
 *
 * @return string
 */
function khse_get_admin_build_asset_version( $file ) {
	$path = KHSE_PLUGIN_DIR . 'inc/assets/build/' . ltrim( $file, '/' );

	return file_exists( $path ) ? (string) filemtime( $path ) : KHSE_PLUGIN_VERSION;
}

/**
 * Load specfic CSS file for the KH Speed Engine setting page.
 */
function khse_admin_enqueue_styles( $hook_suffix ) {

	if ( false === strpos( $hook_suffix, 'kh-speed-engine' ) ) {
		return;
	}

	$entry = khse_get_admin_build_asset_entry();
	$css_files = array();

	if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
		$css_files = $entry['css'];
	} elseif ( file_exists( KHSE_PLUGIN_DIR . 'inc/assets/build/admin.css' ) ) {
		$css_files = array( 'admin.css' );
	}

	if ( ! empty( $css_files ) ) {
		foreach ( $css_files as $index => $css_file ) {
			wp_enqueue_style( 'kh-speed-engine-admin-built-' . $index, khse_get_admin_build_asset_url( $css_file ), array(), khse_get_admin_build_asset_version( $css_file ), 'all' );
		}
	}
}

/**
 * Register JS files.
 */
function khse_admin_enqueue_scripts( $hook_suffix ) {

	if ( false === strpos( $hook_suffix, 'kh-speed-engine' ) ) {
		return;
	}

	$entry = khse_get_admin_build_asset_entry();

	if ( empty( $entry['file'] ) ) {
		return;
	}

	wp_enqueue_script( 'kh-speed-engine-dashboard', khse_get_admin_build_asset_url( $entry['file'] ), array(), khse_get_admin_build_asset_version( $entry['file'] ), true );

	wp_localize_script(
		'kh-speed-engine-dashboard',
		'khseDashboard',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'khse_dashboard_' . khse_get_dir_hash() ),
			'view'    => khse_get_admin_console_view(),
			'status'  => khse_dashboard_get_status_data(),
			'version' => KHSE_PLUGIN_VERSION,
			'i18n'    => array(
				'failed' => __( 'Request failed.', 'kh-speed-engine' ),
				'saved'  => __( 'Settings saved.', 'kh-speed-engine' ),
			),
		)
	);
}

/**
 * Show header on setting pages.
 *
 * @return void
 */
function khse_show_settings_header() {
	echo '<div class="wrap khse-wrap">';

	if ( '' === get_option( 'permalink_structure' ) ) {
		$url_html = '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/options-permalink.php">' . __( 'Permalink Setting', 'kh-speed-engine' ) . '</a>';
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo __( 'KH Speed Engine supports only static URL structure.', 'kh-speed-engine' ) . ' ';
		// translators: %s: permalink setting url.
		printf( __( 'You need to go to the %s page and change the permalink settings.', 'kh-speed-engine' ), $url_html );
		echo '</p></div>';
	}
}

/**
 * Show footer on setting pages.
 *
 * @return void
 */
function khse_show_settings_footer() {
	echo '</div>';
}
