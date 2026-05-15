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
	<input type="hidden" name="scm_option_visibility_login_user" value="no">
	<span class="ams-readonly-choice"><?php _e( 'Guest-only cache serving', 'ams-cache' ); ?></span>
</div>
<br />
<p><strong><?php _e( 'Note', 'ams-cache' ); ?></strong></p>
<p><em><?php _e( 'Logged-in users never receive cached HTML. Normal Mode checks WordPress login state; Expert Mode also bypasses cache when WordPress auth cookies are present.', 'ams-cache' ); ?></em></p>
