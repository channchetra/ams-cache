<?php
/**
 * AMS Cache - html_debug_comment.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_html_debug_comment = get_option( 'scm_option_html_debug_comment', 'yes' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_html_debug_comment" id="ams-cache-html-debug-comment-option-enable" value="yes" 
			<?php checked( $option_html_debug_comment, 'yes' ); ?>>
		<label for="ams-cache-html-debug-comment-option-enable">
			<?php _e( 'Yes', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_html_debug_comment" id="ams-cache-html-debug-comment-option-disable" value="no" 
			<?php checked( $option_html_debug_comment, 'no' ); ?>>
		<label for="ams-cache-html-debug-comment-option-disable">
			<?php _e( 'No', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Insert an HTML debug comment in the source code. This is for debug purposes only, to let us know that the page is being cached.', 'ams-cache' ); ?></em></p>
