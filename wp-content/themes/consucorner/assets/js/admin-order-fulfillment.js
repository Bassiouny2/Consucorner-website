(function ($) {
	'use strict';

	if (typeof CC_RETURNS === 'undefined') {
		return;
	}

	function showNotice(message, type) {
		var $notice = $('.cc-order-fulfillment-notice');
		if (!$notice.length) {
			$notice = $('<div class="notice cc-order-fulfillment-notice" />').insertBefore('.cc-order-fulfillment-panel');
		}
		$notice
			.removeClass('notice-success notice-error')
			.addClass(type === 'error' ? 'notice-error' : 'notice-success')
			.html('<p>' + message + '</p>')
			.show();
	}

	$(document).on('click', '.cc-returns-update-fulfillment', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $card = $btn.closest('.cc-returns-ops-vendor-card');

		$btn.prop('disabled', true);
		$.post(CC_RETURNS.ajaxUrl, {
			action: 'cc_returns_update_fulfillment',
			nonce: CC_RETURNS.nonce,
			order_id: $btn.data('order-id'),
			vendor_id: $btn.data('vendor-id'),
			status: $card.find('.cc-returns-fulfillment-status').val(),
			note: $card.find('.cc-returns-fulfillment-note').val()
		})
			.done(function (response) {
				if (!response || !response.success) {
					showNotice(
						(response && response.data && response.data.message) || CC_RETURNS.i18n.errorOrder,
						'error'
					);
					return;
				}
				showNotice(response.data.message || CC_RETURNS.i18n.updated, 'success');
				window.setTimeout(function () {
					window.location.reload();
				}, 600);
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
})(jQuery);
