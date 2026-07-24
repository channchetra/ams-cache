<?php
/**
 * AMS Cache — cache-preload.php
 *
 * @package   AMS Cache
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'SCM_INC' ) ) {
    die;
}

function scm_is_preload_enabled() {
	return 'yes' === get_option( 'scm_option_preload_cache', 'no' );
}

/**
 * Get the configured preload URL limit.
 *
 * @param int|null $limit Optional limit override.
 *
 * @return int
 */
function scm_get_preload_limit( $limit = null ) {
	$limit     = is_null( $limit ) ? (int) get_option( 'scm_option_preload_limit', 50 ) : (int) $limit;
	$max_limit = (int) apply_filters( 'scm_preload_max_limit', 1000 );
	$max_limit = max( 1, min( 5000, $max_limit ) );

	return max( 1, min( $max_limit, $limit ) );
}

/**
 * Get the number of URLs to dispatch per preload batch.
 *
 * @return int
 */
function scm_get_preload_batch_size() {
	$batch_size = (int) apply_filters( 'scm_preload_batch_size', 25 );

	return max( 1, min( 100, $batch_size ) );
}

/**
 * Schedule a background cache preload.
 *
 * @param bool $force Replace an existing scheduled preload.
 *
 * @return void
 */
function scm_schedule_preload_cache( $force = false ) {
	if ( ! function_exists( 'wp_schedule_single_event' ) || ! scm_is_preload_enabled() || 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return;
	}

	if ( $force && function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'scm_preload_cache_event' );
	}

	if ( $force || ! wp_next_scheduled( 'scm_preload_cache_event' ) ) {
		wp_schedule_single_event( time() + 5, 'scm_preload_cache_event' );
	}
}

/**
 * Schedule the next preload queue batch.
 *
 * @param int $delay Delay in seconds.
 *
 * @return void
 */
function scm_schedule_preload_queue( $delay = 15 ) {
	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		return;
	}

	if ( ! wp_next_scheduled( 'scm_preload_queue_event' ) ) {
		wp_schedule_single_event( time() + max( 1, (int) $delay ), 'scm_preload_queue_event' );
	}
}

/**
 * Bootstrap preload once after a plugin deployment.
 *
 * @return void
 */
function scm_maybe_schedule_preload_cache() {
	if ( ! scm_is_preload_enabled() || 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) || ! function_exists( 'wp_next_scheduled' ) ) {
		return;
	}

	// Resume a stalled queue whose cron event was lost (object cache flush, deploy mid-run).
	// Cheap in-memory cron check first; queue transient (DB query) fetched only when the event is missing.
	if ( ! wp_next_scheduled( 'scm_preload_queue_event' ) ) {
		$queue = get_transient( 'scm_preload_queue' );

		if ( ! empty( $queue ) && is_array( $queue ) ) {
			scm_schedule_preload_queue();
		}
	}

	$signature = defined( 'SCM_PLUGIN_VERSION' ) ? (string) SCM_PLUGIN_VERSION : 'ams-cache';

	if ( wp_next_scheduled( 'scm_preload_cache_event' ) ) {
		if ( $signature !== get_option( 'scm_preload_bootstrap_version', '' ) ) {
			update_option( 'scm_preload_bootstrap_version', $signature );
		}

		return;
	}

	if ( $signature === get_option( 'scm_preload_bootstrap_version', '' ) ) {
		return;
	}

	scm_schedule_preload_cache();

	if ( wp_next_scheduled( 'scm_preload_cache_event' ) ) {
		update_option( 'scm_preload_bootstrap_version', $signature );
	}
}

/**
 * Check whether a hostname resolves only to the local machine.
 *
 * @param string $host Hostname or IP address.
 *
 * @return bool
 */
function scm_preload_host_is_loopback( $host ) {
	static $results = array();

	$host = strtolower( trim( (string) $host ) );

	if ( isset( $results[ $host ] ) ) {
		return $results[ $host ];
	}

	$addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );

	if ( function_exists( 'dns_get_record' ) && defined( 'DNS_AAAA' ) ) {
		foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $record ) {
			if ( ! empty( $record['ipv6'] ) ) {
				$addresses[] = $record['ipv6'];
			}
		}
	}

	$has_address = false;

	foreach ( array_unique( $addresses ) as $address ) {
		if ( ! is_string( $address ) || '' === $address ) {
			continue;
		}

		$has_address = true;

		if ( '::1' !== strtolower( trim( $address ) ) && 0 !== strpos( $address, '127.' ) ) {
			return $results[ $host ] = false;
		}
	}

	return $results[ $host ] = $has_address;
}

