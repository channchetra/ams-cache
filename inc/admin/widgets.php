<?php
/**
 * KH Speed Engine - Benchmark widget registration.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

khse_add_benchmark_widget();

/**
 * Initialize banchmark widget.
 */
function khse_add_benchmark_widget() {
	if ( 'yes' === get_option( 'khse_option_benchmark_widget', 'no' ) ) {
		add_action( 'widgets_init', 'khse_register_benchmark_widget' );
	}
}

/**
 * Register banchmark widget. Trigged by khse_add_benchmark_widget
 */
function khse_register_benchmark_widget() {
	require_once KHSE_PLUGIN_DIR . 'inc/admin/class-khse-benchmark-widget.php';
	register_widget( 'KHSE_Benchmark_Widget' );
}
