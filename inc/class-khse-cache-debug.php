<?php
/**
 * KH Speed Engine - KHSE_Cache debug, timing, and presentation helpers.
 *
 * Extracted from KHSE_Cache to keep the cache lifecycle class focused.
 * Trait methods run in the KHSE_Cache instance and use its private state.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

trait KHSE_Cache_Debug {
	/**
	 * Add inline CSS.
	 *
	 * @return string
	 */
	public function get_front_inline_css() {
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

		khse_set_view_variable( 'now',  $date, $stage );
		khse_set_view_variable( 'memory_usage',  $memory_usage, $stage );
		khse_set_view_variable( 'sql_queries',  $sql_queries, $stage );
		khse_set_view_variable( 'page_generation_time',  $timer_stop, $stage );

		if ( 'no' === get_option( 'khse_option_html_debug_comment' ) ) {
			return '';
		}

		switch ( $position ) {
			case 'ob_stop':
				$ttl          = (int) get_option( 'khse_option_ttl' );
				$expires      = time() + $ttl;
				$date_expires = $this->get_date( $expires );

				$ttl_mechanism = get_option( 'khse_option_ttl_mechanism' );

				$this->msg();
				$this->msg( '<!--', 2 );
				$this->msg( __( 'This page is cached by KH Speed Engine plugin.', 'kh-speed-engine' ), 2 );
				$this->msg( '....... ' . __( 'Before', 'kh-speed-engine' ) . ' .......', 2 );
				$this->msg( sprintf( __( 'Time to cache: %s', 'kh-speed-engine' ), $date ) );

				if ( $ttl_mechanism === 'disable' ) {
					$this->msg( __( 'TTL mechanism: disabled', 'kh-speed-engine' ) );
				} else {
					$this->msg( sprintf( __( 'Expires at: %s', 'kh-speed-engine' ), $date_expires ) );
				}

				$this->msg( sprintf( __( 'Memory usage: %s MB', 'kh-speed-engine' ), $memory_usage ) );
				$this->msg( sprintf( __( 'SQL queries: %s', 'kh-speed-engine' ), $sql_queries ) );
				$this->msg( sprintf( __( 'Page generated in %s seconds.', 'kh-speed-engine' ), $timer_stop ) );
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
	 * @return string|null Return a string and clear the stack when $msg is null.
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
	 * This method makes KH Speed Engine become the first level of output
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
	 * KH Speed Engine level for the final output buffering.
	 *
	 * @return void
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
	 * @return string
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
	 * Return the memory usage in megabytes.
	 *
	 * @return float
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
		$dir = khse_get_stats_dir( $type );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$size = $this->get_string_bytes( $data );
		$file = $dir . '/' . $key . '.json';

		khse_delete_duplicate_stats_for_uri( $type, $this->get_request_uri(), $key, $this->get_driver() );

		$written = @file_put_contents(
			$file,
			wp_json_encode(
				array(
					'size' => $size,
					'uri'  => $this->get_request_uri(),
				)
			),
			LOCK_EX
		);

		if ( false === $written ) {
			error_log( '[KH Speed Engine] Could not write cache stats row: ' . $file );
		}

		khse_enforce_cache_entry_limit( $this->get_driver() );
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
	 * @return string
	 */
	private function footer_html() {
		return '<div class="kh-speed-engine-benchmark-report" style="display: none">' . khse_render_benchmark_metrics( 'footer' ) . '</div>';
	}

	}