/**
 * Resolve preload TLS verification without weakening production HTTPS.
 *
 * @param string $url Same-site preload URL.
 *
 * @return bool
 */
function scm_preload_sslverify( $url ) {
	$verify    = (bool) apply_filters( 'scm_preload_sslverify', true );
	$url_parts = parse_url( $url );

	if ( $verify && ! empty( $url_parts['host'] ) && 'https' === strtolower( isset( $url_parts['scheme'] ) ? $url_parts['scheme'] : '' ) && scm_preload_host_is_loopback( $url_parts['host'] ) ) {
		return false;
	}

	return $verify;
}

/**
 * Preload a single URL with an unauthenticated loopback request.
 *
 * @param string $url      URL to preload.
 * @param bool   $blocking Wait for the page response so cache writing finishes.
 * @param int    $timeout  Request timeout in seconds.
 *
 * @return array|\WP_Error|null
 */
function scm_preload_url( $url, $blocking = true, $timeout = 8 ) {
	if ( empty( $url ) || ! function_exists( 'wp_remote_get' ) ) {
		return null;
	}

	$url_parts  = parse_url( esc_url_raw( $url ) );
	$home_parts = parse_url( home_url( '/' ) );

	if (
		empty( $url_parts['scheme'] ) ||
		empty( $url_parts['host'] ) ||
		empty( $home_parts['host'] ) ||
		strtolower( $url_parts['host'] ) !== strtolower( $home_parts['host'] ) ||
		( isset( $url_parts['port'] ) ? (int) $url_parts['port'] : 0 ) !== ( isset( $home_parts['port'] ) ? (int) $home_parts['port'] : 0 )
	) {
		return null;
	}

	return wp_remote_get(
		$url_parts['scheme'] . '://' . $url_parts['host'] . ( isset( $url_parts['port'] ) ? ':' . (int) $url_parts['port'] : '' ) . ( isset( $url_parts['path'] ) ? $url_parts['path'] : '/' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' ),
		array(
			'blocking'    => (bool) $blocking,
			'timeout'     => max( 1, (int) $timeout ),
			'redirection' => 2,
			'sslverify'   => scm_preload_sslverify( $url ),
			'user-agent'  => 'AMS Cache Preloader/' . SCM_PLUGIN_VERSION,
		)
	);
}

/**
 * Get post types enabled for preload.
 *
 * @return array
 */
function scm_get_preload_post_types() {
	$post_types = (array) get_option( 'scm_option_post_types', array() );
	$post_types = array_keys(
		array_filter(
			$post_types,
			function ( $enabled ) {
				return 'yes' === $enabled;
			}
		)
	);

	$post_types = array_values( array_diff( $post_types, array( 'home' ) ) );

	if ( empty( $post_types ) ) {
		$post_types = array( 'post' );
	}

	return apply_filters( 'scm_preload_post_types', $post_types );
}

/**
 * Convert a link from homepage HTML into a same-site absolute URL.
 *
 * @param string $href     Raw href value.
 * @param string $base_url Base URL.
 *
 * @return string
 */
function scm_normalize_homepage_link_url( $href, $base_url ) {
	$href = trim( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ) );

	if ( '' === $href || '#' === $href || preg_match( '#^(?:javascript|mailto|tel|data):#i', $href ) ) {
		return '';
	}

	$href = strtok( $href, '#' );

	if ( false === $href || '' === $href ) {
		return '';
	}

	$home_parts = parse_url( home_url( '/' ) );
	$base_parts = parse_url( $base_url );
	$href_parts = parse_url( $href );
	$scheme     = isset( $home_parts['scheme'] ) ? $home_parts['scheme'] : 'https';
	$host       = isset( $home_parts['host'] ) ? $home_parts['host'] : '';
	$port       = isset( $home_parts['port'] ) ? ':' . $home_parts['port'] : '';

	if ( empty( $host ) ) {
		return '';
	}

	if ( 0 === strpos( $href, '//' ) ) {
		$href = $scheme . ':' . $href;
		$href_parts = parse_url( $href );
	}

	if ( ! empty( $href_parts['scheme'] ) && ! empty( $href_parts['host'] ) ) {
		if ( strtolower( $href_parts['host'] ) !== strtolower( $host ) ) {
			return '';
		}

		$path = isset( $href_parts['path'] ) ? $href_parts['path'] : '/';
	} elseif ( 0 === strpos( $href, '/' ) ) {
		$path = $href;
	} else {
		$base_path = isset( $base_parts['path'] ) ? $base_parts['path'] : '/';
		$base_dir  = rtrim( dirname( $base_path ), '\\/' );
		$path      = ( '/' === $base_dir ? '' : $base_dir ) . '/' . $href;
	}

	$path = scm_normalize_cache_uri( $path );

	if ( ! scm_is_cacheable_document_path( $path ) ) {
		return '';
	}

	return $scheme . '://' . $host . $port . $path;
}

