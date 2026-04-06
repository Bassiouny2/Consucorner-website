(function () {
  "use strict";

  var FALLBACK_IMAGE = "assets/images/blog image s.png";

  var PRODUCTS = [
    { id: 1, name: "YAG Iridectomy Lens", price: 1800, brand: "acmed", category: "ophthalmology", procedures: ["glaucoma", "anterior-segment"], inStock: true, date: Date.now() - 1000000 },
    { id: 2, name: "Fundus Lens 78D", price: 2200, brand: "zeiss", category: "ophthalmology", procedures: ["general"], inStock: true, date: Date.now() - 2000000 },
    { id: 3, name: "Goldmann 3-Mirror Lens", price: 3500, brand: "zeiss", category: "ophthalmology", procedures: ["glaucoma", "cornea"], inStock: true, date: Date.now() - 3000000 },
    { id: 4, name: "Capsulorhexis Forceps 0.12mm", price: 1700, brand: "acmed", category: "surgical-instruments", procedures: ["capsulotomies", "cataract"], inStock: true, date: Date.now() - 4000000 },
    { id: 5, name: "Tissue Forceps Angled", price: 1650, brand: "madhu", category: "surgical-instruments", procedures: ["cornea", "anterior-segment"], inStock: true, date: Date.now() - 5000000 },
    { id: 6, name: "Non-Contact Tonometer", price: 28000, brand: "zeiss", category: "diagnostic-instruments", procedures: ["glaucoma"], inStock: true, date: Date.now() - 6000000 },
    { id: 7, name: "Slit Lamp Biomicroscope", price: 45000, brand: "zeiss", category: "diagnostic-instruments", procedures: ["anterior-segment", "cornea"], inStock: false, date: Date.now() - 7000000 },
    { id: 8, name: "Visual Field Analyzer", price: 42000, brand: "storz", category: "diagnostic-instruments", procedures: ["glaucoma"], inStock: true, date: Date.now() - 8000000 },
    { id: 9, name: "Nasal Speculum Adult", price: 900, brand: "acmed", category: "ent", procedures: ["general"], inStock: true, date: Date.now() - 9000000 },
    { id: 10, name: "Rhinoscope Rigid 4mm", price: 8500, brand: "storz", category: "ent", procedures: ["general"], inStock: true, date: Date.now() - 10000000 },
    { id: 11, name: "Colposcope Digital", price: 22000, brand: "zeiss", category: "gynecology", procedures: ["general"], inStock: true, date: Date.now() - 11000000 },
    { id: 12, name: "Uterine Dilator Set", price: 1800, brand: "acmed", category: "gynecology", procedures: ["general"], inStock: true, date: Date.now() - 12000000 },
    { id: 13, name: "Phaco Tip Kelman Angled", price: 1600, brand: "moria", category: "ophthalmology", procedures: ["phaco", "cataract"], inStock: true, date: Date.now() - 13000000 },
    { id: 14, name: "Lacrimal Dilator Probe", price: 720, brand: "katena", category: "ophthalmology", procedures: ["lacrimal", "dacryocystorhinostomy"], inStock: true, date: Date.now() - 14000000 },
    { id: 15, name: "LASIK Microkeratome Blade", price: 2800, brand: "moria", category: "ophthalmology", procedures: ["lasik", "refractive"], inStock: false, date: Date.now() - 15000000 },
    { id: 16, name: "Chalazion Clamp", price: 590, brand: "acmed", category: "ophthalmology", procedures: ["chalazion", "eyelid"], inStock: true, date: Date.now() - 16000000 },
    { id: 17, name: "Retinal Camera", price: 38000, brand: "zeiss", category: "diagnostic-instruments", procedures: ["general"], inStock: true, date: Date.now() - 17000000 },
    { id: 18, name: "OCT Scanner", price: 75000, brand: "zeiss", category: "diagnostic-instruments", procedures: ["glaucoma", "anterior-segment"], inStock: true, date: Date.now() - 18000000 },
    { id: 19, name: "Iris Scissors Curved", price: 1400, brand: "acmed", category: "surgical-instruments", procedures: ["cornea", "anterior-segment"], inStock: true, date: Date.now() - 19000000 },
    { id: 20, name: "Strabismus Hook Fine", price: 1200, brand: "katena", category: "surgical-instruments", procedures: ["pediatric", "general"], inStock: true, date: Date.now() - 20000000 }
  ];

  var BRAND_LABEL = {
    acmed: "ACMED",
    madhu: "Madhu",
    katena: "Katena",
    moria: "Moria",
    zeiss: "Zeiss",
    storz: "Storz"
  };

  var state = {
    category: "",
    procedures: [],
    maxPrice: 75000,
    sort: "default",
    page: 1,
    perPage: 12
  };

  function getFilteredProducts() {
    var list = PRODUCTS.filter(function (p) {
      if (state.category && p.category !== state.category) return false;
      if (p.price > state.maxPrice) return false;
      if (!state.procedures.length) return true;
      return state.procedures.some(function (proc) {
        return p.procedures.indexOf(proc) !== -1;
      });
    });

    list = list.slice();
    if (state.sort === "price_asc") list.sort(function (a, b) { return a.price - b.price; });
    if (state.sort === "price_desc") list.sort(function (a, b) { return b.price - a.price; });
    if (state.sort === "name_asc") list.sort(function (a, b) { return a.name.localeCompare(b.name); });
    if (state.sort === "name_desc") list.sort(function (a, b) { return b.name.localeCompare(a.name); });
    if (state.sort === "newest") list.sort(function (a, b) { return b.date - a.date; });

    return list;
  }

  function productCardHtml(p) {
    var brand = BRAND_LABEL[p.brand] || p.brand || "Brand";
    var outOfStock = p.inStock ? "" : '<span class="ap-out-of-stock-badge">Out of stock</span>';
    var dataAttrs = ' data-product-id="' + p.id + '"' +
      ' data-product-name="' + p.name.replace(/"/g, '&quot;') + '"' +
      ' data-product-price="' + p.price + '"' +
      ' data-product-image="' + FALLBACK_IMAGE + '"';
    var addBtn = p.inStock
      ? '<a href="#" class="btn-add-cart"' + dataAttrs + ' onclick="event.stopPropagation()">Add to cart</a>'
      : '<a href="#" class="btn-add-cart btn-add-cart--disabled"' + dataAttrs + ' onclick="event.stopPropagation()">Out of stock</a>';

    return (
      '<article class="card-shop" data-href="single-product.html">' +
      '<div class="card-shop-img-wrapper">' +
      '<img src="' + FALLBACK_IMAGE + '" alt="' + p.name + '" loading="lazy" />' +
      '<span class="fp-card-company-badge">' + brand + "</span>" +
      outOfStock +
      "</div>" +
      '<div class="card-shop-body">' +
      '<h3 class="product-card-title">' + p.name + "</h3>" +
      '<div class="priceing"><p class="price">' + p.price.toLocaleString("en-EG") + '</p><p class="currency">EGP</p></div>' +
      '<div class="product-card-btn">' +
      '<div class="product-card-btn-left">' +
      addBtn +
      '<a href="#" class="btn-save" onclick="event.stopPropagation()"><img src="assets/images/save-product-icon.svg" alt="Save" /></a>' +
      "</div>" +
      '<a href="#" class="btn-compare" onclick="event.stopPropagation()"><img src="assets/images/Show-icon.svg" alt="Quick View" /></a>' +
      "</div>" +
      "</div>" +
      "</article>"
    );
  }

  function renderPagination(total) {
    var wrap = document.getElementById("apPagination");
    if (!wrap) return;
    var totalPages = Math.ceil(total / state.perPage);
    if (totalPages <= 1) {
      wrap.innerHTML = "";
      return;
    }

    var html = "";
    for (var i = 1; i <= totalPages; i += 1) {
      html += '<button class="ap-page-btn' + (i === state.page ? " ap-page-btn--active" : "") + '" data-page="' + i + '">' + i + "</button>";
    }
    wrap.innerHTML = html;
  }

  function renderGrid() {
    var grid = document.getElementById("apGrid");
    if (!grid) return;

    var filtered = getFilteredProducts();
    var start = (state.page - 1) * state.perPage;
    var pageItems = filtered.slice(start, start + state.perPage);

    var resultEl = document.getElementById("apResultsCount");
    if (resultEl) {
      resultEl.textContent = "Showing " + filtered.length + " result" + (filtered.length === 1 ? "" : "s");
    }

    if (!pageItems.length) {
      grid.innerHTML = '<p class="fp-no-results">No products match your filters. <a href="#" id="apClearFilters">Clear filters</a></p>';
      var clear = document.getElementById("apClearFilters");
      if (clear) {
        clear.addEventListener("click", function (e) {
          e.preventDefault();
          clearFilters();
        });
      }
    } else {
      grid.innerHTML = pageItems.map(productCardHtml).join("");
    }

    renderPagination(filtered.length);
  }

  function clearFilters() {
    state.category = "";
    state.procedures = [];
    state.maxPrice = 75000;
    state.sort = "default";
    state.page = 1;

    var sort = document.getElementById("apSortSelect");
    if (sort) sort.value = "default";

    var slider = document.getElementById("apPriceSlider");
    if (slider) slider.value = "75000";
    var toEl = document.getElementById("apPriceTo");
    if (toEl) toEl.textContent = "75,000";

    document.querySelectorAll(".ap-filter-item").forEach(function (item) {
      item.classList.remove("is-selected");
    });
    document.querySelectorAll(".ap-radio").forEach(function (radio) {
      radio.checked = radio.value === "";
      if (radio.checked) {
        var item = radio.closest(".ap-filter-item");
        if (item) item.classList.add("is-selected");
      }
    });
    document.querySelectorAll(".ap-checkbox").forEach(function (cb) {
      cb.checked = false;
    });

    renderGrid();
  }

  function initSidebarSlider() {
    var slides = document.querySelectorAll(".fp-sidebar-banner .hero-slide");
    if (!slides.length) return;
    var idx = 0;
    setInterval(function () {
      slides[idx].classList.remove("active");
      idx = (idx + 1) % slides.length;
      slides[idx].classList.add("active");
    }, 4500);
  }

  function initSidebarMobileToggle() {
    var toggle = document.getElementById("apFilterToggle");
    var sidebar = document.getElementById("apSidebar");
    var overlay = document.getElementById("apSidebarOverlay");
    if (!toggle || !sidebar || !overlay) return;

    toggle.addEventListener("click", function () {
      sidebar.classList.add("open");
      overlay.classList.add("active");
      document.body.style.overflow = "hidden";
    });
    overlay.addEventListener("click", function () {
      sidebar.classList.remove("open");
      overlay.classList.remove("active");
      document.body.style.overflow = "";
    });
  }

  function bindEvents() {
    document.querySelectorAll(".ap-radio").forEach(function (radio) {
      radio.addEventListener("change", function () {
        state.category = this.value;
        state.page = 1;
        document.querySelectorAll("#apCategoryList .ap-filter-item").forEach(function (item) {
          item.classList.remove("is-selected");
        });
        var active = this.closest(".ap-filter-item");
        if (active) active.classList.add("is-selected");
        renderGrid();
      });
      if (radio.checked) {
        var current = radio.closest(".ap-filter-item");
        if (current) current.classList.add("is-selected");
      }
    });

    document.querySelectorAll(".ap-checkbox").forEach(function (cb) {
      cb.addEventListener("change", function () {
        var val = this.value;
        if (this.checked) {
          if (state.procedures.indexOf(val) === -1) state.procedures.push(val);
        } else {
          state.procedures = state.procedures.filter(function (entry) {
            return entry !== val;
          });
        }
        var item = this.closest(".ap-filter-item");
        if (item) item.classList.toggle("is-selected", this.checked);
        state.page = 1;
        renderGrid();
      });
    });

    var sort = document.getElementById("apSortSelect");
    if (sort) {
      sort.addEventListener("change", function () {
        state.sort = this.value;
        state.page = 1;
        renderGrid();
      });
    }

    var slider = document.getElementById("apPriceSlider");
    var toEl = document.getElementById("apPriceTo");
    if (slider && toEl) {
      slider.addEventListener("input", function () {
        state.maxPrice = Number(this.value);
        toEl.textContent = Number(this.value).toLocaleString("en-EG");
      });
    }

    var applyPrice = document.getElementById("apPriceFilterBtn");
    if (applyPrice) {
      applyPrice.addEventListener("click", function () {
        state.page = 1;
        renderGrid();
      });
    }

    var pagination = document.getElementById("apPagination");
    if (pagination) {
      pagination.addEventListener("click", function (e) {
        var btn = e.target.closest(".ap-page-btn");
        if (!btn) return;
        state.page = Number(btn.dataset.page);
        renderGrid();
        window.scrollTo({ top: 0, behavior: "smooth" });
      });
    }

    var grid = document.getElementById("apGrid");
    if (grid) {
      grid.addEventListener("click", function (e) {
        var add = e.target.closest(".btn-add-cart");
        if (add) {
          e.preventDefault();
          if (add.classList.contains("btn-add-cart--disabled")) return;
          var text = add.textContent;
          add.textContent = "Added!";
          setTimeout(function () {
            add.textContent = text;
          }, 1000);
          return;
        }

        if (e.target.closest(".btn-save") || e.target.closest(".btn-compare")) {
          e.preventDefault();
          return;
        }

        var card = e.target.closest("[data-href]");
        if (card) {
          window.location.href = card.dataset.href;
        }
      });
    }
  }

  function init() {
    bindEvents();
    initSidebarSlider();
    initSidebarMobileToggle();
    renderGrid();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
