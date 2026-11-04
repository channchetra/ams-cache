import React from 'react';
import {
	ArrowRight,
	BarChart3,
	Code2,
	Database,
	FileText,
	Filter,
	HardDrive,
	Play,
	WandSparkles,
	Zap
} from 'lucide-react';
import {Button, Card, EmptyState, Pill, Ring, Stat, StatusRow} from '../components/ui.jsx';
import {cacheTypeTone, formatNumber, ttlLabel} from '../lib/format.js';

function buildChecks({cache, settings, optimization}) {
	const cacheSettings = settings.cache || {};
	const drivers = cacheSettings.drivers || [];
	const driver = drivers.find((item) => item.value === cacheSettings.driver);
	const driverReady = driver ? driver.available !== false : true;
	const guestCacheable = cacheSettings.visibilityGuest !== false;
	const permalinkReady = cache.permalinkReady !== false;
	const requirements = optimization.requirements || [];
	const requirementsPassed = requirements.length
		? requirements.every((item) => item.passed)
		: Number(optimization.reqPassed || 0) >= Number(optimization.reqTotal || 0);

	return [
		{
			key: 'enabled',
			passed: Boolean(cache.enabled),
			title: 'Page caching',
			detail: 'Caching is off, so guests receive uncached HTML.',
			actionLabel: 'Open cache settings',
			view: 'cache'
		},
		{
			key: 'permalink',
			passed: permalinkReady,
			title: 'Pretty permalinks',
			detail: 'Plain permalinks collapse cache keys onto the homepage. Choose a permalink structure under Settings → Permalinks.',
			actionLabel: 'Review cache settings',
			view: 'cache'
		},
		{
			key: 'driver',
			passed: driverReady,
			title: 'Cache driver',
			detail: `${driver?.label || cacheSettings.driver || 'Selected'} is not available on this server.`,
			actionLabel: 'Choose a driver',
			view: 'cache'
		},
		{
			key: 'guest',
			passed: guestCacheable,
			title: 'Guest page cache',
			detail: 'Caching for guests is turned off in the traffic settings.',
			actionLabel: 'Open traffic settings',
			view: 'cache'
		},
		{
			key: 'optimization',
			passed: requirementsPassed,
			title: 'Optimization requirements',
			detail: `${Number(optimization.reqTotal || 0) - Number(optimization.reqPassed || 0)} of ${Number(optimization.reqTotal || 0)} checks still need a fix.`,
			actionLabel: 'Review requirements',
			view: 'performance'
		}
	];
}

function StatusHero({cache, settings, checks, health, entries, optimizedPages, go, actions}) {
	const cacheSettings = settings.cache || {};
	const driverLabel = (cacheSettings.drivers || []).find((driver) => driver.value === cacheSettings.driver)?.label || cacheSettings.driver || 'File';
	const failing = checks.filter((check) => !check.passed);

	let headline = 'Your site is running cache-first';
	let explanation = 'Guests get cached pages, preload is warming content, and optimization checks pass.';

	if (!cache.enabled) {
		headline = 'Page caching is off';
		explanation = 'Guests are receiving uncached HTML. Turn it on to start serving static pages.';
	} else if (failing.length > 0) {
		headline = 'Caching is live, optimization needs attention';
		explanation = 'Pages are cached. Some optimization checks still need a fix.';
	}

	const tone = health === 100 ? 'green' : health >= 60 ? 'amber' : 'red';

	return (
		<section className="khse-hero">
			<div className="khse-hero__content">
				<span className="khse-eyebrow">System status</span>
				<h2 className="khse-hero__headline">{headline}</h2>
				<p className="khse-hero__description">{explanation}</p>
				<div className="khse-hero__actions">
					<Button variant="primary" onClick={() => go('cache')}>Open cache settings</Button>
					<Button variant="secondary" icon={Play} onClick={() => actions.runPreload()}>Run preload</Button>
				</div>
			</div>
			<div className="khse-hero__aside">
				<Ring value={health} size={148} stroke={10} tone={tone}>
					<strong>{health}%</strong>
					<span>checks passed</span>
				</Ring>
				<div className="khse-hero__stats">
					<div>
						<span>Cache driver</span>
						<strong>{driverLabel}</strong>
					</div>
					<div>
						<span>Page TTL</span>
						<strong>{ttlLabel(cacheSettings.ttl)}</strong>
					</div>
				</div>
			</div>
		</section>
	);
}

