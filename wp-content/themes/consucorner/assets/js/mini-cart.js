/**
 * mini-cart.js
 * ============================================================
 * Right-side cart drawer for ConsuCorner.
 *
 * All mutations (remove, qty change) are now wired to the WooCommerce
 * server cart via AJAX so the drawer, the badge, and the /cart/ page
 * always stay in sync.
 *
 * Flow:
 *   add-to-cart  → optimistic localStorage update → WC AJAX → loadFromWC()
 *   remove       → lock row → cancel qty update → cc_update_cart_qty(0)
 *   qty change   → WC AJAX (cc_update_cart_qty)        → loadFromWC()
 *   page load    → loadFromWC() seeds localStorage from WC session
 *
 * localStorage schema per item:
 *   { wcKey, id, name, price, qty, image, permalink }
 * ============================================================
 */
(function () {
  "use strict";

  /* ------------------------------------------------------------------ */
  /*  Constants                                                           */
  /* ------------------------------------------------------------------ */
  var STORAGE_KEY = "cc_mini_cart";
  var COUNT_KEY   = "cc_cart_count";
  var pendingQtyRequests = {};
  var removingItems = {};
  var qtyRequestSeq = {};

  var CFG        = (typeof window !== "undefined" && window.consuMiniCartData) || {};
  var FS_CFG     = CFG.freeShipping || {};
  var FREE_SHIPPING_ENABLED =
    !!FS_CFG.enabled && !!FS_CFG.hasThreshold && parseFloat(FS_CFG.minAmount) > 0;
  var FREE_SHIPPING = FREE_SHIPPING_ENABLED ? parseFloat(FS_CFG.minAmount) : 0;

  var CART_URL     = CFG.cartUrl     || "cart.html";
  var CHECKOUT_URL = CFG.checkoutUrl || "checkout.html";
  var SHOP_URL     = CFG.shopUrl     || "shop.html";
  var FALLBACK_IMG = CFG.placeholderImage || "assets/images/consucorner%20icon-logo.jpg";

  /* WC AJAX endpoints */
  var AJAX_URL    = CFG.ajaxUrl || "/wp-admin/admin-ajax.php";
  var CART_NONCE  = CFG.nonce   || "";
  var WC_BASE     = (window.consuSiteData && window.consuSiteData.siteUrl)
    ? window.consuSiteData.siteUrl.replace(/\/$/, "")
    : window.location.origin;

  /* ------------------------------------------------------------------ */
  /*  Storage helpers                                                     */
  /* ------------------------------------------------------------------ */
  function loadItems() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
    catch (_) { return []; }
  }

  function saveItems(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }

  function clearCartCache() {
    localStorage.removeItem(STORAGE_KEY);
    localStorage.setItem(COUNT_KEY, "0");
  }

  function getItemToken(item) {
    if (!item) return "";
    return item.wcKey ? "key:" + item.wcKey : "id:" + String(item.id || "");
  }

  function isRemoving(item) {
    var token = getItemToken(item);
    return !!(token && removingItems[token]);
  }

  function abortPendingQty(token) {
    if (
      token &&
      pendingQtyRequests[token] &&
      typeof pendingQtyRequests[token].abort === "function"
    ) {
      pendingQtyRequests[token].abort();
    }
    if (token) {
      delete pendingQtyRequests[token];
    }
  }

  function lockItemRow(idx) {
    if (!bodyEl) return;
    var row = bodyEl.querySelector('[data-mc-idx="' + idx + '"]');
    if (!row) return;

    row.classList.add("is-processing");
    row.setAttribute("aria-busy", "true");
    row.querySelectorAll("button").forEach(function (button) {
      button.disabled = true;
    });
  }

  function triggerWcFragmentRefresh() {
    if (window.jQuery && window.jQuery(document.body).trigger) {
      window.jQuery(document.body).trigger("wc_fragment_refresh");
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Badge sync                                                          */
  /* ------------------------------------------------------------------ */
  function syncBadge(items) {
    var list  = items || loadItems();
    var count = list.reduce(function (s, i) { return s + (i.qty || 1); }, 0);
    localStorage.setItem(COUNT_KEY, String(count));

    document.querySelectorAll(".cart-badge").forEach(function (badge) {
      badge.textContent = count > 0 ? String(count) : "";
      badge.style.display = count > 0 ? "inline-flex" : "none";
      badge.classList.remove("mc-bump");
      void badge.offsetWidth;
      if (count > 0) badge.classList.add("mc-bump");
    });
  }

  /* ------------------------------------------------------------------ */
  /*  WC AJAX: load cart from server and rebuild localStorage            */
  /* ------------------------------------------------------------------ */
  function loadFromWC(callback) {
    if (!AJAX_URL || !CART_NONCE) {
      if (callback) callback();
      return;
    }

    var body = new URLSearchParams();
    body.set("action", "cc_get_cart_json");
    body.set("nonce", CART_NONCE);

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
        if (data && data.success && data.data && Array.isArray(data.data.items)) {
          var items = data.data.items.map(function (i) {
            return {
              wcKey:     i.wcKey     || "",
              id:        i.id,
              name:      i.name      || "Product",
              price:     parseFloat(i.price) || 0,
              qty:       parseInt(i.qty, 10) || 1,
              maxQty:    parseInt(i.maxQty, 10) || 0,
              bulkMinQty: parseInt(i.bulkMinQty, 10) || 0,
              bulkStep:   parseInt(i.bulkStep, 10) || 1,
              bulkPriceDisplay: i.bulkPriceDisplay || null,
              image:     i.image     || FALLBACK_IMG,
              permalink: i.permalink || "#",
              variation: i.variation || {},
              bundleId:       i.bundleId       || 0,
              bundleInstance: i.bundleInstance || "",
              bundleTitle:    i.bundleTitle    || "",
              bundlePrice:    parseFloat(i.bundlePrice) || 0,
              bundleSize:     parseInt(i.bundleSize, 10) || 0,
            };
          });
          saveItems(items);
          syncBadge(items);
          render();
        }
        if (callback) callback();
      })
      .catch(function () {
        if (callback) callback();
      });
  }

  function isCartPage() {
    return document.body.classList.contains("woocommerce-cart") ||
      /\/cart\/?$/i.test(window.location.pathname);
  }

  function refreshCartPageIfNeeded() {
    if (isCartPage()) {
      window.location.reload();
    }
  }

  /* ------------------------------------------------------------------ */
  /*  DOM helpers                                                         */
  /* ------------------------------------------------------------------ */
  function esc(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  /* ------------------------------------------------------------------ */
  /*  Bundle grouping — items sharing a bundleInstance render as one     */
  /*  framed block (locked qty, single flat price, one remove control). */
  /* ------------------------------------------------------------------ */
  function groupItems(items) {
    var groups = [];
    var indexByInstance = {};

    items.forEach(function (item, idx) {
      var instance = item.bundleInstance || "";
      if (!instance) {
        groups.push({ type: "single", idx: idx });
        return;
      }
      if (!(instance in indexByInstance)) {
        indexByInstance[instance] = groups.length;
        groups.push({ type: "bundle", instance: instance, indices: [] });
      }
      groups[indexByInstance[instance]].indices.push(idx);
    });

    return groups;
  }

  /* ------------------------------------------------------------------ */
  /*  Drawer elements                                                     */
  /* ------------------------------------------------------------------ */
  var overlay, drawer, bodyEl, subtotalEl, titleCountEl, footerEl, shippingBar;

  function createDrawer() {
    overlay = document.createElement("div");
    overlay.className = "mc-overlay";
    overlay.setAttribute("aria-hidden", "true");
    overlay.addEventListener("click", close);

    drawer = document.createElement("div");
    drawer.className = "mc-drawer";
    drawer.setAttribute("role", "dialog");
    drawer.setAttribute("aria-modal", "true");
    drawer.setAttribute("aria-label", "Shopping Cart");

    drawer.innerHTML = [
      '<div class="mc-header">',
      '  <h2 class="mc-title">',
      '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">',
      '      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>',
      '      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
      "    </svg>",
      "    Shopping Cart",
      '    <span class="mc-title-count" id="mcTitleCount"></span>',
      "  </h2>",
      '  <button class="mc-close" id="mcClose" aria-label="Close cart">',
      '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
      '      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      "    </svg>",
      "  </button>",
      "</div>",

      FREE_SHIPPING_ENABLED
        ? '<div class="mc-shipping-bar" id="mcShippingBar"></div>'
        : "",

      '<div class="mc-body" id="mcBody"></div>',

      '<div class="mc-footer" id="mcFooter" style="display:none">',
      '  <div class="mc-subtotal-row">',
      '    <span class="mc-subtotal-label">Subtotal</span>',
      '    <span class="mc-subtotal-val" id="mcSubtotal">0 <span class="mc-subtotal-currency">EGP</span></span>',
      "  </div>",
      '  <div class="mc-footer-btns">',
      '    <a href="' + esc(CHECKOUT_URL) + '" class="mc-btn-checkout">Proceed to Checkout</a>',
      '    <a href="' + esc(CART_URL)     + '" class="mc-btn-view-cart">View Cart</a>',
      "  </div>",
      "</div>",
    ].join("");

    document.body.appendChild(overlay);
    document.body.appendChild(drawer);

    bodyEl       = document.getElementById("mcBody");
    subtotalEl   = document.getElementById("mcSubtotal");
    titleCountEl = document.getElementById("mcTitleCount");
    footerEl     = document.getElementById("mcFooter");
    shippingBar  = document.getElementById("mcShippingBar");

    document.getElementById("mcClose").addEventListener("click", close);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
    });

    bodyEl.addEventListener("click", handleBodyClick);
  }

  /* ------------------------------------------------------------------ */
  /*  Rendering                                                           */
  /* ------------------------------------------------------------------ */
  function renderShipping(subtotal) {
    if (!FREE_SHIPPING_ENABLED || !shippingBar) return;
    var remaining = FREE_SHIPPING - subtotal;
    var pct = Math.min(100, Math.round((subtotal / FREE_SHIPPING) * 100));

    if (subtotal <= 0) {
      shippingBar.innerHTML = "";
      shippingBar.style.display = "none";
      return;
    }

    shippingBar.style.display = "";
    if (remaining > 0) {
      shippingBar.innerHTML =
        '<p class="mc-shipping-text">Add <strong>' +
        remaining.toLocaleString("en-EG") +
        " EGP</strong> more to get <strong>FREE shipping!</strong></p>" +
        '<div class="mc-shipping-track"><div class="mc-shipping-fill" style="width:' +
        pct + '%"></div></div>';
    } else {
      shippingBar.innerHTML =
        '<p class="mc-shipping-text" style="color:#22c55e;font-weight:700;">&#10003; You qualify for <strong>FREE shipping!</strong></p>' +
        '<div class="mc-shipping-track"><div class="mc-shipping-fill" style="width:100%"></div></div>';
    }
  }

  function render() {
    if (!bodyEl) return;
    var items    = loadItems();
    var totalQty = items.reduce(function (s, i) { return s + (i.qty || 1); }, 0);

    if (titleCountEl) {
      titleCountEl.textContent = totalQty > 0 ? String(totalQty) : "";
      titleCountEl.classList.toggle("has-items", totalQty > 0);
    }

    if (!items.length) {
      bodyEl.innerHTML = [
        '<div class="mc-empty">',
        '  <svg class="mc-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">',
        '    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>',
        '    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        "  </svg>",
        '  <p class="mc-empty-title">Your cart is empty</p>',
        '  <p class="mc-empty-sub">Add items to start shopping.</p>',
        '  <a href="' + esc(SHOP_URL) + '" class="mc-empty-link">Browse Products</a>',
        "</div>",
      ].join("");
      if (footerEl) footerEl.style.display = "none";
      if (shippingBar) shippingBar.style.display = "none";
      return;
    }

    var subtotal = items.reduce(function (s, i) {
      return s + (i.price || 0) * (i.qty || 1);
    }, 0);
    if (subtotalEl) {
      subtotalEl.innerHTML =
        subtotal.toLocaleString("en-EG") +
        ' <span class="mc-subtotal-currency">EGP</span>';
    }
    if (footerEl) footerEl.style.display = "";
    renderShipping(subtotal);

    function renderSingleRow(item, idx) {
      var linePrice = ((item.price || 0) * (item.qty || 1)).toLocaleString("en-EG");
      var unitPrice = (item.price || 0).toLocaleString("en-EG");
      var imgSrc    = item.image || FALLBACK_IMG;
      var link      = item.permalink || "#";
      var token     = getItemToken(item);
      var locked    = isRemoving(item);
      var maxQty    = parseInt(item.maxQty, 10) || 0;
      var minQty    = Math.max(1, parseInt(item.bulkMinQty, 10) || 1);
      var atMax     = maxQty > 0 && (item.qty || 1) >= maxQty;
      var atMin     = (item.qty || 1) <= minQty;
      var varHtml   = "";
      var bulkNote  = "";

      if (
        item.bulkPriceDisplay &&
        item.bulkPriceDisplay.enabled &&
        item.bulkPriceDisplay.unitFormatted
      ) {
        unitPrice = String(item.bulkPriceDisplay.unitFormatted);
        bulkNote =
          '<p class="mc-item-bulk-note">' +
          esc(
            item.bulkPriceDisplay.note ||
              "Bulk price: " + item.bulkPriceDisplay.unitFormatted + " EGP / unit",
          ) +
          "</p>";
      }

      if (item.variation && typeof item.variation === "object") {
        Object.keys(item.variation).forEach(function (k) {
          varHtml +=
            '<span class="mc-item-var">' +
            esc(k) +
            ": " +
            esc(item.variation[k]) +
            "</span>";
        });
      }

      return [
        '<li class="mc-item' + (locked ? ' is-processing' : '') + '" data-mc-idx="' + idx + '" data-mc-token="' + esc(token) + '"' + (locked ? ' aria-busy="true"' : '') + '>',
        '  <a href="' + esc(link) + '" class="mc-item-img-wrap" tabindex="-1" aria-label="' + esc(item.name || "") + '">',
        '    <img class="mc-item-img" src="' + esc(imgSrc) + '" alt="' + esc(item.name || "") + '"',
        "      onerror=\"this.src='" + FALLBACK_IMG + "'\">",
        "  </a>",
        '  <div class="mc-item-body">',
        '    <a href="' + esc(link) + '" class="mc-item-name" title="' + esc(item.name || "") + '">' + esc(item.name || "Product") + "</a>",
        varHtml,
        '    <p class="mc-item-line-price">',
        "      " + linePrice + ' <span class="mc-item-currency">EGP</span>',
        '      <span class="mc-item-unit-price"> &times; ' + unitPrice + "</span>",
        "    </p>",
        bulkNote,
        '    <div class="mc-qty">',
        '      <button class="mc-qty-btn" data-mc-action="dec" data-mc-idx="' + idx + '" aria-label="Decrease quantity"' + (locked || atMin ? ' disabled' : '') + '>&#8722;</button>',
        '      <span class="mc-qty-val">' + (item.qty || 1) + "</span>",
        '      <button class="mc-qty-btn" data-mc-action="inc" data-mc-idx="' + idx + '" aria-label="Increase quantity"' + (locked || atMax ? ' disabled' : '') + (atMax ? ' title="Maximum available stock reached"' : '') + '>&#43;</button>',
        "    </div>",
        (atMax ? '    <p class="mc-item-stock-note">Only ' + maxQty + " in stock</p>" : ""),
        "  </div>",
        '  <button class="mc-item-remove" data-mc-remove="' + idx + '" aria-label="Remove ' + esc(item.name || "item") + '"' + (locked ? ' disabled' : '') + '>',
        '    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
        '      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        "    </svg>",
        "  </button>",
        "</li>",
      ].join("");
    }

    function renderBundleFrame(group, allItems) {
      var first     = allItems[group.indices[0]];
      var title     = first.bundleTitle || "Bundle";
      var price     = parseFloat(first.bundlePrice) || 0;
      var size      = parseInt(first.bundleSize, 10) || 0;
      var removeIdx = group.indices[0];
      var locked    = group.indices.some(function (i) { return isRemoving(allItems[i]); });

      var rows = group.indices.map(function (idx) {
        var item   = allItems[idx];
        var imgSrc = item.image || FALLBACK_IMG;
        return [
          '<li class="mc-bundle-item">',
          '  <img class="mc-bundle-item-img" src="' + esc(imgSrc) + '" alt="' + esc(item.name || "") + '"',
          "    onerror=\"this.src='" + FALLBACK_IMG + "'\">",
          '  <span class="mc-bundle-item-name">' + esc(item.name || "Product") + "</span>",
          '  <span class="mc-bundle-item-qty">&times;' + (item.qty || 1) + "</span>",
          "</li>",
        ].join("");
      }).join("");

      return [
        '<li class="mc-item mc-bundle-frame' + (locked ? ' is-processing' : '') + '" data-mc-idx="' + removeIdx + '"' + (locked ? ' aria-busy="true"' : '') + '>',
        '  <div class="mc-bundle-frame-head">',
        '    <span class="mc-bundle-frame-label">Bundle</span>',
        '    <span class="mc-bundle-frame-title" title="' + esc(title) + '">' + esc(title) + "</span>",
        (size > 0 ? '    <span class="mc-bundle-frame-size">' + size + " items</span>" : ""),
        '    <span class="mc-bundle-frame-price">' + price.toLocaleString("en-EG") + ' <span class="mc-item-currency">EGP</span></span>',
        "  </div>",
        '  <ul class="mc-bundle-frame-items">' + rows + "</ul>",
        '  <button class="mc-item-remove mc-bundle-frame-remove" data-mc-remove="' + removeIdx + '" aria-label="Remove ' + esc(title) + '"' + (locked ? ' disabled' : '') + '>',
        '    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
        '      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        "    </svg>",
        "  </button>",
        "</li>",
      ].join("");
    }

    var groups = groupItems(items);

    bodyEl.innerHTML =
      '<ul class="mc-items">' +
      groups.map(function (group) {
        return group.type === "bundle"
          ? renderBundleFrame(group, items)
          : renderSingleRow(items[group.idx], group.idx);
      }).join("") +
      "</ul>";
  }

  /* ------------------------------------------------------------------ */
  /*  Body click delegation                                               */
  /* ------------------------------------------------------------------ */
  function handleBodyClick(e) {
    var removeBtn = e.target.closest("[data-mc-remove]");
    if (removeBtn) {
      e.preventDefault();
      e.stopPropagation();
      var removeIdx = parseInt(removeBtn.getAttribute("data-mc-remove"), 10);
      removeItem(removeIdx);
      return;
    }

    var qtyBtn = e.target.closest("[data-mc-action]");
    if (qtyBtn) {
      e.preventDefault();
      e.stopPropagation();
      var idx    = parseInt(qtyBtn.getAttribute("data-mc-idx"), 10);
      var action = qtyBtn.getAttribute("data-mc-action");
      changeQty(idx, action === "inc" ? 1 : -1);
      return;
    }
  }

  /* ------------------------------------------------------------------ */
  /*  changeQty — optimistic UI + WC AJAX                                */
  /* ------------------------------------------------------------------ */
  function changeQty(idx, delta) {
    var items  = loadItems();
    if (!items[idx]) return;

    var wcKey  = items[idx].wcKey || "";
    var token  = getItemToken(items[idx]);
    if (isRemoving(items[idx])) return;

    var maxQty = parseInt(items[idx].maxQty, 10) || 0;
    var minQty = Math.max(1, parseInt(items[idx].bulkMinQty, 10) || 1);
    var step   = Math.max(1, parseInt(items[idx].bulkStep, 10) || 1);
    var change = (delta < 0 ? -1 : 1) * step;
    var newQty = Math.max(minQty, (items[idx].qty || 1) + change);
    if (newQty === (items[idx].qty || 1)) {
      render();
      return;
    }

    /* Stock guard: never let the line exceed available stock, so the
       customer can't reach checkout with an unfulfillable quantity. */
    if (maxQty > 0 && newQty > maxQty) {
      if ((items[idx].qty || 1) >= maxQty) {
        render();
        return;
      }
      newQty = maxQty;
    }

    var reqId = (qtyRequestSeq[token] || 0) + 1;
    qtyRequestSeq[token] = reqId;

    /* Optimistic local update */
    items[idx].qty = newQty;
    saveItems(items);
    syncBadge(items);
    render();

    /* Sync to WC */
    if ((wcKey || items[idx].id) && AJAX_URL && CART_NONCE) {
      abortPendingQty(token);

      var controller = typeof AbortController !== "undefined"
        ? new AbortController()
        : null;
      if (controller) {
        pendingQtyRequests[token] = controller;
      }

      var body = new URLSearchParams();
      body.set("action",        "cc_update_cart_qty");
      body.set("nonce",         CART_NONCE);
      body.set("cart_item_key", wcKey);
      body.set("product_id",    String(items[idx].id || ""));
      body.set("quantity",      String(newQty));

      fetch(AJAX_URL, {
        method: "POST",
        credentials: "include",
        signal: controller ? controller.signal : undefined,
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: body.toString(),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (removingItems[token]) return;

          /* Race guard: only the newest request for this item may apply its
             response. Older/out-of-order responses are dropped so they can't
             overwrite the cart with stale quantities. */
          if (reqId !== qtyRequestSeq[token]) return;

          if (data && data.success && data.data && Array.isArray(data.data.items)) {
            var fresh = data.data.items.map(function (i) {
              return {
                wcKey:     i.wcKey || "",
                id:        i.id,
                name:      i.name      || "Product",
                price:     parseFloat(i.price) || 0,
                qty:       parseInt(i.qty, 10) || 1,
                maxQty:    parseInt(i.maxQty, 10) || 0,
                bulkMinQty: parseInt(i.bulkMinQty, 10) || 0,
                bulkStep:   parseInt(i.bulkStep, 10) || 1,
                bulkPriceDisplay: i.bulkPriceDisplay || null,
                image:     i.image     || FALLBACK_IMG,
                permalink: i.permalink || "#",
                bundleId:       i.bundleId       || 0,
                bundleInstance: i.bundleInstance || "",
                bundleTitle:    i.bundleTitle    || "",
                bundlePrice:    parseFloat(i.bundlePrice) || 0,
                bundleSize:     parseInt(i.bundleSize, 10) || 0,
              };
            });
            saveItems(fresh);
            syncBadge(fresh);
            render();
          }
        })
        .catch(function (err) {
          if (err && err.name === "AbortError") return;
          /* optimistic state already applied */
        })
        .then(function () {
          if (pendingQtyRequests[token] === controller) {
            delete pendingQtyRequests[token];
          }
        });
    }
  }

  /* ------------------------------------------------------------------ */
  /*  removeItem — locked UI + WC AJAX                                    */
  /* ------------------------------------------------------------------ */
  function removeItem(idx) {
    var items = loadItems();
    var item  = items[idx];
    if (!item) return;

    var wcKey = item.wcKey || "";
    var token = getItemToken(item);
    if (isRemoving(item)) return;

    removingItems[token] = true;
    abortPendingQty(token);
    lockItemRow(idx);

    /* Sync to WC. Use the theme endpoint rather than wc-ajax fragments so
       stale localStorage items can still be matched by product_id fallback. */
    if (AJAX_URL && CART_NONCE) {
      var body = new URLSearchParams();
      body.set("action",        "cc_update_cart_qty");
      body.set("nonce",         CART_NONCE);
      body.set("cart_item_key", wcKey);
      body.set("product_id",    String(item.id || ""));
      body.set("quantity",      "0");

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
          if (data && data.success && data.data && Array.isArray(data.data.items)) {
            var fresh = data.data.items.map(function (i) {
              return {
                wcKey:     i.wcKey || "",
                id:        i.id,
                name:      i.name      || "Product",
                price:     parseFloat(i.price) || 0,
                qty:       parseInt(i.qty, 10) || 1,
                bulkPriceDisplay: i.bulkPriceDisplay || null,
                image:     i.image     || FALLBACK_IMG,
                permalink: i.permalink || "#",
                bundleId:       i.bundleId       || 0,
                bundleInstance: i.bundleInstance || "",
                bundleTitle:    i.bundleTitle    || "",
                bundlePrice:    parseFloat(i.bundlePrice) || 0,
                bundleSize:     parseInt(i.bundleSize, 10) || 0,
              };
            });
            saveItems(fresh);
            syncBadge(fresh);
            render();
            triggerWcFragmentRefresh();
          } else {
            loadFromWC();
          }
          refreshCartPageIfNeeded();
        })
        .catch(function () { loadFromWC(refreshCartPageIfNeeded); })
        .then(function () {
          delete removingItems[token];
        });
    } else {
      items.splice(idx, 1);
      saveItems(items);
      syncBadge(items);
      render();
      delete removingItems[token];
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Open / Close                                                        */
  /* ------------------------------------------------------------------ */
  function open() {
    render();
    overlay.classList.add("is-open");
    drawer.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function close() {
    overlay.classList.remove("is-open");
    drawer.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  /* ------------------------------------------------------------------ */
  /*  Public addItem (optimistic — caller must follow up with loadFromWC) */
  /* ------------------------------------------------------------------ */
  function addItem(item) {
    if (!item || !item.id) return;
    var items    = loadItems();
    var existing = items.find(function (i) {
      return String(i.id) === String(item.id);
    });
    if (existing) {
      existing.qty = (existing.qty || 1) + (item.qty || 1);
    } else {
      items.push({
        wcKey:     "",
        id:        item.id,
        name:      item.name      || "Product",
        price:     parseFloat(item.price) || 0,
        qty:       item.qty       || 1,
        image:     item.image     || FALLBACK_IMG,
        permalink: item.permalink || "#",
      });
    }
    saveItems(items);
    syncBadge(items);
  }

  /* ------------------------------------------------------------------ */
  /*  Init                                                                */
  /* ------------------------------------------------------------------ */
  function init() {
    if (CFG.clearCartCache) {
      clearCartCache();
    }

    createDrawer();

    /* Seed drawer + badge from WC session immediately */
    loadFromWC(function () {
      /* Fallback: if WC sync not available, use cached localStorage */
      if (!loadItems().length) syncBadge();
    });

    document.addEventListener("click", function (e) {
      var cartBtn = e.target.closest(".cart-btn");
      if (cartBtn) {
        e.preventDefault();
        /* Refresh from WC when user opens the drawer */
        loadFromWC(open);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  /* ------------------------------------------------------------------ */
  /*  Public API                                                          */
  /* ------------------------------------------------------------------ */
  window.CCMiniCart = {
    open:           open,
    close:          close,
    addItem:        addItem,
    clearCartCache: clearCartCache,
    render:         render,
    syncBadge:      syncBadge,
    loadFromWC:     loadFromWC,
  };
})();
