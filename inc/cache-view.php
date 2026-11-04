<?php
/**
 * AMS Cache — cache-view.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
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
function scm_get_svg_icon( $type ) {
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
 * Internal helper; use scm_set_view_variable() / scm_drain_view_variables().
 *
 * @param string|null $key      Variable name, or null to drain.
 * @param mixed       $value    Variable value.
 * @param string      $position before|after.
 *
 * @return string|null JSON when draining, null otherwise.
 */
function scm_view_variable_state( $key = null, $value = '', $position = 'before' ) {
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
function scm_set_view_variable( $key, $value = '', $position = 'before' ) {
	scm_view_variable_state( $key, $value, $position );
}

/**
 * Drain the page performance variable state as JSON.
 *
 * @return string
 */
function scm_drain_view_variables() {
	return (string) scm_view_variable_state( null );
}

/**
 * Create JavaScript snippet used for performance report.
 *
 * This function is also used in expert mode.
 *
 * @return void
 */
function scm_javascript() {
	$script = '
		<script id="ams-cache-plugin">
			var cache_master = \'' . scm_drain_view_variables() . '\';
			var scm_report   = JSON.parse(cache_master);

			var scm_text_cache_status = "";
			var scm_text_memory_usage = "";
			var scm_text_sql_queries  = "";
			var scm_text_page_generation_time = "";

			if ("before" in scm_report) {
				scm_text_cache_status = "ទេ";
				scm_text_memory_usage = scm_report["before"]["memory_usage"];
				scm_text_sql_queries = scm_report["before"]["sql_queries"];
				scm_text_page_generation_time = scm_report["before"]["page_generation_time"];
			}
			if ("after" in scm_report) {
				scm_text_cache_status = "មាន";
				scm_text_memory_usage = scm_report["after"]["memory_usage"];
				scm_text_sql_queries = scm_report["after"]["sql_queries"];
				scm_text_page_generation_time = scm_report["after"]["page_generation_time"];
			}
			var scm_field_cache_status = document.querySelector(".scm-field-cache-status");
			if (null !== scm_field_cache_status) { scm_field_cache_status.textContent = scm_text_cache_status; }
			var scm_field_memory_usage = document.querySelector(".scm-field-memory-usage");
			if (null !== scm_field_memory_usage) { scm_field_memory_usage.textContent = scm_text_memory_usage; }
			var scm_field_sql_queries = document.querySelector(".scm-field-sql-queries");
			if (null !== scm_field_sql_queries) { scm_field_sql_queries.textContent = scm_text_sql_queries; }
			var scm_field_page_generation_time = document.querySelector(".scm-field-page-generation-time");
			if (null !== scm_field_page_generation_time) { scm_field_page_generation_time.textContent = scm_text_page_generation_time; }
			var cache_master_benchmark_report = document.querySelector(".ams-cache-benchmark-report");
			if (null !== cache_master_benchmark_report) { cache_master_benchmark_report.setAttribute("style", ""); }
			var cache_master_plugin_widget_wrapper = document.querySelector(".ams-cache-plugin-widget-wrapper");
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
function scm_render_benchmark_metrics( $layout = 'footer' ) {
	$metrics = array(
		array(
			'key'    => 'cache-status',
			'label'  => __( 'Cache status', 'ams-cache' ),
			'title'  => __( 'Cache status powered by AMS Cache plugin', 'ams-cache' ),
			'icon'   => 'status',
			'suffix' => '',
		),
		array(
			'key'    => 'memory-usage',
			'label'  => __( 'Memory usage', 'ams-cache' ),
			'title'  => __( 'Memory usage', 'ams-cache' ),
			'icon'   => 'memory',
			'suffix' => ' MB',
		),
		array(
			'key'    => 'sql-queries',
			'label'  => __( 'SQL queries', 'ams-cache' ),
			'title'  => __( 'SQL queries', 'ams-cache' ),
			'icon'   => 'database',
			'suffix' => '',
		),
		array(
			'key'    => 'page-generation-time',
			'label'  => __( 'Page generation time', 'ams-cache' ),
			'title'  => __( 'Page generation time', 'ams-cache' ),
			'icon'   => 'speed',
			'suffix' => ' (' . __( 'seconds', 'ams-cache' ) . ')',
		),
	);

	$html  = '';
	$index = 0;

	foreach ( $metrics as $metric ) {
		$index++;
		$icon  = '<span class="scm-img scm-img-' . $index . '" title="' . esc_attr( $metric['title'] ) . '">' . scm_get_svg_icon( $metric['icon'] ) . '</span>';
		$label = '<span class="scm-text">' . esc_html( $metric['label'] ) . ( 'footer' === $layout ? ': ' : '' ) . '</span>';
		$value = '<span class="scm-field-' . esc_attr( $metric['key'] ) . '">-</span>' . esc_html( $metric['suffix'] );

		if ( 'widget' === $layout ) {
			$html .= '<div class="scm-tr"><div class="scm-td">' . $icon . $label . '</div><div class="scm-td">' . $value . '</div></div>';
		} else {
			$html .= '<div class="scm-td">' . $icon . $label . '<span class="scm-value">' . $value . '</span></div>';
		}
	}

	return $html;
}
