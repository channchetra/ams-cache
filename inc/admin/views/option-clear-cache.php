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

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_clear_cache" id="ams-cache-clear-cache-all-option-enable" value="all" >
		<label for="ams-cache-clear-cache-all-option-enable">
			<?php _e( 'All', 'ams-cache' ); ?><br />
		<label>
	</div>
	<?php if ( 'enable' === get_option( 'scm_option_statistics_status' ) ) : ?>
		<?php foreach ( scm_get_cache_type_list() as $k => $v ) : ?>
		<div class="scm-option-item scm-cache-type-list" data-type="<?php echo $k; ?>">
			<input type="radio" name="scm_option_clear_cache" id="ams-cache-clear-cache-<?php echo $k; ?>-option-disable" value="<?php echo $k; ?>">
			<label for="ams-cache-clear-cache-<?php echo $k; ?>-option-enable">
			<?php echo $v; ?><br />
		<label>
		</div>	
		<?php endforeach; ?>
		<p><em><?php _e( 'Clear cache data of a specific cache type, or just clear all of them.', 'ams-cache' ); ?></em></p>
	<?php endif; ?>
</div>
