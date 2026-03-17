/**
 * browse-specialty.js
 * =====================================================
 * Handles the "Browse by Specialty" section on the homepage.
 *
 * Features:
 *  - Active pill highlighting based on the current URL query param (?specialty=...)
 *  - Clicking a pill navigates to shop.html?specialty=SLUG so WooCommerce
 *    (or the REST API / GraphQL layer) can filter products by taxonomy.
 *  - Works entirely without external libraries.
 *  - Ready to be wired to a WooCommerce REST API call for dynamic product rendering.
 *
 * WooCommerce integration note:
 *  When a user lands on shop.html?specialty=ophthalmology, the shop page JS
 *  should read window.location.search, extract the `specialty` param, and
 *  pass it as a WooCommerce taxonomy (product_cat) query:
 *    GET /wp-json/wc/v3/products?category=<term_id>&per_page=10
 * =====================================================
 */

(function () {
  'use strict';

  /**
   * Reads a URL query parameter by name.
   * @param {string} name - The query param key.
   * @param {string} [search] - Optional search string (defaults to location.search).
   * @returns {string|null}
   */
  function getQueryParam(name, search) {
    const params = new URLSearchParams(search || window.location.search);
    return params.get(name);
  }

  /**
   * Marks the correct specialty pill as active based on the current URL.
   * Falls back to the first pill if no param is present.
   */
  function syncActivePill() {
    const container = document.getElementById('browse-categories');
    if (!container) return;

    const currentSpecialty = getQueryParam('specialty');
    const pills = container.querySelectorAll('.specialty-pill');

    pills.forEach(function (pill) {
      const slug = pill.dataset.specialty;
      pill.classList.toggle('active', currentSpecialty
        ? slug === currentSpecialty
        : pill === pills[0]          // Default: first pill active
      );
    });
  }

  /**
   * Renders an array of WooCommerce product objects into the grid container.
   */
  function renderProducts(products, grid) {
    if (!products || products.length === 0) return;

    grid.innerHTML = products.map(function (p) {
      var img = (p.images && p.images[0]) ? p.images[0] : {};
      var src = img.src || 'assets/images/demo-product-shop.png';
      var alt = img.alt || p.name;

      var srcset = img.src
        ? (img.src + ' 800w, ' + (img.src.replace(/(\.\w+)$/, '-300x300$1')) + ' 300w')
        : '';

      var price = parseFloat(p.price || 0).toLocaleString('en-EG');

      return [
        '<div class="card-shop">',
        '  <div class="card-shop-img-wrapper">',
        '    <picture>',
        srcset
          ? '<source srcset="' + srcset + '" sizes="(max-width:768px) 100vw, 242px">'
          : '',
        '      <img src="' + src + '" alt="' + alt + '" loading="lazy">',
        '    </picture>',
        '  </div>',
        '  <div class="card-shop-body">',
        '    <h3 class="product-card-title">' + p.name + '</h3>',
        '    <div class="priceing">',
        '      <p class="price">' + price + '</p>',
        '      <p class="currency">EGP</p>',
        '    </div>',
        '    <div class="product-card-btn">',
        '      <div class="product-card-btn-left">',
        '        <a href="?add-to-cart=' + p.id + '" class="btn-add-cart">Add to cart</a>',
        '        <a href="#" class="btn-save" aria-label="Save">',
        '          <img src="assets/images/save-product-icon.svg" alt="Save">',
        '        </a>',
        '      </div>',
        '      <a href="' + (p.permalink || '#') + '" class="btn-compare" aria-label="View product">',
        '        <img src="assets/images/Show-icon.svg" alt="View">',
        '      </a>',
        '    </div>',
        '  </div>',
        '</div>'
      ].join('\n');
    }).join('\n');
  }

  /**
   * Sets up the Browse grid to load products fetched from WooCommerce REST API
   * based on the active specialty category.
   *
   * @param {string} specialtySlug - The WooCommerce category slug.
   */
  function loadSpecialtyProducts(specialtySlug) {
    const grid = document.getElementById('browse-grid');
    if (!grid) return;

    // Show loading state
    grid.style.opacity = '0.5';
    grid.style.pointerEvents = 'none';

    // ── Replace this URL with your actual WordPress domain ──
    const apiBase = (window.CC_CONFIG && window.CC_CONFIG.apiBase)
      ? window.CC_CONFIG.apiBase
      : '/wp-json/wc/v3';

    const cacheKey = 'cc_specialty_' + specialtySlug;
    const cached = sessionStorage.getItem(cacheKey);

    if (cached) {
      renderProducts(JSON.parse(cached), grid);
      grid.style.opacity = '1';
      grid.style.pointerEvents = 'auto';
      return;
    }

    fetch(apiBase + '/products?category=' + encodeURIComponent(specialtySlug) + '&per_page=10')
      .then(function (res) {
        if (!res.ok) throw new Error('WC API error: ' + res.status);
        return res.json();
      })
      .then(function (products) {
        sessionStorage.setItem(cacheKey, JSON.stringify(products));
        renderProducts(products, grid);
      })
      .catch(function (err) {
        console.warn('[ConsuCorner] Could not load specialty products:', err.message);
      })
      .finally(function () {
        grid.style.opacity = '1';
        grid.style.pointerEvents = 'auto';
      });
  }

  // ── Init ──────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('browse-categories');
    if (container) {
      container.addEventListener('click', function (e) {
        const pill = e.target.closest('.specialty-pill');
        if (!pill) return;

        e.preventDefault();
        const slug = pill.dataset.specialty;
        if (!slug) return;

        // Update URL query without reload
        const newUrl = window.location.pathname + '?specialty=' + slug;
        window.history.pushState({ specialty: slug }, '', newUrl);

        syncActivePill();
        loadSpecialtyProducts(slug);
      });
    }

    window.addEventListener('popstate', function () {
      syncActivePill();
      const specialty = getQueryParam('specialty') || 'ophthalmology';
      loadSpecialtyProducts(specialty);
    });

    syncActivePill();

    const specialty = getQueryParam('specialty') || 'ophthalmology';
    if (window.CC_CONFIG && window.CC_CONFIG.apiBase) {
      loadSpecialtyProducts(specialty);
    }
  });

}());
