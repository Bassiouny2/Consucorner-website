(function () {
  var wrapper = document.querySelector('.testimonials-slider-wrapper');
  var track   = document.querySelector('.testimonials-track');
  if (!track || !wrapper) return;

  var TRANS_MS     = 500;
  var AUTO_DELAY   = 4000;
  var moving       = false;
  var autoTimer    = null;

  function getStep() {
    var firstCard = track.querySelector('.review-card');
    if (!firstCard) return 311;
    var cardWidth = firstCard.getBoundingClientRect().width;
    var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap || 16);
    if (isNaN(gap)) gap = 16;
    return Math.round(cardWidth + gap);
  }

  /* ── helpers ─────────────────────────────────────── */
  function setTransition(on) {
    if (on) {
      track.classList.remove('no-transition');
    } else {
      track.classList.add('no-transition');
    }
  }

  function shiftNext() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(true);
    track.style.transform = 'translateX(-' + step + 'px)';

    setTimeout(function () {
      setTransition(false);
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      moving = false;
    }, TRANS_MS);
  }

  function shiftPrev() {
    if (moving) return;
    moving = true;
    var step = getStep();
    setTransition(false);
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = 'translateX(-' + step + 'px)';
    void track.offsetWidth;

    setTransition(true);
    track.style.transform = 'translateX(0)';

    setTimeout(function () {
      moving = false;
    }, TRANS_MS);
  }

  /* ── auto-scroll ──────────────────────────────────── */
  function startAuto() {
    stopAuto();
    autoTimer = setInterval(shiftNext, AUTO_DELAY);
  }

  function stopAuto() {
    if (autoTimer) {
      clearInterval(autoTimer);
      autoTimer = null;
    }
  }

  startAuto();

  /* ── mouse drag ───────────────────────────────────── */
  var dragStartX   = 0;
  var isDragging   = false;
  var dragMoved    = false;
  var DRAG_THRESH  = 50; // px needed to count as a swipe

  wrapper.addEventListener('mousedown', function (e) {
    stopAuto();
    isDragging  = true;
    dragMoved   = false;
    dragStartX  = e.clientX;
    wrapper.classList.add('dragging');
  });

  window.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    if (Math.abs(e.clientX - dragStartX) > 5) dragMoved = true;
  });

  window.addEventListener('mouseup', function (e) {
    if (!isDragging) return;
    isDragging = false;
    wrapper.classList.remove('dragging');

    if (dragMoved) {
      var diff = e.clientX - dragStartX;
      if (diff < -DRAG_THRESH) shiftNext();
      else if (diff > DRAG_THRESH) shiftPrev();
    }

    startAuto();
  });

  /* prevent clicks on cards during a drag */
  wrapper.addEventListener('click', function (e) {
    if (dragMoved) e.preventDefault();
  });

  /* ── touch swipe ──────────────────────────────────── */
  var touchStartX = 0;

  wrapper.addEventListener('touchstart', function (e) {
    stopAuto();
    touchStartX = e.touches[0].clientX;
  }, { passive: true });

  wrapper.addEventListener('touchend', function (e) {
    var diff = e.changedTouches[0].clientX - touchStartX;
    if (diff < -DRAG_THRESH) shiftNext();
    else if (diff > DRAG_THRESH) shiftPrev();
    startAuto();
  }, { passive: true });

  /* pause on hover */
  wrapper.addEventListener('mouseenter', stopAuto);
  wrapper.addEventListener('mouseleave', startAuto);
})();
