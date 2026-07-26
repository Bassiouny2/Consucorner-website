/**
 * ConsuCorner Tours v2 — Welcome modal (custom CSS, no Driver.js).
 */
(function (window, document) {
  "use strict";

  var cfg = window.ccProductTour || {};

  function getState() {
    if (window.ccTourCore) return window.ccTourCore.getState();
    try {
      return JSON.parse(localStorage.getItem(cfg.storageKey || "cc_site_tours_v2"));
    } catch (_e) {
      return null;
    }
  }

  function saveState(mutator) {
    var base = getState() || {
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
    };
    mutator(base);
    if (window.ccTourCore) {
      window.ccTourCore.setState(base);
    } else {
      localStorage.setItem(cfg.storageKey || "cc_site_tours_v2", JSON.stringify(base));
    }
  }

  function setCookie(name) {
    document.cookie = name + "=1; path=/; max-age=2592000; SameSite=Lax";
  }

  function setWelcomeSeenCookie() {
    setCookie(cfg.welcomeSeenCookie || "cc_tours_welcome_seen");
  }

  function markWelcomeShown(path) {
    setWelcomeSeenCookie();
    saveState(function (s) {
      s.welcome_seen = true;
      if (path) {
        s.welcome_path = path;
      }
    });
    window.__ccWelcomeShownThisLoad = true;
  }

  function buildModal() {
    var str = (cfg.strings && cfg.strings.welcome) || {};
    var root = document.createElement("div");
    root.className = "cc-welcome-modal";
    root.setAttribute("role", "dialog");
    root.setAttribute("aria-modal", "true");
    root.setAttribute("aria-labelledby", "cc-welcome-modal-title");

    root.innerHTML =
      '<div class="cc-welcome-modal__backdrop" data-cc-welcome-dismiss></div>' +
      '<div class="cc-welcome-modal__card">' +
      '<h2 class="cc-welcome-modal__title" id="cc-welcome-modal-title"></h2>' +
      '<p class="cc-welcome-modal__subtitle"></p>' +
      '<div class="cc-welcome-modal__paths">' +
      '<button type="button" class="cc-welcome-modal__path cc-welcome-modal__path--primary" data-path="specialty"></button>' +
      '<button type="button" class="cc-welcome-modal__path cc-welcome-modal__path--primary" data-path="search"></button>' +
      '<button type="button" class="cc-welcome-modal__path cc-welcome-modal__path--secondary" data-path="categories"></button>' +
      "</div>" +
      '<footer class="cc-welcome-modal__footer">' +
      '<button type="button" class="cc-welcome-modal__skip-all" data-action="skip-all"></button>' +
      "</footer>" +
      "</div>";

    root.querySelector(".cc-welcome-modal__title").textContent =
      str.title || "Welcome to ConsuCorner";
    root.querySelector(".cc-welcome-modal__subtitle").textContent =
      str.subtitle || "";
    root.querySelector('[data-path="specialty"]').textContent =
      (str.paths && str.paths.specialty) || "Browse by specialty";
    root.querySelector('[data-path="search"]').textContent =
      (str.paths && str.paths.search) || "Search products";
    root.querySelector('[data-path="categories"]').textContent =
      (str.paths && str.paths.categories) || "Explore categories";
    root.querySelector('[data-action="skip-all"]').textContent =
      str.skipAll || "Skip";

    return root;
  }

  function scrollToSection(selector) {
    var el = document.querySelector(selector);
    if (el && el.scrollIntoView) {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function navigateSpecialty() {
    if (document.querySelector(".browse-categories-carousel")) {
      scrollToSection(".browse-categories-carousel");
      return;
    }
    window.location.href = cfg.homeUrl || "/";
  }

  function navigateCategories() {
    if (document.querySelector(".popular-categories")) {
      scrollToSection(".popular-categories");
      return;
    }
    window.location.href = cfg.homeUrl || "/";
  }

  /**
   * Run the home Driver tour after Welcome (same UX as specialty path on reload).
   *
   * @param {string} path welcome_path value.
   */
  function startHomeTourAfterWelcome(path) {
    if (path !== "specialty" && path !== "categories" && path !== "search") {
      return;
    }
    if (cfg.phase !== "home") {
      return;
    }

    function tryStart() {
      if (!window.ccTourCore || !window.ccTourPhases) {
        return false;
      }
      var state = window.ccTourCore.getState();
      if (!state || state.global_disabled || state.phases.home !== "pending") {
        return true;
      }
      var delay = Math.max(Number(cfg.startDelayMs) || 400, 600);
      window.setTimeout(function () {
        window.ccTourCore.runTour("home");
      }, delay);
      return true;
    }

    if (tryStart()) {
      return;
    }

    var attempts = 0;
    var timer = window.setInterval(function () {
      if (tryStart() || ++attempts > 50) {
        window.clearInterval(timer);
      }
    }, 100);
  }

  function openModal() {
    var state = getState();
    if (!cfg.welcomePending) return;
    if (state && state.global_disabled) return;
    if (state && state.welcome_seen) return;

    var modal = buildModal();
    document.body.appendChild(modal);
    document.body.classList.add("cc-welcome-modal-open");

    window.__ccWelcomeShownThisLoad = true;

    if (window.ccTourAnalytics) {
      window.ccTourAnalytics.tourStarted("welcome", 0, null);
    }

    function close() {
      modal.remove();
      document.body.classList.remove("cc-welcome-modal-open");
    }

    modal.addEventListener("click", function (e) {
      var pathBtn = e.target.closest("[data-path]");
      if (pathBtn) {
        var path = pathBtn.getAttribute("data-path");
        markWelcomeShown(path);
        close();
        if (path === "specialty") {
          navigateSpecialty();
          startHomeTourAfterWelcome(path);
        } else if (path === "search") {
          startHomeTourAfterWelcome(path);
        } else if (path === "categories") {
          navigateCategories();
          startHomeTourAfterWelcome(path);
        }
        return;
      }

      if (e.target.closest('[data-action="skip-all"]')) {
        markWelcomeShown(null);
        close();
        return;
      }

      if (e.target.closest("[data-cc-welcome-dismiss]")) {
        markWelcomeShown(null);
        close();
      }
    });
  }

  function initWelcome() {
    var state = getState();
    if (state && state.welcome_seen) {
      setWelcomeSeenCookie();
      return;
    }
    if (!cfg.welcomePending) {
      return;
    }
    openModal();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWelcome);
  } else {
    initWelcome();
  }
})(window, document);
