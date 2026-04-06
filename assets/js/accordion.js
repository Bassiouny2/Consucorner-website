(function () {
  function initAccordion(prefix) {
    var items = document.querySelectorAll('.' + prefix + '-item');
    if (!items.length) return;
    items.forEach(function (item) {
      var btn = item.querySelector('.' + prefix + '-header');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var isOpen = item.classList.contains(prefix + '-item--open');
        items.forEach(function (i) {
          i.classList.remove(prefix + '-item--open');
          i.querySelector('.' + prefix + '-header').setAttribute('aria-expanded', 'false');
          var path = i.querySelector('.' + prefix + '-toggle svg path');
          if (path) { path.setAttribute('d', 'M7 1V13M1 7H13'); path.setAttribute('stroke', '#00C8B3'); }
        });
        if (!isOpen) {
          item.classList.add(prefix + '-item--open');
          btn.setAttribute('aria-expanded', 'true');
          var path = item.querySelector('.' + prefix + '-toggle svg path');
          if (path) { path.setAttribute('d', 'M1 1L13 13M13 1L1 13'); path.setAttribute('stroke', 'white'); }
        }
      });
    });
  }

  initAccordion('blog-faq');
  initAccordion('sp-faq');
  initAccordion('vendor-faq');
})();
