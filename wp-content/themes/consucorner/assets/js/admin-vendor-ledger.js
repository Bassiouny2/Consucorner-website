/**
 * Vendor Financial Ledger & Analytics — admin page controller.
 *
 * Pure Vanilla JS for AJAX, Chart.js rendering and DOM updates.
 * Select2 (jQuery) is used only to enhance the existing <select> elements.
 * Flatpickr drives the date range pickers (vanilla).
 *
 * Backend contract:
 *   POST admin-ajax.php
 *     action=cc_vlg_filter
 *     nonce=...
 *     date_filter_mode=completed|created
 *     completed_start_date=YYYY-MM-DD  (completed mode only)
 *     completed_end_date=YYYY-MM-DD    (completed mode only)
 *     created_start_date=YYYY-MM-DD    (created mode only)
 *     created_end_date=YYYY-MM-DD      (created mode only)
 *     vendor_id=int
 *     status=string
 *
 *   GET admin-ajax.php?action=cc_vlg_export&... → CSV download.
 */
(function () {
	'use strict';

	if (typeof window.CC_VLG === 'undefined') {
		return;
	}

	var $ = window.jQuery;
	var CFG = window.CC_VLG;

	var els = {};
	var chart = null;
	var splitChart = null;
	var fmt = new Intl.NumberFormat(undefined, {
		style: 'decimal',
		minimumFractionDigits: 2,
		maximumFractionDigits: 2
	});
	var inflight = null;
	var tableRows = [];
	var visibleOrderRows = 5;
	var orderRowsStep = 10;

	var state = {
		dateFilterMode: 'completed',
		completedStart: '',
		completedEnd: '',
		createdStart: '',
		createdEnd: '',
		vendor: 0,
		status: ''
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		cacheDom();
		if (!els.applyBtn) {
			return;
		}
		initFlatpickr();
		initSelect2();
		bindEvents();
		setDateFilterMode('completed', false);
		readState();
		fetchData();
	}

	function cacheDom() {
		els.completedDateInput  = document.getElementById('cc-vlg-completed-date');
		els.completedStartInput = document.getElementById('cc-vlg-completed-start');
		els.completedEndInput   = document.getElementById('cc-vlg-completed-end');
		els.dateInput           = document.getElementById('cc-vlg-date');
		els.startInput          = document.getElementById('cc-vlg-start');
		els.endInput            = document.getElementById('cc-vlg-end');
		els.dateModeRadios      = document.querySelectorAll('input[name="cc-vlg-date-mode"]');
		els.dateFields          = document.querySelectorAll('[data-cc-date-field]');
		els.subtitle            = document.getElementById('cc-vlg-subtitle');
		els.vendor              = document.getElementById('cc-vlg-vendor');
		els.status              = document.getElementById('cc-vlg-status');
		els.applyBtn            = document.getElementById('cc-vlg-apply');
		els.resetBtn            = document.getElementById('cc-vlg-reset');
		els.exportBtn           = document.getElementById('cc-vlg-export');
		els.exportCustomersBtn  = document.getElementById('cc-vlg-export-customers');
		els.tabButtons          = document.querySelectorAll('[data-cc-tab]');
		els.tabPanels           = document.querySelectorAll('[data-cc-tab-panel]');
		els.tbody               = document.querySelector('[data-cc-tbody]');
		els.customerTbody       = document.querySelector('[data-cc-customer-tbody]');
		els.showMoreOrders      = document.querySelector('[data-cc-action="show-more-orders"]');
		els.canvas              = document.getElementById('cc-vlg-chart');
		els.splitCanvas         = document.getElementById('cc-vlg-split-chart');
		els.loader              = document.getElementById('cc-vlg-loader');
		els.cardSales           = document.querySelector('[data-cc-stat="total_sales"]');
		els.cardComm            = document.querySelector('[data-cc-stat="total_commission"]');
		els.cardVendor          = document.querySelector('[data-cc-stat="vendor_earnings"]');
		els.ordersHint          = document.querySelector('[data-cc-stat="orders_count_label"]');
		els.avgOrder            = document.querySelector('[data-cc-stat="average_order"]');
		els.activeVendors       = document.querySelector('[data-cc-stat="active_vendors"]');
		els.commissionRate      = document.querySelector('[data-cc-stat="commission_rate"]');
		els.bestDay             = document.querySelector('[data-cc-stat="best_day"]');
		els.bestDaySales        = document.querySelector('[data-cc-stat="best_day_sales"]');
		els.retentionRate       = document.querySelector('[data-cc-stat="retention_rate"]');
		els.averageLtv          = document.querySelector('[data-cc-stat="average_ltv"]');
		els.returnCustomers     = document.querySelector('[data-cc-stat="return_customers"]');
		els.totalCustomers      = document.querySelector('[data-cc-stat="total_customers"]');
		els.tableShowing        = document.querySelector('[data-cc-stat="table_showing"]');
		els.customerTableShowing = document.querySelector('[data-cc-stat="customer_table_showing"]');
		els.topVendors          = document.querySelector('[data-cc-list="top_vendors"]');
		els.statusBreakdown     = document.querySelector('[data-cc-list="status_breakdown"]');
		els.salesByChannel      = document.querySelector('[data-cc-list="sales_by_channel"]');
		els.referringChannels   = document.querySelector('[data-cc-list="referring_channels"]');
		els.salesByProduct      = document.querySelector('[data-cc-list="sales_by_product"]');
		els.customerCohorts     = document.querySelector('[data-cc-list="customer_cohorts"]');
		els.salesByReferrer     = document.querySelector('[data-cc-list="sales_by_referrer"]');
		els.sessionsByReferrer  = document.querySelector('[data-cc-list="sessions_by_referrer"]');
		els.productSellThrough  = document.querySelector('[data-cc-list="product_sell_through"]');
	}

	function initFlatpickr() {
		if (typeof window.flatpickr === 'undefined') {
			return;
		}

		if (els.completedDateInput && els.completedStartInput && els.completedEndInput) {
			window.flatpickr(els.completedDateInput, {
				mode: 'range',
				dateFormat: 'Y-m-d',
				defaultDate: [els.completedStartInput.value, els.completedEndInput.value],
				maxDate: 'today',
				onChange: function (dates) {
					if (dates.length === 2) {
						els.completedStartInput.value = ymd(dates[0]);
						els.completedEndInput.value   = ymd(dates[1]);
					}
				}
			});
		}

		if (els.dateInput) {
			var createdDefaults = [];
			if (els.startInput.value && els.endInput.value) {
				createdDefaults = [els.startInput.value, els.endInput.value];
			}

			window.flatpickr(els.dateInput, {
				mode: 'range',
				dateFormat: 'Y-m-d',
				defaultDate: createdDefaults.length ? createdDefaults : null,
				maxDate: 'today',
				onChange: function (dates) {
					if (dates.length === 2) {
						els.startInput.value = ymd(dates[0]);
						els.endInput.value   = ymd(dates[1]);
					} else if (!dates.length) {
						els.startInput.value = '';
						els.endInput.value   = '';
					}
				}
			});
		}
	}

	function initSelect2() {
		if (!$ || !$.fn || !$.fn.select2) {
			return;
		}
		$('.cc-vlg-select2').select2({
			width: '100%',
			minimumResultsForSearch: 6,
			dropdownParent: $('.cc-vlg-wrap')
		});
	}

	function bindEvents() {
		els.applyBtn.addEventListener('click', function () {
			readState();
			fetchData();
		});

		els.resetBtn.addEventListener('click', function () {
			resetFilters();
			readState();
			fetchData();
		});

		if (els.dateModeRadios && els.dateModeRadios.length) {
			for (var m = 0; m < els.dateModeRadios.length; m++) {
				els.dateModeRadios[m].addEventListener('change', function () {
					if (!this.checked) {
						return;
					}
					setDateFilterMode(this.value, true);
				});
			}
		}

		els.exportBtn.addEventListener('click', exportCsv);
		if (els.exportCustomersBtn) {
			els.exportCustomersBtn.addEventListener('click', function () {
				exportCsv('customers');
			});
		}
		if (els.tabButtons && els.tabButtons.length) {
			for (var i = 0; i < els.tabButtons.length; i++) {
				els.tabButtons[i].addEventListener('click', function () {
					activateTab(this.getAttribute('data-cc-tab'));
				});
			}
		}
		if (els.showMoreOrders) {
			els.showMoreOrders.addEventListener('click', function () {
				visibleOrderRows += orderRowsStep;
				renderTable(tableRows);
			});
		}

		bindEnterToApply(els.completedDateInput);
		bindEnterToApply(els.dateInput);
		bindOrderPreview();
	}

	function bindOrderPreview() {
		if (!els.tbody || els.tbody._ccPreviewBound || !$) {
			return;
		}
		els.tbody._ccPreviewBound = true;
		els.tbody.addEventListener('click', function (e) {
			var link = e.target.closest('.order-preview');
			if (!link) {
				return;
			}
			e.preventDefault();
			if (link.classList.contains('disabled')) {
				return;
			}
			var orderId = link.getAttribute('data-order-id');
			if (!orderId) {
				return;
			}
			link.classList.add('disabled');
			$.ajax({
				url: CFG.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cc_vlg_order_preview',
					nonce: CFG.nonce,
					order_id: orderId
				}
			}).done(function (response) {
				link.classList.remove('disabled');
				if (response && response.success && response.data && $.fn.WCBackboneModal) {
					$(link).WCBackboneModal({
						template: 'wc-modal-view-order',
						variable: response.data
					});
				}
			}).fail(function () {
				link.classList.remove('disabled');
			});
		});
	}

	function bindEnterToApply(input) {
		if (!input) {
			return;
		}
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				els.applyBtn.click();
			}
		});
	}

	function getDefaultCompletedRange() {
		if (CFG.defaultCompleted && CFG.defaultCompleted.start && CFG.defaultCompleted.end) {
			return {
				start: CFG.defaultCompleted.start,
				end: CFG.defaultCompleted.end
			};
		}

		var today = new Date();
		var start = new Date();
		start.setDate(today.getDate() - 89);
		return {
			start: ymd(start),
			end: ymd(today)
		};
	}

	function getDefaultCreatedRange() {
		if (CFG.defaultCreated && CFG.defaultCreated.start && CFG.defaultCreated.end) {
			return {
				start: CFG.defaultCreated.start,
				end: CFG.defaultCreated.end
			};
		}
		return getDefaultCompletedRange();
	}

	function clearCompletedRange() {
		els.completedStartInput.value = '';
		els.completedEndInput.value   = '';

		if (els.completedDateInput && els.completedDateInput._flatpickr) {
			els.completedDateInput._flatpickr.clear();
		} else if (els.completedDateInput) {
			els.completedDateInput.value = '';
		}
	}

	function clearCreatedRange() {
		els.startInput.value = '';
		els.endInput.value   = '';

		if (els.dateInput && els.dateInput._flatpickr) {
			els.dateInput._flatpickr.clear();
		} else if (els.dateInput) {
			els.dateInput.value = '';
		}
	}

	function setDateFilterMode(mode, populateDefaults) {
		mode = mode === 'created' ? 'created' : 'completed';
		state.dateFilterMode = mode;

		if (els.dateModeRadios && els.dateModeRadios.length) {
			for (var i = 0; i < els.dateModeRadios.length; i++) {
				els.dateModeRadios[i].checked = els.dateModeRadios[i].value === mode;
			}
		}

		if (els.dateFields && els.dateFields.length) {
			for (var j = 0; j < els.dateFields.length; j++) {
				var field = els.dateFields[j];
				var active = field.getAttribute('data-cc-date-field') === mode;
				field.classList.toggle('is-inactive', !active);
				field.hidden = !active;

				var input = field.querySelector('.cc-vlg-input');
				if (input) {
					input.disabled = !active;
				}
			}
		}

		if (mode === 'created') {
			clearCompletedRange();
			if (populateDefaults) {
				var createdDefaults = getDefaultCreatedRange();
				els.startInput.value = createdDefaults.start;
				els.endInput.value   = createdDefaults.end;
				if (els.dateInput && els.dateInput._flatpickr) {
					els.dateInput._flatpickr.setDate([createdDefaults.start, createdDefaults.end], true);
				}
			}
		} else {
			clearCreatedRange();
			if (populateDefaults) {
				var completedDefaults = getDefaultCompletedRange();
				els.completedStartInput.value = completedDefaults.start;
				els.completedEndInput.value   = completedDefaults.end;
				if (els.completedDateInput && els.completedDateInput._flatpickr) {
					els.completedDateInput._flatpickr.setDate([completedDefaults.start, completedDefaults.end], true);
				}
			}
		}

		updateModeLabels(mode);
	}

	function updateModeLabels(mode) {
		if (els.subtitle) {
			els.subtitle.textContent = mode === 'created'
				? (CFG.i18n.subtitleCreated || '')
				: (CFG.i18n.subtitleCompleted || '');
		}
	}

	function resetFilters() {
		if (els.dateModeRadios && els.dateModeRadios.length) {
			for (var i = 0; i < els.dateModeRadios.length; i++) {
				if (els.dateModeRadios[i].value === 'completed') {
					els.dateModeRadios[i].checked = true;
				}
			}
		}

		setDateFilterMode('completed', true);

		els.vendor.value = '0';
		els.status.value = '';

		if ($ && $.fn && $.fn.select2) {
			$(els.vendor).val('0').trigger('change');
			$(els.status).val('').trigger('change');
		}
	}

	function readState() {
		state.dateFilterMode = 'created';
		if (els.dateModeRadios && els.dateModeRadios.length) {
			for (var i = 0; i < els.dateModeRadios.length; i++) {
				if (els.dateModeRadios[i].checked) {
					state.dateFilterMode = els.dateModeRadios[i].value === 'created' ? 'created' : 'completed';
					break;
				}
			}
		}

		state.completedStart = els.completedStartInput ? els.completedStartInput.value : '';
		state.completedEnd   = els.completedEndInput ? els.completedEndInput.value : '';
		state.createdStart   = els.startInput ? els.startInput.value : '';
		state.createdEnd     = els.endInput ? els.endInput.value : '';
		state.vendor         = parseInt(els.vendor.value, 10) || 0;
		state.status         = els.status.value || '';
	}

	function appendFilterParams(params) {
		params.set('date_filter_mode', state.dateFilterMode);

		if (state.dateFilterMode === 'created') {
			params.set('created_start_date', state.createdStart);
			params.set('created_end_date', state.createdEnd);
		} else {
			params.set('completed_start_date', state.completedStart);
			params.set('completed_end_date', state.completedEnd);
		}

		params.set('vendor_id', String(state.vendor));
		params.set('status', state.status);
	}

	function fetchData() {
		showLoader(true);

		if (inflight && typeof inflight.abort === 'function') {
			inflight.abort();
		}

		var ctrl = ('AbortController' in window) ? new AbortController() : null;
		inflight = ctrl;

		var body = new URLSearchParams();
		body.set('action', 'cc_vlg_filter');
		body.set('nonce', CFG.nonce);
		appendFilterParams(body);

		fetch(CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			signal: ctrl ? ctrl.signal : undefined
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error('Bad response');
				}
				renderAll(json.data);
			})
			.catch(function (err) {
				if (err && err.name === 'AbortError') {
					return;
				}
				renderError();
			})
			.then(function () { showLoader(false); });
	}

	function renderAll(data) {
		if (data.filters && data.filters.date_filter_mode) {
			updateModeLabels(data.filters.date_filter_mode);
		}
		renderSummary(data.summary, data.filters);
		renderChart(data.chart);
		renderSplitChart(data.analytics && data.analytics.earnings_split);
		renderBreakdowns(data.analytics || {});
		visibleOrderRows = 5;
		renderTable(data.rows);
		renderCustomerTable(data.analytics && data.analytics.customer_rows ? data.analytics.customer_rows : []);
	}

	function renderSummary(s, filters) {
		var mode = filters && filters.date_filter_mode === 'created' ? 'created' : 'completed';
		var ordersLabel = mode === 'created'
			? (CFG.i18n.ordersLabelCreated || CFG.i18n.orders)
			: (CFG.i18n.ordersLabelCompleted || CFG.i18n.orders);
		if (els.cardSales)  els.cardSales.innerHTML  = s.total_sales;
		if (els.cardComm)   els.cardComm.innerHTML   = s.total_commission;
		if (els.cardVendor) els.cardVendor.innerHTML = s.vendor_earnings;
		if (els.avgOrder) els.avgOrder.innerHTML = s.average_order || '—';
		if (els.activeVendors) els.activeVendors.textContent = s.active_vendors || 0;
		if (els.commissionRate) els.commissionRate.textContent = s.commission_rate || '0%';
		if (els.bestDay) els.bestDay.textContent = s.best_day || '—';
		if (els.bestDaySales) els.bestDaySales.innerHTML = s.best_day_sales || '—';
		if (els.retentionRate) els.retentionRate.textContent = s.retention_rate || '0%';
		if (els.averageLtv) els.averageLtv.innerHTML = s.average_ltv || '—';
		if (els.returnCustomers) els.returnCustomers.textContent = s.return_customers || 0;
		if (els.totalCustomers) els.totalCustomers.textContent = s.total_customers || 0;
		if (els.tableShowing) {
			var showing = Math.min(visibleOrderRows, s.table_showing || 0);
			var total = s.orders_count || 0;
			els.tableShowing.textContent = showing + ' ' + CFG.i18n.showingRows + ' / ' + total + ' ' + CFG.i18n.orders;
		}

		if (els.ordersHint) {
			els.ordersHint.textContent = (s.orders_count || 0) + ' ' + ordersLabel;
		}
	}

	function renderChart(c) {
		if (!els.canvas || typeof window.Chart === 'undefined') return;

		var ctx = els.canvas.getContext('2d');

		if (chart) {
			chart.destroy();
			chart = null;
		}

		var grad1 = ctx.createLinearGradient(0, 0, 0, 240);
		grad1.addColorStop(0, 'rgba(33, 102, 248, 0.28)');
		grad1.addColorStop(1, 'rgba(33, 102, 248, 0.00)');

		var grad2 = ctx.createLinearGradient(0, 0, 0, 240);
		grad2.addColorStop(0, 'rgba(255, 149, 0, 0.28)');
		grad2.addColorStop(1, 'rgba(255, 149, 0, 0.00)');

		chart = new window.Chart(ctx, {
			type: 'line',
			data: {
				labels: c.labels,
				datasets: [
					{
						label: CFG.i18n.netSales,
						data: c.sales,
						borderColor: '#2166f8',
						backgroundColor: grad1,
						borderWidth: 2.5,
						tension: 0.35,
						fill: true,
						pointRadius: 2,
						pointHoverRadius: 5,
						pointBackgroundColor: '#2166f8'
					},
					{
						label: CFG.i18n.commission,
						data: c.commission,
						borderColor: '#ff9500',
						backgroundColor: grad2,
						borderWidth: 2.5,
						tension: 0.35,
						fill: true,
						pointRadius: 2,
						pointHoverRadius: 5,
						pointBackgroundColor: '#ff9500'
					},
					{
						label: CFG.i18n.vendorPay,
						data: c.vendor,
						borderColor: '#16a34a',
						backgroundColor: 'rgba(22, 163, 74, 0.06)',
						borderWidth: 2,
						tension: 0.35,
						fill: false,
						pointRadius: 1.5,
						pointHoverRadius: 5,
						pointBackgroundColor: '#16a34a'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: {
						position: 'top',
						align: 'end',
						labels: { boxWidth: 10, usePointStyle: true, padding: 16 }
					},
					tooltip: {
						backgroundColor: 'rgba(17, 24, 39, 0.95)',
						titleColor: '#fff',
						bodyColor: '#fff',
						padding: 12,
						borderColor: 'rgba(255,255,255,0.06)',
						borderWidth: 1,
						callbacks: {
							label: function (item) {
								return item.dataset.label + ': ' + CFG.currency + fmt.format(item.parsed.y);
							}
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
					},
					y: {
						beginAtZero: true,
						ticks: { callback: function (v) { return CFG.currency + fmt.format(v); } },
						grid: { color: 'rgba(15, 23, 42, 0.06)' }
					}
				}
			}
		});
	}

	function renderSplitChart(split) {
		if (!els.splitCanvas || typeof window.Chart === 'undefined' || !split) return;

		var ctx = els.splitCanvas.getContext('2d');
		if (splitChart) {
			splitChart.destroy();
			splitChart = null;
		}

		splitChart = new window.Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: split.labels || [],
				datasets: [{
					data: split.data || [],
					backgroundColor: ['#2166f8', '#16a34a'],
					borderColor: '#ffffff',
					borderWidth: 4,
					hoverOffset: 6
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '72%',
				plugins: {
					legend: {
						position: 'bottom',
						labels: { boxWidth: 10, usePointStyle: true, padding: 14 }
					},
					tooltip: {
						callbacks: {
							label: function (item) {
								return item.label + ': ' + CFG.currency + fmt.format(item.parsed || 0);
							}
						}
					}
				}
			}
		});
	}

	function renderBreakdowns(analytics) {
		renderBarList(els.topVendors, analytics.top_vendors || [], 'name');
		renderBarList(els.statusBreakdown, analytics.status_breakdown || [], 'label');
		renderBarList(els.salesByChannel, analytics.sales_by_channel || [], 'label');
		renderPerformanceList(els.referringChannels, analytics.referring_channels || []);
		renderProductSalesList(els.salesByProduct, analytics.sales_by_product || []);
		renderCohortList(els.customerCohorts, analytics.customer_cohorts || []);
		renderBarList(els.salesByReferrer, analytics.sales_by_referrer || [], 'label');
		renderSessionsList(els.sessionsByReferrer, analytics.sessions_by_referrer || []);
		renderSellThroughList(els.productSellThrough, analytics.product_sell_through || []);
	}

	function renderCustomerTable(rows) {
		if (!els.customerTbody) return;

		if (!rows || !rows.length) {
			els.customerTbody.innerHTML =
				'<tr><td colspan="10" class="cc-vlg-empty">' +
					escapeHtml(CFG.i18n.noResults) +
				'</td></tr>';
			if (els.customerTableShowing) {
				els.customerTableShowing.textContent = '0 customers';
			}
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			html +=
				'<tr>' +
					'<td><strong>' + escapeHtml(r.customer_name || 'Guest customer') + '</strong>' + (r.customer_id ? '<span class="cc-vlg-table-sub">#' + escapeHtml(String(r.customer_id)) + '</span>' : '') + '</td>' +
					'<td>' + escapeHtml(r.email || '—') + '</td>' +
					'<td>' + escapeHtml(r.type || '—') + '</td>' +
					'<td><span class="cc-vlg-status ' + (r.is_returning ? 'cc-vlg-status--completed' : 'cc-vlg-status--pending') + '">' + escapeHtml(r.returning_label || '—') + '</span></td>' +
					'<td class="cc-vlg-num">' + escapeHtml(String(r.orders || 0)) + '</td>' +
					'<td class="cc-vlg-num cc-vlg-num--accent">' + (r.total_spent_label || money(r.total_spent)) + '</td>' +
					'<td class="cc-vlg-num">' + (r.aov_label || money(r.aov)) + '</td>' +
					'<td>' + escapeHtml(r.first_order || '—') + '</td>' +
					'<td>' + escapeHtml(r.last_order || '—') + '</td>' +
					'<td class="cc-vlg-num">' + escapeHtml(String(r.vendor_count || 0)) + '</td>' +
				'</tr>';
		}
		els.customerTbody.innerHTML = html;
		if (els.customerTableShowing) {
			els.customerTableShowing.textContent = rows.length + ' customers';
		}
	}

	function renderBarList(container, rows, labelKey) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var pct = Math.max(0, Math.min(100, Number(r.percent) || 0));
			html +=
				'<div class="cc-vlg-list-row">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong>' + escapeHtml(r[labelKey]) + '</strong>' +
						'<span>' + (r.sales_formatted || (CFG.currency + fmt.format(r.sales || 0))) + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-list-row__meta">' +
						'<span>' + escapeHtml(String(r.orders || 0)) + ' ' + escapeHtml(CFG.i18n.orders) + '</span>' +
						'<span>' + escapeHtml(String(pct)) + '% ' + escapeHtml(CFG.i18n.ofSales) + '</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderPerformanceList(container, rows) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var pct = Math.max(0, Math.min(100, Number(r.percent) || 0));
			html +=
				'<div class="cc-vlg-list-row cc-vlg-list-row--rich">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong>' + escapeHtml(r.label) + '</strong>' +
						'<span>' + (r.sales_formatted || money(r.sales)) + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-metric-grid">' +
						'<span><b>' + escapeHtml(String(r.orders || 0)) + '</b> ' + escapeHtml(CFG.i18n.orders) + '</span>' +
						'<span><b>' + (r.aov_formatted || money(0)) + '</b> AOV</span>' +
						'<span><b>' + (r.commission_formatted || money(r.commission)) + '</b> admin</span>' +
						'<span><b>' + (r.vendor_formatted || money(r.vendor)) + '</b> vendor</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderProductSalesList(container, rows) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var pct = Math.max(0, Math.min(100, Number(r.percent) || 0));
			html +=
				'<div class="cc-vlg-list-row">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong title="' + escapeAttr(r.label) + '">' + escapeHtml(r.label) + '</strong>' +
						'<span>' + (r.sales_formatted || money(r.sales)) + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-list-row__meta">' +
						'<span>' + escapeHtml(String(r.quantity || 0)) + ' sold</span>' +
						'<span>' + escapeHtml(String(pct)) + '% ' + escapeHtml(CFG.i18n.ofSales) + '</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderCohortList(container, rows) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var pct = Math.max(0, Math.min(100, Number(r.percent) || 0));
			html +=
				'<div class="cc-vlg-list-row cc-vlg-list-row--rich">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong>' + escapeHtml(r.label) + '</strong>' +
						'<span>' + (r.sales_formatted || money(r.sales)) + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-list-row__meta">' +
						'<span>' + escapeHtml(String(r.customers || 0)) + ' customers</span>' +
						'<span>' + escapeHtml(String(r.orders || 0)) + ' ' + escapeHtml(CFG.i18n.orders) + '</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderSessionsList(container, rows) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var pct = Math.max(0, Math.min(100, Number(r.percent) || 0));
			html +=
				'<div class="cc-vlg-list-row">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong title="' + escapeAttr(r.label) + '">' + escapeHtml(r.label) + '</strong>' +
						'<span>' + escapeHtml(String(r.sessions || 0)) + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-list-row__meta">' +
						'<span>converted sessions estimate</span>' +
						'<span>' + escapeHtml(String(r.orders || 0)) + ' ' + escapeHtml(CFG.i18n.orders) + '</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderSellThroughList(container, rows) {
		if (!container) return;
		if (!rows.length) {
			container.innerHTML = '<p class="cc-vlg-empty">' + escapeHtml(CFG.i18n.noResults) + '</p>';
			return;
		}

		var html = '';
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var hasRate = r.sell_through_percent !== null && typeof r.sell_through_percent !== 'undefined';
			var pct = hasRate ? Math.max(0, Math.min(100, Number(r.sell_through_percent) || 0)) : 0;
			html +=
				'<div class="cc-vlg-list-row">' +
					'<div class="cc-vlg-list-row__top">' +
						'<strong title="' + escapeAttr(r.label) + '">' + escapeHtml(r.label) + '</strong>' +
						'<span>' + (hasRate ? escapeHtml(String(pct)) + '%' : 'Stock unknown') + '</span>' +
					'</div>' +
					'<div class="cc-vlg-progress" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
					'<div class="cc-vlg-list-row__meta">' +
						'<span>' + escapeHtml(String(r.quantity || 0)) + ' sold</span>' +
						'<span>' + (hasRate ? escapeHtml(String(r.stock || 0)) + ' in stock' : 'Add stock to calculate rate') + '</span>' +
					'</div>' +
				'</div>';
		}
		container.innerHTML = html;
	}

	function renderTable(rows) {
		if (!els.tbody) return;

		tableRows = rows || [];
		if (!rows || !rows.length) {
			els.tbody.innerHTML =
				'<tr><td colspan="13" class="cc-vlg-empty">' +
					escapeHtml(CFG.i18n.noResults) +
				'</td></tr>';
			updateShowMoreButton(0, 0);
			return;
		}

		var html = '';
		var visibleRows = rows.slice(0, visibleOrderRows);
		for (var i = 0; i < visibleRows.length; i++) {
			var r = visibleRows[i];
			html +=
				'<tr>' +
					'<td class="cc-vlg-order-cell">' +
						'<a href="#" class="order-preview cc-vlg-order-preview" data-order-id="' + escapeAttr(String(r.order_id)) + '" title="' + escapeAttr(CFG.i18n.previewOrder || 'Preview') + '">' +
							'<span class="screen-reader-text">' + escapeHtml(CFG.i18n.previewOrder || 'Preview') + '</span>' +
						'</a>' +
						'<a class="cc-vlg-order-link order-view" href="' + escapeAttr(r.order_url) + '"><strong>' + escapeHtml(r.order_number) + '</strong></a>' +
					'</td>' +
					'<td>' + escapeHtml(r.billing_name || '—') + '</td>' +
					'<td>' + escapeHtml(r.date) + '</td>' +
					'<td>' + escapeHtml(r.completed || '—') + '</td>' +
					'<td>' + escapeHtml(r.vendor_name) + '</td>' +
					'<td class="cc-vlg-num">' + CFG.currency + fmt.format(r.gross_total) + '</td>' +
					'<td class="cc-vlg-num">' + CFG.currency + fmt.format(r.shipping_total || 0) + '</td>' +
					'<td class="cc-vlg-num">' + CFG.currency + fmt.format(r.total_without_shipping || 0) + '</td>' +
					'<td class="cc-vlg-num">' + CFG.currency + fmt.format(r.admin_fee) + '</td>' +
					'<td class="cc-vlg-num cc-vlg-num--accent">' + CFG.currency + fmt.format(r.vendor_earning) + '</td>' +
					'<td class="cc-vlg-num">' + CFG.currency + fmt.format(r.return_deductions || 0) + '</td>' +
					'<td class="cc-vlg-num cc-vlg-num--accent">' + CFG.currency + fmt.format(r.net_payable != null ? r.net_payable : r.vendor_earning) + '</td>' +
					'<td><span class="cc-vlg-status cc-vlg-status--' + escapeAttr(r.status) + '">' + escapeHtml(r.status_label) + '</span></td>' +
				'</tr>';
		}
		els.tbody.innerHTML = html;
		updateShowMoreButton(visibleRows.length, rows.length);
	}

	function updateShowMoreButton(visible, total) {
		if (!els.showMoreOrders) return;
		var hasMore = visible < total;
		els.showMoreOrders.hidden = !hasMore;
		if (hasMore) {
			els.showMoreOrders.textContent = CFG.i18n.showMore + ' (' + visible + ' / ' + total + ')';
		}
		if (els.tableShowing) {
			els.tableShowing.textContent = visible + ' ' + CFG.i18n.showingRows + ' / ' + total + ' ' + CFG.i18n.orders;
		}
	}

	function renderError() {
		if (!els.tbody) return;
		els.tbody.innerHTML =
			'<tr><td colspan="13" class="cc-vlg-empty cc-vlg-empty--error">' +
				escapeHtml(CFG.i18n.errorLoad) +
			'</td></tr>';
		updateShowMoreButton(0, 0);
	}

	function exportCsv(type) {
		readState();
		type = type || 'orders';

		var params = new URLSearchParams();
		params.set('action', 'cc_vlg_export');
		params.set('nonce', CFG.nonce);
		appendFilterParams(params);
		params.set('export_type', type);

		var a = document.createElement('a');
		a.href = CFG.ajaxUrl + '?' + params.toString();
		a.style.display = 'none';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { document.body.removeChild(a); }, 250);
	}

	function activateTab(tab) {
		if (!tab) return;

		for (var i = 0; i < els.tabButtons.length; i++) {
			var activeButton = els.tabButtons[i].getAttribute('data-cc-tab') === tab;
			els.tabButtons[i].classList.toggle('is-active', activeButton);
			els.tabButtons[i].setAttribute('aria-selected', activeButton ? 'true' : 'false');
		}

		for (var j = 0; j < els.tabPanels.length; j++) {
			var activePanel = els.tabPanels[j].getAttribute('data-cc-tab-panel') === tab;
			els.tabPanels[j].classList.toggle('is-active', activePanel);
			els.tabPanels[j].hidden = !activePanel;
		}
	}

	function showLoader(show) {
		if (els.loader) {
			els.loader.classList.toggle('is-active', !!show);
		}
		if (els.applyBtn) {
			els.applyBtn.disabled = !!show;
		}
	}

	function ymd(d) {
		if (typeof d === 'string') {
			return d;
		}
		var y  = d.getFullYear();
		var m  = ('0' + (d.getMonth() + 1)).slice(-2);
		var dd = ('0' + d.getDate()).slice(-2);
		return y + '-' + m + '-' + dd;
	}

	function money(value) {
		return CFG.currency + fmt.format(Number(value) || 0);
	}

	function escapeHtml(s) {
		s = (s == null) ? '' : String(s);
		return s.replace(/[&<>"']/g, function (c) {
			return ({
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			})[c];
		});
	}

	function escapeAttr(s) {
		return escapeHtml(s).replace(/\s+/g, '-');
	}
})();
