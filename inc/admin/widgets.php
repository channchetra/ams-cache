<?php
/**
 * AMS Cache - Benchmark widget registration.
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

scm_add_benchmark_widget();

/**
 * Initialize banchmark widget.
 */
function scm_add_benchmark_widget() {
	if ( 'yes' === get_option( 'scm_option_benchmark_widget', 'no' ) ) {
		add_action( 'widgets_init', 'scm_register_benchmark_widget' );
	}
}

/**
 * Register banchmark widget. Trigged by scm_add_benchmark_widget
 */
function scm_register_benchmark_widget() {
	require_once SCM_PLUGIN_DIR . 'inc/admin/class-scm-benchmark-widget.php';
	register_widget( 'SCM_Benchmark_Widget' );
}
