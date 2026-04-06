(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /*  Elements                                                            */
  /* ------------------------------------------------------------------ */
  var navItem = document.getElementById('nav-item-shop');
  var shopLink = navItem ? navItem.querySelector('.nav-link') : null;
  var megaMenu = document.getElementById('mega-menu');
  if (!navItem || !megaMenu) return;
  var navItemExplore = document.getElementById('nav-item-explore');
  var exploreLink = navItemExplore ? navItemExplore.querySelector('.nav-link') : null;
  var exploreMenu = document.getElementById('explore-mega-menu');
  var specialtyTrack = document.getElementById('specialty-track');
  var instrumentTrack = document.getElementById('instrument-track');
  var specialtyLabel = document.querySelector('.mega-specialty-label');
  var categoryItems = Array.prototype.slice.call(
    megaMenu.querySelectorAll('.mega-cat-item')
  );

  if (!specialtyTrack || !instrumentTrack || !specialtyLabel || !categoryItems.length) {
    return;
  }

  /* ------------------------------------------------------------------ */
  /*  Show / Hide with a tiny delay so mouse can travel header → panel   */
  /* ------------------------------------------------------------------ */
  var hideTimer;
  var exploreHideTimer;
  var isPinnedOpen = false;
  var isExplorePinnedOpen = false;

  var menuData = {
    surgical: {
      label: 'OPHTHALMOLOGY',
      specialties: [
        { text: 'OPHTHALMOLOGY', color: 'green' },
        { text: 'ENT', color: 'green' },
        { text: 'GYNECOLOGY', color: 'blue' },
        { text: 'GENERAL SURGERY', color: 'blue' },
        { text: 'ORTHOPEDICS', color: 'blue' },
        { text: 'NEUROSURGERY', color: 'blue' },
        { text: 'UROLOGY', color: 'green' },
        { text: 'CARDIAC SURGERY', color: 'blue' }
      ],
      instruments: [
        { text: 'LACRIMAL INSTRUMENTS', color: 'green' },
        { text: 'MARKERS', color: 'green' },
        { text: 'CANNULAS', color: 'blue' },
        { text: 'SPECULUMS & RETRACTORS', color: 'blue' },
        { text: 'FORCEPS', color: 'blue' },
        { text: 'SCISSORS', color: 'blue' },
        { text: 'NEEDLE HOLDERS', color: 'green' },
        { text: 'HOOKS & MANIPULATORS', color: 'blue' }
      ]
    },
    diagnostic: {
      label: 'DIAGNOSTIC',
      specialties: [
        { text: 'RADIOLOGY', color: 'green' },
        { text: 'CARDIOLOGY', color: 'green' },
        { text: 'LAB MEDICINE', color: 'blue' },
        { text: 'PATHOLOGY', color: 'blue' },
        { text: 'PULMONOLOGY', color: 'blue' },
        { text: 'NEUROLOGY', color: 'blue' },
        { text: 'DERMATOLOGY', color: 'green' },
        { text: 'EMERGENCY', color: 'blue' }
      ],
      instruments: [
        { text: 'OTOSCOPES', color: 'green' },
        { text: 'DERMATOSCOPES', color: 'green' },
        { text: 'STETHOSCOPES', color: 'blue' },
        { text: 'ECG ACCESSORIES', color: 'blue' },
        { text: 'PULSE OXIMETERS', color: 'blue' },
        { text: 'BP MONITORS', color: 'blue' },
        { text: 'THERMOMETERS', color: 'green' },
        { text: 'EXAM LIGHTS', color: 'blue' }
      ]
    },
    equipment: {
      label: 'MEDICAL EQUIPMENT',
      specialties: [
        { text: 'ICU', color: 'green' },
        { text: 'ANESTHESIA', color: 'green' },
        { text: 'NEONATAL CARE', color: 'blue' },
        { text: 'DIALYSIS', color: 'blue' },
        { text: 'IMAGING', color: 'blue' },
        { text: 'STERILIZATION', color: 'blue' },
        { text: 'LAB SETUP', color: 'green' },
        { text: 'REHABILITATION', color: 'blue' }
      ],
      instruments: [
        { text: 'PATIENT MONITORS', color: 'green' },
        { text: 'INFUSION PUMPS', color: 'green' },
        { text: 'SUCTION UNITS', color: 'blue' },
        { text: 'DEFIBRILLATORS', color: 'blue' },
        { text: 'AUTOCLAVES', color: 'blue' },
        { text: 'OPERATING LIGHTS', color: 'blue' },
        { text: 'ECG MACHINES', color: 'green' },
        { text: 'VENTILATORS', color: 'blue' }
      ]
    },
    endoscopy: {
      label: 'ENDOSCOPY',
      specialties: [
        { text: 'GASTROENTEROLOGY', color: 'green' },
        { text: 'URO ENDOSCOPY', color: 'green' },
        { text: 'LAPAROSCOPY', color: 'blue' },
        { text: 'ENT ENDOSCOPY', color: 'blue' },
        { text: 'ARTHROSCOPY', color: 'blue' },
        { text: 'BRONCHOSCOPY', color: 'blue' },
        { text: 'HYSTEROSCOPY', color: 'green' },
        { text: 'COLONOSCOPY', color: 'blue' }
      ],
      instruments: [
        { text: 'ENDOSCOPIC SCISSORS', color: 'green' },
        { text: 'GRASPERS', color: 'green' },
        { text: 'TROCARS', color: 'blue' },
        { text: 'LIGHT CABLES', color: 'blue' },
        { text: 'CAMERA HEADS', color: 'blue' },
        { text: 'COAGULATION PROBES', color: 'blue' },
        { text: 'IRRIGATION SETS', color: 'green' },
        { text: 'BIOPSY FORCEPS', color: 'blue' }
      ]
    },
    dental: {
      label: 'DENTAL',
      specialties: [
        { text: 'ORTHODONTICS', color: 'green' },
        { text: 'ENDODONTICS', color: 'green' },
        { text: 'PROSTHODONTICS', color: 'blue' },
        { text: 'PERIODONTICS', color: 'blue' },
        { text: 'ORAL SURGERY', color: 'blue' },
        { text: 'PEDIATRIC DENTAL', color: 'blue' },
        { text: 'DENTAL IMPLANTS', color: 'green' },
        { text: 'ESTHETIC DENTAL', color: 'blue' }
      ],
      instruments: [
        { text: 'DENTAL MIRRORS', color: 'green' },
        { text: 'SCALERS', color: 'green' },
        { text: 'EXCAVATORS', color: 'blue' },
        { text: 'BURS & DRILLS', color: 'blue' },
        { text: 'MATRIX BANDS', color: 'blue' },
        { text: 'ROOT CANAL FILES', color: 'blue' },
        { text: 'DENTAL FORCEPS', color: 'green' },
        { text: 'IMPRESSION TRAYS', color: 'blue' }
      ]
    },
    orthopedic: {
      label: 'ORTHOPEDIC',
      specialties: [
        { text: 'TRAUMA CARE', color: 'green' },
        { text: 'SPINE', color: 'green' },
        { text: 'ARTHROPLASTY', color: 'blue' },
        { text: 'SPORTS MEDICINE', color: 'blue' },
        { text: 'BONE FIXATION', color: 'blue' },
        { text: 'RECONSTRUCTION', color: 'blue' },
        { text: 'PEDIATRIC ORTHO', color: 'green' },
        { text: 'LIMB SALVAGE', color: 'blue' }
      ],
      instruments: [
        { text: 'BONE CUTTERS', color: 'green' },
        { text: 'ORTHO DRILLS', color: 'green' },
        { text: 'REAMERS', color: 'blue' },
        { text: 'K-WIRES', color: 'blue' },
        { text: 'PLATES & SCREWS', color: 'blue' },
        { text: 'EXTERNAL FIXATORS', color: 'blue' },
        { text: 'BONE HOLDING FORCEPS', color: 'green' },
        { text: 'CAST SAWS', color: 'blue' }
      ]
    },
    consumables: {
      label: 'CONSUMABLES',
      specialties: [
        { text: 'OT CONSUMABLES', color: 'green' },
        { text: 'ICU CONSUMABLES', color: 'green' },
        { text: 'WARD SUPPLIES', color: 'blue' },
        { text: 'LAB CONSUMABLES', color: 'blue' },
        { text: 'INFECTION CONTROL', color: 'blue' },
        { text: 'WOUND CARE', color: 'blue' },
        { text: 'ANESTHESIA CONSUMABLES', color: 'green' },
        { text: 'EMERGENCY SUPPLIES', color: 'blue' }
      ],
      instruments: [
        { text: 'SYRINGES', color: 'green' },
        { text: 'GLOVES', color: 'green' },
        { text: 'IV CANNULAS', color: 'blue' },
        { text: 'DRESSINGS', color: 'blue' },
        { text: 'SUTURES', color: 'blue' },
        { text: 'MASKS & CAPS', color: 'blue' },
        { text: 'URINE BAGS', color: 'green' },
        { text: 'BLOOD COLLECTION SETS', color: 'blue' }
      ]
    }
  };

  function showMenu() {
    clearTimeout(hideTimer);
    clearTimeout(exploreHideTimer);
    if (exploreMenu) {
      exploreMenu.classList.remove('active');
      exploreMenu.setAttribute('aria-hidden', 'true');
      isExplorePinnedOpen = false;
    }
    var openingFromClosed = !megaMenu.classList.contains('active');
    megaMenu.classList.add('active');
    megaMenu.setAttribute('aria-hidden', 'false');
    if (openingFromClosed) {
      megaMenu.classList.add('awaiting-selection');
      categoryItems.forEach(function (item) {
        item.classList.remove('active');
      });
    }
  }

  function scheduleHide() {
    if (isPinnedOpen) return;
    hideTimer = setTimeout(function () {
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
    }, 130);
  }

  function showExploreMenu() {
    if (!exploreMenu) return;
    clearTimeout(exploreHideTimer);
    clearTimeout(hideTimer);
    megaMenu.classList.remove('active');
    megaMenu.setAttribute('aria-hidden', 'true');
    isPinnedOpen = false;
    exploreMenu.classList.add('active');
    exploreMenu.setAttribute('aria-hidden', 'false');
  }

  function scheduleExploreHide() {
    if (!exploreMenu || isExplorePinnedOpen) return;
    exploreHideTimer = setTimeout(function () {
      exploreMenu.classList.remove('active');
      exploreMenu.setAttribute('aria-hidden', 'true');
    }, 130);
  }

  navItem.addEventListener('mouseenter', showMenu);
  navItem.addEventListener('mouseleave', function () {
    if (!isPinnedOpen) scheduleHide();
  });
  megaMenu.addEventListener('mouseenter', showMenu);
  megaMenu.addEventListener('mouseleave', scheduleHide);

  if (navItemExplore && exploreMenu) {
    navItemExplore.addEventListener('mouseenter', showExploreMenu);
    navItemExplore.addEventListener('mouseleave', function () {
      if (!isExplorePinnedOpen) scheduleExploreHide();
    });
    exploreMenu.addEventListener('mouseenter', showExploreMenu);
    exploreMenu.addEventListener('mouseleave', scheduleExploreHide);
  }

  if (shopLink) {
    shopLink.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 1024px)').matches) return;
      e.preventDefault();
      clearTimeout(hideTimer);
      if (megaMenu.classList.contains('active') && isPinnedOpen) {
        isPinnedOpen = false;
        megaMenu.classList.remove('active');
        megaMenu.setAttribute('aria-hidden', 'true');
        return;
      }
      isPinnedOpen = true;
      isExplorePinnedOpen = false;
      showMenu();
    });
  }

  if (exploreLink && exploreMenu) {
    exploreLink.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 1024px)').matches) return;
      e.preventDefault();
      clearTimeout(exploreHideTimer);
      if (exploreMenu.classList.contains('active') && isExplorePinnedOpen) {
        isExplorePinnedOpen = false;
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
        return;
      }
      isExplorePinnedOpen = true;
      isPinnedOpen = false;
      showExploreMenu();
    });
  }

  document.addEventListener('click', function (e) {
    if (!isPinnedOpen && !isExplorePinnedOpen) return;
    var clickInsideMenu = megaMenu.contains(e.target);
    var clickInsideExplore = exploreMenu ? exploreMenu.contains(e.target) : false;
    var clickOnNavItem = navItem.contains(e.target);
    var clickOnExploreNavItem = navItemExplore ? navItemExplore.contains(e.target) : false;
    if (!clickInsideMenu && !clickInsideExplore && !clickOnNavItem && !clickOnExploreNavItem) {
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
      if (exploreMenu) {
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
      }
    }
  });

  /* ------------------------------------------------------------------ */
  /*  Slider factory                                                      */
  /* ------------------------------------------------------------------ */
  function createSlider(track, prevId, nextId) {
    var prevBtn = document.getElementById(prevId);
    var nextBtn = document.getElementById(nextId);
    if (!track || !prevBtn || !nextBtn) return null;

    var index = 0;

    /* Width of one card + its trailing gap */
    function step() {
      var card = track.querySelector('.mega-card');
      if (!card) return 205;
      var gapStr = window.getComputedStyle(track).gap || '12px';
      var gap = parseFloat(gapStr) || 12;
      return card.offsetWidth + gap;
    }

    /* How many cards fit inside the viewport at once */
    function visible() {
      var vp = track.parentElement;
      return Math.max(1, Math.floor(vp.offsetWidth / step()));
    }

    function total() {
      return track.querySelectorAll('.mega-card').length;
    }

    function maxIdx() {
      return Math.max(0, total() - visible());
    }

    function go(newIdx) {
      index = Math.max(0, Math.min(newIdx, maxIdx()));
      track.style.transform = 'translateX(-' + index * step() + 'px)';

      /* Update button states */
      var atStart = index === 0;
      var atEnd = index >= maxIdx();

      prevBtn.disabled = atStart;
      prevBtn.setAttribute('aria-disabled', String(atStart));
      nextBtn.disabled = atEnd;
      nextBtn.setAttribute('aria-disabled', String(atEnd));
    }

    prevBtn.addEventListener('click', function () { go(index - 1); });
    nextBtn.addEventListener('click', function () { go(index + 1); });

    /* Reset to first card */
    function reset() { go(0); }

    function refresh() {
      go(index);
    }

    /* Initial render */
    reset();

    return { go: go, reset: reset, refresh: refresh };
  }

  function cardMarkup(item) {
    var cls = item.color === 'green' ? 'mega-card-green' : 'mega-card-blue';
    return '<a href="#" class="mega-card ' + cls + '">' + item.text + '</a>';
  }

  function setCards(track, cards) {
    track.innerHTML = cards.map(cardMarkup).join('');
  }

  function setActiveCategory(categoryId) {
    var data = menuData[categoryId];
    if (!data) return;
    categoryItems.forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-cat') === categoryId);
    });
    megaMenu.classList.remove('awaiting-selection');

    specialtyLabel.textContent = data.label;
    setCards(specialtyTrack, data.specialties);
    setCards(instrumentTrack, data.instruments);

    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  }

  /* ------------------------------------------------------------------ */
  /*  Initialise both sliders                                             */
  /* ------------------------------------------------------------------ */
  var specialtySlider = createSlider(
    specialtyTrack, 'specialty-prev', 'specialty-next'
  );
  var instrumentSlider = createSlider(
    instrumentTrack, 'instrument-prev', 'instrument-next'
  );

  categoryItems.forEach(function (item) {
    var categoryId = item.getAttribute('data-cat');
    if (!categoryId) return;
    item.addEventListener('mouseenter', function () {
      setActiveCategory(categoryId);
    });
    item.addEventListener('click', function () {
      setActiveCategory(categoryId);
    });
  });

  /* Reset sliders to position 0 every time the menu opens */
  navItem.addEventListener('mouseenter', function () {
    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  });

  /* Re-evaluate button states on resize */
  window.addEventListener('resize', function () {
    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  });

  /* ------------------------------------------------------------------ */
  /*  Hide mega menu on Escape key                                        */
  /* ------------------------------------------------------------------ */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
      if (exploreMenu) {
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
      }
    }
  });
})();

