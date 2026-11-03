<?php
/**
 * Class CacheIdentityTest
 *
 * @package Cache_Master
 */

/**
 * Cache identity policy and Cache_Master request-gating regression tests.
 *
 * Covers the cache-poisoning regression where Cache_Master::init() passed a
 * path-only URI to scm_is_cacheable_request_uri(), bypassing the query-string
 * allow-list, and where unparseable authority-form URIs such as "//?p=1" were
 * treated as having no query string.
 */
class CacheIdentityTest extends WP_UnitTestCase {

	/**
	 * Whether REQUEST_URI existed before set_up().
	 *
	 * @var bool
	 */
	private $had_request_uri = false;

	/**
	 * Original REQUEST_URI value.
	 *
	 * @var string|null
	 */
	private $original_request_uri = null;

	/**
	 * Whether REQUEST_METHOD existed before set_up().
	 *
	 * @var bool
	 */
	private $had_request_method = false;

	/**
	 * Original REQUEST_METHOD value.
	 *
	 * @var string|null
	 */
	private $original_request_method = null;

	/**
	 * Original cookie superglobal.
	 *
	 * @var array
	 */
	private $original_cookie = array();

	/**
	 * Path to the private config file, if the plugin exposes one.
	 *
	 * @var string|null
	 */
	private $config_path = null;

	/**
	 * Backup of the private config file contents.
	 *
	 * @var string|null
	 */
	private $config_backup = null;

	/**
	 * Isolate request globals, options, and the private config file.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->had_request_uri      = array_key_exists( 'REQUEST_URI', $_SERVER );
		$this->original_request_uri = $this->had_request_uri ? $_SERVER['REQUEST_URI'] : null;

		$this->had_request_method      = array_key_exists( 'REQUEST_METHOD', $_SERVER );
		$this->original_request_method = $this->had_request_method ? $_SERVER['REQUEST_METHOD'] : null;

		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->original_cookie = $_COOKIE;
		$_COOKIE               = array();

		update_option( 'scm_option_driver', 'file' );
		update_option( 'scm_config', scm_get_default_config() );

		$this->config_path   = null;
		$this->config_backup = null;

		if ( function_exists( 'scm_get_config_path' ) ) {
			$this->config_path = scm_get_config_path();

			if ( file_exists( $this->config_path ) ) {
				$this->config_backup = file_get_contents( $this->config_path );
				@unlink( $this->config_path );
			}
		}
	}

	/**
	 * Restore request globals and the private config file.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null !== $this->config_path ) {
			if ( null !== $this->config_backup ) {
				file_put_contents( $this->config_path, $this->config_backup );
			} else {
				@unlink( $this->config_path );
			}
		}

		$_COOKIE = $this->original_cookie;

		if ( $this->had_request_uri ) {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}

		if ( $this->had_request_method ) {
			$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
		} else {
			unset( $_SERVER['REQUEST_METHOD'] );
		}

		parent::tear_down();
	}

	/**
	 * Data provider for cacheability policy expectations.
	 *
	 * @return array
	 */
	public function cacheability_uri_provider() {
		return array(
			'homepage'                    => array( '/', true ),
			'pretty permalink'            => array( '/sample-page/', true ),
			'bare question mark'          => array( '/?', true ),
			'single tracking var'         => array( '/?utm_source=newsletter', true ),
			'multiple tracking vars'      => array( '/?fbclid=abc&utm_medium=social', true ),
			'uppercase tracking var'      => array( '/?UTM_SOURCE=newsletter', true ),
			'array-shaped tracking var'   => array( '/?utm_source[]=newsletter', true ),
			'unknown query var'           => array( '/?p=123', false ),
			'tracking plus unknown var'   => array( '/?utm_source=x&preview=true', false ),
			'pagination query var'        => array( '/sample-page/?page=2', false ),
			'search query var'            => array( '/?s=test', false ),
			'wp-json root'                => array( '/wp-json/', false ),
			'wp-json route'               => array( '/wp-json/wp/v2/posts', false ),
			'authority-form double slash' => array( '//?p=1', false ),
			'authority-form triple slash' => array( '///?p=1', false ),
			'scheme with empty host'      => array( 'http:///x?p=1', false ),
			'raw fragment differential'   => array( '/about/?utm_source=x#&p=1', false ),
			'raw hash in path'            => array( '/about/#/?utm_source=x', false ),
			'encoded hash in value'       => array( '/?utm_source=x%23y', true ),
		);
	}

