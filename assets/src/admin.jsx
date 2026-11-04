import './admin.css';

import React, {useEffect, useMemo, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {
	Card,
	Input,
	Label,
	ListBox,
	Select,
	TextArea
} from '@heroui/react';
import {
	Activity,
	BarChart3,
	CheckCircle2,
	Code2,
	Copy,
	Database,
	FastForward,
	Filter,
	FileText,
	Gauge,
	HardDrive,
	Home,
	Info,
	Link2,
	Play,
	RefreshCcw,
	Save,
	Settings,
	ShoppingCart,
	SlidersHorizontal,
	Trash2,
	WandSparkles,
	X,
	Zap
} from 'lucide-react';

const boot = window.khseDashboard || {};
const i18n = boot.i18n || {};

const NAV = [
	{key: 'overview', label: 'Overview', icon: Gauge},
	{key: 'cache', label: 'Cache', icon: Database},
	{key: 'preload', label: 'Preload', icon: Zap},
	{key: 'performance', label: 'Performance', icon: Activity},
	{key: 'rules', label: 'Rules', icon: Filter},
	{key: 'statistics', label: 'Statistics', icon: BarChart3},
	{key: 'expert', label: 'Expert Mode', icon: Code2},
	{key: 'benchmark', label: 'Benchmark', icon: WandSparkles},
	{key: 'woocommerce', label: 'WooCommerce', icon: ShoppingCart},
	{key: 'about', label: 'About', icon: Info}
];

const SAVE_VIEWS = new Set(['cache', 'preload', 'performance', 'rules', 'statistics', 'expert', 'benchmark', 'woocommerce']);

const boolYes = (value) => value === true || value === 'yes' || value === 'enable';
const yesNo = (value) => (boolYes(value) ? (i18n.yes || 'Yes') : (i18n.no || 'No'));
const percent = (value) => `${Math.max(0, Math.min(100, Number(value) || 0))}%`;
const clone = (value) => JSON.parse(JSON.stringify(value || {}));
const humanStatus = (value) => {
	const text = Array.isArray(value) ? value.join('') : String(value ?? '');
	return (i18n.statuses && i18n.statuses[text]) || text.replaceAll('_', ' ');
};

function AmsButton({children, icon: Icon, tone = 'default', size = 'md', busy = false, disabled = false, iconOnly = false, className = '', onPress, title, ...props}) {
	const label = iconOnly && typeof children === 'string' ? children : undefined;

	return (
		<button
			{...props}
			type="button"
			className={`khse-button khse-button--${tone} khse-button--${size} ${iconOnly ? 'khse-button--icon' : ''} ${className}`.trim()}
			disabled={disabled || busy}
			onClick={onPress}
			title={title || label}
			aria-label={props['aria-label'] || label}
		>
			{Icon ? <Icon size={size === 'sm' ? 15 : 17} aria-hidden="true" /> : null}
			{iconOnly ? <span className="screen-reader-text">{children}</span> : <span>{busy ? (i18n.loading || 'Working...') : children}</span>}
		</button>
	);
}

function request(action, payload = {}) {
	const body = new FormData();
	body.append('action', action);
	body.append('_wpnonce', boot.nonce || '');

	Object.entries(payload).forEach(([key, value]) => body.append(key, value));

	return fetch(boot.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body
	}).then((response) => response.json()).then((json) => {
		if (!json || !json.success) {
			throw new Error(json?.data?.message || i18n.failed || 'Request failed.');
		}

		return json.data || {};
	});
}

function Pill({tone = 'info', children}) {
	return <span className={`khse-pill khse-pill--${tone}`}>{humanStatus(children)}</span>;
}

function StatCard({icon: Icon, title, value, detail, progress = 0}) {
	return (
		<Card className="khse-card khse-stat-card">
			<Card.Content>
				<div className="khse-card-title">
					<span className="khse-icon-badge"><Icon size={18} /></span>
					<span>{title}</span>
				</div>
				<strong>{value}</strong>
				<small>{detail}</small>
				<div className="khse-progress"><span style={{width: percent(progress)}} /></div>
			</Card.Content>
		</Card>
	);
}

function Panel({title, action, children, className = ''}) {
	return (
		<Card className={`khse-card khse-panel ${className}`.trim()}>
			<Card.Header className="khse-panel-header">
				<Card.Title className="khse-panel-title">{title}</Card.Title>
				{action ? <div className="khse-panel-action">{action}</div> : null}
			</Card.Header>
			<Card.Content className="khse-panel-content">{children}</Card.Content>
		</Card>
	);
}

function FactList({items}) {
	return (
		<ul className="khse-fact-list">
			{items.map((item) => (
				<li key={item.label}>
					<span>{item.label}</span>
					<strong>{item.value}</strong>
				</li>
			))}
		</ul>
	);
}

function SwitchRow({label, description, checked, onChange}) {
	return (
		<div className="khse-setting-row">
			<div>
				<strong>{label}</strong>
				{description ? <p>{description}</p> : null}
			</div>
			<button
				type="button"
				className={`khse-switch ${checked ? 'is-on' : ''}`}
				role="switch"
				aria-checked={!!checked}
				onClick={() => onChange(!checked)}
			>
				<span className="khse-switch-track"><span className="khse-switch-thumb" /></span>
				<span className="khse-switch-text">{checked ? 'Enable' : 'Disable'}</span>
			</button>
		</div>
	);
}

function TextFieldRow({label, value, onChange, type = 'text', min, max}) {
	return (
		<div className="khse-setting-row">
			<Label>{label}</Label>
			<Input
				type={type}
				value={String(value ?? '')}
				min={min}
				max={max}
				onChange={(event) => onChange(event.target.value)}
				variant="secondary"
				className="khse-input"
			/>
		</div>
	);
}

