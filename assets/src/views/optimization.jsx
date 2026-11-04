import React, {useState} from 'react';
import {
	BarChart3,
	ChevronDown,
	Database,
	FileText,
	HardDrive,
	Info,
	Layers,
	RefreshCcw,
	Trash2,
	WandSparkles,
	Zap
} from 'lucide-react';
import {
	Button,
	Card,
	Disclosure,
	EmptyState,
	Field,
	Pill,
	Progress,
	Segmented,
	Select,
	Stat,
	StatusRow,
	Switch,
	Textarea,
	TextInput
} from '../components/ui.jsx';
import {cacheTypeTone, formatNumber, humanLabel} from '../lib/format.js';

const PERFORMANCE_TABS = [
	{value: 'overview', label: 'Overview'},
	{value: 'features', label: 'Features'},
	{value: 'reports', label: 'Reports'}
];

const FEATURE_LABELS = {
	minify_html: 'HTML minify',
	remove_comments: 'Comments',
	minify_inline_css: 'Inline CSS',
	lazy_media: 'Lazy media',
	critical_images: 'Critical images',
	preconnect_fonts: 'Font preconnect',
	external_ucss: 'External UCSS',
	local_ucss: 'Local UCSS',
	defer_js: 'Defer JS',
	js_analysis: 'JS analysis'
};

const STATUS_TONES = {
	applied: 'success',
	no_change: 'info',
	disabled: 'neutral',
	pending_engine: 'warning',
	failed: 'danger',
	unknown: 'neutral'
};

function featureLabel(key) {
	return FEATURE_LABELS[key] || humanLabel(key);
}

function statusTone(status) {
	return STATUS_TONES[status] || 'neutral';
}

function FeatureStatuses({features = []}) {
	if (features.length === 0) {
		return <p className="khse-note">No feature details were recorded for this page.</p>;
	}

	return (
		<div className="khse-report-features">
			{features.map((feature) => (
				<div className="khse-report-feature" key={feature.key}>
					<div className="khse-report-feature__text">
						<strong>{featureLabel(feature.key)}</strong>
						{feature.detail ? <span>{feature.detail}</span> : null}
					</div>
					<Pill tone={statusTone(feature.status)}>{humanLabel(feature.status)}</Pill>
				</div>
			))}
		</div>
	);
}

function ReportCompare({report}) {
	return (
		<div className="khse-compare">
			<div className="khse-compare__part">
				<span>Before</span>
				<strong>{report.beforeLabel || '0 B'}</strong>
			</div>
			<span className="khse-compare__arrow" aria-hidden="true">→</span>
			<div className="khse-compare__part">
				<span>After</span>
				<strong>{report.afterLabel || '0 B'}</strong>
			</div>
			<div className="khse-compare__part khse-compare__part--saved">
				<span>Saved</span>
				<strong>{report.savedLabel || '0 B'}</strong>
				<em>{report.savedPercent || 0}%</em>
			</div>
			{Number(report.expandedBytes) > 0 ? (
				<div className="khse-compare__part khse-compare__part--expanded">
					<span>Expanded</span>
					<strong>{report.expandedLabel}</strong>
					<em>{report.expandedPercent || 0}%</em>
				</div>
			) : null}
		</div>
	);
}

function RequirementsCard({optimization}) {
	const requirements = optimization.requirements || [];
	const passed = Number(optimization.reqPassed) || 0;
	const total = Number(optimization.reqTotal) || requirements.length;
	const allPassed = total > 0 && passed >= total;

	return (
		<Card
			title="Requirements"
			description="Server capabilities optimization depends on."
			action={<Pill tone={allPassed ? 'success' : 'warning'} dot>{passed}/{total} passing</Pill>}
		>
			{requirements.length === 0 ? (
				<EmptyState icon={WandSparkles} title="No requirements reported" description="Refresh status to check this server again." />
			) : (
				<div className="khse-status-list">
					{requirements.map((requirement) => (
						<StatusRow
							key={requirement.key}
							status={requirement.passed ? 'pass' : 'fail'}
							title={requirement.label}
							description={requirement.detail}
						/>
					))}
				</div>
			)}
		</Card>
	);
}

