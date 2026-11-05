<?php
/**
 * KH Speed Engine — admin/expert-mode-snippet.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( "KHSE_INC" ) ) {
	die;
}

/**
 * Get the Expert Mode code snippet.
 *
 * @return string
 */
function khse_expert_mode_code_template() {
	$plugin_dir        = wp_normalize_path( rtrim( KHSE_PLUGIN_DIR, '/\\' ) );
	$plugin_upload_dir = wp_normalize_path( rtrim( khse_get_upload_dir(), '/\\' ) );
	$runtime_dir       = wp_normalize_path( rtrim( khse_get_private_runtime_dir(), '/\\' ) );
	$expert_mode_file  = $plugin_dir . '/inc/expert-mode.php';
	$config_file       = $runtime_dir . '/config.json';

	ob_start();

	?>
// BEGIN - KH Speed Engine
// Settings are read from the private runtime file <?php echo $config_file; ?>.
// Update Redis DB, key prefix, driver, and preload options in KH Speed Engine admin.
//
// Raise memory early — Expert Mode runs before WP_MEMORY_LIMIT is applied
// and large cache pages can exhaust FPM's default 128M.
if ( function_exists( 'ini_set' ) ) {
	@ini_set( 'memory_limit', '512M' );
}

if ( file_exists( <?php echo var_export( $expert_mode_file, true ); ?> ) ) {

	include_once( <?php echo var_export( $expert_mode_file, true ); ?> );

	/* BEGIN - Blog ID: <?php echo get_current_blog_id(); ?> */

	khse_run_expert_mode( array(
		'plugin_dir'        => <?php echo var_export( $plugin_dir, true ); ?>,
		'plugin_upload_dir' => <?php echo var_export( $plugin_upload_dir, true ); ?>,
		'runtime_dir'       => <?php echo var_export( $runtime_dir, true ); ?>,
	) );

	/* END - Blog ID: <?php echo get_current_blog_id(); ?> */
}

// END - KH Speed Engine
	<?php
	return ob_get_clean();
}

/**
 * Get the single-line Expert Mode value for Docker or custom wp-config fields.
 *
 * @return string
 */
function khse_expert_mode_custom_value() {
	$plugin_dir        = wp_normalize_path( rtrim( KHSE_PLUGIN_DIR, '/\\' ) );
	$plugin_upload_dir = wp_normalize_path( rtrim( khse_get_upload_dir(), '/\\' ) );
	$runtime_dir       = wp_normalize_path( rtrim( khse_get_private_runtime_dir(), '/\\' ) );
	$expert_mode_file  = $plugin_dir . '/inc/expert-mode.php';

	return 'if ( file_exists( ' . var_export( $expert_mode_file, true ) . ' ) ) { require_once ' . var_export( $expert_mode_file, true ) . '; khse_run_expert_mode( array( '
		. "'plugin_dir' => " . var_export( $plugin_dir, true ) . ', '
		. "'plugin_upload_dir' => " . var_export( $plugin_upload_dir, true ) . ', '
		. "'runtime_dir' => " . var_export( $runtime_dir, true )
		. ' ) ); }';
}

/**
 * Read wp-config.php contents, including the parent-directory fallback.
 *
 * @return string
 */
function khse_get_wp_config_contents() {
	$wp_config_file = ABSPATH . 'wp-config.php';

	if ( ! file_exists( $wp_config_file ) && file_exists( ABSPATH . '../wp-config.php' ) ) {
		$wp_config_file = ABSPATH . '../wp-config.php';
	}

	return file_exists( $wp_config_file ) ? (string) @file_get_contents( $wp_config_file ) : '';
}

/**
 * Check whether a config source contains the Expert Mode loader and paths.
 *
 * @param string $source      Config source.
 * @param string $upload_dir  Plugin upload dir.
 * @param string $runtime_dir Private runtime dir.
 *
 * @return bool
 */
function khse_expert_mode_snippet_matches( $source, $upload_dir, $runtime_dir ) {
	$source = (string) $source;

	return false !== strpos( $source, 'expert-mode.php' )
		&& false !== strpos( $source, wp_normalize_path( $upload_dir ) )
		&& false !== strpos( $source, wp_normalize_path( $runtime_dir ) );
}

/**
 * Check if PHP code for Expert Mode is ready or not.
 *
 * @return bool
 */
function khse_is_expert_mode_code_ready() {
	$upload_dir  = wp_normalize_path( khse_get_upload_dir() );
	$runtime_dir = wp_normalize_path( khse_get_private_runtime_dir() );

	if ( khse_expert_mode_snippet_matches( khse_get_wp_config_contents(), $upload_dir, $runtime_dir ) ) {
		return true;
	}

	foreach ( array( 'WORDPRESS_CONFIG_EXTRA', 'WP_CONFIG_EXTRA' ) as $env_key ) {
		$value = getenv( $env_key );

		if ( false === $value && isset( $_ENV[ $env_key ] ) ) {
			$value = $_ENV[ $env_key ];
		}

		if ( false === $value && isset( $_SERVER[ $env_key ] ) ) {
			$value = $_SERVER[ $env_key ];
		}

		if ( is_string( $value ) && khse_expert_mode_snippet_matches( $value, $upload_dir, $runtime_dir ) ) {
			return true;
		}
	}

	return false;
}
