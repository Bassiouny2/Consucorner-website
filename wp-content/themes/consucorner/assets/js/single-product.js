(function () {
  /* ── Often Ordered With Carousel (infinite loop) ── */
  var oowViewport = document.getElementById('oowViewport');
  var oowTrack    = document.getElementById('oowTrack');
  var oowPrev     = document.getElementById('oowPrev');
  var oowNext     = document.getElementById('oowNext');

  if (oowTrack && oowPrev && oowNext && oowViewport) {
    var oowOriginals = Array.prototype.slice.call(oowTrack.querySelectorAll('.card-shop'));
    var oowTotal     = oowOriginals.length;

    var OOW_GAP = 16;

    function oowGetVisible() {
      var w = window.innerWidth;
      if (w <= 480) return 2;
      if (w <= 1024) return 3;
      return 5;
    }

    /* Set card widths explicitly from viewport so % doesn't resolve against track */
    function oowSetWidths() {
      var visible    = oowGetVisible();
      var containerW = oowViewport.offsetWidth;
      var cardW      = (containerW - (visible - 1) * OOW_GAP) / visible;
      Array.prototype.slice.call(oowTrack.querySelectorAll('.card-shop')).forEach(function (c) {
        c.style.flex  = '0 0 ' + cardW + 'px';
        c.style.width = cardW + 'px';
      });
    }

    /* Clone all cards and append for seamless loop */
    oowOriginals.forEach(function (card) {
      oowTrack.appendChild(card.cloneNode(true));
    });

    var oowIndex = 0;

    function oowGetCardWidth() {
      var card = oowTrack.querySelector('.card-shop');
      return card ? card.offsetWidth + OOW_GAP : 0;
    }

    function oowSnap() {
      /* After transition ends, silently jump if in cloned zone */
      if (oowIndex >= oowTotal) {
        oowTrack.style.transition = 'none';
        oowIndex -= oowTotal;
        oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
        oowTrack.offsetHeight; /* force reflow */
        oowTrack.style.transition = '';
      }
      if (oowIndex < 0) {
        oowTrack.style.transition = 'none';
        oowIndex += oowTotal;
        oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
        oowTrack.offsetHeight;
        oowTrack.style.transition = '';
      }
    }

    function oowGoTo(index) {
      oowIndex = index;
      oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
      setTimeout(oowSnap, 420); /* run after CSS transition (400ms) */
    }

    function oowResetTimer() {
      clearInterval(oowTimer);
      oowTimer = setInterval(function () { oowGoTo(oowIndex + 1); }, 3500);
    }

    /* Init widths + resize */
    oowSetWidths();
    window.addEventListener('resize', function () { oowSetWidths(); oowGoTo(oowIndex); });

    /* Auto-play */
    var oowTimer = setInterval(function () { oowGoTo(oowIndex + 1); }, 3500);

    /* Pause on hover */
    oowViewport.addEventListener('mouseenter', function () { clearInterval(oowTimer); });
    oowViewport.addEventListener('mouseleave', oowResetTimer);

    /* Buttons — top nav */
    oowPrev.addEventListener('click', function () { oowGoTo(oowIndex - 1); oowResetTimer(); });
    oowNext.addEventListener('click', function () { oowGoTo(oowIndex + 1); oowResetTimer(); });

    /* Buttons — bottom nav (mobile) */
    var oowPrevBottom = document.getElementById('oowPrevBottom');
    var oowNextBottom = document.getElementById('oowNextBottom');
    if (oowPrevBottom) oowPrevBottom.addEventListener('click', function () { oowGoTo(oowIndex - 1); oowResetTimer(); });
    if (oowNextBottom) oowNextBottom.addEventListener('click', function () { oowGoTo(oowIndex + 1); oowResetTimer(); });

    /* ── Mouse drag ── */
    var oowDragStartX = 0;
    var oowDragging   = false;
    var oowDragMoved  = false;

    oowViewport.addEventListener('mousedown', function (e) {
      oowDragStartX = e.clientX;
      oowDragging   = true;
      oowDragMoved  = false;
      oowTrack.style.transition = 'none';
    });

    window.addEventListener('mousemove', function (e) {
      if (!oowDragging) return;
      if (Math.abs(e.clientX - oowDragStartX) > 5) oowDragMoved = true;
    });

    window.addEventListener('mouseup', function (e) {
      if (!oowDragging) return;
      oowDragging = false;
      oowTrack.style.transition = '';
      if (!oowDragMoved) return;
      var dx = e.clientX - oowDragStartX;
      if (Math.abs(dx) < 40) { oowSnap(); return; }
      oowGoTo(dx < 0 ? oowIndex + 1 : oowIndex - 1);
      oowResetTimer();
    });

    oowViewport.addEventListener('click', function (e) {
      if (oowDragMoved) e.stopPropagation();
    }, true);

    /* ── Touch swipe ── */
    var oowTouchX = 0;

    oowViewport.addEventListener('touchstart', function (e) {
      oowTouchX = e.touches[0].clientX;
    }, { passive: true });

    oowViewport.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - oowTouchX;
      if (Math.abs(dx) < 40) return;
      oowGoTo(dx < 0 ? oowIndex + 1 : oowIndex - 1);
      oowResetTimer();
    }, { passive: true });

    oowGoTo(0);

    /* OOW card click — navigate via data-href */
    oowTrack.addEventListener('click', function (e) {
      var card = e.target.closest('[data-href]');
      if (card) {
        window.location.href = card.dataset.href;
      }
    });
  }

  /* ── Quantity Selector + Buy now (checkout) link ── */
  var qtyVal   = document.getElementById('spQtyVal');
  var qtyMinus = document.getElementById('spQtyMinus');
  var qtyPlus  = document.getElementById('spQtyPlus');
  var buyNow   = document.getElementById('spBuyNow');

  function getWcAjaxBase() {
    return (window.consuSiteData && window.consuSiteData.siteUrl)
      ? window.consuSiteData.siteUrl.replace(/\/$/, '')
      : window.location.origin;
  }

  function getQtyFloor() {
    return qtyVal ? Math.max(1, parseInt(qtyVal.getAttribute('data-bulk-min'), 10) || 1) : 1;
  }

  function getQtyValue() {
    var floor = getQtyFloor();
    var qty = qtyVal ? parseInt(qtyVal.value, 10) : floor;
    return Math.max(floor, qty || floor);
  }

  function getFormAttributeValues(form) {
    var attrs = {};
    if (!form) {
      return attrs;
    }
    form.querySelectorAll('select[name^="attribute_"], .cc-variation-select').forEach(function (sel) {
      if (sel.name && sel.value) {
        attrs[sel.name] = sel.value;
      }
    });
    return attrs;
  }

  function getSpOptimisticItemData(form, qty) {
    var name = '';
    var price = 0;
    var image = '';
    var permalink = window.location.href;
    var titleEl = document.querySelector('.sp-title');
    if (titleEl) {
      name = titleEl.textContent.trim();
    }
    var priceEl = document.querySelector(
      '#spProductPrice .sp-price, #spProductPrice ins .woocommerce-Price-amount, #spProductPrice .woocommerce-Price-amount, #spStickyPrice .sp-price, .sp-price-current .sp-price'
    );
    if (priceEl) {
      price = parseFloat(priceEl.textContent.replace(/[^0-9.]/g, '')) || 0;
    }
    var imgEl = document.querySelector('.sp-main-img, .sp-gallery img');
    if (imgEl) {
      image = imgEl.src || '';
    }
    var vidInput = form ? form.querySelector('input.variation_id') : null;
    var variationId = vidInput ? parseInt(vidInput.value, 10) : 0;
    var parentId = form ? parseInt(form.getAttribute('data-product_id'), 10) : 0;
    return {
      id: variationId || parentId,
      name: name,
      price: price,
      qty: qty,
      image: image,
      permalink: permalink,
    };
  }

  function addVariableToCartFromForm(form, qty) {
    var parentId = parseInt(form.getAttribute('data-product_id'), 10) || 0;
    var vidInput = form.querySelector('input.variation_id');
    var variationId = vidInput ? parseInt(vidInput.value, 10) : 0;

    if (!parentId || !variationId) {
      return Promise.reject(new Error('Missing variation selection'));
    }

    var endpoint = getWcAjaxBase() + '/?wc-ajax=add_to_cart';
    var body = new URLSearchParams();
    // WC AJAX add_to_cart reads product_id only; variation must be passed as product_id.
    body.set('product_id', String(variationId));
    body.set('quantity', String(Math.max(getQtyFloor(), qty || 1)));

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body.toString(),
    }).then(function (res) {
      if (!res.ok) {
        throw new Error('Add-to-cart failed: ' + res.status);
      }
      return res.json();
    }).then(function (data) {
      if (data && data.error) {
        throw new Error('WC rejected add-to-cart');
      }
      return data;
    });
  }

  function handleBuyNowClick(e) {
    if (!buyNow) {
      return;
    }

    if (
      buyNow.classList.contains('is-disabled') ||
      buyNow.getAttribute('aria-disabled') === 'true'
    ) {
      if (e) {
        e.preventDefault();
      }
      return;
    }

    var form = buyNow.closest('.variations_form');
    if (!form) {
      return;
    }

    if (e) {
      e.preventDefault();
    }

    var vidInput = form.querySelector('input.variation_id');
    var variationId = vidInput ? parseInt(vidInput.value, 10) : 0;
    if (!variationId) {
      return;
    }

    var q = getQtyValue();
    var checkout = buyNow.getAttribute('data-checkout') || '';
    if (checkout.indexOf('?') >= 0) {
      checkout = checkout.split('?')[0];
    }

    addVariableToCartFromForm(form, q).then(function () {
      if (checkout) {
        window.location.href = checkout;
      }
    });
  }

  function updateBuyNowHref() {
    if (!buyNow || !qtyVal) return;
    var base = buyNow.getAttribute('data-checkout');
    var pid  = buyNow.getAttribute('data-product-id');
    if (!base || !pid) return;
    var q = getQtyValue();
    var sep = base.indexOf('?') >= 0 ? '&' : '?';
    var form = buyNow.closest('.variations_form');

    if (form) {
      var vidInput = form.querySelector('input.variation_id');
      var variationId = vidInput ? parseInt(vidInput.value, 10) : 0;
      if (!variationId) {
        buyNow.setAttribute('href', '#');
        setButtonsEnabled(false);
        return;
      }
      // Variable products: add via AJAX in handleBuyNowClick — never use GET add-to-cart
      // params (WC re-processes them on checkout and shows "choose product options").
      buyNow.setAttribute('href', base);
      setButtonsEnabled(true);
      return;
    }

    buyNow.setAttribute(
      'href',
      base + sep + 'add-to-cart=' + encodeURIComponent(pid) + '&quantity=' + encodeURIComponent(String(q))
    );
    setButtonsEnabled(true);
  }

  function syncStickyBuyHref() {
    var stickyBuyEl = document.getElementById('spStickyBuyNow');
    if (!stickyBuyEl || !buyNow) {
      return;
    }
    stickyBuyEl.setAttribute('href', buyNow.getAttribute('href') || '#');
    if (buyNow.classList.contains('is-disabled')) {
      stickyBuyEl.classList.add('is-disabled');
      stickyBuyEl.setAttribute('aria-disabled', 'true');
    } else {
      stickyBuyEl.classList.remove('is-disabled');
      stickyBuyEl.setAttribute('aria-disabled', 'false');
    }
  }

  function setButtonsEnabled(enabled) {
    var stickyBuyEl = document.getElementById('spStickyBuyNow');
    if (buyNow) {
      if (enabled) {
        buyNow.classList.remove('is-disabled');
        buyNow.setAttribute('aria-disabled', 'false');
      } else {
        buyNow.classList.add('is-disabled');
        buyNow.setAttribute('aria-disabled', 'true');
      }
    }
    if (stickyBuyEl) {
      if (enabled) {
        stickyBuyEl.classList.remove('is-disabled');
        stickyBuyEl.setAttribute('aria-disabled', 'false');
      } else {
        stickyBuyEl.classList.add('is-disabled');
        stickyBuyEl.setAttribute('aria-disabled', 'true');
      }
    }
  }

  if (qtyVal && qtyMinus && qtyPlus) {
    // Bulk-only products (no Offer Deal) render a data-bulk-min floor so the
    // +/- steppers and typed input can never drop below the smallest tier.
    function getMinQty() {
      return getQtyFloor();
    }

    // Bulk-tier products can configure a bigger +/- click increment (e.g. +5)
    // so reaching a high minimum/tier doesn't take dozens of clicks.
    function getStep() {
      return Math.max(1, parseInt(qtyVal.getAttribute('data-bulk-step'), 10) || 1);
    }

    function getVal() {
      return parseInt(qtyVal.value, 10) || getMinQty();
    }

    function setVal(n) {
      var floor = getMinQty();
      var v = Math.max(floor, n);
      qtyVal.value = v;
      qtyMinus.disabled = v <= floor;
      updateBuyNowHref();
      syncStickyBuyHref();
    }

    qtyMinus.addEventListener('click', function () { setVal(getVal() - getStep()); });
    qtyPlus.addEventListener('click',  function () { setVal(getVal() + getStep()); });

    /* Keyboard: up/down arrows */
    qtyVal.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowUp')   { e.preventDefault(); setVal(getVal() + getStep()); }
      if (e.key === 'ArrowDown') { e.preventDefault(); setVal(getVal() - getStep()); }
    });

    /* Typed number: sanitize on change */
    qtyVal.addEventListener('input', function () {
      var floor = getMinQty();
      var v = parseInt(qtyVal.value, 10);
      if (isNaN(v) || v < floor) qtyVal.value = qtyVal.value === '' ? qtyVal.value : floor;
      qtyMinus.disabled = parseInt(qtyVal.value, 10) <= floor;
      updateBuyNowHref();
      syncStickyBuyHref();
    });

    /* On blur: clear empty field back to the floor */
    qtyVal.addEventListener('blur', function () {
      var floor = getMinQty();
      if (!qtyVal.value || parseInt(qtyVal.value, 10) < floor) setVal(floor);
      else updateBuyNowHref();
    });

    updateBuyNowHref();
    syncStickyBuyHref();
  }

  if (buyNow) {
    buyNow.addEventListener('click', function (e) {
      if (buyNow.closest('.variations_form')) {
        e.preventDefault();
        handleBuyNowClick(e);
      }
    });
  }

  function initVariableAjaxAddToCart() {
    var cfg = window.ccSingleProduct || {};
    if (!cfg.isVariable) {
      return;
    }

    var form = document.querySelector('.variations_form');
    if (!form) {
      return;
    }

    var cartBtn = form.querySelector('.single_add_to_cart_button');

    form.addEventListener('submit', function (e) {
      var vidInput = form.querySelector('input.variation_id');
      var variationId = vidInput ? parseInt(vidInput.value, 10) : 0;

      e.preventDefault();
      e.stopPropagation();

      if (cartBtn && (cartBtn.classList.contains('disabled') || cartBtn.disabled)) {
        return;
      }

      if (!variationId) {
        return;
      }

      var q = qtyVal ? Math.max(1, parseInt(qtyVal.value, 10) || 1) : 1;
      var originalText = cartBtn ? cartBtn.textContent : '';

      if (window.CCMiniCart) {
        window.CCMiniCart.addItem(getSpOptimisticItemData(form, q));
        window.CCMiniCart.open();
      }

      if (cartBtn) {
        cartBtn.disabled = true;
      }

      addVariableToCartFromForm(form, q)
        .then(function (data) {
          if (cartBtn) {
            cartBtn.disabled = false;
            cartBtn.textContent = 'Added!';
            window.setTimeout(function () {
              cartBtn.textContent = originalText;
            }, 1200);
          }

          if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('added_to_cart', [
              data && data.fragments ? data.fragments : {},
              data && data.cart_hash ? data.cart_hash : '',
              jQuery(cartBtn || form),
            ]);
          }
        })
        .catch(function (err) {
          if (cartBtn) {
            cartBtn.disabled = false;
          }
          if (window.console && window.console.warn) {
            window.console.warn('[ConsuCorner] Variable add-to-cart failed:', err && err.message ? err.message : err);
          }
        });
    }, true);
  }

  initVariableAjaxAddToCart();

  /* ── Variable product: price sync + variation buy-now ── */
  (function initVariableProduct() {
    var cfg = window.ccSingleProduct || {};
    if (!cfg.isVariable || typeof jQuery === 'undefined') {
      return;
    }

    var $form = jQuery('.variations_form');
    if (!$form.length) {
      return;
    }

    var $price = jQuery('#spProductPrice');
    var $stickyPrice = jQuery('#spStickyPrice');

    function setPriceSelected(selected) {
      if ($price.length) {
        $price.toggleClass('is-selected', selected);
        $price.toggleClass('sp-pricing--pending', !selected);
      }
      if ($stickyPrice.length) {
        $stickyPrice.toggleClass('is-selected', selected);
        $stickyPrice.toggleClass('sp-pricing--pending', !selected);
      }
    }

    function syncSwatchSelection($formEl) {
      $formEl.find('.sp-swatch').each(function () {
        var $btn = jQuery(this);
        var selectId = $btn.data('select-id');
        var $select = selectId ? jQuery('#' + selectId) : jQuery();
        var isSelected = $select.length && String($select.val()) === String($btn.data('value'));
        $btn.toggleClass('is-selected', isSelected);
        $btn.attr('aria-selected', isSelected ? 'true' : 'false');
      });
    }

    function syncPrices(html) {
      if (html && $price.length) {
        $price.html(html);
      }
      if (html && $stickyPrice.length) {
        $stickyPrice.html(html);
      }
    }

    function applyVariationState(variation) {
      if (variation && variation.price_html) {
        syncPrices(variation.price_html);
      }
      setPriceSelected(!!(variation && variation.variation_id));
      syncSwatchSelection($form);
      if (typeof updateBuyNowHref === 'function') {
        updateBuyNowHref();
      }
      if (typeof syncStickyBuyHref === 'function') {
        syncStickyBuyHref();
      }
    }

    function resetVariationState() {
      if (cfg.parentPriceHtml) {
        syncPrices(cfg.parentPriceHtml);
      }
      setPriceSelected(false);
      setButtonsEnabled(false);
      syncSwatchSelection($form);
      if (typeof updateBuyNowHref === 'function') {
        updateBuyNowHref();
      }
      if (typeof syncStickyBuyHref === 'function') {
        syncStickyBuyHref();
      }
    }

    $form.on('found_variation show_variation', function (event, variation) {
      applyVariationState(variation);
    });

    $form.on('reset_data hide_variation', resetVariationState);

    $form.on('woocommerce_variation_select_change', function () {
      syncSwatchSelection($form);
      if (typeof updateBuyNowHref === 'function') {
        updateBuyNowHref();
      }
      if (typeof syncStickyBuyHref === 'function') {
        syncStickyBuyHref();
      }
    });

    $form.on('change', '.cc-variation-select', function () {
      syncSwatchSelection($form);
    });

    resetVariationState();

    jQuery(document.body).on('added_to_cart', function () {
      if (window.CCMiniCart && window.CCMiniCart.loadFromWC) {
        window.CCMiniCart.loadFromWC(function () {
          if (window.CCMiniCart.open) {
            window.CCMiniCart.open();
          }
        });
      }
    });
  })();

  /* ── Variation swatch buttons / images ── */
  (function initVariationSwatches() {
    if (typeof jQuery === 'undefined') {
      return;
    }

    jQuery(document).on('click', '.sp-swatch', function (e) {
      e.preventDefault();
      var $btn = jQuery(this);
      if ($btn.is(':disabled') || $btn.hasClass('is-disabled')) {
        return;
      }

      var selectId = $btn.data('select-id');
      var value = $btn.data('value');
      var $select = selectId ? jQuery('#' + selectId) : jQuery();

      if (!$select.length) {
        return;
      }

      $select.val(value).trigger('change');
    });
  })();

  /* ── Mobile sticky add-to-cart bar (Woodmart-style) ── */
  (function initSpStickyAtc() {
    var bar = document.getElementById('spStickyAtc');
    var cartForm = document.querySelector('.sp-cart-form, .variations_form');
    var mainQty = document.getElementById('spQtyVal');
    var mainBuy = document.getElementById('spBuyNow');
    var mainCartBtn = document.querySelector('.sp-btn-cart.single_add_to_cart_button');
    var stickyQty = document.getElementById('spStickyQtyVal');
    var stickyMinus = document.getElementById('spStickyQtyMinus');
    var stickyPlus = document.getElementById('spStickyQtyPlus');
    var stickyBuy = document.getElementById('spStickyBuyNow');
    var stickyCart = document.getElementById('spStickyAddCart');
    var mq = window.matchMedia('(max-width: 768px)');
    var stickyObserver = null;

    if (!bar || !cartForm) {
      return;
    }

    function setBarVisible(show) {
      bar.classList.toggle('is-visible', show);
      bar.hidden = !show;
      bar.setAttribute('aria-hidden', show ? 'false' : 'true');
      document.body.classList.toggle('sp-sticky-atc-active', show);
    }

    function getQtyFloor() {
      return mainQty ? Math.max(1, parseInt(mainQty.getAttribute('data-bulk-min'), 10) || 1) : 1;
    }

    function getQtyStep() {
      return mainQty ? Math.max(1, parseInt(mainQty.getAttribute('data-bulk-step'), 10) || 1) : 1;
    }

    function syncQtyFromMain() {
      if (!stickyQty || !mainQty) {
        return;
      }
      stickyQty.value = mainQty.value;
      if (stickyMinus) {
        stickyMinus.disabled = parseInt(mainQty.value, 10) <= getQtyFloor();
      }
    }

    function syncQtyToMain(val) {
      if (!mainQty) {
        return;
      }
      var v = Math.max(getQtyFloor(), val);
      mainQty.value = String(v);
      mainQty.dispatchEvent(new Event('input', { bubbles: true }));
      if (typeof updateBuyNowHref === 'function') {
        updateBuyNowHref();
      }
      if (stickyBuy && mainBuy) {
        if (typeof syncStickyBuyHref === 'function') {
          syncStickyBuyHref();
        } else {
          stickyBuy.setAttribute('href', mainBuy.getAttribute('href') || '#');
        }
      }
    }

    function bindStickyQty() {
      if (!stickyQty || !stickyMinus || !stickyPlus) {
        return;
      }

      function stickyGetVal() {
        return parseInt(stickyQty.value, 10) || getQtyFloor();
      }

      function stickySetVal(n) {
        var floor = getQtyFloor();
        var v = Math.max(floor, n);
        stickyQty.value = String(v);
        stickyMinus.disabled = v <= floor;
        syncQtyToMain(v);
      }

      stickyMinus.addEventListener('click', function () {
        stickySetVal(stickyGetVal() - getQtyStep());
      });
      stickyPlus.addEventListener('click', function () {
        stickySetVal(stickyGetVal() + getQtyStep());
      });
      stickyQty.addEventListener('input', function () {
        var parsed = parseInt(stickyQty.value, 10);
        if (isNaN(parsed) || parsed < 1) {
          stickyQty.value = '1';
        }
        stickyMinus.disabled = parseInt(stickyQty.value, 10) <= 1;
        syncQtyToMain(parseInt(stickyQty.value, 10) || 1);
      });
      stickyQty.addEventListener('blur', function () {
        if (!stickyQty.value || parseInt(stickyQty.value, 10) < 1) {
          stickySetVal(1);
        }
      });

      if (mainQty) {
        mainQty.addEventListener('input', syncQtyFromMain);
        mainQty.addEventListener('change', syncQtyFromMain);
      }
      syncQtyFromMain();
    }

    function bindStickyActions() {
      if (stickyBuy && mainBuy) {
        stickyBuy.addEventListener('click', function (e) {
          if (mainBuy.closest('.variations_form')) {
            e.preventDefault();
            handleBuyNowClick(e);
            return;
          }
          e.preventDefault();
          if (
            mainBuy.classList.contains('is-disabled') ||
            mainBuy.getAttribute('aria-disabled') === 'true' ||
            !mainBuy.href ||
            mainBuy.href === '#' ||
            mainBuy.href.endsWith('#')
          ) {
            return;
          }
          window.location.href = mainBuy.href;
        });
      }
      if (stickyCart && mainCartBtn) {
        stickyCart.addEventListener('click', function () {
          mainCartBtn.click();
        });
      }
    }

    function teardownObserver() {
      if (stickyObserver) {
        stickyObserver.disconnect();
        stickyObserver = null;
      }
      setBarVisible(false);
    }

    function setupObserver() {
      teardownObserver();
      if (!mq.matches || !('IntersectionObserver' in window)) {
        return;
      }

      stickyObserver = new IntersectionObserver(
        function (entries) {
          var entry = entries[0];
          if (!entry) {
            return;
          }
          setBarVisible(!entry.isIntersecting);
        },
        { threshold: 0, rootMargin: '0px 0px 0px 0px' }
      );
      stickyObserver.observe(cartForm);
      syncQtyFromMain();
      if (stickyBuy && mainBuy && typeof syncStickyBuyHref === 'function') {
        syncStickyBuyHref();
      } else if (stickyBuy && mainBuy) {
        stickyBuy.setAttribute('href', mainBuy.getAttribute('href') || '#');
      }
    }

    bindStickyQty();
    bindStickyActions();
    setupObserver();

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', setupObserver);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(setupObserver);
    }
  })();

  /* ── Offer Deal + Bulk pricing: live unit price on qty change ── */
  (function initSpPricingDeals() {
    var container = document.getElementById('spPricingDeals');
    if (!container) {
      return;
    }

    var data;
    try {
      data = JSON.parse(container.getAttribute('data-cc-pricing') || '{}');
    } catch (e) {
      return;
    }

    var mainQtyInput   = document.getElementById('spQtyVal');
    var mainQtyMinus   = document.getElementById('spQtyMinus');
    var mainQtyPlus    = document.getElementById('spQtyPlus');
    var stickyQtyInput = document.getElementById('spStickyQtyVal');
    var stickyQtyMinus = document.getElementById('spStickyQtyMinus');
    var stickyQtyPlus  = document.getElementById('spStickyQtyPlus');
    var priceEls       = document.querySelectorAll('.sp-price, .sp-sticky-atc__amount');
    var tierButtons    = container.querySelectorAll('.sp-bulk-tier');
    var offerDealEl    = document.getElementById('spOfferDeal');

    function getCurrentQty() {
      var raw = mainQtyInput ? mainQtyInput.value : (stickyQtyInput ? stickyQtyInput.value : 1);
      var qty = parseInt(raw, 10);
      return isNaN(qty) || qty < 1 ? 1 : qty;
    }

    function findMatch(qty) {
      // Deal applies once qty reaches the deal threshold ("buy N or more");
      // every unit is then charged at the deal unit price.
      var dealMatch = (data.deal && qty >= data.deal.qty) ? data.deal : null;
      var tierMatch = null;

      if (data.tiers && data.tiers.length) {
        // A tier applies once qty reaches its min ("buy N or more"); among all
        // applicable tiers pick the cheapest per-unit price. This mirrors the
        // server-side cc_find_bulk_tier_for_qty() so the displayed price matches
        // what the cart will charge, even above the highest tier's max.
        for (var i = 0; i < data.tiers.length; i++) {
          var t = data.tiers[i];
          if (qty >= t.min && (!tierMatch || t.price < tierMatch.price)) {
            tierMatch = t;
          }
        }
      }

      if (dealMatch && tierMatch) {
        return dealMatch.unit <= tierMatch.price
          ? { type: 'deal', deal: dealMatch }
          : { type: 'tier', tier: tierMatch };
      }
      if (dealMatch) {
        return { type: 'deal', deal: dealMatch };
      }
      if (tierMatch) {
        return { type: 'tier', tier: tierMatch };
      }
      return null;
    }

    function refresh() {
      var qty   = getCurrentQty();
      var match = findMatch(qty);

      tierButtons.forEach(function (btn) {
        btn.classList.remove('is-active');
      });
      if (offerDealEl) {
        offerDealEl.classList.remove('is-active');
        offerDealEl.setAttribute('aria-pressed', 'false');
      }

      var formatted = data.catalogPriceFormatted;

      if (match && match.type === 'tier') {
        formatted = match.tier.priceFormatted;
        tierButtons.forEach(function (btn) {
          if (parseInt(btn.getAttribute('data-min'), 10) === match.tier.min && parseInt(btn.getAttribute('data-max'), 10) === match.tier.max) {
            btn.classList.add('is-active');
          }
        });
      } else if (match && match.type === 'deal') {
        formatted = match.deal.unitFormatted;
        if (offerDealEl) {
          offerDealEl.classList.add('is-active');
          offerDealEl.setAttribute('aria-pressed', 'true');
        }
      }

      if (formatted) {
        priceEls.forEach(function (el) {
          el.textContent = formatted;
        });
      }
    }

    function setQty(qty) {
      qty = Math.max(1, qty);
      if (mainQtyInput) {
        mainQtyInput.value = String(qty);
        mainQtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        mainQtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (stickyQtyInput) {
        stickyQtyInput.value = String(qty);
        stickyQtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      refresh();
    }

    tierButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setQty(parseInt(btn.getAttribute('data-min'), 10) || 1);
      });
    });

    if (offerDealEl && data.deal) {
      function applyOfferDeal() {
        setQty(parseInt(data.deal.qty, 10) || 1);
      }
      offerDealEl.addEventListener('click', applyOfferDeal);
      offerDealEl.addEventListener('keydown', function (evt) {
        if (evt.key === 'Enter' || evt.key === ' ') {
          evt.preventDefault();
          applyOfferDeal();
        }
      });
    }

    [mainQtyInput, stickyQtyInput].forEach(function (el) {
      if (!el) {
        return;
      }
      ['input', 'change', 'blur'].forEach(function (evt) {
        el.addEventListener(evt, refresh);
      });
    });

    [mainQtyMinus, mainQtyPlus, stickyQtyMinus, stickyQtyPlus].forEach(function (btn) {
      if (btn) {
        btn.addEventListener('click', refresh);
      }
    });

    refresh();
  })();

  /* ── Image Slider ── */
  var viewport = document.getElementById('spSliderViewport');
  var track    = document.getElementById('spSliderTrack');
  var dots     = document.querySelectorAll('.sp-dot');
  var prev     = document.getElementById('spPrev');
  var next     = document.getElementById('spNext');
  var current  = 0;

  if (!viewport || !track) return;

  /* Read total from data-total (rendered by PHP) or fall back to slide count. */
  var total = parseInt(track.getAttribute('data-total'), 10);
  if (isNaN(total) || total <= 0) {
    total = track.querySelectorAll('.sp-slide').length || 1;
  }

  /* Single image: no slider behavior, no autoplay, no swipe nav. */
  if (total <= 1) return;

  if (!prev || !next) return;

  function goTo(index) {
    current = (index + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    dots.forEach(function (d, i) {
      d.classList.toggle('sp-dot--active', i === current);
    });
  }

  prev.addEventListener('click', function () { goTo(current - 1); });
  next.addEventListener('click', function () { goTo(current + 1); });
  dots.forEach(function (d) {
    d.addEventListener('click', function () { goTo(+d.dataset.index); });
  });

  /* ── Auto-play Timer ── */
  var timer = setInterval(function () { goTo(current + 1); }, 3000);

  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function () { goTo(current + 1); }, 3000);
  }

  prev.addEventListener('click', resetTimer);
  next.addEventListener('click', resetTimer);
  dots.forEach(function (d) {
    d.addEventListener('click', resetTimer);
  });

  /* Pause on hover */
  viewport.addEventListener('mouseenter', function () { clearInterval(timer); });
  viewport.addEventListener('mouseleave', function () {
    timer = setInterval(function () { goTo(current + 1); }, 3000);
  });

  /* ── Touch / Swipe ── */
  var touchStartX = 0;
  var touchStartY = 0;
  var isDragging  = false;

  viewport.addEventListener('touchstart', function (e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isDragging  = true;
  }, { passive: true });

  viewport.addEventListener('touchmove', function (e) {
    if (!isDragging) return;
    var dx = e.touches[0].clientX - touchStartX;
    var dy = e.touches[0].clientY - touchStartY;
    if (Math.abs(dy) > Math.abs(dx)) {
      isDragging = false;
    }
  }, { passive: true });

  viewport.addEventListener('touchend', function (e) {
    if (!isDragging) return;
    isDragging = false;
    var dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) < 40) return;
    if (dx < 0) {
      goTo(current + 1); /* swipe left → next */
    } else {
      goTo(current - 1); /* swipe right → prev */
    }
  }, { passive: true });
})();