function LatestReportCard({report}) {
	if (!report) {
		return (
			<Card title="Latest report" description="The most recent optimized page.">
				<EmptyState
					icon={FileText}
					title="No reports yet"
					description="Optimization reports appear after a cached page is processed."
				/>
			</Card>
		);
	}

	return (
		<Card
			title="Latest report"
			description={report.displayUri || report.uri}
			action={<Pill tone={statusTone(report.overallStatus)} dot>{humanLabel(report.overallStatus)}</Pill>}
		>
			<ReportCompare report={report} />
			<FeatureStatuses features={report.features || []} />
			<p className="khse-note">Recorded {report.generatedAt || 'recently'}.</p>
		</Card>
	);
}

function PerformanceOverview({optimization, reports}) {
	const latest = (reports.reports || [])[0] || null;

	return (
		<div className="khse-stack">
			<div className="khse-kpis khse-kpis--3">
				<Stat
					icon={FileText}
					tone="blue"
					label="Recent reports"
					value={formatNumber(reports.reportCount ?? 0)}
					detail={`${formatNumber(reports.totalReports ?? 0)} stored in total`}
				/>
				<Stat
					icon={WandSparkles}
					tone="green"
					label="Optimized pages"
					value={formatNumber(reports.appliedPages ?? 0)}
					detail="Pages with applied changes"
				/>
				<Stat
					icon={HardDrive}
					tone="violet"
					label="Bytes saved"
					value={reports.savedLabel || '0 B'}
					detail="Across the report window"
				/>
				<Stat
					icon={Layers}
					tone="cyan"
					label="External UCSS saved"
					value={reports.externalUcssSavedLabel || '0 B'}
					detail={`${formatNumber(reports.externalUcssAppliedPages ?? 0)} pages optimized`}
				/>
				<Stat
					icon={Database}
					tone="amber"
					label="Local UCSS saved"
					value={reports.ucssSavedLabel || '0 B'}
					detail={`${formatNumber(reports.ucssAppliedPages ?? 0)} pages optimized`}
				/>
				<Stat
					icon={Zap}
					tone="red"
					label="JS analysis"
					value={formatNumber(reports.jsAnalyzed ?? 0)}
					detail={`${formatNumber(reports.jsDeferred ?? 0)} scripts deferred`}
				/>
			</div>

			<div className="khse-grid khse-grid--2">
				<RequirementsCard optimization={optimization} />
				<LatestReportCard report={latest} />
			</div>
		</div>
	);
}

function HtmlCard({perf, updatePerformance}) {
	return (
		<Card title="HTML" description="Reduce the bytes sent for every cached page.">
			<Switch
				label="Minify HTML"
				description="Collapse whitespace and remove redundant markup."
				checked={Boolean(perf.minify_html)}
				onChange={(checked) => updatePerformance('minify_html', checked)}
			/>
			<Switch
				label="Remove safe HTML comments"
				description="Strip comments while keeping conditional and preserved comments."
				checked={Boolean(perf.remove_comments)}
				onChange={(checked) => updatePerformance('remove_comments', checked)}
			/>
			<Switch
				label="Minify inline CSS"
				description="Compress style blocks inside the page."
				checked={Boolean(perf.minify_inline_css)}
				onChange={(checked) => updatePerformance('minify_inline_css', checked)}
			/>
		</Card>
	);
}

function MediaCard({perf, updatePerformance}) {
	return (
		<Card title="Media" description="Improve loading behavior for images and fonts.">
			<Switch
				label="Lazy load images and iframes"
				description="Defer offscreen media until it is needed."
				checked={Boolean(perf.lazy_media)}
				onChange={(checked) => updatePerformance('lazy_media', checked)}
			/>
			<Switch
				label="Prioritize the first image"
				description="Mark the first image as the LCP candidate."
				checked={Boolean(perf.critical_images)}
				onChange={(checked) => updatePerformance('critical_images', checked)}
			/>
			<Field label="Critical image count" description="Between 0 and 5 images." htmlFor="khse-critical-image-count">
				<TextInput
					id="khse-critical-image-count"
					type="number"
					min="0"
					max="5"
					value={perf.critical_image_count ?? 0}
					onChange={(event) => updatePerformance('critical_image_count', event.target.value)}
				/>
			</Field>
			<Switch
				label="Preconnect Google Fonts"
				description="Add an early connection hint for font requests."
				checked={Boolean(perf.preconnect_fonts)}
				onChange={(checked) => updatePerformance('preconnect_fonts', checked)}
			/>
		</Card>
	);
}

