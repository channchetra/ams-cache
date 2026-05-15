<?php
/**
 * AMS Cache - Nginx direct cache.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.2.0
 * @version 2.2.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_nginx_direct_cache_status = get_option( 'scm_option_nginx_direct_cache_status', 'no' );
$requirements                     = scm_get_nginx_direct_cache_requirements();
$snippet                          = scm_get_nginx_direct_cache_snippet();

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_nginx_direct_cache_status" id="ams-cache-nginx-direct-cache-option-yes" value="yes"
			<?php checked( $option_nginx_direct_cache_status, 'yes' ); ?>>
		<label for="ams-cache-nginx-direct-cache-option-yes">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_nginx_direct_cache_status" id="ams-cache-nginx-direct-cache-option-no" value="no"
			<?php checked( $option_nginx_direct_cache_status, 'no' ); ?>>
		<label for="ams-cache-nginx-direct-cache-option-no">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>
</div>

<ul class="ams-option-list">
	<?php foreach ( $requirements as $requirement ) : ?>
		<li>
			<strong><?php echo $requirement['passed'] ? esc_html__( 'Passed', 'ams-cache' ) : esc_html__( 'Check', 'ams-cache' ); ?></strong>
			<span><?php echo esc_html( $requirement['label'] ); ?></span>
			<code><?php echo esc_html( $requirement['detail'] ); ?></code>
		</li>
	<?php endforeach; ?>
</ul>

<p><em><?php _e( 'This creates a raw HTML mirror for File driver cache. Nginx can serve it before PHP, and the snippet also adds gzip plus long browser cache headers for static assets. You must install the snippet manually and test your Nginx config first.', 'ams-cache' ); ?></em></p>
<p><em><?php _e( 'Security: logged-in, WooCommerce cart/session, query string, admin, REST, XML-RPC, and non-GET requests are bypassed in the generated snippet.', 'ams-cache' ); ?></em></p>

<textarea class="large-text code" rows="18" readonly><?php echo esc_textarea( $snippet ); ?></textarea>
