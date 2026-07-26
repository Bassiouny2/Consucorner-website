/**
 * bundle-builder.js
 * ============================================================
 * Powers the mix-and-match "pick N from a pool" bundle cards on the
 * /bundles/ page.
 *
 * Per card:
 *   - qty steppers on each pool product (clamped to per-product stock)
 *   - a live "X / N selected" counter
 *   - "Add bundle to cart" enabled only when the total selected qty === N
 *   - on submit, POSTs the selection to cc_bundle_add_to_cart and, on
 *     success, resets the card and refreshes the mini-cart drawer
 * ============================================================
 */
(function () {
  "use strict";

  var CFG     = (typeof window !== "undefined" && window.ccBundleBuilderData) || {};
  var AJAX_URL = CFG.ajaxUrl || "/wp-admin/admin-ajax.php";
  var NONCE    = CFG.nonce || "";

  function getSelectedTotal(card) {
    var total = 0;
    card.querySelectorAll(".cc-bundle-pool-item").forEach(function (row) {
      total += parseInt(row.querySelector(".cc-bundle-qty-val").textContent, 10) || 0;
    });
    return total;
  }

  function refreshCard(card) {
    var size    = parseInt(card.getAttribute("data-bundle-size"), 10) || 0;
    var total   = getSelectedTotal(card);
    var counter = card.querySelector(".cc-bundle-selected");
    var submit  = card.querySelector(".cc-bundle-card__submit");
    var bar     = card.querySelector(".cc-bundle-card__progress-bar");
    var complete = size > 0 && total === size;

    if (counter) counter.textContent = String(total);
    if (submit) submit.disabled = !complete;
    if (bar) {
      var pct = size > 0 ? Math.min(100, Math.round((total / size) * 100)) : 0;
      bar.style.width = pct + "%";
    }
    card.classList.toggle("is-complete", complete);

    card.querySelectorAll(".cc-bundle-pool-item").forEach(function (row) {
      if (row.classList.contains("is-disabled")) return;
      var qtyEl  = row.querySelector(".cc-bundle-qty-val");
      var plus   = row.querySelector(".cc-bundle-qty-plus");
      var minus  = row.querySelector(".cc-bundle-qty-minus");
      var qty    = parseInt(qtyEl.textContent, 10) || 0;
      var max    = parseInt(row.getAttribute("data-max"), 10) || 0;

      row.classList.toggle("is-selected", qty > 0);
      if (plus) plus.disabled = total >= size || (max > 0 && qty >= max);
      if (minus) minus.disabled = qty <= 0;
    });
  }

  function showMessage(card, text, isError) {
    var msg = card.querySelector(".cc-bundle-card__msg");
    if (!msg) return;
    msg.textContent = text || "";
    msg.classList.toggle("is-error", !!isError);
    msg.classList.toggle("is-success", !isError && !!text);
  }

  function resetCard(card) {
    card.querySelectorAll(".cc-bundle-pool-item .cc-bundle-qty-val").forEach(function (el) {
      el.textContent = "0";
    });
    refreshCard(card);
  }

  function collectSelections(card) {
    var selections = {};
    card.querySelectorAll(".cc-bundle-pool-item").forEach(function (row) {
      var qty = parseInt(row.querySelector(".cc-bundle-qty-val").textContent, 10) || 0;
      if (qty > 0) {
        selections[row.getAttribute("data-product-id")] = qty;
      }
    });
    return selections;
  }

  function submitBundle(card) {
    var submit = card.querySelector(".cc-bundle-card__submit");
    if (!submit || submit.disabled || submit.classList.contains("is-loading")) return;

    var bundleId = card.getAttribute("data-bundle-id");
    var packKey = card.getAttribute("data-pack-key") || "";
    var selections = collectSelections(card);

    submit.classList.add("is-loading");
    submit.disabled = true;
    showMessage(card, "", false);

    var body = new URLSearchParams();
    body.set("action", "cc_bundle_add_to_cart");
    body.set("nonce", NONCE);
    body.set("bundle_id", bundleId);
    if (packKey) {
      body.set("pack_key", packKey);
    }
    body.set("selections", JSON.stringify(selections));

    fetch(AJAX_URL, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body.toString(),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        submit.classList.remove("is-loading");

        if (data && data.success) {
          showMessage(card, "Bundle added to your cart!", false);
          resetCard(card);

          if (window.CCMiniCart && typeof window.CCMiniCart.loadFromWC === "function") {
            window.CCMiniCart.loadFromWC(function () {
              if (typeof window.CCMiniCart.open === "function") {
                window.CCMiniCart.open();
              }
            });
          }
        } else {
          var message = (data && data.data && data.data.message) || "Could not add this bundle. Please try again.";
          showMessage(card, message, true);
          refreshCard(card);
        }
      })
      .catch(function () {
        submit.classList.remove("is-loading");
        showMessage(card, "Network error. Please try again.", true);
        refreshCard(card);
      });
  }

  function handleStep(row, delta) {
    var qtyEl = row.querySelector(".cc-bundle-qty-val");
    var max   = parseInt(row.getAttribute("data-max"), 10) || 0;
    var qty   = parseInt(qtyEl.textContent, 10) || 0;
    var next  = Math.max(0, qty + delta);

    if (max > 0 && next > max) next = max;

    if (delta > 0) {
      var card = row.closest(".cc-bundle-card");
      var size = card ? parseInt(card.getAttribute("data-bundle-size"), 10) || 0 : 0;
      var total = card ? getSelectedTotal(card) : 0;
      if (size > 0 && total + (next - qty) > size) {
        next = qty + Math.max(0, size - total);
      }
    }

    qtyEl.textContent = String(next);
  }

  function init() {
    document.querySelectorAll(".cc-bundle-card").forEach(function (card) {
      refreshCard(card);
    });

    document.addEventListener("click", function (e) {
      var plus = e.target.closest(".cc-bundle-qty-plus");
      var minus = e.target.closest(".cc-bundle-qty-minus");
      if (plus || minus) {
        var row = e.target.closest(".cc-bundle-pool-item");
        if (!row || row.classList.contains("is-disabled")) return;
        handleStep(row, plus ? 1 : -1);
        var card = row.closest(".cc-bundle-card");
        if (card) refreshCard(card);
        return;
      }

      var submitBtn = e.target.closest(".cc-bundle-card__submit");
      if (submitBtn) {
        var submitCard = submitBtn.closest(".cc-bundle-card");
        if (submitCard) submitBundle(submitCard);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
