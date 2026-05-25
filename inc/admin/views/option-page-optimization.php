<?php
/**
 * AMS Cache - Page optimization.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.3.0
 * @version 2.3.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$settings     = scm_get_page_optimization_settings();
$requirements = scm_get_page_optimization_requirements();

$toggles = array(
	'status'            => __( 'Enable page optimization', 'ams-cache' ),
	'minify_html'       => __( 'Minify HTML', 'ams-cache' ),
	'remove_comments'   => __( 'Remove safe HTML comments', 'ams-cache' ),
	'minify_inline_css' => __( 'Minify inline CSS blocks', 'ams-cache' ),
	'lazy_media'        => __( 'Lazy load images and iframes', 'ams-cache' ),
	'critical_images'   => __( 'Prioritize first image for LCP', 'ams-cache' ),
	'preconnect_fonts'  => __( 'Preconnect Google Fonts', 'ams-cache' ),
	'defer_js'          => __( 'Defer JavaScript files', 'ams-cache' ),
	'external_ucss'     => __( 'External UCSS Generation', 'ams-cache' ),
	'local_ucss'        => __( 'Local UCSS Generation', 'ams-cache' ),
	'js_analysis'       => __( 'JS Analysis', 'ams-cache' ),
	'image_optimization' => __( 'Image Optimization', 'ams-cache' ),
	'image_optimize_on_upload' => __( 'Optimize images on upload', 'ams-cache' ),
	'image_rewrite_html' => __( 'Serve generated images in HTML', 'ams-cache' ),
	'image_remote_rewrite' => __( 'Allow offloaded remote URL rewrite', 'ams-cache' ),
);

?>

<ul class="ams-option-list">
	<?php foreach ( $requirements as $requirement ) : ?>
		<li>
			<strong><?php echo $requirement['passed'] ? esc_html__( 'Passed', 'ams-cache' ) : esc_html__( 'Check', 'ams-cache' ); ?></strong>
			<span><?php echo esc_html( $requirement['label'] ); ?></span>
			<code><?php echo esc_html( $requirement['detail'] ); ?></code>
		</li>
	<?php endforeach; ?>
</ul>

<p><em><?php _e( 'Optimization runs only on cacheable guest HTML before AMS Cache stores it in File, Redis, Memcached, APCu, or another selected driver.', 'ams-cache' ); ?></em></p>

<?php foreach ( $toggles as $key => $label ) : ?>
	<div class="scm-option-item">
		<input type="hidden" name="scm_option_page_optimization[<?php echo esc_attr( $key ); ?>]" value="no">
		<input type="checkbox" name="scm_option_page_optimization[<?php echo esc_attr( $key ); ?>]" id="ams-cache-page-optimization-<?php echo esc_attr( $key ); ?>" value="yes"
			<?php checked( $settings[ $key ], 'yes' ); ?>>
		<label for="ams-cache-page-optimization-<?php echo esc_attr( $key ); ?>">
			<?php echo esc_html( $label ); ?>
		</label>
	</div>
<?php endforeach; ?>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-critical-image-count">
		<?php _e( 'Critical image count', 'ams-cache' ); ?>
	</label><br />
	<input type="number" min="0" max="5" step="1" id="ams-cache-page-optimization-critical-image-count" name="scm_option_page_optimization[critical_image_count]" class="small-text" value="<?php echo esc_attr( $settings['critical_image_count'] ); ?>">
</div>
<p><em><?php _e( 'Usually 1 is best. It marks the first non-excluded image as eager/high priority and preloads it for better LCP.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-external-ucss-max-file-size">
		<?php _e( 'External CSS max file size', 'ams-cache' ); ?>
	</label><br />
	<input type="number" min="51200" max="1048576" step="1024" id="ams-cache-page-optimization-external-ucss-max-file-size" name="scm_option_page_optimization[external_ucss_max_file_size]" class="regular-text" value="<?php echo esc_attr( $settings['external_ucss_max_file_size'] ); ?>">
</div>
<p><em><?php _e( 'External UCSS reads only same-site local .css files under WordPress root, skips SRI/crossorigin/preload/alternate/importing stylesheets, rewrites relative asset URLs before inlining, and keeps the original link when the optimizer fails.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-node-path">
		<?php _e( 'Node.js path', 'ams-cache' ); ?>
	</label><br />
	<input type="text" id="ams-cache-page-optimization-node-path" name="scm_option_page_optimization[node_path]" class="regular-text" value="<?php echo esc_attr( $settings['node_path'] ); ?>">
</div>
<div style="margin-top: 12px">
	<label for="ams-cache-page-optimization-purgecss-path">
		<?php _e( 'PurgeCSS path', 'ams-cache' ); ?>
	</label><br />
	<input type="text" id="ams-cache-page-optimization-purgecss-path" name="scm_option_page_optimization[purgecss_path]" class="regular-text" value="<?php echo esc_attr( $settings['purgecss_path'] ); ?>">
</div>
<p><em><?php _e( 'Local UCSS Generation runs PurgeCSS against inline page CSS. External UCSS runs PurgeCSS against eligible same-site stylesheet files. JS Analysis runs local Node.js checks and defers only readable same-site scripts classified safe.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-ucss-safelist">
		<?php _e( 'UCSS safelist', 'ams-cache' ); ?>
	</label><br />
	<textarea id="ams-cache-page-optimization-ucss-safelist" name="scm_option_page_optimization[ucss_safelist]" class="large-text code" rows="6"><?php echo esc_textarea( $settings['ucss_safelist'] ); ?></textarea>
</div>
<p><em><?php _e( 'One selector or class token per line. AMS Cache also protects common WordPress menu, dropdown, Slider Revolution, Slick, and Swiper states even if an older saved setting did not include them.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-media-exclusions">
		<?php _e( 'Media exclusions', 'ams-cache' ); ?>
	</label><br />
	<textarea id="ams-cache-page-optimization-media-exclusions" name="scm_option_page_optimization[media_exclusions]" class="large-text code" rows="5"><?php echo esc_textarea( $settings['media_exclusions'] ); ?></textarea>
</div>
<p><em><?php _e( 'One keyword per line. Matching image or iframe tags are skipped.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-js-exclusions">
		<?php _e( 'JavaScript defer exclusions', 'ams-cache' ); ?>
	</label><br />
	<textarea id="ams-cache-page-optimization-js-exclusions" name="scm_option_page_optimization[js_exclusions]" class="large-text code" rows="6"><?php echo esc_textarea( $settings['js_exclusions'] ); ?></textarea>
</div>
<p><em><?php _e( 'Defer JavaScript can break menus, sliders, checkout, ads, analytics, or builder scripts. AMS Cache force-skips common menu, Slider Revolution, Slick, Swiper, and checkout scripts, but still test on staging and exclude any fragile theme script.', 'ams-cache' ); ?></em></p>
<p><em><?php _e( 'Local UCSS, External UCSS, and JS Analysis require Node.js, PurgeCSS, shell_exec, and a writable optimizer workspace. Use the requirement table above before enabling them on production.', 'ams-cache' ); ?></em></p>

<hr />
<h3><?php _e( 'Image Optimization', 'ams-cache' ); ?></h3>
<p><em><?php _e( 'AMS Cache generates WebP/AVIF variants beside original uploads, prefers the npm sharp engine when installed, falls back to the WordPress image editor, stores variant metadata on the attachment, and rewrites WordPress attachment image HTML only when a generated variant exists.', 'ams-cache' ); ?></em></p>

<fieldset class="scm-option-item">
	<legend><?php _e( 'Output formats', 'ams-cache' ); ?></legend>
	<label>
		<input type="checkbox" name="scm_option_page_optimization[image_formats][]" value="webp" <?php checked( in_array( 'webp', $settings['image_formats'], true ) ); ?>>
		<?php _e( 'WebP', 'ams-cache' ); ?>
	</label>
	<label style="margin-left: 16px">
		<input type="checkbox" name="scm_option_page_optimization[image_formats][]" value="avif" <?php checked( in_array( 'avif', $settings['image_formats'], true ) ); ?>>
		<?php _e( 'AVIF', 'ams-cache' ); ?>
	</label>
</fieldset>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-image-quality">
		<?php _e( 'Image quality', 'ams-cache' ); ?>
	</label><br />
	<input type="number" min="1" max="100" step="1" id="ams-cache-page-optimization-image-quality" name="scm_option_page_optimization[image_quality]" class="small-text" value="<?php echo esc_attr( $settings['image_quality'] ); ?>">
</div>

<div style="margin-top: 20px">
	<label for="ams-cache-page-optimization-image-batch-size">
		<?php _e( 'Background batch size', 'ams-cache' ); ?>
	</label><br />
	<input type="number" min="1" max="20" step="1" id="ams-cache-page-optimization-image-batch-size" name="scm_option_page_optimization[image_batch_size]" class="small-text" value="<?php echo esc_attr( $settings['image_batch_size'] ); ?>">
</div>
<p><em><?php _e( 'Remote S3/CDN JPG URLs change only after a WebP/AVIF variant exists locally and your offload plugin syncs that generated variant to the same remote path. Hard-coded theme URLs or unsynced attachment sizes stay as original JPG/PNG. Disabled mode is safest for WP Offload Media and Advanced Offload Media.', 'ams-cache' ); ?></em></p>