function CssCard({perf, updatePerformance}) {
	return (
		<Card title="CSS" description="Remove unused CSS from linked and inline styles.">
			<Switch
				label="External UCSS generation"
				description="Generate a smaller stylesheet for eligible same-site CSS files."
				checked={Boolean(perf.external_ucss)}
				onChange={(checked) => updatePerformance('external_ucss', checked)}
			/>
			<Switch
				label="Local UCSS generation"
				description="Reduce inline CSS blocks after matching used selectors."
				checked={Boolean(perf.local_ucss)}
				onChange={(checked) => updatePerformance('local_ucss', checked)}
			/>
			<div className="khse-subgrid">
				<Field label="External CSS max file size" description="Bytes. Larger files are skipped." htmlFor="khse-external-ucss-max">
					<TextInput
						id="khse-external-ucss-max"
						type="number"
						min="51200"
						max="1048576"
						suffix="bytes"
						value={perf.external_ucss_max_file_size ?? ''}
						onChange={(event) => updatePerformance('external_ucss_max_file_size', event.target.value)}
					/>
				</Field>
				<Field label="Local CSS max bytes" description="Bytes. Larger inline blocks are skipped." htmlFor="khse-local-ucss-max">
					<TextInput
						id="khse-local-ucss-max"
						type="number"
						min="51200"
						max="1048576"
						suffix="bytes"
						value={perf.local_ucss_max_file_size ?? ''}
						onChange={(event) => updatePerformance('local_ucss_max_file_size', event.target.value)}
					/>
				</Field>
			</div>
		</Card>
	);
}

function JavaScriptCard({perf, updatePerformance}) {
	return (
		<Card title="JavaScript" description="Defer scripts only when the analyzer says it is safe.">
			<Switch
				label="Defer JavaScript files"
				description="Add defer to scripts that do not depend on execution order."
				checked={Boolean(perf.defer_js)}
				onChange={(checked) => updatePerformance('defer_js', checked)}
			/>
			<Switch
				label="JS analysis"
				description="Scan scripts before deferring them."
				checked={Boolean(perf.js_analysis)}
				onChange={(checked) => updatePerformance('js_analysis', checked)}
			/>
			<div className="khse-subgrid">
				<Field label="JS max script bytes" description="Bytes per script file." htmlFor="khse-js-max-script">
					<TextInput
						id="khse-js-max-script"
						type="number"
						min="16384"
						max="1048576"
						suffix="bytes"
						value={perf.js_analysis_max_script_bytes ?? ''}
						onChange={(event) => updatePerformance('js_analysis_max_script_bytes', event.target.value)}
					/>
				</Field>
				<Field label="JS max total bytes" description="Bytes across all scripts." htmlFor="khse-js-max-total">
					<TextInput
						id="khse-js-max-total"
						type="number"
						min="65536"
						max="4194304"
						suffix="bytes"
						value={perf.js_analysis_max_total_bytes ?? ''}
						onChange={(event) => updatePerformance('js_analysis_max_total_bytes', event.target.value)}
					/>
				</Field>
			</div>
		</Card>
	);
}

function LimitsDisclosure({perf, updatePerformance}) {
	return (
		<Disclosure title="Limits & exclusions" description="Safelists, script exclusions, and optional legacy binary paths.">
			<div className="khse-grid khse-grid--2">
				<Field label="UCSS safelist" description="Selectors to keep even when they look unused." htmlFor="khse-ucss-safelist">
					<Textarea
						id="khse-ucss-safelist"
						rows={5}
						value={perf.ucss_safelist}
						onChange={(event) => updatePerformance('ucss_safelist', event.target.value)}
					/>
				</Field>
				<Field label="Script defer exclusions" description="One script handle or URL fragment per line." htmlFor="khse-js-exclusions">
					<Textarea
						id="khse-js-exclusions"
						rows={5}
						value={perf.js_exclusions}
						onChange={(event) => updatePerformance('js_exclusions', event.target.value)}
					/>
				</Field>
			</div>
		</Disclosure>
	);
}