	/**
	 * The policy must allow only tracking query vars and reject unparseable URIs.
	 *
	 * @dataProvider cacheability_uri_provider
	 *
	 * @param string $uri      Request URI.
	 * @param bool   $expected Expected cacheability.
	 *
	 * @return void
	 */
	public function test_cacheability_policy( $uri, $expected ) {
		// Arrange: set_up() resets method and cookies to a cacheable baseline.

		// Act.
		$actual = scm_is_cacheable_request_uri( $uri );

		// Assert.
		$this->assertSame(
			$expected,
			$actual,
			sprintf(
				'scm_is_cacheable_request_uri( %s ) should be %s.',
				var_export( $uri, true ),
				$expected ? 'true' : 'false'
			)
		);
	}

	/**
	 * The default null argument must read $_SERVER['REQUEST_URI'].
	 *
	 * @return void
	 */
	public function test_null_argument_reads_server_request_uri() {
		// Arrange: set_up() resets method and cookies to a cacheable baseline.
		$_SERVER['REQUEST_URI'] = '/?p=123';

		// Act.
		$unknown_var_cacheable = scm_is_cacheable_request_uri();

		// Assert.
		$this->assertFalse(
			$unknown_var_cacheable,
			'scm_is_cacheable_request_uri() must reject $_SERVER["REQUEST_URI"] of /?p=123.'
		);

		// Arrange.
		$_SERVER['REQUEST_URI'] = '/?utm_source=newsletter';

		// Act.
		$tracking_cacheable = scm_is_cacheable_request_uri();

		// Assert.
		$this->assertTrue(
			$tracking_cacheable,
			'scm_is_cacheable_request_uri() must accept $_SERVER["REQUEST_URI"] of /?utm_source=newsletter.'
		);
	}

	/**
	 * Cache_Master must not register cache hooks for an unknown query string.
	 *
	 * @return void
	 */
	public function test_cache_master_rejects_unknown_query_string() {
		// Arrange.
		$_SERVER['REQUEST_URI'] = '/?p=123';
		$cm                     = new Cache_Master();

		// Act.
		$cm->init();

		// Assert.
		$this->assertFalse(
			has_action( 'plugins_loaded', array( $cm, 'ob_start' ) ),
			'plugins_loaded/ob_start must not be registered for /?p=123.'
		);
		$this->assertFalse(
			has_action( 'wp', array( $cm, 'get_post_data' ) ),
			'wp/get_post_data must not be registered for /?p=123.'
		);
		$this->assertFalse(
			has_action( 'shutdown', array( $cm, 'ob_stop' ) ),
			'shutdown/ob_stop must not be registered for /?p=123.'
		);
	}

	/**
	 * Cache_Master must not register cache hooks for an unparseable URI.
	 *
	 * @return void
	 */
	public function test_cache_master_rejects_unparseable_request_uri() {
		// Arrange.
		$_SERVER['REQUEST_URI'] = '//?p=1';
		$cm                     = new Cache_Master();

		// Act.
		$cm->init();

		// Assert.
		$this->assertFalse(
			has_action( 'plugins_loaded', array( $cm, 'ob_start' ) ),
			'plugins_loaded/ob_start must not be registered for //?p=1.'
		);
	}

	/**
	 * The .php early guard must fire before the query-string policy.
	 *
	 * @return void
	 */
	public function test_init_rejects_php_endpoint_with_tracking_query() {
		// Arrange: a tracking-only query would pass the query policy; only the .php guard can reject it.
		$_SERVER['REQUEST_URI'] = '/index.php?utm_source=newsletter';
		$cm                     = new Cache_Master();

		// Act.
		$cm->init();

		// Assert.
		$this->assertFalse(
			has_action( 'plugins_loaded', array( $cm, 'ob_start' ) ),
			'plugins_loaded/ob_start must not be registered for /index.php?utm_source=newsletter.'
		);
	}

