(function () {
  var track = document.querySelector('.rec-track');
  var prevBtn = document.querySelector('.rec-prev');
  var nextBtn = document.querySelector('.rec-next');

  if (!track || !prevBtn || !nextBtn) return;

  var CARD_W = 242;
  var GAP = 16;
  var step = CARD_W + GAP;
  var TRANSITION_MS = 500;
  var moving = false;

  nextBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(-' + step + 'px)';

    setTimeout(function () {
      track.style.transition = 'none';
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      moving = false;
    }, TRANSITION_MS);
  });

  prevBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'none';
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = 'translateX(-' + step + 'px)';
    void track.offsetWidth;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(0)';

    setTimeout(function () {
      moving = false;
    }, TRANSITION_MS);
  });
})();

/* Popular Brands slider logic (same behavior as recommended-for-you) */
(function () {
  var track = document.querySelector('.popular-brands-track');
  var prevBtn = document.querySelector('.popular-brands-prev');
  var nextBtn = document.querySelector('.popular-brands-next');

  if (!track || !prevBtn || !nextBtn) return;

  var CARD_W = 220;
  var GAP = 16;
  var step = CARD_W + GAP;
  var TRANSITION_MS = 500;
  var moving = false;

  nextBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(-' + step + 'px)';

    setTimeout(function () {
      track.style.transition = 'none';
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      moving = false;
    }, TRANSITION_MS);
  });

  prevBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'none';
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = 'translateX(-' + step + 'px)';
    void track.offsetWidth;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(0)';

    setTimeout(function () {
      moving = false;
    }, TRANSITION_MS);
  });
})();

/* Country of Origin slider logic (same behavior as recommended-for-you and popular-brands) */
(function () {
  var track = document.querySelector('.country-origin-track');
  var prevBtn = document.querySelector('.country-origin-prev');
  var nextBtn = document.querySelector('.country-origin-next');

  if (!track || !prevBtn || !nextBtn) return;

  var CARD_W = 220;
  var GAP = 16;
  var step = CARD_W + GAP;
  var TRANSITION_MS = 500;
  var moving = false;

  nextBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(-' + step + 'px)';

    setTimeout(function () {
      track.style.transition = 'none';
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      moving = false;
    }, TRANSITION_MS);
  });

  prevBtn.addEventListener('click', function () {
    if (moving) return;
    moving = true;

    track.style.transition = 'none';
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = 'translateX(-' + step + 'px)';
    void track.offsetWidth;

    track.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
    track.style.transform = 'translateX(0)';

    setTimeout(function () {
      moving = false;
    }, TRANSITION_MS);
  });
})();
