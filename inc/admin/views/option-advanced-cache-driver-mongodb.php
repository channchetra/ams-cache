<?php
/**
 * AMS Cache - Advanced settings - Driver: MongoDB.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_mongodb = get_option( 'scm_option_advanced_driver_mongodb' );

$option_mongodb_connection_type = get_option( 'scm_option_advanced_driver_mongodb_connection_type', 'tcp' );

$option_list = array(
	'host'        => __( 'Host', 'ams-cache' ),
	'port'        => __( 'Port', 'ams-cache' ),
	'or'          => 'or',
	'unix_socket' => __( 'Unix Socket', 'ams-cache' ),
	'or2'         => 'or',
	'user'        => __( 'User', 'ams-cache' ),
	'pass'        => __( 'Password', 'ams-cache' ),
	'database'    => __( 'Database', 'ams-cache' ),
	'collection'  => __( 'Collection', 'ams-cache' ),
	'or3'         => 'or',
);

$option_default_list = array(
	'host'        => '127.0.0.1',
	'port'        => 27017,
	'or'          => 'or',
	'unix_socket' => '',
	'or2'         => 'or',
	'user'        => '',
	'pass'        => '',
	'database'    => 'test',
	'collection'  => 'cache_data',
	'or3'         => 'or',
);

$is_driver_setting_correct = false;

if ( scm_test_driver( 'mongo' ) ) {
	$is_driver_setting_correct = true;
}

?>

<?php if ( extension_loaded( 'mongodb' ) ) : ?>

	<?php if ( 'mongo' !== get_option( 'scm_option_driver' ) ) : ?>

		<div>

			<div class="scm-option-item">
				<div class="scm-label-wrapper">
					<label>
						<?php _e( 'Connection', 'ams-cache' ); ?>
					<label>
				</div>
				<span>
					<input type="radio" name="scm_option_advanced_driver_mongodb_connection_type" id="ams-cache-advanced-driver-mongodb-connection-tcp" value="tcp" 
						<?php checked( $option_mongodb_connection_type, 'tcp' ); ?>>
					<label for="ams-cache-advanced-driver-mongodb-connection-tcp">
						<?php _e( 'TCP', 'ams-cache' ); ?>
					<label>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="radio" name="scm_option_advanced_driver_mongodb_connection_type" id="ams-cache-advanced-driver-mongodb-connection-socket" value="socket" 
						<?php checked( $option_mongodb_connection_type, 'socket' ); ?>>
					<label for="ams-cache-advanced-driver-mongodb-connection-socket">
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
					<label for="ams-cache-advanced-driver-mongodb-option-<?php echo $k; ?>">
						<?php echo $v; ?>
					<label>
				</div>

				<?php if ( ! empty( $option_mongodb[ $k ] ) ) : ?>
					<?php $mongodb_field_value = $option_mongodb[ $k ]; ?>
				<?php else : ?>
					<?php $mongodb_field_value = $option_default_list[ $k ]; ?>
				<?php endif; ?>

				<input 
					type="text" 
					name="scm_option_advanced_driver_mongodb[<?php echo $k; ?>]" 
					id="ams-cache-advanced-driver-mongodb-option-<?php echo $k; ?>" 
					value="<?php echo esc_attr( $mongodb_field_value ); ?>" 
				/>
			</div>
			<?php endforeach; ?>

		</div>
		<p><em><?php _e( 'Change the settings carefully, make sure you know what you are doing.', 'ams-cache' ); ?></em></p>
		<?php if ( ! $is_driver_setting_correct ) : ?>
		<p><em class="scm-msg scm-msg-error">
			<?php _e( 'The settings you have set are not working, please recheck your settings.', 'ams-cache' ); ?>
			<?php if ( 'socket' === $option_mongodb_connection_type ) : ?>
				<br />
				<?php _e( 'Set the permission of the socket file to 777 might solve this problem.', 'ams-cache' ); ?>
			<?php endif; ?>
		<?php endif; ?>
		</em></p>
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
						<?php checked( $option_mongodb_connection_type, 'tcp' ); ?>>
					<label>
						<?php _e( 'TCP', 'ams-cache' ); ?>
					<label>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="radio" value="socket" disabled
						<?php checked( $option_mongodb_connection_type, 'socket' ); ?>>
					<label>
						<?php _e( 'Unix Socket', 'ams-cache' ); ?>
					<label>
					<input type="hidden" name="scm_option_advanced_driver_mongodb_connection_type" value="<?php echo esc_attr( $option_mongodb_connection_type ); ?>">
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
						<?php echo $v; ?>
					<label>
				</div>

				<?php if ( ! empty( $option_mongodb[ $k ] ) ) : ?>
					<?php $mongodb_field_value = $option_mongodb[ $k ]; ?>
				<?php else : ?>
					<?php $mongodb_field_value = $option_default_list[ $k ]; ?>
				<?php endif; ?>

				<input type="text" value="<?php echo esc_attr( $mongodb_field_value ); ?>" disabled  />
			</div>
			<?php endforeach; ?>
		</div>
		<p><em class="scm-msg scm-msg-info"><?php _e( 'This option is not available to change, becasue you are using this driver.', 'ams-cache' ); ?></em></p>

	<?php endif; ?>

<?php else : ?>

	<div>
		<?php foreach ( $option_list as $k => $v ) : ?>
		<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label>
					<?php echo $v; ?>
				<label>
			</div>
			<input type="text" value="<?php echo esc_attr( $option_default_list[ $k ] ); ?>" disabled  />
		</div>
		<?php endforeach; ?>
	</div>
	<?php // translators: %s is the name of the PHP extension ?>
	<p><em class="scm-msg scm-msg-error"><?php echo sprintf( __( 'PHP extension "%s" is not installed on your system.', 'ams-cache' ), 'mongodb' ); ?></em></p>

<?php endif; ?>
