<?php
/**
 * KH Speed Engine — cache-view.php
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Get a SVG icon.
 *
 * @param string $type The icon type.
 *
 * Font Awesome Free 5.15.1
 *
 * @return string
 */
function khse_get_svg_icon( $type ) {
	$svg = '';

	switch ( $type ) {
		case 'status':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 520 562'><path d='M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zM262.655 90c-54.497 0-89.255 22.957-116.549 63.758-3.536 5.286-2.353 12.415 2.715 16.258l34.699 26.31c5.205 3.947 12.621 3.008 16.665-2.122 17.864-22.658 30.113-35.797 57.303-35.797 20.429 0 45.698 13.148 45.698 32.958 0 14.976-12.363 22.667-32.534 33.976C247.128 238.528 216 254.941 216 296v4c0 6.627 5.373 12 12 12h56c6.627 0 12-5.373 12-12v-1.333c0-28.462 83.186-29.647 83.186-106.667 0-58.002-60.165-102-116.531-102zM256 338c-25.365 0-46 20.635-46 46 0 25.364 20.635 46 46 46s46-20.636 46-46c0-25.365-20.635-46-46-46z'/></svg>";
			break;

		case 'memory':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 582 552'><path d='M640 130.94V96c0-17.67-14.33-32-32-32H32C14.33 64 0 78.33 0 96v34.94c18.6 6.61 32 24.19 32 45.06s-13.4 38.45-32 45.06V320h640v-98.94c-18.6-6.61-32-24.19-32-45.06s13.4-38.45 32-45.06zM224 256h-64V128h64v128zm128 0h-64V128h64v128zm128 0h-64V128h64v128zM0 448h64v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h128v-26.67c0-8.84 7.16-16 16-16s16 7.16 16 16V448h64v-96H0v96z'/></svg>";
			break;

		case 'database':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 482 572'><path d='M448 73.143v45.714C448 159.143 347.667 192 224 192S0 159.143 0 118.857V73.143C0 32.857 100.333 0 224 0s224 32.857 224 73.143zM448 176v102.857C448 319.143 347.667 352 224 352S0 319.143 0 278.857V176c48.125 33.143 136.208 48.572 224 48.572S399.874 209.143 448 176zm0 160v102.857C448 479.143 347.667 512 224 512S0 479.143 0 438.857V336c48.125 33.143 136.208 48.572 224 48.572S399.874 369.143 448 336z'/></svg>";
			break;

		case 'speed':
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M296 160H180.6l42.6-129.8C227.2 15 215.7 0 200 0H56C44 0 33.8 8.9 32.2 20.8l-32 240C-1.7 275.2 9.5 288 24 288h118.7L96.6 482.5c-3.6 15.2 8 29.5 23.3 29.5 8.4 0 16.4-4.4 20.8-12l176-304c9.3-15.9-2.2-36-20.7-36z'/></svg>";
			break;
	}

	return $svg;
}

/**
 * Read or write the page performance variable state.
 *
 * Internal helper; use khse_set_view_variable() / khse_drain_view_variables().
 *
 * @param string|null $key      Variable name, or null to drain.
 * @param mixed       $value    Variable value.
 * @param string      $position before|after.
 *
 * @return string|null JSON when draining, null otherwise.
 */
function khse_view_variable_state( $key = null, $value = '', $position = 'before' ) {
	static $vars = array();

	if ( null === $key ) {
		$output = $vars;
		$vars   = array();

		return json_encode( $output );
	}

	$vars[ $position ][ $key ] = $value;

	return null;
}

/**
 * Add one value to the page performance variable state.
 *
 * @param string $key      Variable name.
 * @param mixed  $value    Variable value.
 * @param string $position before|after.
 *
 * @return void
 */
function khse_set_view_variable( $key, $value = '', $position = 'before' ) {
	khse_view_variable_state( $key, $value, $position );
}

/**
 * Drain the page performance variable state as JSON.
 *
 * @return string
 */
function khse_drain_view_variables() {
	return (string) khse_view_variable_state( null );
}

/**
 * Create JavaScript snippet used for performance report.
 *
 * This function is also used in expert mode.
 *
 * @return string
 */
