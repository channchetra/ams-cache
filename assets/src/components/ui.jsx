import React, {useEffect, useId, useRef, useState} from 'react';
import {
	AlertCircle,
	Check,
	CheckCircle2,
	ChevronDown,
	Copy,
	Info,
	X,
	XCircle
} from 'lucide-react';

function copyToClipboard(value) {
	if (navigator.clipboard?.writeText) {
		return navigator.clipboard.writeText(value);
	}

	return new Promise((resolve, reject) => {
		try {
			const textarea = document.createElement('textarea');

			textarea.value = value;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.left = '-9999px';
			document.body.appendChild(textarea);
			textarea.select();
			document.execCommand('copy');
			document.body.removeChild(textarea);
			resolve();
		} catch (error) {
			reject(error);
		}
	});
}

export function Button({
	children,
	icon: Icon,
	variant = 'secondary',
	size = 'md',
	busy = false,
	disabled = false,
	iconOnly = false,
	onClick,
	title,
	type = 'button',
	className = ''
}) {
	const label = title || (iconOnly && typeof children === 'string' ? children : undefined);
	const classes = [
		'khse-btn',
		`khse-btn--${variant}`,
		`khse-btn--${size}`,
		iconOnly ? 'khse-btn--icon' : '',
		className
	].filter(Boolean).join(' ');

	return (
		<button
			type={type}
			className={classes}
			disabled={disabled || busy}
			onClick={onClick}
			title={title}
			aria-label={label}
			aria-busy={busy || undefined}
		>
			{busy ? <span className="khse-spinner" aria-hidden="true" /> : Icon ? <Icon size={size === 'sm' ? 15 : 17} aria-hidden="true" /> : null}
			{iconOnly ? <span className="screen-reader-text">{children}</span> : <span>{children}</span>}
		</button>
	);
}

export function Card({title, description, action, children, className = '', padded = true}) {
	const hasHead = Boolean(title || description || action);

	return (
		<section className={`khse-card${className ? ` ${className}` : ''}`}>
			{hasHead ? (
				<header className="khse-card__head">
					<div className="khse-card__heading">
						{title ? <h2 className="khse-card__title">{title}</h2> : null}
						{description ? <p className="khse-card__description">{description}</p> : null}
					</div>
					{action ? <div className="khse-card__action">{action}</div> : null}
				</header>
			) : null}
			{children ? (
				<div className={`khse-card__body${padded ? '' : ' khse-card__body--flush'}`}>{children}</div>
			) : null}
		</section>
	);
}

export function Field({label, description, htmlFor, hint, children}) {
	return (
		<div className="khse-field">
			<div className="khse-field__head">
				{label ? <label className="khse-field__label" htmlFor={htmlFor}>{label}</label> : null}
				{description ? <p className="khse-field__description">{description}</p> : null}
			</div>
			<div className="khse-field__control">{children}</div>
			{hint ? <p className="khse-field__hint">{hint}</p> : null}
		</div>
	);
}

export function Switch({checked, onChange, label, description, disabled = false, ariaLabel}) {
	const labelId = useId();

	return (
		<button
			type="button"
			className={`khse-switch${checked ? ' is-on' : ''}`}
			role="switch"
			aria-checked={Boolean(checked)}
			aria-labelledby={label ? labelId : undefined}
			aria-label={!label ? ariaLabel : undefined}
			disabled={disabled}
			onClick={() => onChange(!checked)}
		>
			<span className="khse-switch__text">
				{label ? <span className="khse-switch__label" id={labelId}>{label}</span> : null}
				{description ? <span className="khse-switch__description">{description}</span> : null}
			</span>
			<span className="khse-switch__track" aria-hidden="true">
				<span className="khse-switch__thumb" />
			</span>
		</button>
	);
}

export function TextInput({value, onChange, type = 'text', min, max, placeholder, id, ariaLabel, suffix}) {
	const input = (
		<input
			className={`khse-input${suffix ? ' khse-input--suffix' : ''}`}
			id={id}
			type={type}
			min={min}
			max={max}
			placeholder={placeholder}
			value={value ?? ''}
			aria-label={ariaLabel}
			onChange={onChange}
		/>
	);

	if (!suffix) {
		return input;
	}

	return (
		<span className="khse-input-wrap">
			{input}
			<span className="khse-input-suffix">{suffix}</span>
		</span>
	);
}