function PerformanceFeatures({perf, updatePerformance}) {
	return (
		<div className="khse-stack">
			<Card
				title="Page optimization"
				description="Apply safe transforms to cached HTML before it is stored."
				action={<Pill tone={perf.status ? 'success' : 'neutral'} dot>{perf.status ? 'Optimization on' : 'Optimization off'}</Pill>}
			>
				<Switch
					label="Enable page optimization"
					description="Turns every feature below on or off as a group."
					checked={Boolean(perf.status)}
					onChange={(checked) => updatePerformance('status', checked)}
				/>
			</Card>

			<div className={`khse-feature-groups${perf.status ? '' : ' is-paused'}`}>
				{!perf.status ? (
					<div className="khse-paused-note">
						<Info size={16} aria-hidden="true" />
						<span>Feature values are saved but inactive while page optimization is off.</span>
					</div>
				) : null}
				<div className="khse-grid khse-grid--2">
					<HtmlCard perf={perf} updatePerformance={updatePerformance} />
					<MediaCard perf={perf} updatePerformance={updatePerformance} />
					<CssCard perf={perf} updatePerformance={updatePerformance} />
					<JavaScriptCard perf={perf} updatePerformance={updatePerformance} />
				</div>
			</div>

			<LimitsDisclosure perf={perf} updatePerformance={updatePerformance} />
		</div>
	);
}

function ReportRow({report, open, onToggle}) {
	return (
		<li className="khse-report-item">
			<button
				type="button"
				className="khse-report-item__summary"
				aria-expanded={open}
				onClick={onToggle}
			>
				<span className="khse-report-item__uri">
					<code>{report.displayUri || report.uri}</code>
					<span>{report.generatedAt || '—'}</span>
				</span>
				<span className="khse-report-item__size">{report.savedLabel || '0 B'} saved</span>
				<Pill tone={statusTone(report.overallStatus)} dot>{humanLabel(report.overallStatus)}</Pill>
				<ChevronDown className={`khse-report-item__chevron${open ? ' is-open' : ''}`} size={16} aria-hidden="true" />
			</button>
			{open ? (
				<div className="khse-report-item__panel">
					<ReportCompare report={report} />
					<FeatureStatuses features={report.features || []} />
				</div>
			) : null}
		</li>
	);
}

function PerformanceReports({reports, loadReports}) {
	const [expanded, setExpanded] = useState({});
	const [loading, setLoading] = useState(false);
	const rows = reports.reports || [];

	const loadMore = () => {
		setLoading(true);
		Promise.resolve()
			.then(() => loadReports())
			.finally(() => setLoading(false));
	};

	if (rows.length === 0) {
		return (
			<Card title="Optimization reports" description="Before and after sizes for cached pages.">
				<EmptyState
					icon={FileText}
					title="No reports yet"
					description="Reports appear after optimization processes a cached page."
				/>
			</Card>
		);
	}

	return (
		<Card
			title="Optimization reports"
			description="Expand a page to see before, after, and feature statuses."
			action={<Pill tone="info">{formatNumber(rows.length)} of {formatNumber(reports.totalReports ?? rows.length)}</Pill>}
			padded={false}
		>
			<ul className="khse-reports">
				{rows.map((report, index) => {
					const key = `${report.uri}-${report.generatedAt}-${index}`;

					return (
						<ReportRow
							key={key}
							report={report}
							open={Boolean(expanded[key])}
							onToggle={() => setExpanded((current) => ({...current, [key]: !current[key]}))}
						/>
					);
				})}
			</ul>
			<div className="khse-reports__footer">
				<span className="khse-note">Showing {formatNumber(rows.length)} of {formatNumber(reports.totalReports ?? rows.length)} reports.</span>
				{reports.hasMore ? (
					<Button size="sm" variant="secondary" busy={loading} onClick={loadMore}>Load 5 more</Button>
				) : null}
			</div>
		</Card>
	);
}

export function PerformanceView({data, settings, updatePerformance, loadReports}) {
	const [tab, setTab] = useState('overview');
	const perf = settings.performance || {};
	const optimization = data.optimization || {};
	const reports = optimization.reports || {};

	return (
		<div className="khse-stack">
			<div className="khse-perf-head">
				<Segmented items={PERFORMANCE_TABS} value={tab} onChange={setTab} label="Performance sections" />
				<Pill tone={perf.status ? 'success' : 'neutral'} dot>
					{perf.status ? 'Optimization on' : 'Optimization off'}
				</Pill>
			</div>
			{tab === 'overview' ? <PerformanceOverview optimization={optimization} reports={reports} /> : null}
			{tab === 'features' ? <PerformanceFeatures perf={perf} updatePerformance={updatePerformance} /> : null}
			{tab === 'reports' ? <PerformanceReports reports={reports} loadReports={loadReports} /> : null}
		</div>
	);
}

