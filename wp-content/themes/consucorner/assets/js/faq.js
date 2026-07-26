(function () {
  'use strict';

  var faqItems = Array.prototype.slice.call(document.querySelectorAll('.faq-item'));

  if (!faqItems.length) {
    return;
  }

  faqItems.forEach(function (item) {
    var button = item.querySelector('.faq-question');
    var answer = item.querySelector('.faq-answer');

    if (!button || !answer) {
      return;
    }

    button.addEventListener('click', function () {
      var isOpen = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      answer.hidden = isOpen;
    });
  });
})();
