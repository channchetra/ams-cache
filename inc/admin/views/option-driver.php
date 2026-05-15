<?php
/**
 * AMS Cache - Driver.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_driver_type = get_option( 'scm_option_driver', 'file' );

$option_list = array(
	'file'      => __( 'File', 'ams-cache' ),
	'redis'     => __( 'Redis', 'ams-cache' ),
	'memcache'  => __( 'Memcache', 'ams-cache' ),
	'memcached' => __( 'Memcached', 'ams-cache' ),
	'apc'       => __( 'APC', 'ams-cache' ),
	'apcu'      => __( 'APCu', 'ams-cache' ),
	'wincache'  => __( 'WinCache', 'ams-cache' ),
	'mongo'     => __( 'MongoDB', 'ams-cache' ),
	'mysql'     => __( 'MySQL', 'ams-cache' ),
	'sqlite'    => __( 'SQLite', 'ams-cache' ),
);

$driver_status = array();

foreach ( array_keys( $option_list ) as $v ) {
	$driver_status[ $v ] = scm_test_driver( $v );
}

?>

<div>
	<div>
		<select name="scm_option_driver" class="regular">
			<?php foreach ( $option_list as $k => $v ) : ?>
				<?php if ( $driver_status[ $k ] ) : ?>
					<option value="<?php echo $k; ?>" <?php selected( $option_driver_type, $k ); ?>><?php echo $v; ?></option>
				<?php else : ?>
					<option value="<?php echo $k; ?>" disabled><?php echo $v; ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
	</div>
	<p><em><?php _e( 'Choose a driver to cache your posts and pages.', 'ams-cache' ); ?></em></p>
</div>
<div>
	<div class="driver-status-container">
	<?php foreach ( $option_list as $k => $v ) : ?>

		<div class="driver-status-box">
			<div class="ams-driver-status-row">
				<span><?php echo $v; ?></span>
				<span>
					<?php if ( $driver_status[ $k ] ) : ?>
						<?php if ( $option_driver_type === $k ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #23b900"></span>
						<?php else : ?>
							<span class="dashicons dashicons-marker" style="color: #23b900"></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="dashicons dashicons-marker" style="color: #c60900"></span>
					<?php endif; ?>
				</span>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
</div>
