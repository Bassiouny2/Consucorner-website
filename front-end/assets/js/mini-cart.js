/**
 * mini-cart.js
 * ============================================================
 * WoodMart-style right-side cart drawer for ConsuCorner.
 *
 * Exposes window.CCMiniCart with:
 *   .open()              – slide the drawer in
 *   .close()             – slide the drawer out
 *   .addItem(itemObj)    – add / increment an item then re-render
 *   .render()            – force a re-render of the drawer contents
 *   .syncBadge()         – recalculate and set all .cart-badge elements
 *
 * Cart state lives in localStorage under "cc_mini_cart" as a JSON array:
 *   [{ id, name, price, qty, image, permalink }, ...]
 *
 * Integration note (WordPress/WooCommerce):
 *   Replace addItem / removeItem / changeQty stubs with WC REST API
 *   or wc-ajax calls once the backend is connected.
 * ============================================================
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /*  Constants                                                           */
  /* ------------------------------------------------------------------ */
  var STORAGE_KEY   = 'cc_mini_cart';
  var COUNT_KEY     = 'cc_cart_count';
  var FALLBACK_IMG  = 'assets/images/demo-product-shop.png';
  var FREE_SHIPPING = 5000; /* EGP threshold — adjust as needed */

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

  /* ------------------------------------------------------------------ */
  /*  Badge sync                                                          */
  /* ------------------------------------------------------------------ */
  function syncBadge(items) {
    var list  = items || loadItems();
    var count = list.reduce(function (s, i) { return s + (i.qty || 1); }, 0);
    localStorage.setItem(COUNT_KEY, String(count));

    document.querySelectorAll('.cart-badge').forEach(function (badge) {
      badge.textContent = count > 0 ? String(count) : '';
      badge.style.display = count > 0 ? 'inline-flex' : 'none';
      /* alignItems / justifyContent come from CSS; no need to force via JS */

      /* bump animation */
      badge.classList.remove('mc-bump');
      void badge.offsetWidth; /* reflow to restart animation */
      if (count > 0) badge.classList.add('mc-bump');
    });
  }

  /* ------------------------------------------------------------------ */
  /*  DOM helpers                                                         */
  /* ------------------------------------------------------------------ */
  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ------------------------------------------------------------------ */
  /*  Drawer elements (assigned during createDrawer)                      */
  /* ------------------------------------------------------------------ */
  var overlay, drawer, bodyEl, subtotalEl, titleCountEl, footerEl, shippingBar;

  function createDrawer() {
    /* Overlay */
    overlay = document.createElement('div');
    overlay.className = 'mc-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.addEventListener('click', close);

    /* Drawer */
    drawer = document.createElement('div');
    drawer.className = 'mc-drawer';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-label', 'Shopping Cart');

    drawer.innerHTML = [
      '<div class="mc-header">',
      '  <h2 class="mc-title">',
      '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">',
      '      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>',
      '      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
      '    </svg>',
      '    Shopping Cart',
      '    <span class="mc-title-count" id="mcTitleCount"></span>',
      '  </h2>',
      '  <button class="mc-close" id="mcClose" aria-label="Close cart">',
      '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
      '      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      '    </svg>',
      '  </button>',
      '</div>',

      '<div class="mc-shipping-bar" id="mcShippingBar"></div>',

      '<div class="mc-body" id="mcBody"></div>',

      '<div class="mc-footer" id="mcFooter" style="display:none">',
      '  <div class="mc-subtotal-row">',
      '    <span class="mc-subtotal-label">Subtotal</span>',
      '    <span class="mc-subtotal-val" id="mcSubtotal">0 <span class="mc-subtotal-currency">EGP</span></span>',
      '  </div>',
      '  <div class="mc-footer-btns">',
      '    <a href="checkout.html" class="mc-btn-checkout">Proceed to Checkout</a>',
      '    <a href="cart.html"     class="mc-btn-view-cart">View Cart</a>',
      '  </div>',
      '</div>'
    ].join('');

    document.body.appendChild(overlay);
    document.body.appendChild(drawer);

    bodyEl        = document.getElementById('mcBody');
    subtotalEl    = document.getElementById('mcSubtotal');
    titleCountEl  = document.getElementById('mcTitleCount');
    footerEl      = document.getElementById('mcFooter');
    shippingBar   = document.getElementById('mcShippingBar');

    document.getElementById('mcClose').addEventListener('click', close);

    /* Keyboard: close on Escape */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });

    /* Click-delegation inside bodyEl (qty ± and remove) */
    bodyEl.addEventListener('click', handleBodyClick);
  }

  /* ------------------------------------------------------------------ */
  /*  Rendering                                                           */
  /* ------------------------------------------------------------------ */
  function renderShipping(subtotal) {
    if (!shippingBar) return;
    var remaining = FREE_SHIPPING - subtotal;
    var pct       = Math.min(100, Math.round((subtotal / FREE_SHIPPING) * 100));

    if (subtotal <= 0) {
      shippingBar.innerHTML = '';
      shippingBar.style.display = 'none';
      return;
    }

    shippingBar.style.display = '';
    if (remaining > 0) {
      shippingBar.innerHTML =
        '<p class="mc-shipping-text">Add <strong>' + remaining.toLocaleString('en-EG') + ' EGP</strong> more to get <strong>FREE shipping!</strong></p>' +
        '<div class="mc-shipping-track"><div class="mc-shipping-fill" style="width:' + pct + '%"></div></div>';
    } else {
      shippingBar.innerHTML =
        '<p class="mc-shipping-text" style="color:#22c55e;font-weight:700;">&#10003; You qualify for <strong>FREE shipping!</strong></p>' +
        '<div class="mc-shipping-track"><div class="mc-shipping-fill" style="width:100%"></div></div>';
    }
  }

  function render() {
    var items    = loadItems();
    var totalQty = items.reduce(function (s, i) { return s + (i.qty || 1); }, 0);

    /* title count badge */
    if (titleCountEl) {
      titleCountEl.textContent = totalQty > 0 ? String(totalQty) : '';
      titleCountEl.classList.toggle('has-items', totalQty > 0);
    }

    /* empty state */
    if (!items.length) {
      bodyEl.innerHTML = [
        '<div class="mc-empty">',
        '  <svg class="mc-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">',
        '    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>',
        '    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        '  </svg>',
        '  <p class="mc-empty-title">Your cart is empty</p>',
        '  <p class="mc-empty-sub">Add items to start shopping.</p>',
        '  <a href="shop.html" class="mc-empty-link">Browse Products</a>',
        '</div>'
      ].join('');
      if (footerEl)    footerEl.style.display    = 'none';
      if (shippingBar) shippingBar.style.display  = 'none';
      return;
    }

    /* subtotal */
    var subtotal = items.reduce(function (s, i) { return s + (i.price || 0) * (i.qty || 1); }, 0);
    if (subtotalEl) {
      subtotalEl.innerHTML = subtotal.toLocaleString('en-EG') + ' <span class="mc-subtotal-currency">EGP</span>';
    }
    if (footerEl) footerEl.style.display = '';

    renderShipping(subtotal);

    /* item rows */
    bodyEl.innerHTML = '<ul class="mc-items">' +
      items.map(function (item, idx) {
        var linePrice = ((item.price || 0) * (item.qty || 1)).toLocaleString('en-EG');
        var unitPrice = (item.price || 0).toLocaleString('en-EG');
        var imgSrc    = item.image || FALLBACK_IMG;
        var link      = item.permalink || '#';

        return [
          '<li class="mc-item" data-mc-idx="' + idx + '">',
          '  <a href="' + esc(link) + '" class="mc-item-img-wrap" tabindex="-1" aria-label="' + esc(item.name || '') + '">',
          '    <img class="mc-item-img" src="' + esc(imgSrc) + '" alt="' + esc(item.name || '') + '"',
          '      onerror="this.src=\'' + FALLBACK_IMG + '\'">',
          '  </a>',
          '  <div class="mc-item-body">',
          '    <a href="' + esc(link) + '" class="mc-item-name" title="' + esc(item.name || '') + '">' + esc(item.name || 'Product') + '</a>',
          '    <p class="mc-item-line-price">',
          '      ' + linePrice + ' <span class="mc-item-currency">EGP</span>',
          '      <span class="mc-item-unit-price"> &times; ' + unitPrice + '</span>',
          '    </p>',
          '    <div class="mc-qty">',
          '      <button class="mc-qty-btn" data-mc-action="dec" data-mc-idx="' + idx + '" aria-label="Decrease quantity">&#8722;</button>',
          '      <span class="mc-qty-val">' + (item.qty || 1) + '</span>',
          '      <button class="mc-qty-btn" data-mc-action="inc" data-mc-idx="' + idx + '" aria-label="Increase quantity">&#43;</button>',
          '    </div>',
          '  </div>',
          '  <button class="mc-item-remove" data-mc-remove="' + idx + '" aria-label="Remove ' + esc(item.name || 'item') + '">',
          '    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
          '      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
          '    </svg>',
          '  </button>',
          '</li>'
        ].join('');
      }).join('') +
      '</ul>';
  }

  /* ------------------------------------------------------------------ */
  /*  Body click delegation (qty ± / remove)                             */
  /* ------------------------------------------------------------------ */
  function handleBodyClick(e) {
    var qtyBtn = e.target.closest('[data-mc-action]');
    if (qtyBtn) {
      var idx    = parseInt(qtyBtn.getAttribute('data-mc-idx'), 10);
      var action = qtyBtn.getAttribute('data-mc-action');
      changeQty(idx, action === 'inc' ? 1 : -1);
      return;
    }
    var removeBtn = e.target.closest('[data-mc-remove]');
    if (removeBtn) {
      var removeIdx = parseInt(removeBtn.getAttribute('data-mc-remove'), 10);
      removeItem(removeIdx);
    }
  }

  function changeQty(idx, delta) {
    var items = loadItems();
    if (!items[idx]) return;
    var newQty = Math.max(1, (items[idx].qty || 1) + delta);
    items[idx].qty = newQty;
    saveItems(items);
    syncBadge(items);
    render();
  }

  function removeItem(idx) {
    var items = loadItems();
    items.splice(idx, 1);
    saveItems(items);
    syncBadge(items);
    render();
  }

  /* ------------------------------------------------------------------ */
  /*  Open / Close                                                        */
  /* ------------------------------------------------------------------ */
  function open() {
    render();
    overlay.classList.add('is-open');
    drawer.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    overlay.classList.remove('is-open');
    drawer.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  /* ------------------------------------------------------------------ */
  /*  Public addItem                                                      */
  /* ------------------------------------------------------------------ */
  function addItem(item) {
    if (!item || !item.id) return;
    var items    = loadItems();
    var existing = items.find(function (i) { return String(i.id) === String(item.id); });
    if (existing) {
      existing.qty = (existing.qty || 1) + (item.qty || 1);
    } else {
      items.push({
        id:        item.id,
        name:      item.name      || 'Product',
        price:     parseFloat(item.price) || 0,
        qty:       item.qty       || 1,
        image:     item.image     || FALLBACK_IMG,
        permalink: item.permalink || '#'
      });
    }
    saveItems(items);
    syncBadge(items);
  }

  /* ------------------------------------------------------------------ */
  /*  Init                                                                */
  /* ------------------------------------------------------------------ */
  function init() {
    createDrawer();

    /* Restore badge from cache on page load */
    syncBadge();

    /* Cart icon in header → open mini-cart */
    document.addEventListener('click', function (e) {
      var cartBtn = e.target.closest('.cart-btn');
      if (cartBtn) {
        e.preventDefault();
        open();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ------------------------------------------------------------------ */
  /*  Public API                                                          */
  /* ------------------------------------------------------------------ */
  window.CCMiniCart = {
    open:      open,
    close:     close,
    addItem:   addItem,
    render:    render,
    syncBadge: syncBadge
  };

})();
