<?php
/**
 * AMS Cache - Max cache entries.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.2.0
 * @version 2.2.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_cache_max_entries = scm_get_cache_max_entries();

?>

<div>
	<div>
		<input type="number" min="0" step="1" name="scm_option_cache_max_entries" class="small-text" value="<?php echo esc_attr( $option_cache_max_entries ); ?>">
	</div>
</div>
<p><em><?php _e( 'Maximum cached pages to keep. Set 0 for unlimited. When the limit is reached, the oldest known cache entries are removed first.', 'ams-cache' ); ?></em></p>
