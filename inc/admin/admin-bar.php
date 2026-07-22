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
		(function() {
			var scmAjaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var scmActions = {
				'#wp-admin-bar-scm-clear-cache .ab-item': {
					action: 'scm_action_clear_cache',
					nonce: <?php echo wp_json_encode( wp_create_nonce( 'scm_clear_cache_' . scm_get_dir_hash() ) ); ?>
				},
				'#wp-admin-bar-scm-purge-current-page .ab-item': {
					action: 'scm_action_purge_current_page',
					nonce: <?php echo wp_json_encode( wp_create_nonce( 'scm_purge_current_page_' . scm_get_dir_hash() ) ); ?>
				}
			};

			document.addEventListener('click', function(event) {
				Object.keys(scmActions).some(function(selector) {
					var target = event.target.closest ? event.target.closest(selector) : null;

					if (!target) {
						return false;
					}

					event.preventDefault();
					var action = scmActions[selector];
					var data = new URLSearchParams({action: action.action, _wpnonce: action.nonce});

					if ('scm_action_purge_current_page' === action.action) {
						data.set('url', window.location.href);
					}

					fetch(scmAjaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
						body: data.toString()
					})
						.then(function(response) { return response.json(); })
						.then(function(response) {
							var payload = response && response.data ? response.data : response;
							alert(payload && payload.message ? payload.message : 'AMS Cache request completed.');
						})
						.catch(function() { alert('AMS Cache request failed.'); });

					return true;
				});
			});
		})();
	</script> 
	<?php
}