function TextAreaRow({label, value, onChange, rows = 5}) {
	return (
		<div className="khse-setting-stack">
			<Label>{label}</Label>
			<TextArea
				value={String(value ?? '')}
				onChange={(event) => onChange(event.target.value)}
				rows={rows}
				variant="secondary"
				className="khse-textarea"
			/>
		</div>
	);
}

function DriverSelect({settings, update}) {
	const drivers = settings.cache?.drivers || [];

	return (
		<div className="khse-setting-row">
			<Label>Cache Driver</Label>
			<Select selectedKey={settings.cache?.driver || 'file'} onSelectionChange={(value) => update('cache', 'driver', String(value))} className="khse-select" placeholder="Select driver">
				<Select.Trigger>
					<Select.Value />
					<Select.Indicator />
				</Select.Trigger>
				<Select.Popover>
					<ListBox>
						{drivers.map((driver) => (
							<ListBox.Item id={driver.value} key={driver.value} textValue={driver.label}>
								<Label>{driver.label}</Label>
							</ListBox.Item>
						))}
					</ListBox>
				</Select.Popover>
			</Select>
		</div>
	);
}

function MiniSelectRow({label, value, options, onChange}) {
	return (
		<div className="khse-setting-row">
			<Label>{label}</Label>
			<select className="khse-native-select" value={String(value ?? '')} onChange={(event) => onChange(event.target.value)}>
				{options.map((option) => (
					<option key={option.value} value={option.value}>{option.label}</option>
				))}
			</select>
		</div>
	);
}

function DriverGrid({drivers = [], selected, onSelect}) {
	return (
		<div className="khse-driver-grid">
			{drivers.map((driver) => {
				const available = driver.available !== false;
				const active = selected === driver.value;

				return (
					<button key={driver.value} type="button" className={`khse-driver-card ${active ? 'is-active' : ''}`} onClick={() => onSelect(driver.value)}>
						<span>{driver.label}</span>
						<span className={`khse-driver-status ${available ? 'is-ready' : 'is-missing'}`} title={available ? 'Available' : 'Extension missing'} />
					</button>
				);
			})}
		</div>
	);
}

function ToggleList({title, items = [], onToggle}) {
	return (
		<div className="khse-toggle-list">
			<h3>{title}</h3>
			{items.map((item) => (
				<SwitchRow
					key={item.value}
					label={item.label}
					checked={item.enabled === 'yes'}
					onChange={(checked) => onToggle(item.value, checked)}
				/>
			))}
		</div>
	);
}

function DriverAdvanced({cache, updateAdvanced, updateConnection, testDriver, isBusy}) {
	const driver = cache.driver || 'file';
	const advanced = cache.driverAdvanced || {};
	const current = advanced[driver] || {};
	const connection = advanced.connection || {};
	const connectType = connection[driver] || 'tcp';
	const isConnectionDriver = ['redis', 'memcache', 'memcached', 'mongo'].includes(driver);
	const selectedDriver = (cache.drivers || []).find((item) => item.value === driver);
	const driverReady = selectedDriver?.available !== false;
	const dbOptions = Array.from({length: 16}, (_, index) => ({value: String(index), label: `DB${index}`}));
	const health = (
		<div className="khse-driver-health">
			<Pill tone={driverReady ? 'good' : 'warning'}>{driverReady ? 'Extension ready' : 'Extension missing'}</Pill>
			<AmsButton size="sm" icon={CheckCircle2} busy={isBusy} onPress={() => testDriver(driver)}>Test Connection</AmsButton>
		</div>
	);

	if (driver === 'file') {
		return (
			<div className="khse-driver-advanced">
				{health}
				<SwitchRow label="File Compression" description="Saves disk space for large HTML files. Raw files can be faster on fast SSD storage." checked={current.compress === 'yes'} onChange={(checked) => updateAdvanced('file', 'compress', checked ? 'yes' : 'no')} />
				<TextFieldRow label="Compression Threshold" type="number" min="0" value={current.compress_threshold ?? 4096} onChange={(value) => updateAdvanced('file', 'compress_threshold', value)} />
				<TextFieldRow label="Compression Level" type="number" min="1" max="9" value={current.compress_level ?? 1} onChange={(value) => updateAdvanced('file', 'compress_level', value)} />
				<SwitchRow label="Nginx Direct Cache" description="Expose raw file cache for server-level FastCGI bypass. Install generated Nginx snippet manually." checked={Boolean(cache.nginxDirect)} onChange={(checked) => updateAdvanced('__cache', 'nginxDirect', checked)} />
			</div>
		);
	}

	if (driver === 'redis') {
		return (
			<div className="khse-driver-advanced">
				{health}
				<MiniSelectRow label="Connection" value={connectType} options={[{value: 'tcp', label: 'TCP'}, {value: 'socket', label: 'Unix Socket'}]} onChange={(value) => updateConnection('redis', value)} />
				{connectType === 'socket' ? <TextFieldRow label="Unix Socket" value={current.unix_socket || ''} onChange={(value) => updateAdvanced('redis', 'unix_socket', value)} /> : (
					<>
						<TextFieldRow label="Host" value={current.host || '127.0.0.1'} onChange={(value) => updateAdvanced('redis', 'host', value)} />
						<TextFieldRow label="Port" type="number" min="1" value={current.port || 6379} onChange={(value) => updateAdvanced('redis', 'port', value)} />
					</>
				)}
				<TextFieldRow label="User" value={current.user || ''} onChange={(value) => updateAdvanced('redis', 'user', value)} />
				<TextFieldRow label="Password" value={current.pass || ''} onChange={(value) => updateAdvanced('redis', 'pass', value)} />
				<MiniSelectRow label="Database" value={current.database ?? 0} options={dbOptions} onChange={(value) => updateAdvanced('redis', 'database', value)} />
				<SwitchRow label="Redis Compression" description="Reduces Redis memory for cached full-page HTML." checked={(current.compress ?? 'yes') === 'yes'} onChange={(checked) => updateAdvanced('redis', 'compress', checked ? 'yes' : 'no')} />
				<TextFieldRow label="Compression Threshold" type="number" min="0" value={current.compress_threshold ?? 1024} onChange={(value) => updateAdvanced('redis', 'compress_threshold', value)} />
				<TextFieldRow label="Compression Level" type="number" min="1" max="9" value={current.compress_level ?? 6} onChange={(value) => updateAdvanced('redis', 'compress_level', value)} />
			</div>
		);
	}

	if (isConnectionDriver) {
		const defaults = driver === 'mongo' ? {host: '127.0.0.1', port: 27017} : {host: '127.0.0.1', port: 11211};

		return (
			<div className="khse-driver-advanced">
				{health}
				<MiniSelectRow label="Connection" value={connectType} options={[{value: 'tcp', label: 'TCP'}, {value: 'socket', label: 'Unix Socket'}]} onChange={(value) => updateConnection(driver, value)} />
				{connectType === 'socket' ? <TextFieldRow label="Unix Socket" value={current.unix_socket || ''} onChange={(value) => updateAdvanced(driver, 'unix_socket', value)} /> : (
					<>
						<TextFieldRow label="Host" value={current.host || defaults.host} onChange={(value) => updateAdvanced(driver, 'host', value)} />
						<TextFieldRow label="Port" type="number" min="1" value={current.port || defaults.port} onChange={(value) => updateAdvanced(driver, 'port', value)} />
					</>
				)}
				{driver === 'mongo' ? (
					<>
						<TextFieldRow label="User" value={current.user || ''} onChange={(value) => updateAdvanced(driver, 'user', value)} />
						<TextFieldRow label="Password" value={current.pass || ''} onChange={(value) => updateAdvanced(driver, 'pass', value)} />
						<TextFieldRow label="Database" value={current.dbname || current.database || 'test'} onChange={(value) => updateAdvanced(driver, 'dbname', value)} />
						<TextFieldRow label="Collection" value={current.collection || 'cache_data'} onChange={(value) => updateAdvanced(driver, 'collection', value)} />
					</>
				) : null}
			</div>
		);
	}

	return <div className="khse-driver-advanced">{health}<p className="khse-muted">No extra settings for this driver. Use Cache Key Prefix to isolate sites sharing the same store.</p></div>;
}