/**
 * Crawl homepage HTML and return internal URLs in document order.
 *
 * @param int $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_homepage_discovered_urls( $limit = 100 ) {
	if ( ! function_exists( 'wp_remote_get' ) ) {
		return array();
	}

	$limit  = scm_get_preload_limit( $limit );
	$cached = get_transient( 'scm_homepage_discovered_urls' );

	if ( is_array( $cached ) && isset( $cached['urls'], $cached['limit'] ) && (int) $cached['limit'] >= $limit ) {
		return array_slice( (array) $cached['urls'], 0, $limit );
	}

	$home_url = home_url( '/' );
	$result   = wp_remote_get(
		$home_url,
		array(
			'blocking'    => true,
			'timeout'     => 8,
			'redirection' => 3,
			'sslverify'   => scm_preload_sslverify( $home_url ),
			'user-agent'  => 'AMS Cache Preload Crawler/' . SCM_PLUGIN_VERSION,
		)
	);

	if ( is_wp_error( $result ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$status = (int) wp_remote_retrieve_response_code( $result );

	if ( $status < 200 || $status >= 400 ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$html = wp_remote_retrieve_body( $result );

	if ( empty( $html ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	preg_match_all( '/<a\b[^>]*\shref\s*=\s*(["\'])(.*?)\1/i', $html, $matches );

	if ( empty( $matches[2] ) ) {
		set_transient( 'scm_homepage_discovered_urls', array( 'urls' => array(), 'limit' => $limit ), MINUTE_IN_SECONDS );
		return array();
	}

	$urls = array();

	foreach ( $matches[2] as $href ) {
		$url = scm_normalize_homepage_link_url( $href, $home_url );

		if ( '' === $url || in_array( $url, $urls, true ) ) {
			continue;
		}

		$urls[] = $url;

		if ( count( $urls ) >= $limit ) {
			break;
		}
	}

	set_transient(
		'scm_homepage_discovered_urls',
		array(
			'urls'  => $urls,
			'limit' => $limit,
		),
		5 * MINUTE_IN_SECONDS
	);

	return apply_filters( 'scm_homepage_discovered_preload_urls', $urls, $limit );
}

/**
 * Fallback priority URLs when the homepage crawler cannot see internal links.
 *
 * @param int   $limit         Maximum URLs.
 * @param array $existing_urls URLs already selected.
 *
 * @return array
 */