function khse_javascript() {
	$cache_status_empty = function_exists( '__' ) ? __( 'No', 'kh-speed-engine' ) : 'No';
	$cache_status_hit   = function_exists( '__' ) ? __( 'Yes', 'kh-speed-engine' ) : 'Yes';
	$cache_status_empty_js = json_encode( $cache_status_empty );
	$cache_status_hit_js   = json_encode( $cache_status_hit );

	$script = '
		<script id="kh-speed-engine-plugin">
			var cache_master = \'' . khse_drain_view_variables() . '\';
			var khse_report   = JSON.parse(cache_master);

			var khse_text_cache_status = "";
			var khse_text_memory_usage = "";
			var khse_text_sql_queries  = "";
			var khse_text_page_generation_time = "";

			if ("before" in khse_report) {
				khse_text_cache_status = ' . $cache_status_empty_js . ';
				khse_text_memory_usage = khse_report["before"]["memory_usage"];
				khse_text_sql_queries = khse_report["before"]["sql_queries"];
				khse_text_page_generation_time = khse_report["before"]["page_generation_time"];
			}
			if ("after" in khse_report) {
				khse_text_cache_status = ' . $cache_status_hit_js . ';
				khse_text_memory_usage = khse_report["after"]["memory_usage"];
				khse_text_sql_queries = khse_report["after"]["sql_queries"];
				khse_text_page_generation_time = khse_report["after"]["page_generation_time"];
			}
			var khse_field_cache_status = document.querySelector(".khse-field-cache-status");
			if (null !== khse_field_cache_status) { khse_field_cache_status.textContent = khse_text_cache_status; }
			var khse_field_memory_usage = document.querySelector(".khse-field-memory-usage");
			if (null !== khse_field_memory_usage) { khse_field_memory_usage.textContent = khse_text_memory_usage; }
			var khse_field_sql_queries = document.querySelector(".khse-field-sql-queries");
			if (null !== khse_field_sql_queries) { khse_field_sql_queries.textContent = khse_text_sql_queries; }
			var khse_field_page_generation_time = document.querySelector(".khse-field-page-generation-time");
			if (null !== khse_field_page_generation_time) { khse_field_page_generation_time.textContent = khse_text_page_generation_time; }
			var cache_master_benchmark_report = document.querySelector(".kh-speed-engine-benchmark-report");
			if (null !== cache_master_benchmark_report) { cache_master_benchmark_report.setAttribute("style", ""); }
			var cache_master_plugin_widget_wrapper = document.querySelector(".kh-speed-engine-plugin-widget-wrapper");
			if (null !== cache_master_plugin_widget_wrapper) { cache_master_plugin_widget_wrapper.setAttribute("style", ""); }
		</script>
	';

	return preg_replace( '/\s+/', ' ', $script );
}

/**
 * Render the benchmark metric rows shared by the widget and footer.
 *
 * @param string $layout widget|footer.
 *
 * @return string
 */
function khse_render_benchmark_metrics( $layout = 'footer' ) {
	$metrics = array(
		array(
			'key'    => 'cache-status',
			'label'  => __( 'Cache status', 'kh-speed-engine' ),
			'title'  => __( 'Cache status powered by KH Speed Engine plugin', 'kh-speed-engine' ),
			'icon'   => 'status',
			'suffix' => '',
		),
		array(
			'key'    => 'memory-usage',
			'label'  => __( 'Memory usage', 'kh-speed-engine' ),
			'title'  => __( 'Memory usage', 'kh-speed-engine' ),
			'icon'   => 'memory',
			'suffix' => ' MB',
		),
		array(
			'key'    => 'sql-queries',
			'label'  => __( 'SQL queries', 'kh-speed-engine' ),
			'title'  => __( 'SQL queries', 'kh-speed-engine' ),
			'icon'   => 'database',
			'suffix' => '',
		),
		array(
			'key'    => 'page-generation-time',
			'label'  => __( 'Page generation time', 'kh-speed-engine' ),
			'title'  => __( 'Page generation time', 'kh-speed-engine' ),
			'icon'   => 'speed',
			'suffix' => ' (' . __( 'seconds', 'kh-speed-engine' ) . ')',
		),
	);

	$html  = '';
	$index = 0;

	foreach ( $metrics as $metric ) {
		$index++;
		$icon  = '<span class="khse-img khse-img-' . $index . '" title="' . esc_attr( $metric['title'] ) . '">' . khse_get_svg_icon( $metric['icon'] ) . '</span>';
		$label = '<span class="khse-text">' . esc_html( $metric['label'] ) . ( 'footer' === $layout ? ': ' : '' ) . '</span>';
		$value = '<span class="khse-field-' . esc_attr( $metric['key'] ) . '">-</span>' . esc_html( $metric['suffix'] );

		if ( 'widget' === $layout ) {
			$html .= '<div class="khse-tr"><div class="khse-td">' . $icon . $label . '</div><div class="khse-td">' . $value . '</div></div>';
		} else {
			$html .= '<div class="khse-td">' . $icon . $label . '<span class="khse-value">' . $value . '</span></div>';
		}
	}

	return $html;
}