function SaveBar({isBusy, onSave, global = false}) {
	return (
		<div className={`khse-savebar ${global ? 'khse-savebar--global' : ''}`.trim()}>
			<AmsButton tone="primary" icon={Save} busy={isBusy} onPress={onSave}>Save Changes</AmsButton>
		</div>
	);
}

function Overview({data, go, actions}) {
	const cache = data.cache || {};
	const optimization = data.optimization || {};
	const preload = data.preload || {};
	const stats = data.stats || {};

	return (
		<>
			<div className="grid w-full grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-4">
				<StatCard icon={Database} title="Page Cache" value={cache.enabled ? 'Enabled' : 'Disabled'} detail={`${cache.driver || 'file'} | TTL ${cache.ttl || 0}`} progress={cache.progress} />
				<StatCard icon={WandSparkles} title="Optimization" value={optimization.enabled ? 'Active' : 'Inactive'} detail={`${optimization.enabledCount || 0} / ${optimization.totalCount || 0} features`} progress={optimization.progress} />
				<StatCard icon={Activity} title="Requirements" value={`${optimization.reqPassed || 0} / ${optimization.reqTotal || 0}`} detail="checks passed" progress={optimization.reqProgress} />
				<StatCard icon={Zap} title="Preload" value={preload.enabled ? 'Enabled' : 'Disabled'} detail={`${preload.queueProcessed || 0} / ${preload.queueTotal || preload.limit || 0} processed`} progress={preload.progress} />
			</div>
			<div className="grid w-full grid-cols-1 gap-4 items-stretch auto-rows-fr 2xl:grid-cols-2">
				<Panel title="Cache Store" action={<AmsButton size="sm" icon={Settings} onPress={() => go('cache')}>Settings</AmsButton>}>
					<FactList items={[
						{label: 'Driver', value: cache.driver || 'file'},
						{label: 'Key Prefix', value: cache.keyPrefix || ''},
						{label: 'Max Entries', value: cache.maxEntries || 0},
						{label: 'Nginx Direct', value: yesNo(cache.nginxDirect)},
						{label: 'Expert Mode', value: `${cache.expertMode ? 'Enabled' : 'Disabled'} | ${cache.expertReady ? 'Ready' : 'Not ready'}`}
					]} />
				</Panel>
				<Panel title="Preload Queue" action={<AmsButton size="sm" icon={Settings} onPress={() => go('preload')}>Settings</AmsButton>}>
					<FactList items={[
						{label: 'Limit', value: preload.limit || 0},
						{label: 'Homepage Crawl', value: yesNo(preload.crawlHomepage)},
						{label: 'Priority URLs', value: preload.priorityCount || 0},
						{label: 'Critical URLs', value: preload.criticalCount || 0},
						{label: 'Queue Remaining', value: preload.queueRemaining || 0},
						{label: 'Last Run', value: preload.lastRun || 'Never'}
					]} />
				</Panel>
			</div>
			<div className="grid w-full grid-cols-1 gap-4 items-stretch auto-rows-fr 2xl:grid-cols-2">
				<Panel title="Optimization Checks" action={<AmsButton size="sm" icon={Activity} onPress={() => go('performance')}>Performance</AmsButton>}>
					<StatusList items={optimization.requirements || []} />
				</Panel>
				<Panel title="Cache Footprint" action={<AmsButton size="sm" icon={BarChart3} onPress={() => go('statistics')}>Statistics</AmsButton>}>
					<div className="khse-footprint">
						<strong>{stats.totalRows || 0}<span>rows</span></strong>
						<strong>{stats.totalSizeLabel || '0 B'}<span>stored</span></strong>
					</div>
					<CacheRows rows={(stats.rows || []).slice(0, 8)} clearType={actions.clearType} />
				</Panel>
			</div>
		</>
	);
}

