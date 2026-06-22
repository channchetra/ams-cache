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

?>

<div class="ams-about-page">
	<section class="ams-about-hero" aria-labelledby="ams-about-title">
		<div class="ams-about-hero-copy">
			<span class="ams-status-pill is-info"><?php echo esc_html( 'AMS Cache ' . SCM_PLUGIN_VERSION ); ?></span>
			<h2 id="ams-about-title"><?php _e( 'Performance console for real WordPress pages.', 'ams-cache' ); ?></h2>
			<p><?php _e( 'AMS Cache combines guest-only page caching, preload control, page optimization, External UCSS, JS analysis, Bun image optimization, and a Vite-built WordPress admin experience.', 'ams-cache' ); ?></p>
			<div class="ams-about-actions">
				<a href="https://ams.com.kh/" target="_blank" rel="noopener noreferrer"><?php _e( 'AMS Technical Team', 'ams-cache' ); ?></a>
				<a href="https://github.com/terrylinooo/simple-cache" target="_blank" rel="noopener noreferrer"><?php _e( 'Simple Cache Core', 'ams-cache' ); ?></a>
			</div>
		</div>
		<div class="ams-about-visual" aria-hidden="true">
			<div class="ams-about-visual-top">
				<span></span>
				<span></span>
				<span></span>
			</div>
			<div class="ams-about-visual-grid">
				<div>
					<strong>10 / 10</strong>
					<span><?php _e( 'requirements', 'ams-cache' ); ?></span>
				</div>
				<div>
					<strong>Guest</strong>
					<span><?php _e( 'safe cache', 'ams-cache' ); ?></span>
				</div>
				<div>
					<strong>UCSS</strong>
					<span><?php _e( 'local engine', 'ams-cache' ); ?></span>
				</div>
				<div>
					<strong>WebP</strong>
					<span><?php _e( 'image path', 'ams-cache' ); ?></span>
				</div>
			</div>
			<div class="ams-about-visual-line is-long"><span></span></div>
			<div class="ams-about-visual-line is-short"><span></span></div>
		</div>
	</section>

	<nav class="ams-about-tabs" aria-label="<?php esc_attr_e( 'About AMS Cache sections', 'ams-cache' ); ?>">
		<a href="#ams-about-whats-new" class="is-active"><?php _e( 'What\'s New', 'ams-cache' ); ?></a>
		<a href="#ams-about-performance"><?php _e( 'Performance', 'ams-cache' ); ?></a>
		<a href="#ams-about-security"><?php _e( 'Security', 'ams-cache' ); ?></a>
		<a href="#ams-about-credits"><?php _e( 'Credits', 'ams-cache' ); ?></a>
	</nav>

	<section id="ams-about-whats-new" class="ams-about-section">
		<h3><?php printf( esc_html__( 'Welcome to AMS Cache %s', 'ams-cache' ), esc_html( SCM_PLUGIN_VERSION ) ); ?></h3>
		<p><?php _e( 'This release focuses on faster cached delivery and clearer operations: cleaner dashboard surfaces, safer optimization reporting, External UCSS for linked local stylesheets, and image optimizer controls that keep originals intact.', 'ams-cache' ); ?></p>
	</section>

	<section id="ams-about-performance" class="ams-about-feature-grid" aria-label="<?php esc_attr_e( 'Performance highlights', 'ams-cache' ); ?>">
		<article class="ams-about-card">
			<span class="dashicons dashicons-performance" aria-hidden="true"></span>
			<h4><?php _e( 'Cache-first pages', 'ams-cache' ); ?></h4>
			<p><?php _e( 'Preload can warm the homepage, archive routes, priority links, and discovered internal URLs so guests hit cache without waiting for the first visit.', 'ams-cache' ); ?></p>
		</article>
		<article class="ams-about-card">
			<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
			<h4><?php _e( 'External UCSS', 'ams-cache' ); ?></h4>
			<p><?php _e( 'Eligible same-site CSS files are tested with PurgeCSS, rewritten safely, and only inlined into cached HTML when the result is smaller.', 'ams-cache' ); ?></p>
		</article>
		<article class="ams-about-card">
			<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
			<h4><?php _e( 'Image optimizer path', 'ams-cache' ); ?></h4>
			<p><?php _e( 'WebP variants are generated beside original uploads, with optional HTML rewriting only when a generated local variant exists.', 'ams-cache' ); ?></p>
		</article>
	</section>

	<section id="ams-about-security" class="ams-about-split">
		<article class="ams-about-card">
			<span class="ams-status-pill is-applied"><?php _e( 'Security', 'ams-cache' ); ?></span>
			<h4><?php _e( 'Fail-open optimization', 'ams-cache' ); ?></h4>
			<p><?php _e( 'When an optimizer cannot parse a stylesheet, script, or image, AMS Cache keeps the original asset path and records the failure in the page report.', 'ams-cache' ); ?></p>
		</article>
		<article id="ams-about-credits" class="ams-about-card">
			<span class="ams-status-pill is-info"><?php _e( 'Credits', 'ams-cache' ); ?></span>
			<h4><?php _e( 'Built for AMS workflows', 'ams-cache' ); ?></h4>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						// translators: %1$s: contributor link. %2$s: team link.
						__( 'AMS Cache is maintained by %1$s with the %2$s, and builds on Amazing Project Simple Cache by Terry Lin.', 'ams-cache' ),
						'<a href="https://github.com/channchetra" target="_blank" rel="noopener noreferrer">Chetra CHANN</a>',
						'<a href="https://ams.com.kh/" target="_blank" rel="noopener noreferrer">AMS Technical Team</a>'
					)
				);
				?>
			</p>
		</article>
	</section>
</div>
