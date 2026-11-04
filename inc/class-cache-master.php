<?php
/**
 * AMS Cache - Cache lifecycle controller.
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

require_once __DIR__ . '/class-cache-master-debug.php';

class Cache_Master {

	use SCM_Cache_Master_Debug;

	/**
	 * Cache insance.
	 *
	 * @var \Shieldon\SimpleCache\Cache
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
	 * Constructer.
	 */
	public function __construct() {
		$this->driver = scm_driver_factory( get_option( 'scm_option_driver' ) );

		if ( is_wp_error( $this->driver ) ) {
			$this->driver = null;
		}

		$this->config = scm_get_config_data();
	}

	/**
	 * Initialize everything the SCM plugin needs.
	 * 
	 * @return void
	 */
	public function init() {

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$uri         = scm_normalize_cache_uri( $request_uri );

		add_action( 'wp_enqueue_scripts', array( $this, 'front_enqueue_styles' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'front_enqueue_styles' ) );

		// Ignore all .php files.
		if ( '.php' === substr( $uri, -4 ) ) {
			return;
		}

		// The cacheability policy needs the full request URI, including the query string.
		if ( ! scm_is_cacheable_request_uri( $request_uri ) ) {
			return;
		}

		if ( $this->is_ignore() ) {
			return;
		}

		$uri = $this->get_request_uri();

		$this->cache_key = scm_get_cache_key( $uri );

		add_action( 'plugins_loaded', array( $this, 'ob_start' ), 5 );
		add_action( 'shutdown', array( $this, 'ob_stop' ), 0 );
		add_action( 'wp', array( $this, 'resolve_post_cache_state' ), 0 );
	}

	/**
	 * Register CSS style files for frontend use.
	 * 
	 * @return void
	 */
	public function front_enqueue_styles() {
		wp_register_style( 'scm-style', false );
		wp_enqueue_style( 'scm-style' );
		wp_add_inline_style( 'scm-style', $this->get_front_enqueue_styles() );
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

		$post_homepage = get_option( 'scm_option_post_homepage' );
		$post_types    = get_option( 'scm_option_post_types' );
		$post_archives = get_option( 'scm_option_post_archives' );
		$status        = get_option( 'scm_option_caching_status' );

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

		// Support to WooCommerce plugin.
		$woocommerce_support       = get_option( 'scm_option_woocommerce_status' );
		$woocommerce_post_types    = get_option( 'scm_option_woocommerce_post_types' );
		$woocommerce_post_archives = get_option( 'scm_option_woocommerce_post_archives' );

		if ( 'yes' === $woocommerce_support ) {

			if ( $is_singular ) {
				if ( isset( $woocommerce_post_types[ 'product' ] ) && is_singular( 'product' ) ) {
					$this->is_cache  = true;
					$this->data_type = 'product';
					return;
				}
			} elseif ( $is_archive ) {
				$woocommerce_archives = array(
					'product_cat',
					'product_tag',
				);
	
				foreach( $woocommerce_archives as $type ) {
					if ( isset( $woocommerce_post_archives[ $type ] ) && is_tax( $type ) ) {
						$this->is_cache  = true;
						$this->data_type = $type;
						return;
					}
				}
			}
		}

		// Do not cache 404 page.
		if ( is_404() ) {
			$this->is_cache = false;
			return;
		}
	}

	/**
	 * Start output cache if exists.
	 *
	 * @return void
	 */
	public function ob_start() {

		if ( $this->is_cache_visible() && $this->driver ) {

			$cached_content = $this->driver->get( $this->cache_key );

			// Never serve cached payloads that are empty or lack a valid HTML document.
			if ( ! empty( $cached_content ) && strpos( $cached_content, '</body>' ) !== false && strlen( $cached_content ) >= 1024 ) {

				$cached_content .= $this->debug_message( 'ob_start' );

				// This line must be at after debug_message.
				$cached_content = str_replace(
					'</body>',
					"\n" . scm_javascript() . "\n" . '</body>',
					$cached_content
				);

				echo $cached_content;
				exit;
			}
		}

		$this->wp_ob_start();
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

		if ( 'yes' === get_option( 'scm_option_benchmark_footer_text') ) {
			$content = str_replace(
				'</body>',
				"\n" . $this->footer_html() . "\n" . '</body>',
				$content
			);
		}

		if ( $this->is_cache ) {
			$before_optimization = $content;
			$content             = scm_optimize_html( $content );
			$optimization_report = scm_build_page_optimization_report(
				$before_optimization,
				$content,
				scm_get_page_optimization_settings(),
				$this->get_request_uri(),
				$this->data_type
			);

			scm_write_page_optimization_report( $this->get_request_uri(), $optimization_report );

			// Guard: optimization must never produce empty/invalid HTML.
			if ( empty( $content ) || strpos( $content, '</body>' ) === false || strlen( $content ) < 1024 ) {
				$content = $before_optimization;
			}
		}

		$cache_content = $content;
		$debug_message = $this->debug_message( 'ob_stop' );

		if ( $this->is_cache && $this->driver ) {
			$ttl = (int) get_option( 'scm_option_ttl' );

			$cache_content .= $debug_message;

			// Only store the cache entry when the final payload is a valid HTML document.
			if ( ! empty( $cache_content ) && strpos( $cache_content, '</body>' ) !== false && strlen( $cache_content ) >= 1024 ) {
				if ( 'disable' === get_option( 'scm_option_ttl_mechanism' ) ) {
					$ttl = null;
				}

				$this->driver->set( $this->cache_key, $cache_content, $ttl );
				scm_write_nginx_static_cache( $this->get_request_uri(), $cache_content );
				$this->log( $this->data_type, $this->cache_key, $cache_content );
			}
		}

		$content = str_replace(
			'</body>',
			"\n" . scm_javascript() . "\n" . '</body>',
			$content
		);

		ob_end_clean();
		ob_start();

		echo $content;
	}

	/**
	 * Check if a user can see the cached pages.
	 *
	 * @return bool
	 */
	private function is_cache_visible() {
		if ( is_user_logged_in() ) {
			return false;
		}

		return ! scm_request_has_cache_bypass_cookie();
	}

	/**
	 * Get request URI.
	 *
	 * @return string
	 */
	private function get_request_uri()
	{
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = scm_normalize_cache_uri( $_SERVER['REQUEST_URI'] );
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
		$uri = $this->get_request_uri();

		if ( true === $this->config['exclusion']['enable'] ) {
			foreach ( $this->config['exclusion']['excluded_list'] as $ignored_url ) {
				if ( strpos( $uri, $ignored_url ) === 0 ) {
					return true;
				}
			}
			foreach ( $this->config['exclusion']['excluded_get_vars'] as $var ) {
				if ( isset( $_GET[ $var ] ) ) {
					
					return true;
				}
			}
			foreach ( $this->config['exclusion']['excluded_post_vars'] as $var ) {
				if ( isset( $_POST[ $var ] ) ) {
					return true;
				}
			}
			foreach ( $this->config['exclusion']['excluded_cookie_vars'] as $var ) {
				if ( isset( $_COOKIE[ $var ] ) ) {
					return true;
				}
			}
		}

		if ( true === $this->config['woocommerce']['enable'] ) {	
			if ( isset( $_POST['add-to-cart'] ) && is_numeric( $_POST['add-to-cart'] ) ) {
				return true;
			}
			if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) {
				return true;
			}
			if ( isset( $_GET['wc-ajax'] ) ) {
				return true;
			}
		}

		return false;
	}
}
