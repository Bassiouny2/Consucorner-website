/**
 * ConsuCorner GTM — client-side dataLayer helpers.
 */
(function () {
  "use strict";

  var cfg = window.ccGtmConfig || {};
  var currency = cfg.currency || "EGP";
  var listLimit = parseInt(cfg.listLimit, 10) || 20;

  function dl() {
    window.dataLayer = window.dataLayer || [];
    return window.dataLayer;
  }

  function clearEcommerce() {
    dl().push({ ecommerce: null });
  }

  function pushEvent(payload) {
    if (!payload || typeof payload !== "object") return;
    dl().push(payload);
  }

  function num(v, fallback) {
    var n = parseFloat(v);
    return isNaN(n) ? (fallback || 0) : n;
  }

  function str(v) {
    return v == null ? "" : String(v);
  }

  function readItemFromEl(el) {
    if (!el) return null;
    var card = el.closest(
      ".card-shop, .oow-card, .fp-card, .ap-card, [data-cc-gtm-item]",
    );
    var root = card || el;
    var id =
      root.getAttribute("data-product_id") ||
      root.getAttribute("data-product-id") ||
      el.getAttribute("data-product_id") ||
      el.getAttribute("data-product-id");
    if (!id) return null;
    return {
      item_id: str(id),
      item_sku: str(
        root.getAttribute("data-product_sku") ||
          root.getAttribute("data-product-sku") ||
          "",
      ),
      item_name: str(
        root.getAttribute("data-product_name") ||
          root.getAttribute("data-product-name") ||
          "",
      ),
      price: num(
        root.getAttribute("data-product_price") ||
          root.getAttribute("data-product-price"),
        0,
      ),
      quantity: num(el.getAttribute("data-quantity"), 1) || 1,
      item_category: str(
        root.getAttribute("data-product_category") ||
          root.getAttribute("data-product-category") ||
          "",
      ),
      item_category2: str(root.getAttribute("data-specialty") || ""),
      item_category3: str(
        root.getAttribute("data-procedure") || "",
      ),
      item_brand: str(
        root.getAttribute("data-item_brand") ||
          root.getAttribute("data-vendor") ||
          "",
      ),
    };
  }

  function itemsValue(items) {
    var total = 0;
    (items || []).forEach(function (it) {
      total += num(it.price, 0) * (num(it.quantity, 1) || 1);
    });
    return total;
  }

  function pushAddToCart(item, value) {
    if (!item || !item.item_id) return;
    clearEcommerce();
    pushEvent({
      event: "add_to_cart",
      ecommerce: {
        currency: currency,
        value: num(value, itemsValue([item])),
        items: [item],
      },
    });
  }

  function pushViewItemList(items, listId, listName) {
    if (!items || !items.length) return;
    var limited = items.slice(0, listLimit);
    clearEcommerce();
    pushEvent({
      event: "view_item_list",
      ecommerce: {
        currency: currency,
        item_list_id: str(listId || "product_list"),
        item_list_name: str(listName || "Products"),
        items: limited,
      },
    });
  }

  function pushSelectItem(item, listId, listName) {
    if (!item || !item.item_id) return;
    clearEcommerce();
    pushEvent({
      event: "select_item",
      ecommerce: {
        currency: currency,
        item_list_id: str(listId || "product_list"),
        item_list_name: str(listName || "Products"),
        items: [item],
      },
    });
  }

  function pushSearch(term) {
    var q = str(term).trim();
    if (q.length < 2) return;
    pushEvent({
      event: "search",
      search_term: q,
    });
  }

  function pushFilterProducts(detail) {
    pushEvent({
      event: "filter_products",
      filter_detail: detail || {},
    });
  }

  function scanProductGrid(container) {
    if (!container) return [];
    var seen = {};
    var items = [];
    container.querySelectorAll(".card-shop").forEach(function (card) {
      var btn = card.querySelector("[data-product-id], [data-product_id]");
      var item = readItemFromEl(btn || card);
      if (!item || !item.item_id || seen[item.item_id]) return;
      seen[item.item_id] = true;
      items.push(item);
    });
    return items.slice(0, listLimit);
  }

  function handleCategoryFilterPayload(payload) {
    if (!payload) return;
    var items = payload.gtm_items || [];
    var listId = payload.gtm_list_id || (cfg.listContext && cfg.listContext.item_list_id);
    var listName = payload.gtm_list_name || (cfg.listContext && cfg.listContext.item_list_name);
    if (items.length) {
      pushViewItemList(items, listId, listName);
      return;
    }
    var grid = document.getElementById("fpGrid");
    if (grid) {
      var scanned = scanProductGrid(grid);
      if (scanned.length) pushViewItemList(scanned, listId, listName);
    }
  }

  window.ccGtmHandleCategoryFilterPayload = handleCategoryFilterPayload;

  window.ccGtm = {
    currency: currency,
    clearEcommerce: clearEcommerce,
    push: pushEvent,
    pushAddToCart: pushAddToCart,
    pushViewItemList: pushViewItemList,
    pushSelectItem: pushSelectItem,
    pushSearch: pushSearch,
    pushFilterProducts: pushFilterProducts,
    readItemFromEl: readItemFromEl,
    scanProductGrid: scanProductGrid,
    itemsValue: itemsValue,
  };

  /* WooCommerce jQuery AJAX add to cart (when available). */
  if (window.jQuery) {
    window.jQuery(document.body).on(
      "added_to_cart",
      function (event, fragments, cartHash, $button) {
        var button = $button && $button.length ? $button[0] : null;
        if (!button) return;
        var item = readItemFromEl(button);
        if (item) pushAddToCart(item);
      },
    );
  }

  /* Live search submit (theme forms). */
  document.addEventListener(
    "submit",
    function (e) {
      var form = e.target;
      if (!form || form.getAttribute("role") !== "search") return;
      var input = form.querySelector('input[name="s"]');
      if (!input) return;
      var query = (input.value || "").trim();
      if (query.length >= 3) pushSearch(query);
    },
    true,
  );

  /* Search results page. */
  if (cfg.pageType === "search" && cfg.searchQuery) {
    pushSearch(cfg.searchQuery);
  }

  /* Shop archive: initial server-rendered grid (before AJAX replace). */
  function maybeScanInitialProductGrid() {
    var types = ["shop", "category", "specialty", "procedure"];
    if (types.indexOf(cfg.pageType) === -1) return;
    var grid = document.getElementById("fpGrid");
    if (!grid || grid.getAttribute("data-cc-gtm-list-scanned") === "1") return;
    if (!grid.querySelector(".card-shop")) return;
    grid.setAttribute("data-cc-gtm-list-scanned", "1");
    var items = scanProductGrid(grid);
    if (!items.length) return;
    var listCtx = cfg.listContext || {};
    pushViewItemList(items, listCtx.item_list_id, listCtx.item_list_name);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", maybeScanInitialProductGrid);
  } else {
    maybeScanInitialProductGrid();
  }
})();
