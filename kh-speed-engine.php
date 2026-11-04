<?php
/**
 * Plugin Name: KH Speed Engine
 * Plugin URI:  https://khspeed.com/
 * Description: A WordPress cache plugin.
 * Version:     4.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.1
 * Author:      Chetra Chann
 * Author URI:  https://khspeed.com/
 * License:     GPL 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: kh-speed-engine
 * Domain Path: /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * KH Speed Engine uses the khse_ function prefix and KHSE_ constant prefix.
 */

if ( ! defined( 'KHSE_INC' ) ) {
	define( 'KHSE_INC', true );
}

/**
 * KH Speed Engine constants.
 *
 * KHSE_PLUGIN_NAME          : Plugin's name.
 * KHSE_PLUGIN_DIR           : The absolute path of the KH Speed Engine plugin directory.
 * KHSE_PLUGIN_URL           : The URL of the KH Speed Engine plugin directory.
 * KHSE_PLUGIN_PATH          : The absolute path of the KH Speed Engine plugin launcher.
 * KHSE_PLUGIN_LANGUAGE_PACK : Translation Language pack.
 * KHSE_PLUGIN_VERSION       : KH Speed Engine version number
 * KHSE_PLUGIN_TEXT_DOMAIN   : KH Speed Engine text domain
 *
 * Expected values:
 *
 * KHSE_PLUGIN_DIR           : {absolute_path}/wp-content/plugins/kh-speed-engine/
 * KHSE_PLUGIN_URL           : {protocal}://{domain_name}/wp-content/plugins/kh-speed-engine/
 * KHSE_PLUGIN_PATH          : {absolute_path}/wp-content/plugins/kh-speed-engine/kh-speed-engine.php
 * KHSE_PLUGIN_LANGUAGE_PACK : kh-speed-engine/languages
 */

define( 'KHSE_PLUGIN_NAME', plugin_basename( __FILE__ ) );
define( 'KHSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KHSE_PLUGIN_PATH', __FILE__ );
define( 'KHSE_PLUGIN_LANGUAGE_PACK', dirname( plugin_basename( __FILE__ ) ) . '/languages' );
define( 'KHSE_PLUGIN_VERSION', '4.0.0' );
define( 'KHSE_CORE_VERSION', '1.3.4' );
define( 'KHSE_PLUGIN_TEXT_DOMAIN', 'kh-speed-engine' );

/**
 * Start KH Speed Engine cores.
 */

// The minimum supported version of PHP.
if ( version_compare( phpversion(), '7.1.0', '>=' ) ) {

	// This section is for unit testing.
	if ( defined( 'KHSE_PLUGIN_UNIT_TEST' ) ) {
		$required_files = array(
			'register',
			'menu',
			'update-setting',
			'functions',
			'ajax-action',
			'update-woocommerce',
			'widgets',
		);

		require_once KHSE_PLUGIN_DIR . 'inc/helpers.php';
		khse_register_wordpress_hooks();
		// Composer's autoloader prepends itself to the SPL stack, so it must be
		// loaded first and the local library loader registered afterwards to win.
		require_once KHSE_PLUGIN_DIR . 'vendor/autoload.php';
		require_once KHSE_PLUGIN_DIR . 'inc/lib/autoload.php';
		require_once KHSE_PLUGIN_DIR . 'inc/class-khse-cache.php';
		require_once KHSE_PLUGIN_DIR . 'inc/update-post.php';
		require_once KHSE_PLUGIN_DIR . 'inc/admin-bar.php';

		foreach ( $required_files as $file ) {
			require_once KHSE_PLUGIN_DIR . 'inc/admin/' . $file . '.php';
		}

		register_activation_hook( __FILE__, 'khse_activation' );
		register_deactivation_hook( __FILE__, 'khse_deactivation' );

		khse_load_textdomain();

		$cm = new KHSE_Cache();
		$cm->init();

	} else {

		require_once KHSE_PLUGIN_DIR . 'inc/helpers.php';
		khse_register_wordpress_hooks();
		require_once KHSE_PLUGIN_DIR . 'inc/lib/autoload.php';
		// Post mutations can run through AJAX, REST, or frontend requests.
		require_once KHSE_PLUGIN_DIR . 'inc/update-post.php';
		require_once KHSE_PLUGIN_DIR . 'inc/admin-bar.php';
		// Benchmark widget registration must run on front-end and admin requests.
		require_once KHSE_PLUGIN_DIR . 'inc/admin/widgets.php';

		// Full runtime is not needed during AJAX calls, but upload/offload hooks are.
		if ( ! wp_doing_ajax() ) {

			register_activation_hook( __FILE__, 'khse_activation' );
			register_deactivation_hook( __FILE__, 'khse_deactivation' );

			khse_load_textdomain();

			if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

				$required_files = array(
					'register',               // Event: activate and uninstall plugin.
					'menu',                   // Display menu link and render setting page.
					'update-setting',         // Event: update settings.
					'functions',              // Helper functions used in admin scope.
				);

				if ( 'yes' === get_option( 'khse_option_woocommerce_status' ) ) {
					$required_files[] = 'update-woocommerce';
				}

				foreach ( $required_files as $file ) {
					require_once KHSE_PLUGIN_DIR . 'inc/admin/' . $file . '.php';
				}
			} else {

				require_once KHSE_PLUGIN_DIR . 'inc/class-khse-cache.php';

				$cm = new KHSE_Cache();
				$cm->init();
			}
		} else {
			if ( is_admin() ) {
				require_once KHSE_PLUGIN_DIR . 'inc/admin/ajax-action.php';
				// AJAX saves use the same option side-effect hooks as admin saves.
				require_once KHSE_PLUGIN_DIR . 'inc/admin/update-setting.php';
			}
		}
	}
}

/**
 * KH Speed Engine is open sourced at:
 * https://khspeed.com/
 *
 * If you have found any bug or have any suggestion, please let me know.
 */