	/**
	 * Cache_Master must register cache hooks for tracking-only query strings.
	 *
	 * @return void
	 */
	public function test_cache_master_accepts_tracking_query_string() {
		// Arrange.
		$_SERVER['REQUEST_URI'] = '/?utm_source=newsletter';
		$cm                     = new Cache_Master();

		// Act.
		$cm->init();

		// Assert.
		$this->assertNotFalse(
			has_action( 'plugins_loaded', array( $cm, 'ob_start' ) ),
			'plugins_loaded/ob_start must be registered for /?utm_source=newsletter.'
		);
		$this->assertNotFalse(
			has_action( 'wp', array( $cm, 'get_post_data' ) ),
			'wp/get_post_data must be registered for /?utm_source=newsletter.'
		);
		$this->assertNotFalse(
			has_action( 'shutdown', array( $cm, 'ob_stop' ) ),
			'shutdown/ob_stop must be registered for /?utm_source=newsletter.'
		);
	}

	/**
	 * Tracking query strings must not change the cache key.
	 *
	 * @return void
	 */
	public function test_cache_key_is_path_only_for_tracking_queries() {
		// Arrange.
		$path_uri    = '/';
		$tracking_ok = '/?utm_source=newsletter';

		// Act.
		$path_key     = scm_get_cache_key( $path_uri );
		$tracking_key = scm_get_cache_key( $tracking_ok );

		// Assert.
		$this->assertSame(
			$path_key,
			$tracking_key,
			'Cache key must ignore tracking query strings.'
		);
	}

	/**
	 * Cache key output is a pinned contract; changing it invalidates every stored entry.
	 *
	 * @return void
	 */
	public function test_cache_key_format_is_pinned() {
		// Arrange.
		update_option( 'scm_option_cache_key_prefix', 'fixture' );

		// Act.
		$sample_key  = scm_get_cache_key( '/sample-page/' );
		$homepage_key = scm_get_cache_key( '/' );

		// Assert.
		$this->assertSame(
			'b13fff94880b899e710496580b1b901e',
			$sample_key,
			'Cache key for /sample-page/ must stay pinned to the md5( prefix|path ) contract.'
		);
		$this->assertSame(
			'04361aeb3a5145a3ddadb18220dcd7e9',
			$homepage_key,
			'Cache key for / must stay pinned to the md5( prefix|path ) contract.'
		);
	}

	/**
	 * Non-GET requests must never be cacheable.
	 *
	 * @return void
	 */
	public function test_non_get_requests_are_not_cacheable() {
		// Arrange.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// Act.
		$cacheable = scm_is_cacheable_request_uri( '/' );

		// Assert.
		$this->assertFalse( $cacheable, 'POST requests must not be cacheable.' );
	}

	/**
	 * HEAD requests must be cacheable.
	 *
	 * @return void
	 */
	public function test_head_requests_are_cacheable() {
		// Arrange.
		$_SERVER['REQUEST_METHOD'] = 'HEAD';

		// Act.
		$cacheable = scm_is_cacheable_request_uri( '/' );

		// Assert.
		$this->assertTrue( $cacheable, 'HEAD requests must be cacheable.' );
	}

	/**
	 * Authenticated requests must never be cacheable.
	 *
	 * @return void
	 */
	public function test_auth_cookie_requests_are_not_cacheable() {
		// Arrange.
		$_COOKIE['wordpress_logged_in_abc'] = 'user';

		// Act.
		$cacheable = scm_is_cacheable_request_uri( '/' );

		// Assert.
		$this->assertFalse( $cacheable, 'Requests with WordPress auth cookies must not be cacheable.' );
	}

	/**
	 * Admin and static-asset paths must never be cacheable.
	 *
	 * @return void
	 */
	public function test_admin_and_static_paths_are_not_cacheable() {
		// Arrange.
		$admin_uri  = '/wp-admin/';
		$static_uri = '/image.jpg?utm_source=x';

		// Act.
		$admin_cacheable  = scm_is_cacheable_request_uri( $admin_uri );
		$static_cacheable = scm_is_cacheable_request_uri( $static_uri );

		// Assert.
		$this->assertFalse( $admin_cacheable, '/wp-admin/ must not be cacheable.' );
		$this->assertFalse( $static_cacheable, '/image.jpg?utm_source=x must not be cacheable.' );
	}
}
