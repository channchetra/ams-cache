<?php
/**
 * AMS Cache - Vue admin console.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 2.5.0
 * @version 2.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$is_woocommerce_active = is_plugin_active( 'woocommerce/woocommerce.php' );

?>

<div id="ams-cache-dashboard" class="ams-console" v-cloak>
	<aside class="ams-console-sidebar">
		<div class="ams-console-brand">
			<strong>AMS Cache</strong>
			<span><?php _e( 'Performance Console', 'ams-cache' ); ?></span>
		</div>

		<nav class="ams-console-nav" aria-label="<?php esc_attr_e( 'AMS Cache sections', 'ams-cache' ); ?>">
			<button type="button" :class="{ 'is-active': view === 'overview' }" @click="go('overview')">
				<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
				<?php _e( 'Overview', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'cache' }" @click="go('cache')">
				<span class="dashicons dashicons-database" aria-hidden="true"></span>
				<?php _e( 'Cache', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'preload' }" @click="go('preload')">
				<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
				<?php _e( 'Preload', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'performance' }" @click="go('performance')">
				<span class="dashicons dashicons-performance" aria-hidden="true"></span>
				<?php _e( 'Performance', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'advanced' }" @click="go('advanced')">
				<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
				<?php _e( 'Advanced', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'rules' }" @click="go('rules')">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span>
				<?php _e( 'Rules', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'statistics' }" @click="go('statistics')">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
				<?php _e( 'Statistics', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'expert' }" @click="go('expert')">
				<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
				<?php _e( 'Expert Mode', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'benchmark' }" @click="go('benchmark')">
				<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
				<?php _e( 'Benchmark', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'woocommerce' }" @click="go('woocommerce')">
				<span class="dashicons dashicons-cart" aria-hidden="true"></span>
				<?php _e( 'WooCommerce', 'ams-cache' ); ?>
			</button>
			<button type="button" :class="{ 'is-active': view === 'about' }" @click="go('about')">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<?php _e( 'About', 'ams-cache' ); ?>
			</button>
		</nav>
	</aside>

	<main class="ams-console-main">
		<header class="ams-console-toolbar">
			<div>
				<h2>{{ currentViewLabel }}</h2>
				<p>
					<?php _e( 'Live cache controls and page optimization state.', 'ams-cache' ); ?>
					<span class="ams-dashboard-updated"><?php _e( 'Updated', 'ams-cache' ); ?>: {{ data.generatedAt }}</span>
				</p>
			</div>
			<div class="ams-dashboard-actions">
				<button type="button" class="button" @click="refresh" :disabled="isBusy">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php _e( 'Refresh', 'ams-cache' ); ?>
				</button>
				<button type="button" class="button button-primary" @click="runPreload" :disabled="isBusy">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<?php _e( 'Run Preload', 'ams-cache' ); ?>
				</button>
				<button type="button" class="button" @click="purgeHomepage" :disabled="isBusy">
					<span class="dashicons dashicons-admin-home" aria-hidden="true"></span>
					<?php _e( 'Purge Homepage', 'ams-cache' ); ?>
				</button>
				<button type="button" class="button button-link-delete" @click="clearCache" :disabled="isBusy">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php _e( 'Clear All', 'ams-cache' ); ?>
				</button>
			</div>
		</header>

		<div v-if="notice.text" class="notice" :class="notice.type === 'error' ? 'notice-error' : 'notice-success'">
			<p>{{ notice.text }}</p>
		</div>

		<section v-show="view === 'overview'" class="ams-console-view">
			<div class="ams-dashboard-grid ams-dashboard-grid-4">
				<section class="ams-dashboard-card">
					<div class="ams-card-heading">
						<span class="dashicons dashicons-performance" aria-hidden="true"></span>
						<?php _e( 'Page Cache', 'ams-cache' ); ?>
					</div>
					<strong :class="statusClass(data.cache.enabled)">{{ data.cache.enabled ? '<?php echo esc_js( __( 'Enabled', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'ams-cache' ) ); ?>' }}</strong>
					<small>{{ data.cache.driver }} | TTL {{ data.cache.ttl }}</small>
					<div class="ams-progress"><span :style="progressStyle(data.cache.progress)"></span></div>
				</section>

				<section class="ams-dashboard-card">
					<div class="ams-card-heading">
						<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
						<?php _e( 'Optimization', 'ams-cache' ); ?>
					</div>
					<strong :class="statusClass(data.optimization.enabled)">{{ data.optimization.enabled ? '<?php echo esc_js( __( 'Active', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'ams-cache' ) ); ?>' }}</strong>
					<small>{{ data.optimization.enabledCount }} / {{ data.optimization.totalCount }} <?php _e( 'features', 'ams-cache' ); ?></small>
					<div class="ams-progress"><span :style="progressStyle(data.optimization.progress)"></span></div>
				</section>

				<section class="ams-dashboard-card">
					<div class="ams-card-heading">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<?php _e( 'Requirements', 'ams-cache' ); ?>
					</div>
					<strong>{{ data.optimization.reqPassed }} / {{ data.optimization.reqTotal }}</strong>
					<small><?php _e( 'checks passed', 'ams-cache' ); ?></small>
					<div class="ams-progress"><span :style="progressStyle(data.optimization.reqProgress)"></span></div>
				</section>

				<section class="ams-dashboard-card">
					<div class="ams-card-heading">
						<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
						<?php _e( 'Preload', 'ams-cache' ); ?>
					</div>
					<strong :class="statusClass(data.preload.enabled)">{{ data.preload.enabled ? '<?php echo esc_js( __( 'Enabled', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'ams-cache' ) ); ?>' }}</strong>
					<small>{{ data.preload.queueProcessed }} / {{ data.preload.queueTotal || data.preload.limit }} <?php _e( 'processed', 'ams-cache' ); ?></small>
					<div class="ams-progress"><span :style="progressStyle(data.preload.progress)"></span></div>
				</section>
			</div>

			<div class="ams-dashboard-grid ams-dashboard-grid-2">
				<section class="ams-dashboard-panel">
					<header>
						<h3><?php _e( 'Cache Store', 'ams-cache' ); ?></h3>
						<button type="button" class="button button-small" @click="go('advanced')"><?php _e( 'Advanced', 'ams-cache' ); ?></button>
					</header>
					<dl class="ams-dashboard-facts">
						<div><dt><?php _e( 'Driver', 'ams-cache' ); ?></dt><dd>{{ data.cache.driver }}</dd></div>
						<div><dt><?php _e( 'Key Prefix', 'ams-cache' ); ?></dt><dd><code>{{ data.cache.keyPrefix }}</code></dd></div>
						<div><dt><?php _e( 'Max Entries', 'ams-cache' ); ?></dt><dd>{{ data.cache.maxEntries }}</dd></div>
						<div><dt><?php _e( 'Nginx Direct', 'ams-cache' ); ?></dt><dd :class="statusClass(data.cache.nginxDirect)">{{ yesNo(data.cache.nginxDirect) }}</dd></div>
						<div><dt><?php _e( 'Expert Mode', 'ams-cache' ); ?></dt><dd>{{ data.cache.expertMode ? '<?php echo esc_js( __( 'Enabled', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'ams-cache' ) ); ?>' }} | {{ data.cache.expertReady ? '<?php echo esc_js( __( 'Ready', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Not ready', 'ams-cache' ) ); ?>' }}</dd></div>
					</dl>
				</section>

				<section class="ams-dashboard-panel">
					<header>
						<h3><?php _e( 'Preload Queue', 'ams-cache' ); ?></h3>
						<button type="button" class="button button-small" @click="go('preload')"><?php _e( 'Settings', 'ams-cache' ); ?></button>
					</header>
					<dl class="ams-dashboard-facts">
						<div><dt><?php _e( 'Limit', 'ams-cache' ); ?></dt><dd>{{ data.preload.limit }}</dd></div>
						<div><dt><?php _e( 'Homepage Crawl', 'ams-cache' ); ?></dt><dd :class="statusClass(data.preload.crawlHomepage)">{{ yesNo(data.preload.crawlHomepage) }}</dd></div>
						<div><dt><?php _e( 'Priority URLs', 'ams-cache' ); ?></dt><dd>{{ data.preload.priorityCount }}</dd></div>
						<div><dt><?php _e( 'Critical URLs', 'ams-cache' ); ?></dt><dd>{{ data.preload.criticalCount }}</dd></div>
						<div><dt><?php _e( 'Queue Total', 'ams-cache' ); ?></dt><dd>{{ data.preload.queueTotal }}</dd></div>
						<div><dt><?php _e( 'Queue Remaining', 'ams-cache' ); ?></dt><dd>{{ data.preload.queueRemaining }}</dd></div>
						<div><dt><?php _e( 'Last Run', 'ams-cache' ); ?></dt><dd>{{ data.preload.lastRun }}</dd></div>
					</dl>
				</section>
			</div>

			<div class="ams-dashboard-grid ams-dashboard-grid-2">
				<section class="ams-dashboard-panel">
					<header>
						<h3><?php _e( 'Optimization Checks', 'ams-cache' ); ?></h3>
						<button type="button" class="button button-small" @click="go('performance')"><?php _e( 'Performance', 'ams-cache' ); ?></button>
					</header>
					<ul class="ams-detail-list">
						<li v-for="item in data.optimization.requirements" :key="item.key">
							<span><span class="ams-dot" :class="item.passed ? 'is-good' : 'is-bad'"></span>{{ item.label }}</span>
							<code>{{ item.detail }}</code>
						</li>
					</ul>
				</section>

				<section class="ams-dashboard-panel">
					<header>
						<h3><?php _e( 'Cache Footprint', 'ams-cache' ); ?></h3>
						<button type="button" class="button button-small" @click="go('statistics')"><?php _e( 'Statistics', 'ams-cache' ); ?></button>
					</header>
					<div class="ams-footprint-summary">
						<div><strong>{{ data.stats.totalRows }}</strong><span><?php _e( 'rows', 'ams-cache' ); ?></span></div>
						<div><strong>{{ data.stats.totalSizeLabel }}</strong><span><?php _e( 'stored', 'ams-cache' ); ?></span></div>
					</div>
					<ul class="ams-detail-list ams-footprint-list">
						<li v-for="row in visibleStats" :key="row.type">
							<span>{{ row.label }}</span>
							<strong>{{ row.rows }}</strong>
							<code>{{ row.sizeLabel }}</code>
						</li>
					</ul>
				</section>
			</div>
		</section>

		<section v-show="view === 'cache'" class="ams-console-view ams-console-form">
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_1' ); ?>
				<?php do_settings_sections( 'scm_setting_page_1' ); ?>
				<hr />
				<?php submit_button(); ?>
			</form>
		</section>

		<section v-show="view === 'preload'" class="ams-console-view ams-console-form">
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_6' ); ?>
				<?php do_settings_sections( 'scm_setting_page_6' ); ?>
				<hr />
				<p><em><?php _e( 'Changing these settings clears cached pages and starts the configured preload flow.', 'ams-cache' ); ?></em></p>
				<?php submit_button(); ?>
			</form>
		</section>

		<section v-show="view === 'performance'" class="ams-console-view">
			<div class="ams-performance-grid">
				<section class="ams-dashboard-card">
					<div class="ams-card-heading"><?php _e( 'Recent Reports', 'ams-cache' ); ?></div>
					<strong>{{ data.optimization.reports.totalReports }}</strong>
					<small><?php _e( 'stored page reports', 'ams-cache' ); ?></small>
				</section>
				<section class="ams-dashboard-card">
					<div class="ams-card-heading"><?php _e( 'Optimized Pages', 'ams-cache' ); ?></div>
					<strong>{{ data.optimization.reports.appliedPages }}</strong>
					<small><?php _e( 'with at least one applied transform', 'ams-cache' ); ?></small>
				</section>
				<section class="ams-dashboard-card">
					<div class="ams-card-heading"><?php _e( 'Bytes Saved', 'ams-cache' ); ?></div>
					<strong>{{ data.optimization.reports.savedLabel }}</strong>
					<small><?php _e( 'across recent reports', 'ams-cache' ); ?></small>
				</section>
				<section class="ams-dashboard-card">
					<div class="ams-card-heading"><?php _e( 'Local UCSS Saved', 'ams-cache' ); ?></div>
					<strong>{{ data.optimization.reports.ucssSavedLabel }}</strong>
					<small>{{ data.optimization.reports.ucssAppliedPages }} <?php _e( 'pages with CSS removed', 'ams-cache' ); ?></small>
				</section>
				<section class="ams-dashboard-card">
					<div class="ams-card-heading"><?php _e( 'JS Analysis', 'ams-cache' ); ?></div>
					<strong>{{ data.optimization.reports.jsDeferred }} / {{ data.optimization.reports.jsAnalyzed }}</strong>
					<small><?php _e( 'safely deferred / analyzed', 'ams-cache' ); ?></small>
				</section>
			</div>

			<div class="ams-dashboard-grid ams-dashboard-grid-2">
				<section class="ams-dashboard-panel">
					<header><h3><?php _e( 'Optimization Requirements', 'ams-cache' ); ?></h3></header>
					<ul class="ams-detail-list">
						<li v-for="item in data.optimization.requirements" :key="'perf-' + item.key">
							<span><span class="ams-dot" :class="item.passed ? 'is-good' : 'is-bad'"></span>{{ item.label }}</span>
							<code>{{ item.detail }}</code>
						</li>
					</ul>
				</section>

				<section class="ams-dashboard-panel">
					<header><h3><?php _e( 'Latest Page Report', 'ams-cache' ); ?></h3></header>
					<div v-if="selectedReport" class="ams-report-summary">
						<strong>{{ selectedReport.displayUri }}</strong>
						<span>{{ selectedReport.beforeLabel }} -> {{ selectedReport.afterLabel }}</span>
						<span>{{ selectedReport.savedPercent }}% <?php _e( 'saved', 'ams-cache' ); ?></span>
					</div>
					<p v-else class="ams-empty-state"><?php _e( 'No page optimization reports yet. Visit or preload a cacheable guest page first.', 'ams-cache' ); ?></p>
					<ul v-if="selectedReport" class="ams-feature-report">
						<li v-for="feature in selectedReport.features" :key="feature.key">
							<span class="ams-status-pill" :class="'is-' + feature.status">{{ featureStatusLabel(feature.status) }}</span>
							<strong>{{ featureLabel(feature.key) }}</strong>
							<small>{{ feature.detail }}</small>
						</li>
					</ul>
				</section>
			</div>

			<section class="ams-dashboard-panel">
				<header>
					<h3><?php _e( 'Recent Page Optimization Reports', 'ams-cache' ); ?></h3>
					<span>{{ data.optimization.reports.loadedCount }} / {{ data.optimization.reports.totalReports }}</span>
				</header>
				<ul class="ams-report-list">
					<li class="ams-report-list-head">
						<span><?php _e( 'Page', 'ams-cache' ); ?></span>
						<span><?php _e( 'Type', 'ams-cache' ); ?></span>
						<span><?php _e( 'Result', 'ams-cache' ); ?></span>
						<span><?php _e( 'Saved', 'ams-cache' ); ?></span>
						<span><?php _e( 'Generated', 'ams-cache' ); ?></span>
					</li>
					<li v-for="report in data.optimization.reports.reports" :key="report.uri" @click="selectReport(report)" class="ams-report-row">
						<code>{{ report.displayUri }}</code>
						<span>{{ report.dataType }}</span>
						<span><span class="ams-status-pill" :class="'is-' + report.overallStatus">{{ featureStatusLabel(report.overallStatus) }}</span></span>
						<span>{{ report.savedLabel }} ({{ report.savedPercent }}%)</span>
						<span>{{ report.generatedAt }}</span>
					</li>
				</ul>
				<div v-if="data.optimization.reports.hasMore" class="ams-table-footer">
					<button type="button" class="button" @click="loadMoreReports" :disabled="isLoadingReports">
						{{ isLoadingReports ? '<?php echo esc_js( __( 'Loading...', 'ams-cache' ) ); ?>' : '<?php echo esc_js( __( 'Load 5 more', 'ams-cache' ) ); ?>' }}
					</button>
				</div>
			</section>

			<section class="ams-console-form">
				<form action="options.php" method="post">
					<?php settings_fields( 'scm_setting_group_10' ); ?>
					<?php do_settings_sections( 'scm_setting_page_10' ); ?>
					<hr />
					<p><em><?php _e( 'Changing optimization settings clears cached pages so the next cache write can be rebuilt with the new pipeline.', 'ams-cache' ); ?></em></p>
					<?php submit_button(); ?>
				</form>
			</section>
		</section>

		<section v-show="view === 'advanced'" class="ams-console-view ams-console-form">
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_7' ); ?>
				<?php do_settings_sections( 'scm_setting_page_7' ); ?>
				<hr />
				<?php submit_button(); ?>
			</form>
		</section>

		<section v-show="view === 'rules'" class="ams-console-view ams-console-form">
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_9' ); ?>
				<?php do_settings_sections( 'scm_setting_page_9' ); ?>
				<hr />
				<?php submit_button(); ?>
			</form>
		</section>

		<section v-show="view === 'statistics'" class="ams-console-view">
			<div class="ams-statistics-layout">
				<section class="ams-dashboard-panel">
					<header>
						<h3><?php _e( 'Cache Statistics', 'ams-cache' ); ?></h3>
					</header>
					<div class="ams-table-toolbar">
						<input type="search" v-model="statsFilter" placeholder="<?php esc_attr_e( 'Filter cache types...', 'ams-cache' ); ?>">
						<span>{{ filteredStats.length }} <?php _e( 'rows', 'ams-cache' ); ?></span>
					</div>
					<ul class="ams-statistics-list">
						<li class="ams-statistics-list-head">
							<span><?php _e( 'Cache Type', 'ams-cache' ); ?></span>
							<span><?php _e( 'Rows', 'ams-cache' ); ?></span>
							<span><?php _e( 'Stored', 'ams-cache' ); ?></span>
							<span><?php _e( 'Actions', 'ams-cache' ); ?></span>
						</li>
						<li v-for="row in filteredStats" :key="row.type">
							<span>{{ row.label }}</span>
							<span>{{ row.rows }}</span>
							<span>{{ row.sizeLabel }}</span>
							<span>
								<button
									type="button"
									class="button ams-row-action"
									:title="'<?php echo esc_js( __( 'Clear cache', 'ams-cache' ) ); ?>'"
									@click="clearCacheType(row.type)"
									:disabled="isBusy || row.rows === 0"
								>
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</button>
							</span>
						</li>
						<li class="ams-statistics-total">
							<span><?php _e( 'All', 'ams-cache' ); ?></span>
							<span>{{ data.stats.totalRows }}</span>
							<span>{{ data.stats.totalSizeLabel }}</span>
							<span>
								<button
									type="button"
									class="button ams-row-action"
									:title="'<?php echo esc_js( __( 'Clear all cache', 'ams-cache' ) ); ?>'"
									@click="clearCache"
									:disabled="isBusy || data.stats.totalRows === 0"
								>
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</button>
							</span>
						</li>
					</ul>
				</section>

				<section class="ams-console-form ams-statistics-settings">
					<form action="options.php" method="post">
						<?php settings_fields( 'scm_setting_group_3' ); ?>
						<?php do_settings_sections( 'scm_setting_page_3' ); ?>
						<hr />
						<?php submit_button(); ?>
					</form>
				</section>
			</div>
		</section>

		<section v-show="view === 'expert'" class="ams-console-view ams-console-form">
			<?php echo scm_load_view( 'page_expert_mode' ); ?>
		</section>

		<section v-show="view === 'benchmark'" class="ams-console-view ams-console-form">
			<p><em><?php _e( 'Benchmark information includes memory usage, SQL queries, generation time, and cache status.', 'ams-cache' ); ?></em></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_5' ); ?>
				<?php do_settings_sections( 'scm_setting_page_5' ); ?>
				<hr />
				<p><em><?php _e( 'Changing benchmark settings clears cached pages.', 'ams-cache' ); ?></em></p>
				<?php submit_button(); ?>
			</form>
		</section>

		<section v-show="view === 'woocommerce'" class="ams-console-view ams-console-form">
			<?php if ( ! $is_woocommerce_active ) : ?>
				<div class="notice notice-warning inline">
					<p><?php _e( 'WooCommerce is not active.', 'ams-cache' ); ?></p>
				</div>
			<?php endif; ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'scm_setting_group_8' ); ?>
				<?php do_settings_sections( 'scm_setting_page_8' ); ?>
				<hr />
				<p><em><?php _e( 'Changing WooCommerce cache settings clears cached pages.', 'ams-cache' ); ?></em></p>
				<?php if ( $is_woocommerce_active ) : ?>
					<?php submit_button(); ?>
				<?php else : ?>
					<p class="submit"><input type="button" class="button button-primary disabled" value="<?php esc_attr_e( 'Save Changes' ); ?>"></p>
				<?php endif; ?>
			</form>
		</section>

		<section v-show="view === 'about'" class="ams-console-view">
			<?php echo scm_load_view( 'page_about' ); ?>
		</section>
	</main>
</div>

<noscript>
	<div class="notice notice-warning"><p><?php _e( 'JavaScript is required for the interactive AMS Cache console.', 'ams-cache' ); ?></p></div>
</noscript>
