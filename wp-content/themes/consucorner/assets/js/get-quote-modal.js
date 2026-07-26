(function () {
  "use strict";

  var previousOverflow = "";
  var lastTrigger = null;

  function getModal() {
    return document.getElementById("ccQuoteModal");
  }

  function getQuoteToken() {
    var match = document.cookie.match(/(?:^|; )cc_quote_token=([^;]*)/);
    if (match && match[1]) {
      return decodeURIComponent(match[1]);
    }

    var token = String(Date.now()) + "-" + Math.random().toString(36).slice(2);
    document.cookie =
      "cc_quote_token=" +
      encodeURIComponent(token) +
      "; path=/; SameSite=Lax; max-age=600";
    return token;
  }

  function setCookie(name, value) {
    document.cookie =
      name +
      "=" +
      encodeURIComponent(value || "") +
      "; path=/; SameSite=Lax; max-age=600";
  }

  function prefillProductName(modal, productName) {
    var candidates = modal.querySelectorAll(
      'input[type="hidden"], input[name*="page"], input[id*="page"]'
    );

    Array.prototype.slice.call(candidates).forEach(function (field) {
      var name = (field.getAttribute("name") || "").toLowerCase();
      var id = (field.getAttribute("id") || "").toLowerCase();

      if (
        name.indexOf("nonce") !== -1 ||
        name.indexOf("form_id") !== -1 ||
        name.indexOf("post_id") !== -1 ||
        id.indexOf("nonce") !== -1
      ) {
        return;
      }

      if (
        name.indexOf("page") !== -1 ||
        id.indexOf("page") !== -1 ||
        /^hidden-\d+$/.test(name)
      ) {
        field.value = productName;
        field.dispatchEvent(new Event("input", { bubbles: true }));
        field.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
  }

  function revealForminatorForm(modal) {
    var forms = modal.querySelectorAll("form.forminator-custom-form");
    Array.prototype.slice.call(forms).forEach(function (form) {
      form.style.removeProperty("display");

      if (window.jQuery) {
        window.jQuery(form).show();
      }
    });

    var containers = modal.querySelectorAll(".forminator-ui");
    Array.prototype.slice.call(containers).forEach(function (container) {
      container.style.removeProperty("display");
    });

    if (window.jQuery) {
      window.jQuery(document).trigger("forminator.front.loaded");
    }

    window.dispatchEvent(new Event("resize"));
  }

  function openModal(trigger) {
    var modal = getModal();
    if (!modal) {
      return false;
    }

    var closeBtn = modal.querySelector(".cc-quote-modal__close");
    var productName = trigger.getAttribute("data-product-name") || "";

    lastTrigger = trigger;
    getQuoteToken();
    setCookie("cc_quote_product", productName);
    prefillProductName(modal, productName);

    modal.removeAttribute("hidden");
    modal.setAttribute("aria-hidden", "false");
    revealForminatorForm(modal);
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    if (closeBtn) {
      closeBtn.focus();
    }

    return true;
  }

  function closeModal() {
    var modal = getModal();
    if (!modal || modal.hasAttribute("hidden")) {
      return;
    }

    modal.setAttribute("hidden", "");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = previousOverflow;

    if (lastTrigger && typeof lastTrigger.focus === "function") {
      lastTrigger.focus();
    }
  }

  document.addEventListener("click", function (event) {
    var trigger = event.target.closest(".js-cc-quote-trigger, #spGetQuoteBtn");
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      openModal(trigger);
      return;
    }

    if (
      event.target.closest(".cc-quote-modal__close") ||
      event.target.classList.contains("cc-quote-modal__backdrop")
    ) {
      event.preventDefault();
      closeModal();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeModal();
    }
  });
})();
