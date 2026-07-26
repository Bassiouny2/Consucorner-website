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
    var number = Math.round(Number(value || 0));
    if (Number.isNaN(number) || number <= 0) {
      return "";
    }
    return String(number).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function renderProducts(grid, products, html) {
    if (typeof html === "string" && html.trim()) {
      grid.innerHTML = html;
      return;
    }

    if (!Array.isArray(products) || products.length === 0) {
      grid.innerHTML =
        '<p class="fp-no-results" style="grid-column:1/-1;text-align:center;">No products found for this specialty.</p>';
      return;
    }

    var html = products
      .map(function (product) {
        var price = formatPrice(product.price);
        var priceMarkup = price
          ? '<div class="priceing"><p class="price">' +
            escapeHtml(price) +
            '</p><p class="currency">EGP</p></div>'
          : '<div class="priceing"><p class="price">-</p><p class="currency">EGP</p></div>';

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
            escapeHtml(consuBrowseData.placeholderImage) +
            "';\" />",
          "  </a>",
          '  <div class="card-shop-body">',
          '    <h3 class="product-card-title">' +
            escapeHtml(product.name) +
            "</h3>",
          priceMarkup,
          '    <div class="product-card-btn">',
          '      <div class="product-card-btn-left">',
          '        <a href="/?add-to-cart=' +
            encodeURIComponent(product.id) +
            '" class="btn-add-cart" data-product-id="' +
            escapeHtml(String(product.id)) +
            '">Add to cart</a>',
          '        <a href="' +
            escapeHtml(product.link) +
            '" class="btn-save" aria-label="Save">',
          '          <img src="' +
            escapeHtml(consuBrowseData.saveIcon) +
            '" alt="Save" onerror="this.onerror=null;this.src=\'' +
            escapeHtml(consuBrowseData.saveIconFallback) +
            "';\" />",
          "        </a>",
          "      </div>",
          '      <a href="' +
            escapeHtml(product.link) +
            '" class="btn-compare" aria-label="View product">',
          '        <img src="' +
            escapeHtml(consuBrowseData.viewIcon) +
            '" alt="View" onerror="this.onerror=null;this.src=\'' +
            escapeHtml(consuBrowseData.viewIconFallback) +
            "';\" />",
          "      </a>",
          "    </div>",
          "  </div>",
          "</div>",
        ].join("");
      })
      .join("");

    grid.innerHTML = html;
  }

  function loadSpecialtyProducts(grid, specialtySlug) {
    if (!specialtySlug) {
      return;
    }

    var body = new URLSearchParams();
    body.set("action", "consucorner_get_specialty_products");
    body.set("nonce", consuBrowseData.nonce);
    body.set("specialty", specialtySlug);

    grid.style.opacity = "0.5";

    fetch(consuBrowseData.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Request failed.");
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error("Invalid response.");
        }
        renderProducts(grid, payload.data.products || [], payload.data.html || "");
      })
      .catch(function () {
        grid.innerHTML =
          '<p class="fp-no-results" style="grid-column:1/-1;text-align:center;">Unable to load products now.</p>';
      })
      .finally(function () {
        grid.style.opacity = "1";
      });
  }

  function rememberSpecialty(slug) {
    if (!slug) return;
    try {
      localStorage.setItem("consu_last_category_slug", slug);
    } catch (e) {
      // Ignore storage issues.
    }
    document.cookie =
      "consu_last_category_slug=" +
      encodeURIComponent(slug) +
      "; path=/; max-age=" +
      60 * 60 * 24 * 30;
  }

  function updateBrowseButton(button, pill) {
    if (!button || !pill) return;

    var specialtyName =
      pill.dataset.specialtyName || pill.textContent.trim() || "";
    var specialtyUrl = pill.getAttribute("href") || "";
    var defaultLabel = button.dataset.defaultLabel || "Shop All";

    button.href = specialtyUrl || button.href;
    button.textContent = specialtyName
      ? "Shop " + specialtyName
      : defaultLabel;
    button.setAttribute(
      "aria-label",
      specialtyName ? "Open " + specialtyName + " specialty" : defaultLabel
    );
  }

  function initBrowseCategoriesCarousel() {
    var carousel = document.querySelector("[data-browse-carousel]");
    if (!carousel) {
      return;
    }

    var viewport = carousel.querySelector(".browse-categories-viewport");
    var prevBtn = carousel.querySelector(".browse-categories-arrow--prev");
    var nextBtn = carousel.querySelector(".browse-categories-arrow--next");

    if (!viewport || !prevBtn || !nextBtn) {
      return;
    }

    function getScrollStep() {
      var pill = viewport.querySelector(".specialty-pill");
      if (!pill) {
        return 190;
      }
      var styles = window.getComputedStyle(
        viewport.querySelector(".browse-categories-track") || viewport
      );
      var gap = parseFloat(styles.columnGap || styles.gap || 13);
      if (Number.isNaN(gap)) {
        gap = 13;
      }
      return Math.round(pill.getBoundingClientRect().width + gap);
    }

    function updateArrows() {
      var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
      prevBtn.disabled = viewport.scrollLeft <= 2;
      nextBtn.disabled = viewport.scrollLeft >= maxScroll - 2;
    }

    prevBtn.addEventListener("click", function () {
      viewport.scrollBy({
        left: -getScrollStep() * 2,
        behavior: "smooth",
      });
    });

    nextBtn.addEventListener("click", function () {
      viewport.scrollBy({
        left: getScrollStep() * 2,
        behavior: "smooth",
      });
    });

    viewport.addEventListener("scroll", updateArrows, { passive: true });
    window.addEventListener("resize", updateArrows);
    updateArrows();
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (typeof consuBrowseData === "undefined") {
      return;
    }

    initBrowseCategoriesCarousel();

    var pillsContainer = document.getElementById("browse-categories");
    var grid = document.getElementById("browse-grid");
    var browseButton = document.querySelector(
      ".browse-specialties-section .browse-actions .btn-all-specialties"
    );
    if (!pillsContainer || !grid) {
      return;
    }

    pillsContainer.addEventListener("click", function (event) {
      var pill = event.target.closest(".specialty-pill");
      if (!pill) {
        return;
      }

      event.preventDefault();

      pillsContainer
        .querySelectorAll(".specialty-pill")
        .forEach(function (node) {
          node.classList.remove("active");
        });
      pill.classList.add("active");

      var selectedSlug = pill.dataset.specialty || "";
      updateBrowseButton(browseButton, pill);
      rememberSpecialty(selectedSlug);
      loadSpecialtyProducts(grid, selectedSlug);
    });

    var defaultSlug = grid.dataset.defaultSpecialty || "";
    updateBrowseButton(
      browseButton,
      pillsContainer.querySelector(".specialty-pill.active")
    );
    rememberSpecialty(defaultSlug);
    loadSpecialtyProducts(grid, defaultSlug);
  });
})();
