(function () {
  var slider = document.querySelector('.bestsellers-slider');
  var track = document.querySelector('.bs-track');
  var prevBtn = document.querySelector('.bs-prev');
  var nextBtn = document.querySelector('.bs-next');

  if (!slider || !track || !prevBtn || !nextBtn) return;

  var TRANSITION_MS = 500;
  var AUTO_DELAY_MS = 3200;
  var DRAG_THRESHOLD = 40;
  var moving = false;
  var autoTimer = null;

  function getStep() {
    var firstCard = track.querySelector('.card-shop');
    if (!firstCard) return 258;
    var cardWidth = firstCard.getBoundingClientRect().width;
    var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap || 16);
    if (isNaN(gap)) gap = 16;
    return Math.round(cardWidth + gap);
  }

  function shiftNext() {
    if (moving || !track.firstElementChild) return;
    moving = true;
    var step = getStep();

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(-' + step + 'px)';

    setTimeout(function () {
      track.style.transition = 'none';
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      moving = false;
    }, TRANSITION_MS);
  }

  function shiftPrev() {
    if (moving || !track.lastElementChild) return;
    moving = true;
    var step = getStep();

    track.style.transition = 'none';
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = 'translateX(-' + step + 'px)';
    void track.offsetWidth;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(0)';

    setTimeout(function () {
      moving = false;
    }, TRANSITION_MS);
  }

  function stopAuto() {
    if (!autoTimer) return;
    clearInterval(autoTimer);
    autoTimer = null;
  }

  function startAuto() {
    stopAuto();
    autoTimer = setInterval(shiftNext, AUTO_DELAY_MS);
  }

  function restartAuto() {
    startAuto();
  }

  nextBtn.addEventListener('click', function () {
    shiftNext();
    restartAuto();
  });

  prevBtn.addEventListener('click', function () {
    shiftPrev();
    restartAuto();
  });

  /* Drag / Swipe support */
  var dragStartX = 0;
  var dragging = false;

  function onDragStart(x) {
    if (moving) return;
    dragging = true;
    dragStartX = x;
    slider.classList.add('dragging');
    stopAuto();
  }

  function onDragEnd(x) {
    if (!dragging) return;
    dragging = false;
    slider.classList.remove('dragging');

    var delta = x - dragStartX;
    if (Math.abs(delta) >= DRAG_THRESHOLD) {
      if (delta < 0) shiftNext();
      else shiftPrev();
    }
    restartAuto();
  }

  slider.addEventListener('mousedown', function (e) {
    onDragStart(e.clientX);
  });

  window.addEventListener('mouseup', function (e) {
    onDragEnd(e.clientX);
  });

  slider.addEventListener('touchstart', function (e) {
    if (!e.touches || !e.touches.length) return;
    onDragStart(e.touches[0].clientX);
  }, { passive: true });

  window.addEventListener('touchend', function (e) {
    if (!e.changedTouches || !e.changedTouches.length) return;
    onDragEnd(e.changedTouches[0].clientX);
  }, { passive: true });

  slider.addEventListener('mouseenter', stopAuto);
  slider.addEventListener('mouseleave', startAuto);

  startAuto();
})();
