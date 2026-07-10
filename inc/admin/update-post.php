<?php
/**
 * AMS Cache - Update post.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

add_action( 'save_post', 'scm_update_post', 10, 3 );
add_action( 'transition_post_status', 'scm_update_post_status', 10, 3 );
add_action( 'before_delete_post', 'scm_delete_post', 10, 2 );
add_filter( 'post_updated_messages', 'scm_notice_after_update_post' );

/**
 * Delete the cache of the post that is just updated.
 *
 * @return void
 */
function scm_update_post( $post_ID, $post, $update ) {

	$option_caching_status = get_option( 'scm_option_caching_status', 'disable' );

	if ( 'enable' === $option_caching_status ) {
		if ( wp_is_post_revision( $post_ID ) || wp_is_post_autosave( $post_ID ) ) {
			return;
		}

		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! empty( $GLOBALS['scm_recently_published_post_ids'][ $post_ID ] ) ) {
			unset( $GLOBALS['scm_recently_published_post_ids'][ $post_ID ] );
			return;
		}

		$post_type = get_post_type_object( $post->post_type );

		if ( ! $post_type || ! $post_type->public ) {
			return;
		}

		$post_url    = get_permalink( $post_ID );
		$driver_type = get_option( 'scm_option_driver' );
		$driver      = scm_driver_factory( $driver_type );

		scm_purge_cache_uri( parse_url( $post_url, PHP_URL_PATH ), $driver );
		scm_preload_critical_urls( $post_ID, true, $driver );
		scm_preload_url( $post_url );
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
function scm_update_post_status( $new_status, $old_status, $post ) {
	if ( 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return;
	}

	if ( ! $post || wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return;
	}

	$driver = scm_driver_factory( get_option( 'scm_option_driver' ) );
	$post_url = get_permalink( $post->ID );

	if ( 'publish' === $old_status && 'publish' !== $new_status ) {
		if ( $post_url ) {
			scm_purge_cache_uri( parse_url( $post_url, PHP_URL_PATH ), $driver );
		}

		scm_preload_critical_urls( 0, true, $driver );
		return;
	}

	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	$GLOBALS['scm_recently_published_post_ids'][ $post->ID ] = true;

	if ( $post_url ) {
		scm_purge_cache_uri( parse_url( $post_url, PHP_URL_PATH ), $driver );
	}

	scm_preload_critical_urls( $post->ID, true, $driver );
}

/**
 * Purge homepage and archive cache before a public post is deleted.
 *
 * @param int     $post_ID Post ID.
 * @param WP_Post $post    Post object.
 *
 * @return void
 */
function scm_delete_post( $post_ID, $post = null ) {
	if ( 'enable' !== get_option( 'scm_option_caching_status', 'disable' ) ) {
		return;
	}

	if ( ! $post ) {
		$post = get_post( $post_ID );
	}

	if ( ! $post || 'publish' !== $post->post_status ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return;
	}

	$driver = scm_driver_factory( get_option( 'scm_option_driver' ) );
	$post_url = get_permalink( $post_ID );

	if ( $post_url ) {
		scm_purge_cache_uri( parse_url( $post_url, PHP_URL_PATH ), $driver );
	}

	scm_preload_critical_urls( $post_ID, true, $driver );
}

/**
 * Display message after updating post.
 *
 * @param array $messages The messages.
 * @return void
 */
function scm_notice_after_update_post( $messages ) {

	$option_caching_status = get_option( 'scm_option_caching_status', 'disable' );

	if ( 'enable' === $option_caching_status ) {
		$custom  = '</div>';
		$custom .= '<div class="notice notice-warning is-dismissible"><p>';
		$custom .= '<strong>' . __( 'AMS Cache', 'ams-cache' ) . '</strong>: ' . __( 'Affected cache has been purged.', 'ams-cache' );
		$custom .= '</p></div>';
		$custom .= '<div>';

		$messages['post'][1] = $messages['post'][1] . $custom;
		$messages['page'][1] = $messages['page'][1] . $custom;
	}

	return $messages;
}