export function StatisticsView({data, settings, update, actions}) {
	const stats = data.stats || {};
	const rows = [...(stats.rows || [])].sort((left, right) => (Number(right.size) || 0) - (Number(left.size) || 0));
	const totalSize = Number(stats.totalSize) || 0;
	const [query, setQuery] = useState('');
	const [type, setType] = useState('all');
	const needle = query.trim().toLowerCase();
	const filtered = rows.filter((row) => {
		const matchesType = type === 'all' || row.type === type;
		const matchesQuery = !needle
			|| String(row.label || '').toLowerCase().includes(needle)
			|| String(row.type || '').toLowerCase().includes(needle);

		return matchesType && matchesQuery;
	});
	const filters = [{value: 'all', label: 'All types'}, ...rows.map((row) => ({value: row.type, label: row.label}))];

	return (
		<div className="khse-stack">
			<Card
				title="Cache statistics"
				description="Record per-type cache footprint so storage is easy to audit."
				action={<Pill tone={settings.statistics?.enabled ? 'success' : 'neutral'} dot>{settings.statistics?.enabled ? 'Recording' : 'Paused'}</Pill>}
			>
				<div className="khse-stats-head">
					<dl className="khse-stats-head__facts">
						<div>
							<dt>Total entries</dt>
							<dd>{formatNumber(stats.totalRows)}</dd>
						</div>
						<div>
							<dt>Total size</dt>
							<dd>{stats.totalSizeLabel || '0 B'}</dd>
						</div>
					</dl>
					<Switch
						label="Record statistics"
						description="Count entries and measure storage for each cache type."
						checked={Boolean(settings.statistics?.enabled)}
						onChange={(checked) => update('statistics', 'enabled', checked)}
					/>
				</div>
			</Card>

			<Card
				title="Storage by type"
				description="Sorted by size. Clear a type to remove its cached entries."
				action={(
					<div className="khse-toolbar">
						<TextInput
							ariaLabel="Search cache types"
							placeholder="Search types"
							value={query}
							onChange={(event) => setQuery(event.target.value)}
						/>
						<Select
							ariaLabel="Filter by cache type"
							value={type}
							options={filters}
							onChange={(event) => setType(event.target.value)}
						/>
						<span className="khse-toolbar__count">{filtered.length} of {rows.length} types</span>
						<Button size="sm" variant="secondary" icon={RefreshCcw} iconOnly onClick={() => actions.refresh()} title="Refresh statistics">
							Refresh statistics
						</Button>
					</div>
				)}
				padded={false}
			>
				{filtered.length === 0 ? (
					<div className="khse-card__inner">
						<EmptyState
							icon={BarChart3}
							title={rows.length === 0 ? 'No cached entries yet' : 'No matching cache types'}
							description={rows.length === 0 ? 'Entries appear here after pages are cached.' : 'Try a different search or filter.'}
						/>
					</div>
				) : (
					<div className="khse-table-wrap">
						<table className="khse-table khse-stats-table">
							<thead>
								<tr>
									<th scope="col">Type</th>
									<th scope="col">Entries</th>
									<th scope="col">Size</th>
									<th scope="col">Share</th>
									<th scope="col"><span className="screen-reader-text">Actions</span></th>
								</tr>
							</thead>
							<tbody>
								{filtered.map((row) => {
									const share = totalSize > 0 ? Math.round(((Number(row.size) || 0) / totalSize) * 100) : 0;

									return (
										<tr key={row.type}>
											<td data-label="Type">
												<strong>{row.label}</strong>
												<code className="khse-table__key">{row.type}</code>
											</td>
											<td data-label="Entries">{formatNumber(row.rows)}</td>
											<td data-label="Size">{row.sizeLabel || '0 B'}</td>
											<td data-label="Share">
												<div className="khse-table__share">
													<Progress value={share} tone={cacheTypeTone(row.type)} label={`${row.label} share`} />
													<em>{share}%</em>
												</div>
											</td>
											<td className="khse-table__action" data-label="Action">
												<Button size="sm" variant="ghost" icon={Trash2} onClick={() => actions.clearType(row.type)}>
													Clear
												</Button>
											</td>
										</tr>
									);
								})}
							</tbody>
						</table>
					</div>
				)}
			</Card>
		</div>
	);
}
