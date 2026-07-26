/* ConsuCorner cart.js
 *
 * Drives quantity ± buttons on the custom WooCommerce cart template.
 * Qty changes use the same AJAX endpoint as mini-cart (cc_update_cart_qty),
 * then reload the page so totals stay in sync with the server.
 *
 *  - qty + / qty -  →  cc_update_cart_qty (WC session) → location.reload()
 *  - .cart-remove   →  normal WC remove link
 *  - coupon apply   →  standard form submit (apply_coupon)
 */
(function () {
  var form = document.querySelector(".woocommerce-cart-form");
  if (!form) return;

  var cfg = window.consuCartData || {};
  var ajaxUrl = cfg.ajaxUrl || "";
  var nonce = cfg.nonce || "";

  var pageCartEl = document.querySelector(".page-cart");
  var topPillEl = document.querySelector(".cart-top-pill");
  var listHeadEl = document.querySelector(".cart-list-head");
  var summaryCardEl = document.querySelector(".cart-summary-card");
  var checkoutBtn = document.querySelector(".cart-checkout-btn");

  var pendingKey = null;

  function getCartKey(cartItem) {
    var qtyRow = cartItem.querySelector(".cart-qty-row");
    if (qtyRow && qtyRow.getAttribute("data-cart-key")) {
      return qtyRow.getAttribute("data-cart-key");
    }
    return cartItem.getAttribute("data-key") || "";
  }

  function getProductId(cartItem) {
    var removeEl = cartItem.querySelector(".cart-remove");
    if (removeEl && removeEl.getAttribute("data-product_id")) {
      return removeEl.getAttribute("data-product_id");
    }
    return "";
  }

  function setQtyDisplay(cartItem, qty) {
    var qtyEl = cartItem.querySelector(".qty-value");
    var qtyInput = cartItem.querySelector(".qty-input");
    if (qtyEl) qtyEl.textContent = String(qty);
    if (qtyInput) qtyInput.value = String(qty);
  }

  function syncLinePrice(cartItem, qty) {
    var unitPrice = parseFloat(cartItem.getAttribute("data-price")) || 0;
    var nowEl = cartItem.querySelector(".cart-item-price .price-now");
    if (nowEl && unitPrice > 0) {
      nowEl.dataset.optimistic = String(unitPrice * qty);
    }
  }

  function updateQtyAjax(cartItem, cartKey, productId, nextQty, prevQty) {
    if (!ajaxUrl || !nonce) {
      location.reload();
      return;
    }

    if (pendingKey) return;
    pendingKey = cartKey;
    cartItem.classList.add("cart-item--loading");

    var body = new URLSearchParams();
    body.set("action", "cc_update_cart_qty");
    body.set("nonce", nonce);
    body.set("cart_item_key", cartKey);
    body.set("product_id", String(productId || ""));
    body.set("quantity", String(nextQty));

    fetch(ajaxUrl, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body.toString(),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data && data.success) {
          location.reload();
          return;
        }
        setQtyDisplay(cartItem, prevQty);
        cartItem.classList.remove("cart-item--loading");
        pendingKey = null;
      })
      .catch(function () {
        setQtyDisplay(cartItem, prevQty);
        cartItem.classList.remove("cart-item--loading");
        pendingKey = null;
      });
  }

  document.addEventListener("click", function (event) {
    var minus = event.target.closest(".qty-minus");
    var plus = event.target.closest(".qty-plus");
    if (!minus && !plus) return;

    event.preventDefault();

    var cartItem = event.target.closest(".cart-item");
    if (!cartItem) return;

    var qtyEl = cartItem.querySelector(".qty-value");
    if (!qtyEl) return;

    var cartKey = getCartKey(cartItem);
    if (!cartKey) return;

    var current = parseInt(qtyEl.textContent, 10) || 1;
    var minQty = Math.max(1, parseInt(cartItem.getAttribute("data-bulk-min"), 10) || 1);
    var step = Math.max(1, parseInt(cartItem.getAttribute("data-bulk-step"), 10) || 1);
    var next = minus ? Math.max(minQty, current - step) : current + step;
    if (next === current) return;

    var maxStock = parseInt(cartItem.getAttribute("data-max-stock"), 10) || 0;
    if (plus && maxStock > 0 && next > maxStock) return;

    setQtyDisplay(cartItem, next);
    syncLinePrice(cartItem, next);
    updateQtyAjax(cartItem, cartKey, getProductId(cartItem), next, current);
  });

  function updateEmptyState() {
    var hasItems = !!form.querySelector(".cart-item");
    if (pageCartEl) pageCartEl.classList.toggle("cart-is-empty", !hasItems);
    if (topPillEl) topPillEl.style.display = hasItems ? "" : "none";
    if (listHeadEl) listHeadEl.hidden = !hasItems;
    if (summaryCardEl) summaryCardEl.hidden = !hasItems;
    if (checkoutBtn) checkoutBtn.hidden = !hasItems;
  }

  updateEmptyState();
})();
