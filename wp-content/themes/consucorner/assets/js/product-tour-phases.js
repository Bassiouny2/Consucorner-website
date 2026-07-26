/**
 * ConsuCorner Tours v2 — step builders (max 5 steps, element spotlight required).
 */
(function (window) {
  "use strict";

  var MAX_STEPS = 5;

  function cfg() {
    return window.ccProductTour || {};
  }

  function strings() {
    return (cfg().strings) || {};
  }

  function isMobile() {
    return window.matchMedia("(max-width: " + (cfg().mobileMaxWidth || 768) + "px)").matches;
  }

  function queryTour(slug) {
    return document.querySelector('[data-cc-tour="' + slug + '"]');
  }

  function firstVisible(nodes) {
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i] && nodes[i].offsetParent !== null) {
        return nodes[i];
      }
    }
    for (var j = 0; j < nodes.length; j++) {
      if (nodes[j]) return nodes[j];
    }
    return null;
  }

  function popover(title, description, side, align) {
    return {
      title: title,
      description: description,
      side: side || "bottom",
      align: align || "start",
    };
  }

  function step(element, pop) {
    if (!element) return null;
    return {
      element: element,
      popover: pop,
    };
  }

  function enforceCap(steps, tourId) {
    var valid = steps.filter(Boolean);
    if (valid.length > MAX_STEPS) {
      if (window.console && window.console.warn) {
        window.console.warn(
          "[cc-tours] " + tourId + " exceeded " + MAX_STEPS + " steps; trimming.",
        );
      }
      return valid.slice(0, MAX_STEPS);
    }
    return valid;
  }

  function buildShopSteps() {
    var s = strings().shop || {};
    var out = [];

    var filterBar = firstVisible([
      queryTour("shop-filter-bar"),
      document.querySelector(".fp-filter-bar-left"),
      document.querySelector(".fp-filter-bar.ap-filter-bar"),
    ]);
    if (filterBar) {
      var barCopy = s.filterBar || s.allFilters || s.specialty || {};
      out.push(
        step(
          filterBar,
          popover(barCopy.title, barCopy.description, "bottom", "start"),
        ),
      );
    }

    var specialtyChip = queryTour("specialty-filter");
    var step2El = firstVisible([
      specialtyChip,
      queryTour("all-filters"),
    ]);
    if (step2El) {
      var step2Copy =
        specialtyChip && step2El === specialtyChip
          ? s.specialty
          : s.allFilters || s.specialty;
      out.push(
        step(
          step2El,
          popover(step2Copy.title, step2Copy.description, "bottom", "start"),
        ),
      );
    }

    var category = firstVisible([queryTour("category-filter")]);
    if (category) {
      out.push(
        step(
          category,
          popover(s.category.title, s.category.description, "bottom", "start"),
        ),
      );
    }

    return enforceCap(out, "shop");
  }

  function buildProductSteps() {
    return [];
  }

  function getTourState() {
    if (window.ccTourCore && window.ccTourCore.getState) {
      return window.ccTourCore.getState();
    }
    try {
      return JSON.parse(
        localStorage.getItem(cfg().storageKey || "cc_site_tours_v2"),
      );
    } catch (_e) {
      return null;
    }
  }

  function buildHomeSteps() {
    var s = strings().home || {};
    var state = getTourState() || {};
    var path = state.welcome_path;
    var out = [];

    if (path === "search") {
      var searchEl = firstVisible([
        document.querySelector("form.header-search"),
        document.querySelector('form[data-cc-tour="header-search"]'),
        document.querySelector("form.mobile-search-inner"),
      ]);
      if (searchEl) {
        var searchCopy = s.search || {};
        out.push(
          step(
            searchEl,
            popover(
              searchCopy.title,
              searchCopy.description,
              "bottom",
              "start",
            ),
          ),
        );
      }
      return enforceCap(out, "home");
    }

    if (path === "categories") {
      var popular = document.querySelector(".popular-categories");
      if (popular) {
        var catCopy = s.popularCategories || s.categories;
        out.push(
          step(
            popular,
            popover(catCopy.title, catCopy.description, "top", "center"),
          ),
        );
      }
      return enforceCap(out, "home");
    }

    var carousel = document.querySelector(".browse-categories-carousel");
    if (carousel) {
      var carouselCopy = s.carousel || s.categories;
      out.push(
        step(
          carousel,
          popover(
            carouselCopy.title,
            carouselCopy.description,
            "top",
            "center",
          ),
        ),
      );
    }

    return enforceCap(out, "home");
  }

  function buildCartSteps() {
    var s = strings().cart || {};
    var out = [];
    var list = document.querySelector(".cart-list-card");
    if (list) {
      out.push(step(list, popover(s.list.title, s.list.description, "bottom", "start")));
    }
    var checkout = document.querySelector(".cart-checkout-btn");
    if (checkout) {
      out.push(
        step(checkout, popover(s.checkout.title, s.checkout.description, "top", "end")),
      );
    }
    var share = document.querySelector("button[data-cc-cart-share]");
    if (share) {
      out.push(
        step(share, popover(s.share.title, s.share.description, "bottom", "start")),
      );
    }
    var coupon = document.querySelector(".coupon-block");
    if (coupon) {
      out.push(
        step(coupon, popover(s.coupon.title, s.coupon.description, "top", "start")),
      );
    }
    return enforceCap(out, "cart");
  }

  function buildAccountSteps() {
    var s = strings().account || {};
    var out = [];
    var orders = document.querySelector('[data-modal="modal-orders"]');
    if (orders) {
      out.push(step(orders, popover(s.orders.title, s.orders.description, "bottom", "start")));
    }
    var wallet = document.querySelector('[data-modal="modal-wallet"]');
    if (wallet) {
      out.push(step(wallet, popover(s.wallet.title, s.wallet.description, "bottom", "start")));
    }
    return enforceCap(out, "account");
  }

  function findWishlistAccountTarget() {
    return (
      document.getElementById("wishlist-grid") ||
      document.querySelector('[data-modal="modal-orders"]') ||
      document.querySelector(".drawer-account-link") ||
      document.querySelector('a[href*="my-account"]')
    );
  }

  function buildWishlistSteps(options) {
    options = options || {};
    var s = strings().wishlist || {};
    var out = [];
    var btn = document.querySelector(
      ".sp-wishlist-btn.is-saved, .card-wish-icon.is-saved",
    );
    if (!btn) {
      btn = document.querySelector(".sp-wishlist-btn, .card-wish-icon");
    }
    if (btn) {
      out.push(
        step(btn, popover(s.save.title, s.save.description, "left", "start")),
      );
    }
    if (!options.pdpOnly && options.onAccountPage) {
      var target = findWishlistAccountTarget();
      if (target) {
        out.push(
          step(target, popover(s.grid.title, s.grid.description, "top", "center")),
        );
      }
    }
    return enforceCap(out, "wishlist");
  }

  window.ccTourPhases = {
    MAX_STEPS: MAX_STEPS,
    buildShopSteps: buildShopSteps,
    buildProductSteps: buildProductSteps,
    buildHomeSteps: buildHomeSteps,
    buildCartSteps: buildCartSteps,
    buildAccountSteps: buildAccountSteps,
    buildWishlistSteps: buildWishlistSteps,
    getStepsForPhase: function (phase, options) {
      options = options || {};
      switch (phase) {
        case "shop":
          return buildShopSteps();
        case "home":
          return buildHomeSteps();
        case "cart":
          return buildCartSteps();
        case "account":
          return buildAccountSteps();
        case "wishlist":
          return buildWishlistSteps(options);
        default:
          return [];
      }
    },
  };
})(window);