function AttentionList({checks, go}) {
	const failing = checks.filter((check) => !check.passed).slice(0, 4);

	if (failing.length === 0) {
		return (
			<Card className="khse-attention" padded={false}>
				<div className="khse-attention__ok">
					<StatusRow
						status="pass"
						title="All checks passed"
						description="Caching, driver availability, guest traffic, and optimization requirements are all in good shape."
					/>
				</div>
			</Card>
		);
	}

	return (
		<Card
			className="khse-attention"
			title="Needs attention"
			action={<Pill tone="warning">{failing.length} to fix</Pill>}
			padded={false}
		>
			<div className="khse-attention__list">
				{failing.map((check) => (
					<StatusRow
						key={check.key}
						status="fail"
						title={check.title}
						description={check.detail}
						action={(
							<Button size="sm" variant="ghost" onClick={() => go(check.view)}>
								{check.actionLabel}
								<ArrowRight size={14} aria-hidden="true" />
							</Button>
						)}
					/>
				))}
			</div>
		</Card>
	);
}

function FootprintCard({stats, go}) {
	const rows = stats.rows || [];
	const maxSize = Math.max(1, ...rows.map((row) => Number(row.size) || 0));

	return (
		<Card
			title="Cache footprint"
			description="Storage used by each cache type."
			action={<Button size="sm" variant="ghost" onClick={() => go('statistics')}>Manage statistics</Button>}
		>
			{rows.length === 0 ? (
				<EmptyState icon={BarChart3} title="No cache entries yet" description="Cached pages will appear here as guests browse the site." />
			) : (
				<ul className="khse-footprint">
					{rows.map((row) => {
						const size = Number(row.size) || 0;
						const share = Math.max(size > 0 ? 2 : 0, Math.round((size / maxSize) * 100));

						return (
							<li key={row.type} className="khse-footprint__row">
								<span className="khse-footprint__label">{row.label}</span>
								<span className="khse-footprint__track">
									<span className={`khse-footprint__bar khse-footprint__bar--${cacheTypeTone(row.type)}`} style={{width: `${share}%`}} />
								</span>
								<span className="khse-footprint__rows">{formatNumber(row.rows)}</span>
								<span className="khse-footprint__size">{row.sizeLabel || '0 B'}</span>
							</li>
						);
					})}
				</ul>
			)}
		</Card>
	);
}

function PreloadCard({preload, actions, go}) {
	const total = Number(preload.queueTotal) || 0;
	const processed = Number(preload.queueProcessed) || 0;
	const remaining = Number(preload.queueRemaining) || 0;
	const progress = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : Number(preload.progress) || 0;

	return (
		<Card
			title="Preload"
			description="Priority warming for cache-first pages."
			action={<Button size="sm" variant="ghost" onClick={() => go('preload')}>Open preload</Button>}
		>
			<div className="khse-preload">
				<div className="khse-preload__progress">
					<Ring value={progress} size={112} stroke={9} tone="cyan">
						<strong>{Math.round(progress)}%</strong>
						<span>{formatNumber(processed)} / {formatNumber(total)}</span>
					</Ring>
				</div>
				<dl className="khse-facts">
					<div><dt>Remaining</dt><dd>{formatNumber(remaining)}</dd></div>
					<div><dt>Last run</dt><dd>{preload.lastRun || 'Never'}</dd></div>
					<div><dt>Last priority run</dt><dd>{preload.lastPriority || 'Never'}</dd></div>
				</dl>
			</div>
			<div className="khse-preload__actions">
				<Button variant="primary" icon={Play} onClick={() => actions.runPreload()}>Run preload</Button>
			</div>
		</Card>
	);
}

