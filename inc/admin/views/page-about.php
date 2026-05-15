<?php
/**
 * AMS Cache - Stats
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

<div class="about-us-container">
	<div class="shieldon-cover"><img src="<?php echo SCM_PLUGIN_URL; ?>inc/assets/images/Screenshot.png"></div>
	<div class="shieldon-author">
		<p class="created-by">
			<?php
			printf(
				// translators: %1$s: Terry L. %2$s: Taiwan
				__( 'AMS Cache is brought to you by <a href="%1$s">Chetra CHANN</a> from <a href="%2$s">AMS Technical Team</a>.', 'ams-cache' ),
				'https://github.com/channchetra',
				'https://ams.com.kh/'
			);
			?>
		</p>
		<p><?php _e( 'This plugin is based on Amazing Project Simple Cache by Terry Lin. If you have any issues or have found any bugs, please report them at the following URL.', 'ams-cache' ); ?></p>
		<div class="report-area">
			<span><a href="https://github.com/terrylinooo/simple-cache" target="_blank"><?php _e( 'Core', 'ams-cache' ); ?></a></span>
			<span><a href="https://ams.com.kh/" target="_blank"><?php _e( 'Plugin', 'ams-cache' ); ?></a></span>
		</div>
	</div>
</div>
