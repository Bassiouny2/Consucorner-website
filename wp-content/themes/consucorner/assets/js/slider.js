(function () {
  /* ─────────────────────────────────────────────────────────
     HERO BANNER SLIDER (homepage — runs independently)
  ──────────────────────────────────────────────────────────── */
  var heroBanner = document.querySelector(".hero-section .hero-banner");
  if (heroBanner) {
    var heroSlides = heroBanner.querySelectorAll(".hero-slide");
    if (heroSlides.length > 1) {
      var heroIndex = 0;
      var heroAutoTimer = null;
      var heroUserInteracted = false;
      var HERO_AUTO_MS = 4500;
      var HERO_SWIPE_MIN = 40;

      function showHeroSlide(index) {
        heroIndex = (index + heroSlides.length) % heroSlides.length;
        heroSlides.forEach(function (slide, i) {
          var isActive = i === heroIndex;
          slide.classList.toggle("active", isActive);
          slide.setAttribute("aria-hidden", isActive ? "false" : "true");
          var bannerBtn = slide.querySelector(".banner-btn");
          if (bannerBtn) {
            bannerBtn.setAttribute("tabindex", isActive ? "0" : "-1");
          }
        });

        var currentEl = heroBanner.querySelector("[data-hero-banner-current]");
        var progressEl = heroBanner.querySelector("[data-hero-banner-progress]");
        if (currentEl) {
          currentEl.textContent = String(heroIndex + 1).padStart(2, "0");
        }
        if (progressEl) {
          progressEl.style.width =
            ((heroIndex + 1) / heroSlides.length) * 100 + "%";
        }
      }

      function heroNext() {
        showHeroSlide(heroIndex + 1);
      }

      function heroPrev() {
        showHeroSlide(heroIndex - 1);
      }

      function stopHeroAuto() {
        clearInterval(heroAutoTimer);
        heroAutoTimer = null;
      }

      function startHeroAuto() {
        stopHeroAuto();
        if (heroUserInteracted) return;
        heroAutoTimer = setInterval(heroNext, HERO_AUTO_MS);
      }

      function onHeroUserNav() {
        heroUserInteracted = true;
        stopHeroAuto();
      }

      var heroPrevBtn = heroBanner.querySelector("[data-hero-banner-prev]");
      var heroNextBtn = heroBanner.querySelector("[data-hero-banner-next]");

      if (heroPrevBtn) {
        heroPrevBtn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          onHeroUserNav();
          heroPrev();
        });
      }

      if (heroNextBtn) {
        heroNextBtn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          onHeroUserNav();
          heroNext();
        });
      }

      var heroTouchStartX = 0;
      heroBanner.addEventListener(
        "touchstart",
        function (e) {
          if (!e.touches || !e.touches.length) return;
          heroTouchStartX = e.touches[0].clientX;
        },
        { passive: true }
      );

      heroBanner.addEventListener(
        "touchend",
        function (e) {
          if (!e.changedTouches || !e.changedTouches.length) return;
          var delta = heroTouchStartX - e.changedTouches[0].clientX;
          if (Math.abs(delta) < HERO_SWIPE_MIN) return;
          onHeroUserNav();
          if (delta > 0) heroNext();
          else heroPrev();
        },
        { passive: true }
      );

      showHeroSlide(0);
      startHeroAuto();
    }
  }

  var section  = document.querySelector(".popular-categories");
  var slider   = document.querySelector(".categories-slider");
  var track    = document.querySelector(".slider-track");
  var prevBtn  = document.querySelector(".slider-prev");
  var nextBtn  = document.querySelector(".slider-next");

  if (!track || !prevBtn || !nextBtn) return;

  var originalCards = Array.prototype.slice.call(track.querySelectorAll(".card"));
  var cards         = originalCards.slice();
  var CARD_WIDTH = 200;
  var GAP        = 16;
  var step       = CARD_WIDTH + GAP;

  var activeIndex    = 0;
  var desktopMoving  = false;
  var DESKTOP_MS     = 350;
  var userInteracted = false;

  /* Mobile arrows — hidden on desktop via CSS, always functional */
  var mobilePrevBtn = document.querySelector(".arrow-prev");
  var mobileNextBtn = document.querySelector(".arrow-next");

  var mobileLoopReady     = false;
  var mobileLoopStart     = 0;
  var mobileLoopEnd       = 0;
  var mobileLoopBaseWidth = 0;
  var mobileLoopJumping   = false;
  var mobileLoopSettleTimer = null;
  var MOBILE_LOOP_COPIES = 2;

  function refreshCards() {
    cards = Array.prototype.slice.call(track.querySelectorAll(".card"));
  }

  /* Detect real mobile stacked-card mode from CSS, not viewport math.
     With the new mobile design (native CSS scroll-snap) cards are
     position: static so this returns false — the legacy 3D-stack
     code paths are bypassed. Kept only as a safety net. */
  function isStackedMode() {
    if (!cards.length) return false;
    return window.getComputedStyle(cards[0]).position === "absolute";
  }

  /* Detect native scroll-snap mode (mobile, ≤ 768px breakpoint).
     The browser handles touch, momentum, and snap natively — the JS
     only needs to wire the prev/next arrows to scrollBy(). */
  function getScrollElement() {
    if (track) {
      var trackOverflow = window.getComputedStyle(track).overflowX;
      if (trackOverflow === "auto" || trackOverflow === "scroll") return track;
    }

    if (slider) {
      var sliderOverflow = window.getComputedStyle(slider).overflowX;
      if (sliderOverflow === "auto" || sliderOverflow === "scroll") return slider;
    }

    return null;
  }

  function isScrollMode() {
    return !!getScrollElement();
  }

  /* Measure the live card+gap step so the arrow scroll matches the
     real layout at any breakpoint. */
  function getScrollStep() {
    var first = cards[0];
    if (!first) return CARD_WIDTH + GAP;
    var w = first.offsetWidth || first.getBoundingClientRect().width;
    var g = parseFloat(window.getComputedStyle(track).gap) || GAP;
    return Math.round(w + g);
  }

  function getCenteredScrollLeft(card) {
    var scrollElement = getScrollElement();
    if (!scrollElement || !card) return 0;
    return card.offsetLeft - (scrollElement.clientWidth - card.offsetWidth) / 2;
  }

  function setupMobileLoop() {
    if (mobileLoopReady || originalCards.length < 2 || !isScrollMode()) return;

    var before = document.createDocumentFragment();
    var after = document.createDocumentFragment();

    originalCards.forEach(function (card, index) {
      card.setAttribute("data-mobile-loop-index", index);
    });

    for (var copy = 0; copy < MOBILE_LOOP_COPIES; copy += 1) {
      originalCards.forEach(function (card, index) {
        var beforeClone = card.cloneNode(true);
        beforeClone.setAttribute("data-mobile-loop-clone", "true");
        beforeClone.setAttribute("data-mobile-loop-index", index);
        beforeClone.setAttribute("aria-hidden", "true");
        beforeClone.querySelectorAll("a, button").forEach(function (item) {
          item.setAttribute("tabindex", "-1");
        });
        before.appendChild(beforeClone);

        var afterClone = card.cloneNode(true);
        afterClone.setAttribute("data-mobile-loop-clone", "true");
        afterClone.setAttribute("data-mobile-loop-index", index);
        afterClone.setAttribute("aria-hidden", "true");
        afterClone.querySelectorAll("a, button").forEach(function (item) {
          item.setAttribute("tabindex", "-1");
        });
        after.appendChild(afterClone);
      });
    }

    track.insertBefore(before, track.firstChild);
    track.appendChild(after);
    refreshCards();

    mobileLoopBaseWidth = getScrollStep() * originalCards.length;
    mobileLoopStart = getCenteredScrollLeft(originalCards[0]);
    mobileLoopEnd = mobileLoopStart + mobileLoopBaseWidth;
    mobileLoopReady = true;

    mobileLoopJumping = true;
    track.scrollTo({ left: mobileLoopStart, behavior: "auto" });
    window.requestAnimationFrame(function () {
      mobileLoopJumping = false;
      updateScrollActive();
    });
  }

  function removeMobileLoop() {
    if (!mobileLoopReady) return;

    Array.prototype.slice.call(track.querySelectorAll("[data-mobile-loop-clone]")).forEach(function (clone) {
      clone.remove();
    });

    refreshCards();
    mobileLoopReady = false;
    mobileLoopStart = 0;
    mobileLoopEnd = 0;
    mobileLoopBaseWidth = 0;
    window.clearTimeout(mobileLoopSettleTimer);
    mobileLoopSettleTimer = null;
  }

  function maintainMobileLoop() {
    var scrollElement = getScrollElement();
    if (!mobileLoopReady || !scrollElement || mobileLoopJumping) return;

    var nextLeft = null;
    var leftResetPoint = mobileLoopStart - mobileLoopBaseWidth;
    var rightResetPoint = mobileLoopEnd + mobileLoopBaseWidth;

    if (scrollElement.scrollLeft < leftResetPoint) {
      nextLeft = scrollElement.scrollLeft + mobileLoopBaseWidth;
    } else if (scrollElement.scrollLeft >= rightResetPoint) {
      nextLeft = scrollElement.scrollLeft - mobileLoopBaseWidth;
    }

    if (nextLeft !== null) {
      mobileLoopJumping = true;
      scrollElement.scrollTo({ left: nextLeft, behavior: "auto" });
      window.requestAnimationFrame(function () {
        mobileLoopJumping = false;
        updateScrollActive();
      });
    }
  }

  function scheduleMobileLoopSettle() {
    if (!mobileLoopReady) return;
    window.clearTimeout(mobileLoopSettleTimer);
    mobileLoopSettleTimer = window.setTimeout(maintainMobileLoop, 140);
  }

  function scrollByStep(direction) {
    var scrollElement = getScrollElement();
    if (!scrollElement) return;
    setupMobileLoop();
    scrollElement.scrollBy({ left: direction * getScrollStep(), behavior: "smooth" });
    scheduleMobileLoopSettle();
    window.setTimeout(scheduleMobileLoopSettle, 460);
  }

  function updateScrollActive() {
    var scrollElement = getScrollElement();
    if (!scrollElement || !cards.length) return;

    var sliderBox = scrollElement.getBoundingClientRect();
    var sliderCenter = sliderBox.left + sliderBox.width / 2;
    var nearestIndex = 0;
    var nearestDistance = Infinity;

    cards.forEach(function (card, index) {
      var box = card.getBoundingClientRect();
      var cardCenter = box.left + box.width / 2;
      var distance = Math.abs(sliderCenter - cardCenter);

      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearestIndex = index;
      }
    });

    cards.forEach(function (card, index) {
      card.setAttribute("data-scroll-active", index === nearestIndex ? "true" : "false");
    });
  }

  var scrollRaf = null;
  function requestScrollActiveUpdate() {
    if (!isScrollMode()) return;
    if (scrollRaf) window.cancelAnimationFrame(scrollRaf);
    scrollRaf = window.requestAnimationFrame(function () {
      scrollRaf = null;
      updateScrollActive();
      scheduleMobileLoopSettle();
    });
  }

  /* ─────────────────────────────────────────────────────────
     UPDATE LAYOUT
  ──────────────────────────────────────────────────────────── */
  function updateSlider() {
    if (isStackedMode()) {
      track.style.transform = "none";

      cards.forEach(function (card, i) {
        var diff    = i - activeIndex;
        var scale   = 1, offset = 0, opacity = 1;
        var zIndex  = cards.length - Math.abs(diff);
        var active  = "false";

        if (diff === 0) {
          scale = 1; offset = 0; opacity = 1; active = "true";
        } else if (diff === -1) {
          scale = 0.82; offset = -130; opacity = 0.65;
        } else if (diff === 1) {
          scale = 0.82; offset = 130; opacity = 0.65;
        } else {
          scale = 0.6; opacity = 0; offset = diff < 0 ? -200 : 200;
        }

        card.style.setProperty("--scale",   scale);
        card.style.setProperty("--offset",  offset + "px");
        card.style.setProperty("--opacity", opacity);
        card.style.setProperty("--z-index", zIndex);
        card.setAttribute("data-active", active);
      });
    } else {
      cards.forEach(function (card) {
        card.style.removeProperty("--scale");
        card.style.removeProperty("--offset");
        card.style.removeProperty("--opacity");
        card.style.removeProperty("--z-index");
        card.removeAttribute("data-active");
      });
      track.style.transform = "translateX(0)";
    }
  }

  /* ─────────────────────────────────────────────────────────
     AUTO-SCROLL  (mobile only, stops after first interaction)
  ──────────────────────────────────────────────────────────── */
  var autoTimer = null;

  function startAuto() {
    stopAuto();
    /* Auto-advance is only meaningful in the legacy stacked mode.
       In native scroll mode the user controls the scroll position;
       programmatic auto-scroll would fight their input. */
    if (!isStackedMode() || isScrollMode() || userInteracted) return;
    autoTimer = setInterval(function () {
      activeIndex = (activeIndex + 1) % cards.length;
      updateSlider();
    }, 2500);
  }

  function stopAuto() {
    clearInterval(autoTimer);
    autoTimer = null;
  }

  function markInteracted() {
    userInteracted = true;
    stopAuto();
  }

  /* ─────────────────────────────────────────────────────────
     DESKTOP ARROWS  (infinite-loop DOM rotation)
  ──────────────────────────────────────────────────────────── */
  function desktopPrev() {
    /* Bail out in scroll mode — manipulating the track transform
       would fight the native scroll position on mobile. */
    if (isScrollMode() || isStackedMode() || desktopMoving) return;
    desktopMoving = true;

    track.style.transition = "none";
    track.insertBefore(track.lastElementChild, track.firstElementChild);
    track.style.transform = "translateX(-" + step + "px)";
    void track.offsetWidth;
    track.style.transition = "";
    track.style.transform = "translateX(0)";

    setTimeout(function () { desktopMoving = false; }, DESKTOP_MS);
  }

  function desktopNext() {
    if (isScrollMode() || isStackedMode() || desktopMoving) return;
    desktopMoving = true;

    track.style.transition = "";
    track.style.transform = "translateX(-" + step + "px)";

    setTimeout(function () {
      track.style.transition = "none";
      track.appendChild(track.firstElementChild);
      track.style.transform = "translateX(0)";
      void track.offsetWidth;
      track.style.transition = "";
      desktopMoving = false;
    }, DESKTOP_MS);
  }

  prevBtn.addEventListener("click", function () {
    if (isScrollMode()) { scrollByStep(-1); return; }
    desktopPrev();
  });
  nextBtn.addEventListener("click", function () {
    if (isScrollMode()) { scrollByStep(1); return; }
    desktopNext();
  });

  /* ─────────────────────────────────────────────────────────
     MOBILE ARROW BUTTONS
     Note: NO isMobile() guard — CSS hides them on desktop
  ──────────────────────────────────────────────────────────── */
  if (mobilePrevBtn) {
    mobilePrevBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      markInteracted();
      if (isScrollMode()) {
        scrollByStep(-1);
      } else if (isStackedMode()) {
        activeIndex = (activeIndex - 1 + cards.length) % cards.length;
        updateSlider();
      } else {
        desktopPrev();
      }
    });
  }

  if (mobileNextBtn) {
    mobileNextBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      markInteracted();
      if (isScrollMode()) {
        scrollByStep(1);
      } else if (isStackedMode()) {
        activeIndex = (activeIndex + 1) % cards.length;
        updateSlider();
      } else {
        desktopNext();
      }
    });
  }

  /* ─────────────────────────────────────────────────────────
     TOUCH SWIPE
     • touchstart on the slider container
     • touchend on document so finger can leave the element
  ──────────────────────────────────────────────────────────── */
  var touchStartX = 0;
  var touchActive = false;
  var SWIPE_MIN   = 40; /* px minimum to register as a swipe */

  (slider || track).addEventListener("touchstart", function (e) {
    /* In native scroll mode the browser owns the gesture — don't
       intercept it from JS or we'd block the scroll. */
    if (isScrollMode() || !isStackedMode()) return;
    touchActive  = true;
    touchStartX  = e.touches[0].clientX;
  }, { passive: true });

  document.addEventListener("touchend", function (e) {
    if (isScrollMode() || !touchActive || !isStackedMode()) return;
    touchActive = false;

    var endX  = e.changedTouches[0].clientX;
    var delta = touchStartX - endX;

    if (Math.abs(delta) >= SWIPE_MIN) {
      markInteracted();
      if (delta > 0) {
        activeIndex = (activeIndex + 1) % cards.length;   /* swipe left  → next */
      } else {
        activeIndex = (activeIndex - 1 + cards.length) % cards.length; /* swipe right → prev */
      }
      updateSlider();
    }
  }, { passive: true });

  /* ─────────────────────────────────────────────────────────
     CLICK SIDE CARD  → jump to it (legacy stacked mode only).
     In scroll mode every card is a real link and should navigate
     normally on tap — do nothing here.
  ──────────────────────────────────────────────────────────── */
  cards.forEach(function (card, idx) {
    card.addEventListener("click", function (e) {
      if (isScrollMode() || !isStackedMode()) return;
      if (idx !== activeIndex) {
        e.preventDefault();
        markInteracted();
        activeIndex = idx;
        updateSlider();
      }
    });
  });

  /* ─────────────────────────────────────────────────────────
     MOUSE DRAG  — works on both desktop AND mobile
     • Desktop: translates the track left/right
     • Mobile:  same threshold logic as touch swipe
  ──────────────────────────────────────────────────────────── */
  var dragStartX    = 0;
  var isDragging    = false;
  var dragMoved     = false;   /* true once cursor moves > 5px */
  var currentOffset = 0;
  var maxOffset     = Math.max(0, (cards.length - 6) * step);

  (slider || track).addEventListener("mousedown", function (e) {
    /* Mobile uses native CSS scroll-snap — no JS drag needed. */
    if (isScrollMode()) return;
    isDragging  = true;
    dragMoved   = false;
    dragStartX  = e.pageX;
    if (!isStackedMode()) {
      track.style.transition = "none";
      track.style.cursor = "grabbing";
    }
    stopAuto();
  });

  window.addEventListener("mousemove", function (e) {
    if (!isDragging) return;

    if (Math.abs(e.pageX - dragStartX) > 5) dragMoved = true;

    if (!isStackedMode()) {
      /* Desktop: live-translate the track */
      var delta = dragStartX - e.pageX;
      currentOffset = Math.max(0, Math.min(currentOffset + delta, maxOffset));
      dragStartX    = e.pageX;
      track.style.transform = "translateX(-" + currentOffset + "px)";
    }
  });

  window.addEventListener("mouseup", function (e) {
    if (!isDragging) return;
    isDragging = false;
    track.style.cursor = "";

    if (isStackedMode()) {
      /* Mobile: decide next/prev on release */
      var delta = dragStartX - e.pageX;
      if (dragMoved && Math.abs(delta) >= SWIPE_MIN) {
        markInteracted();
        if (delta > 0) {
          activeIndex = (activeIndex + 1) % cards.length;
        } else {
          activeIndex = (activeIndex - 1 + cards.length) % cards.length;
        }
        updateSlider();
      } else if (!dragMoved) {
        /* No meaningful drag — restart auto if user hasn't interacted */
        if (!userInteracted) startAuto();
      }
    } else {
      /* Desktop: snap to nearest card */
      track.style.transition = "";
      currentOffset = Math.round(currentOffset / step) * step;
      track.style.transform = "translateX(-" + currentOffset + "px)";
    }
  });

  /* Prevent ghost image drag */
  track.querySelectorAll("img").forEach(function (img) {
    img.addEventListener("dragstart", function (e) { e.preventDefault(); });
  });

  /* ─────────────────────────────────────────────────────────
     RESIZE
  ──────────────────────────────────────────────────────────── */
  window.addEventListener("resize", function () {
    if (isScrollMode()) {
      /* Wipe any inline transform left over from a previous desktop
         session so the CSS scroll-snap layout renders cleanly. */
      track.style.transform = "";
      track.style.transition = "";
      if (mobileLoopReady) removeMobileLoop();
      setupMobileLoop();
      updateScrollActive();
      stopAuto();
      return;
    }
    removeMobileLoop();
    updateSlider();
    if (isStackedMode() && !userInteracted) startAuto();
    else stopAuto();
  });

  /* ─────────────────────────────────────────────────────────
     INIT
  ──────────────────────────────────────────────────────────── */
  if (isScrollMode()) {
    /* Native scroll mode: the track owns horizontal scroll and the
       arrow row stays stable below it. */
    track.style.transform = "";
    track.style.transition = "";
    setupMobileLoop();
    updateScrollActive();
  } else {
    updateSlider();
    startAuto();
  }

  if (slider) {
    slider.addEventListener("scroll", requestScrollActiveUpdate, { passive: true });
  }
  if (track) {
    track.addEventListener("scroll", requestScrollActiveUpdate, { passive: true });
  }
})();