export function Select({value, onChange, options = [], id, ariaLabel}) {
	return (
		<select
			className="khse-input khse-select"
			id={id}
			aria-label={ariaLabel}
			value={String(value ?? '')}
			onChange={onChange}
		>
			{options.map((option) => (
				<option key={option.value} value={option.value}>{option.label}</option>
			))}
		</select>
	);
}

export function Textarea({value, onChange, rows = 5, placeholder, id, mono = false}) {
	return (
		<textarea
			className={`khse-input khse-textarea${mono ? ' khse-textarea--mono' : ''}`}
			id={id}
			rows={rows}
			placeholder={placeholder}
			value={value ?? ''}
			onChange={onChange}
		/>
	);
}

export function Pill({tone = 'neutral', dot = false, children}) {
	return (
		<span className={`khse-pill khse-pill--${tone}`}>
			{dot ? <span className="khse-pill__dot" aria-hidden="true" /> : null}
			{children}
		</span>
	);
}

export function Segmented({items = [], value, onChange, label}) {
	return (
		<div className="khse-segmented" role="group" aria-label={label}>
			{items.map((item) => {
				const Icon = item.icon;
				const active = item.value === value;

				return (
					<button
						key={item.value}
						type="button"
						className={`khse-segmented__item${active ? ' is-active' : ''}`}
						aria-pressed={active}
						onClick={() => onChange(item.value)}
					>
						{Icon ? <Icon size={14} aria-hidden="true" /> : null}
						<span>{item.label}</span>
					</button>
				);
			})}
		</div>
	);
}

export function Progress({value, tone = 'blue', label}) {
	const normalized = Math.max(0, Math.min(100, Number(value) || 0));

	return (
		<div
			className="khse-progress"
			role="progressbar"
			aria-valuemin={0}
			aria-valuemax={100}
			aria-valuenow={Math.round(normalized)}
			aria-label={label}
		>
			<span className={`khse-progress__fill khse-progress__fill--${tone}`} style={{width: `${normalized}%`}} />
		</div>
	);
}

export function Stat({icon: Icon, label, value, detail, tone = 'blue', progress}) {
	return (
		<div className="khse-stat" data-tone={tone}>
			<div className="khse-stat__top">
				{Icon ? <span className="khse-stat__icon" aria-hidden="true"><Icon size={17} /></span> : null}
				<span className="khse-stat__label">{label}</span>
			</div>
			<strong className="khse-stat__value">{value}</strong>
			{detail ? <span className="khse-stat__detail">{detail}</span> : null}
			{progress === null || progress === undefined ? null : <Progress value={progress} tone={tone} label={label} />}
		</div>
	);
}

export function Ring({value, size = 132, stroke = 11, tone = 'blue', children}) {
	const normalized = Math.max(0, Math.min(100, Number(value) || 0));
	const radius = (size - stroke) / 2;
	const circumference = 2 * Math.PI * radius;
	const dash = (normalized / 100) * circumference;

	return (
		<div
			className={`khse-ring khse-ring--${tone}`}
			style={{width: `${size}px`, height: `${size}px`}}
			role="img"
			aria-label={`${Math.round(normalized)} percent`}
		>
			<svg viewBox={`0 0 ${size} ${size}`} aria-hidden="true">
				<circle
					className="khse-ring__track"
					cx={size / 2}
					cy={size / 2}
					r={radius}
					strokeWidth={stroke}
				/>
				<circle
					className="khse-ring__value"
					cx={size / 2}
					cy={size / 2}
					r={radius}
					strokeWidth={stroke}
					strokeDasharray={`${dash} ${circumference}`}
				/>
			</svg>
			<div className="khse-ring__label">{children ?? <strong>{Math.round(normalized)}%</strong>}</div>
		</div>
	);
}