function scm_get_homepage_priority_fallback_urls( $limit = 100, $existing_urls = array() ) {
	$limit = scm_get_preload_limit( $limit );

	if ( $limit <= 0 ) {
		return array();
	}

	$existing_map = array();

	foreach ( (array) $existing_urls as $url ) {
		$existing_map[ scm_normalize_cache_uri( $url ) ] = true;
	}

	$urls  = array();
	$posts = get_posts(
		array(
			'post_type'           => scm_get_preload_post_types(),
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => false,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);

	foreach ( $posts as $post_id ) {
		$url = get_permalink( $post_id );
		$key = scm_normalize_cache_uri( $url );

		if ( empty( $url ) || isset( $existing_map[ $key ] ) ) {
			continue;
		}

		$existing_map[ $key ] = true;
		$urls[]              = $url;

		if ( count( $urls ) >= $limit ) {
			break;
		}
	}

	return apply_filters( 'scm_homepage_priority_fallback_urls', $urls, $limit, $existing_urls );
}

/**
 * Get homepage and same-site links discovered from homepage.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_homepage_priority_preload_urls( $limit = null ) {
	$limit = scm_get_preload_limit( $limit );
	$urls  = array();

	if ( 'yes' === get_option( 'scm_option_post_homepage', 'yes' ) ) {
		$urls[] = home_url( '/' );
	}

	if ( 'yes' === get_option( 'scm_option_preload_homepage_links', 'yes' ) && count( $urls ) < $limit ) {
		$discovered_urls = scm_get_homepage_discovered_urls( $limit - count( $urls ) );

		foreach ( $discovered_urls as $url ) {
			$urls[] = $url;

			if ( count( $urls ) >= $limit ) {
				break;
			}
		}

		$minimum_discovered = (int) apply_filters( 'scm_homepage_priority_min_discovered_urls', 25 );

		if ( count( $discovered_urls ) < $minimum_discovered && count( $urls ) < $limit ) {
			foreach ( scm_get_homepage_priority_fallback_urls( $limit - count( $urls ), $urls ) as $url ) {
				$urls[] = $url;

				if ( count( $urls ) >= $limit ) {
					break;
				}
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_homepage_priority_preload_urls', $urls, $limit ), 0, $limit );
}

/**
 * Warm homepage and homepage-discovered links first.
 *
 * @param int|null $limit    Maximum URLs.
 * @param bool     $blocking Use blocking requests.
 *
 * @return int
 */
function scm_preload_homepage_priority_urls( $limit = null, $blocking = true ) {
	if ( ! scm_is_preload_enabled() || 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return 0;
	}

	$urls           = scm_get_homepage_priority_preload_urls( $limit );
	$dispatch_limit = count( $urls );

	if ( $blocking ) {
		$dispatch_limit = min( $dispatch_limit, (int) apply_filters( 'scm_homepage_priority_blocking_limit', 1 ) );
	}

	foreach ( array_slice( $urls, 0, $dispatch_limit ) as $url ) {
		scm_preload_url( $url, $blocking, $blocking ? 8 : 1 );
	}

	update_option( 'scm_last_homepage_priority_preload_time', time() );
	update_option( 'scm_last_homepage_priority_preload_count', count( $urls ) );

	return $dispatch_limit;
}

/**
 * Build a short list of homepage and archive URLs to warm immediately.
 *
 * @param int $post_id Optional affected post ID for related archives.
 * @param int $limit   Maximum URLs.
 *
 * @return array
 */
function scm_get_critical_preload_urls( $post_id = 0, $limit = 25 ) {
	$limit = max( 1, min( 100, (int) $limit ) );
	$urls  = array();
	$post  = $post_id ? get_post( $post_id ) : null;

	$add_url = function ( $url ) use ( &$urls, $limit ) {
		if ( count( $urls ) >= $limit || empty( $url ) ) {
			return;
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $url ) ) {
			return;
		}

		$urls[] = (string) $url;
	};

	if ( 'yes' === get_option( 'scm_option_post_homepage', 'yes' ) ) {
		$add_url( home_url( '/' ) );
	}

	$post_archives = (array) get_option( 'scm_option_post_archives', array() );

	if ( $post ) {
		if ( isset( $post_archives['category'] ) && 'yes' === $post_archives['category'] ) {
			foreach ( wp_get_post_categories( $post->ID ) as $term_id ) {
				$add_url( get_category_link( (int) $term_id ) );
			}
		}

		if ( isset( $post_archives['tag'] ) && 'yes' === $post_archives['tag'] ) {
			foreach ( wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) ) as $term_id ) {
				$add_url( get_tag_link( (int) $term_id ) );
			}
		}

		if ( isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
			$add_url( get_author_posts_url( (int) $post->post_author ) );
		}

		if ( isset( $post_archives['date'] ) && 'yes' === $post_archives['date'] ) {
			$timestamp = strtotime( $post->post_date );

			if ( $timestamp ) {
				$add_url( get_year_link( (int) gmdate( 'Y', $timestamp ) ) );
				$add_url( get_month_link( (int) gmdate( 'Y', $timestamp ), (int) gmdate( 'm', $timestamp ) ) );
			}
		}

		$archive_key = 'archive_' . $post->post_type;

		if ( isset( $post_archives[ $archive_key ] ) && 'yes' === $post_archives[ $archive_key ] ) {
			$add_url( get_post_type_archive_link( $post->post_type ) );
		}

		if ( 'yes' === get_option( 'scm_option_woocommerce_status', 'no' ) && 'product' === $post->post_type ) {
			$woocommerce_archives = (array) get_option( 'scm_option_woocommerce_post_archives', array() );

			foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
				if ( isset( $woocommerce_archives[ $taxonomy ] ) && 'yes' === $woocommerce_archives[ $taxonomy ] ) {
					foreach ( wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) ) as $term_id ) {
						$add_url( get_term_link( (int) $term_id, $taxonomy ) );
					}
				}
			}
		}
	} else {
		foreach ( array( 'category' => 'get_category_link', 'tag' => 'get_tag_link' ) as $archive => $link_function ) {
			if ( isset( $post_archives[ $archive ] ) && 'yes' === $post_archives[ $archive ] ) {
				$taxonomy = ( 'tag' === $archive ) ? 'post_tag' : 'category';
				$terms    = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => true,
						'fields'     => 'ids',
						'number'     => max( 1, $limit - count( $urls ) ),
					)
				);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term_id ) {
						$add_url( $link_function( (int) $term_id ) );
					}
				}
			}
		}

		if ( isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
			$authors = get_users(
				array(
					'fields'              => 'ID',
					'has_published_posts' => true,
					'number'              => max( 1, $limit - count( $urls ) ),
				)
			);

			foreach ( $authors as $author_id ) {
				$add_url( get_author_posts_url( (int) $author_id ) );
			}
		}

		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects', 'and' ) as $post_type ) {
			$archive_key = 'archive_' . $post_type->name;

			if ( isset( $post_archives[ $archive_key ] ) && 'yes' === $post_archives[ $archive_key ] ) {
				$add_url( get_post_type_archive_link( $post_type->name ) );
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_critical_preload_urls', $urls, $post_id, $limit ), 0, $limit );
}

