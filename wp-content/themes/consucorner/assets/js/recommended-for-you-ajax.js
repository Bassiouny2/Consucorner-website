(function () {
  "use strict";

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatPrice(value) {
    var num = Math.round(Number(value || 0));
    if (Number.isNaN(num) || num <= 0) return "0";
    return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function getLastSeenCategory() {
    try {
      return localStorage.getItem("consu_last_category_slug") || "";
    } catch (e) {
      return "";
    }
  }

  function appendInterestProfile(body) {
    var profile =
      window.consuTracker && typeof window.consuTracker.getProfile === "function"
        ? window.consuTracker.getProfile()
        : null;
    if (!profile) return;
    if (profile.categories && profile.categories.length) {
      body.set("pref_categories", profile.categories.join(","));
    }
    if (profile.specialties && profile.specialties.length) {
      body.set("pref_specialties", profile.specialties.join(","));
    }
    if (profile.searches && profile.searches.length) {
      body.set("pref_searches", profile.searches.join(","));
    }
  }

  function renderRecommended(track, products, html) {
    if (typeof html === "string" && html.trim()) {
      track.innerHTML = html;
      return;
    }

    if (!Array.isArray(products) || !products.length) return;

    track.innerHTML = products
      .map(function (product) {
        return [
          '<div class="card-shop" data-href="' +
            escapeHtml(product.link) +
            '" tabindex="0" style="cursor:pointer">',
          '  <a href="' +
            escapeHtml(product.link) +
            '" class="card-shop-img-wrapper" aria-label="' +
            escapeHtml(product.name) +
            '">',
          '    <img src="' +
            escapeHtml(product.image) +
            '" alt="' +
            escapeHtml(product.name) +
            '" onerror="this.onerror=null;this.src=\'' +
            escapeHtml(consuRecommendedData.placeholderImage) +
            "';\" />",
          "  </a>",
          '  <div class="card-shop-body">',
          '    <h3 class="product-card-title">' +
            escapeHtml(product.name) +
            "</h3>",
          '    <div class="priceing">',
          '      <p class="price">' +
            escapeHtml(formatPrice(product.price)) +
            "</p>",
          '      <p class="currency">EGP</p>',
          "    </div>",
          '    <div class="product-card-btn">',
          '      <div class="product-card-btn-left">',
          '        <a href="/?add-to-cart=' +
            encodeURIComponent(product.id) +
            '" class="btn-add-cart">Add to cart</a>',
          '        <a href="' +
            escapeHtml(product.link) +
            '" class="btn-save" aria-label="Save">',
          '          <img src="' +
            escapeHtml(consuRecommendedData.saveIcon) +
            '" alt="Save" onerror="this.onerror=null;this.src=\'' +
            escapeHtml(consuRecommendedData.saveIconFallback) +
            "';\" />",
          "        </a>",
          "      </div>",
          '      <a href="' +
            escapeHtml(product.link) +
            '" class="btn-compare" aria-label="View product">',
          '        <img src="' +
            escapeHtml(consuRecommendedData.viewIcon) +
            '" alt="Compare/View" onerror="this.onerror=null;this.src=\'' +
            escapeHtml(consuRecommendedData.viewIconFallback) +
            "';\" />",
          "      </a>",
          "    </div>",
          "  </div>",
          "</div>",
        ].join("");
      })
      .join("");
  }

  function loadRecommended() {
    if (typeof consuRecommendedData === "undefined") return;

    var track = document.querySelector(".recommended-section .rec-track");
    if (!track) return;

    var body = new URLSearchParams();
    body.set("action", "consucorner_get_recommended_products");
    body.set("nonce", consuRecommendedData.nonce);
    body.set("preferred_category", getLastSeenCategory());
    appendInterestProfile(body);

    fetch(consuRecommendedData.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    })
      .then(function (response) {
        if (!response.ok) throw new Error("Request failed");
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success) return;
        renderRecommended(track, payload.data.products || [], payload.data.html || "");
      })
      .catch(function () {
        // Keep static cards as fallback.
      });
  }

  document.addEventListener("DOMContentLoaded", loadRecommended);
})();