export function StatusRow({status = 'note', title, description, action}) {
	const Icon = status === 'pass' ? CheckCircle2 : status === 'fail' ? XCircle : Info;

	return (
		<div className={`khse-status-row khse-status-row--${status}`}>
			<Icon className="khse-status-row__icon" size={17} aria-hidden="true" />
			<div className="khse-status-row__text">
				<strong>{title}</strong>
				{description ? <p>{description}</p> : null}
			</div>
			{action ? <div className="khse-status-row__action">{action}</div> : null}
		</div>
	);
}

export function EmptyState({icon: Icon, title, description, action}) {
	return (
		<div className="khse-empty">
			{Icon ? <span className="khse-empty__icon" aria-hidden="true"><Icon size={20} /></span> : null}
			<strong className="khse-empty__title">{title}</strong>
			{description ? <p className="khse-empty__description">{description}</p> : null}
			{action ? <div className="khse-empty__action">{action}</div> : null}
		</div>
	);
}

export function Disclosure({title, description, children, defaultOpen = false, action}) {
	return (
		<details className="khse-disclosure" open={defaultOpen}>
			<summary className="khse-disclosure__summary">
				<ChevronDown className="khse-disclosure__chevron" size={16} aria-hidden="true" />
				<span className="khse-disclosure__text">
					<span className="khse-disclosure__title">{title}</span>
					{description ? <span className="khse-disclosure__description">{description}</span> : null}
				</span>
				{action ? <span className="khse-disclosure__action">{action}</span> : null}
			</summary>
			<div className="khse-disclosure__body">{children}</div>
		</details>
	);
}

export function CopyButton({value, label = 'Copy', copiedLabel = 'Copied'}) {
	const [copied, setCopied] = useState(false);
	const timer = useRef(null);

	useEffect(() => () => window.clearTimeout(timer.current), []);

	const handleCopy = () => {
		if (!value) {
			return;
		}

		copyToClipboard(String(value))
			.then(() => {
				setCopied(true);
				window.clearTimeout(timer.current);
				timer.current = window.setTimeout(() => setCopied(false), 1600);
			})
			.catch(() => {});
	};

	return (
		<Button
			size="sm"
			icon={copied ? Check : Copy}
			onClick={handleCopy}
			disabled={!value}
			title={copied ? copiedLabel : label}
		>
			{copied ? copiedLabel : label}
		</Button>
	);
}

export function ConfirmDialog({open, title, description, confirmLabel = 'Confirm', tone = 'danger', onConfirm, onCancel}) {
	const dialog = useRef(null);

	useEffect(() => {
		const element = dialog.current;

		if (!element) {
			return;
		}

		if (open && !element.open && typeof element.showModal === 'function') {
			element.showModal();
		}

		if (!open && element.open) {
			element.close();
		}
	}, [open]);

	useEffect(() => {
		if (!open) {
			return undefined;
		}

		const onKeyDown = (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				onCancel();
			}
		};

		document.addEventListener('keydown', onKeyDown);

		return () => document.removeEventListener('keydown', onKeyDown);
	}, [open, onCancel]);

	return (
		<dialog
			ref={dialog}
			className="khse-dialog"
			aria-labelledby="khse-dialog-title"
			aria-describedby={description ? 'khse-dialog-description' : undefined}
			onCancel={(event) => {
				event.preventDefault();
				onCancel();
			}}
			onClick={(event) => {
				if (event.target === dialog.current) {
					onCancel();
				}
			}}
		>
			<div className="khse-dialog__panel">
				<div className={`khse-dialog__icon khse-dialog__icon--${tone}`} aria-hidden="true">
					{tone === 'danger' ? <AlertCircle size={20} /> : <Info size={20} />}
				</div>
				<div className="khse-dialog__content">
					<h2 className="khse-dialog__title" id="khse-dialog-title">{title}</h2>
					{description ? <p className="khse-dialog__description" id="khse-dialog-description">{description}</p> : null}
				</div>
				<div className="khse-dialog__actions">
					<Button variant="ghost" onClick={onCancel}>Cancel</Button>
					<Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm}>{confirmLabel}</Button>
				</div>
				<button type="button" className="khse-dialog__close" onClick={onCancel} aria-label="Close dialog">
					<X size={16} aria-hidden="true" />
				</button>
			</div>
		</dialog>
	);
}
