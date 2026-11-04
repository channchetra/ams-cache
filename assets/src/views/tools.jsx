import React from 'react';
import {ArrowLeft, Gauge, Zap} from 'lucide-react';
import {
	Button,
	Card,
	CopyButton,
	Disclosure,
	Pill,
	Segmented,
	StatusRow,
	Switch
} from '../components/ui.jsx';

const DISPLAY_OPTIONS = [
	{value: 'text', label: 'Text'},
	{value: 'icon', label: 'Icon'},
	{value: 'both', label: 'Both'}
];

function Step({number, title, description, children}) {
	return (
		<section className="khse-step">
			<div className="khse-step__rail" aria-hidden="true">
				<span className="khse-step__number">{number}</span>
			</div>
			<div className="khse-step__body">
				<h2 className="khse-step__title">{title}</h2>
				{description ? <p className="khse-step__description">{description}</p> : null}
				<div className="khse-step__content">{children}</div>
			</div>
		</section>
	);
}

export function ExpertView({settings, update, actions}) {
	const cache = settings.cache || {};
	const code = cache.expertModeCode || '';
	const customValue = cache.expertModeCustomValue || '';

	return (
		<div className="khse-stack">
			<Card
				title="Expert mode"
				description="Load a small runtime configuration block before WordPress boots. Expert mode is optional."
				action={(
					<Button size="sm" variant="ghost" icon={ArrowLeft} onClick={() => actions.go('cache')}>
						Back to cache
					</Button>
				)}
			>
				<div className="khse-steps">
					<Step
						number="1"
						title="Enable Expert mode"
						description="This tells the plugin to look for the runtime constant before WordPress starts."
					>
						<Switch
							label="Expert mode"
							description="Guest-only bypass stays active. Only the runtime configuration moves earlier."
							checked={Boolean(cache.expertModeStatus)}
							onChange={(checked) => update('cache', 'expertModeStatus', checked)}
						/>
						<div className="khse-inline">
							<Pill tone={cache.expertModeReady ? 'success' : cache.expertModeStatus ? 'warning' : 'neutral'} dot>
								{cache.expertModeReady ? 'Runtime config detected' : cache.expertModeStatus ? 'Waiting for config' : 'Optional'}
							</Pill>
							<span className="khse-note">
								{cache.expertModeReady
									? 'The runtime constant is loaded early on this site.'
									: cache.expertModeStatus
										? 'Add the wp-config.php block below, then refresh status.'
										: 'Enable Expert mode when you want config loaded before WordPress boots.'}
							</span>
						</div>
					</Step>

					<Step
						number="2"
						title="Copy the wp-config.php block"
						description="Paste this block into wp-config.php above “That’s all, stop editing”."
					>
						<div className="khse-code-wrap">
							<pre className="khse-code"><code>{code}</code></pre>
							<CopyButton value={code} label="Copy block" />
						</div>
						<p className="khse-note">
							Keep the block above the “stop editing” line so the constant exists before WordPress loads plugins.
						</p>
					</Step>

					<Step
						number="3"
						title="Docker or custom value"
						description="For hosts that only expose a custom wp-config value, use this single line in WORDPRESS_CONFIG_EXTRA."
					>
						<Disclosure title="Docker / custom value" description="Runs before WordPress boots.">
							<div className="khse-code-wrap">
								<pre className="khse-code"><code>{customValue}</code></pre>
								<CopyButton value={customValue} label="Copy value" />
							</div>
						</Disclosure>
					</Step>
				</div>
			</Card>

			<Card title="How to verify" description="Two quick checks after saving.">
				<div className="khse-status-list">
					<StatusRow
						status="note"
						title="Check the served-by comment"
						description="View a cached page source. The debug comment should report the cache status."
					/>
					<StatusRow
						status="note"
						title="Confirm the ready pill"
						description="The Expert mode card shows Runtime config detected when the constant is loaded early. Expert mode is optional, so the rest of the cache keeps working without it."
					/>
				</div>
			</Card>
		</div>
	);
}

function BenchmarkPreview({widget, widgetDisplay, footer, footerDisplay}) {
	const widgetText = widgetDisplay !== 'icon';
	const widgetIcon = widgetDisplay !== 'text';
	const footerText = footerDisplay !== 'icon';
	const footerIcon = footerDisplay !== 'text';

	return (
		<Card title="Preview" description="Approximate rendering on a cached page." className="khse-preview-card">
			<div className="khse-browser">
				<div className="khse-browser__bar" aria-hidden="true">
					<span /><span /><span />
					<em>example.com</em>
				</div>
				<div className="khse-browser__page">
					<div className="khse-browser__skeleton" aria-hidden="true">
						<span /><span /><span />
					</div>
					{widget ? (
						<div className="khse-browser__widget">
							{widgetIcon ? <Zap size={15} aria-hidden="true" /> : null}
							{widgetText ? <span>Page load: 0.42 sec · Queries: 12</span> : null}
						</div>
					) : (
						<p className="khse-browser__muted">Widget is off.</p>
					)}
					{footer ? (
						<div className="khse-browser__footer">
							{footerIcon ? <Gauge size={14} aria-hidden="true" /> : null}
							{footerText ? <span>Page load: 0.42 sec | Queries: 12 | Memory: 32 MB</span> : null}
						</div>
					) : (
						<p className="khse-browser__muted">Footer is off.</p>
					)}
				</div>
			</div>
		</Card>
	);
}

export function BenchmarkView({settings, update}) {
	const benchmark = settings.benchmark || {};
	const widget = Boolean(benchmark.widget);
	const footer = Boolean(benchmark.footer);

	return (
		<div className="khse-benchmark">
			<Card title="Benchmark settings" description="Show performance details on cached pages for visitors and site owners.">
				<Switch
					label="Frontend widget"
					description="A compact floating card with page load, query, and memory details."
					checked={widget}
					onChange={(checked) => update('benchmark', 'widget', checked)}
				/>
				<div className="khse-field">
					<div className="khse-field__head">
						<span className="khse-field__label">Widget display</span>
					</div>
					<Segmented
						items={DISPLAY_OPTIONS}
						value={benchmark.widgetDisplay || 'both'}
						onChange={(value) => update('benchmark', 'widgetDisplay', value)}
						label="Widget display"
					/>
				</div>
				<Switch
					label="Footer text"
					description="A single line appended to cached pages."
					checked={footer}
					onChange={(checked) => update('benchmark', 'footer', checked)}
				/>
				<div className="khse-field">
					<div className="khse-field__head">
						<span className="khse-field__label">Footer display</span>
					</div>
					<Segmented
						items={DISPLAY_OPTIONS}
						value={benchmark.footerDisplay || 'text'}
						onChange={(value) => update('benchmark', 'footerDisplay', value)}
						label="Footer display"
					/>
				</div>
			</Card>
			<div className="khse-benchmark__preview">
				<BenchmarkPreview
					widget={widget}
					widgetDisplay={benchmark.widgetDisplay || 'both'}
					footer={footer}
					footerDisplay={benchmark.footerDisplay || 'text'}
				/>
			</div>
		</div>
	);
}