function StatusList({items}) {
	return (
		<ul className="khse-status-list">
			{items.map((item) => (
				<li key={item.key}>
					<span className={`khse-dot ${item.passed ? 'is-good' : 'is-bad'}`} />
					<strong>{item.label}</strong>
					<code>{item.detail}</code>
				</li>
			))}
		</ul>
	);
}

function CacheRows({rows, clearType}) {
	return (
		<ul className="kh-speed-engine-rows">
			{rows.map((row) => (
				<li key={row.type}>
					<span>{row.label}</span>
					<strong>{row.rows}</strong>
					<code>{row.sizeLabel}</code>
					<AmsButton icon={Trash2} iconOnly size="sm" tone="danger" onPress={() => clearType(row.type)} aria-label={`Clear ${row.label}`}>
						Clear {row.label}
					</AmsButton>
				</li>
			))}
		</ul>
	);
}

function CacheSettings({settings, update, save, isBusy, testDriver}) {
	const cache = settings.cache || {};
	const selectedDriver = cache.driver || 'file';
	const selectedLabel = (cache.drivers || []).find((driver) => driver.value === selectedDriver)?.label || 'File';
	const setAdvanced = (driver, key, value) => {
		if (driver === '__cache') {
			update('cache', key, value);
			return;
		}

		const current = cache.driverAdvanced || {};
		const aliases = driver === 'memcache' || driver === 'memcached'
			? ['memcache', 'memcached']
			: (driver === 'mongo' || driver === 'mongodb' ? ['mongo', 'mongodb'] : [driver]);
		const next = {...current};

		aliases.forEach((alias) => {
			next[alias] = {
				...(current[alias] || {}),
				[key]: value
			};
		});

		update('cache', 'driverAdvanced', {
			...next
		});
	};
	const setConnection = (driver, value) => {
		const current = cache.driverAdvanced || {};
		const aliases = driver === 'memcache' || driver === 'memcached'
			? ['memcache', 'memcached']
			: (driver === 'mongo' || driver === 'mongodb' ? ['mongo', 'mongodb'] : [driver]);
		const nextConnection = {...(current.connection || {})};

		aliases.forEach((alias) => {
			nextConnection[alias] = value;
		});

		update('cache', 'driverAdvanced', {
			...current,
			connection: nextConnection
		});
	};

	return (
		<div className="grid w-full grid-cols-1 items-stretch gap-4">
			<Panel title="Driver" className="khse-wide-panel">
				<SwitchRow label="Caching Status" description="Guest-only page cache. Logged-in users never receive cached HTML." checked={Boolean(cache.cachingStatus)} onChange={(checked) => update('cache', 'cachingStatus', checked)} />
				<DriverSelect settings={settings} update={update} />
				<DriverGrid drivers={cache.drivers || []} selected={selectedDriver} onSelect={(value) => update('cache', 'driver', value)} />
				<div className="khse-driver-form-grid">
					<TextFieldRow label="Time to Live" type="number" min="300" max="2592000" value={cache.ttl} onChange={(value) => update('cache', 'ttl', value)} />
					<SwitchRow label="TTL Auto Clear" checked={Boolean(cache.ttlMechanism)} onChange={(checked) => update('cache', 'ttlMechanism', checked)} />
					<TextFieldRow label="Cache Key Prefix" value={cache.cacheKeyPrefix} onChange={(value) => update('cache', 'cacheKeyPrefix', value)} />
					<TextFieldRow label="Max Cache Entries" type="number" min="0" value={cache.maxEntries} onChange={(value) => update('cache', 'maxEntries', value)} />
					<SwitchRow label="Expert Mode" checked={Boolean(cache.expertModeStatus)} onChange={(checked) => update('cache', 'expertModeStatus', checked)} />
					<SwitchRow label="Debug Comment" checked={Boolean(cache.debugComment)} onChange={(checked) => update('cache', 'debugComment', checked)} />
				</div>
			</Panel>
			<Panel title={`${selectedLabel} Advanced Settings`} action={<Pill tone="info">{selectedDriver}</Pill>} className="khse-wide-panel">
				<DriverAdvanced cache={cache} updateAdvanced={setAdvanced} updateConnection={setConnection} testDriver={testDriver} isBusy={isBusy} />
			</Panel>
		</div>
	);
}

