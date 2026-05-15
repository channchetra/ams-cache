<?php
/**
 * AMS Cache - Post Archives
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.3.0
 * @version 1.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_post_archives = get_option( 'scm_option_post_archives' );

$option_list = array(
	'category' => __( 'Category', 'ams-cache' ),
	'tag'      => __( 'Tag', 'ams-cache' ),
	'date'     => __( 'Date', 'ams-cache' ),
	'author'   => __( 'Author', 'ams-cache' ),
);

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
	$option_list[ 'archive_' . $post_type->name ] = sprintf(
		/* translators: %s is the post type singular label. */
		__( 'Archive for %s', 'ams-cache' ),
		$post_type->labels->singular_name
	);
}

?>

<div>
	<?php foreach ( $option_list as $k => $v ) : ?>
	<div class="scm-option-item">
		<input type="checkbox" name="scm_option_post_archives[<?php echo $k; ?>]" id="ams-cache-post-archive-option-<?php echo $k; ?>" value="yes" 
			<?php if ( isset( $option_post_archives[ $k ] ) ) : ?>
				<?php checked( $option_post_archives[ $k ], 'yes' ); ?>
			<?php endif; ?>
		>
		<label for="ams-cache-post-archive-option-<?php echo $k; ?>">
			<?php echo $v; ?><br />
		<label>
	</div>
	<?php endforeach; ?>
</div>
<p><em><?php _e( 'What type of archive page do you like to cache?', 'ams-cache' ); ?></em></p>
