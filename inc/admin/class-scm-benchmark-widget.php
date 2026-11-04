<?php
/**
 * AMS Cache - Benchmark widget class.
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

/**
 * Display the WordPress benchmark report in the widget area.
 */
class SCM_Benchmark_Widget extends WP_Widget {

	/**
	 * Sets up a new widget instance.
	 */
	public function __construct() {

		$widget_ops = array(
			'classname'                   => 'widget_scm_benchmark',
			'description'                 => __( 'Display the benchmark report before and after caching by AMS Cache.', 'ams-cache' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct( 'scm_benchmark', __( 'Benchmark Report', 'ams-cache' ), $widget_ops );
	}

	/**
	 * Outputs the content for the Mynote TOC instance.
	 */
	public function widget( $args, $instance ) {

		echo $args['before_widget'];

		echo '<div class="ams-cache-plugin-widget-wrapper" style="display: none">';

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		?>
			<div class="ams-cache-plugin-widget">
				<div class="scm-table">
					<?php echo scm_render_benchmark_metrics( 'widget' ); ?>
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
		$title = __( 'AMS Cache Report', 'ams-cache' );

		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		}
		?>
			<p>
				<label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
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