/**
 * Build the inline CSS for the dashboard and benchmark UI.
 *
 * @return string
 */
function khse_front_inline_css() {
	$custom_css = '';

	$is_widget = ( 'yes' === get_option( 'khse_option_benchmark_widget' ) );
	$is_footer = ( 'yes' === get_option( 'khse_option_benchmark_footer_text' ) );

	$display_widget = get_option( 'khse_option_benchmark_widget_display' );
	$display_footer = get_option( 'khse_option_benchmark_footer_text_display' );

	if ( $is_widget || $is_footer ) {
		$custom_css .= '
			.khse-img {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				flex: 0 0 28px;
				width: 28px;
				height: 28px;
				border: 0;
				border-radius: 10px;
				background: #eff6ff;
				color: #3b82f6;
				line-height: 1;
				overflow: hidden;
			}
			.khse-img svg {
				width: 16px;
				height: 16px;
			}
			.khse-img path {
				fill: currentColor;
			}
			.khse-text {
				color: #111827;
				font-weight: 600;
				vertical-align: middle;
			}
			.khse-value {
				color: #111827;
				font-weight: 700;
			}
		';
	}

	if ( $is_widget ) {
		$custom_css .= '
			.kh-speed-engine-plugin-widget {
				overflow: hidden;
				border-radius: 16px;
				background: #ffffff;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
			}
			.kh-speed-engine-plugin-widget .khse-table {
				display: grid;
				gap: 0;
				width: 100%;
			}
			.kh-speed-engine-plugin-widget .khse-tr {
				display: grid;
				grid-template-columns: minmax(0, 1fr) auto;
				gap: 12px;
				align-items: center;
				padding: 12px 14px;
				border-top: 1px solid #e5e7eb;
			}
			.kh-speed-engine-plugin-widget .khse-tr:first-child {
				border-top: 0;
			}
			.kh-speed-engine-plugin-widget .khse-td {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				min-width: 0;
				padding: 0;
				font-size: 13px;
				line-height: 1.4;
			}
		';

		if ( 'text' === $display_widget ) {
			$custom_css .= '
				.kh-speed-engine-plugin-widget .khse-img  {
					display: none;
				}
			';
		}

		if ( 'icon' === $display_widget ) {
			$custom_css .= '
				.kh-speed-engine-plugin-widget .khse-text  {
					display: none;
				}
				.kh-speed-engine-plugin-widget .khse-tr {
					grid-template-columns: 40px minmax(0, 1fr);
				}
			';
		}
	}

	if ( $is_footer ) {
		$custom_css .= '
			.kh-speed-engine-benchmark-report {
				clear: both;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				justify-content: center;
				gap: 8px;
				width: 100%;
				text-align: center;
				font-size: 12px;
				margin: 12px 0;
			}
			.kh-speed-engine-benchmark-report .khse-td {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				min-height: 34px;
				padding: 5px 10px;
				border: 1px solid #e5e7eb;
				border-radius: 999px;
				background: #ffffff;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
				font-size: 13px;
			}
			.kh-speed-engine-benchmark-report .khse-img {
				width: 22px;
				height: 22px;
				flex-basis: 22px;
				border-radius: 8px;
			}
			.kh-speed-engine-benchmark-report .khse-td svg {
				width: 14px;
				height: 14px;
			}
		';

		if ( 'text' === $display_footer ) {
			$custom_css .= '
				.kh-speed-engine-benchmark-report .khse-img  {
					display: none;
				}
			';
		}

		if ( 'icon' === $display_footer ) {
			$custom_css .= '
				.kh-speed-engine-benchmark-report .khse-text  {
					display: none;
				}
				.kh-speed-engine-benchmark-report .khse-value {
					padding-left: 2px;
				}
			';
		}
	}

	if ( ! empty( $custom_css ) ) {
		return preg_replace( '/\s+/', ' ', $custom_css );
	}

	return '';
}
