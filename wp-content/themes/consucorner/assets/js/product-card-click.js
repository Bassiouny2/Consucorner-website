/**
 * product-card-click.js
 *
 * Makes any `.card-shop[data-href]` clickable as a whole — clicking anywhere
 * on the card (except an interactive child like a link, button, or form input)
 * navigates to its data-href URL.
 *
 * Used on the home page (Browse Specialty, Bestsellers, Recommended) so that
 * clicking the card or the product image opens the single-product page, while
 * Add-to-cart / Save / View buttons keep their original behavior.
 */
(function () {
  "use strict";

  document.addEventListener("click", function (event) {
    var card = event.target.closest(".card-shop[data-href]");
    if (!card) {
      return;
    }

    // If the user clicked an interactive element inside the card,
    // let that element handle the event normally.
    if (event.target.closest("a, button, input, select, textarea, label")) {
      return;
    }

    var href = card.getAttribute("data-href");
    if (!href) {
      return;
    }

    // Open in a new tab on Ctrl/Cmd-click or middle-click.
    if (event.ctrlKey || event.metaKey || event.button === 1) {
      window.open(href, "_blank", "noopener");
    } else {
      window.location.href = href;
    }
  });

  // Keyboard support: Enter on focused card.
  document.addEventListener("keydown", function (event) {
    if (event.key !== "Enter") {
      return;
    }
    var card = event.target.closest(".card-shop[data-href]");
    if (!card || event.target !== card) {
      return;
    }
    var href = card.getAttribute("data-href");
    if (href) {
      window.location.href = href;
    }
  });
})();
