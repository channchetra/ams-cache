<?php
/**
 * AMS Cache - Setting page.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.5.2
 * @version 1.5.2
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

add_action( 'admin_bar_menu', 'scm_button_clear_cache', 999 );
add_action( 'admin_footer', 'scm_footer_js_clear_cache' );
add_action( 'wp_footer', 'scm_footer_js_clear_cache' );

/**
 * Add a clear cache button on admin bar.
 *
 * @param object $admin_bar
 *
 * @return void
 */
function scm_button_clear_cache( $admin_bar ) {
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		$admin_bar->add_menu(
			array(
				'id'    => 'scm-clear-cache',
				'title' => '<span class="ab-icon dashicons dashicons-trash" style="top: 2px"></span><span class="ab-label">' . __( 'Clear Cache', 'ams-cache' ) . '</span>',
				'href'  => '#',
			)
		);
	}

	if ( ! is_admin() && current_user_can( 'edit_posts' ) ) {
		$admin_bar->add_menu(
			array(
				'id'    => 'scm-purge-current-page',
				'title' => '<span class="ab-icon dashicons dashicons-update" style="top: 2px"></span><span class="ab-label">' . __( 'Purge Current Page', 'ams-cache' ) . '</span>',
				'href'  => '#',
			)
		);
	}
}

/**
 * Ajax for the action of the clear cache button.
 *
 * @return void
 */
function scm_footer_js_clear_cache() {
	if ( ! is_admin_bar_showing() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) ) {
		return;
	}

	?>
	<script>
		(function($) {
			$(function() {
				var scmAjaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

				$('li#wp-admin-bar-scm-clear-cache .ab-item').on('click', function(event) {
					event.preventDefault();

					var data = {
						'action': 'scm_action_clear_cache',
						'_wpnonce': '<?php echo wp_create_nonce( 'scm_clear_cache_' . scm_get_dir_hash() ); ?>'
					};

					$.post(scmAjaxUrl, data, function(response) {
						alert(response);
					});
				});

				$('li#wp-admin-bar-scm-purge-current-page .ab-item').on('click', function(event) {
					event.preventDefault();

					var data = {
						'action': 'scm_action_purge_current_page',
						'_wpnonce': '<?php echo wp_create_nonce( 'scm_purge_current_page_' . scm_get_dir_hash() ); ?>',
						'url': window.location.href
					};

					$.post(scmAjaxUrl, data, function(response) {
						alert(response);
					});
				});
			});
		})(jQuery);
	</script> 
	<?php
}
