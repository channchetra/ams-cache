<?php
/**
 * AMS Cache - Advanced settings - Driver: File.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.2.0
 * @version 2.2.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_file = (array) get_option( 'scm_option_advanced_driver_file', array() );

$option_default_list = array(
	'compress'           => 'no',
	'compress_threshold' => 4096,
	'compress_level'     => 1,
);

$option_list = array(
	'compress'           => __( 'Compression', 'ams-cache' ),
	'compress_threshold' => __( 'Compression Threshold', 'ams-cache' ),
	'compress_level'     => __( 'Compression Level', 'ams-cache' ),
);

?>

<div>
	<?php foreach ( $option_list as $k => $v ) : ?>
		<div class="scm-option-item">
			<div class="scm-label-wrapper">
				<label for="ams-cache-advanced-driver-file-option-<?php echo esc_attr( $k ); ?>">
					<?php echo esc_html( $v ); ?>
				<label>
			</div>

			<?php if ( isset( $option_file[ $k ] ) && '' !== $option_file[ $k ] ) : ?>
				<?php $file_field_value = $option_file[ $k ]; ?>
			<?php else : ?>
				<?php $file_field_value = $option_default_list[ $k ]; ?>
			<?php endif; ?>

			<?php if ( 'compress' === $k ) : ?>
				<select
					name="scm_option_advanced_driver_file[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-file-option-<?php echo esc_attr( $k ); ?>"
				>
					<option value="no" <?php selected( $file_field_value, 'no' ); ?>><?php _e( 'Disable', 'ams-cache' ); ?></option>
					<option value="yes" <?php selected( $file_field_value, 'yes' ); ?>><?php _e( 'Enable', 'ams-cache' ); ?></option>
				</select>
			<?php elseif ( 'compress_level' === $k ) : ?>
				<select
					name="scm_option_advanced_driver_file[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-file-option-<?php echo esc_attr( $k ); ?>"
				>
					<?php for ( $i = 1; $i <= 9; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $file_field_value, $i ); ?>>
							<?php echo esc_html( $i ); ?>
						</option>
					<?php endfor; ?>
				</select>
			<?php else : ?>
				<input
					type="number"
					min="0"
					step="1"
					name="scm_option_advanced_driver_file[<?php echo esc_attr( $k ); ?>]"
					id="ams-cache-advanced-driver-file-option-<?php echo esc_attr( $k ); ?>"
					value="<?php echo esc_attr( $file_field_value ); ?>"
				/>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<p><em><?php _e( 'File compression saves disk space and disk I/O for large HTML pages, but adds CPU work. On fast SSD storage, raw files can be faster.', 'ams-cache' ); ?></em></p>
