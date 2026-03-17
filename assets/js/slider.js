(function () {
  var section  = document.querySelector(".popular-categories");
  var slider   = document.querySelector(".categories-slider");
  var track    = document.querySelector(".slider-track");
  var prevBtn  = document.querySelector(".slider-prev");
  var nextBtn  = document.querySelector(".slider-next");

  if (!track || !prevBtn || !nextBtn) return;

  var cards      = Array.prototype.slice.call(track.querySelectorAll(".card"));
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

  /* Detect real mobile stacked-card mode from CSS, not viewport math */
  function isStackedMode() {
    if (!cards.length) return false;
    return window.getComputedStyle(cards[0]).position === "absolute";
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
    if (!isStackedMode() || userInteracted) return;
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
    if (isStackedMode() || desktopMoving) return;
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
    if (isStackedMode() || desktopMoving) return;
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

  prevBtn.addEventListener("click", desktopPrev);
  nextBtn.addEventListener("click", desktopNext);

  /* ─────────────────────────────────────────────────────────
     MOBILE ARROW BUTTONS
     Note: NO isMobile() guard — CSS hides them on desktop
  ──────────────────────────────────────────────────────────── */
  if (mobilePrevBtn) {
    mobilePrevBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      markInteracted();
      if (isStackedMode()) {
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
      if (isStackedMode()) {
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
    if (!isStackedMode()) return;
    touchActive  = true;
    touchStartX  = e.touches[0].clientX;
  }, { passive: true });

  document.addEventListener("touchend", function (e) {
    if (!touchActive || !isStackedMode()) return;
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
     CLICK SIDE CARD  → jump to it
  ──────────────────────────────────────────────────────────── */
  cards.forEach(function (card, idx) {
    card.addEventListener("click", function (e) {
      if (!isStackedMode()) return;
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
    updateSlider();
    if (isStackedMode() && !userInteracted) startAuto();
    else stopAuto();
  });

  /* ─────────────────────────────────────────────────────────
     INIT
  ──────────────────────────────────────────────────────────── */
  updateSlider();
  startAuto();

  /* ─────────────────────────────────────────────────────────
     HERO BANNER AUTO SLIDER
  ──────────────────────────────────────────────────────────── */
  var heroSlides = document.querySelectorAll(".hero-slide");
  if (heroSlides.length > 0) {
    var heroIndex = 0;
    setInterval(function () {
      heroSlides[heroIndex].classList.remove("active");
      heroIndex = (heroIndex + 1) % heroSlides.length;
      heroSlides[heroIndex].classList.add("active");
    }, 4500);
  }
})();

/* ─────────────────────────────────────────────────────────────
   MOBILE DRAWER
──────────────────────────────────────────────────────────────── */
(function () {
  var drawer    = document.getElementById("mobile-drawer");
  var overlay   = document.getElementById("drawer-overlay");
  var hamburger = document.getElementById("hamburger-btn");
  var closeBtn  = document.getElementById("drawer-close-btn");

  if (!drawer || !overlay || !hamburger || !closeBtn) return;

  function openDrawer()  {
    drawer.classList.add("open");
    overlay.classList.add("active");
    drawer.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeDrawer() {
    drawer.classList.remove("open");
    overlay.classList.remove("active");
    drawer.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  hamburger.addEventListener("click", openDrawer);
  closeBtn.addEventListener("click", closeDrawer);
  overlay.addEventListener("click", closeDrawer);

  /* Tab switching */
  var tabs   = drawer.querySelectorAll(".drawer-tab");
  var panels = drawer.querySelectorAll(".drawer-content");
  tabs.forEach(function (tab) {
    tab.addEventListener("click", function () {
      var target = tab.getAttribute("data-tab");
      tabs.forEach(function (t) { t.classList.remove("active"); });
      tab.classList.add("active");
      panels.forEach(function (panel) {
        if (panel.getAttribute("data-content") === target) panel.classList.remove("hidden");
        else panel.classList.add("hidden");
      });
    });
  });

  /* Category accordion */
  drawer.querySelectorAll(".drawer-category-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var sub    = btn.nextElementSibling;
      if (!sub) return;
      var isOpen = sub.classList.contains("open");
      drawer.querySelectorAll(".drawer-subcategories.open").forEach(function (el) { el.classList.remove("open"); });
      drawer.querySelectorAll(".drawer-category-toggle.active").forEach(function (el) { el.classList.remove("active"); });
      if (!isOpen) { sub.classList.add("open"); btn.classList.add("active"); }
    });
  });

  /* Subcategory accordion */
  drawer.querySelectorAll(".drawer-subcategory-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var list   = btn.nextElementSibling;
      if (!list) return;
      var isOpen = list.classList.contains("open");
      drawer.querySelectorAll(".drawer-items.open").forEach(function (el) { el.classList.remove("open"); });
      drawer.querySelectorAll(".drawer-subcategory-toggle.active").forEach(function (el) { el.classList.remove("active"); });
      if (!isOpen) { list.classList.add("open"); btn.classList.add("active"); }
    });
  });
})();
