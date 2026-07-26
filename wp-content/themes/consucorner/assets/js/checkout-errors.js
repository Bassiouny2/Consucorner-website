/**
 * Checkout error modal — Geidea payment failures / cancel only (WhatsApp CTA).
 */
(function (window, document) {
  "use strict";

  var cfg = window.ccCheckoutErrors || {};
  var shownFor = "";
  var FLOW_KEY = "cc_geidea_flow";
  var ERROR_KEY = "cc_geidea_payment_error";
  var DEFAULT_FLOW_TTL = 15 * 60 * 1000;

  function getModal() {
    return document.getElementById("cc-checkout-error-modal");
  }

  function getNoticesWrap() {
    return document.querySelector(".checkout-notices-wrap");
  }

  function getFlowTtl() {
    var ttl = parseInt(cfg.flowTtlMs, 10);
    return ttl > 0 ? ttl : DEFAULT_FLOW_TTL;
  }

  function storageGet(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (err) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (err) {
      /* ignore */
    }
  }

  function storageRemove(key) {
    try {
      window.sessionStorage.removeItem(key);
    } catch (err) {
      /* ignore */
    }
  }

  function isFreshGeideaFlow() {
    var raw = storageGet(FLOW_KEY);
    if (!raw) return false;
    var ts = parseInt(raw, 10);
    if (!ts) return false;
    return Date.now() - ts < getFlowTtl();
  }

  function setGeideaFlowFlag() {
    storageSet(FLOW_KEY, String(Date.now()));
  }

  function clearGeideaFlowFlags() {
    storageRemove(FLOW_KEY);
    storageRemove(ERROR_KEY);
  }

  function hideTopCheckoutErrors() {
    var wrap = getNoticesWrap();
    if (wrap) {
      wrap.querySelectorAll(".woocommerce-error").forEach(function (el) {
        el.setAttribute("data-cc-error-hidden", "1");
        el.style.display = "none";
      });
    }

    document.querySelectorAll(".woocommerce-NoticeGroup-checkout .woocommerce-error").forEach(function (el) {
      el.setAttribute("data-cc-error-hidden", "1");
      el.style.display = "none";
    });
  }

  function buildContextLines(message) {
    var lines = [];
    var ctx = cfg.geideaContext || {};

    if (ctx.orderId) {
      lines.push("Order: #" + ctx.orderId);
    }
    if (ctx.amount && ctx.currency) {
      lines.push("Amount: " + ctx.amount + " " + ctx.currency);
    } else if (ctx.amount) {
      lines.push("Amount: " + ctx.amount);
    }

    if (lines.length) {
      return lines.join("\n") + "\n\n" + message;
    }

    return message;
  }

  function buildWhatsAppUrl(message) {
    var base = cfg.whatsappBase || "https://wa.me/201555458555";
    var prefix = cfg.whatsappPrefix || "Hi ConsuCorner, I need help with checkout:";
    var checkoutUrl = cfg.checkoutUrl || window.location.href;
    var body = prefix + "\n\n" + buildContextLines(message) + "\n\n" + checkoutUrl;
    return base + "?text=" + encodeURIComponent(body);
  }

  function setModalTitle(title) {
    var modal = getModal();
    if (!modal) return;
    var titleEl = modal.querySelector(".cc-checkout-error-modal__title");
    if (titleEl) {
      titleEl.textContent = title;
    }
  }

  function openModal(message, options) {
    options = options || {};
    message = (message || "").trim();
    if (!message) return;

    var signature = (options.title || "") + "|" + message;
    if (signature === shownFor) return;
    shownFor = signature;

    var modal = getModal();
    if (!modal) return;

    var body = modal.querySelector(".cc-checkout-error-modal__body");
    var wa = document.getElementById("cc-checkout-error-whatsapp");
    var title =
      options.title ||
      (cfg.strings && cfg.strings.errorTitle) ||
      "Something went wrong";

    setModalTitle(title);

    if (body) {
      body.textContent = message;
    }
    if (wa) {
      wa.setAttribute("href", buildWhatsAppUrl(message));
    }

    hideTopCheckoutErrors();

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("cc-checkout-error-modal-open");

    var dismiss = modal.querySelector(".cc-checkout-error-modal__dismiss");
    if (dismiss) dismiss.focus();
  }

  function closeModal() {
    var modal = getModal();
    if (!modal) return;

    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("cc-checkout-error-modal-open");
    shownFor = "";
    clearGeideaFlowFlags();
  }

  function showCancelModal() {
    var title = (cfg.strings && cfg.strings.cancelTitle) || "Payment cancelled";
    var message =
      (cfg.strings && cfg.strings.cancelMessage) ||
      "You closed the payment window. Need help completing your order?";
    openModal(message, { title: title });
  }

  function handleStoredGeideaState() {
    var storedError = storageGet(ERROR_KEY);
    if (storedError) {
      storageRemove(ERROR_KEY);
      storageRemove(FLOW_KEY);
      openModal(storedError);
      return true;
    }

    if (cfg.isCheckout && isFreshGeideaFlow()) {
      showCancelModal();
      return true;
    }

    return false;
  }

  function handlePendingAlert() {
    if (window.__ccGeideaAlertPending) {
      var pending = window.__ccGeideaAlertPending;
      window.__ccGeideaAlertPending = "";
      openModal(pending);
      return true;
    }
    return false;
  }

  window.ccCheckoutErrorModal = {
    show: openModal,
    close: closeModal,
  };

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-cc-checkout-error-close]")) {
      event.preventDefault();
      closeModal();
    }
  });

  document.addEventListener("keydown", function (event) {
    var modal = getModal();
    if (!modal || modal.hidden) return;
    if (event.key === "Escape") {
      event.preventDefault();
      closeModal();
    }
  });

  function init() {
    if (cfg.isGeideaFlow) {
      setGeideaFlowFlag();
    }

    if (handlePendingAlert()) {
      return;
    }

    handleStoredGeideaState();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
