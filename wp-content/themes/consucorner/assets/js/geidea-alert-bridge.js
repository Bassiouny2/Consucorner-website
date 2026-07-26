/**
 * Intercept Geidea payment gateway alert() calls — persist error for WhatsApp modal.
 * Loaded in head before Geidea plugin scripts.
 */
(function (window) {
  "use strict";

  var STORAGE_KEY = "cc_geidea_payment_error";
  var nativeAlert = window.alert;

  function isGeideaMessage(message) {
    return /geidea|payment gateway|gateway error|بوابة/i.test(String(message || ""));
  }

  function persistError(message) {
    try {
      window.sessionStorage.setItem(STORAGE_KEY, String(message));
    } catch (err) {
      /* ignore */
    }
  }

  function showModalIfReady(message) {
    if (window.ccCheckoutErrorModal && typeof window.ccCheckoutErrorModal.show === "function") {
      window.ccCheckoutErrorModal.show(String(message));
      return true;
    }
    return false;
  }

  window.alert = function (message) {
    if (isGeideaMessage(message)) {
      var text = String(message);
      persistError(text);
      if (!showModalIfReady(text)) {
        window.__ccGeideaAlertPending = text;
      }
      return;
    }

    return nativeAlert.apply(window, arguments);
  };
})(window);