/**
 * Immediately warm homepage and archive URLs.
 *
 * @param int                            $post_id         Optional published post ID.
 * @param bool                           $delete_existing Delete matching cache keys first.
 * @param \Shieldon\SimpleCache\Cache|null $driver        Cache driver.
 *
 * @return int Number of dispatched URLs.
 */
function scm_preload_critical_urls( $post_id = 0, $delete_existing = true, $driver = null ) {
	if ( 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return 0;
	}

	$limit = (int) apply_filters( 'scm_critical_preload_limit', 25, $post_id );
	$urls  = scm_get_critical_preload_urls( $post_id, $limit );

	if ( empty( $urls ) ) {
		return 0;
	}

	if ( $delete_existing && null === $driver ) {
		$driver = scm_driver_factory( get_option( 'scm_option_driver', 'file' ) );
	}

	foreach ( $urls as $url ) {
		if ( $delete_existing && $driver ) {
			$path = parse_url( $url, PHP_URL_PATH );
			$path = empty( $path ) ? '/' : $path;

			scm_purge_cache_uri( $path, $driver );
		}

		scm_preload_url( $url, true, 8 );
	}

	update_option( 'scm_last_critical_preload_time', time() );
	update_option( 'scm_last_critical_preload_count', count( $urls ) );

	return count( $urls );
}

/**
 * Build URL list for preload.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return array
 */
function scm_get_preload_urls( $limit = null, $priority_urls = null ) {
	$limit = scm_get_preload_limit( $limit );
	$urls  = is_array( $priority_urls ) ? $priority_urls : scm_get_homepage_priority_preload_urls( $limit );

	$post_types = scm_get_preload_post_types();

	if ( ! empty( $post_types ) && count( $urls ) < $limit ) {
		$remaining = $limit - count( $urls );
		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $remaining,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $posts as $post_id ) {
			$urls[] = get_permalink( $post_id );

			if ( count( $urls ) >= $limit ) {
				break;
			}
		}
	}

	$post_archives = (array) get_option( 'scm_option_post_archives', array() );

	if ( count( $urls ) < $limit && isset( $post_archives['category'] ) && 'yes' === $post_archives['category'] ) {
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$urls[] = get_category_link( $term_id );
			}
		}
	}

	if ( count( $urls ) < $limit && isset( $post_archives['tag'] ) && 'yes' === $post_archives['tag'] ) {
		$terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$urls[] = get_tag_link( $term_id );
			}
		}
	}

	if ( count( $urls ) < $limit && isset( $post_archives['author'] ) && 'yes' === $post_archives['author'] ) {
		$authors = get_users( array( 'has_published_posts' => true, 'number' => $limit - count( $urls ) ) );

		foreach ( $authors as $author ) {
			$urls[] = get_author_posts_url( $author->ID );
		}
	}

	foreach ( $post_archives as $archive => $enabled ) {
		if ( count( $urls ) >= $limit ) {
			break;
		}

		if ( 'yes' === $enabled && 0 === strpos( $archive, 'archive_' ) ) {
			$post_type = substr( $archive, 8 );
			$url       = get_post_type_archive_link( $post_type );

			if ( $url ) {
				$urls[] = $url;
			}
		}
	}

	$woocommerce_archives = (array) get_option( 'scm_option_woocommerce_post_archives', array() );

	foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
		if ( count( $urls ) >= $limit ) {
			break;
		}

		if ( isset( $woocommerce_archives[ $taxonomy ] ) && 'yes' === $woocommerce_archives[ $taxonomy ] ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'fields' => 'ids', 'number' => $limit - count( $urls ) ) );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					$url = get_term_link( (int) $term_id, $taxonomy );

					if ( ! is_wp_error( $url ) ) {
						$urls[] = $url;
					}
				}
			}
		}
	}

	$urls = array_values( array_unique( array_filter( $urls ) ) );

	return array_slice( apply_filters( 'scm_preload_urls', $urls, $limit ), 0, $limit );
}