/* ── Product Testimonials Slider (sp-t-) ── */
(function () {
  var spTWrapper = document.getElementById('spTWrapper');
  var spTTrack   = document.getElementById('spTTrack');
  if (!spTTrack || !spTWrapper) return;

  var TRANS_MS   = 500;
  var AUTO_DELAY = 4000;
  var moving     = false;
  var autoTimer  = null;

  function getStep() {
    var firstCard = spTTrack.querySelector('.sp-t-card');
    if (!firstCard) return 311;
    var cardWidth = firstCard.getBoundingClientRect().width;
    var gap = parseFloat(window.getComputedStyle(spTTrack).columnGap || window.getComputedStyle(spTTrack).gap || 16);
    if (isNaN(gap)) gap = 16;
    return Math.round(cardWidth + gap);
  }

  function setTransition(on) {
    if (on) {
      spTTrack.classList.remove('sp-t-no-transition');
    } else {
      spTTrack.classList.add('sp-t-no-transition');
    }
  }

  function shiftNext() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(true);
    spTTrack.style.transform = 'translateX(-' + step + 'px)';
    setTimeout(function () {
      setTransition(false);
      spTTrack.appendChild(spTTrack.firstElementChild);
      spTTrack.style.transform = 'translateX(0)';
      void spTTrack.offsetWidth;
      moving = false;
    }, TRANS_MS);
  }

  function shiftPrev() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(false);
    spTTrack.insertBefore(spTTrack.lastElementChild, spTTrack.firstElementChild);
    spTTrack.style.transform = 'translateX(-' + step + 'px)';
    void spTTrack.offsetWidth;
    setTransition(true);
    spTTrack.style.transform = 'translateX(0)';
    setTimeout(function () { moving = false; }, TRANS_MS);
  }

  function startAuto() {
    stopAuto();
    autoTimer = setInterval(shiftNext, AUTO_DELAY);
  }

  function stopAuto() {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
  }

  startAuto();

  /* Mouse drag */
  var dragStartX  = 0;
  var isDragging  = false;
  var dragMoved   = false;
  var DRAG_THRESH = 50;

  spTWrapper.addEventListener('mousedown', function (e) {
    stopAuto();
    isDragging = true;
    dragMoved  = false;
    dragStartX = e.clientX;
    spTWrapper.classList.add('sp-t-dragging');
  });

  window.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    if (Math.abs(e.clientX - dragStartX) > 5) dragMoved = true;
  });

  window.addEventListener('mouseup', function (e) {
    if (!isDragging) return;
    isDragging = false;
    spTWrapper.classList.remove('sp-t-dragging');
    if (dragMoved) {
      var diff = e.clientX - dragStartX;
      if (diff < -DRAG_THRESH) shiftNext();
      else if (diff > DRAG_THRESH) shiftPrev();
    }
    startAuto();
  });

  spTWrapper.addEventListener('click', function (e) {
    if (dragMoved) e.preventDefault();
  });

  /* Touch swipe */
  var touchStartX = 0;

  spTWrapper.addEventListener('touchstart', function (e) {
    stopAuto();
    touchStartX = e.touches[0].clientX;
  }, { passive: true });

  spTWrapper.addEventListener('touchend', function (e) {
    var diff = e.changedTouches[0].clientX - touchStartX;
    if (diff < -DRAG_THRESH) shiftNext();
    else if (diff > DRAG_THRESH) shiftPrev();
    startAuto();
  }, { passive: true });

  /* Pause on hover */
  spTWrapper.addEventListener('mouseenter', stopAuto);
  spTWrapper.addEventListener('mouseleave', startAuto);

  /* ── Dynamic page title ── */
  var spTitleEl = document.querySelector('.sp-title');
  if (spTitleEl) {
    document.title = spTitleEl.textContent.trim() + ' - ConsuCorner';
  }

})();
