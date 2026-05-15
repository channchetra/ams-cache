<?php
/**
 * AMS Cache - Statistic status
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.4.0
 * @version 1.4.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_statistics_status = get_option( 'scm_option_statistics_status', 'disable' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_statistics_status" id="ams-cache-statistics-option-enable" value="enable" 
			<?php checked( $option_statistics_status, 'enable' ); ?>>
		<label for="ams-cache-statistics-option-enable">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_statistics_status" id="ams-cache-statistics-option-disable" value="disable" 
			<?php checked( $option_statistics_status, 'disable' ); ?>>
		<label for="ams-cache-statistics-option-disable">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Record the caching information.', 'ams-cache' ); ?></em></p>
