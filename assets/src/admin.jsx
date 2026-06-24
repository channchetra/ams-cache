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
	Image,
	Info,
	Link2,
	Play,
	RefreshCcw,
	Settings,
	ShoppingCart,
	SlidersHorizontal,
	Trash2,
	WandSparkles,
	X,
	Zap
} from 'lucide-react';

const boot = window.amsCacheDashboard || {};
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

function AmsButton({children, icon: Icon, tone = 'default', size = 'md', busy = false, disabled = false, iconOnly = false, className = '', onPress, ...props}) {
	return (
		<button
			type="button"
			className={`ams-button ams-button--${tone} ams-button--${size} ${iconOnly ? 'ams-button--icon' : ''} ${className}`.trim()}
			disabled={disabled || busy}
			onClick={onPress}
			{...props}
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
	return <span className={`ams-pill ams-pill--${tone}`}>{humanStatus(children)}</span>;
}

function StatCard({icon: Icon, title, value, detail, progress = 0}) {
	return (
		<Card className="ams-card ams-stat-card">
			<Card.Content>
				<div className="ams-card-title">
					<span className="ams-icon-badge"><Icon size={18} /></span>
					<span>{title}</span>
				</div>
				<strong>{value}</strong>
				<small>{detail}</small>
				<div className="ams-progress"><span style={{width: percent(progress)}} /></div>
			</Card.Content>
		</Card>
	);
}

function Panel({title, action, children, className = ''}) {
	return (
		<Card className={`ams-card ams-panel ${className}`.trim()}>
			<Card.Header className="ams-panel-header">
				<Card.Title className="ams-panel-title">{title}</Card.Title>
				{action ? <div className="ams-panel-action">{action}</div> : null}
			</Card.Header>
			<Card.Content className="ams-panel-content">{children}</Card.Content>
		</Card>
	);
}

function FactList({items}) {
	return (
		<ul className="ams-fact-list">
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
		<div className="ams-setting-row">
			<div>
				<strong>{label}</strong>
				{description ? <p>{description}</p> : null}
			</div>
			<button
				type="button"
				className={`ams-switch ${checked ? 'is-on' : ''}`}
				role="switch"
				aria-checked={!!checked}
				onClick={() => onChange(!checked)}
			>
				<span className="ams-switch-track"><span className="ams-switch-thumb" /></span>
				<span className="ams-switch-text">{checked ? 'Enable' : 'Disable'}</span>
			</button>
		</div>
	);
}

function TextFieldRow({label, value, onChange, type = 'text', min, max}) {
	return (
		<div className="ams-setting-row">
			<Label>{label}</Label>
			<Input
				type={type}
				value={String(value ?? '')}
				min={min}
				max={max}
				onChange={(event) => onChange(event.target.value)}
				variant="secondary"
				className="ams-input"
			/>
		</div>
	);
}

function TextAreaRow({label, value, onChange, rows = 5}) {
	return (
		<div className="ams-setting-stack">
			<Label>{label}</Label>
			<TextArea
				value={String(value ?? '')}
				onChange={(event) => onChange(event.target.value)}
				rows={rows}
				variant="secondary"
				className="ams-textarea"
			/>
		</div>
	);
}

function DriverSelect({settings, update}) {
	const drivers = settings.cache?.drivers || [];

	return (
		<div className="ams-setting-row">
			<Label>Cache Driver</Label>
			<Select selectedKey={settings.cache?.driver || 'file'} onSelectionChange={(value) => update('cache', 'driver', String(value))} className="ams-select" placeholder="Select driver">
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
		<div className="ams-setting-row">
			<Label>{label}</Label>
			<select className="ams-native-select" value={String(value ?? '')} onChange={(event) => onChange(event.target.value)}>
				{options.map((option) => (
					<option key={option.value} value={option.value}>{option.label}</option>
				))}
			</select>
		</div>
	);
}

function DriverGrid({drivers = [], selected, onSelect}) {
	return (
		<div className="ams-driver-grid">
			{drivers.map((driver) => {
				const available = driver.available !== false;
				const active = selected === driver.value;

				return (
					<button key={driver.value} type="button" className={`ams-driver-card ${active ? 'is-active' : ''}`} onClick={() => onSelect(driver.value)}>
						<span>{driver.label}</span>
						<span className={`ams-driver-status ${available ? 'is-ready' : 'is-missing'}`} title={available ? 'Available' : 'Extension missing'} />
					</button>
				);
			})}
		</div>
	);
}

function ToggleList({title, items = [], onToggle}) {
	return (
		<div className="ams-toggle-list">
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
		<div className="ams-driver-health">
			<Pill tone={driverReady ? 'good' : 'warning'}>{driverReady ? 'Extension ready' : 'Extension missing'}</Pill>
			<AmsButton size="sm" icon={CheckCircle2} busy={isBusy} onPress={() => testDriver(driver)}>Test Connection</AmsButton>
		</div>
	);

	if (driver === 'file') {
		return (
			<div className="ams-driver-advanced">
				{health}
				<SwitchRow label="File Compression" description="Saves disk space for large HTML files. Raw files can be faster on fast SSD storage." checked={current.compress === 'yes'} onChange={(checked) => updateAdvanced('file', 'compress', checked ? 'yes' : 'no')} />
				<TextFieldRow label="Compression Threshold" type="number" min="0" value={current.compress_threshold ?? 4096} onChange={(value) => updateAdvanced('file', 'compress_threshold', value)} />
				<TextFieldRow label="Compression Level" type="number" min="1" max="9" value={current.compress_level ?? 1} onChange={(value) => updateAdvanced('file', 'compress_level', value)} />
				<SwitchRow label="Nginx Direct Cache" description="Expose raw file cache for server-level FastCGI bypass. Install generated Nginx snippet manually." checked={cache.nginxDirect === 'yes'} onChange={(checked) => updateAdvanced('__cache', 'nginxDirect', checked ? 'yes' : 'no')} />
			</div>
		);
	}

	if (driver === 'redis') {
		return (
			<div className="ams-driver-advanced">
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
			<div className="ams-driver-advanced">
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

	return <div className="ams-driver-advanced">{health}<p className="ams-muted">No extra settings for this driver. Use Cache Key Prefix to isolate sites sharing the same store.</p></div>;
}

function SaveBar({isBusy, onSave, global = false}) {
	return (
		<div className={`ams-savebar ${global ? 'ams-savebar--global' : ''}`.trim()}>
			<AmsButton tone="primary" busy={isBusy} onPress={onSave}>Save Changes</AmsButton>
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
				<Panel title="Cache Store" action={<AmsButton size="sm" onPress={() => go('cache')}>Settings</AmsButton>}>
					<FactList items={[
						{label: 'Driver', value: cache.driver || 'file'},
						{label: 'Key Prefix', value: cache.keyPrefix || ''},
						{label: 'Max Entries', value: cache.maxEntries || 0},
						{label: 'Nginx Direct', value: yesNo(cache.nginxDirect)},
						{label: 'Expert Mode', value: `${cache.expertMode ? 'Enabled' : 'Disabled'} | ${cache.expertReady ? 'Ready' : 'Not ready'}`}
					]} />
				</Panel>
				<Panel title="Preload Queue" action={<AmsButton size="sm" onPress={() => go('preload')}>Settings</AmsButton>}>
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
				<Panel title="Optimization Checks" action={<AmsButton size="sm" onPress={() => go('performance')}>Performance</AmsButton>}>
					<StatusList items={optimization.requirements || []} />
				</Panel>
				<Panel title="Cache Footprint" action={<AmsButton size="sm" onPress={() => go('statistics')}>Statistics</AmsButton>}>
					<div className="ams-footprint">
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
		<ul className="ams-status-list">
			{items.map((item) => (
				<li key={item.key}>
					<span className={`ams-dot ${item.passed ? 'is-good' : 'is-bad'}`} />
					<strong>{item.label}</strong>
					<code>{item.detail}</code>
				</li>
			))}
		</ul>
	);
}

function CacheRows({rows, clearType}) {
	return (
		<ul className="ams-cache-rows">
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
			<Panel title="Driver" className="ams-wide-panel">
				<SwitchRow label="Caching Status" description="Guest-only page cache. Logged-in users never receive cached HTML." checked={cache.cachingStatus === 'enable'} onChange={(checked) => update('cache', 'cachingStatus', checked ? 'enable' : 'disable')} />
				<DriverSelect settings={settings} update={update} />
				<DriverGrid drivers={cache.drivers || []} selected={selectedDriver} onSelect={(value) => update('cache', 'driver', value)} />
				<div className="ams-driver-form-grid">
					<TextFieldRow label="Time to Live" type="number" min="300" max="2592000" value={cache.ttl} onChange={(value) => update('cache', 'ttl', value)} />
					<SwitchRow label="TTL Auto Clear" checked={cache.ttlMechanism === 'enable'} onChange={(checked) => update('cache', 'ttlMechanism', checked ? 'enable' : 'disable')} />
					<TextFieldRow label="Cache Key Prefix" value={cache.cacheKeyPrefix} onChange={(value) => update('cache', 'cacheKeyPrefix', value)} />
					<TextFieldRow label="Max Cache Entries" type="number" min="0" value={cache.maxEntries} onChange={(value) => update('cache', 'maxEntries', value)} />
					<SwitchRow label="Expert Mode" checked={cache.expertModeStatus === 'enable'} onChange={(checked) => update('cache', 'expertModeStatus', checked ? 'enable' : 'disable')} />
					<SwitchRow label="Debug Comment" checked={cache.debugComment === 'yes'} onChange={(checked) => update('cache', 'debugComment', checked ? 'yes' : 'no')} />
				</div>
			</Panel>
			<Panel title={`${selectedLabel} Advanced Settings`} action={<Pill tone="info">{selectedDriver}</Pill>} className="ams-wide-panel">
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
					{label: 'Queue Remaining', value: data.preload?.queueRemaining || 0},
					{label: 'Homepage Priority', value: data.preload?.priorityCount || 0},
					{label: 'Critical URLs', value: data.preload?.criticalCount || 0}
				]} />
				<div className="ams-action-row">
					<AmsButton icon={Play} tone="primary" busy={isBusy} onPress={runPreload}>Run Preload</AmsButton>
					<AmsButton icon={Home} busy={isBusy} onPress={purgeHomepage}>Purge Homepage</AmsButton>
				</div>
			</Panel>
			<Panel title="Preload Settings">
				<SwitchRow label="Cache Preload" checked={preload.enabled === 'yes'} onChange={(checked) => update('preload', 'enabled', checked ? 'yes' : 'no')} />
				<TextFieldRow label="Limit" type="number" min="1" max="1000" value={preload.limit} onChange={(value) => update('preload', 'limit', value)} />
				<SwitchRow label="Homepage Crawl" description="Homepage links become priority cache-first URLs." checked={preload.crawlHomepage === 'yes'} onChange={(checked) => update('preload', 'crawlHomepage', checked ? 'yes' : 'no')} />
			</Panel>
			<Panel title="Preload Options" className="ams-full-width">
				<div className="ams-option-columns">
					<ToggleList title="Post Types" items={preload.postTypes || []} onToggle={(value, checked) => toggleList('postTypes', value, checked)} />
					<div className="ams-toggle-list">
						<h3>Homepage</h3>
						<SwitchRow label="Homepage" checked={preload.homepage === 'yes'} onChange={(checked) => update('preload', 'homepage', checked ? 'yes' : 'no')} />
					</div>
					<ToggleList title="Archive Pages" items={preload.archives || []} onToggle={(value, checked) => toggleList('archives', value, checked)} />
				</div>
				<p className="ams-muted">Preloader crawls homepage first, pushes visible internal links to front of queue, then uses selected post types and archives.</p>
			</Panel>
		</div>
	);
}

function PerformanceSettings({data, settings, updatePerformance, save, isBusy, queueImages, loadReports}) {
	const [tab, setTab] = useState('overview');
	const perf = settings.performance || {};
	const reports = data.optimization?.reports || {};
	const tabs = ['overview', 'requirements', 'optimization', 'images'];

	return (
		<>
			<div className="ams-tabs">
				{tabs.map((item) => (
					<button key={item} type="button" className={`ams-tab ${tab === item ? 'is-active' : ''}`} onClick={() => setTab(item)}>
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
						<StatCard icon={Image} title="Image Optimizer" value={reports.imageSavedLabel || '0 B'} detail={reports.imageQueueTotal ? `${reports.imageQueueCompleted || 0}/${reports.imageQueueTotal || 0} done, ${reports.imageQueue || 0} queued` : `${reports.imageQueue || 0} attachments queued`} progress={reports.imageProgress != null ? reports.imageProgress : 100} />
					</div>
					<div className="grid w-full grid-cols-1 items-stretch gap-4 2xl:grid-cols-2">
						<Panel title="Optimization Summary">
							<FactList items={[
								{label: 'Pages Optimized', value: reports.appliedPages || 0},
								{label: 'Total Saved', value: reports.savedLabel || '0 B'},
								{label: 'External UCSS Saved', value: reports.externalUcssSavedLabel || '0 B'},
								{label: 'Local UCSS Saved', value: reports.ucssSavedLabel || '0 B'},
								{label: 'JS Deferred', value: reports.jsDeferred || 0},
								{label: 'Image Queue', value: reports.imageQueue || 0}
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
						<SwitchRow key={key} label={label} checked={perf[key] === 'yes'} onChange={(checked) => updatePerformance(key, checked ? 'yes' : 'no')} />
					))}
					<TextFieldRow label="Critical image count" type="number" min="0" max="5" value={perf.critical_image_count} onChange={(value) => updatePerformance('critical_image_count', value)} />
					<TextFieldRow label="External CSS max file size" type="number" min="51200" max="1048576" value={perf.external_ucss_max_file_size} onChange={(value) => updatePerformance('external_ucss_max_file_size', value)} />
					<TextFieldRow label="Bun path" value={perf.bun_path || 'bun'} onChange={(value) => updatePerformance('bun_path', value)} />
					<TextFieldRow label="PurgeCSS path" value={perf.purgecss_path} onChange={(value) => updatePerformance('purgecss_path', value)} />
					<TextAreaRow label="UCSS safelist" value={perf.ucss_safelist} onChange={(value) => updatePerformance('ucss_safelist', value)} />
					<TextAreaRow label="JavaScript defer exclusions" value={perf.js_exclusions} onChange={(value) => updatePerformance('js_exclusions', value)} />
				</Panel>
			) : null}
			{tab === 'images' ? (
				<Panel title="Image Optimization">
					<SwitchRow label="Image Optimization" checked={perf.image_optimization === 'yes'} onChange={(checked) => updatePerformance('image_optimization', checked ? 'yes' : 'no')} />
					<SwitchRow label="Optimize images on upload" description="New uploads convert before offload when source files are local." checked={perf.image_optimize_on_upload === 'yes'} onChange={(checked) => updatePerformance('image_optimize_on_upload', checked ? 'yes' : 'no')} />
					<SwitchRow label="Serve generated images in HTML" checked={perf.image_rewrite_html === 'yes'} onChange={(checked) => updatePerformance('image_rewrite_html', checked ? 'yes' : 'no')} />
					<SwitchRow label="Allow remote URL rewrite" description="Keep disabled unless offload plugin syncs generated variants." checked={perf.image_remote_rewrite === 'yes'} onChange={(checked) => updatePerformance('image_remote_rewrite', checked ? 'yes' : 'no')} />
					<FactList items={[{label: 'Output format', value: 'WebP'}, {label: 'Primary upload format', value: 'WebP'}]} />
					<TextFieldRow label="Image quality" type="number" min="1" max="100" value={perf.image_quality} onChange={(value) => updatePerformance('image_quality', value)} />
					<TextFieldRow label="Background batch size" type="number" min="1" max="20" value={perf.image_batch_size} onChange={(value) => updatePerformance('image_batch_size', value)} />
					<SwitchRow label="Image placeholders" description="Store a tiny safe data URL and show it as a background while the final image loads." checked={perf.image_placeholders === 'yes'} onChange={(checked) => updatePerformance('image_placeholders', checked ? 'yes' : 'no')} />
					<TextAreaRow label="Media exclusions" value={perf.media_exclusions} onChange={(value) => updatePerformance('media_exclusions', value)} />
					<div className="ams-action-row">
						<AmsButton icon={Image} tone="primary" busy={isBusy} onPress={queueImages}>Start Optimize</AmsButton>
						<AmsButton onPress={loadReports}>Refresh Reports</AmsButton>
					</div>
				</Panel>
			) : null}
		</>
	);
}

function LatestReport({report}) {
	if (!report) {
		return <Panel title="Latest Page Report"><p className="ams-muted">No page optimization report yet.</p></Panel>;
	}

	return (
		<Panel title="Latest Page Report">
			<div className="ams-report-head">
				<strong>{report.displayUri || report.uri}</strong>
				<span>{report.beforeLabel} -&gt; {report.afterLabel}</span>
				<Pill tone={report.expandedBytes > 0 ? 'warning' : 'good'}>{report.expandedBytes > 0 ? `${report.expandedLabel} bigger` : `${report.savedLabel} saved`}</Pill>
			</div>
			<ul className="ams-feature-list">
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
		<Panel title="Recent Page Optimization Reports" action={<span className="ams-muted">{reports.loadedCount || rows.length} / {reports.totalReports || rows.length}</span>}>
			<ul className="ams-report-list">
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
			{reports.hasMore ? <div className="ams-loadmore"><AmsButton onPress={loadReports}>Load 5 more</AmsButton></div> : null}
		</Panel>
	);
}

function RulesSettings({settings, update, save, isBusy}) {
	const rules = settings.rules || {};

	return (
		<Panel title="Rules">
			<SwitchRow label="Enable exclusion rules" checked={rules.enabled === 'yes'} onChange={(checked) => update('rules', 'enabled', checked ? 'yes' : 'no')} />
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
				<SwitchRow label="Record cache statistics" checked={settings.statistics?.enabled === 'enable'} onChange={(checked) => update('statistics', 'enabled', checked ? 'enable' : 'disable')} />
			</Panel>
		</div>
	);
}

function SimpleTogglePage({title, children}) {
	return <Panel title={title}>{children}</Panel>;
}

function ExpertSettings({settings, update, save, isBusy}) {
	const code = settings.cache?.expertModeCode || '';
	const [copied, setCopied] = useState(false);
	const copyCode = () => {
		if (!code) {
			return;
		}

		const done = () => {
			setCopied(true);
			window.setTimeout(() => setCopied(false), 1600);
		};

		if (navigator.clipboard?.writeText) {
			navigator.clipboard.writeText(code).then(done).catch(() => {});
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = code;
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
				<SwitchRow label="Expert Mode" description="Reads runtime config early from wp-config.php. Guest-only bypass stays active." checked={settings.cache?.expertModeStatus === 'enable'} onChange={(checked) => update('cache', 'expertModeStatus', checked ? 'enable' : 'disable')} />
			</Panel>
			<Panel title="Configure Code Block" action={<div className="ams-action-row ams-action-row--compact"><Pill tone={settings.cache?.expertModeReady ? 'good' : 'warning'}>{settings.cache?.expertModeReady ? 'Ready' : 'Not ready'}</Pill><AmsButton size="sm" icon={Copy} onPress={copyCode}>{copied ? 'Copied' : 'Copy Code'}</AmsButton></div>}>
				<p className="ams-muted">Paste this block into wp-config.php above ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œThatÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢s all, stop editingÃƒÂ¢Ã¢â€šÂ¬Ã‚Â. It loads config before WordPress boots.</p>
				<pre
					className="ams-code-block ams-code-block--copyable"
					role="button"
					tabIndex={0}
					title="Click to copy"
					onClick={copyCode}
					onKeyDown={(event) => {
						if (event.key === 'Enter' || event.key === ' ') {
							event.preventDefault();
							copyCode();
						}
					}}
				><code>{code}</code></pre>
			</Panel>
		</div>
	);
}

function BenchmarkSettings({settings, update, save, isBusy}) {
	const displays = [{value: 'text', label: 'Text'}, {value: 'icon', label: 'Icon'}, {value: 'both', label: 'Both'}];

	return (
		<Panel title="Benchmark">
			<SwitchRow label="Frontend Widget" checked={settings.benchmark?.widget === 'yes'} onChange={(checked) => update('benchmark', 'widget', checked ? 'yes' : 'no')} />
			<MiniSelectRow label="Widget Display" value={settings.benchmark?.widgetDisplay || 'both'} options={displays} onChange={(value) => update('benchmark', 'widgetDisplay', value)} />
			<SwitchRow label="Footer Text" checked={settings.benchmark?.footer === 'yes'} onChange={(checked) => update('benchmark', 'footer', checked ? 'yes' : 'no')} />
			<MiniSelectRow label="Footer Display" value={settings.benchmark?.footerDisplay || 'text'} options={displays} onChange={(value) => update('benchmark', 'footerDisplay', value)} />
			<div className="ams-benchmark-preview">
				<span>ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¿ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã†â€™ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¡</span>
				<strong>ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ | ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‚Â 0.42 ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¸ | ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â  32 MB</strong>
			</div>
		</Panel>
	);
}

function AboutPage({data}) {
	return (
		<div className="ams-about-page">
			<section className="ams-about-hero">
				<div>
					<Pill tone="info">AMS Cache 3.0.6</Pill>
					<h2>Performance console for real WordPress pages.</h2>
					<p>AMS Cache combines guest-only page caching, preload control, page optimization, External UCSS, JS analysis, and image conversion in one clean WordPress admin experience.</p>
					<div className="ams-action-row">
						<AmsButton tone="primary">AMS Technical Team</AmsButton>
						<AmsButton>Simple Cache Core</AmsButton>
					</div>
				</div>
				<div className="ams-about-window" aria-hidden="true">
					<div className="ams-window-dots"><span /><span /><span /></div>
					<div className="ams-mini-metric"><strong>{data.optimization?.reqPassed || 0} / {data.optimization?.reqTotal || 0}</strong><span>requirements</span></div>
					<div className="ams-mini-metric"><strong>Guest</strong><span>safe cache</span></div>
					<div className="ams-mini-metric"><strong>UCSS</strong><span>local engine</span></div>
					<div className="ams-mini-metric"><strong>WebP</strong><span>image path</span></div>
					<div className="ams-progress"><span style={{width: '82%'}} /></div>
					<div className="ams-progress"><span style={{width: '58%'}} /></div>
				</div>
			</section>
			<div className="ams-about-tabs">
				<button type="button" className="is-active">WhatÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢s New</button>
				<button type="button">Performance</button>
				<button type="button">Security</button>
				<button type="button">Credits</button>
			</div>
			<Panel title="Welcome to AMS Cache 3.0.6" className="ams-full-width">
				<p className="ams-center-copy">This release focuses on cleaner dashboard surfaces, safer upload-time image conversion before offload, restored Expert Mode configuration, and page cache controls that stay guest-only.</p>
			</Panel>
			<div className="ams-about-feature-grid grid w-full grid-cols-1 gap-4 xl:grid-cols-3">
				<Panel title="Cache-first Pages"><p>Preload starts from homepage links, selected post types, and archives so guests get warm cache before first visit.</p></Panel>
				<Panel title="External UCSS"><p>Eligible same-site CSS files are tested with PurgeCSS and only inlined into cached HTML when result is smaller.</p></Panel>
				<Panel title="Image Optimizer Path"><p>New uploads can become WebP metadata before offload plugins read and move files.</p></Panel>
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

	const save = () => run('scm_action_dashboard_save_settings', {settings: JSON.stringify(settings)});
	const refresh = () => run('scm_action_dashboard_status');

	// Auto-refresh when image queue is active (every 8 seconds).
	const imageQueue = data.optimization?.reports?.imageQueue || 0;
	const imageQueueTotal = data.optimization?.reports?.imageQueueTotal || 0;
	const imageProgress = data.optimization?.reports?.imageProgress;

	useEffect(() => {
		if (imageQueue <= 0 && imageQueueTotal <= 0) return;

		const timer = setInterval(() => {
			request('scm_action_dashboard_status')
				.then((result) => { if (result?.status) setStatus(result.status); })
				.catch(() => {});
		}, 8000);

		return () => clearInterval(timer);
	}, [imageQueue, imageQueueTotal]);
	const loadReports = () => {
		const reports = data.optimization?.reports || {};
		return run('scm_action_dashboard_reports', {offset: String(reports.loadedCount || 0)}).then((result) => {
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
		clearAll: () => run('scm_action_dashboard_clear_cache'),
		clearType: (cacheType) => run('scm_action_dashboard_clear_cache_type', {cacheType}),
		runPreload: () => run('scm_action_dashboard_preload'),
		purgeHomepage: () => run('scm_action_dashboard_purge_homepage'),
		queueImages: () => run('scm_action_dashboard_queue_images'),
		testDriver: (driver) => run('scm_action_dashboard_test_driver', {driver})
	};

	return (
		<div className="ams-admin">
			<aside className="ams-sidebar" aria-label="AMS Cache 3.0.6">
				<div className="ams-logo">AMS</div>
				{NAV.map(({key, label, icon: Icon}) => (
					<button key={key} className={view === key ? 'is-active' : ''} onClick={() => setView(key)} title={label} aria-label={label}>
						<Icon size={19} />
					</button>
				))}
			</aside>
			<main className="ams-main">
				<header className="ams-topbar">
					<div>
						<h1>{viewLabel}</h1>
						<p>Live cache controls and page optimization state. <strong>Updated:</strong> {data.generatedAt}</p>
					</div>
					{imageQueueTotal > 0 ? (
						<div className="ams-image-queue-progress" style={{display:'flex',flexDirection:'column',gap:4,minWidth:180}}>
							<div style={{display:'flex',justifyContent:'space-between',fontSize:11,color:'var(--ams-muted,#6b7280)'}}>
								<span>Image optimization</span>
								<span>{imageQueueTotal - imageQueue}/{imageQueueTotal}</span>
							</div>
							<div className="ams-progress" style={{marginTop:0}}><span style={{width:percent(imageProgress)}} /></div>
						</div>
					) : null}
					<div className="ams-toolbar-actions">
						<AmsButton icon={RefreshCcw} iconOnly busy={busy} onPress={actions.refresh} aria-label="Refresh">Refresh</AmsButton>
						<AmsButton icon={Play} tone="primary" busy={busy} onPress={actions.runPreload}>Run Preload</AmsButton>
						<AmsButton icon={Home} iconOnly onPress={actions.purgeHomepage} aria-label="Purge Homepage">Purge Homepage</AmsButton>
						<AmsButton icon={Image} iconOnly onPress={actions.queueImages} aria-label="Queue Images">Queue Images</AmsButton>
						<AmsButton icon={Trash2} iconOnly tone="danger" onPress={actions.clearAll} aria-label="Clear All">Clear All</AmsButton>
					</div>
				</header>
				{notice ? (
					<div className={`ams-notice ams-notice--${notice.type}`} role="status" aria-live="polite">
						<span>{notice.text}</span>
						<button type="button" className="ams-notice__close" onClick={() => setNotice(null)} aria-label="Clear notification">
							<X size={16} aria-hidden="true" />
						</button>
					</div>
				) : null}
				<section className="ams-view">
					{view === 'overview' ? <Overview data={data} go={setView} actions={actions} /> : null}
					{view === 'cache' ? <CacheSettings settings={settings} update={update} save={save} isBusy={busy} testDriver={actions.testDriver} /> : null}
					{view === 'preload' ? <PreloadSettings data={data} settings={settings} update={update} save={save} isBusy={busy} runPreload={actions.runPreload} purgeHomepage={actions.purgeHomepage} /> : null}
					{view === 'performance' ? <PerformanceSettings data={data} settings={settings} updatePerformance={updatePerformance} save={save} isBusy={busy} queueImages={actions.queueImages} loadReports={loadReports} /> : null}
					{view === 'rules' ? <RulesSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'statistics' ? <StatisticsSettings data={data} settings={settings} update={update} save={save} isBusy={busy} clearType={actions.clearType} /> : null}
					{view === 'expert' ? <ExpertSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'benchmark' ? <BenchmarkSettings settings={settings} update={update} save={save} isBusy={busy} /> : null}
					{view === 'woocommerce' ? (
						<SimpleTogglePage title="WooCommerce">
							{settings.woocommerce?.active ? null : <Pill tone="warning">WooCommerce is not active</Pill>}
							<SwitchRow label="WooCommerce cache rules" checked={settings.woocommerce?.enabled === 'yes'} onChange={(checked) => update('woocommerce', 'enabled', checked ? 'yes' : 'no')} />
							<SwitchRow label="Clear after payment complete" checked={settings.woocommerce?.paymentComplete === 'yes'} onChange={(checked) => update('woocommerce', 'paymentComplete', checked ? 'yes' : 'no')} />
						</SimpleTogglePage>
					) : null}
					{view === 'about' ? <AboutPage data={data} /> : null}
				</section>
				{SAVE_VIEWS.has(view) ? <div className="ams-page-spacer" aria-hidden="true" /> : null}
				{SAVE_VIEWS.has(view) ? <SaveBar isBusy={busy} onSave={save} global /> : null}
			</main>
		</div>
	);
}

const root = document.getElementById('ams-cache-admin-root');

if (root) {
	createRoot(root).render(<App />);
}
