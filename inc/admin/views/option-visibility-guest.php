<?php
/**
 * AMS Cache - The visibility of cache for logged-in users.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.2.1
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

?>

<div>
	<input type="hidden" name="scm_option_visibility_guest" value="yes">
	<span class="ams-readonly-choice"><?php _e( 'Always cached for guests', 'ams-cache' ); ?></span>
</div>
<p><em><?php _e( 'Always show cached pages to guests.', 'ams-cache' ); ?></em></p>
