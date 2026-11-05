<?php
/**
 * KH Speed Engine - Post update hooks.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

add_action( 'save_post', 'khse_update_post', 10, 3 );
add_action( 'transition_post_status', 'khse_update_post_status', 10, 3 );
add_action( 'wp_trash_post', 'khse_purge_post_before_trash', 10, 2 );
add_action( 'before_delete_post', 'khse_delete_post', 10, 2 );
add_action( 'set_object_terms', 'khse_update_post_terms', 10, 6 );
add_filter( 'post_updated_messages', 'khse_notice_after_update_post' );

/**
 * Delete the cache of the post that is just updated.
 *
 * @return void
 */
function khse_update_post( $post_ID, $post, $update ) {

	$option_caching_status = get_option( 'khse_option_caching_status', 'disable' );

	if ( 'enable' === $option_caching_status ) {
		if ( wp_is_post_revision( $post_ID ) || wp_is_post_autosave( $post_ID ) ) {
			return;
		}

		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! empty( $GLOBALS['khse_recently_published_post_ids'][ $post_ID ] ) ) {
			unset( $GLOBALS['khse_recently_published_post_ids'][ $post_ID ] );
			return;
		}

		$post_type = get_post_type_object( $post->post_type );

		if ( ! $post_type || ! $post_type->public ) {
			return;
		}

		$driver_type = get_option( 'khse_option_driver' );
		$driver      = khse_driver_factory( $driver_type );

		if ( is_wp_error( $driver ) ) {
			$driver = null;
		}

		khse_purge_post_cache_urls( $post_ID, $post, $driver );
		khse_queue_critical_preload_urls( $post_ID );
	}
}

/**
 * Purge homepage and archive cache when a post becomes public.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 *
 * @return void
 */
function khse_update_post_status( $new_status, $old_status, $post ) {
	if ( 'enable' !== get_option( 'khse_option_caching_status', 'disable' ) ) {
		return;
	}

	if ( ! $post || wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return;
	}

	$driver = khse_driver_factory( get_option( 'khse_option_driver' ) );

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	if ( 'publish' === $old_status && 'publish' !== $new_status ) {
		khse_purge_post_cache_urls( $post->ID, $post, $driver );
		khse_queue_critical_preload_urls( $post->ID );
		return;
	}

	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	$GLOBALS['khse_recently_published_post_ids'][ $post->ID ] = true;

	khse_purge_post_cache_urls( $post->ID, $post, $driver );
	khse_queue_critical_preload_urls( $post->ID );
}

/**
 * Purge a published post before WordPress changes its status to trash.
 *
 * @param int    $post_ID         Post ID.
 * @param string $previous_status Previous post status.
 *
 * @return void
 */
function khse_purge_post_before_trash( $post_ID, $previous_status = '' ) {
	if ( '' === $previous_status ) {
		$post            = get_post( $post_ID );
		$previous_status = $post ? $post->post_status : '';
	}

	if ( 'enable' !== get_option( 'khse_option_caching_status', 'disable' ) || 'publish' !== $previous_status ) {
		return;
	}

	$post   = get_post( $post_ID );
	$driver = khse_driver_factory( get_option( 'khse_option_driver', 'file' ) );

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	khse_purge_post_cache_urls( $post_ID, $post, $driver );
	khse_queue_critical_preload_urls( $post_ID );
}

/**
 * Purge the deleted post and affected archive cache before permanent deletion.
 *
 * @param int     $post_ID Post ID.
 * @param WP_Post $post    Post object.
 *
 * @return void
 */
function khse_delete_post( $post_ID, $post = null ) {
	if ( 'enable' !== get_option( 'khse_option_caching_status', 'disable' ) ) {
		return;
	}

	if ( ! $post ) {
		$post = get_post( $post_ID );
	}

	if ( ! $post ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return;
	}

	$driver = khse_driver_factory( get_option( 'khse_option_driver' ) );

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	khse_purge_post_cache_urls( $post_ID, $post, $driver );
	khse_queue_critical_preload_urls( $post_ID );
}

/**
 * Purge post-related caches when terms are assigned after the post is saved.
 *
 * The block editor can create the post before assigning categories/tags, so
 * save_post alone is not enough to invalidate term archives.
 *
 * @param int    $object_id  Post ID.
 * @param array  $terms      Term IDs or slugs.
 * @param array  $tt_ids     Term taxonomy IDs.
 * @param string $taxonomy   Taxonomy name.
 * @param bool   $append     Whether terms were appended.
 * @param array  $old_tt_ids Previous term taxonomy IDs.
 *
 * @return void
 */
function khse_update_post_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
	if ( 'enable' !== get_option( 'khse_option_caching_status', 'disable' ) ) {
		return;
	}

	$post = get_post( $object_id );

	if ( ! $post || 'publish' !== $post->post_status || wp_is_post_revision( $object_id ) || wp_is_post_autosave( $object_id ) ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return;
	}

	$driver = khse_driver_factory( get_option( 'khse_option_driver', 'file' ) );

	if ( is_wp_error( $driver ) ) {
		$driver = null;
	}

	khse_purge_post_cache_urls( $object_id, $post, $driver );
	khse_queue_critical_preload_urls( $object_id );
}

/**
 * Display message after updating post.
 *
 * @param array $messages The messages.
 * @return array
 */
function khse_notice_after_update_post( $messages ) {

	$option_caching_status = get_option( 'khse_option_caching_status', 'disable' );

	if ( 'enable' === $option_caching_status ) {
		$custom  = '</div>';
		$custom .= '<div class="notice notice-warning is-dismissible"><p>';
		$custom .= '<strong>' . __( 'KH Speed Engine', 'kh-speed-engine' ) . '</strong>: ' . __( 'Affected cache has been purged.', 'kh-speed-engine' );
		$custom .= '</p></div>';
		$custom .= '<div>';

		$messages['post'][1] = $messages['post'][1] . $custom;
		$messages['page'][1] = $messages['page'][1] . $custom;
	}

	return $messages;
}
