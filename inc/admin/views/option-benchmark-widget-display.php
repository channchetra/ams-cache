<?php
/**
 * AMS Cache - Benchmark widget display option.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.3.0
 * @version 2.8.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_benchmark_widget_display = get_option( 'scm_option_benchmark_widget_display', 'both' );
$choices                         = array(
	'text' => array(
		'label'       => __( 'Text', 'ams-cache' ),
		'description' => __( 'Use Khmer metric names in the widget.', 'ams-cache' ),
		'preview'     => 'អង្គចងចាំ 32 MB',
	),
	'icon' => array(
		'label'       => __( 'Icon', 'ams-cache' ),
		'description' => __( 'Use only metric icons for compact sidebars.', 'ams-cache' ),
		'preview'     => '◉ 32 MB',
	),
	'both' => array(
		'label'       => __( 'Both', 'ams-cache' ),
		'description' => __( 'Use icon and Khmer label together.', 'ams-cache' ),
		'preview'     => '◉ អង្គចងចាំ 32 MB',
	),
);

?>

<div class="ams-display-choice-grid">
	<?php foreach ( $choices as $value => $choice ) : ?>
		<label class="ams-display-choice" for="ams-cache-benchmark-widget-display-option-<?php echo esc_attr( $value ); ?>">
			<input
				type="radio"
				name="scm_option_benchmark_widget_display"
				id="ams-cache-benchmark-widget-display-option-<?php echo esc_attr( $value ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( $option_benchmark_widget_display, $value ); ?>
			>
			<span class="ams-display-choice-card">
				<strong><?php echo esc_html( $choice['label'] ); ?></strong>
				<small><?php echo esc_html( $choice['description'] ); ?></small>
				<code><?php echo esc_html( $choice['preview'] ); ?></code>
			</span>
		</label>
	<?php endforeach; ?>
</div>
<p><em><?php _e( 'Choose how the benchmark widget labels appear on guest pages.', 'ams-cache' ); ?></em></p>
