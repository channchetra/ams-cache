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

$option_excluded_list = get_option( 'scm_option_excluded_list_filtered', '' );

?>

<div>
	<div class="scm-option-item">
		<textarea
			name="scm_option_excluded_list"
			class="ams-rules-textarea"
			rows="15"
			placeholder="<?php esc_attr_e( '/custom-type/ — one URL per line', 'ams-cache' ); ?>"
		><?php echo esc_textarea( $option_excluded_list ); ?></textarea>
	</div>	
</div>
<p><em><?php _e( 'Please enter the <strong>begin with</strong> URLs you want them excluded from AMS Cache.', 'ams-cache' ); ?></em></p>
<p><em><?php _e( 'A URL per line.', 'ams-cache' ); ?></em></p>
<p><em><?php _e( 'For example, use <code>/custom-type/</code> instead of <code>www.example.com/custom-type/1/</code> for a web page.', 'ams-cache' ); ?></em></p>
