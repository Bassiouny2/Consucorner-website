(function () {
  "use strict";

  if (typeof window.consuCategoryFilter === "undefined") {
    return;
  }

  var data = window.consuCategoryFilter;

  function normalizeFilterMap(map) {
    var out = {};
    if (!map || typeof map !== "object") return out;

    Object.keys(map).forEach(function (tax) {
      var ids = [];
      (Array.isArray(map[tax]) ? map[tax] : [map[tax]]).forEach(function (id) {
        var parsed = parseInt(id, 10);
        if (parsed && ids.indexOf(parsed) === -1) ids.push(parsed);
      });
      if (ids.length) out[tax] = ids;
    });

    return out;
  }

  function cloneFilterMap(map) {
    var out = {};
    Object.keys(map || {}).forEach(function (tax) {
      out[tax] = map[tax].slice();
    });
    return out;
  }

  var lockedFilters = normalizeFilterMap(data.lockedFilters);
  var urlFilters = normalizeFilterMap(data.urlFilters);
  var initialFilters = cloneFilterMap(lockedFilters);
  var queryParams = new URLSearchParams(window.location.search || "");
  var initialUrlSearch = window.location.search || "";
  var hasUrlMinPrice = queryParams.has("min_price");
  var hasUrlMaxPrice = queryParams.has("max_price");
  var urlMinPrice = hasUrlMinPrice
    ? parseFloat(queryParams.get("min_price")) || 0
    : 0;
  var urlMaxPrice = hasUrlMaxPrice
    ? parseFloat(queryParams.get("max_price")) || 0
    : 0;

  var state = {
    subcategoryId: 0,
    filters: initialFilters,
    minPrice: urlMinPrice,
    maxPrice: urlMaxPrice,
    priceActive: hasUrlMinPrice || hasUrlMaxPrice,
    sort: "default",
    search: data.searchTerm || "",
    page: 1,
    perPage: data.perPage || 12,
    totalPages: 1,
    currentLoadedPage: 1,
    pageLoading: false,
  };

  var grid = document.getElementById("fpGrid");
  var resultsCount = document.getElementById("fpResultsCount");
  var paginationNav = document.getElementById("fpPagination");
  var pricePreviewRequestId = 0;
  var sidebarHome = null;
  var activeFilterTrigger = null;

  function isDesktopFilterViewport() {
    return window.matchMedia("(min-width: 769px)").matches;
  }

  function rememberSidebarHome(sidebar) {
    if (sidebarHome || !sidebar || !sidebar.parentNode) return;
    sidebarHome = {
      parent: sidebar.parentNode,
      next: sidebar.nextSibling,
    };
  }

  function getSidebarRestoreParent() {
    var home = document.querySelector("[data-cc-sidebar-home]");
    if (home) return home;
    return sidebarHome ? sidebarHome.parent : null;
  }

  function clearDesktopFilterPanelStyles(sidebar) {
    if (!sidebar) return;
    sidebar.style.top = "";
    sidebar.style.left = "";
    sidebar.style.right = "";
    sidebar.style.position = "";
    sidebar.style.width = "";
  }

  function getFilterDropdownHost(bar) {
    if (!bar) return null;
    var host = bar.querySelector(".cc-filter-dropdown-host");
    if (host) return host;

    host = document.createElement("div");
    host.className = "cc-filter-dropdown-host";
    host.setAttribute("aria-hidden", "true");
    bar.appendChild(host);
    return host;
  }

  function anchorDesktopFilterPanel(sidebar, trigger, bar) {
    if (!sidebar || !trigger || !bar) return;

    var barRect = bar.getBoundingClientRect();
    var triggerRect = trigger.getBoundingClientRect();
    var viewportWidth =
      window.innerWidth || document.documentElement.clientWidth;
    var panelWidth = Math.min(560, viewportWidth - 32);
    var leftInViewport = Math.max(
      16,
      Math.min(triggerRect.left, viewportWidth - panelWidth - 16),
    );
    var left = leftInViewport - barRect.left;

    sidebar.style.position = "relative";
    sidebar.style.top = "";
    sidebar.style.left = left + "px";
    sidebar.style.right = "auto";
    sidebar.style.width = panelWidth + "px";
  }

  function unmountDesktopFilterPanel(sidebar) {
    if (!sidebar) return;

    var mountedBar = sidebar.closest(".fp-filter-bar");
    if (mountedBar) {
      mountedBar.classList.remove("has-open-filter-panel");
      var host = mountedBar.querySelector(".cc-filter-dropdown-host");
      if (host) host.setAttribute("aria-hidden", "true");
    }

    var restoreParent = getSidebarRestoreParent();
    if (restoreParent && sidebar.parentNode !== restoreParent) {
      if (sidebarHome && sidebarHome.parent) {
        sidebarHome.parent.insertBefore(sidebar, sidebarHome.next);
      } else {
        restoreParent.insertBefore(sidebar, restoreParent.firstChild);
      }
    }

    clearDesktopFilterPanelStyles(sidebar);
    activeFilterTrigger = null;
  }

  function mountDesktopFilterPanel(sidebar, trigger) {
    if (!sidebar || !trigger || !isDesktopFilterViewport()) return;

    var bar = trigger.closest(".fp-filter-bar");
    var host = getFilterDropdownHost(bar);
    if (!bar || !host) return;

    rememberSidebarHome(sidebar);
    host.appendChild(sidebar);
    host.setAttribute("aria-hidden", "false");
    bar.classList.add("has-open-filter-panel");
    activeFilterTrigger = trigger;
    anchorDesktopFilterPanel(sidebar, trigger, bar);
  }

  function setupDesktopFilterPanelResize() {
    window.addEventListener("resize", function () {
      var sidebar = document.getElementById("fpSidebar");

      if (!isDesktopFilterViewport()) {
        if (sidebar && sidebar.classList.contains("open")) {
          unmountDesktopFilterPanel(sidebar);
          clearDesktopFilterPanelStyles(sidebar);
        }
        return;
      }

      if (!sidebar || !sidebar.classList.contains("open") || !activeFilterTrigger) {
        return;
      }

      var bar = activeFilterTrigger.closest(".fp-filter-bar");
      if (!bar || !sidebar.closest(".fp-filter-bar")) return;

      anchorDesktopFilterPanel(sidebar, activeFilterTrigger, bar);
    });
  }

  function isLockedFilter(tax, termId) {
    termId = parseInt(termId, 10);
    return !!(
      tax &&
      termId &&
      lockedFilters[tax] &&
      lockedFilters[tax].indexOf(termId) !== -1
    );
  }

  function getStickyTopPx(el) {
    var top = window.getComputedStyle(el).top;
    var value = parseFloat(top);
    return isNaN(value) ? 0 : value;
  }

  function setupStickyFilterState() {
    var bar = document.querySelector(".fp-filter-bar.ap-filter-bar");
    if (!bar) return;

    var lastScrollY = window.pageYOffset || 0;
    var scrollTopReveal = 48;
    var scrollDeltaMin = 8;

    function update() {
      var stickyTop = getStickyTopPx(bar);
      var rect = bar.getBoundingClientRect();
      bar.classList.toggle("is-sticky", rect.top <= stickyTop + 1);

      var scrollY = window.pageYOffset || 0;
      var delta = scrollY - lastScrollY;
      var panelOpen = bar.classList.contains("has-open-filter-panel");

      if (panelOpen) {
        bar.classList.remove("is-scroll-hidden");
      } else if (scrollY <= scrollTopReveal) {
        bar.classList.remove("is-scroll-hidden");
      } else if (delta > scrollDeltaMin) {
        bar.classList.add("is-scroll-hidden");
      } else if (delta < -scrollDeltaMin) {
        bar.classList.remove("is-scroll-hidden");
      }

      lastScrollY = scrollY;
    }

    update();
    window.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
  }

  /* ──────────────────────────────────────────────────────────────────
     Mobile view toggle: switches the product grid between
     list view (default) and grid view. Persists the user's
     preference in localStorage so it survives reloads / navigation.
     The toggle UI itself is hidden on desktop via CSS.
     ────────────────────────────────────────────────────────────────── */
  function setupViewToggle() {
    var grid = document.getElementById("fpGrid");
    var toggles = document.querySelectorAll(".fp-view-toggle");
    if (!grid || !toggles.length) return;

    var STORAGE_KEY = "cc_archive_view_mode";
    var stored;
    try {
      stored = window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      stored = null;
    }
    var initial = stored === "grid" ? "grid" : "list";

    function applyView(mode) {
      var isGrid = mode === "grid";
      grid.classList.toggle("is-grid-view", isGrid);
      toggles.forEach(function (toggleEl) {
        toggleEl
          .querySelectorAll(".fp-view-toggle-btn")
          .forEach(function (btn) {
            var active = btn.getAttribute("data-cc-view") === mode;
            btn.classList.toggle("is-active", active);
            btn.setAttribute("aria-pressed", active ? "true" : "false");
          });
      });
      try {
        window.localStorage.setItem(STORAGE_KEY, mode);
      } catch (e) {
        /* storage may be unavailable */
      }
    }

    applyView(initial);

    toggles.forEach(function (toggleEl) {
      toggleEl.addEventListener("click", function (e) {
        var btn = e.target.closest(".fp-view-toggle-btn");
        if (!btn) return;
        e.preventDefault();
        var mode =
          btn.getAttribute("data-cc-view") === "grid" ? "grid" : "list";
        applyView(mode);
      });
    });
  }

  function getGridTotalPages() {
    if (!grid) return 1;
    var n = parseInt(grid.getAttribute("data-total-pages"), 10);
    return !isNaN(n) && n > 0 ? n : 1;
  }

  function getGridCurrentLoadedPage() {
    if (!grid) return 1;
    var n = parseInt(grid.getAttribute("data-current-loaded-page"), 10);
    return !isNaN(n) && n > 0 ? n : 1;
  }

  function setGridMeta(totalPages, loadedPage) {
    if (!grid) return;
    grid.setAttribute("data-total-pages", String(Math.max(1, totalPages)));
    grid.setAttribute(
      "data-current-loaded-page",
      String(Math.max(1, loadedPage)),
    );
  }

  function getPaginationPageItems(current, total) {
    if (total <= 7) {
      var all = [];
      for (var p = 1; p <= total; p++) all.push(p);
      return all;
    }

    var items = [1];
    var start = Math.max(2, current - 1);
    var end = Math.min(total - 1, current + 1);

    if (start > 2) items.push("ellipsis");
    for (var i = start; i <= end; i++) items.push(i);
    if (end < total - 1) items.push("ellipsis");
    items.push(total);
    return items;
  }

  function renderPagination() {
    if (!paginationNav) return;

    var total = state.totalPages;
    var current = state.currentLoadedPage;

    if (total <= 1) {
      paginationNav.innerHTML = "";
      paginationNav.hidden = true;
      return;
    }

    paginationNav.hidden = false;
    var html = "";
    html +=
      '<button type="button" class="fp-page-btn" data-cc-page="prev" aria-label="Previous page"' +
      (current <= 1 ? " disabled" : "") +
      ">Prev</button>";

    getPaginationPageItems(current, total).forEach(function (item) {
      if (item === "ellipsis") {
        html += '<span class="fp-page-ellipsis" aria-hidden="true">…</span>';
        return;
      }
      var active = item === current;
      html +=
        '<button type="button" class="fp-page-btn' +
        (active ? " fp-page-btn--active" : "") +
        '" data-cc-page="' +
        item +
        '"' +
        (active ? ' aria-current="page" disabled' : "") +
        ">" +
        item +
        "</button>";
    });

    html +=
      '<button type="button" class="fp-page-btn" data-cc-page="next" aria-label="Next page"' +
      (current >= total ? " disabled" : "") +
      ">Next</button>";

    paginationNav.innerHTML = html;
  }

  function scrollToProductGrid() {
    if (!grid) return;
    var offset = 120;
    var top = grid.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
  }

  function setReplaceLoading(on) {
    if (!grid) return;
    grid.style.opacity = on ? "0.45" : "1";
    grid.style.pointerEvents = on ? "none" : "";
  }

  function resolvePricePanelCount(payload) {
    if (!payload || typeof payload !== "object") return 0;

    if (state.priceActive) {
      return payload.count !== undefined && payload.count !== null
        ? parseInt(payload.count, 10) || 0
        : 0;
    }

    if (payload.price_count !== undefined && payload.price_count !== null) {
      return parseInt(payload.price_count, 10) || 0;
    }

    return parseInt(payload.count, 10) || 0;
  }

  function updatePriceCountDisplay(count) {
    var newCount = String(parseInt(count, 10) || 0);

    document.querySelectorAll("#fpPriceCount").forEach(function (el) {
      if (el.textContent === newCount) return;

      el.style.transition = "opacity 0.18s";
      el.style.opacity = "0.35";
      el.textContent = newCount;
      setTimeout(function () {
        el.style.opacity = "1";
      }, 100);
    });
  }

  function renderReplace(payload) {
    if (!grid) return;

    state.totalPages = Math.max(1, parseInt(payload.total_pages, 10) || 1);
    state.currentLoadedPage = Math.max(1, parseInt(payload.page, 10) || 1);
    setGridMeta(state.totalPages, state.currentLoadedPage);

    if (!payload.has_results) {
      grid.innerHTML =
        '<p class="fp-no-results">No products match your filters. <a href="#" id="fpClearFilters">Clear filters</a></p>';
      var clr = document.getElementById("fpClearFilters");
      if (clr) {
        clr.addEventListener("click", function (e) {
          e.preventDefault();
          clearAll();
        });
      }
    } else {
      grid.innerHTML = payload.html;
    }

    if (resultsCount) {
      resultsCount.textContent =
        "Showing " +
        payload.count +
        " product" +
        (payload.count === 1 ? "" : "s");
    }
    if (payload.available_terms) applyTermAvailability(payload.available_terms);
    updateSubcategoryVisibility();

    /* Update "Total products" count in the price panel. */
    updatePriceCountDisplay(resolvePricePanelCount(payload));

    /* Narrow the price slider to the range of products that match current
       taxonomy/search filters (ignoring any price filter the user set). */
    if (payload.price_range) {
      updatePriceSliderBounds(
        parseFloat(payload.price_range.min) || 0,
        parseFloat(payload.price_range.max) || 0
      );
    }

    renderPagination();

    if (grid) {
      grid.setAttribute("data-cc-gtm-list-scanned", "1");
    }
    if (window.ccGtmHandleCategoryFilterPayload) {
      window.ccGtmHandleCategoryFilterPayload(payload);
    } else if (window.ccGtm && window.ccGtm.scanProductGrid) {
      var listCtx = (window.ccGtmConfig && window.ccGtmConfig.listContext) || {};
      var scanned = window.ccGtm.scanProductGrid(grid);
      if (scanned.length) {
        window.ccGtm.pushViewItemList(
          scanned,
          listCtx.item_list_id,
          listCtx.item_list_name,
        );
      }
    }
  }

  /**
   * Apply cross-filter availability: hide every filter item whose term id is
   * NOT in the available list for that taxonomy. Items currently selected
   * always remain visible so users can deselect them. Empty/missing entries
   * mean "all hidden" for that taxonomy (no products match).
   */
  function applyTermAvailability(map) {
    if (!map || typeof map !== "object") return;

    document.querySelectorAll("[data-filter-tax]").forEach(function (group) {
      var tax = group.getAttribute("data-filter-tax");
      if (!tax || !group.classList.contains("fp-filter-group")) return;
      if (!Object.prototype.hasOwnProperty.call(map, tax)) return;

      if (tax === "specialty") {
        group
          .querySelectorAll(".ap-filter-item[data-term-id], .fp-checkbox-label")
          .forEach(function (item) {
            item.classList.remove("cc-filter-item-hidden");
          });
        updateFilterGroupVisibility(group);
        return;
      }

      var available = {};
      (map[tax] || []).forEach(function (id) {
        available[parseInt(id, 10)] = true;
      });

      group
        .querySelectorAll(".ap-filter-item[data-term-id], .fp-checkbox-label")
        .forEach(function (item) {
          var input = item.querySelector(".fp-checkbox");
          var termId = parseInt(
            (item.getAttribute && item.getAttribute("data-term-id")) ||
              (input && input.getAttribute("data-filter-term")) ||
              0,
            10,
          );
          if (!termId) return;

          var selected =
            input &&
            input.dataset.filterTax &&
            state.filters[input.dataset.filterTax] &&
            state.filters[input.dataset.filterTax].indexOf(termId) !== -1;
          var keepVisible =
            available[termId] === true || selected || (input && input.checked);
          item.classList.toggle("cc-filter-item-hidden", !keepVisible);
        });

      updateFilterGroupVisibility(group);
      if (group.getAttribute("data-cc-filter-panel") === "subcategory") {
        updateSubcategoryTriggerByOptionCount(group);
      }
    });
  }

  function fetchPayload(options) {
    options = options || {};

    var body = new URLSearchParams();
    body.set("action", "consucorner_filter_category_products");
    body.set("nonce", data.nonce);
    body.set("category_id", String(data.categoryId));
    body.set("category_taxonomy", data.categoryTaxonomy || "product_cat");
    body.set("subcategory_id", String(state.subcategoryId || 0));
    body.set("page", String(options.page || state.page));
    body.set("per_page", String(options.perPage || state.perPage));
    body.set("sort", state.sort);
    body.set("search", state.search || "");
    body.set(
      "min_price",
      state.priceActive ? String(state.minPrice || 0) : "0",
    );
    body.set(
      "max_price",
      state.priceActive ? String(state.maxPrice || 0) : "0",
    );
    body.set("filters", JSON.stringify(state.filters));
    body.set("specialties", JSON.stringify(state.filters.specialty || []));
    if (options.countOnly) {
      body.set("count_only", "1");
    }

    return fetch(data.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
      credentials: "same-origin",
    })
      .then(function (r) {
        if (!r.ok) throw new Error("bad status " + r.status);
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.success) throw new Error("bad payload");
        return json.data;
      });
  }

  /** Full replace (filters, sort, clear, or explicit page navigation). */
  function fetchProducts(targetPage, scrollAfter) {
    if (!grid || state.pageLoading) return;

    if (typeof targetPage === "number" && targetPage > 0) {
      state.page = targetPage;
    } else {
      state.page = 1;
    }

    pricePreviewRequestId += 1;
    state.pageLoading = true;
    setReplaceLoading(true);

    fetchPayload()
      .then(function (payload) {
        renderReplace(payload);
        if (scrollAfter) scrollToProductGrid();
      })
      .catch(function (err) {
        if (window.CC_DEBUG && window.console)
          window.console.error("[category-filter]", err);
        if (grid) {
          grid.innerHTML =
            '<p class="fp-no-results">Failed to load products. Please retry.</p>';
        }
        renderPagination();
      })
      .finally(function () {
        state.pageLoading = false;
        setReplaceLoading(false);
      });
  }

  function fetchPriceCountPreview() {
    if (state.pageLoading) return;

    var requestId = (pricePreviewRequestId += 1);
    fetchPayload({ countOnly: true, page: 1, perPage: 1 })
      .then(function (payload) {
        if (requestId !== pricePreviewRequestId || state.pageLoading) return;
        updatePriceCountDisplay(resolvePricePanelCount(payload));
      })
      .catch(function (err) {
        if (window.CC_DEBUG && window.console) {
          window.console.error("[category-filter:price-count]", err);
        }
      });
  }

  var debouncedPriceCountPreview = debounce(fetchPriceCountPreview, 250);

  function goToPage(page) {
    page = parseInt(page, 10);
    if (!page || page < 1 || page > state.totalPages) return;
    if (page === state.currentLoadedPage) return;
    fetchProducts(page, true);
  }

  function setupPagination() {
    if (!paginationNav || paginationNav.dataset.ccPaginationBound) return;
    paginationNav.dataset.ccPaginationBound = "1";

    paginationNav.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-cc-page]");
      if (!btn || btn.disabled) return;

      var raw = btn.getAttribute("data-cc-page");
      if (raw === "prev") {
        goToPage(state.currentLoadedPage - 1);
        return;
      }
      if (raw === "next") {
        goToPage(state.currentLoadedPage + 1);
        return;
      }
      goToPage(parseInt(raw, 10));
    });
  }

  function addFilter(tax, termId) {
    if (!state.filters[tax]) state.filters[tax] = [];
    if (state.filters[tax].indexOf(termId) === -1)
      state.filters[tax].push(termId);
  }
  function removeFilter(tax, termId) {
    if (isLockedFilter(tax, termId)) return;
    if (!state.filters[tax]) return;
    state.filters[tax] = state.filters[tax].filter(function (v) {
      return v !== termId;
    });
    if (!state.filters[tax].length) delete state.filters[tax];
  }

  /* ── URL-Driven Filter Utilities ───────────────────────────────────── */

  /**
   * Simple debounce: returns a function that delays invoking `fn` until
   * after `delay` ms have elapsed since the last call.
   */
  function debounce(fn, delay) {
    var timer;
    return function () {
      clearTimeout(timer);
      var args = arguments,
        ctx = this;
      timer = setTimeout(function () {
        fn.apply(ctx, args);
      }, delay);
    };
  }

  /**
   * Resolve a term ID to its URL slug using the value attribute on the
   * matching checkbox element already rendered in the DOM.
   */
  function termIdToSlug(tax, termId) {
    var cb = document.querySelector(
      '.fp-checkbox[data-filter-tax="' +
        tax +
        '"][data-filter-term="' +
        termId +
        '"]'
    );
    return cb ? cb.value : null;
  }

  /**
   * Resolve a term slug back to a term ID by reading the data-filter-term
   * attribute from the matching checkbox element.
   */
  function slugToTermId(tax, slug) {
    var cb = document.querySelector(
      '.fp-checkbox[data-filter-tax="' + tax + '"][value="' + slug + '"]'
    );
    return cb ? parseInt(cb.getAttribute("data-filter-term"), 10) || 0 : 0;
  }

  /**
   * Collect every distinct taxonomy slug that has a checkbox in the DOM.
   * This includes pa_* attribute taxonomies added dynamically per archive.
   */
  function getUrlFilterTaxes() {
    var seen = {};
    document
      .querySelectorAll(".fp-checkbox[data-filter-tax]")
      .forEach(function (cb) {
        seen[cb.getAttribute("data-filter-tax")] = true;
      });
    return Object.keys(seen);
  }

  /**
   * Build a URLSearchParams object from the current filter state.
   * Locked (archive-scoped) terms are excluded — they are already
   * encoded in the base URL path.  Price and sort are included when active.
   */
  function buildUrlParams() {
    var params = new URLSearchParams();
    var taxes = getUrlFilterTaxes();

    taxes.forEach(function (tax) {
      // Campaign/share URLs intentionally omit specialty; use archive permalinks for specialty scope.
      if (tax === "specialty") return;

      var ids = state.filters[tax];
      if (!ids || !ids.length) return;

      var userIds = ids.filter(function (id) {
        return !isLockedFilter(tax, id);
      });
      if (!userIds.length) return;

      var slugs = userIds
        .map(function (id) {
          return termIdToSlug(tax, id);
        })
        .filter(Boolean);
      if (slugs.length) params.set(tax, slugs.join(","));
    });

    if (state.priceActive && state.minPrice > 0)
      params.set("min_price", String(state.minPrice));
    if (state.priceActive && state.maxPrice > 0)
      params.set("max_price", String(state.maxPrice));
    if (state.sort && state.sort !== "default")
      params.set("sort", state.sort);

    return params;
  }

  function stringifyUrlParams(params) {
    /* Standard URL encoding: commas are encoded as %2C (e.g. product_cat=a%2Cb).
       cc_parse_url_filters() decodes these server-side. Specialty is omitted from
       shareable campaign URLs; use specialty archive permalinks for specialty scope. */
    return params.toString();
  }

  /**
   * Frontend filters are AJAX-only; shareable URLs are built in Products → Link Builder.
   */
  function pushStateFromFilters() {
    return;
  }

  /* ── End URL-Driven Filter Utilities ────────────────────────────────── */

  function getInputLabel(input) {
    var label = input.closest("label");
    if (!label) return input.value || "";
    var explicit = label.querySelector(".ap-filter-text");
    var text = explicit ? explicit.textContent : label.textContent;
    return (text || "").trim().replace(/\s+/g, " ");
  }

  function bindFilterTermGroups() {
    document.querySelectorAll(".ap-filter-group-btn").forEach(function (btn) {
      if (btn.dataset.ccGroupBound === "1") return;
      btn.dataset.ccGroupBound = "1";

      btn.addEventListener("click", function () {
        var expanded = this.getAttribute("aria-expanded") === "true";
        var panelId = this.getAttribute("aria-controls");
        var panel = panelId ? document.getElementById(panelId) : null;
        if (!panel) return;

        this.setAttribute("aria-expanded", expanded ? "false" : "true");
        panel.hidden = expanded;
      });
    });
  }

  function getArchiveParentTermId() {
    return data.isProductCatArchive && data.archiveParentTermId
      ? parseInt(data.archiveParentTermId, 10) || 0
      : 0;
  }

  function updateFilterGroupVisibility(scope) {
    (scope || document)
      .querySelectorAll(".ap-filter-item-group[data-cc-term-group]")
      .forEach(function (group) {
        var visibleChildren = group.querySelectorAll(
          ".ap-filter-group-children .ap-filter-item:not(.cc-filter-item-hidden):not(.cc-subcategory-parent-hidden)",
        ).length;
        group.classList.toggle("cc-filter-item-hidden", visibleChildren === 0);

        var btn = group.querySelector(".ap-filter-group-btn");
        var panel = btn
          ? document.getElementById(btn.getAttribute("aria-controls") || "")
          : null;

        if (visibleChildren === 1) {
          group.classList.add("cc-filter-group-single-visible");
          if (btn) btn.setAttribute("aria-expanded", "true");
          if (panel) panel.hidden = false;
        } else {
          group.classList.remove("cc-filter-group-single-visible");
        }
      });
  }

  function updateGroupBadges() {
    document
      .querySelectorAll(".ap-filter-item-group[data-cc-term-group]")
      .forEach(function (group) {
        var count = group.querySelectorAll(".fp-checkbox:checked").length;
        var btn = group.querySelector(".ap-filter-group-btn");
        var badge = group.querySelector(".cc-group-sel-count");
        if (!btn) return;

        btn.classList.toggle("has-selected", count > 0);
        if (badge) {
          badge.textContent = String(count);
          badge.hidden = count < 1;
        }

        if (count > 0 && btn.getAttribute("aria-expanded") === "false") {
          var panelId = btn.getAttribute("aria-controls");
          var panel = panelId ? document.getElementById(panelId) : null;
          btn.setAttribute("aria-expanded", "true");
          if (panel) panel.hidden = false;
        }
      });

    updateFilterGroupVisibility(document);
  }

  function appendActiveChip(strip, label, type, tax, termId, locked) {
    var chip = document.createElement("button");
    chip.type = "button";
    chip.className = "cc-active-filter-chip";
    chip.dataset.chipType = type;
    if (tax) chip.dataset.filterTax = tax;
    if (termId) chip.dataset.filterTerm = String(termId);
    if (locked) {
      chip.dataset.locked = "true";
      chip.setAttribute("aria-disabled", "true");
    }
    chip.appendChild(document.createTextNode(label));

    if (!locked) {
      var close = document.createElement("span");
      close.setAttribute("aria-hidden", "true");
      close.textContent = "×";
      chip.appendChild(close);
    }

    strip.appendChild(chip);
  }

  function formatPrice(value) {
    var num = Math.round(Number(value || 0));
    if (Number.isNaN(num)) return "0";
    return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  /** Cap user-entered max price to filterable ceiling (excludes Get Quote products). */
  function clampPriceFilterMax(value) {
    var ceiling = parseFloat(data.priceFilterCeiling) || 0;
    var num = parseFloat(value);
    if (!ceiling || Number.isNaN(num) || num <= 0) {
      return num > 0 ? num : 0;
    }
    var cap = ceiling - 0.01;
    return num > cap ? cap : num;
  }

  /**
   * Return how many products fall within [minVal, maxVal] by summing
   * the bucket data passed from PHP. Returns null when no bucket data exists.
   */
  function countProductsInRange(minVal, maxVal) {
    var buckets = data.priceBuckets;
    if (!buckets || !Array.isArray(buckets) || !buckets.length) return null;
    var pMin = parseFloat(data.priceMinInitial) || 0;
    var pMax = parseFloat(data.priceMaxInitial) || 1;
    var rangeW = pMax - pMin || 1;
    var n = buckets.length;
    var total = 0;
    for (var i = 0; i < n; i++) {
      var bucketMin = pMin + (i / n) * rangeW;
      var bucketMax = pMin + ((i + 1) / n) * rangeW;
      if (bucketMax >= minVal && bucketMin <= maxVal) {
        total += Number(buckets[i]) || 0;
      }
    }
    return total;
  }

  function getPriceBounds() {
    var maxSlider = document.getElementById("fpMaxSlider");
    return {
      min: parseFloat(data.priceMinInitial) || 0,
      max: maxSlider
        ? parseFloat(maxSlider.max) || parseFloat(data.priceMaxInitial) || 0
        : parseFloat(data.priceMaxInitial) || 0,
    };
  }

  function isElementVisible(el) {
    if (!el) return false;
    var rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  /**
   * Read min/max from the visible price inputs (desktop + mobile panels
   * duplicate IDs) and sync into state before applying filters.
   */
  function commitPriceInputsToState() {
    var bounds = getPriceBounds();
    var minInputs = document.querySelectorAll("#fpMinPriceInput");
    var maxInputs = document.querySelectorAll("#fpMaxPriceInput");

    function pickInput(inputs) {
      var visible = null;
      for (var i = 0; i < inputs.length; i++) {
        if (isElementVisible(inputs[i])) visible = inputs[i];
      }
      return visible || inputs[0] || null;
    }

    var minInput = pickInput(minInputs);
    var maxInput = pickInput(maxInputs);

    if (minInput && minInput.value !== "") {
      state.minPrice = parseFloat(minInput.value) || 0;
    }
    if (maxInput && maxInput.value !== "") {
      state.maxPrice = clampPriceFilterMax(parseFloat(maxInput.value) || bounds.max);
    }
    state.priceActive = true;
  }

  /* ── Dynamic price histogram drawn on .cc-price-canvas ── */
  function drawPriceCurveOnCanvas(canvas, minVal, maxVal) {
    if (!canvas || !canvas.getContext) return;

    var cH = 72;
    var cW = Math.floor(canvas.getBoundingClientRect().width);
    if (cW < 10) {
      cW = Math.floor(canvas.clientWidth);
    }
    if (cW < 10) return;

    var dpr = window.devicePixelRatio || 1;

    /* resize only when dimensions actually change to avoid flicker */
    var needW = Math.round(cW * dpr);
    var needH = Math.round(cH * dpr);
    if (canvas.width !== needW || canvas.height !== needH) {
      canvas.width = needW;
      canvas.height = needH;
    }
    canvas.style.height = cH + "px";

    var ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cW, cH);
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, cW, cH);
    ctx.clip();

    var raw = data.priceBuckets;
    var buckets = raw && Array.isArray(raw) && raw.length > 1 ? raw : null;
    if (!buckets) {
      ctx.restore();
      return;
    }

    var n = buckets.length;
    var maxCount = 1;
    for (var i = 0; i < n; i++) {
      if (buckets[i] > maxCount) maxCount = buckets[i];
    }

    /* build smooth curve point list */
    var pts = [];
    for (var j = 0; j < n; j++) {
      pts.push([
        (j / (n - 1)) * cW,
        cH - 4 - Math.max(2, (buckets[j] / maxCount) * (cH - 12)),
      ]);
    }

    function traceFill() {
      ctx.beginPath();
      ctx.moveTo(0, cH);
      ctx.lineTo(pts[0][0], pts[0][1]);
      for (var k = 0; k < n - 1; k++) {
        var cpx = (pts[k][0] + pts[k + 1][0]) * 0.5;
        ctx.bezierCurveTo(
          cpx,
          pts[k][1],
          cpx,
          pts[k + 1][1],
          pts[k + 1][0],
          pts[k + 1][1],
        );
      }
      ctx.lineTo(cW, cH);
      ctx.closePath();
    }
    function traceStroke() {
      ctx.beginPath();
      ctx.moveTo(pts[0][0], pts[0][1]);
      for (var k = 0; k < n - 1; k++) {
        var cpx = (pts[k][0] + pts[k + 1][0]) * 0.5;
        ctx.bezierCurveTo(
          cpx,
          pts[k][1],
          cpx,
          pts[k + 1][1],
          pts[k + 1][0],
          pts[k + 1][1],
        );
      }
    }

    /* 1) grey background */
    traceFill();
    ctx.fillStyle = "rgba(218, 228, 240, 0.75)";
    ctx.fill();

    /* 2) teal selected region with clip */
    var pMin = getPriceBounds().min;
    var pMax = getPriceBounds().max;
    var rangeW = pMax - pMin || 1;
    var selMinX = Math.max(0, ((minVal - pMin) / rangeW) * cW);
    var selMaxX = Math.min(cW, ((maxVal - pMin) / rangeW) * cW);

    if (selMaxX > selMinX) {
      ctx.save();
      ctx.beginPath();
      ctx.rect(selMinX, 0, selMaxX - selMinX, cH);
      ctx.clip();

      traceFill();
      var grad = ctx.createLinearGradient(0, 0, 0, cH);
      grad.addColorStop(0, "rgba(7, 199, 181, 0.50)");
      grad.addColorStop(1, "rgba(7, 199, 181, 0.12)");
      ctx.fillStyle = grad;
      ctx.fill();

      traceStroke();
      ctx.strokeStyle = "#19aee8";
      ctx.lineWidth = 2.5;
      ctx.lineJoin = "round";
      ctx.lineCap = "round";
      ctx.stroke();

      ctx.restore();
    }

    ctx.restore();
  }

  function drawPriceCurve(minVal, maxVal) {
    document.querySelectorAll(".cc-price-canvas").forEach(function (canvas) {
      drawPriceCurveOnCanvas(canvas, minVal, maxVal);
    });
  }

  function syncPriceControls() {
    var bounds = getPriceBounds();
    var minSliders = document.querySelectorAll("#fpMinSlider");
    var maxSliders = document.querySelectorAll("#fpMaxSlider");
    var minInputs = document.querySelectorAll("#fpMinPriceInput");
    var maxInputs = document.querySelectorAll("#fpMaxPriceInput");
    var displays = document.querySelectorAll("#fpPriceDisplay");
    var trackFills = document.querySelectorAll("#fpTrackFill");
    var activeElement = document.activeElement;

    var rawMin = parseFloat(state.minPrice);
    var min = !isNaN(rawMin) ? rawMin : bounds.min;
    var max = parseFloat(state.maxPrice) || bounds.max;
    min = Math.max(0, Math.min(min, bounds.max));
    max = Math.max(bounds.min, Math.min(max, bounds.max));
    if (min > max) {
      var swap = min;
      min = max;
      max = swap;
    }

    state.minPrice = min;
    state.maxPrice = max;

    minSliders.forEach(function (minSlider) {
      minSlider.value = String(Math.max(bounds.min, min));
    });
    maxSliders.forEach(function (maxSlider) {
      maxSlider.value = String(max);
    });
    minInputs.forEach(function (minInput) {
      if (activeElement === minInput) return;
      minInput.value =
        state.priceActive && Number(state.minPrice) > 0
          ? String(Math.round(Number(state.minPrice)))
          : "";
    });
    maxInputs.forEach(function (maxInput) {
      if (activeElement === maxInput) return;
      maxInput.value = max ? String(Math.round(max)) : "";
    });
    displays.forEach(function (display) {
      display.textContent =
        "Price: " +
        formatPrice(min || bounds.min) +
        " " +
        data.currency +
        " \u2013 " +
        formatPrice(max) +
        " " +
        data.currency;
    });

    /* track fill bar between the two thumbs */
    var rng = bounds.max - bounds.min;
    if (rng > 0) {
      var visualMin = Math.max(bounds.min, min);
      trackFills.forEach(function (trackFill) {
        trackFill.style.left =
          (((visualMin - bounds.min) / rng) * 100).toFixed(2) + "%";
        trackFill.style.width = (((max - visualMin) / rng) * 100).toFixed(2) + "%";
      });
    }

    drawPriceCurve(min, max);
    updateActiveFilterStrip();
    updateFilterButtonCounts();
  }

  /**
   * Update slider/input bounds after an AJAX fetch returns a new price_range.
   * When no custom price filter is active the display text resets to show the
   * new full range; when a price filter IS active the selected values are
   * clamped to the new bounds.
   */
  function updatePriceSliderBounds(newMin, newMax) {
    if (!(newMax > 0)) return;

    newMax = clampPriceFilterMax(newMax) || newMax;

    var minSliders = document.querySelectorAll("#fpMinSlider");
    var maxSliders = document.querySelectorAll("#fpMaxSlider");
    var minInputs  = document.querySelectorAll("#fpMinPriceInput");
    var maxInputs  = document.querySelectorAll("#fpMaxPriceInput");
    var displays   = document.querySelectorAll("#fpPriceDisplay");
    var trackFills = document.querySelectorAll("#fpTrackFill");

    minSliders.forEach(function (s) { s.min = String(newMin); s.max = String(newMax); });
    maxSliders.forEach(function (s) { s.min = String(newMin); s.max = String(newMax); });
    minInputs.forEach(function (i)  { i.min = "0"; i.max = String(newMax); });
    maxInputs.forEach(function (i)  { i.min = "0"; i.max = String(newMax); });

    if (!state.priceActive) {
      /* No user price filter — reset display to new full range. */
      state.minPrice = 0;
      state.maxPrice = newMax;
      minSliders.forEach(function (s) { s.value = String(newMin); });
      maxSliders.forEach(function (s) { s.value = String(newMax); });
      minInputs.forEach(function (i)  { i.value = ""; });
      maxInputs.forEach(function (i)  { i.value = String(Math.round(newMax)); });
      displays.forEach(function (d) {
        d.textContent =
          "Price: " + formatPrice(newMin) + " " + data.currency +
          " \u2013 " + formatPrice(newMax) + " " + data.currency;
      });
      trackFills.forEach(function (f) {
        f.style.left  = "0%";
        f.style.width = "100%";
      });
    } else {
      /* Price filter active — clamp selected values to new bounds, redraw. */
      var activeMin = Math.max(newMin, state.minPrice || 0);
      var activeMax = Math.min(newMax, state.maxPrice || newMax);
      if (activeMin > activeMax) {
        activeMin = newMin;
        activeMax = newMax;
      }
      state.minPrice = activeMin;
      state.maxPrice = activeMax;
      syncPriceControls();
    }
  }

  function updateActiveFilterStrip() {
    var strip = document.getElementById("ccActiveFilters");
    if (!strip) return;
    strip.textContent = "";

    var seenIds = {};
    document.querySelectorAll(".fp-checkbox:checked").forEach(function (cb) {
      var tax = cb.dataset.filterTax;
      var termId = parseInt(cb.dataset.filterTerm, 10);
      if (!tax || !termId) return;
      var key = tax + ":" + termId;
      if (seenIds[key]) return;
      seenIds[key] = true;
      appendActiveChip(
        strip,
        getInputLabel(cb),
        "filter",
        tax,
        termId,
        isLockedFilter(tax, termId),
      );
    });

    if (
      state.priceActive &&
      ((state.minPrice && Number(state.minPrice) > 0) ||
        (state.maxPrice &&
          data.priceMaxInitial &&
          Number(state.maxPrice) < Number(data.priceMaxInitial)))
    ) {
      var bounds = getPriceBounds();
      appendActiveChip(
        strip,
        formatPrice(state.minPrice || bounds.min) +
          " – " +
          formatPrice(state.maxPrice || bounds.max) +
          " " +
          data.currency,
        "price",
      );
    }

    strip.hidden = !strip.children.length;
  }

  function isPriceFilterActive() {
    return (
      state.priceActive &&
      ((state.minPrice && Number(state.minPrice) > 0) ||
        (state.maxPrice &&
          data.priceMaxInitial &&
          Number(state.maxPrice) < Number(data.priceMaxInitial)))
    );
  }

  function getSelectedTermIdsInPanel(target) {
    if (!target) return [];
    var panel = document.querySelector(
      '[data-cc-filter-panel="' + target + '"]',
    );
    if (!panel) return [];
    var ids = [];
    panel
      .querySelectorAll(".fp-checkbox[data-filter-tax][data-filter-term]")
      .forEach(function (cb) {
        var tax = cb.getAttribute("data-filter-tax");
        var id = parseInt(cb.getAttribute("data-filter-term"), 10);
        if (
          tax &&
          id &&
          state.filters[tax] &&
          state.filters[tax].indexOf(id) !== -1 &&
          ids.indexOf(id) === -1
        ) {
          ids.push(id);
        }
      });
    return ids;
  }

  function countTermsInPanel(target) {
    return getSelectedTermIdsInPanel(target).length;
  }

  function getSelectedTopCategoryIds() {
    return getSelectedTermIdsInPanel("category");
  }

  function getSelectedSubcategoryParentIds() {
    var parentIds = getSelectedTopCategoryIds();
    var archiveParentId = getArchiveParentTermId();

    if (archiveParentId && parentIds.indexOf(archiveParentId) === -1) {
      parentIds.push(archiveParentId);
    }

    return parentIds;
  }

  function getSelectedSpecialtyIds() {
    return getSelectedTermIdsInPanel("specialty");
  }

  function setSubcategoryTriggersVisible(visible) {
    document
      .querySelectorAll('[data-cc-sheet-target="subcategory"]')
      .forEach(function (el) {
        el.classList.toggle("cc-subcategory-locked", !visible);
        el.hidden = !visible;
        if (!visible) {
          el.setAttribute("aria-hidden", "true");
          el.setAttribute("tabindex", "-1");
        } else {
          el.removeAttribute("aria-hidden");
          el.removeAttribute("tabindex");
        }
      });
  }

  function getSubcategoryPanel() {
    return document.querySelector('[data-cc-filter-panel="subcategory"]');
  }

  function countSubcategoryOptions(subPanel) {
    if (!subPanel) return 0;
    var seen = {};
    var count = 0;

    subPanel
      .querySelectorAll(".ap-filter-item[data-term-id]")
      .forEach(function (item) {
        if (
          item.classList.contains("cc-filter-item-hidden") ||
          item.classList.contains("cc-subcategory-parent-hidden")
        ) {
          return;
        }

        var termId = parseInt(item.getAttribute("data-term-id") || 0, 10);
        if (!termId || seen[termId]) return;
        seen[termId] = true;
        count += 1;
      });

    return count;
  }

  /**
   * Sub-Category panel is always openable on shop/archive when options exist.
   */
  function canOpenSubcategoryPanel() {
    var subPanel = getSubcategoryPanel();
    if (!subPanel) return false;
    return countSubcategoryOptions(subPanel) >= 1;
  }

  function updateSubcategoryTriggerByOptionCount(subPanel) {
    var shouldShow = countSubcategoryOptions(subPanel) >= 1;
    setSubcategoryTriggersVisible(shouldShow);

    if (!shouldShow) {
      var sidebar = document.getElementById("fpSidebar");
      if (sidebar && sidebar.classList.contains("open")) {
        var activeSub = sidebar.querySelector(
          '[data-cc-filter-panel="subcategory"].is-sheet-active',
        );
        if (activeSub) closeFilters();
      }
    }
  }

  function pruneOrphanedSubcategoryFilters(subPanel, selectedParents) {
    if (
      !subPanel ||
      !selectedParents.length ||
      !state.filters.product_cat ||
      !state.filters.product_cat.length
    ) {
      return false;
    }

    var allowedParents = {};
    selectedParents.forEach(function (parentId) {
      allowedParents[parentId] = true;
    });

    var orphaned = {};
    subPanel
      .querySelectorAll(".ap-filter-item[data-term-id][data-cc-parent-term]")
      .forEach(function (item) {
        var termId = parseInt(item.getAttribute("data-term-id") || 0, 10);
        var parentId = parseInt(
          item.getAttribute("data-cc-parent-term") || 0,
          10,
        );

        if (termId && parentId && !allowedParents[parentId]) {
          orphaned[termId] = true;
        }
      });

    if (!Object.keys(orphaned).length) {
      return false;
    }

    var beforeCount = state.filters.product_cat.length;
    state.filters.product_cat = state.filters.product_cat.filter(function (
      termId,
    ) {
      return orphaned[termId] !== true;
    });

    if (!state.filters.product_cat.length) {
      delete state.filters.product_cat;
    }

    return state.filters.product_cat
      ? state.filters.product_cat.length !== beforeCount
      : beforeCount > 0;
  }

  function updateSubcategoryVisibility() {
    var subPanel = getSubcategoryPanel();
    if (!subPanel) {
      setSubcategoryTriggersVisible(false);
      return;
    }

    var selectedParents = getSelectedSubcategoryParentIds();
    var hasParentScope = selectedParents.length > 0;
    var allowedParents = {};

    selectedParents.forEach(function (parentId) {
      allowedParents[parentId] = true;
    });

    subPanel.querySelectorAll(".ap-filter-item[data-term-id]").forEach(function (
      item,
    ) {
      var parentId = parseInt(
        item.getAttribute("data-cc-parent-term") || 0,
        10,
      );
      var hideForParent =
        hasParentScope && (!parentId || allowedParents[parentId] !== true);

      item.classList.toggle("cc-subcategory-parent-hidden", hideForParent);
    });

    updateFilterGroupVisibility(subPanel);
    updateSubcategoryTriggerByOptionCount(subPanel);
  }

  function getSelectedCountForTarget(target) {
    if (!target || target === "all") {
      var total = 0;
      Object.keys(state.filters || {}).forEach(function (tax) {
        total += state.filters[tax] ? state.filters[tax].length : 0;
      });
      if (isPriceFilterActive()) total += 1;
      return total;
    }

    if (target === "price") return isPriceFilterActive() ? 1 : 0;

    if (target === "category" || target === "subcategory") {
      return countTermsInPanel(target);
    }

    return state.filters[target] ? state.filters[target].length : 0;
  }

  function ensureCountBadge(button) {
    var badge = button.querySelector(
      ".cc-filter-chip-count, .cc-filter-panel-count",
    );
    if (badge) return badge;

    badge = document.createElement("span");
    badge.className = button.classList.contains("cc-filter-panel-btn")
      ? "cc-filter-panel-count"
      : "cc-filter-chip-count";

    var arrow = button.querySelector('span[aria-hidden="true"]');
    if (arrow) button.insertBefore(badge, arrow);
    else button.appendChild(badge);

    return badge;
  }

  function updateFilterButtonCounts() {
    document
      .querySelectorAll("[data-cc-sheet-target]")
      .forEach(function (button) {
        var count = getSelectedCountForTarget(button.dataset.ccSheetTarget);
        var badge = ensureCountBadge(button);
        badge.textContent = String(count);
        badge.hidden = count < 1;
        button.classList.toggle("has-selected-filters", count > 0);
      });
  }

  function syncCheckboxesAndPills() {
    var subPanel = getSubcategoryPanel();
    if (subPanel) {
      pruneOrphanedSubcategoryFilters(
        subPanel,
        getSelectedSubcategoryParentIds(),
      );
    }

    document.querySelectorAll(".fp-checkbox").forEach(function (cb) {
      var tax = cb.dataset.filterTax;
      var termId = parseInt(cb.dataset.filterTerm, 10);
      var active = !!(
        state.filters[tax] && state.filters[tax].indexOf(termId) !== -1
      );
      cb.checked = active;
      var item = cb.closest(".ap-filter-item");
      if (item) {
        item.classList.toggle("is-selected", active);
        if (active) item.classList.remove("cc-filter-item-hidden");
      }
      var label = cb.closest(".fp-checkbox-label");
      if (label) {
        label.classList.toggle("is-selected", active);
        if (active) label.classList.remove("cc-filter-item-hidden");
      }
    });
    document
      .querySelectorAll(".fp-filter-tag-btn[data-cc-pill]")
      .forEach(function (btn) {
        var tax = btn.dataset.filterTax;
        var termId = parseInt(btn.dataset.filterTerm, 10);
        var active = !!(
          state.filters[tax] && state.filters[tax].indexOf(termId) !== -1
        );
        btn.classList.toggle("fp-filter-tag-btn--active", active);
      });
    updateActiveFilterStrip();
    updateFilterButtonCounts();
    updateSubcategoryVisibility();
    updateGroupBadges();
  }

  function clearAll() {
    state.filters = cloneFilterMap(lockedFilters);
    state.subcategoryId = 0;
    state.minPrice = 0;
    state.maxPrice = 0;
    state.priceActive = false;
    state.sort = "default";
    state.search = "";
    state.page = 1;

    var sortSel = document.getElementById("fpSortSelect");
    if (sortSel) sortSel.value = "default";

    state.maxPrice = 0;
    state.minPrice = 0;
    syncPriceControls();
    updateFilterButtonCounts();

    document.querySelectorAll(".fp-collection-tab").forEach(function (t) {
      t.classList.remove("fp-collection-tab--active");
    });
    var allTab = document.querySelector(
      '.fp-collection-tab[data-subcategory="0"]',
    );
    if (allTab) allTab.classList.add("fp-collection-tab--active");

    syncCheckboxesAndPills();
    pushStateFromFilters();
    fetchProducts();
  }

  function closeFilters() {
    var sidebar = document.getElementById("fpSidebar");
    var overlay = document.getElementById("fpSidebarOverlay");
    if (sidebar) {
      unmountDesktopFilterPanel(sidebar);
      sidebar.classList.remove("open", "cc-sheet-filtered");
      clearDesktopFilterPanelStyles(sidebar);
      sidebar
        .querySelectorAll("[data-cc-filter-panel]")
        .forEach(function (panel) {
          panel.classList.remove("is-sheet-active", "is-filter-highlight");
        });
    }
    document.querySelectorAll("[data-cc-sheet-target]").forEach(function (btn) {
      btn.setAttribute("aria-expanded", "false");
    });
    if (overlay) overlay.classList.remove("active");
    document.body.style.overflow = "";
  }

  function openFilterPanel(target, title, trigger) {
    var sidebar = document.getElementById("fpSidebar");
    var overlay = document.getElementById("fpSidebarOverlay");
    var titleEl = document.getElementById("ccFilterSheetTitle");
    if (!sidebar || !overlay) return;

    target = target || "all";
    if (target === "subcategory" && !canOpenSubcategoryPanel()) return;
    if (titleEl) titleEl.textContent = title || "Filters";

    var panels = sidebar.querySelectorAll("[data-cc-filter-panel]");
    panels.forEach(function (panel) {
      var active = target === "all" || panel.dataset.ccFilterPanel === target;
      panel.classList.toggle("is-sheet-active", active);
      panel.classList.remove("is-filter-highlight");
    });
    document.querySelectorAll("[data-cc-sheet-target]").forEach(function (btn) {
      btn.setAttribute("aria-expanded", btn === trigger ? "true" : "false");
    });

    if (window.matchMedia("(max-width: 768px)").matches) {
      unmountDesktopFilterPanel(sidebar);
      clearDesktopFilterPanelStyles(sidebar);
      sidebar.classList.toggle("cc-sheet-filtered", target !== "all");
      sidebar.classList.add("open");
      overlay.classList.add("active");
      document.body.style.overflow = "hidden";
      return;
    }

    sidebar.classList.toggle("cc-sheet-filtered", target !== "all");
    mountDesktopFilterPanel(sidebar, trigger);
    sidebar.classList.add("open");

    /* Repaint histogram now that the price panel is visible and has width */
    if (target === "price" || target === "all") {
      requestAnimationFrame(function () {
        var b = getPriceBounds();
        drawPriceCurve(state.minPrice || b.min, state.maxPrice || b.max);
      });
    }
  }

  /**
   * Parse URL query params into `state` on page load (or popstate).
   * Resets user-changeable filters to locked filters first, then applies
   * whatever taxonomy / price / sort params are present in the URL.
   */
  function initFromUrl() {
    var params = new URLSearchParams(window.location.search || "");

    // Reset to locked filters only (clear any stale user selections).
    state.filters = cloneFilterMap(lockedFilters);

    // Re-apply taxonomy filters from server-parsed URL IDs first. This keeps
    // shared campaign URLs reliable even when some selected terms are hidden
    // from the currently visible facet list.
    var usedServerUrlFilters = false;
    if ((window.location.search || "") === initialUrlSearch) {
      var skipSpecialtyUrl =
        data.categoryTaxonomy === "specialty" || !!data.lockedFilters.specialty;
      Object.keys(urlFilters).forEach(function (tax) {
        if (skipSpecialtyUrl && tax === "specialty") return;
        (urlFilters[tax] || []).forEach(function (id) {
          if (id && !isLockedFilter(tax, id)) addFilter(tax, id);
          usedServerUrlFilters = true;
        });
      });
    }

    // Fallback for browser history entries created after page load.
    if (!usedServerUrlFilters) {
      var skipSpecialtyInUrl =
        data.categoryTaxonomy === "specialty" || !!data.lockedFilters.specialty;
      getUrlFilterTaxes().forEach(function (tax) {
        if (skipSpecialtyInUrl && tax === "specialty") return;
        var raw = params.get(tax);
        if (!raw) return;
        raw.split(",").forEach(function (slug) {
          slug = slug.trim();
          if (!slug) return;
          var id = slugToTermId(tax, slug);
          if (id && !isLockedFilter(tax, id)) addFilter(tax, id);
        });
      });
    }

    // Price bounds from URL.
    var urlMin = parseFloat(params.get("min_price")) || 0;
    var urlMax = parseFloat(params.get("max_price")) || 0;
    if (urlMin > 0 || urlMax > 0) {
      state.minPrice = urlMin;
      state.maxPrice = urlMax || getPriceBounds().max;
      state.priceActive = true;
    } else {
      state.minPrice = 0;
      state.maxPrice = getPriceBounds().max;
      state.priceActive = false;
    }

    // Sort from URL.
    var urlSort = params.get("sort");
    state.sort = urlSort || "default";
    var sortSel = document.getElementById("fpSortSelect");
    if (sortSel && urlSort) sortSel.value = urlSort;
  }

  // Debounced wrapper around fetchProducts to prevent AJAX flood on rapid clicks.
  var debouncedFetch = debounce(fetchProducts, 200);

  function init() {
    // Sync JS state with any URL filter params on first load.
    initFromUrl();

    updateSubcategoryVisibility();
    state.totalPages = getGridTotalPages();
    state.currentLoadedPage = getGridCurrentLoadedPage();
    state.page = state.currentLoadedPage;
    var initialMaxSlider = document.getElementById("fpMaxSlider");
    if (initialMaxSlider && !state.maxPrice) {
      state.maxPrice = parseFloat(initialMaxSlider.max) || 0;
    }
    syncPriceControls();
    if (!state.priceActive && data.initialPriceCount !== undefined) {
      updatePriceCountDisplay(data.initialPriceCount);
    }
    /* Histogram canvas may be 0-width while hidden; repaint once layout settles */
    requestAnimationFrame(function () {
      var b = getPriceBounds();
      drawPriceCurve(state.minPrice || b.min, state.maxPrice || b.max);
    });
    setupPagination();
    renderPagination();
    bindFilterTermGroups();

    document.querySelectorAll(".fp-checkbox").forEach(function (cb) {
      cb.addEventListener("change", function () {
        var tax = this.dataset.filterTax;
        var termId = parseInt(this.dataset.filterTerm, 10);
        if (!tax || !termId) return;
        if (this.checked) addFilter(tax, termId);
        else removeFilter(tax, termId);
        state.page = 1;
        syncCheckboxesAndPills();
        pushStateFromFilters();
        debouncedFetch();
      });
    });

    document
      .querySelectorAll(".fp-filter-tag-btn[data-cc-pill]")
      .forEach(function (btn) {
        btn.addEventListener("click", function () {
          var tax = this.dataset.filterTax;
          var termId = parseInt(this.dataset.filterTerm, 10);
          if (!tax || !termId) return;
          var isActive = this.classList.contains("fp-filter-tag-btn--active");
          if (isActive) {
            removeFilter(tax, termId);
          } else {
            addFilter(tax, termId);
          }
          state.page = 1;
          syncCheckboxesAndPills();
          pushStateFromFilters();
          debouncedFetch();
        });
      });

    var sortSel = document.getElementById("fpSortSelect");
    if (sortSel) {
      sortSel.addEventListener("change", function () {
        state.sort = this.value || "default";
        state.page = 1;
        pushStateFromFilters();
        fetchProducts();
      });
    }

    var minSliders = document.querySelectorAll("#fpMinSlider");
    var maxSliders = document.querySelectorAll("#fpMaxSlider");
    var minInputs = document.querySelectorAll("#fpMinPriceInput");
    var maxInputs = document.querySelectorAll("#fpMaxPriceInput");
    minSliders.forEach(function (minSlider) {
      minSlider.addEventListener("input", function () {
        var v = parseFloat(this.value) || getPriceBounds().min;
        if (v > state.maxPrice) v = state.maxPrice;
        state.minPrice = v;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
    });
    maxSliders.forEach(function (maxSlider) {
      maxSlider.addEventListener("input", function () {
        var v = parseFloat(this.value) || getPriceBounds().max;
        if (v < state.minPrice) v = state.minPrice;
        state.maxPrice = v;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
    });
    minInputs.forEach(function (minInput) {
      minInput.addEventListener("input", function () {
        state.minPrice = parseFloat(this.value) || 0;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
      minInput.addEventListener("change", function () {
        state.minPrice = parseFloat(this.value) || 0;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
    });
    maxInputs.forEach(function (maxInput) {
      maxInput.addEventListener("input", function () {
        state.maxPrice = parseFloat(this.value) || getPriceBounds().max;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
      maxInput.addEventListener("change", function () {
        state.maxPrice = parseFloat(this.value) || getPriceBounds().max;
        state.priceActive = true;
        syncPriceControls();
        debouncedPriceCountPreview();
      });
    });
    document.querySelectorAll("#fpPriceFilterBtn").forEach(function (priceBtn) {
      priceBtn.addEventListener("click", function () {
        state.page = 1;
        commitPriceInputsToState();
        syncPriceControls();
        pushStateFromFilters();
        fetchProducts();
      });
    });

    document
      .querySelectorAll(".fp-collection-tab[data-subcategory]")
      .forEach(function (tab) {
        tab.addEventListener("click", function () {
          document
            .querySelectorAll(".fp-collection-tab[data-subcategory]")
            .forEach(function (t) {
              t.classList.remove("fp-collection-tab--active");
            });
          this.classList.add("fp-collection-tab--active");
          state.subcategoryId = parseInt(this.dataset.subcategory, 10) || 0;
          state.page = 1;
          pushStateFromFilters();
          fetchProducts();
        });
      });

    var clearBtn = document.getElementById("fpClearAll");
    if (clearBtn) {
      clearBtn.addEventListener("click", function (e) {
        e.preventDefault();
        clearAll();
      });
    }

    var activeStrip = document.getElementById("ccActiveFilters");
    if (activeStrip) {
      activeStrip.addEventListener("click", function (e) {
        var chip = e.target.closest(".cc-active-filter-chip");
        if (!chip) return;
        if (chip.dataset.locked === "true") return;

        var type = chip.dataset.chipType;
        if (type === "filter") {
          removeFilter(
            chip.dataset.filterTax,
            parseInt(chip.dataset.filterTerm, 10),
          );
        } else if (type === "price") {
          state.maxPrice = 0;
          state.minPrice = 0;
          state.priceActive = false;
          syncPriceControls();
        }

        state.page = 1;
        syncCheckboxesAndPills();
        pushStateFromFilters();
        fetchProducts();
      });
    }

    document.querySelectorAll("[data-cc-sheet-target]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (
          this.dataset.ccSheetTarget === "subcategory" &&
          !canOpenSubcategoryPanel()
        ) {
          return;
        }
        openFilterPanel(
          this.dataset.ccSheetTarget,
          this.dataset.ccSheetTitle || this.textContent.trim(),
          this,
        );
      });
    });

    document.querySelectorAll(".cc-filter-sheet-close").forEach(function (btn) {
      btn.addEventListener("click", closeFilters);
    });

    document.querySelectorAll(".cc-sheet-done").forEach(function (btn) {
      btn.addEventListener("click", function () {
        commitPriceInputsToState();
        syncPriceControls();
        state.page = 1;
        if (window.ccGtm && window.ccGtm.pushFilterProducts) {
          window.ccGtm.pushFilterProducts({
            filters: state.filters,
            sort: state.sort,
            search: state.search || "",
          });
        }
        pushStateFromFilters();
        fetchProducts();
        closeFilters();
      });
    });

    document.querySelectorAll(".cc-sheet-reset").forEach(function (btn) {
      btn.addEventListener("click", function () {
        clearAll();
      });
    });

    if (grid) {
      grid.addEventListener("click", function (e) {
        if (
          e.target.closest(
            ".btn-add-cart, .btn-save, .btn-compare, .ajax_add_to_cart, a, .card-variation-wrap",
          )
        )
          return;
        var card = e.target.closest("[data-href]");
        if (card && card.dataset.href) {
          if (window.ccGtm && window.ccGtm.pushSelectItem) {
            var gtmItem = window.ccGtm.readItemFromEl(card);
            var listCtx = (window.ccGtmConfig && window.ccGtmConfig.listContext) || {};
            if (gtmItem) {
              window.ccGtm.pushSelectItem(
                gtmItem,
                listCtx.item_list_id,
                listCtx.item_list_name,
              );
            }
          }
          window.location.href = card.dataset.href;
        }
      });
    }

    var filterToggle = document.getElementById("fpFilterToggle");
    var sidebar = document.getElementById("fpSidebar");
    var overlay = document.getElementById("fpSidebarOverlay");
    if (filterToggle && sidebar && overlay) {
      overlay.addEventListener("click", function () {
        closeFilters();
      });
    }

    document.addEventListener("click", function (e) {
      var isMobile = window.matchMedia("(max-width: 768px)").matches;
      if (isMobile || !sidebar || !sidebar.classList.contains("open")) return;
      if (
        e.target.closest("#fpSidebar") ||
        e.target.closest("[data-cc-sheet-target]")
      )
        return;
      closeFilters();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeFilters();
    });

    syncCheckboxesAndPills();
    setupStickyFilterState();
    setupDesktopFilterPanelResize();
    setupViewToggle();
    initCardVariationPanels();

    if (data.autoFetchOnLoad || (state.priceActive && !data.urlFiltersActive)) {
      fetchProducts();
    }

    var slides = document.querySelectorAll(".fp-sidebar-banner .hero-slide");
    if (slides.length) {
      var idx = 0;
      setInterval(function () {
        slides[idx].classList.remove("active");
        idx = (idx + 1) % slides.length;
        slides[idx].classList.add("active");
      }, 4500);
    }
  }

  /**
   * Inline variation picker on archive cards for variable products.
   */
  function initCardVariationPanels() {
    var openPanel = null;
    var wcBase = (window.consuSiteData && window.consuSiteData.siteUrl)
      ? window.consuSiteData.siteUrl.replace(/\/$/, "")
      : window.location.origin;

    function closeAllPanels() {
      document.querySelectorAll(".card-variation-panel.is-open").forEach(function (panel) {
        panel.classList.remove("is-open");
        panel.setAttribute("hidden", "");
        panel.setAttribute("aria-hidden", "true");
      });
      document.querySelectorAll(".btn-add-cart--variable[aria-expanded='true']").forEach(function (btn) {
        btn.setAttribute("aria-expanded", "false");
      });
      openPanel = null;
    }

    function parseVariations(panel) {
      var raw = panel.getAttribute("data-product-variations") || "[]";
      try {
        var parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      } catch (_) {
        return [];
      }
    }

    function getSelectedAttributes(panel) {
      var attrs = {};
      panel.querySelectorAll(".card-variation-select").forEach(function (sel) {
        if (sel.name && sel.value) {
          attrs[sel.name] = sel.value;
        }
      });
      return attrs;
    }

    function allAttributesSelected(panel, attrs) {
      var selects = panel.querySelectorAll(".card-variation-select");
      if (!selects.length) {
        return false;
      }
      for (var i = 0; i < selects.length; i++) {
        if (!selects[i].value) {
          return false;
        }
      }
      return Object.keys(attrs).length === selects.length;
    }

    function findMatchingVariation(variations, attrs) {
      for (var i = 0; i < variations.length; i++) {
        var variation = variations[i];
        if (!variation || !variation.attributes) {
          continue;
        }
        var match = true;
        for (var attrKey in variation.attributes) {
          if (!Object.prototype.hasOwnProperty.call(variation.attributes, attrKey)) {
            continue;
          }
          var varVal = variation.attributes[attrKey];
          if (varVal === "" || varVal === undefined) {
            continue;
          }
          if (attrs[attrKey] !== varVal) {
            match = false;
            break;
          }
        }
        if (!match) {
          continue;
        }
        for (var selKey in attrs) {
          if (!Object.prototype.hasOwnProperty.call(attrs, selKey)) {
            continue;
          }
          var expected = variation.attributes[selKey];
          if (expected !== "" && expected !== undefined && expected !== attrs[selKey]) {
            match = false;
            break;
          }
        }
        if (match && variation.is_in_stock && variation.is_purchasable) {
          return variation;
        }
      }
      return null;
    }

    function updateCardVariationPrice(panel) {
      var priceEl = panel.querySelector(".card-variation-price");
      if (!priceEl) {
        return;
      }
      var attrs = getSelectedAttributes(panel);
      if (!allAttributesSelected(panel, attrs)) {
        priceEl.innerHTML = "";
        priceEl.classList.add("is-pending");
        return;
      }
      var matched = findMatchingVariation(parseVariations(panel), attrs);
      if (matched && matched.price_html) {
        priceEl.innerHTML = matched.price_html;
        priceEl.classList.remove("is-pending");
      } else {
        priceEl.innerHTML = "";
        priceEl.classList.add("is-pending");
      }
    }

    function addVariableToCart(parentId, variationId, attrs, qty) {
      var endpoint = wcBase + "/?wc-ajax=add_to_cart";
      var body = new URLSearchParams();
      body.set("product_id", String(variationId));
      body.set("quantity", String(qty || 1));

      return fetch(endpoint, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: body.toString(),
      }).then(function (res) {
        if (!res.ok) {
          throw new Error("Add-to-cart failed: " + res.status);
        }
        return res.json();
      }).then(function (data) {
        if (data && data.error) {
          throw new Error("WC rejected add-to-cart");
        }
        return data;
      });
    }

    document.addEventListener(
      "change",
      function (e) {
        var select = e.target.closest(".card-variation-select");
        if (!select) {
          return;
        }
        var panel = select.closest(".card-variation-panel");
        if (panel) {
          updateCardVariationPrice(panel);
        }
      },
      true,
    );

    document.addEventListener(
      "click",
      function (e) {
        var toggleBtn = e.target.closest(".btn-add-cart--variable");
        if (toggleBtn) {
          e.preventDefault();
          e.stopPropagation();

          var wrap = toggleBtn.closest(".card-variation-wrap");
          var panel = wrap ? wrap.querySelector(".card-variation-panel") : null;
          if (!panel) {
            return;
          }

          var isOpen = panel.classList.contains("is-open");
          closeAllPanels();
          if (!isOpen) {
            panel.classList.add("is-open");
            panel.removeAttribute("hidden");
            panel.setAttribute("aria-hidden", "false");
            toggleBtn.setAttribute("aria-expanded", "true");
            openPanel = panel;
            updateCardVariationPrice(panel);
          }
          return;
        }

        var addBtn = e.target.closest(".card-variation-add");
        if (addBtn) {
          e.preventDefault();
          e.stopPropagation();

          if (addBtn.disabled) {
            return;
          }

          var panelEl = addBtn.closest(".card-variation-panel");
          if (!panelEl) {
            return;
          }

          var parentId = parseInt(panelEl.getAttribute("data-product-id"), 10) || 0;
          var attrs = getSelectedAttributes(panelEl);
          if (!parentId || !allAttributesSelected(panelEl, attrs)) {
            return;
          }

          var variations = parseVariations(panelEl);
          var matched = findMatchingVariation(variations, attrs);
          if (!matched || !matched.variation_id) {
            var viewLink = panelEl.querySelector(".card-variation-view");
            if (viewLink && viewLink.href) {
              window.location.href = viewLink.href;
            }
            return;
          }

          var original = addBtn.textContent;
          addBtn.disabled = true;
          addBtn.textContent = "Adding...";

          addVariableToCart(parentId, matched.variation_id, attrs, 1)
            .then(function () {
              addBtn.textContent = "Added!";
              closeAllPanels();
              if (window.CCMiniCart && window.CCMiniCart.loadFromWC) {
                window.CCMiniCart.loadFromWC(function () {
                  if (window.CCMiniCart.open) {
                    window.CCMiniCart.open();
                  }
                });
              }
              if (window.ccGtm && window.ccGtm.pushAddToCart) {
                var card = panelEl.closest(".card-shop");
                var gtmItem = card && window.ccGtm.readItemFromEl
                  ? window.ccGtm.readItemFromEl(card)
                  : null;
                if (gtmItem) {
                  window.ccGtm.pushAddToCart(gtmItem);
                }
              }
              window.setTimeout(function () {
                addBtn.textContent = original;
                addBtn.disabled = false;
              }, 1200);
            })
            .catch(function () {
              addBtn.textContent = original;
              addBtn.disabled = false;
            });
          return;
        }

        if (openPanel && !e.target.closest(".card-variation-wrap")) {
          closeAllPanels();
        }
      },
      true,
    );

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closeAllPanels();
      }
    });
  }

  /**
   * Browser back/forward is not used for in-page filter state (AJAX-only filters).
   */

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
