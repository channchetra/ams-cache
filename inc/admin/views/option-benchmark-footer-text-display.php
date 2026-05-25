<?php
/**
 * AMS Cache - Benchmark footer display option.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.3.0
 * @version 2.8.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_benchmark_footer_text_display = get_option( 'scm_option_benchmark_footer_text_display', 'text' );
$choices                              = array(
	'text' => array(
		'label'       => __( 'Text', 'ams-cache' ),
		'description' => __( 'Show Khmer labels with each footer metric.', 'ams-cache' ),
		'preview'     => 'ស្ថានភាពឃ្លាំង: មាន',
	),
	'icon' => array(
		'label'       => __( 'Icon', 'ams-cache' ),
		'description' => __( 'Show compact icons only.', 'ams-cache' ),
		'preview'     => '✓ 0.42 វិនាទី',
	),
	'both' => array(
		'label'       => __( 'Both', 'ams-cache' ),
		'description' => __( 'Show icon and Khmer label together.', 'ams-cache' ),
		'preview'     => '✓ ពេលបង្កើតទំព័រ: 0.42 វិនាទី',
	),
);

?>

<div class="ams-display-choice-grid">
	<?php foreach ( $choices as $value => $choice ) : ?>
		<label class="ams-display-choice" for="ams-cache-benchmark-footer-text-display-option-<?php echo esc_attr( $value ); ?>">
			<input
				type="radio"
				name="scm_option_benchmark_footer_text_display"
				id="ams-cache-benchmark-footer-text-display-option-<?php echo esc_attr( $value ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( $option_benchmark_footer_text_display, $value ); ?>
			>
			<span class="ams-display-choice-card">
				<strong><?php echo esc_html( $choice['label'] ); ?></strong>
				<small><?php echo esc_html( $choice['description'] ); ?></small>
				<code><?php echo esc_html( $choice['preview'] ); ?></code>
			</span>
		</label>
	<?php endforeach; ?>
</div>
<p><em><?php _e( 'Choose how the footer benchmark label appears on guest pages.', 'ams-cache' ); ?></em></p>
