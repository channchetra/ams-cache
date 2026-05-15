<?php
/**
 * AMS Cache - Exclusion status.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_exclusion_status = get_option( 'scm_option_exclusion_status', 'no' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_exclusion_status" id="ams-cache-exclusion-status-option-yes" value="yes" 
			<?php checked( $option_exclusion_status, 'yes' ); ?>>
		<label for="ams-cache-exclusion-status-option-yes">
			<?php _e( 'Yes', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_exclusion_status" id="ams-cache-exclusion-status-option-no" value="no" 
			<?php checked( $option_exclusion_status, 'no' ); ?>>
		<label for="ams-cache-exclusion-status-option-no">
			<?php _e( 'No', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'The following settings work only when this option is enabled.', 'ams-cache' ); ?></em></p>
