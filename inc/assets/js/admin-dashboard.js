(function (window, document) {
	'use strict';

	var mount = document.getElementById('ams-cache-dashboard');

	if (!mount) {
		return;
	}

	if (!window.Vue || !window.amsCacheDashboard) {
		var message = window.amsCacheDashboard && window.amsCacheDashboard.i18n ? window.amsCacheDashboard.i18n.vueFail : 'AMS Cache dashboard could not load Vue 3. Check CDN access or override the scm_vue_cdn_url filter.';
		var notice = document.createElement('div');
		var paragraph = document.createElement('p');

		mount.removeAttribute('v-cloak');
		notice.className = 'notice notice-error';
		paragraph.textContent = message;
		notice.appendChild(paragraph);
		mount.textContent = '';
		mount.appendChild(notice);
		return;
	}

	var createApp = window.Vue.createApp;
	var config = window.amsCacheDashboard;

	function enhanceSettingsSections(form) {
		form.classList.add('ams-settings-form');

		Array.prototype.forEach.call(form.querySelectorAll('h2'), function (heading) {
			heading.classList.add('ams-settings-heading');
		});

		Array.prototype.forEach.call(form.querySelectorAll('.form-table'), function (table) {
			table.classList.add('ams-settings-table');
		});

		Array.prototype.forEach.call(form.querySelectorAll('.widefat, .scm-table-ttl-example'), function (table) {
			table.classList.add('ams-data-table');
		});

		Array.prototype.forEach.call(form.querySelectorAll('p > em'), function (help) {
			help.parentElement.classList.add('ams-field-help');
		});

		Array.prototype.forEach.call(form.querySelectorAll('.scm-option-item'), function (item) {
			if (item.querySelector('input[type="checkbox"]')) {
				item.classList.add('ams-checkbox-item');
			}

			if (item.querySelector('input[type="text"], input[type="number"], input[type="password"], select, textarea')) {
				item.classList.add('ams-field-row');
			}
		});

		normalizeCheckboxes(form);
		normalizeRadioGroups(form);
		normalizeDisabledRadioGroups(form);
		normalizeStaticRadios(form);

		if (!form.querySelector('.ams-form-status')) {
			var status = document.createElement('div');

			status.className = 'ams-form-status';
			status.setAttribute('aria-live', 'polite');
			form.appendChild(status);
		}
	}

	function getLabelText(form, input) {
		var labels = form.querySelectorAll('label');
		var match = null;

		Array.prototype.some.call(labels, function (label) {
			if (label.htmlFor === input.id) {
				match = label;
				return true;
			}

			return false;
		});

		if (!match) {
			return input.value;
		}

		var ownText = Array.prototype.map.call(match.childNodes, function (node) {
			return node.nodeType === window.Node.TEXT_NODE ? node.textContent : '';
		}).join(' ').replace(/\s+/g, ' ').trim();

		return ownText || match.textContent.replace(/\s+/g, ' ').trim() || input.value;
	}

	function normalizeRadioGroups(form) {
		var groups = {};

		Array.prototype.forEach.call(form.querySelectorAll('input[type="radio"][name]'), function (input) {
			if (!groups[input.name]) {
				groups[input.name] = [];
			}

			groups[input.name].push(input);
		});

		Object.keys(groups).forEach(function (name) {
			var inputs = groups[name];
			var first = inputs[0];
			var firstSource = first.closest('.scm-option-item') || first.parentElement;
			var wrapper;

			if (inputs.length < 2 || inputs.length > 4 || !firstSource || firstSource.dataset.amsRadioNormalized === 'true') {
				return;
			}

			if (isSwitchGroup(form, inputs)) {
				normalizeSwitchGroup(form, inputs, firstSource);
				return;
			}

			wrapper = document.createElement('div');
			wrapper.className = 'ams-segmented-control';
			mountChoiceWrapper(firstSource, wrapper);

			inputs.forEach(function (input) {
				var source = input.closest('.scm-option-item') || input.parentElement;
				var labelText = getLabelText(form, input);
				var oldLabels = form.querySelectorAll('label');
				var label = document.createElement('label');
				var text = document.createElement('span');

				Array.prototype.forEach.call(oldLabels, function (oldLabel) {
					if (oldLabel.htmlFor === input.id) {
						oldLabel.remove();
					}
				});

				label.className = 'ams-segmented-item';
				label.htmlFor = input.id;
				text.textContent = labelText;
				label.appendChild(input);
				label.appendChild(text);
				wrapper.appendChild(label);

				if (source && source !== wrapper && source !== firstSource.parentNode) {
					source.dataset.amsRadioNormalized = 'true';

					if (!source.querySelector('input, select, textarea') && source.textContent.trim() === '') {
						source.remove();
					}
				}
			});
		});
	}

	function mountChoiceWrapper(source, wrapper) {
		if (source.querySelector('.scm-label-wrapper')) {
			source.appendChild(wrapper);
			return;
		}

		source.parentNode.insertBefore(wrapper, source);
	}

	function removeEmptyChoiceArtifacts(source) {
		Array.prototype.forEach.call(source.querySelectorAll('span'), function (node) {
			if (!node.classList.contains('ams-segmented-item') && !node.querySelector('input, select, textarea') && node.textContent.trim() === '') {
				node.remove();
			}
		});
	}

	function normalizeCheckboxes(form) {
		Array.prototype.forEach.call(form.querySelectorAll('.scm-option-item input[type="checkbox"]'), function (input) {
			var source = input.closest('.scm-option-item');
			var labelText;
			var wrapper;
			var control;
			var track;
			var thumb;
			var label;
			var oldLabels;

			if (!source || input.classList.contains('ams-checkbox-input')) {
				return;
			}

			labelText = getLabelText(form, input);
			wrapper = document.createElement('div');
			control = document.createElement('button');
			track = document.createElement('span');
			thumb = document.createElement('span');
			label = document.createElement('span');
			oldLabels = form.querySelectorAll('label');

			wrapper.className = 'ams-switch-control ams-checkbox-switch-control';
			control.type = 'button';
			control.className = 'ams-switch';
			control.disabled = input.disabled;
			track.className = 'ams-switch-track';
			thumb.className = 'ams-switch-thumb';
			label.className = 'ams-switch-label';
			label.textContent = labelText;
			track.appendChild(thumb);
			control.appendChild(track);
			wrapper.appendChild(control);
			wrapper.appendChild(label);
			source.parentNode.insertBefore(wrapper, source);

			Array.prototype.forEach.call(oldLabels, function (oldLabel) {
				if (oldLabel.htmlFor === input.id) {
					oldLabel.remove();
				}
			});

			function syncCheckbox() {
				control.classList.toggle('is-checked', !!input.checked);
				control.setAttribute('aria-checked', input.checked ? 'true' : 'false');
			}

			control.setAttribute('role', 'switch');
			control.setAttribute('aria-checked', input.checked ? 'true' : 'false');
			control.addEventListener('click', function () {
				input.checked = !input.checked;
				input.dispatchEvent(new window.Event('change', { bubbles: true }));
				syncCheckbox();
			});

			Array.prototype.forEach.call(source.querySelectorAll('input[type="hidden"]'), function (hiddenInput) {
				if (hiddenInput.name === input.name) {
					wrapper.appendChild(hiddenInput);
				}
			});

			input.classList.add('ams-checkbox-input');
			wrapper.appendChild(input);
			source.dataset.amsCheckboxNormalized = 'true';

			if (!source.querySelector('input, select, textarea') && source.textContent.trim() === '') {
				source.remove();
			}

			syncCheckbox();
		});
	}

	function normalizeToken(value) {
		return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
	}

	function formatChoiceLabel(value) {
		var labels = {
			enable: 'Enable',
			disable: 'Disable',
			yes: 'Yes',
			no: 'No'
		};
		var token = normalizeToken(value);

		return labels[token] || String(value || '').trim();
	}

	function isSwitchGroup(form, inputs) {
		var values = inputs.map(function (input) {
			return normalizeToken(input.value);
		}).sort();
		var labels = inputs.map(function (input) {
			return normalizeToken(getLabelText(form, input));
		}).sort();

		return inputs.length === 2
			&& (
				values.join('|') === 'disable|enable'
				|| values.join('|') === 'no|yes'
				|| labels.join('|') === 'disable|enable'
				|| labels.join('|') === 'no|yes'
			);
	}

	function normalizeSwitchGroup(form, inputs, firstSource) {
		var onInput = inputs.filter(function (input) {
			return normalizeToken(input.value) === 'enable' || normalizeToken(input.value) === 'yes';
		})[0] || inputs[0];
		var offInput = inputs.filter(function (input) {
			return input !== onInput;
		})[0];
		var wrapper = document.createElement('div');
		var control = document.createElement('button');
		var track = document.createElement('span');
		var thumb = document.createElement('span');
		var label = document.createElement('span');
		var sourceParent = firstSource.parentNode;

		wrapper.className = 'ams-switch-control';
		control.type = 'button';
		control.className = 'ams-switch';
		track.className = 'ams-switch-track';
		thumb.className = 'ams-switch-thumb';
		label.className = 'ams-switch-label';
		track.appendChild(thumb);
		control.appendChild(track);
		wrapper.appendChild(control);
		wrapper.appendChild(label);
		mountChoiceWrapper(firstSource, wrapper);

		function syncSwitch() {
			var enabled = !!onInput.checked;

			control.classList.toggle('is-checked', enabled);
			control.setAttribute('aria-checked', enabled ? 'true' : 'false');
			label.textContent = formatChoiceLabel(getLabelText(form, enabled ? onInput : offInput));
		}

		control.setAttribute('role', 'switch');
		control.setAttribute('aria-checked', onInput.checked ? 'true' : 'false');
		control.addEventListener('click', function () {
			onInput.checked = !onInput.checked;
			offInput.checked = !onInput.checked;
			onInput.dispatchEvent(new window.Event('change', { bubbles: true }));
			offInput.dispatchEvent(new window.Event('change', { bubbles: true }));
			syncSwitch();
		});

		inputs.forEach(function (input) {
			var source = input.closest('.scm-option-item') || input.parentElement;
			var oldLabels = form.querySelectorAll('label');

			Array.prototype.forEach.call(oldLabels, function (oldLabel) {
				if (oldLabel.htmlFor === input.id) {
					oldLabel.remove();
				}
			});

			input.classList.add('ams-switch-input');
			wrapper.appendChild(input);

			if (source && source !== wrapper && source !== sourceParent) {
				source.dataset.amsRadioNormalized = 'true';
				removeEmptyChoiceArtifacts(source);

				if (!source.querySelector('input, select, textarea') && source.textContent.trim() === '') {
					source.remove();
				}
			}
		});

		syncSwitch();
	}

	function humanizeRadioValue(value) {
		if (normalizeToken(value) === 'tcp') {
			return 'TCP';
		}

		if (normalizeToken(value) === 'socket') {
			return 'Unix Socket';
		}

		return String(value || '');
	}

	function normalizeDisabledRadioGroups(form) {
		Array.prototype.forEach.call(form.querySelectorAll('.scm-option-item'), function (source) {
			var inputs = source.querySelectorAll('input[type="radio"]:disabled');
			var wrapper;

			if (inputs.length < 2 || source.dataset.amsRadioNormalized === 'true') {
				return;
			}

			wrapper = document.createElement('div');
			wrapper.className = 'ams-segmented-control is-disabled';
			mountChoiceWrapper(source, wrapper);

			Array.prototype.forEach.call(inputs, function (input) {
				var item = document.createElement('span');
				var text = document.createElement('span');

				item.className = 'ams-segmented-item';
				text.textContent = humanizeRadioValue(input.value);
				item.appendChild(input);
				item.appendChild(text);
				wrapper.appendChild(item);
			});

			Array.prototype.forEach.call(source.querySelectorAll('span label'), function (label) {
				label.remove();
			});

			source.dataset.amsRadioNormalized = 'true';
			removeEmptyChoiceArtifacts(source);

			if (!source.querySelector('input, select, textarea') && source.textContent.trim() === '') {
				source.remove();
			}
		});
	}

	function normalizeStaticRadios(form) {
		Array.prototype.forEach.call(form.querySelectorAll('input[type="radio"]'), function (input) {
			var sameNameCount = input.name ? form.querySelectorAll('input[type="radio"][name="' + input.name + '"]').length : 0;
			var source = input.closest('.scm-option-item') || input.parentElement;
			var wrapper;
			var label;
			var oldLabels;

			if (
				input.classList.contains('ams-switch-input')
				|| input.closest('.ams-segmented-control')
				|| input.closest('.ams-static-choice')
				|| (!input.disabled && sameNameCount !== 1)
				|| !source
			) {
				return;
			}

			wrapper = document.createElement('span');
			label = document.createElement('span');
			oldLabels = form.querySelectorAll('label');

			wrapper.className = 'ams-static-choice';
			label.textContent = getLabelText(form, input);

			Array.prototype.forEach.call(oldLabels, function (oldLabel) {
				if (oldLabel.htmlFor === input.id) {
					oldLabel.remove();
				}
			});

			source.parentNode.insertBefore(wrapper, source);
			input.classList.add('ams-static-input');
			wrapper.appendChild(input);
			wrapper.appendChild(label);

			if (!source.querySelector('input, select, textarea') && source.textContent.trim() === '') {
				source.remove();
			}
		});
	}

	function enhanceSettingsForms(app) {
		Array.prototype.forEach.call(mount.querySelectorAll('form'), function (form) {
			if (!form.querySelector('input[name="option_page"]')) {
				return;
			}

			if (form.dataset.amsEnhanced === 'true') {
				return;
			}

			form.dataset.amsEnhanced = 'true';
			enhanceSettingsSections(form);
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				app.saveForm(form);
			});
		});
	}

	createApp({
		data: function () {
			return {
				data: config.status,
				view: config.view || 'overview',
				isBusy: false,
				activeAction: '',
				statsFilter: '',
				isLoadingReports: false,
				selectedReport: config.status.optimization.reports.reports[0] || null,
				notice: {
					type: '',
					text: ''
				}
			};
		},
		computed: {
			currentViewLabel: function () {
				var labels = config.i18n.views;

				return labels[this.view] || labels.overview;
			},
			visibleStats: function () {
				var rows = this.data.stats.rows.filter(function (row) {
					return row.rows > 0;
				});

				if (rows.length > 0) {
					return rows.slice(0, 10);
				}

				return this.data.stats.rows.slice(0, 6);
			},
			filteredStats: function () {
				var term = normalizeToken(this.statsFilter);

				if (!term) {
					return this.data.stats.rows;
				}

				return this.data.stats.rows.filter(function (row) {
					return normalizeToken(row.label).indexOf(term) !== -1 || normalizeToken(row.type).indexOf(term) !== -1;
				});
			}
		},
		mounted: function () {
			enhanceSettingsForms(this);
		},
		methods: {
			request: function (action, fields) {
				var app = this;
				var body = new window.URLSearchParams();

				body.append('action', action);
				body.append('_wpnonce', config.nonce);

				Object.keys(fields || {}).forEach(function (key) {
					body.append(key, fields[key]);
				});

				app.isBusy = true;
				app.activeAction = action;
				app.notice = {
					type: '',
					text: config.i18n.loading
				};

				return window.fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (!payload || !payload.success) {
							throw new Error(payload && payload.data && payload.data.message ? payload.data.message : config.i18n.failed);
						}

						if (payload.data && payload.data.status) {
							app.data = payload.data.status;

							if (!app.selectedReport && app.data.optimization.reports.reports.length > 0) {
								app.selectedReport = app.data.optimization.reports.reports[0];
							}
						}

						app.notice = {
							type: 'success',
							text: payload.data && payload.data.message ? payload.data.message : ''
						};
					})
					.catch(function (error) {
						app.notice = {
							type: 'error',
							text: error.message || config.i18n.failed
						};
					})
					.finally(function () {
						app.isBusy = false;
						app.activeAction = '';
					});
			},
			refresh: function () {
				return this.request('scm_action_dashboard_status');
			},
			clearCache: function () {
				return this.request('scm_action_dashboard_clear_cache');
			},
			clearCacheType: function (type) {
				return this.request('scm_action_dashboard_clear_cache_type', {
					cacheType: type
				});
			},
			runPreload: function () {
				return this.request('scm_action_dashboard_preload');
			},
			purgeHomepage: function () {
				return this.request('scm_action_dashboard_purge_homepage');
			},
			refreshStatusQuietly: function () {
				var app = this;
				var body = new window.URLSearchParams();

				body.append('action', 'scm_action_dashboard_status');
				body.append('_wpnonce', config.nonce);

				return window.fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (!payload || !payload.success || !payload.data || !payload.data.status) {
							throw new Error(config.i18n.failed);
						}

						app.data = payload.data.status;

						if (!app.selectedReport && app.data.optimization.reports.reports.length > 0) {
							app.selectedReport = app.data.optimization.reports.reports[0];
						}
					});
			},
			loadMoreReports: function () {
				var app = this;
				var body = new window.URLSearchParams();
				var reports = app.data.optimization.reports;

				if (app.isLoadingReports || !reports.hasMore) {
					return;
				}

				body.append('action', 'scm_action_dashboard_reports');
				body.append('_wpnonce', config.nonce);
				body.append('offset', reports.loadedCount);
				app.isLoadingReports = true;

				return window.fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (!payload || !payload.success || !payload.data) {
							throw new Error(config.i18n.failed);
						}

						reports.reports = reports.reports.concat(payload.data.reports || []);
						reports.loadedCount = payload.data.loadedCount || reports.reports.length;
						reports.totalReports = payload.data.totalReports || reports.totalReports;
						reports.hasMore = !!payload.data.hasMore;
					})
					.catch(function (error) {
						app.notice = {
							type: 'error',
							text: error.message || config.i18n.failed
						};
					})
					.finally(function () {
						app.isLoadingReports = false;
					});
			},
			saveForm: function (form) {
				var app = this;
				var status = form.querySelector('.ams-form-status');
				var buttons = form.querySelectorAll('button, input[type="submit"]');
				var action = form.getAttribute('action') || 'options.php';

				if (form.dataset.amsSaving === 'true') {
					return;
				}

				form.dataset.amsSaving = 'true';
				form.classList.add('is-saving');

				if (status) {
					status.className = 'ams-form-status is-saving';
					status.textContent = config.i18n.saving;
				}

				Array.prototype.forEach.call(buttons, function (button) {
					button.disabled = true;
				});

				return window.fetch(action, {
					method: 'POST',
					credentials: 'same-origin',
					body: new window.FormData(form),
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
					.then(function (response) {
						if (!response.ok || response.url.indexOf('wp-login.php') !== -1) {
							throw new Error(config.i18n.saveFail);
						}

						return app.refreshStatusQuietly();
					})
					.then(function () {
						app.notice = {
							type: 'success',
							text: config.i18n.saved
						};

						if (status) {
							status.className = 'ams-form-status is-saved';
							status.textContent = config.i18n.saved;
						}
					})
					.catch(function (error) {
						app.notice = {
							type: 'error',
							text: error.message || config.i18n.saveFail
						};

						if (status) {
							status.className = 'ams-form-status is-error';
							status.textContent = error.message || config.i18n.saveFail;
						}
					})
					.finally(function () {
						form.dataset.amsSaving = 'false';
						form.classList.remove('is-saving');

						Array.prototype.forEach.call(buttons, function (button) {
							button.disabled = false;
						});
					});
			},
			go: function (view) {
				var app = this;

				this.view = view;

				if (window.history && window.URL) {
					var url = new window.URL(window.location.href);

					url.searchParams.set('view', view);
					window.history.replaceState({}, '', url.toString());
				}

				this.$nextTick(function () {
					enhanceSettingsForms(app);
				});
			},
			selectReport: function (report) {
				this.selectedReport = report;
			},
			progressStyle: function (value) {
				var width = Math.max(0, Math.min(100, parseInt(value, 10) || 0));

				return {
					width: width + '%'
				};
			},
			statusClass: function (value) {
				return value ? 'is-good' : 'is-bad';
			},
			yesNo: function (value) {
				return value ? config.i18n.yes : config.i18n.no;
			},
			featureLabel: function (key) {
				var labels = config.i18n.features;

				return labels[key] || key;
			},
			featureStatusLabel: function (status) {
				var labels = config.i18n.statuses;

				return labels[status] || status;
			}
		}
	}).mount(mount);
}(window, document));
