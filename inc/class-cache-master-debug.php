<?php
/**
 * AMS Cache - Cache_Master debug, timing, and presentation helpers.
 *
 * Extracted from Cache_Master to keep the cache lifecycle class focused.
 * Trait methods run in the Cache_Master instance and use its private state.
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

trait SCM_Cache_Master_Debug {
	/**
	 * Add inline CSS.
	 *
	 * @return string
	 */
	public function get_front_enqueue_styles() {
		$custom_css = '';

		$is_widget = ( 'yes' === get_option( 'scm_option_benchmark_widget' ) );
		$is_footer = ( 'yes' === get_option( 'scm_option_benchmark_footer_text' ) );

		$display_widget = get_option( 'scm_option_benchmark_widget_display' );
		$display_footer = get_option( 'scm_option_benchmark_footer_text_display' );

		if ( $is_widget || $is_footer ) {
			$custom_css .= '
				.scm-img {
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
				.scm-img svg {
					width: 16px;
					height: 16px;
				}
				.scm-img path {
					fill: currentColor;
				}
				.scm-text {
					color: #111827;
					font-weight: 600;
					vertical-align: middle;
				}
				.scm-value {
					color: #111827;
					font-weight: 700;
				}
			';
		}

		if ( $is_widget ) {
			$custom_css .= '
				.ams-cache-plugin-widget {
					overflow: hidden;
					border-radius: 16px;
					background: #ffffff;
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
				}
				.ams-cache-plugin-widget .scm-table {
					display: grid;
					gap: 0;
					width: 100%;
				}
				.ams-cache-plugin-widget .scm-tr {
					display: grid;
					grid-template-columns: minmax(0, 1fr) auto;
					gap: 12px;
					align-items: center;
					padding: 12px 14px;
					border-top: 1px solid #e5e7eb;
				}
				.ams-cache-plugin-widget .scm-tr:first-child {
					border-top: 0;
				}
				.ams-cache-plugin-widget .scm-td {
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
					.ams-cache-plugin-widget .scm-img  {
						display: none;
					}
				';
			}

			if ( 'icon' === $display_widget ) {
				$custom_css .= '
					.ams-cache-plugin-widget .scm-text  {
						display: none;
					}
					.ams-cache-plugin-widget .scm-tr {
						grid-template-columns: 40px minmax(0, 1fr);
					}
				';
			}
		}

		if ( $is_footer ) {
			$custom_css .= '
				.ams-cache-benchmark-report {
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
				.ams-cache-benchmark-report .scm-td {
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
				.ams-cache-benchmark-report .scm-img {
					width: 22px;
					height: 22px;
					flex-basis: 22px;
					border-radius: 8px;
				}
				.ams-cache-benchmark-report .scm-td svg {
					width: 14px;
					height: 14px;
				}
			';

			if ( 'text' === $display_footer ) {
				$custom_css .= '
					.ams-cache-benchmark-report .scm-img  {
						display: none;
					}
				';
			}

			if ( 'icon' === $display_footer ) {
				$custom_css .= '
					.ams-cache-benchmark-report .scm-text  {
						display: none;
					}
					.ams-cache-benchmark-report .scm-value {
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

	/**
	 * Print debug message.
	 *
	 * @param string $position The position of the hook lifecycle.
	 *
	 * @return string
	 */
	private function debug_message( $position = '' )
	{
		$sql_queries  = get_num_queries();
		$timer_stop   = $this->wp_timer_stop();
		$memory_usage = $this->get_memory_usage();
		$date         = $this->get_date();
		$stage        = ( $position === 'ob_start' ) ? 'after' : 'before';

		scm_variable_stack( 'now',  $date, $stage );
		scm_variable_stack( 'memory_usage',  $memory_usage, $stage );
		scm_variable_stack( 'sql_queries',  $sql_queries, $stage );
		scm_variable_stack( 'page_generation_time',  $timer_stop, $stage );

		if ( 'no' === get_option( 'scm_option_html_debug_comment' ) ) {
			return '';
		}

		switch ( $position ) {
			case 'ob_start':
				$this->msg();
				$this->msg( '....... ' . __( 'After', 'ams-cache' ) . ' .......', 2 );
				$this->msg( sprintf( __( 'Now: %s', 'ams-cache' ), $date ) );
				$this->msg( sprintf( __( 'Memory usage: %s MB', 'ams-cache' ), $memory_usage ) );
				$this->msg( sprintf( __( 'SQL queries: %s', 'ams-cache' ), $sql_queries ) );
				$this->msg( sprintf( __( 'Page generated in %s seconds.', 'ams-cache' ), $timer_stop ) );
				$this->msg();
				$this->msg( '//-->' );
				break;

			case 'ob_stop':
				$ttl          = (int) get_option( 'scm_option_ttl' );
				$expires      = time() + $ttl;
				$date_expires = $this->get_date( $expires );

				$ttl_mechanism = get_option( 'scm_option_ttl_mechanism' );

				$this->msg();
				$this->msg( '<!--', 2 );
				$this->msg( __( 'This page is cached by AMS Cache plugin.', 'ams-cache' ), 2 );
				$this->msg( '....... ' . __( 'Before', 'ams-cache' ) . ' .......', 2 );
				$this->msg( sprintf( __( 'Time to cache: %s', 'ams-cache' ), $date ) );

				if ( $ttl_mechanism === 'disable' ) {
					$this->msg( __( 'TTL mechanism: disabled', 'ams-cache' ) );
				} else {
					$this->msg( sprintf( __( 'Expires at: %s', 'ams-cache' ), $date_expires ) );
				}

				$this->msg( sprintf( __( 'Memory usage: %s MB', 'ams-cache' ), $memory_usage ) );
				$this->msg( sprintf( __( 'SQL queries: %s', 'ams-cache' ), $sql_queries ) );
				$this->msg( sprintf( __( 'Page generated in %s seconds.', 'ams-cache' ), $timer_stop ) );
				break;
		}

		return $this->msg( null );
	}

	

	/**
	 * Create a message stack.
	 *
	 * @param string|null $msg         The message text body.
	 * @param int         $line_breaks The number of line break.
	 *
	 * @return void|string Return a string and clear the stack if $msg is null.
	 */
	private function msg( $msg = '', $line_breaks = 1 ) {
		static $message = array();

		if ( is_null( $msg ) ) {
			$output  = $message;
			$message = array();

			return implode( '', $output );
		}

		for ( $i = 0; $i < $line_breaks; $i++ ) {
			$msg .= "\n";
		}
		$message[] = $msg;
	}

	/**
	 * Create a clean output buffering for Cahce Master.
	 * This method makes AMS Cache become the first level of output 
	 * buffering.
	 *
	 * @return void
	 */
	private function wp_ob_start() {
		$levels = ob_get_level();
		for ( $i = 0; $i < $levels; $i++ ) {
			ob_end_clean();
		}
		ob_start();
	}

	/**
	 * Same as WordPress function wp_ob_end_flush_all, but leave the 
	 * AMS Cache level for the final output buffering.
	 *
	 * @return string
	 */
	private function wp_ob_end_flush_all() {
		$levels = ob_get_level();
		for ( $i = 0; $i < $levels - 1; $i++ ) {
			ob_end_flush();
		}
	}

	/**
	 * Return the WordPress timer.
	 *
	 * @return float
	 */
	private function wp_timer_stop() {
		// timer_stop is WordPress function.
		return timer_stop();
	}

	/**
	 * Get the date in format Y-m-d H:i:s.
	 *
	 * @param int $timestamp
	 *
	 * @return string
	 */
	private function get_date( $timestamp = 0 ) {
		if ( ! empty( $timestamp ) ) {
			return date( 'Y-m-d H:i:s', $timestamp );
		}
		return date( 'Y-m-d H:i:s' );
	}

	/**
	 * Return a string that is the memory usage in Megabyte.
	 *
	 * @return string
	 */
	private function get_memory_usage() {
		$memory_usage = memory_get_usage();
		$memory_usage = $memory_usage / ( 1024 * 1024 );
		$memory_usage = round( $memory_usage, 4 );

		return $memory_usage;
	}

	/**
	 * Log the chaning processes.
	 *
	 * @param string $type The type of the data source.
	 * @param string $key  The key name of a cache.
	 * @param string $data The string of HTML source code.
	 *
	 * @return void
	 */
	private function log( $type, $key, $data )
	{
		$dir = scm_get_stats_dir( $type );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$size = $this->get_string_bytes( $data );
		$file = $dir . '/' . $key . '.json';

		scm_delete_duplicate_stats_for_uri( $type, $this->get_request_uri(), $key, $this->driver );

		file_put_contents(
			$file,
			wp_json_encode(
				array(
					'size' => $size,
					'uri'  => $this->get_request_uri(),
				)
			)
		);

		scm_enforce_cache_entry_limit( $this->driver );
	}

	/**
	 * Get the bytes of string.
	 *
	 * @param string $content The string of the page content.
	 *
	 * @return int
	 */
	private function get_string_bytes( $content ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $content, '8bit' );
		}
		return strlen( $content );
	}

	/**
	 * The footer HTML.
	 *
	 * @return void
	 */
	private function footer_html() {
		
		$html = '
			<div class="ams-cache-benchmark-report" style="display: none">
				<div class="scm-td">
					<span class="scm-img scm-img-1" title="' . esc_attr( __( 'Cache status powered by AMS Cache plugin', 'ams-cache' ) ) . '">' . scm_get_svg_icon( 'status' ) . '</span>
					<span class="scm-text">' .  __( 'ស្ថានភាពឃ្លាំង', 'ams-cache' ) . ': </span>
					<span class="scm-value">
						<span class="scm-field-cache-status">-</span>
					</span>
				</div>
				<div class="scm-td">
					<span class="scm-img scm-img-2" title="' . esc_attr( __( 'Memory usage', 'ams-cache' ) ) . '">' . scm_get_svg_icon( 'memory' ) . '</span>
					<span class="scm-text">' .  __( 'អង្គចងចាំ', 'ams-cache' ) . ': </span>
					<span class="scm-value">
						<span class="scm-field-memory-usage">-</span> MB
					</span>
				</div>
				<div class="scm-td">
					<span class="scm-img scm-img-3" title="' . esc_attr( __( 'SQL queries', 'ams-cache' ) ) . '">' . scm_get_svg_icon( 'database' ) . '</span>
					<span class="scm-text">' .  __( 'សំណួរ SQL', 'ams-cache' ) . ': </span>
					<span class="scm-value">
						<span class="scm-field-sql-queries">-</span>
					</span>
				</div>
				<div class="scm-td">
					<span class="scm-img scm-img-4" title="' . esc_attr( __( 'Page generation time', 'ams-cache' ) ) . '">' . scm_get_svg_icon( 'speed' ) . '</span>
					<span class="scm-text">' .  __( 'ពេលបង្កើតទំព័រ', 'ams-cache' ) . ': </span>
					<span class="scm-value">
						<span class="scm-field-page-generation-time">-</span> (' .  __( 'វិនាទី', 'ams-cache' ) . ')
					</span>
				</div>
			</div>';
		
		return $html;
	}

	}
