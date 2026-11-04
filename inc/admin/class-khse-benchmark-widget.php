<?php
/**
 * KH Speed Engine - Benchmark widget class.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

/**
 * Display the WordPress benchmark report in the widget area.
 */
class KHSE_Benchmark_Widget extends WP_Widget {

	/**
	 * Sets up a new widget instance.
	 */
	public function __construct() {

		$widget_ops = array(
			'classname'                   => 'widget_khse_benchmark',
			'description'                 => __( 'Display the benchmark report before and after caching by KH Speed Engine.', 'kh-speed-engine' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct( 'khse_benchmark', __( 'Benchmark Report', 'kh-speed-engine' ), $widget_ops );
	}

	/**
	 * Outputs the content for the Mynote TOC instance.
	 */
	public function widget( $args, $instance ) {

		echo $args['before_widget'];

		echo '<div class="kh-speed-engine-plugin-widget-wrapper" style="display: none">';

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		?>
			<div class="kh-speed-engine-plugin-widget">
				<div class="khse-table">
					<?php echo khse_render_benchmark_metrics( 'widget' ); ?>
				</div>
			</div>
		<?php

		echo '</div>';

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = __( 'KH Speed Engine Report', 'kh-speed-engine' );

		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		}
		?>
			<p>
				<label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'kh-speed-engine' ); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}

	/**
	 * Flushes the widget cache.
	 */
	public function flush_widget_cache() {
		_deprecated_function( __METHOD__, '4.4.0' );
	}
}