function PreloadSettings({data, settings, update, save, isBusy, runPreload, purgeHomepage}) {
	const preload = settings.preload || {};
	const toggleList = (field, value, checked) => {
		update('preload', field, (preload[field] || []).map((item) => (
			item.value === value ? {...item, enabled: checked ? 'yes' : 'no'} : item
		)));
	};

	return (
		<div className="grid w-full grid-cols-1 items-stretch gap-4 2xl:grid-cols-2">
			<Panel title="Queue Control">
				<FactList items={[
					{label: 'Queue Total', value: data.preload?.queueTotal || 0},
					{label: 'Queue Processed', value: data.preload?.queueProcessed || 0},
					{label: 'Queue Failed', value: data.preload?.queueFailed || 0},
					{label: 'Queue Remaining', value: data.preload?.queueRemaining || 0},
					{label: 'Homepage Priority', value: data.preload?.priorityCount || 0},
					{label: 'Critical URLs', value: data.preload?.criticalCount || 0}
				]} />
				<div className="khse-action-row">
					<AmsButton icon={Play} tone="primary" busy={isBusy} onPress={runPreload}>Run Preload</AmsButton>
					<AmsButton icon={Home} busy={isBusy} onPress={purgeHomepage}>Purge Homepage</AmsButton>
				</div>
			</Panel>
			<Panel title="Preload Settings">
				<SwitchRow label="Cache Preload" checked={Boolean(preload.enabled)} onChange={(checked) => update('preload', 'enabled', checked)} />
				<TextFieldRow label="Limit" type="number" min="1" max="1000" value={preload.limit} onChange={(value) => update('preload', 'limit', value)} />
				<SwitchRow label="Homepage Crawl" description="Homepage links become priority cache-first URLs." checked={Boolean(preload.crawlHomepage)} onChange={(checked) => update('preload', 'crawlHomepage', checked)} />
			</Panel>
			<Panel title="Preload Options" className="khse-full-width">
				<div className="khse-option-columns">
					<ToggleList title="Post Types" items={preload.postTypes || []} onToggle={(value, checked) => toggleList('postTypes', value, checked)} />
					<div className="khse-toggle-list">
						<h3>Homepage</h3>
						<SwitchRow label="Homepage" checked={Boolean(preload.homepage)} onChange={(checked) => update('preload', 'homepage', checked)} />
					</div>
					<ToggleList title="Archive Pages" items={preload.archives || []} onToggle={(value, checked) => toggleList('archives', value, checked)} />
				</div>
				<p className="khse-muted">Preloader crawls homepage first, pushes visible internal links to front of queue, then uses selected post types and archives.</p>
			</Panel>
		</div>
	);
}

function PerformanceSettings({data, settings, updatePerformance, save, isBusy, loadReports}) {
	const [tab, setTab] = useState('overview');
	const perf = settings.performance || {};
	const reports = data.optimization?.reports || {};
	const tabs = ['overview', 'requirements', 'optimization'];

	return (
		<>
			<div className="khse-tabs">
				{tabs.map((item) => (
					<button key={item} type="button" className={`khse-tab ${tab === item ? 'is-active' : ''}`} onClick={() => setTab(item)}>
						{item[0].toUpperCase() + item.slice(1)}
					</button>
				))}
			</div>
			{tab === 'overview' ? (
				<>
					<div className="grid w-full grid-cols-1 items-stretch gap-4 md:grid-cols-2 2xl:grid-cols-4">
						<StatCard icon={FileText} title="Recent Reports" value={reports.totalReports || 0} detail="stored page reports" progress={100} />
						<StatCard icon={CheckCircle2} title="Optimized Pages" value={reports.appliedPages || 0} detail="with at least one applied transform" progress={reports.totalReports ? ((reports.appliedPages || 0) / reports.totalReports) * 100 : 0} />
						<StatCard icon={HardDrive} title="Bytes Saved" value={reports.savedLabel || '0 B'} detail="across recent reports" progress={100} />
						<StatCard icon={Link2} title="External UCSS Saved" value={reports.externalUcssSavedLabel || '0 B'} detail="linked CSS removed" progress={100} />
						<StatCard icon={Code2} title="Local UCSS Saved" value={reports.ucssSavedLabel || '0 B'} detail="inline CSS removed" progress={100} />
						<StatCard icon={FastForward} title="JS Analysis" value={`${reports.jsDeferred || 0} / ${reports.jsAnalyzed || 0}`} detail="safely deferred / analyzed" progress={reports.jsAnalyzed ? ((reports.jsDeferred || 0) / reports.jsAnalyzed) * 100 : 0} />
					</div>
					<div className="grid w-full grid-cols-1 items-stretch gap-4 2xl:grid-cols-2">
						<Panel title="Optimization Summary">
							<FactList items={[
								{label: 'Pages Optimized', value: reports.appliedPages || 0},
								{label: 'Total Saved', value: reports.savedLabel || '0 B'},
								{label: 'External UCSS Saved', value: reports.externalUcssSavedLabel || '0 B'},
								{label: 'Local UCSS Saved', value: reports.ucssSavedLabel || '0 B'},
								{label: 'JS Deferred', value: reports.jsDeferred || 0}
							]} />
						</Panel>
						<LatestReport report={(reports.reports || [])[0]} />
					</div>
					<Reports reports={reports} loadReports={loadReports} />
				</>
			) : null}
			{tab === 'requirements' ? (
				<Panel title="Requirements">
					<StatusList items={data.optimization?.requirements || []} />
				</Panel>
			) : null}
			{tab === 'optimization' ? (
				<Panel title="Page Optimization">
					{[
						['status', 'Enable page optimization'],
						['minify_html', 'Minify HTML'],
						['remove_comments', 'Remove safe HTML comments'],
						['minify_inline_css', 'Minify inline CSS blocks'],
						['lazy_media', 'Lazy load images and iframes'],
						['critical_images', 'Prioritize first image for LCP'],
						['preconnect_fonts', 'Preconnect Google Fonts'],
						['defer_js', 'Defer JavaScript files'],
						['external_ucss', 'External UCSS Generation'],
						['local_ucss', 'Local UCSS Generation'],
						['js_analysis', 'JS Analysis']
					].map(([key, label]) => (
						<SwitchRow key={key} label={label} checked={Boolean(perf[key])} onChange={(checked) => updatePerformance(key, checked)} />
					))}
					<TextFieldRow label="Critical image count" type="number" min="0" max="5" value={perf.critical_image_count} onChange={(value) => updatePerformance('critical_image_count', value)} />
					<TextFieldRow label="External CSS max file size" type="number" min="51200" max="1048576" value={perf.external_ucss_max_file_size} onChange={(value) => updatePerformance('external_ucss_max_file_size', value)} />
					<TextFieldRow label="Legacy Bun path (optional)" value={perf.bun_path || ''} onChange={(value) => updatePerformance('bun_path', value)} />
					<TextFieldRow label="Legacy PurgeCSS path (optional)" value={perf.purgecss_path || ''} onChange={(value) => updatePerformance('purgecss_path', value)} />
					<TextFieldRow label="Local CSS max bytes" type="number" min="51200" max="1048576" value={perf.local_ucss_max_file_size} onChange={(value) => updatePerformance('local_ucss_max_file_size', value)} />
					<TextFieldRow label="JS max script bytes" type="number" min="16384" max="1048576" value={perf.js_analysis_max_script_bytes} onChange={(value) => updatePerformance('js_analysis_max_script_bytes', value)} />
					<TextFieldRow label="JS max total bytes" type="number" min="65536" max="4194304" value={perf.js_analysis_max_total_bytes} onChange={(value) => updatePerformance('js_analysis_max_total_bytes', value)} />
					<TextAreaRow label="UCSS safelist" value={perf.ucss_safelist} onChange={(value) => updatePerformance('ucss_safelist', value)} />
					<TextAreaRow label="JavaScript defer exclusions" value={perf.js_exclusions} onChange={(value) => updatePerformance('js_exclusions', value)} />
				</Panel>
			) : null}
		</>
	);
}

