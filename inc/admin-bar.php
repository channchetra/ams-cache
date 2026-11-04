<?php
/**
 * KH Speed Engine - Admin bar.
 *
 * @package   KH Speed Engine
 * @license   GPLv3 (or later)
 */

if ( ! defined( 'KHSE_INC' ) ) {
	die;
}

add_action( 'admin_bar_menu', 'khse_button_clear_cache', 999 );
add_action( 'admin_footer', 'khse_footer_js_clear_cache' );
add_action( 'wp_footer', 'khse_footer_js_clear_cache' );

/**
 * Add a clear cache button on admin bar.
 *
 * @param object $admin_bar
 *
 * @return void
 */
function khse_button_clear_cache( $admin_bar ) {
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		$admin_bar->add_menu(
			array(
				'id'    => 'khse-clear-cache',
				'title' => '<span class="ab-icon dashicons dashicons-trash" style="top: 2px"></span><span class="ab-label">' . __( 'Clear Cache', 'kh-speed-engine' ) . '</span>',
				'href'  => '#',
			)
		);
	}

	if ( ! is_admin() && current_user_can( 'edit_posts' ) ) {
		$admin_bar->add_menu(
			array(
				'id'    => 'khse-purge-current-page',
				'title' => '<span class="ab-icon dashicons dashicons-update" style="top: 2px"></span><span class="ab-label">' . __( 'Purge Current Page', 'kh-speed-engine' ) . '</span>',
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
function khse_footer_js_clear_cache() {
	if ( ! is_admin_bar_showing() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) ) {
		return;
	}

	?>
	<script>
		(function() {
			var khseAjaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var khseActions = {
				'#wp-admin-bar-khse-clear-cache .ab-item': {
					action: 'khse_action_clear_cache',
					nonce: <?php echo wp_json_encode( wp_create_nonce( 'khse_clear_cache_' . khse_get_dir_hash() ) ); ?>
				},
				'#wp-admin-bar-khse-purge-current-page .ab-item': {
					action: 'khse_action_purge_current_page',
					nonce: <?php echo wp_json_encode( wp_create_nonce( 'khse_purge_current_page_' . khse_get_dir_hash() ) ); ?>
				}
			};

			document.addEventListener('click', function(event) {
				Object.keys(khseActions).some(function(selector) {
					var target = event.target.closest ? event.target.closest(selector) : null;

					if (!target) {
						return false;
					}

					event.preventDefault();
					var action = khseActions[selector];
					var data = new URLSearchParams({action: action.action, _wpnonce: action.nonce});

					if ('khse_action_purge_current_page' === action.action) {
						data.set('url', window.location.href);
					}

					fetch(khseAjaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
						body: data.toString()
					})
						.then(function(response) { return response.json(); })
						.then(function(response) {
							var payload = response && response.data ? response.data : response;
							alert(payload && payload.message ? payload.message : 'KH Speed Engine request completed.');
						})
						.catch(function() { alert('KH Speed Engine request failed.'); });

					return true;
				});
			});
		})();
	</script> 
	<?php
}
