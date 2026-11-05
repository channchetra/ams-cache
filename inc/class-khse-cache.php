<?php
/**
 * KH Speed Engine - Cache lifecycle controller.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

require_once __DIR__ . '/class-khse-cache-debug.php';

class KHSE_Cache {

	use KHSE_Cache_Debug;

	/**
	 * Cache insance.
	 *
	 * @var \Shieldon\SimpleCache\Cache|null
	 */
	public $driver;

	/**
	 * The key of the cached data.
	 *
	 * @var string
	 */
	public $cache_key = '';

	/**
	 * Is current page available to be cached?
	 *
	 * @var bool
	 */
	public $is_cache = false;

	/**
	 * Data type.
	 *
	 * @var string
	 */
	public $data_type = '';

	/**
	 * Configuation from JSON file.
	 *
	 * @var array
	 */
	public $config = array();

	/**
	 * Whether the driver has been resolved for this request.
	 *
	 * @var bool
	 */
	private $driver_resolved = false;

	/**
	 * Constructer.
	 */
	public function __construct() {
		$this->config = khse_get_config_data();
	}

	/**
	 * Resolve the cache driver on first use.
	 *
	 * Front-end requests that are not cacheable should never open a Redis,
	 * database, or socket connection.
	 *
	 * @return \Shieldon\SimpleCache\Cache|null
	 */
	public function get_driver() {
		if ( $this->driver_resolved ) {
			return $this->driver;
		}

		$this->driver_resolved = true;
		$driver                = khse_driver_factory( get_option( 'khse_option_driver' ) );

		$this->driver = is_wp_error( $driver ) ? null : $driver;

		return $this->driver;
	}

	/**
	 * Initialize everything KH Speed Engine needs.
	 * 
	 * @return void
	 */
	public function init() {

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$uri         = khse_normalize_cache_uri( $request_uri );

		add_action( 'wp_enqueue_scripts', array( $this, 'front_enqueue_styles' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'front_enqueue_styles' ) );

		// Ignore all .php files.
		if ( '.php' === substr( $uri, -4 ) ) {
			return;
		}

		// The cacheability policy needs the full request URI, including the query string.
		if ( ! khse_is_cacheable_request_uri( $request_uri ) ) {
			return;
		}

		if ( $this->is_ignore() ) {
			return;
		}

		$uri = $this->get_request_uri();

		$this->cache_key = khse_get_cache_key( $uri );

		// `init()` runs from `plugins_loaded`, so the buffer must attach to a
		// hook that fires later. `template_redirect` runs before the template
		// is included and after `wp` has resolved the request conditionals.
		add_action( 'wp', array( $this, 'resolve_post_cache_state' ), 0 );
		add_action( 'template_redirect', array( $this, 'ob_start' ), 0 );
	}

	/**
	 * Register CSS style files for frontend use.
	 * 
	 * @return void
	 */
	public function front_enqueue_styles() {
		wp_register_style( 'kh-speed-engine-style', false );
		wp_enqueue_style( 'kh-speed-engine-style' );
		wp_add_inline_style( 'kh-speed-engine-style', $this->get_front_enqueue_styles() );
	}

	/**
	 * Resolve whether the current request is cacheable and which cache bucket it belongs to.
	 *
	 * @return void
	 */
	public function resolve_post_cache_state() {

		// Logged-in users will not trigger the cache.
		if ( is_user_logged_in() ) {
			
			return;
		}

		$post_homepage = get_option( 'khse_option_post_homepage' );
		$post_types    = get_option( 'khse_option_post_types' );
		$post_archives = get_option( 'khse_option_post_archives' );
		$status        = get_option( 'khse_option_caching_status' );

		if ( 'enable' !== $status ) {
			$this->is_cache = false;
			return;
		}

		$is_singular = false;
		$is_archive  = false;

		if ( is_front_page() || is_home() ) {
			$this->is_cache  = true;
			$this->data_type = 'homepage';
			
			if ( 'no' === $post_homepage ) {
				
				$this->is_cache = false;
			}
			return;
		
		} else {

			$is_singular = is_singular();
			$is_archive  = is_archive();

			if ( $is_singular ) {
				$types = array_merge(
					array(
						'post',
						'page',
					),
					array_keys(
						get_post_types(
							array(
								'public'   => true,
								'_builtin' => false,
							),
							'names',
							'and'
						)
					)
				);
	
				foreach( $types as $type ) {
					if ( isset( $post_types[ $type ] ) && is_singular( $type ) ) {
						
						$this->is_cache  = true;
						$this->data_type = $type;
						return;
					}
				}
			} elseif ( $is_archive ) {
				$archives = array(
					'category' => 'is_category',
					'tag'      => 'is_tag',
					'date'     => 'is_date',
					'author'   => 'is_author',
				);
	
				foreach( $archives as $type => $wp_function ) {
					if ( isset( $post_archives[ $type ] ) && $wp_function() ) {
						$this->is_cache  = true;
						$this->data_type = $type;
						return;
					}
				}

				$custom_post_type_archives = get_post_types(
					array(
						'public'      => true,
						'has_archive' => true,
						'_builtin'    => false,
					),
					'objects',
					'and'
				);

				foreach ( $custom_post_type_archives as $post_type ) {
					$type = 'archive_' . $post_type->name;

					if ( isset( $post_archives[ $type ] ) && is_post_type_archive( $post_type->name ) ) {
						$this->is_cache  = true;
						$this->data_type = $type;
						return;
					}
				}
			}
		}

	}

	/**
	 * Start output cache if exists.
	 *
	 * @return void
	 */
	public function ob_start() {
		$driver = $this->get_driver();

		if ( $driver ) {
			$config = $this->config;

			$config['cache_enabled']      = 'enable' === get_option( 'khse_option_caching_status', 'disable' );
			$config['cache_key_prefix']   = khse_get_cache_key_prefix();
			$config['html_debug_comment'] = khse_option_is_enabled( 'khse_option_html_debug_comment', 'yes' );

			$cached_content = khse_serve_cached_response( $config, $driver );

			if ( null !== $cached_content ) {
				echo $cached_content;
				exit;
			}
		}

		$this->wp_ob_start();

		// Pair the buffer start with its shutdown handler so a request that
		// never starts buffering can never stop another component's buffer.
		add_action( 'shutdown', array( $this, 'ob_stop' ), 0 );
	}

	/**
	 *  Stop output buffering.
	 *
	 * @return void
	 */
	public function ob_stop() {

		$this->wp_ob_end_flush_all();

		$content = ob_get_contents();

		// Make sure that the page has valid HTML content.
		if ( empty( $content ) || strpos( $content, '</body>' ) === false ) {
			return;
		}

		if ( 'yes' === get_option( 'khse_option_benchmark_footer_text') ) {
			$content = str_replace(
				'</body>',
				"\n" . $this->footer_html() . "\n" . '</body>',
				$content
			);
		}

		if ( $this->is_cache ) {
			$before_optimization = $content;
			$content             = khse_optimize_html( $content );
			$optimization_report = khse_build_page_optimization_report(
				$before_optimization,
				$content,
				khse_get_page_optimization_settings(),
				$this->get_request_uri(),
				$this->data_type
			);

			khse_write_page_optimization_report( $this->get_request_uri(), $optimization_report );

			// Guard: optimization must never produce empty/invalid HTML.
			if ( empty( $content ) || strpos( $content, '</body>' ) === false || strlen( $content ) < 1024 ) {
				$content = $before_optimization;
			}
		}

		$cache_content = $content;
		$debug_message = $this->debug_message( 'ob_stop' );

		$driver = $this->get_driver();

		if ( $this->is_cache && $driver ) {
			$ttl = (int) get_option( 'khse_option_ttl' );

			$cache_content .= $debug_message;

			// Only store the cache entry when the final payload is a valid HTML document.
			if ( ! empty( $cache_content ) && strpos( $cache_content, '</body>' ) !== false && strlen( $cache_content ) >= 1024 ) {
				if ( 'disable' === get_option( 'khse_option_ttl_mechanism' ) ) {
					$ttl = null;
				}

				try {
					$written = $driver->set( $this->cache_key, $cache_content, $ttl );
				} catch ( \Throwable $e ) {
					$written = false;
					error_log( '[KH Speed Engine] Could not write cache entry ' . $this->cache_key . ': ' . $e->getMessage() );
				}

				if ( $written ) {
					khse_write_nginx_static_cache( $this->get_request_uri(), $cache_content );
					$this->log( $this->data_type, $this->cache_key, $cache_content );
				}
			}
		}

		$content = str_replace(
			'</body>',
			"\n" . khse_javascript() . "\n" . '</body>',
			$content
		);

		ob_end_clean();
		ob_start();

		echo $content;
	}

	/**
	 * Get request URI.
	 *
	 * @return string
	 */
	private function get_request_uri()
	{
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = khse_normalize_cache_uri( $_SERVER['REQUEST_URI'] );
		} else {
			$path = '/';
		}

		return $path;
	}

	/**
	 * Check if current page should be ignored or not.
	 *
	 * @return bool
	 */
	private function is_ignore() {
		return khse_is_cache_request_excluded( $this->config );
	}
}
