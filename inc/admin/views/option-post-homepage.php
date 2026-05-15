<?php
/**
 * AMS Cache - Uninstall option.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.3.0
 * @version 1.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_post_homepage = get_option( 'scm_option_post_homepage', 'yes' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_post_homepage" id="ams-cache-post-homepage-option-yes" value="yes" 
			<?php checked( $option_post_homepage, 'yes' ); ?>>
		<label for="ams-cache-post-homepage-option-yes">
			<?php _e( 'Yes', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_post_homepage" id="ams-cache-post-homepage-option-no" value="no" 
			<?php checked( $option_post_homepage, 'no' ); ?>>
		<label for="ams-cache-post-homepage-option-no">
			<?php _e( 'No', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Would you like to cache the homepage of your site?', 'ams-cache' ); ?></em></p>