function LatestReport({report}) {
	if (!report) {
		return <Panel title="Latest Page Report"><p className="khse-muted">No page optimization report yet.</p></Panel>;
	}

	return (
		<Panel title="Latest Page Report">
			<div className="khse-report-head">
				<strong>{report.displayUri || report.uri}</strong>
				<span>{report.beforeLabel} -&gt; {report.afterLabel}</span>
				<Pill tone={report.expandedBytes > 0 ? 'warning' : 'good'}>{report.expandedBytes > 0 ? `${report.expandedLabel} bigger` : `${report.savedLabel} saved`}</Pill>
			</div>
			<ul className="khse-feature-list">
				{(report.features || []).slice(0, 8).map((feature) => (
					<li key={feature.key}>
						<Pill tone={feature.status === 'applied' ? 'good' : feature.status === 'disabled' ? 'muted' : 'warning'}>{feature.status}</Pill>
						<strong>{feature.key.replaceAll('_', ' ')}</strong>
						<span>{feature.detail}</span>
					</li>
				))}
			</ul>
		</Panel>
	);
}

function Reports({reports, loadReports}) {
	const rows = reports.reports || [];

	return (
		<Panel title="Recent Page Optimization Reports" action={<span className="khse-muted">{reports.loadedCount || rows.length} / {reports.totalReports || rows.length}</span>}>
			<ul className="khse-report-list">
				{rows.map((report) => (
					<li key={`${report.uri}-${report.generatedAt}`}>
						<code>{report.displayUri || report.uri}</code>
						<span>{report.dataType || 'page'}</span>
						<Pill tone={report.overallStatus === 'applied' ? 'good' : 'warning'}>{report.overallStatus}</Pill>
						<strong>{report.expandedBytes > 0 ? `-${report.expandedLabel}` : report.savedLabel}</strong>
						<span>{report.generatedAt}</span>
					</li>
				))}
			</ul>
			{reports.hasMore ? <div className="khse-loadmore"><AmsButton icon={FastForward} onPress={loadReports}>Load 5 more</AmsButton></div> : null}
		</Panel>
	);
}

function RulesSettings({settings, update, save, isBusy}) {
	const rules = settings.rules || {};

	return (
		<Panel title="Rules">
			<SwitchRow label="Enable exclusion rules" checked={Boolean(rules.enabled)} onChange={(checked) => update('rules', 'enabled', checked)} />
			<TextAreaRow label="Excluded URL Path List" value={rules.excludedList} onChange={(value) => update('rules', 'excludedList', value)} rows={8} />
			<TextAreaRow label="Excluded GET variables" value={rules.getVars} onChange={(value) => update('rules', 'getVars', value)} />
			<TextAreaRow label="Excluded POST variables" value={rules.postVars} onChange={(value) => update('rules', 'postVars', value)} />
			<TextAreaRow label="Excluded Cookie variables" value={rules.cookieVars} onChange={(value) => update('rules', 'cookieVars', value)} />
		</Panel>
	);
}

function StatisticsSettings({data, settings, update, save, isBusy, clearType}) {
	const stats = data.stats || {};

	return (
		<div className="grid w-full grid-cols-1 items-stretch gap-4 2xl:grid-cols-[minmax(0,3fr)_minmax(260px,1fr)]">
			<Panel title="Cache Types" action={<Pill tone="info">{stats.totalRows || 0} rows</Pill>}>
				<CacheRows rows={stats.rows || []} clearType={clearType} />
			</Panel>
			<Panel title="Statistics">
				<SwitchRow label="Record cache statistics" checked={Boolean(settings.statistics?.enabled)} onChange={(checked) => update('statistics', 'enabled', checked)} />
			</Panel>
		</div>
	);
}

function SimpleTogglePage({title, children}) {
	return <Panel title={title}>{children}</Panel>;
}

