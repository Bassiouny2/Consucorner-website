(function () {
  /* ── Often Ordered With Carousel (infinite loop) ── */
  var oowViewport = document.getElementById('oowViewport');
  var oowTrack    = document.getElementById('oowTrack');
  var oowPrev     = document.getElementById('oowPrev');
  var oowNext     = document.getElementById('oowNext');

  if (oowTrack && oowPrev && oowNext && oowViewport) {
    var oowOriginals = Array.prototype.slice.call(oowTrack.querySelectorAll('.oow-card'));
    var oowTotal     = oowOriginals.length;

    var OOW_GAP = 16;

    function oowGetVisible() {
      var w = window.innerWidth;
      if (w <= 480) return 2;
      if (w <= 1024) return 3;
      return 5;
    }

    /* Set card widths explicitly from viewport so % doesn't resolve against track */
    function oowSetWidths() {
      var visible    = oowGetVisible();
      var containerW = oowViewport.offsetWidth;
      var cardW      = (containerW - (visible - 1) * OOW_GAP) / visible;
      Array.prototype.slice.call(oowTrack.querySelectorAll('.oow-card')).forEach(function (c) {
        c.style.flex  = '0 0 ' + cardW + 'px';
        c.style.width = cardW + 'px';
      });
    }

    /* Clone all cards and append for seamless loop */
    oowOriginals.forEach(function (card) {
      oowTrack.appendChild(card.cloneNode(true));
    });

    var oowIndex = 0;

    function oowGetCardWidth() {
      var card = oowTrack.querySelector('.oow-card');
      return card ? card.offsetWidth + OOW_GAP : 0;
    }

    function oowSnap() {
      /* After transition ends, silently jump if in cloned zone */
      if (oowIndex >= oowTotal) {
        oowTrack.style.transition = 'none';
        oowIndex -= oowTotal;
        oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
        oowTrack.offsetHeight; /* force reflow */
        oowTrack.style.transition = '';
      }
      if (oowIndex < 0) {
        oowTrack.style.transition = 'none';
        oowIndex += oowTotal;
        oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
        oowTrack.offsetHeight;
        oowTrack.style.transition = '';
      }
    }

    function oowGoTo(index) {
      oowIndex = index;
      oowTrack.style.transform = 'translateX(-' + (oowIndex * oowGetCardWidth()) + 'px)';
      setTimeout(oowSnap, 420); /* run after CSS transition (400ms) */
    }

    function oowResetTimer() {
      clearInterval(oowTimer);
      oowTimer = setInterval(function () { oowGoTo(oowIndex + 1); }, 3500);
    }

    /* Init widths + resize */
    oowSetWidths();
    window.addEventListener('resize', function () { oowSetWidths(); oowGoTo(oowIndex); });

    /* Auto-play */
    var oowTimer = setInterval(function () { oowGoTo(oowIndex + 1); }, 3500);

    /* Pause on hover */
    oowViewport.addEventListener('mouseenter', function () { clearInterval(oowTimer); });
    oowViewport.addEventListener('mouseleave', oowResetTimer);

    /* Buttons — top nav */
    oowPrev.addEventListener('click', function () { oowGoTo(oowIndex - 1); oowResetTimer(); });
    oowNext.addEventListener('click', function () { oowGoTo(oowIndex + 1); oowResetTimer(); });

    /* Buttons — bottom nav (mobile) */
    var oowPrevBottom = document.getElementById('oowPrevBottom');
    var oowNextBottom = document.getElementById('oowNextBottom');
    if (oowPrevBottom) oowPrevBottom.addEventListener('click', function () { oowGoTo(oowIndex - 1); oowResetTimer(); });
    if (oowNextBottom) oowNextBottom.addEventListener('click', function () { oowGoTo(oowIndex + 1); oowResetTimer(); });

    /* ── Mouse drag ── */
    var oowDragStartX = 0;
    var oowDragging   = false;
    var oowDragMoved  = false;

    oowViewport.addEventListener('mousedown', function (e) {
      oowDragStartX = e.clientX;
      oowDragging   = true;
      oowDragMoved  = false;
      oowTrack.style.transition = 'none';
    });

    window.addEventListener('mousemove', function (e) {
      if (!oowDragging) return;
      if (Math.abs(e.clientX - oowDragStartX) > 5) oowDragMoved = true;
    });

    window.addEventListener('mouseup', function (e) {
      if (!oowDragging) return;
      oowDragging = false;
      oowTrack.style.transition = '';
      if (!oowDragMoved) return;
      var dx = e.clientX - oowDragStartX;
      if (Math.abs(dx) < 40) { oowSnap(); return; }
      oowGoTo(dx < 0 ? oowIndex + 1 : oowIndex - 1);
      oowResetTimer();
    });

    oowViewport.addEventListener('click', function (e) {
      if (oowDragMoved) e.stopPropagation();
    }, true);

    /* ── Touch swipe ── */
    var oowTouchX = 0;

    oowViewport.addEventListener('touchstart', function (e) {
      oowTouchX = e.touches[0].clientX;
    }, { passive: true });

    oowViewport.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - oowTouchX;
      if (Math.abs(dx) < 40) return;
      oowGoTo(dx < 0 ? oowIndex + 1 : oowIndex - 1);
      oowResetTimer();
    }, { passive: true });

    oowGoTo(0);

    /* OOW card click — navigate via data-href */
    oowTrack.addEventListener('click', function (e) {
      var card = e.target.closest('[data-href]');
      if (card) {
        window.location.href = card.dataset.href;
      }
    });
  }

  /* ── Quantity Selector ── */
  var qtyVal   = document.getElementById('spQtyVal');
  var qtyMinus = document.getElementById('spQtyMinus');
  var qtyPlus  = document.getElementById('spQtyPlus');

  if (qtyVal && qtyMinus && qtyPlus) {
    function getVal() {
      return parseInt(qtyVal.value, 10) || 1;
    }

    function setVal(n) {
      var v = Math.max(1, n);
      qtyVal.value = v;
      qtyMinus.disabled = v <= 1;
    }

    qtyMinus.addEventListener('click', function () { setVal(getVal() - 1); });
    qtyPlus.addEventListener('click',  function () { setVal(getVal() + 1); });

    /* Keyboard: up/down arrows */
    qtyVal.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowUp')   { e.preventDefault(); setVal(getVal() + 1); }
      if (e.key === 'ArrowDown') { e.preventDefault(); setVal(getVal() - 1); }
    });

    /* Typed number: sanitize on change */
    qtyVal.addEventListener('input', function () {
      var v = parseInt(qtyVal.value, 10);
      if (isNaN(v) || v < 1) qtyVal.value = 1;
      qtyMinus.disabled = parseInt(qtyVal.value, 10) <= 1;
    });

    /* On blur: clear empty field back to 1 */
    qtyVal.addEventListener('blur', function () {
      if (!qtyVal.value || parseInt(qtyVal.value, 10) < 1) setVal(1);
    });
  }

  /* ── Image Slider ── */
  var viewport = document.getElementById('spSliderViewport');
  var track    = document.getElementById('spSliderTrack');
  var dots     = document.querySelectorAll('.sp-dot');
  var prev     = document.getElementById('spPrev');
  var next     = document.getElementById('spNext');
  var total    = 4;
  var current  = 0;

  if (!viewport || !track || !prev || !next) return;

  function goTo(index) {
    current = (index + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    dots.forEach(function (d, i) {
      d.classList.toggle('sp-dot--active', i === current);
    });
  }

  prev.addEventListener('click', function () { goTo(current - 1); });
  next.addEventListener('click', function () { goTo(current + 1); });
  dots.forEach(function (d) {
    d.addEventListener('click', function () { goTo(+d.dataset.index); });
  });

  /* ── Auto-play Timer ── */
  var timer = setInterval(function () { goTo(current + 1); }, 3000);

  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function () { goTo(current + 1); }, 3000);
  }

  prev.addEventListener('click', resetTimer);
  next.addEventListener('click', resetTimer);
  dots.forEach(function (d) {
    d.addEventListener('click', resetTimer);
  });

  /* Pause on hover */
  viewport.addEventListener('mouseenter', function () { clearInterval(timer); });
  viewport.addEventListener('mouseleave', function () {
    timer = setInterval(function () { goTo(current + 1); }, 3000);
  });

  /* ── Touch / Swipe ── */
  var touchStartX = 0;
  var touchStartY = 0;
  var isDragging  = false;

  viewport.addEventListener('touchstart', function (e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isDragging  = true;
  }, { passive: true });

  viewport.addEventListener('touchmove', function (e) {
    if (!isDragging) return;
    var dx = e.touches[0].clientX - touchStartX;
    var dy = e.touches[0].clientY - touchStartY;
    if (Math.abs(dy) > Math.abs(dx)) {
      isDragging = false;
    }
  }, { passive: true });

  viewport.addEventListener('touchend', function (e) {
    if (!isDragging) return;
    isDragging = false;
    var dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) < 40) return;
    if (dx < 0) {
      goTo(current + 1); /* swipe left → next */
    } else {
      goTo(current - 1); /* swipe right → prev */
    }
  }, { passive: true });
})();

