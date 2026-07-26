/**
 * ConsuCorner — checkout help chip (no Driver.js).
 */
(function (window, document) {
  "use strict";

  var cfg = window.ccCheckoutHelp || {};
  var strings = cfg.strings || {};

  function init() {
    var mount = document.querySelector("[data-cc-checkout-help]");
    if (!mount) return;

    var toggle = mount.querySelector(".cc-checkout-help__toggle");
    var panel = mount.querySelector(".cc-checkout-help__panel");
    if (!toggle || !panel) return;

    toggle.addEventListener("click", function () {
      var open = mount.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      panel.hidden = !open;
      if (open && window.ccTourAnalytics) {
        window.ccTourAnalytics.checkoutHelpOpened();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