function ReportsCard({reports, go}) {
	const rows = (reports.reports || []).slice(0, 3);

	return (
		<Card
			title="Recent optimization reports"
			description="Latest page optimization results."
			action={<Button size="sm" variant="ghost" onClick={() => go('performance')}>View all reports</Button>}
			padded={false}
		>
			{rows.length === 0 ? (
				<EmptyState icon={FileText} title="No reports yet" description="Optimized pages will report their before and after sizes here." />
			) : (
				<ul className="khse-report">
					{rows.map((report) => {
						const expanded = Number(report.expandedBytes) > 0;

						return (
							<li key={`${report.uri}-${report.generatedAt}`} className="khse-report__row">
								<code className="khse-report__uri">{report.displayUri || report.uri}</code>
								<span className="khse-report__size">{report.savedLabel || '0 B'} saved</span>
								<Pill tone={report.overallStatus === 'applied' ? 'success' : 'warning'} dot>
									{report.overallStatus || 'recorded'}
								</Pill>
								{expanded ? <span className="khse-report__meta">{report.expandedLabel} bigger</span> : null}
								<span className="khse-report__time">{report.generatedAt}</span>
							</li>
						);
					})}
				</ul>
			)}
		</Card>
	);
}

const SHORTCUTS = [
	{view: 'cache', label: 'Cache settings', description: 'Driver, storage, and traffic.', icon: Database},
	{view: 'preload', label: 'Preload', description: 'Warm priority URLs.', icon: Zap},
	{view: 'rules', label: 'Exclusion rules', description: 'Bypass paths and variables.', icon: Filter},
	{view: 'expert', label: 'Expert mode', description: 'Early runtime configuration.', icon: Code2}
];

function Shortcuts({go}) {
	return (
		<div className="khse-shortcuts">
			{SHORTCUTS.map(({view, label, description, icon: Icon}) => (
				<button key={view} type="button" className="khse-shortcut" onClick={() => go(view)}>
					<span className="khse-shortcut__icon" aria-hidden="true"><Icon size={18} /></span>
					<span className="khse-shortcut__text">
						<strong>{label}</strong>
						<span>{description}</span>
					</span>
					<ArrowRight size={16} className="khse-shortcut__arrow" aria-hidden="true" />
				</button>
			))}
		</div>
	);
}

export default function DashboardView({data, settings, go, actions}) {
	const cache = data.cache || {};
	const optimization = data.optimization || {};
	const preload = data.preload || {};
	const stats = data.stats || {};
	const reports = optimization.reports || {};
	const checks = buildChecks({cache, settings, optimization});
	const passed = checks.filter((check) => check.passed).length;
	const health = checks.length ? Math.round((passed / checks.length) * 100) : 0;
	const entries = Number(stats.totalRows) || 0;
	const rows = stats.rows || [];
	const preloadProgress = Number(preload.progress) || 0;

	return (
		<div className="khse-dashboard">
			<StatusHero
				cache={cache}
				settings={settings}
				checks={checks}
				health={health}
				entries={entries}
				optimizedPages={Number(reports.appliedPages) || 0}
				go={go}
				actions={actions}
			/>

			<AttentionList checks={checks} go={go} />

			<div className="khse-kpis">
				<Stat
					icon={Database}
					tone="blue"
					label="Cache entries"
					value={formatNumber(entries)}
					detail={`${rows.length} cache type${rows.length === 1 ? '' : 's'}`}
				/>
				<Stat
					icon={HardDrive}
					tone="green"
					label="Cache size"
					value={stats.totalSizeLabel || '0 B'}
					detail="Stored cache footprint"
				/>
				<Stat
					icon={Zap}
					tone="amber"
					label="Preload progress"
					value={`${Math.round(preloadProgress)}%`}
					detail={`${formatNumber(preload.queueProcessed)} of ${formatNumber(preload.queueTotal)} queued`}
					progress={preloadProgress}
				/>
				<Stat
					icon={WandSparkles}
					tone="violet"
					label="Optimized pages"
					value={formatNumber(reports.appliedPages || 0)}
					detail={`${formatNumber(reports.totalReports || 0)} reports stored`}
				/>
			</div>

			<div className="khse-dash-grid">
				<FootprintCard stats={stats} go={go} />
				<PreloadCard preload={preload} actions={actions} go={go} />
			</div>

			<ReportsCard reports={reports} go={go} />
			<Shortcuts go={go} />
		</div>
	);
}
