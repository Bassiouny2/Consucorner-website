(function ($) {
	'use strict';

	if (typeof CC_RETURNS === 'undefined') {
		return;
	}

	var $wrap = $('.cc-returns-wrap');
	var $notice = $wrap.find('.cc-returns-notice');
	var $tbody = $wrap.find('.cc-returns-table tbody');
	var $summary = $wrap.find('.cc-returns-summary');
	var $opsOrder = $wrap.find('.cc-returns-ops-order');

	function showNotice(message, type) {
		$notice
			.removeClass('is-error is-success')
			.addClass(type === 'error' ? 'is-error' : 'is-success')
			.text(message)
			.show();
	}

	function getFilters() {
		return {
			action: 'cc_returns_filter',
			nonce: CC_RETURNS.nonce,
			date_from: $('#cc-returns-date-from').val(),
			date_to: $('#cc-returns-date-to').val(),
			vendor_id: $('#cc-returns-vendor').val(),
			status: $('#cc-returns-status').val(),
			type: $('#cc-returns-type').val(),
			order_id: $('#cc-returns-order').val(),
			page: $wrap.data('page') || 1
		};
	}

	function loadRows() {
		$wrap.addClass('cc-returns-loading');
		$.post(CC_RETURNS.ajaxUrl, getFilters())
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorLoad, 'error');
					return;
				}
				$tbody.html(response.data.rows_html || '');
				$summary.text(response.data.summary || '');
				$notice.hide();
			})
			.fail(function () {
				showNotice(CC_RETURNS.i18n.errorLoad, 'error');
			})
			.always(function () {
				$wrap.removeClass('cc-returns-loading');
			});
	}

	function loadOpsOrder(orderId) {
		if (!orderId) {
			showNotice(CC_RETURNS.i18n.errorOrder, 'error');
			return;
		}

		$wrap.addClass('cc-returns-loading');
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_lookup_order',
			nonce: CC_RETURNS.nonce,
			order_id: orderId
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorOrder, 'error');
					return;
				}
				$opsOrder.prop('hidden', false).html(response.data.html || '');
				$('#cc-returns-order').val(orderId);
				showNotice(CC_RETURNS.i18n.updated || 'Order loaded.', 'success');
			})
			.fail(function () {
				showNotice(CC_RETURNS.i18n.errorOrder, 'error');
			})
			.always(function () {
				$wrap.removeClass('cc-returns-loading');
			});
	}

	$wrap.on('click', '.cc-returns-filter-btn', function (e) {
		e.preventDefault();
		$wrap.data('page', 1);
		loadRows();
	});

	$wrap.on('click', '.cc-returns-export-btn', function (e) {
		e.preventDefault();
		var data = getFilters();
		data.action = 'cc_returns_export';
		var $form = $('<form>', { method: 'POST', action: CC_RETURNS.ajaxUrl, target: '_blank' });
		Object.keys(data).forEach(function (key) {
			$form.append($('<input>', { type: 'hidden', name: key, value: data[key] }));
		});
		$('body').append($form);
		$form.trigger('submit');
		$form.remove();
	});

	$wrap.on('click', '.cc-returns-lookup-order', function (e) {
		e.preventDefault();
		var orderId = ($('#cc-returns-ops-order-id').val() || '').replace(/\D+/g, '');
		loadOpsOrder(orderId);
	});

	$wrap.on('click', '.cc-returns-review-cancel', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $card = $btn.closest('.cc-returns-ops-cancel-card');
		var note = $card.find('.cc-returns-cancel-note').val();

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_review_cancel',
			nonce: CC_RETURNS.nonce,
			order_id: $btn.data('order-id'),
			request_id: $btn.data('request-id'),
			decision: $btn.data('decision'),
			note: note
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorOrder, 'error');
					return;
				}
				$opsOrder.html(response.data.html || '');
				showNotice(response.data.message || CC_RETURNS.i18n.updated, 'success');
			})
			.fail(function (xhr) {
				var msg = CC_RETURNS.i18n.errorOrder;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showNotice(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$wrap.on('click', '.cc-returns-update-fulfillment', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $card = $btn.closest('.cc-returns-ops-vendor-card');
		var status = $card.find('.cc-returns-fulfillment-status').val();
		var note = $card.find('.cc-returns-fulfillment-note').val();

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_update_fulfillment',
			nonce: CC_RETURNS.nonce,
			order_id: $btn.data('order-id'),
			vendor_id: $btn.data('vendor-id'),
			status: status,
			note: note
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorOrder, 'error');
					return;
				}
				$opsOrder.html(response.data.html || '');
				showNotice(response.data.message || CC_RETURNS.i18n.updated, 'success');
			})
			.fail(function (xhr) {
				var msg = CC_RETURNS.i18n.errorOrder;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showNotice(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$wrap.on('click', '.cc-returns-create-manual', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var orderId = parseInt($btn.data('order-id'), 10) || 0;
		var items = {};

		$opsOrder.find('.cc-returns-ops-items-table tbody tr').each(function () {
			var $row = $(this);
			var $checkbox = $row.find('.cc-returns-manual-item:checked');
			if (!$checkbox.length) {
				return;
			}
			var itemId = parseInt($checkbox.val(), 10);
			var qty = parseFloat($row.find('.cc-returns-manual-qty').val()) || 0;
			if (itemId && qty > 0) {
				items[itemId] = qty;
			}
		});

		if (!Object.keys(items).length) {
			showNotice(CC_RETURNS.i18n.errorCreate, 'error');
			return;
		}

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_create_manual',
			nonce: CC_RETURNS.nonce,
			order_id: orderId,
			reason: $('#cc-returns-manual-reason').val(),
			details: $('#cc-returns-manual-details').val(),
			note: $('#cc-returns-manual-note').val(),
			items: items
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorCreate, 'error');
					return;
				}
				$opsOrder.html(response.data.html || '');
				showNotice(response.data.message || CC_RETURNS.i18n.created, 'success');
				loadRows();
			})
			.fail(function (xhr) {
				var msg = CC_RETURNS.i18n.errorCreate;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showNotice(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$wrap.on('click', '.cc-returns-workflow-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var status = $btn.data('status');
		var requestId = $btn.data('request-id');
		var note = '';

		if (status === 'rejected') {
			note = window.prompt('Optional rejection note:', '') || '';
		}

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_update_return_status',
			nonce: CC_RETURNS.nonce,
			request_id: requestId,
			status: status,
			note: note
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice((response && response.data && response.data.message) || CC_RETURNS.i18n.errorResolve, 'error');
					$btn.prop('disabled', false);
					return;
				}
				showNotice(response.data.message || CC_RETURNS.i18n.updated, 'success');
				loadRows();
			})
			.fail(function (xhr) {
				var msg = CC_RETURNS.i18n.errorResolve;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showNotice(msg, 'error');
				$btn.prop('disabled', false);
			});
	});

	$wrap.on('click', '.cc-returns-resolve-wallet, .cc-returns-resolve-direct', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var route = $btn.hasClass('cc-returns-resolve-wallet') ? 'wallet' : 'direct';
		var confirmMsg = route === 'wallet' ? CC_RETURNS.i18n.confirmWallet : CC_RETURNS.i18n.confirmDirect;
		if (!window.confirm(confirmMsg)) {
			return;
		}

		var restock = window.confirm(CC_RETURNS.i18n.confirmRestock);
		var shipping = 0;
		if (route === 'wallet') {
			var raw = window.prompt(CC_RETURNS.i18n.shippingPrompt, '0');
			if (raw === null) {
				return;
			}
			shipping = parseFloat(raw) || 0;
		}

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_resolve',
			nonce: CC_RETURNS.nonce,
			request_id: $btn.data('request-id'),
			route: route,
			restock: restock ? 1 : 0,
			shipping_deduction: shipping
		})
			.done(function (response) {
				if (!response || !response.success) {
					var msg =
						(response && response.data && response.data.message) ||
						CC_RETURNS.i18n.errorResolve ||
						CC_RETURNS.i18n.errorLoad;
					showNotice(msg, 'error');
					$btn.prop('disabled', false);
					return;
				}
				showNotice(response.data.message || CC_RETURNS.i18n.resolved, 'success');
				loadRows();
			})
			.fail(function (xhr) {
				var msg = CC_RETURNS.i18n.errorResolve || CC_RETURNS.i18n.errorLoad;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showNotice(msg, 'error');
				$btn.prop('disabled', false);
			});
	});

	var initialOrderId = ($('#cc-returns-ops-order-id').val() || $('#cc-returns-order').val() || '').replace(/\D+/g, '');
	if (initialOrderId) {
		$('#cc-returns-ops-order-id').val(initialOrderId);
		loadOpsOrder(initialOrderId);
	}
})(jQuery);
