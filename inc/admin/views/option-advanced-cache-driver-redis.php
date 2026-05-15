<?php
/**
 * AMS Cache - Advanced settings - Driver: Redis.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_redis = (array) get_option( 'scm_option_advanced_driver_redis', array() );

$option_redis_connection_type = get_option( 'scm_option_advanced_driver_redis_connection_type', 'tcp' );

$option_list = array(
	'host'        => __( 'Host', 'ams-cache' ),
	'port'        => __( 'Port', 'ams-cache' ),
	'user'        => __( 'User', 'ams-cache' ),
	'pass'        => __( 'Password', 'ams-cache' ),
	'database'    => __( 'Database', 'ams-cache' ),
	'or'          => 'or',
	'unix_socket' => __( 'Unix Socket', 'ams-cache' ),
	'or2'         => 'or',
	'compress'    => __( 'Compression', 'ams-cache' ),
	'compress_threshold' => __( 'Compression Threshold', 'ams-cache' ),
	'compress_level' => __( 'Compression Level', 'ams-cache' ),
);

$option_default_list = array(
	'host'        => '127.0.0.1',
	'port'        => 6379,
	'user'        => '',
	'pass'        => '',
	'database'    => 0,
	'or'          => 'or',
	'unix_socket' => '',
	'or2'         => 'or',
	'compress'    => 'yes',
	'compress_threshold' => 1024,
	'compress_level' => 6,
);

$is_driver_setting_correct = false;

if ( scm_test_driver( 'redis' ) ) {
	$is_driver_setting_correct = true;
}

?>
<?php if ( extension_loaded( 'redis' ) ) : ?>

	<div>

	<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label>
					<?php _e( 'Connection', 'ams-cache' ); ?>
				<label>
			</div>
			<span>
				<input type="radio" name="scm_option_advanced_driver_redis_connection_type" id="ams-cache-advanced-driver-redis-connection-tcp" value="tcp" 
					<?php checked( $option_redis_connection_type, 'tcp' ); ?>>
				<label for="ams-cache-advanced-driver-redis-connection-tcp">
					<?php _e( 'TCP', 'ams-cache' ); ?>
				<label>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="radio" name="scm_option_advanced_driver_redis_connection_type" id="ams-cache-advanced-driver-redis-connection-socket" value="socket" 
					<?php checked( $option_redis_connection_type, 'socket' ); ?>>
				<label for="ams-cache-advanced-driver-redis-connection-socket">
					<?php _e( 'Unix Socket', 'ams-cache' ); ?>
				<label>
			</span>
		</div><br /><br />

		<?php foreach ( $option_list as $k => $v ) : ?>
			<?php if ( 'or' === $v ) : ?>
				<hr />
				<?php continue; ?>
			<?php endif; ?>
		<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label for="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>">
					<?php echo esc_html( $v ); ?>
				<label>
			</div>

			<?php if ( isset( $option_redis[ $k ] ) && '' !== $option_redis[ $k ] ) : ?>
				<?php $redis_field_value = $option_redis[ $k ]; ?>
			<?php else : ?>
				<?php $redis_field_value = $option_default_list[ $k ]; ?>
			<?php endif; ?>

			<?php if ( 'database' === $k ) : ?>
				<select
					name="scm_option_advanced_driver_redis[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>"
				>
					<?php for ( $i = 0; $i <= 15; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $redis_field_value, $i ); ?>>
							<?php echo esc_html( 'DB' . $i ); ?>
						</option>
					<?php endfor; ?>
				</select>
			<?php elseif ( 'compress' === $k ) : ?>
				<select
					name="scm_option_advanced_driver_redis[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>"
				>
					<option value="yes" <?php selected( $redis_field_value, 'yes' ); ?>><?php _e( 'Enable', 'ams-cache' ); ?></option>
					<option value="no" <?php selected( $redis_field_value, 'no' ); ?>><?php _e( 'Disable', 'ams-cache' ); ?></option>
				</select>
			<?php elseif ( 'compress_level' === $k ) : ?>
				<select
					name="scm_option_advanced_driver_redis[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>"
				>
					<?php for ( $i = 1; $i <= 9; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $redis_field_value, $i ); ?>>
							<?php echo esc_html( $i ); ?>
						</option>
					<?php endfor; ?>
				</select>
			<?php elseif ( 'compress_threshold' === $k ) : ?>
				<input
					type="number"
					min="0"
					step="1"
					name="scm_option_advanced_driver_redis[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>"
					value="<?php echo esc_attr( $redis_field_value ); ?>"
				/>
			<?php else : ?>
				<input 
					type="text" 
					name="scm_option_advanced_driver_redis[<?php echo esc_attr( $k ); ?>]" 
					id="ams-cache-advanced-driver-redis-option-<?php echo esc_attr( $k ); ?>" 
					value="<?php echo esc_attr( $redis_field_value ); ?>" 
				/>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
	<p><em><?php _e( 'In order to authenticate with a username and password you need Redis >= 6.0.', 'ams-cache' ); ?></em></p>
	<p><em><?php _e( 'Select a Redis database when several sites share the same Redis server. DB0 is Redis default.', 'ams-cache' ); ?></em></p>
	<p><em><?php _e( 'Compression reduces Redis memory for full-page HTML. Threshold is bytes; level 6 is the balanced default.', 'ams-cache' ); ?></em></p>
	<p><em><?php _e( 'Change the settings carefully, make sure you know what you are doing.', 'ams-cache' ); ?></em></p>
	<?php if ( ! $is_driver_setting_correct ) : ?>
	<p><em class="scm-msg scm-msg-error">
		<?php _e( 'The settings you have set are not working, please recheck your settings.', 'ams-cache' ); ?>
		<?php if ( 'socket' === $option_redis_connection_type ) : ?>
			<br />
			<?php _e( 'Set the permission of the socket file to 777 might solve this problem.', 'ams-cache' ); ?>
		<?php endif; ?>
	</em></p>
	<?php endif; ?>

<?php else : ?>

	<div>

		<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label>
					<?php _e( 'Connection', 'ams-cache' ); ?>
				<label>
			</div>
			<span>
				<input type="radio" value="tcp" disabled 
					<?php checked( $option_redis_connection_type, 'tcp' ); ?>>
				<label>
					<?php _e( 'TCP', 'ams-cache' ); ?>
				<label>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="radio" value="socket" disabled
					<?php checked( $option_redis_connection_type, 'socket' ); ?>>
				<label>
					<?php _e( 'Unix Socket', 'ams-cache' ); ?>
				<label>
				<input type="hidden" name="scm_option_advanced_driver_redis_connection_type" value="<?php echo esc_attr( $option_redis_connection_type ); ?>">
			</span>
		</div><br /><br />

		<?php foreach ( $option_list as $k => $v ) : ?>
			<?php if ( 'or' === $v ) : ?>
				<hr />
				<?php continue; ?>
			<?php endif; ?>
		<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label>
					<?php echo esc_html( $v ); ?>
				<label>
			</div>
			<input type="text" value="<?php echo esc_attr( $option_default_list[ $k ] ); ?>" disabled  />
		</div>
		<?php endforeach; ?>
	</div>
	<?php // translators: %s is the name of the PHP extension ?>
	<p><em class="scm-msg scm-msg-error"><?php echo sprintf( __( 'PHP extension "%s" is not installed on your system.', 'ams-cache' ), 'redis' ); ?></em></p>

<?php endif; ?>
