(function ($) {
  'use strict';

  var cfg = window.ccGtmAdmin || {};

  function accountIdFromPath(path) {
    if (!path) return '';
    var m = String(path).match(/accounts\/([^/]+)/);
    return m ? m[1] : '';
  }

  function syncAccountName() {
    var opt = $('#cc-gtm-account').find(':selected');
    $('#cc-gtm-account-name').val(opt.data('name') || '');
  }

  function fillContainerFields(container) {
    if (!container) return;
    $('#cc-gtm-container-api-id').val(container.containerId || '');
    $('#cc-gtm-container-public-id').val(container.publicId || '');
    if (typeof container.usageContext !== 'undefined') {
      $('#cc-gtm-container-usage-context').val(container.usageContext || 'web');
    }
    renderUsageHint(container.usageContext || '');
  }

  function renderUsageHint(usage) {
    var $hint = $('#cc-gtm-container-usage-hint');
    if (!$hint.length) return;
    usage = String(usage || '').toLowerCase();
    if (!usage) {
      $hint.attr('hidden', true).text('');
      return;
    }
    var isServer = usage === 'server';
    $hint
      .removeAttr('hidden')
      .removeClass('cc-gtm-badge--web cc-gtm-badge--server')
      .addClass(isServer ? 'cc-gtm-badge--server' : 'cc-gtm-badge--web')
      .text(isServer ? 'Server container' : usage.charAt(0).toUpperCase() + usage.slice(1) + ' container');
  }

  function loadContainers(accountId, preselect) {
    var $select = $('#cc-gtm-container-select');
    if (!$select.length || !accountId) {
      return;
    }

    var strings = cfg.strings || {};
    $select.prop('disabled', true).html(
      '<option value="">' + (strings.loadingContainers || 'Loading…') + '</option>'
    );

    $.post(cfg.ajaxUrl, {
      action: 'cc_gtm_list_containers',
      nonce: cfg.nonce,
      account_id: accountId
    })
      .done(function (res) {
        if (!res || !res.success || !res.data || !res.data.containers) {
          $select.html(
            '<option value="">' + (strings.containerError || 'Error') + '</option>'
          );
          return;
        }

        var items = res.data.containers;
        var html = '<option value="">' + (strings.selectContainer || 'Select') + '</option>';
        var targetId = (cfg.containerId || '').toUpperCase();
        var matched = null;

        items.forEach(function (c) {
          var usage = (c.usageContext || 'web').toLowerCase();
          var label = (c.name || c.publicId || c.containerId) + ' (' + (c.publicId || '') + ')' +
            ' — ' + usage;
          html += '<option value="' + c.containerId + '" data-public="' + (c.publicId || '') +
            '" data-usage="' + usage + '">' + label + '</option>';
          if (targetId && (c.publicId || '').toUpperCase() === targetId) {
            matched = c;
          }
        });

        $select.html(html).prop('disabled', false);

        if (preselect && preselect.apiId) {
          $select.val(preselect.apiId);
          var opt = $select.find(':selected');
          fillContainerFields({
            containerId: preselect.apiId,
            publicId: preselect.publicId || opt.data('public')
          });
        } else if (matched) {
          $select.val(matched.containerId);
          fillContainerFields(matched);
        }
      })
      .fail(function () {
        $select.html(
          '<option value="">' + (strings.containerError || 'Error') + '</option>'
        );
      });
  }

  $('#cc-gtm-account').on('change', function () {
    syncAccountName();
    var accountId = $(this).val();
    loadContainers(accountId, null);
  });

  $('#cc-gtm-container-select').on('change', function () {
    var opt = $(this).find(':selected');
    fillContainerFields({
      containerId: $(this).val(),
      publicId: opt.data('public') || '',
      usageContext: opt.data('usage') || 'web'
    });
  });

  $(function () {
    syncAccountName();
    var accountId = $('#cc-gtm-account').val();
    if (accountId && $('#cc-gtm-container-select').length) {
      loadContainers(accountId, {
        apiId: $('#cc-gtm-container-api-id').val(),
        publicId: $('#cc-gtm-container-public-id').val()
      });
    }
  });
})(jQuery);