/* ------------------------------------------------------------------ */
/*  AJAX cart badge updater (global)                                   */
/* ------------------------------------------------------------------ */
(function () {
  'use strict';

  var STORE_URL = 'https://woocommerce-1495315-6042724.cloudwaysapps.com';
  var WC_API_BASE = STORE_URL.replace(/\/$/, '') + '/wp-json/wc/v3';
  var WC_CONSUMER_KEY = 'ck_9f6935f4460f1bf88750e7f2c5a839409a32dc8d';
  var WC_CONSUMER_SECRET = 'cs_1d04665a6048cad50f6cef1700404dbc74df56ad';
  var COUNT_KEY = 'cc_cart_count';
  var productIdCache = {};

  function toInt(val, fallback) {
    var n = parseInt(val, 10);
    return isNaN(n) ? (fallback || 0) : n;
  }

  function getStoredCount() {
    return toInt(localStorage.getItem(COUNT_KEY), 0);
  }

  function storeCount(count) {
    localStorage.setItem(COUNT_KEY, String(Math.max(0, count)));
  }

  function setBadge(count) {
    var safe = Math.max(0, toInt(count, 0));
    document.querySelectorAll('.cart-badge').forEach(function (badge) {
      if (!badge) return;
      badge.textContent = safe > 0 ? String(safe) : '';
      badge.style.display = safe > 0 ? 'inline-flex' : 'none';
    });
    storeCount(safe);
  }

  function parseCountFromFragments(data) {
    if (!data || !data.fragments) return null;
    var html = Object.keys(data.fragments).map(function (k) { return String(data.fragments[k]); }).join(' ');

    var m = html.match(/cart-badge[^>]*>(\d+)</i);
    if (m && m[1]) return toInt(m[1], null);

    m = html.match(/cart-contents-count[^>]*>(\d+)</i);
    if (m && m[1]) return toInt(m[1], null);

    return null;
  }

  function buildWooUrl(endpoint, query) {
    var url = new URL(WC_API_BASE + endpoint);
    var params = query || {};
    Object.keys(params).forEach(function (key) {
      if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
        url.searchParams.set(key, String(params[key]));
      }
    });
    url.searchParams.set('consumer_key', WC_CONSUMER_KEY);
    url.searchParams.set('consumer_secret', WC_CONSUMER_SECRET);
    return url.toString();
  }

  function resolveProductIdFromButton(button) {
    if (!button) return Promise.resolve(null);

    var idFromData = button.getAttribute('data-product-id');
    if (idFromData) return Promise.resolve(toInt(idFromData, null));

    var href = button.getAttribute('href') || '';
    try {
      var url = new URL(href, window.location.href);
      var qId = url.searchParams.get('add-to-cart');
      if (qId) return Promise.resolve(toInt(qId, null));
    } catch (_e) {
      // no-op
    }

    var card = button.closest('.card-shop, .oow-card');
    var titleEl = card
      ? (card.querySelector('.product-card-title') || card.querySelector('.oow-card-title'))
      : document.querySelector('.sp-title');
    var productName = titleEl ? titleEl.textContent.trim() : '';
    if (!productName) return Promise.resolve(null);

    if (productIdCache[productName]) {
      return Promise.resolve(productIdCache[productName]);
    }

    var lookupUrl = buildWooUrl('/products', {
      search: productName,
      per_page: 10,
      status: 'publish'
    });

    return fetch(lookupUrl)
      .then(function (res) {
        if (!res.ok) throw new Error('Product lookup failed: ' + res.status);
        return res.json();
      })
      .then(function (products) {
        if (!Array.isArray(products) || !products.length) return null;

        var exact = products.find(function (p) {
          return (p.name || '').trim().toLowerCase() === productName.toLowerCase();
        });
        var chosen = exact || products[0];
        var resolved = chosen && chosen.id ? toInt(chosen.id, null) : null;
        if (resolved) productIdCache[productName] = resolved;
        return resolved;
      })
      .catch(function () {
        return null;
      });
  }

  function addToCartAjax(productId, qty) {
    var endpoint = STORE_URL.replace(/\/$/, '') + '/?wc-ajax=add_to_cart';
    var body = new URLSearchParams();
    body.set('product_id', String(productId));
    body.set('quantity', String(qty || 1));

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Add-to-cart failed: ' + res.status);
        return res.json();
      });
  }

  function reflectAddedState(button) {
    if (!button) return;
    var original = button.textContent;
    button.textContent = 'Added!';
    setTimeout(function () {
      button.textContent = original;
    }, 1200);
  }

  function getRequestedQuantity(button) {
    if (!button) return 1;

    // Single product page quantity input
    var qtyInput = document.getElementById('spQtyVal');
    if (button.classList.contains('sp-btn-cart') && qtyInput) {
      return Math.max(1, toInt(qtyInput.value, 1));
    }
    return 1;
  }

  /**
   * Extract product display data from the card element containing the button.
   * Reads data-product-* attributes first, then falls back to DOM extraction.
   * @param {HTMLElement} button
   * @returns {{ name: string, price: number, image: string, permalink: string }}
   */
  function extractItemData(button) {
    /* Prefer explicit data attributes (set by browse-specialty.js etc.) */
    var name      = button.getAttribute('data-product-name')  || '';
    var price     = parseFloat(button.getAttribute('data-product-price') || 0);
    var image     = button.getAttribute('data-product-image') || '';
    var permalink = button.getAttribute('data-product-permalink') || '#';

    /* Fall back to DOM extraction from the parent card */
    var card = button.closest('.card-shop, .oow-card, .fp-card, .ap-card, .sp-product');
    if (card) {
      if (!name) {
        var nameEl = card.querySelector('.product-card-title, .oow-card-title, .fp-card-title, .ap-card-name, .sp-title, h3, h2');
        if (nameEl) name = nameEl.textContent.trim();
      }
      if (!price) {
        var priceEl = card.querySelector('.price, .oow-price, .fp-card-price, .ap-card-price, .sp-price');
        if (priceEl) price = parseFloat(priceEl.textContent.replace(/[^0-9.]/g, '')) || 0;
      }
      if (!image) {
        var imgEl = card.querySelector('.card-shop-img-wrapper img, .oow-card-img-wrapper img, img');
        if (imgEl) image = imgEl.getAttribute('src') || '';
      }
      if (permalink === '#') {
        var viewLink = card.querySelector('a[href*="product"], a[href*="single-product"], a[href*="single_product"]');
        if (viewLink) permalink = viewLink.href || '#';
      }
    }

    /* Last resort: single-product page */
    if (!name) {
      var spTitle = document.querySelector('.sp-title');
      if (spTitle) name = spTitle.textContent.trim();
    }
    if (!image) {
      var spImg = document.querySelector('.sp-main-img, .sp-gallery img');
      if (spImg) image = spImg.src || '';
    }

    return { name: name, price: price, image: image, permalink: permalink };
  }

  function bindAddToCartDelegation() {
    document.addEventListener('click', function (e) {
      var button = e.target.closest('.btn-add-cart, .oow-btn-add-cart, .sp-btn-cart');
      if (!button) return;

      if (button.classList.contains('btn-add-cart--disabled')) {
        e.preventDefault();
        return;
      }

      e.preventDefault();

      var qty      = getRequestedQuantity(button);
      var itemData = extractItemData(button); /* capture before async */

      resolveProductIdFromButton(button)
        .then(function (productId) {
          if (!productId) {
            throw new Error('No product ID could be resolved.');
          }

          /* Feed mini-cart immediately (optimistic, matches WoodMart UX) */
          if (window.CCMiniCart) {
            window.CCMiniCart.addItem({
              id:        productId,
              name:      itemData.name,
              price:     itemData.price,
              qty:       qty,
              image:     itemData.image,
              permalink: itemData.permalink
            });
            window.CCMiniCart.open();
          }

          return addToCartAjax(productId, qty);
        })
        .then(function (data) {
          var parsed  = parseCountFromFragments(data);
          var current = getStoredCount();
          setBadge(parsed !== null ? parsed : current + qty);
          reflectAddedState(button);
          /* Re-sync badge from mini-cart state (single source of truth) */
          if (window.CCMiniCart) window.CCMiniCart.syncBadge();
        })
        .catch(function (err) {
          console.warn('[ConsuCorner cart] ' + err.message);
        });
    });
  }

  function initBadgeFromCache() {
    setBadge(getStoredCount());
  }

  initBadgeFromCache();
  bindAddToCartDelegation();
})();
