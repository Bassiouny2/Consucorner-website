/* ============================================================
 * Checkout — ConsuCorner
 *
 * Card / COD toggle.  Key design decisions:
 *
 *  1. A local `selectedMethod` variable is the source of truth.
 *     We never read back from WC radios to decide which pill to
 *     highlight — only write TO them so WC can submit the order.
 *
 *  2. update_checkout is NOT fired on payment-method change.
 *     We have no inline gateway form that needs refreshing, and
 *     firing it caused WC to return fragments that reset radios
 *     back to the server-side default, breaking the toggle.
 *
 *  3. Event delegation (document-level click) means the handler
 *     still works after any partial DOM refresh.
 * ============================================================ */
(function () {
  "use strict";

  /* ── Internal state ── */
  var selectedMethod = null;

  /* ── Helpers ── */
  function toArray(nl) {
    return Array.prototype.slice.call(nl);
  }

  function isCodMethod(id) {
    return /(cod|cash\s*on\s*delivery|cheque|bacs|bank\s*transfer)/i.test(
      String(id || ""),
    );
  }

  /* Is the given gateway ID (or button) a card-style gateway? */
  function isCardMethod(id, btn) {
    if (btn) {
      if (btn.getAttribute("data-is-card") === "1") return true;
      if (btn.getAttribute("data-is-card") === "0") return false;
    }
    return !isCodMethod(id);
  }

  /* Write the checked state to the hidden WC radio for the chosen method. */
  function checkRadio(methodId) {
    toArray(document.querySelectorAll('input[name="payment_method"]')).forEach(
      function (radio) {
        radio.checked = radio.value === methodId;
      },
    );
  }

  /* Apply all visual changes for a given payment method id. */
  function applyUiState(methodId) {
    var cardDetails = document.getElementById("co-card-details");
    var submitBtn = document.getElementById("co-submit-btn");
    var selectedBtn = null;

    /* Update pill active classes + aria-pressed */
    toArray(document.querySelectorAll(".co-pay-btn")).forEach(function (btn) {
      var active = btn.getAttribute("data-method") === methodId;
      btn.classList.toggle("co-pay-btn--active", active);
      btn.setAttribute("aria-pressed", active ? "true" : "false");
      if (active) selectedBtn = btn;
    });

    /* Show / hide card-detail panel */
    var isCard = isCardMethod(methodId, selectedBtn);
    if (cardDetails) {
      cardDetails.classList.toggle("co-card-details--hidden", !isCard);
    }

    /* Update submit button label */
    if (submitBtn) {
      var label;
      if (isCard) {
        label =
          submitBtn.getAttribute("data-pay-label") || submitBtn.textContent;
      } else if (isCodMethod(methodId)) {
        label =
          submitBtn.getAttribute("data-cod-label") || submitBtn.textContent;
      } else {
        label =
          submitBtn.getAttribute("data-place-label") || submitBtn.textContent;
      }
      submitBtn.textContent = label;
      submitBtn.value = label;
    }
  }

  /* Public entry point: select a payment method by its gateway id. */
  function setMethod(methodId) {
    if (!methodId || methodId === selectedMethod) return;
    selectedMethod = methodId;

    checkRadio(methodId);
    applyUiState(methodId);
  }

  /* ── Initialise from PHP-rendered radio state ── */
  function initPaymentUi() {
    /* Find which radio PHP pre-checked */
    var radios = toArray(
      document.querySelectorAll('input[name="payment_method"]'),
    );
    var checked = null;
    radios.forEach(function (r) {
      if (r.checked) checked = r;
    });

    if (checked) {
      /* Apply UI without calling setMethod so we don't re-fire checkRadio */
      selectedMethod = checked.value;
      applyUiState(selectedMethod);
      return;
    }

    /* Fallback: pick the first pill */
    var firstBtn = document.querySelector(".co-pay-btn");
    if (firstBtn) {
      setMethod(firstBtn.getAttribute("data-method"));
    }
  }

  /* ── Delegated click on payment pills ── */
  document.addEventListener(
    "click",
    function (event) {
      var btn = event.target.closest
        ? event.target.closest(".co-pay-btn")
        : null;
      if (!btn) return;
      event.preventDefault();
      var method = btn.getAttribute("data-method");
      if (method) setMethod(method);
    },
    false,
  );

  /* ── Wallet credit toggle ── */
  function attachWalletToggle() {
    var toggle = document.querySelector(".co-wallet-toggle__input");
    if (!toggle || !window.ccCheckoutWallet) return;

    toggle.addEventListener("change", function () {
      var payload = new window.FormData();
      payload.append("action", window.ccCheckoutWallet.action);
      payload.append("nonce", window.ccCheckoutWallet.nonce);
      payload.append("enabled", toggle.checked ? "yes" : "no");

      toggle.disabled = true;

      window
        .fetch(window.ccCheckoutWallet.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: payload,
        })
        .then(function (response) {
          return response.json();
        })
        .then(function (response) {
          if (!response || !response.success) {
            throw new Error(
              response && response.data && response.data.message
                ? response.data.message
                : "Unable to update wallet credit.",
            );
          }
          window.location.reload();
        })
        .catch(function () {
          toggle.checked = !toggle.checked;
          toggle.disabled = false;
        });
    });
  }

  /* ── Card / Expiry / CVV formatters (cosmetic only) ── */
  function isAutofill(el, prevLen) {
    return Math.abs(el.value.length - prevLen) > 1;
  }

  function attachFormatter(el, transform) {
    if (!el) return;
    var prev = 0;
    el.addEventListener("focus", function () {
      prev = this.value.length;
    });
    el.addEventListener("input", function () {
      if (isAutofill(this, prev)) {
        prev = this.value.length;
        return;
      }
      var next = transform(this.value);
      if (next !== this.value) this.value = next;
      prev = this.value.length;
    });
  }

  function applyCardFormatters() {
    attachFormatter(document.getElementById("co-card-number"), function (v) {
      return v
        .replace(/\D/g, "")
        .slice(0, 19)
        .replace(/(.{4})/g, "$1 ")
        .trim();
    });
    attachFormatter(document.getElementById("co-expiry"), function (v) {
      var d = v.replace(/\D/g, "").slice(0, 4);
      return d.length > 2 ? d.slice(0, 2) + "/" + d.slice(2) : d;
    });
    attachFormatter(document.getElementById("co-cvv"), function (v) {
      return v.replace(/\D/g, "").slice(0, 4);
    });
  }

  /* ── Client-side required-field guard ── */
  function getFieldWrapper(el) {
    if (!el) return null;
    if (el.classList && el.classList.contains("co-phone-local")) {
      return el.closest(".co-field--phone") || el.closest(".co-field");
    }
    return el.closest(".co-field");
  }

  function getFieldErrorHost(el) {
    var wrap = getFieldWrapper(el);
    if (!wrap) return el;
    if (el.classList && el.classList.contains("co-phone-local")) {
      return wrap.querySelector(".co-phone-input") || el;
    }
    return wrap.querySelector(".co-select-wrap") || el;
  }

  function clearFieldError(el) {
    if (!el) return;
    var wrap = getFieldWrapper(el);
    if (wrap) {
      var err = wrap.querySelector(".co-field-error");
      if (err) err.remove();
    }
    el.style.borderColor = "";
    el.classList.remove("co-input--error");
    if (wrap) {
      var phoneWrap = wrap.querySelector(".co-phone-input");
      if (phoneWrap) phoneWrap.classList.remove("co-input--error");
    }
  }

  function clearAllFieldErrors(form) {
    form.querySelectorAll(".co-input").forEach(clearFieldError);
    form.querySelectorAll(".co-phone-input.co-input--error").forEach(function (wrap) {
      wrap.classList.remove("co-input--error");
    });
  }

  function setFieldError(el, message) {
    if (!el) return;
    clearFieldError(el);
    var host = getFieldErrorHost(el);
    if (el.classList && el.classList.contains("co-phone-local") && host) {
      host.classList.add("co-input--error");
    } else {
      el.style.borderColor = "#ef4444";
      el.classList.add("co-input--error");
    }
    if (!host) return;
    var span = document.createElement("span");
    span.className = "co-field-error";
    span.setAttribute("role", "alert");
    span.textContent = message || "This field is required";
    host.insertAdjacentElement("afterend", span);
  }

  var wcFieldMatchers = [
    { id: "billing_email", patterns: [/email/i, /e-mail/i] },
    { id: "billing_phone_local", patterns: [/phone/i, /mobile/i] },
    { id: "billing_first_name", patterns: [/first name/i] },
    { id: "billing_last_name", patterns: [/last name/i, /surname/i] },
    { id: "billing_address_1", patterns: [/address/i, /shipping/i] },
    { id: "billing_state", patterns: [/governorate/i, /state/i, /region/i] },
  ];

  function mapWcErrorsToFields() {
    var nodes = document.querySelectorAll(
      ".checkout-notices-wrap .woocommerce-error li, .woocommerce-NoticeGroup-checkout .woocommerce-error li",
    );
    toArray(nodes).forEach(function (li) {
      var text = (li.textContent || "").trim();
      if (!text) return;
      wcFieldMatchers.forEach(function (entry) {
        var matched = entry.patterns.some(function (pattern) {
          return pattern.test(text);
        });
        if (!matched) return;
        var el = document.getElementById(entry.id);
        if (el) setFieldError(el, text);
      });
    });
    hideTopCheckoutErrors();
  }

  function hideTopCheckoutErrors() {
    document
      .querySelectorAll(
        ".checkout-notices-wrap .woocommerce-error, .woocommerce-NoticeGroup-checkout .woocommerce-error",
      )
      .forEach(function (el) {
        el.setAttribute("data-cc-error-hidden", "1");
        el.style.display = "none";
      });
  }

  /* ── Egypt mobile phone (+20 locked) ── */
  var EG_MOBILE_RE = /^1(0|1|2|5)\d{8}$/;

  function phoneDigitsOnly(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function normalizeLocalPhoneDigits(raw) {
    var digits = phoneDigitsOnly(raw);

    if (digits.indexOf("20") === 0 && digits.length >= 3) {
      digits = digits.slice(2);
    }

    if (digits.charAt(0) === "0") {
      digits = digits.slice(1);
    }

    return digits.slice(0, 10);
  }

  function formatLocalPhoneDisplay(digits) {
    var d = digits.slice(0, 10);

    if (d.length <= 2) {
      return d;
    }

    if (d.length <= 6) {
      return d.slice(0, 2) + " " + d.slice(2);
    }

    return d.slice(0, 2) + " " + d.slice(2, 6) + " " + d.slice(6);
  }

  function isValidEgyptMobileLocal(digits) {
    return EG_MOBILE_RE.test(digits);
  }

  function syncBillingPhoneHidden() {
    var localInput = document.getElementById("billing_phone_local");
    var hiddenInput = document.getElementById("cc_billing_phone");
    if (!localInput || !hiddenInput) return "";

    var localDigits = normalizeLocalPhoneDigits(localInput.value);
    hiddenInput.value = isValidEgyptMobileLocal(localDigits)
      ? "+20" + localDigits
      : "";

    return localDigits;
  }

  function attachEgyptPhoneField() {
    var localInput = document.getElementById("billing_phone_local");
    var hiddenInput = document.getElementById("cc_billing_phone");
    if (!localInput || !hiddenInput) return;

    var prevLen = 0;

    localInput.addEventListener("focus", function () {
      prevLen = this.value.length;
    });

    localInput.addEventListener("input", function () {
      if (isAutofill(this, prevLen)) {
        prevLen = this.value.length;
        syncBillingPhoneHidden();
        return;
      }

      var digits = normalizeLocalPhoneDigits(this.value);
      var formatted = formatLocalPhoneDisplay(digits);
      if (formatted !== this.value) {
        this.value = formatted;
      }
      prevLen = this.value.length;
      syncBillingPhoneHidden();
      clearFieldError(this);
    });

    localInput.addEventListener("blur", function () {
      syncBillingPhoneHidden();
    });

    syncBillingPhoneHidden();
  }

  function validateEgyptPhoneField() {
    var localInput = document.getElementById("billing_phone_local");
    if (!localInput) return null;

    var strings = window.ccCheckoutPhone || {};
    var requiredMessage =
      strings.requiredPhone ||
      (window.ccCheckoutErrors &&
        window.ccCheckoutErrors.strings &&
        window.ccCheckoutErrors.strings.requiredFields) ||
      "This field is required";
    var invalidMessage =
      strings.invalidPhone ||
      "Please enter a valid Egyptian mobile number (e.g. 10 1234 5678).";

    var localDigits = syncBillingPhoneHidden();

    if (!localDigits) {
      setFieldError(localInput, requiredMessage);
      return localInput;
    }

    if (!isValidEgyptMobileLocal(localDigits)) {
      setFieldError(localInput, invalidMessage);
      return localInput;
    }

    return null;
  }

  function attachFormGuard() {
    var form = document.querySelector("form.checkout");
    if (!form) return;

    var requiredMessage =
      (window.ccCheckoutErrors &&
        window.ccCheckoutErrors.strings &&
        window.ccCheckoutErrors.strings.requiredFields) ||
      "This field is required";

    form.querySelectorAll(".co-input").forEach(function (el) {
      el.addEventListener("input", function () {
        clearFieldError(this);
      });
      el.addEventListener("change", function () {
        clearFieldError(this);
      });
    });

    form.addEventListener(
      "submit",
      function (e) {
        clearAllFieldErrors(form);
        syncBillingPhoneHidden();

        var ids = [
          "billing_email",
          "billing_first_name",
          "billing_last_name",
          "billing_address_1",
          "billing_state",
        ];
        var firstError = validateEgyptPhoneField();

        ids.forEach(function (id) {
          var el = document.getElementById(id);
          if (!el) return;
          var empty = !String(el.value || "").trim();
          if (empty) {
            setFieldError(el, requiredMessage);
            if (!firstError) firstError = el;
          }
        });

        if (firstError) {
          e.preventDefault();
          firstError.focus();
          firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      },
      true,
    );

    if (window.jQuery) {
      window.jQuery(document.body).on("checkout_error", function () {
        window.setTimeout(mapWcErrorsToFields, 50);
      });
      window.jQuery(document.body).on("updated_checkout", function () {
        window.setTimeout(mapWcErrorsToFields, 50);
      });
    }

    window.setTimeout(mapWcErrorsToFields, 50);
  }

  /* ── Boot ── */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initPaymentUi();
      applyCardFormatters();
      attachWalletToggle();
      attachEgyptPhoneField();
      attachFormGuard();
    });
  } else {
    initPaymentUi();
    applyCardFormatters();
    attachWalletToggle();
    attachEgyptPhoneField();
    attachFormGuard();
  }
})();
