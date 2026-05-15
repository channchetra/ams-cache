<?php
/**
 * AMS Cache - Cache key prefix.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.2.0
 * @version 2.2.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_cache_key_prefix = get_option( 'scm_option_cache_key_prefix', '' );
$default_prefix          = 'scm_' . scm_get_blog_id() . '_' . scm_get_dir_hash() . '_';

if ( '' === $option_cache_key_prefix ) {
	$option_cache_key_prefix = $default_prefix;
}

?>

<div>
	<div>
		<input type="text" name="scm_option_cache_key_prefix" class="regular-text" value="<?php echo esc_attr( $option_cache_key_prefix ); ?>">
	</div>
</div>
<p><em><?php _e( 'Use a unique prefix per site when sharing Redis, Memcached, APC, APCu, WinCache, MongoDB, or MySQL cache stores.', 'ams-cache' ); ?></em></p>
