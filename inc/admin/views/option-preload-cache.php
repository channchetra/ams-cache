<?php
/**
 * AMS Cache - Preload cache.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.2.0
 * @version 2.2.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_preload_cache = get_option( 'scm_option_preload_cache', 'no' );
$option_preload_limit = (int) get_option( 'scm_option_preload_limit', 50 );
$option_preload_homepage_links = get_option( 'scm_option_preload_homepage_links', 'yes' );

if ( $option_preload_limit < 1 ) {
	$option_preload_limit = 1;
}

if ( $option_preload_limit > 1000 ) {
	$option_preload_limit = 1000;
}

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_preload_cache" id="ams-cache-preload-cache-option-enable" value="yes"
			<?php checked( $option_preload_cache, 'yes' ); ?>>
		<label for="ams-cache-preload-cache-option-enable">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_preload_cache" id="ams-cache-preload-cache-option-disable" value="no"
			<?php checked( $option_preload_cache, 'no' ); ?>>
		<label for="ams-cache-preload-cache-option-disable">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>
</div>
<div style="margin-top: 20px">
	<div>
		<input type="number" min="1" max="1000" name="scm_option_preload_limit" value="<?php echo esc_attr( $option_preload_limit ); ?>">
	</div>
</div>
<div style="margin-top: 20px">
	<div class="scm-option-item">
		<input type="radio" name="scm_option_preload_homepage_links" id="ams-cache-preload-homepage-links-option-yes" value="yes"
			<?php checked( $option_preload_homepage_links, 'yes' ); ?>>
		<label for="ams-cache-preload-homepage-links-option-yes">
			<?php _e( 'Crawl homepage links first', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_preload_homepage_links" id="ams-cache-preload-homepage-links-option-no" value="no"
			<?php checked( $option_preload_homepage_links, 'no' ); ?>>
		<label for="ams-cache-preload-homepage-links-option-no">
			<?php _e( 'Use configured lists only', 'ams-cache' ); ?>
		<label>
	</div>
</div>
<p><em><?php _e( 'When enabled, AMS Cache warms the homepage first, crawls same-site homepage links in document order, then falls back to selected post type, archive, and WooCommerce URLs.', 'ams-cache' ); ?></em></p>