/* ── Product Testimonials Slider (sp-t-) ── */
(function () {
  var spTWrapper = document.getElementById('spTWrapper');
  var spTTrack   = document.getElementById('spTTrack');
  if (!spTTrack || !spTWrapper) return;

  var TRANS_MS   = 500;
  var AUTO_DELAY = 4000;
  var moving     = false;
  var autoTimer  = null;

  function getStep() {
    var firstCard = spTTrack.querySelector('.sp-t-card');
    if (!firstCard) return 311;
    var cardWidth = firstCard.getBoundingClientRect().width;
    var gap = parseFloat(window.getComputedStyle(spTTrack).columnGap || window.getComputedStyle(spTTrack).gap || 16);
    if (isNaN(gap)) gap = 16;
    return Math.round(cardWidth + gap);
  }

  function setTransition(on) {
    if (on) {
      spTTrack.classList.remove('sp-t-no-transition');
    } else {
      spTTrack.classList.add('sp-t-no-transition');
    }
  }

  function shiftNext() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(true);
    spTTrack.style.transform = 'translateX(-' + step + 'px)';
    setTimeout(function () {
      setTransition(false);
      spTTrack.appendChild(spTTrack.firstElementChild);
      spTTrack.style.transform = 'translateX(0)';
      void spTTrack.offsetWidth;
      moving = false;
    }, TRANS_MS);
  }

  function shiftPrev() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(false);
    spTTrack.insertBefore(spTTrack.lastElementChild, spTTrack.firstElementChild);
    spTTrack.style.transform = 'translateX(-' + step + 'px)';
    void spTTrack.offsetWidth;
    setTransition(true);
    spTTrack.style.transform = 'translateX(0)';
    setTimeout(function () { moving = false; }, TRANS_MS);
  }

  function startAuto() {
    stopAuto();
    autoTimer = setInterval(shiftNext, AUTO_DELAY);
  }

  function stopAuto() {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
  }

  startAuto();

  /* Mouse drag */
  var dragStartX  = 0;
  var isDragging  = false;
  var dragMoved   = false;
  var DRAG_THRESH = 50;

  spTWrapper.addEventListener('mousedown', function (e) {
    stopAuto();
    isDragging = true;
    dragMoved  = false;
    dragStartX = e.clientX;
    spTWrapper.classList.add('sp-t-dragging');
  });

  window.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    if (Math.abs(e.clientX - dragStartX) > 5) dragMoved = true;
  });

  window.addEventListener('mouseup', function (e) {
    if (!isDragging) return;
    isDragging = false;
    spTWrapper.classList.remove('sp-t-dragging');
    if (dragMoved) {
      var diff = e.clientX - dragStartX;
      if (diff < -DRAG_THRESH) shiftNext();
      else if (diff > DRAG_THRESH) shiftPrev();
    }
    startAuto();
  });

  spTWrapper.addEventListener('click', function (e) {
    if (dragMoved) e.preventDefault();
  });

  /* Touch swipe */
  var touchStartX = 0;

  spTWrapper.addEventListener('touchstart', function (e) {
    stopAuto();
    touchStartX = e.touches[0].clientX;
  }, { passive: true });

  spTWrapper.addEventListener('touchend', function (e) {
    var diff = e.changedTouches[0].clientX - touchStartX;
    if (diff < -DRAG_THRESH) shiftNext();
    else if (diff > DRAG_THRESH) shiftPrev();
    startAuto();
  }, { passive: true });

  /* Pause on hover */
  spTWrapper.addEventListener('mouseenter', stopAuto);
  spTWrapper.addEventListener('mouseleave', startAuto);

  /* ── FAQ Accordion ── */
  var faqItems = document.querySelectorAll('.sp-faq-item');
  faqItems.forEach(function (item) {
    var btn = item.querySelector('.sp-faq-header');
    btn.addEventListener('click', function () {
      var isOpen = item.classList.contains('sp-faq-item--open');
      faqItems.forEach(function (i) {
        i.classList.remove('sp-faq-item--open');
        i.querySelector('.sp-faq-header').setAttribute('aria-expanded', 'false');
        /* swap icon to plus */
        var svg = i.querySelector('.sp-faq-toggle svg path');
        if (svg) svg.setAttribute('d', 'M7 1V13M1 7H13');
        var path = i.querySelector('.sp-faq-toggle svg path');
        if (path) path.setAttribute('stroke', '#00C8B3');
      });
      if (!isOpen) {
        item.classList.add('sp-faq-item--open');
        btn.setAttribute('aria-expanded', 'true');
        /* swap icon to × */
        var path = item.querySelector('.sp-faq-toggle svg path');
        if (path) {
          path.setAttribute('d', 'M1 1L13 13M13 1L1 13');
          path.setAttribute('stroke', 'white');
        }
      }
    });
  });

  /* ── Dynamic page title ── */
  var spTitleEl = document.querySelector('.sp-title');
  if (spTitleEl) {
    document.title = spTitleEl.textContent.trim() + ' - ConsuCorner';
  }

})();