function ExpertSettings({settings, update, save, isBusy}) {
	const code = settings.cache?.expertModeCode || '';
	const customValue = settings.cache?.expertModeCustomValue || '';
	const [copied, setCopied] = useState('');
	const copyText = (value, key) => {
		if (!value) {
			return;
		}

		const done = () => {
			setCopied(key);
			window.setTimeout(() => setCopied(''), 1600);
		};

		if (navigator.clipboard?.writeText) {
			navigator.clipboard.writeText(value).then(done).catch(() => {});
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = value;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();
		document.execCommand('copy');
		document.body.removeChild(textarea);
		done();
	};

	return (
		<div className="grid w-full grid-cols-1 items-stretch gap-4 2xl:grid-cols-[minmax(280px,3fr)_minmax(0,7fr)]">
			<Panel title="Expert Mode">
				<SwitchRow label="Expert Mode" description="Reads runtime config early from wp-config.php. Guest-only bypass stays active." checked={Boolean(settings.cache?.expertModeStatus)} onChange={(checked) => update('cache', 'expertModeStatus', checked)} />
			</Panel>
			<Panel title="wp-config.php Block" action={<div className="khse-action-row khse-action-row--compact"><Pill tone={settings.cache?.expertModeReady ? 'good' : 'warning'}>{settings.cache?.expertModeReady ? 'Ready' : 'Not ready'}</Pill><AmsButton size="sm" icon={Copy} onPress={() => copyText(code, 'code')}>{copied === 'code' ? 'Copied' : 'Copy Code'}</AmsButton></div>}>
				<p className="khse-muted">Paste this block into wp-config.php above &ldquo;That&rsquo;s all, stop editing&rdquo;. It loads config before WordPress boots.</p>
				<pre
					className="khse-code-block khse-code-block--copyable"
					role="button"
					tabIndex={0}
					title="Click to copy"
					onClick={() => copyText(code, 'code')}
					onKeyDown={(event) => {
						if (event.key === 'Enter' || event.key === ' ') {
							event.preventDefault();
							copyText(code, 'code');
						}
					}}
				><code>{code}</code></pre>
			</Panel>
			<Panel title="Docker / Custom Value" className="khse-wide-panel" action={<AmsButton size="sm" icon={Copy} onPress={() => copyText(customValue, 'custom')}>{copied === 'custom' ? 'Copied' : 'Copy Value'}</AmsButton>}>
				<p className="khse-muted">For Docker or hosts that only expose a custom wp-config value, paste this single line into <code>WORDPRESS_CONFIG_EXTRA</code>. It must run before WordPress boots.</p>
				<pre
					className="khse-code-block khse-code-block--copyable"
					role="button"
					tabIndex={0}
					title="Click to copy"
					onClick={() => copyText(customValue, 'custom')}
					onKeyDown={(event) => {
						if (event.key === 'Enter' || event.key === ' ') {
							event.preventDefault();
							copyText(customValue, 'custom');
						}
					}}
				><code>{customValue}</code></pre>
			</Panel>
		</div>
	);
}

function BenchmarkSettings({settings, update, save, isBusy}) {
	const displays = [{value: 'text', label: 'Text'}, {value: 'icon', label: 'Icon'}, {value: 'both', label: 'Both'}];

	return (
		<Panel title="Benchmark">
			<SwitchRow label="Frontend Widget" checked={Boolean(settings.benchmark?.widget)} onChange={(checked) => update('benchmark', 'widget', checked)} />
			<MiniSelectRow label="Widget Display" value={settings.benchmark?.widgetDisplay || 'both'} options={displays} onChange={(value) => update('benchmark', 'widgetDisplay', value)} />
			<SwitchRow label="Footer Text" checked={Boolean(settings.benchmark?.footer)} onChange={(checked) => update('benchmark', 'footer', checked)} />
			<MiniSelectRow label="Footer Display" value={settings.benchmark?.footerDisplay || 'text'} options={displays} onChange={(value) => update('benchmark', 'footerDisplay', value)} />
			<div className="khse-benchmark-preview">
				<span>Your cached pages served to visitors will show this footer.</span>
				<strong>Page load: 0.42 sec | Queries: 12 | Memory: 32 MB</strong>
			</div>
		</Panel>
	);
}

function AboutPage({data}) {
	return (
		<div className="khse-about-page">
			<section className="khse-about-hero">
				<div>
					<Pill tone="info">KH Speed Engine 3.1.5</Pill>
					<h2>Performance console for real WordPress pages.</h2>
					<p>KH Speed Engine combines guest-only page caching, preload control, page optimization, External UCSS, and JS analysis in one clean WordPress admin experience.</p>
					<p className="khse-muted">KHSE Technical Team Â· Simple Cache Core</p>
				</div>
				<div className="khse-about-window" aria-hidden="true">
					<div className="khse-window-dots"><span /><span /><span /></div>
					<div className="khse-mini-metric"><strong>{data.optimization?.reqPassed || 0} / {data.optimization?.reqTotal || 0}</strong><span>requirements</span></div>
					<div className="khse-mini-metric"><strong>Guest</strong><span>safe cache</span></div>
					<div className="khse-mini-metric"><strong>UCSS</strong><span>local engine</span></div>
					<div className="khse-progress"><span style={{width: '82%'}} /></div>
					<div className="khse-progress"><span style={{width: '58%'}} /></div>
				</div>
			</section>
			<Panel title="Welcome to KH Speed Engine 3.1.5" className="khse-full-width">
				<p className="khse-center-copy">This release focuses on cleaner dashboard surfaces, restored Expert Mode configuration, and page cache controls that stay guest-only.</p>
			</Panel>
			<div className="khse-about-feature-grid grid w-full grid-cols-1 gap-4 xl:grid-cols-2">
				<Panel title="Cache-first Pages"><p>Preload starts from homepage links, selected post types, and archives so guests get warm cache before first visit.</p></Panel>
				<Panel title="External UCSS"><p>Eligible same-site CSS files use conservative PHP matching and are published as hashed assets only when the result is smaller.</p></Panel>
			</div>
		</div>
	);
}

function App() {
	const [data, setData] = useState(clone(boot.status));
	const [settings, setSettings] = useState(clone(boot.status?.settings));
	const [view, setView] = useState(boot.view || 'overview');
	const [busy, setBusy] = useState(false);
	const [notice, setNotice] = useState(null);

	const viewLabel = useMemo(() => (i18n.views && i18n.views[view]) || NAV.find((item) => item.key === view)?.label || 'Overview', [view]);

	const setStatus = (status) => {
		if (!status) {
			return;
		}
		setData(clone(status));
		setSettings(clone(status.settings));
	};

	const run = (action, payload = {}) => {
		setBusy(true);
		setNotice(null);

		return request(action, payload)
			.then((result) => {
				setStatus(result.status);
				setNotice({type: 'success', text: result.message || i18n.saved || 'Done.'});
				return result;
			})
			.catch((error) => {
				setNotice({type: 'error', text: error.message});
			})
			.finally(() => setBusy(false));
	};

	const update = (section, key, value) => {
		setSettings((current) => ({
			...current,
			[section]: {
				...(current?.[section] || {}),
				[key]: value
			}
		}));
	};

	const updatePerformance = (key, value) => update('performance', key, value);

	const save = () => run('khse_action_dashboard_save_settings', {settings: JSON.stringify(settings)});
	const refresh = () => run('khse_action_dashboard_status');

	const loadReports = () => {
		const reports = data.optimization?.reports || {};
		return run('khse_action_dashboard_reports', {offset: String(reports.loadedCount || 0)}).then((result) => {
			if (!result) {
				return;
			}
			setData((current) => ({
				...current,
				optimization: {
					...current.optimization,
					reports: {
						...current.optimization.reports,
						reports: [...(current.optimization.reports.reports || []), ...(result.reports || [])],
						loadedCount: result.loadedCount,
						totalReports: result.totalReports,
						hasMore: result.hasMore
					}
				}
			}));
		});
	};

	const actions = {
		refresh,
		clearAll: () => run('khse_action_dashboard_clear_cache'),
		clearType: (cacheType) => run('khse_action_dashboard_clear_cache_type', {cacheType}),
		runPreload: () => run('khse_action_dashboard_preload'),
		purgeHomepage: () => run('khse_action_dashboard_purge_homepage'),
		testDriver: (driver) => run('khse_action_dashboard_test_driver', {driver})
	};

	return (
		<div className="khse-admin">
			<aside className="khse-sidebar" aria-label="KH Speed Engine 3.1.5">
				<div className="khse-logo">KHSE</div>
				{NAV.map(({key, label, icon: Icon}) => (
					<button key={key} type="button" className={view === key ? 'is-active' : ''} onClick={() => setView(key)} title={label} aria-label={label} aria-current={view === key ? 'page' : undefined}>
						<Icon size={19} aria-hidden="true" />
						<span className="khse-nav-label">{label}</span>
					</button>
				))}
			</aside>
			<main className="khse-main">
				<header className="khse-topbar">
					<div>
						<h1>{viewLabel}</h1>
						<p>Live cache controls and page optimization state. <strong>Updated:</strong> {data.generatedAt}</p>
					</div>
					<div className="khse-toolbar-actions">
						<AmsButton icon={RefreshCcw} busy={busy} onPress={actions.refresh}>Refresh</AmsButton>
						<AmsButton icon={Play} tone="primary" busy={busy} onPress={actions.runPreload}>Run Preload</AmsButton>
						<AmsButton icon={Home} onPress={actions.purgeHomepage}>Purge Homepage</AmsButton>
						<AmsButton icon={Trash2} tone="danger" onPress={actions.clearAll}>Clear All Cache</AmsButton>
					</div>
				</header>
				{notice ? (
					<div className={`khse-notice khse-notice--${notice.type}`} role="status" aria-live="polite">
						<span>{notice.text}</span>
						<button type="button" className="khse-notice__close" onClick={() => setNotice(null)} aria-label="Clear notification">
							<X size={16} aria-hidden="true" />
						</button>
					</div>
				) : null}
				<section className="khse-view">
					{view === 'overview' ? <Overview data={data} go={setView} actions={actions} /> : null}
					{view === 'cache' ? <CacheSettings settings={settings} update={update} save={save} isBusy={busy} testDriver={actions.testDriver} /> : null}
					{view === 'preload' ? <PreloadSettings data={data} settings={settings} update={update} save={save} isBusy={busy} runPreload={actions.runPreload} purgeHomepage={actions.purgeHomepage} /> : null}
					{view === 'performance' ? <PerformanceSettings data={data} settings={settings} updatePerformance={updatePerformance} save={save} isBusy={busy} loadReports={loadReports} /> : null}
					{view === 'rules' ? <RulesSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'statistics' ? <StatisticsSettings data={data} settings={settings} update={update} save={save} isBusy={busy} clearType={actions.clearType} /> : null}
					{view === 'expert' ? <ExpertSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'benchmark' ? <BenchmarkSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'woocommerce' ? (
						<SimpleTogglePage title="WooCommerce">
							{settings.woocommerce?.active ? null : <Pill tone="warning">WooCommerce is not active</Pill>}
							<SwitchRow label="WooCommerce cache rules" checked={Boolean(settings.woocommerce?.enabled)} onChange={(checked) => update('woocommerce', 'enabled', checked)} />
							<SwitchRow label="Clear after payment complete" checked={Boolean(settings.woocommerce?.paymentComplete)} onChange={(checked) => update('woocommerce', 'paymentComplete', checked)} />
						</SimpleTogglePage>
					) : null}
					{view === 'about' ? <AboutPage data={data} /> : null}
				</section>
				{SAVE_VIEWS.has(view) ? <div className="khse-page-spacer" aria-hidden="true" /> : null}
				{SAVE_VIEWS.has(view) ? <SaveBar isBusy={busy} onSave={save} global /> : null}
			</main>
		</div>
	);
}

const root = document.getElementById('khse-admin-root');

if (root) {
	createRoot(root).render(<App />);
}
