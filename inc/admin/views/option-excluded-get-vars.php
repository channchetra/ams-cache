<?php
/**
 * AMS Cache - Exluded List
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_excluded_get_vars = get_option( 'scm_option_excluded_get_vars', '' );

?>

<div>
	<div class="scm-option-item">
		<textarea
			name="scm_option_excluded_get_vars"
			class="ams-rules-textarea"
			rows="4"
			placeholder="<?php esc_attr_e( 'One variable key per line', 'ams-cache' ); ?>"
		><?php echo esc_textarea( $option_excluded_get_vars ); ?></textarea>
	</div>	
</div>
<p><em><?php _e( 'Any request containing those variables, even if its value is empty, will be ignored by Caster Master.', 'ams-cache' ); ?></em></p>
<p><em><?php _e( 'A variable key per line.', 'ams-cache' ); ?></em></p>
