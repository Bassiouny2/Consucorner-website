(function () {
  "use strict";

  var cfg = window.ccOffersLinkBuilder || {};
  var i18n = cfg.i18n || {};

  function debounce(fn, wait) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(null, args);
      }, wait);
    };
  }

  function copyFromInput(inputId, button) {
    var input = document.getElementById(inputId);
    if (!input) return;

    var text = input.value || "";
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        if (!button) return;
        var original = button.textContent;
        button.textContent = i18n.copied || "Copied!";
        setTimeout(function () {
          button.textContent = original || i18n.copyLink || "Copy link";
        }, 1600);
      });
      return;
    }

    input.select();
    document.execCommand("copy");
  }

  function getBaseUrl() {
    var baseEl = document.getElementById("cc-link-builder-base");
    if (!baseEl) {
      return cfg.shopUrl || "";
    }
    var option = baseEl.options[baseEl.selectedIndex];
    if (option && option.getAttribute("data-base-url")) {
      return option.getAttribute("data-base-url");
    }
    return cfg.shopUrl || "";
  }

  function getBaseSpecialtySlug() {
    var baseEl = document.getElementById("cc-link-builder-base");
    return baseEl ? baseEl.value || "" : "";
  }

  function postCount(body, previewEl) {
    if (!cfg.ajaxUrl || !cfg.nonce) return;

    var data = new FormData();
    data.set("action", "cc_offers_link_builder_shop_count");
    data.set("nonce", cfg.nonce);
    Object.keys(body).forEach(function (key) {
      var value = body[key];
      if (Array.isArray(value)) {
        value.forEach(function (item) {
          data.append(key + "[]", item);
        });
      } else if (value !== undefined && value !== null && value !== "") {
        data.set(key, value);
      }
    });

    fetch(cfg.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: data,
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (!previewEl) return;
        if (json && json.success && json.data && typeof json.data.count === "number") {
          var tpl = i18n.previewCount || "%d products match this link.";
          previewEl.textContent = tpl.replace("%d", String(json.data.count));
          return;
        }
        previewEl.textContent = i18n.previewError || "Could not load the product count.";
      })
      .catch(function () {
        if (previewEl) {
          previewEl.textContent = i18n.previewError || "Could not load the product count.";
        }
      });
  }

  function initLinkBuilder() {
    var urlEl = document.getElementById("cc-shop-builder-url");
    var previewEl = document.querySelector("[data-cc-shop-preview]");
    var copyBtn = document.getElementById("cc-shop-copy-link");
    var baseEl = document.getElementById("cc-link-builder-base");
    var minEl = document.getElementById("cc-shop-builder-min-price");
    var maxEl = document.getElementById("cc-shop-builder-max-price");
    if (!urlEl) return;

    function collectFilters() {
      var body = {
        base_specialty: getBaseSpecialtySlug(),
      };
      document.querySelectorAll("[data-cc-shop-tax]").forEach(function (select) {
        var tax = select.getAttribute("data-cc-shop-tax");
        if (!tax || tax === "specialty") return;
        var slugs = Array.prototype.slice
          .call(select.selectedOptions || [])
          .map(function (opt) {
            return opt.value;
          })
          .filter(Boolean);
        if (slugs.length) {
          body["tax_" + tax] = slugs;
        }
      });
      if (minEl && minEl.value) body.min_price = minEl.value;
      if (maxEl && maxEl.value) body.max_price = maxEl.value;
      return body;
    }

    function buildUrl() {
      var base = getBaseUrl();
      var params = new URLSearchParams();

      document.querySelectorAll("[data-cc-shop-tax]").forEach(function (select) {
        var tax = select.getAttribute("data-cc-shop-tax");
        if (!tax || tax === "specialty") return;
        var slugs = Array.prototype.slice
          .call(select.selectedOptions || [])
          .map(function (opt) {
            return opt.value;
          })
          .filter(Boolean);
        if (slugs.length) params.set(tax, slugs.join(","));
      });

      if (minEl && parseFloat(minEl.value) > 0) {
        params.set("min_price", String(minEl.value));
      }
      if (maxEl && parseFloat(maxEl.value) > 0) {
        params.set("max_price", String(maxEl.value));
      }

      var query = params.toString();
      var url = query ? base + (base.indexOf("?") === -1 ? "?" : "&") + query : base;
      urlEl.value = url;
      urlEl.setAttribute("data-cc-shop-base", base);
      return collectFilters();
    }

    var refreshPreview = debounce(function () {
      buildUrl();
      var body = collectFilters();
      var hasFilters = Object.keys(body).some(function (key) {
        return key.indexOf("tax_") === 0;
      });
      var hasBase = !!getBaseSpecialtySlug();
      if (!hasFilters && !(minEl && minEl.value) && !(maxEl && maxEl.value) && !hasBase) {
        if (previewEl) previewEl.textContent = i18n.previewEmpty || "";
        return;
      }
      postCount(body, previewEl);
    }, 250);

    if (baseEl) baseEl.addEventListener("change", refreshPreview);
    document.querySelectorAll("[data-cc-shop-tax]").forEach(function (select) {
      select.addEventListener("change", refreshPreview);
    });
    if (minEl) minEl.addEventListener("input", refreshPreview);
    if (maxEl) maxEl.addEventListener("input", refreshPreview);

    if (copyBtn) {
      copyBtn.addEventListener("click", function () {
        buildUrl();
        copyFromInput("cc-shop-builder-url", copyBtn);
      });
    }

    refreshPreview();
  }

  document.addEventListener("DOMContentLoaded", initLinkBuilder);
})();
