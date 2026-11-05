<?php
/**
 * KH Speed Engine — cache/cache-stats.php
 *
 * Cache statistics index paths, readers, and type catalog.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
    die;
}

/**
 * Get the cache stats root directory.
 *
 * @return string
 */
function khse_get_stats_root() {
	return khse_get_upload_dir() . '/stats';
}

/**
 * Get the path of statistics directory.
 *
 * @param string $cache_type
 *
 * @return string
 */
function khse_get_stats_dir( $cache_type = 'post' ) {
	return khse_get_stats_root() . '/' . $cache_type;
}

/**
 * Read one stats row.
 *
 * @param string $path Stats JSON path.
 *
 * @return array{size:int,uri:string}|null Null when the file is missing or corrupt.
 */
function khse_read_stats_file( $path ) {
	$content = file_exists( $path ) ? file_get_contents( $path ) : '';
	$data    = json_decode( (string) $content, true );

	if ( ! is_array( $data ) ) {
		return null;
	}

	return array(
		'size' => isset( $data['size'] ) ? (int) $data['size'] : 0,
		'uri'  => isset( $data['uri'] ) ? (string) $data['uri'] : '',
	);
}

/**
 * Get the cache type list.
 *
 * @return array
 */
function khse_get_cache_type_labels() {
	$list = array(
		'homepage' => __( 'Homepage', 'kh-speed-engine' ),
		'post'     => __( 'Post', 'kh-speed-engine' ),
		'page'     => __( 'Page', 'kh-speed-engine' ),
	);

	$custom_post_types = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects',
		'and'
	);

	foreach ( $custom_post_types as $post_type ) {
		$list[ $post_type->name ] = $post_type->labels->singular_name;
	}

	$list['category'] = __( 'Category', 'kh-speed-engine' );
	$list['tag']      = __( 'Tag', 'kh-speed-engine' );
	$list['date']     = __( 'Date', 'kh-speed-engine' );
	$list['author']   = __( 'Author', 'kh-speed-engine' );

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
		$list[ 'archive_' . $post_type->name ] = sprintf(
			/* translators: %s is the post type singular label. */
			__( 'Archive for %s', 'kh-speed-engine' ),
			$post_type->labels->singular_name
		);
	}

	$list['uncategorised'] = __( 'Uncategorised', 'kh-speed-engine' );

	return $list;
}

/**
 * Get the cache type keys including public post types and archives.
 *
 * @return array
 */
function khse_get_cache_type_keys() {
	return array_keys( khse_get_cache_type_labels() );
}
