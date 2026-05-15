<?php
/**
 * AMS Cache - Caching status.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_caching_status = get_option( 'scm_option_caching_status', 'disable' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_caching_status" id="ams-cache-caching-status-enable" value="enable" 
			<?php checked( $option_caching_status, 'enable' ); ?>>
		<label for="ams-cache-caching-status-enable">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_caching_status" id="ams-cache-caching-status-disable" value="disable" 
			<?php checked( $option_caching_status, 'disable' ); ?>>
		<label for="ams-cache-caching-status-disable">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Once you disable this option, AMS Cache will stop working and all cache will be cleared.', 'ams-cache' ); ?></em></p>
