<?php
/**
 * AMS Cache - Stats
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.4.0
 * @version 1.4.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

function scm_get_stats( $type ) {
	$dir = scm_get_stats_dir( $type );

	$nums = 0;
	$size = 0;
	$seen = array();

	if ( is_dir( $dir ) ) {
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'json' ) {
				$stats = scm_read_stats_file( $file->getPathname() );

				if ( 'homepage' === $type && ! empty( $stats['uri'] ) && ! scm_is_homepage_uri( $stats['uri'] ) ) {
					continue;
				}

				$identity = empty( $stats['uri'] ) ? $file->getFilename() : scm_normalize_cache_uri( $stats['uri'] );

				if ( isset( $seen[ $identity ] ) ) {
					continue;
				}

				$seen[ $identity ] = true;
				$nums++;
				$size += $stats['size'];
			}
		}
	} else {
		wp_mkdir_p( $dir );
	}

	return array(
		'nums' => $nums,
		'size' => $size,
	);
}

$total_size = 0;
$total_rows = 0;

?>

<div id="scm-statistic-page">

	<?php if ( 'enable' === get_option( 'scm_option_statistics_status' ) ) : ?>

	<div class="scm-content-wrapper">
		<div class="ams-legacy-statistics-layout">
			<section class="ams-dashboard-panel">
				<form action="options.php" method="post">
					<ul class="ams-legacy-statistics-list">
						<li class="ams-legacy-statistics-head">
							<span><?php _e( 'Clear Cache', 'ams-cache' ); ?></span>
							<span><?php _e( 'Cache Type', 'ams-cache' ); ?></span>
							<span><?php _e( 'Rows', 'ams-cache' ); ?></span>
							<span><?php _e( 'Total Size', 'ams-cache' ); ?> (MB)</span>
						</li>
						<?php foreach ( scm_get_cache_type_list() as $key => $value ) : ?>
							<?php $stats_data = scm_get_stats( $key ); ?>
							<li>
								<span id="option-item-<?php echo $key; ?>"></span>
								<span><?php echo $value; ?></span>
								<span><?php echo $stats_data['nums']; ?></span>
								<span>
									<?php

									$size = round( $stats_data['size'] / ( 1024 * 1024 ), 2 );

									if ( $size > 100 ) {
										echo '<span class="scm-warning">' . $size . '</span>';
									} else {
										echo '<span class="scm-info">' . $size . '</span>';
									}

									$total_size += $size;
									$total_rows += $stats_data['nums'];

									?>
								</span>
							</li>
						<?php endforeach; ?>
						<li class="scm-total-size">
							<span><input type="radio" name="scm_option_clear_cache" id="ams-cache-clear-cache-all-option-enable" value="all" ></span>
							<span><?php _e( 'All', 'ams-cache' ); ?></span>
							<span><?php echo $total_rows; ?></span>
							<span><?php echo $total_size; ?></span>
						</li>
					</ul>
					<div id="show-form-clear-cache"></div>
					<?php submit_button( __( 'Confirm Clearing Cache', 'ams-cache' ) ); ?>
				</form>
			</section>

			<section class="ams-dashboard-panel">
				<form action="options.php" method="post">
					<?php settings_fields( 'scm_setting_group_3' ); ?>
					<?php do_settings_sections( 'scm_setting_page_3' ); ?>
					<hr />
					<?php submit_button(); ?>
				</form>
			</section>
		</div>
	</div>
	<div id="hidden-form-clear-cache" style="display: none">
		<?php settings_fields( 'scm_setting_group_4' ); ?>
		<?php do_settings_sections( 'scm_setting_page_4' ); ?>
	</div>

	<?php else : ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'scm_setting_group_3' ); ?>
			<?php do_settings_sections( 'scm_setting_page_3' ); ?>
			<hr />
			<?php submit_button(); ?>
		</form>
		<form action="options.php" method="post">
			<?php settings_fields( 'scm_setting_group_4' ); ?>
			<?php do_settings_sections( 'scm_setting_page_4' ); ?>
			<hr />
			<?php submit_button( __( 'Confirm Clearing Cache', 'ams-cache' ) ); ?>
		</form>

	<?php endif; ?>
</div>

<script>
	(function($) {
		$(function() {
			$('.scm-cache-type-list').each(function() {
				var type = $(this).attr('data-type');
				var html = $(this).html();
				$('#option-item-' + type).html(html);
			});

			$('#hidden-form-clear-cache').find('input[type=hidden]').each(function() {
				$(this).appendTo('#show-form-clear-cache');
			});
		});
	})(jQuery);
</script>
