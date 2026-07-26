/**
 * vendor.js
 * Handles the FAQ accordion on the Vendor page
 * and the promo banner slider on the Privacy Policy page.
 */

/* ── FAQ Accordion ────────────────────────────────────────── */
(function () {
  var items = document.querySelectorAll('.vendor-faq-item');
  if (!items.length) return;

  items.forEach(function (item) {
    var btn  = item.querySelector('.vendor-faq-header');
    var body = item.querySelector('.vendor-faq-body');
    if (!btn || !body) return;

    // Set initial max-height on the open item.
    if (item.classList.contains('vendor-faq-item--open')) {
      body.style.maxHeight = body.scrollHeight + 'px';
    }

    btn.addEventListener('click', function () {
      var isOpen = item.classList.contains('vendor-faq-item--open');

      // Close all items.
      items.forEach(function (el) {
        el.classList.remove('vendor-faq-item--open');
        var b = el.querySelector('.vendor-faq-body');
        var h = el.querySelector('.vendor-faq-header');
        if (b) b.style.maxHeight = null;
        if (h) h.setAttribute('aria-expanded', 'false');

        // Swap icon to +
        var toggle = el.querySelector('.vendor-faq-toggle');
        if (toggle) {
          toggle.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">' +
            '<path d="M7 1V13M1 7H13" stroke="#00C8B3" stroke-width="2" stroke-linecap="round"/>' +
            '</svg>';
        }
      });

      // If it was closed, open it.
      if (!isOpen) {
        item.classList.add('vendor-faq-item--open');
        body.style.maxHeight = body.scrollHeight + 'px';
        btn.setAttribute('aria-expanded', 'true');

        var toggle = btn.querySelector('.vendor-faq-toggle');
        if (toggle) {
          toggle.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">' +
            '<path d="M1 1L13 13M13 1L1 13" stroke="white" stroke-width="2" stroke-linecap="round"/>' +
            '</svg>';
        }
      }
    });
  });
}());

/* ── Privacy Policy Banner Slider ─────────────────────────── */
(function () {
  var banner = document.querySelector('.pp-banner');
  if (!banner) return;

  var slides = banner.querySelectorAll('.pp-banner-slide');
  if (slides.length < 2) return;

  var current = 0;
  var total   = slides.length;

  function goTo(index) {
    slides[current].classList.remove('pp-banner-slide--active');
    current = (index + total) % total;
    slides[current].classList.add('pp-banner-slide--active');
  }

  // Auto-advance every 4 seconds.
  setInterval(function () { goTo(current + 1); }, 4000);
}());