/**
 * Dispatch preload requests.
 *
 * @param int|null $limit Maximum URLs.
 *
 * @return int Number of queued URLs.
 */
function scm_preload_cache( $limit = null ) {
	if ( ! scm_is_preload_enabled() || 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return 0;
	}

	$limit         = scm_get_preload_limit( $limit );
	delete_transient( 'scm_homepage_discovered_urls' );
	$priority_urls = scm_get_homepage_priority_preload_urls( $limit );
	$urls          = scm_get_preload_urls( $limit, $priority_urls );

	update_option( 'scm_last_preload_time', time() );
	update_option( 'scm_last_preload_count', count( $urls ) );
	update_option( 'scm_last_preload_priority_count', count( $priority_urls ) );
	update_option( 'scm_preload_queue_total', count( $urls ) );
	update_option( 'scm_preload_queue_processed', 0 );
	update_option( 'scm_preload_queue_failed', 0 );
	update_option( 'scm_preload_queue_remaining', count( $urls ) );
	update_option( 'scm_preload_queue_started_time', time() );
	update_option( 'scm_preload_queue_finished_time', 0 );

	set_transient( 'scm_preload_queue', $urls, 12 * HOUR_IN_SECONDS );

	scm_process_preload_queue();

	return count( $urls );
}

/**
 * Process one preload queue batch.
 *
 * @return int Number of dispatched URLs.
 */
function scm_process_preload_queue() {
	if ( ! scm_is_preload_enabled() || 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		delete_transient( 'scm_preload_queue' );
		update_option( 'scm_preload_queue_remaining', 0 );
		return 0;
	}

	$queue = get_transient( 'scm_preload_queue' );

	if ( empty( $queue ) || ! is_array( $queue ) ) {
		delete_transient( 'scm_preload_queue' );
		update_option( 'scm_preload_queue_remaining', 0 );
		return 0;
	}

	$queue      = array_values( array_unique( array_filter( $queue ) ) );
	$batch_size = scm_get_preload_batch_size();
	$batch      = array_slice( $queue, 0, $batch_size );
	$remaining  = array_slice( $queue, count( $batch ) );
	$processed  = (int) get_option( 'scm_preload_queue_processed', 0 );
	$failed     = (int) get_option( 'scm_preload_queue_failed', 0 );

	foreach ( $batch as $url ) {
		$response = scm_preload_url( $url, true, 8 );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body     = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 400 || empty( $body ) || false === strpos( $body, '</body>' ) || strlen( $body ) < 1024 ) {
			$failed++;
			continue;
		}

		$processed++;
	}

	update_option( 'scm_preload_queue_processed', $processed );
	update_option( 'scm_preload_queue_failed', $failed );
	update_option( 'scm_preload_queue_remaining', count( $remaining ) );

	if ( empty( $remaining ) ) {
		delete_transient( 'scm_preload_queue' );
		update_option( 'scm_preload_queue_finished_time', time() );
		return count( $batch );
	}

	set_transient( 'scm_preload_queue', $remaining, 12 * HOUR_IN_SECONDS );
	scm_schedule_preload_queue();

	return count( $batch );
}

if ( function_exists( 'add_action' ) ) {
	// Recover the preload event after deployments that keep the option enabled.
	add_action( 'init', 'scm_maybe_schedule_preload_cache', 20 );
	add_action( 'scm_preload_cache_event', 'scm_preload_cache' );
	add_action( 'scm_preload_queue_event', 'scm_process_preload_queue' );
}
