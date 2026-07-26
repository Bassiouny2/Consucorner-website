/**
 * Shareable cart link — create, copy, native share.
 */
(function (window, document) {
  "use strict";

  var cfg = window.ccCartShare || {};
  var strings = cfg.strings || {};
  var pendingUrl = "";

  function getModal() {
    return document.getElementById("cc-cart-share-modal");
  }

  function getInput() {
    return document.getElementById("cc-cart-share-url");
  }

  function getErrorEl() {
    var modal = getModal();
    return modal ? modal.querySelector(".cc-cart-share-modal__error") : null;
  }

  function setError(message) {
    var el = getErrorEl();
    if (!el) return;
    if (message) {
      el.textContent = message;
      el.hidden = false;
    } else {
      el.textContent = "";
      el.hidden = true;
    }
  }

  function openModal(url) {
    var modal = getModal();
    var input = getInput();
    if (!modal || !input) return;

    pendingUrl = url || "";
    input.value = pendingUrl;
    setError("");

    var nativeBtn = modal.querySelector("[data-cc-cart-share-native]");
    if (nativeBtn) {
      nativeBtn.hidden = !(navigator.share && pendingUrl);
    }

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("cc-cart-share-modal-open");
    input.focus();
    input.select();
  }

  function closeModal() {
    var modal = getModal();
    if (!modal) return;

    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("cc-cart-share-modal-open");
    pendingUrl = "";
  }

  function createShareLink() {
    if (!cfg.ajaxUrl || !cfg.nonce) {
      setError(strings.error || "Could not create share link.");
      return Promise.reject();
    }

    var payload = new window.FormData();
    payload.append("action", cfg.action || "cc_create_cart_share");
    payload.append("nonce", cfg.nonce);

    return window
      .fetch(cfg.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: payload,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data || !response.data.url) {
          throw new Error(
            (response && response.data && response.data.message) ||
              strings.error ||
              "Could not create share link.",
          );
        }
        return response.data.url;
      });
  }

  function handleShareClick(event) {
    var btn = event.target.closest("[data-cc-cart-share]");
    if (!btn) return;

    event.preventDefault();
    btn.disabled = true;
    var originalText = btn.textContent;
    btn.textContent = strings.loading || "Creating link…";

    openModal("");
    setError("");

    createShareLink()
      .then(function (url) {
        openModal(url);
      })
      .catch(function (err) {
        setError(err && err.message ? err.message : strings.error);
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = originalText;
      });
  }

  function copyLink() {
    var url = pendingUrl || (getInput() ? getInput().value : "");
    if (!url) return;

    function onCopied() {
      var copyBtn = document.querySelector("[data-cc-cart-share-copy]");
      if (!copyBtn) return;
      var prev = copyBtn.textContent;
      copyBtn.textContent = strings.copied || "Copied!";
      window.setTimeout(function () {
        copyBtn.textContent = prev;
      }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(onCopied).catch(function () {
        fallbackCopy(url);
        onCopied();
      });
      return;
    }

    fallbackCopy(url);
    onCopied();
  }

  function fallbackCopy(text) {
    var input = getInput();
    if (!input) return;
    input.value = text;
    input.focus();
    input.select();
    try {
      document.execCommand("copy");
    } catch (e) {
      /* ignore */
    }
  }

  function nativeShare() {
    var url = pendingUrl || (getInput() ? getInput().value : "");
    if (!url || !navigator.share) return;

    navigator
      .share({
        title: document.title,
        text: strings.description || "",
        url: url,
      })
      .catch(function () {
        /* user cancelled */
      });
  }

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-cc-cart-share]")) {
      handleShareClick(event);
      return;
    }
    if (event.target.closest("[data-cc-cart-share-copy]")) {
      event.preventDefault();
      copyLink();
      return;
    }
    if (event.target.closest("[data-cc-cart-share-native]")) {
      event.preventDefault();
      nativeShare();
      return;
    }
    if (event.target.closest("[data-cc-cart-share-close]")) {
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
})(window, document);
