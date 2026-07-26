(function () {
  "use strict";

  var drawer = document.getElementById("mobile-drawer");
  var overlay = document.getElementById("drawer-overlay");
  var hamburger = document.getElementById("hamburger-btn");
  var closeBtn = document.getElementById("drawer-close-btn");

  if (!drawer || !overlay || !hamburger || !closeBtn || drawer.dataset.drawerReady === "true") {
    return;
  }

  drawer.dataset.drawerReady = "true";

  function openDrawer() {
    drawer.classList.add("open");
    overlay.classList.add("active");
    drawer.setAttribute("aria-hidden", "false");
    hamburger.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";
  }

  function closeDrawer() {
    drawer.classList.remove("open");
    overlay.classList.remove("active");
    drawer.setAttribute("aria-hidden", "true");
    hamburger.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }

  hamburger.setAttribute("aria-expanded", "false");
  hamburger.addEventListener("click", openDrawer);
  closeBtn.addEventListener("click", closeDrawer);
  overlay.addEventListener("click", closeDrawer);

  drawer.querySelectorAll(".drawer-tab").forEach(function (tab) {
    tab.addEventListener("click", function () {
      // If the tab is already active and has a URL, redirect to it.
      // This allows users to double-tap or tap an already active tab to visit its main page.
      if (tab.classList.contains("active")) {
        var url = tab.getAttribute("data-url");
        if (url) {
          window.location.href = url;
        }
        return;
      }

      var target = tab.getAttribute("data-tab");
      var tabs = drawer.querySelectorAll(".drawer-tab");
      var panels = drawer.querySelectorAll(".drawer-content");

      tabs.forEach(function (item) {
        item.classList.remove("active");
      });
      tab.classList.add("active");

      panels.forEach(function (panel) {
        if (panel.getAttribute("data-content") === target) {
          panel.classList.remove("hidden");
        } else {
          panel.classList.add("hidden");
        }
      });
    });
  });

  drawer.querySelectorAll(".drawer-accordion-toggle").forEach(function (button) {
    button.addEventListener("click", function () {
      var panel = button.nextElementSibling;

      if (!panel || !panel.classList.contains("drawer-accordion-panel")) {
        return;
      }

      var isOpen = panel.classList.contains("open");
      panel.classList.toggle("open", !isOpen);
      button.classList.toggle("active", !isOpen);
      button.setAttribute("aria-expanded", String(!isOpen));
    });
  });

  drawer.querySelectorAll(".cc-slider-arrow").forEach(function (button) {
    button.addEventListener("click", function () {
      var section = button.closest ? button.closest(".cc-section") : null;
      var slider = section ? section.querySelector(".cc-slider") : null;

      if (!slider) return;

      var direction = button.classList.contains("cc-slider-arrow--prev")
        ? -1
        : 1;

      slider.scrollBy({
        left: direction * slider.clientWidth,
        behavior: "smooth",
      });
    });
  });
})();
