/**
 * ConsuCorner Tours v2 — state, Driver runner, session rules, REST sync.
 */
(function (window, document) {
  "use strict";

  var cfg = window.ccProductTour || {};
  var SESSION_KEY = "cc_tours_v2_session";
  var syncTimer = null;
  var activeTourId = "";
  var tourStartedAt = 0;
  var activeTourTotal = 0;

  function defaultState() {
    return {
      version: 2,
      global_disabled: false,
      welcome_seen: false,
      welcome_path: null,
      phases: {
        shop: "pending",
        home: "pending",
        cart: "pending",
        account: "pending",
        wishlist: "pending",
      },
      synced_at: new Date().toISOString(),
    };
  }

  function normalizeState(raw) {
    var base = defaultState();
    if (!raw || typeof raw !== "object") return base;
    base.global_disabled = !!raw.global_disabled;
    base.welcome_seen = !!raw.welcome_seen;
    base.welcome_path =
      typeof raw.welcome_path === "string" && raw.welcome_path
        ? raw.welcome_path
        : null;
    if (raw.phases && typeof raw.phases === "object") {
      Object.keys(base.phases).forEach(function (phase) {
        if (
          raw.phases[phase] === "pending" ||
          raw.phases[phase] === "done" ||
          raw.phases[phase] === "skipped"
        ) {
          base.phases[phase] = raw.phases[phase];
        }
      });
    }
    return base;
  }

  function readStorage() {
    try {
      var raw = localStorage.getItem(cfg.storageKey || "cc_site_tours_v2");
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (_e) {
      return null;
    }
  }

  function writeStorage(state) {
    try {
      state.synced_at = new Date().toISOString();
      localStorage.setItem(
        cfg.storageKey || "cc_site_tours_v2",
        JSON.stringify(state),
      );
    } catch (_e2) {
      /* ignore */
    }
  }

  function migrateV1() {
    var legacyKey = cfg.legacyStorageKey || "cc_product_tour_v1";
    try {
      var raw = localStorage.getItem(legacyKey);
      if (!raw) return;
      var v1 = JSON.parse(raw);
      var state = readStorage() ? normalizeState(readStorage()) : defaultState();
      if (v1.shop === "done" || v1.shop === "skipped") {
        state.phases.shop = v1.shop;
      }
      if (v1.product === "done" || v1.product === "skipped") {
        state.phases.product = v1.product;
      }
      if (
        state.phases.shop !== "pending" ||
        state.phases.product !== "pending"
      ) {
        state.welcome_seen = true;
      }
      writeStorage(state);
      localStorage.removeItem(legacyKey);
    } catch (_e3) {
      /* ignore */
    }
  }

  var state = normalizeState(cfg.serverState || readStorage() || defaultState());
  if (cfg.serverState) {
    writeStorage(state);
  }
  migrateV1();

  function getState() {
    return state;
  }

  function setState(next) {
    state = normalizeState(next);
    writeStorage(state);
    scheduleSync();
  }

  function setCookie(name) {
    document.cookie = name + "=1; path=/; max-age=2592000; SameSite=Lax";
  }

  function setIdleCookie() {
    setCookie(cfg.idleCookie || "cc_tours_v2_idle");
  }

  function setWelcomeSeenCookie() {
    setCookie(cfg.welcomeSeenCookie || "cc_tours_welcome_seen");
  }

  function globalDisable() {
    var next = getState();
    next.global_disabled = true;
    Object.keys(next.phases).forEach(function (p) {
      next.phases[p] = "skipped";
    });
    next.welcome_seen = true;
    setState(next);
    setWelcomeSeenCookie();
    setIdleCookie();
    if (window.ccTourAnalytics) {
      window.ccTourAnalytics.tourSkipped("welcome", 0, "all", next.welcome_path);
    }
  }

  function markPhase(phase, status) {
    var next = getState();
    if (next.phases[phase] !== undefined) {
      next.phases[phase] = status;
    }
    setState(next);
  }

  function scheduleSync() {
    if (!cfg.isLoggedIn || !cfg.restUrl || !cfg.restNonce) return;
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(function () {
      fetch(cfg.restUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": cfg.restNonce,
        },
        body: JSON.stringify({
          state: getState(),
          merge_guest: !!cfg.mergeOnLogin,
        }),
      }).catch(function () {});
    }, 600);
  }

  function pullServerState() {
    if (!cfg.isLoggedIn || !cfg.restUrl || !cfg.restNonce) {
      return Promise.resolve();
    }
    return fetch(cfg.restUrl, {
      credentials: "same-origin",
      headers: { "X-WP-Nonce": cfg.restNonce },
    })
      .then(function (res) {
        return res.ok ? res.json() : null;
      })
      .then(function (data) {
        if (data && data.state) {
          if (cfg.mergeOnLogin && readStorage()) {
            var guest = normalizeState(readStorage());
            var server = normalizeState(data.state);
            Object.keys(server.phases).forEach(function (phase) {
              if (
                server.phases[phase] === "pending" &&
                guest.phases[phase] !== "pending"
              ) {
                server.phases[phase] = guest.phases[phase];
              }
            });
            if (guest.welcome_seen) server.welcome_seen = true;
            if (guest.welcome_path) server.welcome_path = guest.welcome_path;
            if (guest.global_disabled) server.global_disabled = true;
            state = server;
          } else {
            state = normalizeState(data.state);
          }
          writeStorage(state);
        }
      })
      .catch(function () {});
  }

  function sessionFlags() {
    try {
      var raw = sessionStorage.getItem(SESSION_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (_e) {
      return {};
    }
  }

  function setSessionFlag(key, value) {
    var flags = sessionFlags();
    flags[key] = value;
    try {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(flags));
    } catch (_e2) {
      /* ignore */
    }
  }

  function hasPageTourShownInSession(phase) {
    var flags = sessionFlags();
    return !!(flags.pageToursShown && flags.pageToursShown[phase]);
  }

  function markPageTourShownInSession(phase) {
    var flags = sessionFlags();
    var shown =
      flags.pageToursShown && typeof flags.pageToursShown === "object"
        ? flags.pageToursShown
        : {};
    shown[phase] = true;
    setSessionFlag("pageToursShown", shown);
  }

  function prefersReducedMotion() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function showWishlistAccountToast() {
    var accountUrl = cfg.accountUrl || "";
    var notice = document.querySelector(".cc-wishlist-toast");
    if (!notice) {
      notice = document.createElement("div");
      notice.className = "cc-wishlist-toast";
      notice.setAttribute("role", "status");
      notice.setAttribute("aria-live", "polite");
      document.body.appendChild(notice);
    }

    if (accountUrl) {
      notice.innerHTML =
        'Saved to wishlist. <a class="cc-wishlist-toast__link" href="' +
        accountUrl +
        '">Open My Account → Saved products</a>';
    } else {
      notice.textContent =
        "Saved to wishlist. Open My Account → Saved products to reorder.";
    }

    notice.classList.add("is-visible");
    clearTimeout(showWishlistAccountToast.timer);
    showWishlistAccountToast.timer = window.setTimeout(function () {
      notice.classList.remove("is-visible");
    }, 5000);
  }

  function createDriverInstance(onSkipAll, phase, options) {
    var driverFactory =
      window.driver && window.driver.js && window.driver.js.driver;
    if (!driverFactory) return null;

    var str = cfg.strings || {};
    options = options || {};

    var instance = driverFactory({
      animate: !prefersReducedMotion(),
      allowClose: true,
      disableActiveInteraction: true,
      overlayColor: "rgba(15, 39, 64, 0.72)",
      overlayOpacity: 0.72,
      smoothScroll: true,
      stagePadding: 8,
      stageRadius: 12,
      popoverOffset: 12,
      popoverClass: "cc-driver-popover",
      showProgress: true,
      progressText: str.progress || "{{current}} / {{total}}",
      nextBtnText: str.next || "Next",
      prevBtnText: str.back || "Back",
      doneBtnText: str.done || "Done",
      showButtons: ["next", "previous", "close"],
      onDestroyed: function () {
        document.documentElement.classList.remove("cc-tour-is-active");
        document.body.classList.remove("cc-tour-is-active");
        activeTourId = "";
        activeTourTotal = 0;
      },
      onNextClick: function () {
        var idx = instance.getActiveIndex();
        if (typeof idx !== "number") {
          return;
        }
        if (idx < activeTourTotal - 1) {
          instance.moveNext();
          return;
        }
        completeTour(phase, instance, "done", options);
      },
      onCloseClick: function () {
        completeTour(phase, instance, "skipped", options);
      },
      onPopoverRender: function (popover) {
        if (popover.closeButton) {
          popover.closeButton.textContent = str.skip || "Skip";
          popover.closeButton.classList.add("cc-driver-skip-btn");
          popover.closeButton.setAttribute("aria-label", str.skip || "Skip");
        }
      },
      onHighlighted: function (element) {
        if (phase !== "home" || !element || !element.scrollIntoView) {
          return;
        }
        element.scrollIntoView({
          block: "center",
          behavior: prefersReducedMotion() ? "auto" : "smooth",
        });
        if (phase === "home") {
          var state = getState();
          if (state && state.welcome_path === "search") {
            var input = element.querySelector(
              '.search-input, .mobile-search-input, [data-cc-tour="header-search"]',
            );
            if (!input && element.matches && element.matches("[data-cc-tour='header-search'], .search-input, .mobile-search-input")) {
              input = element;
            }
            if (input && typeof input.focus === "function") {
              input.focus();
            }
          }
        }
      },
    });

    return instance;
  }

  function completeTour(phase, driverInstance, outcome, options) {
    options = options || {};
    var tourPath = getState().welcome_path;
    var idx =
      typeof driverInstance.getActiveIndex === "function"
        ? driverInstance.getActiveIndex()
        : 0;

    if (outcome === "done") {
      markPhase(phase, "done");
      if (window.ccTourAnalytics) {
        window.ccTourAnalytics.tourCompleted(
          phase,
          activeTourTotal,
          Date.now() - tourStartedAt,
          tourPath,
        );
      }
      if (phase === "wishlist" && options.showAccountToast) {
        showWishlistAccountToast();
      }
    } else {
      markPhase(phase, "skipped");
      if (window.ccTourAnalytics) {
        window.ccTourAnalytics.tourSkipped(
          phase,
          (typeof idx === "number" ? idx : 0) + 1,
          "step",
          tourPath,
        );
      }
    }

    if (phase !== "wishlist") {
      markPageTourShownInSession(phase);
    }

    driverInstance.destroy();
  }

  function shouldAutoStartPhase(phase) {
    var s = getState();
    if (s.global_disabled || s.phases[phase] !== "pending") return false;

    if (hasPageTourShownInSession(phase)) return false;

    if (phase === "cart" && (!cfg.cartCount || cfg.cartCount < 1)) return false;
    if (phase === "account" && !cfg.isLoggedIn) return false;
    if (
      phase === "home" &&
      s.welcome_path !== "specialty" &&
      s.welcome_path !== "categories" &&
      s.welcome_path !== "search"
    ) {
      return false;
    }
    return true;
  }

  function scrollShopFilterBarIntoView() {
    var bar = document.querySelector(".fp-filter-bar.ap-filter-bar");
    if (bar && bar.scrollIntoView) {
      bar.scrollIntoView({
        block: "center",
        behavior: prefersReducedMotion() ? "auto" : "smooth",
      });
    }
  }

  function shopTourDomReady() {
    return !!(
      document.querySelector('[data-cc-tour="shop-filter-bar"]') ||
      document.querySelector(".fp-filter-bar-left") ||
      document.querySelector(".fp-filter-bar.ap-filter-bar")
    );
  }

  function runTour(phase, options) {
    options = options || {};
    if (!window.ccTourPhases) return false;

    if (phase === "shop") {
      scrollShopFilterBarIntoView();
    }

    var steps = window.ccTourPhases.getStepsForPhase(phase, options);
    if (!steps.length) {
      if (phase !== "welcome") {
        if (!(phase === "shop" && shopTourDomReady())) {
          markPhase(phase, "skipped");
        }
      }
      return false;
    }

    var driver = createDriverInstance(null, phase, options);
    if (!driver) return false;

    activeTourId = phase;
    tourStartedAt = Date.now();
    var tourPath = getState().welcome_path;
    activeTourTotal = steps.length;
    document.documentElement.classList.add("cc-tour-is-active");
    document.body.classList.add("cc-tour-is-active");

    if (window.ccTourAnalytics) {
      window.ccTourAnalytics.tourStarted(phase, activeTourTotal, tourPath);
    }

    driver.setSteps(
      steps.map(function (s) {
        return {
          element: s.element,
          popover: s.popover,
        };
      }),
    );

    driver.drive(0);

    if (phase !== "wishlist" && driver.isActive()) {
      markPageTourShownInSession(phase);
    }

    var stepIndex = 0;
    var checkStep = function () {
      var idx = driver.getActiveIndex();
      if (typeof idx === "number" && idx !== stepIndex) {
        stepIndex = idx;
        if (window.ccTourAnalytics) {
          window.ccTourAnalytics.stepViewed(
            phase,
            idx + 1,
            activeTourTotal,
            tourPath,
          );
        }
      }
      if (driver.isActive()) {
        window.requestAnimationFrame(checkStep);
      }
    };
    if (window.ccTourAnalytics) {
      window.ccTourAnalytics.stepViewed(phase, 1, activeTourTotal, tourPath);
    }
    window.requestAnimationFrame(checkStep);

    return true;
  }

  function isAccountPage() {
    return (
      document.body.classList.contains("page-my-account") ||
      !!document.getElementById("wishlist-grid")
    );
  }

  function runWishlistMicroTour() {
    var s = getState();
    if (s.global_disabled || s.phases.wishlist !== "pending") return;

    var onAccount = isAccountPage();
    setTimeout(function () {
      runTour("wishlist", {
        onAccountPage: onAccount,
        pdpOnly: !onAccount,
        showAccountToast: !onAccount,
      });
    }, 400);
  }

  /**
   * Legacy: shop was marked skipped when step build failed; allow one retry per session.
   */
  function reviveShopTourIfAutoSkipped() {
    if (cfg.phase !== "shop" || !cfg.driverEnabled) return;
    var s = getState();
    if (s.phases.shop !== "skipped") return;
    var flags = sessionFlags();
    if (flags.pageToursShown && flags.pageToursShown.shop) return;
    if (!shopTourDomReady()) return;
    var next = getState();
    next.phases.shop = "pending";
    setState(next);
  }

  function initPageTour() {
    if (!cfg.driverEnabled || getState().global_disabled) return;
    if (cfg.welcomePending) {
      if (window.__ccWelcomeShownThisLoad) {
        return;
      }
      return;
    }

    var phase = cfg.phase;
    if (!phase || !shouldAutoStartPhase(phase)) return;

    var delay = cfg.startDelayMs || 400;
    var shopRetries = 0;
    var maxShopRetries = 12;

    function attemptRun() {
      if (!shouldAutoStartPhase(phase)) return;

      if (phase === "shop" && !window.ccTourPhases.getStepsForPhase("shop").length) {
        if (shopTourDomReady() && shopRetries < maxShopRetries) {
          shopRetries += 1;
          setTimeout(attemptRun, 250);
          return;
        }
      }

      runTour(phase);
    }

    function schedule() {
      setTimeout(attemptRun, delay);
    }

    if (phase === "shop") {
      delay = Math.max(delay, 600);
      var startShop = function () {
        requestAnimationFrame(function () {
          requestAnimationFrame(schedule);
        });
      };
      if (document.readyState === "complete") {
        startShop();
      } else {
        window.addEventListener("load", startShop, { once: true });
      }
      return;
    }

    schedule();
  }

  function initThankyou() {
    if (!cfg.thankyouPage || !window.ccTourAnalytics) return;
    var orderId = cfg.orderId || "";
    if (orderId) {
      window.ccTourAnalytics.tryFireOrderAfterTour(String(orderId));
    }
  }

  function bindWishlistEvent() {
    document.addEventListener("cc:wishlist-saved", function () {
      var s = getState();
      if (s.global_disabled || s.phases.wishlist !== "pending") return;
      runWishlistMicroTour();
    });
  }

  function bindCartAttribution() {
    document.addEventListener(
      "click",
      function (e) {
        var btn = e.target.closest(
          ".btn-add-cart, .oow-btn-add-cart, .sp-btn-cart, .single_add_to_cart_button",
        );
        if (!btn || !window.ccTourAnalytics) return;
        var pid =
          btn.getAttribute("data-product-id") ||
          btn.value ||
          btn.getAttribute("data-cc-wishlist-id");
        window.ccTourAnalytics.tryFireCartAfterTour(pid);
      },
      true,
    );
  }

  window.ccTourCore = {
    getState: getState,
    setState: setState,
    globalDisable: globalDisable,
    markPhase: markPhase,
    runTour: runTour,
    setSessionFlag: setSessionFlag,
    sessionFlags: sessionFlags,
  };

  pullServerState().finally(function () {
    if (getState().global_disabled) return;

    initThankyou();
    bindWishlistEvent();
    bindCartAttribution();

    if (cfg.driverEnabled) {
      reviveShopTourIfAutoSkipped();
      initPageTour();
    }
  });
})(window, document);
