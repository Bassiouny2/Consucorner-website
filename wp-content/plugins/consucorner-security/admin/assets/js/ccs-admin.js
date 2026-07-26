(function () {
  'use strict';

  if (typeof window.ccsAdmin === 'undefined') {
    return;
  }

  var cfg = window.ccsAdmin;

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function showToast(message, isError) {
    var toast = qs('[data-ccs-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    toast.classList.toggle('ccs-toast--error', !!isError);
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(function () {
      toast.hidden = true;
    }, 2800);
  }

  function updateStatusUI(data) {
    if (!data) return;

    qsa('[data-ccs-score]').forEach(function (el) {
      el.textContent = String(data.score);
    });

    var pill = qs('[data-ccs-status-pill]');
    if (pill) {
      var isIssues = data.status === 'issues';
      pill.textContent = isIssues ? cfg.i18n.issues : cfg.i18n.protected;
      pill.classList.toggle('ccs-status-pill--issues', isIssues);
      pill.classList.toggle('ccs-status-pill--protected', !isIssues);
    }

    if (data.options) {
      Object.keys(data.options).forEach(function (key) {
        var input = qs('[data-ccs-option-toggle][data-option-key="' + key + '"]');
        if (input) {
          input.checked = !!data.options[key];
        }
      });
    }

    if (data.modules) {
      Object.keys(data.modules).forEach(function (moduleId) {
        var moduleToggle = qs('[data-ccs-module-toggle][data-module-id="' + moduleId + '"]');
        if (moduleToggle) {
          moduleToggle.checked = !!data.modules[moduleId];
        }
      });
    }
  }

  function post(action, payload) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce);
    Object.keys(payload).forEach(function (k) {
      body.set(k, payload[k]);
    });

    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.success) {
          var msg = (json && json.data && json.data.message) || cfg.i18n.error;
          throw { message: msg, critical: json && json.data && json.data.critical };
        }
        return json.data;
      });
  }

  function onOptionToggle(input) {
    var key = input.getAttribute('data-option-key');
    var value = input.checked ? 1 : 0;
    var wasChecked = !input.checked;

    if (input.getAttribute('data-critical') === '1' && !value) {
      input.checked = true;
      showToast(cfg.i18n.blocked, true);
      return;
    }

    input.disabled = true;

    post('ccs_toggle_option', { option_key: key, value: String(value) })
      .then(function (data) {
        updateStatusUI(data);
        showToast(cfg.i18n.saved, false);
      })
      .catch(function (err) {
        input.checked = wasChecked;
        showToast(err.message || cfg.i18n.error, true);
      })
      .finally(function () {
        input.disabled = input.getAttribute('data-critical') === '1' && input.checked;
      });
  }

  function onModuleToggle(input) {
    var moduleId = input.getAttribute('data-module-id');
    var value = input.checked ? 1 : 0;
    var wasChecked = !input.checked;

    input.disabled = true;

    post('ccs_toggle_module', { module_id: moduleId, value: String(value) })
      .then(function (data) {
        updateStatusUI(data);
        showToast(cfg.i18n.saved, false);
      })
      .catch(function (err) {
        input.checked = wasChecked;
        showToast(err.message || cfg.i18n.error, true);
      })
      .finally(function () {
        input.disabled = false;
      });
  }

  function init() {
    qsa('[data-ccs-option-toggle]').forEach(function (input) {
      input.addEventListener('change', function () {
        onOptionToggle(input);
      });
    });

    qsa('[data-ccs-module-toggle]').forEach(function (input) {
      input.addEventListener('change', function () {
        onModuleToggle(input);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
