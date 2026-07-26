/**
 * Shop promo slider (AB layout) — shop & product category archives.
 */
(function () {
  "use strict";

  function ABPromoSlider(rootElement) {
    this.root = rootElement;
    this.slides = Array.prototype.slice.call(
      rootElement.querySelectorAll("[data-promo-slide]"),
    );
    this.prevBtn = rootElement.querySelector("[data-promo-prev]");
    this.nextBtn = rootElement.querySelector("[data-promo-next]");
    this.viewport = rootElement.querySelector("[data-promo-viewport]");

    if (!this.slides.length) {
      return;
    }

    this.index = 0;
    for (var i = 0; i < this.slides.length; i++) {
      if (this.slides[i].classList.contains("is-active")) {
        this.index = i;
        break;
      }
    }

    this.SWIPE_MIN_PX = 48;
    this.SWIPE_RATIO = 1.15;
    this.startX = 0;
    this.startY = 0;
    this.tracking = false;
    this.suppressSlideClick = false;

    this.autoplayDelay =
      parseInt(this.root.getAttribute("data-autoplay-delay"), 10) || 5000;
    this.autoplayTimer = null;

    this.syncSlides();
    this.bindEvents();
    this.startAutoplay();
  }

  ABPromoSlider.prototype.getSlideUrl = function (slide) {
    if (!slide) {
      return "";
    }

    var url = slide.getAttribute("data-promo-url") || "";
    if (url && "#" !== url) {
      return url;
    }

    var overlay = slide.querySelector(".ab-promo-slide-link");
    return overlay ? overlay.getAttribute("href") || "" : "";
  };

  ABPromoSlider.prototype.syncSlides = function () {
    var n = this.slides.length;

    for (var i = 0; i < n; i++) {
      var active = i === this.index;
      var slide = this.slides[i];

      slide.classList.toggle("is-active", active);
      slide.setAttribute("aria-hidden", active ? "false" : "true");

      var url = this.getSlideUrl(slide);
      var overlay = slide.querySelector(".ab-promo-slide-link");
      var cta = slide.querySelector(".ab-promo-btn");
      var flagLink = slide.querySelector(".ab-promo-flag-link");

      if (overlay) {
        if (url) {
          overlay.setAttribute("href", url);
        }

        overlay.tabIndex = active ? 0 : -1;
        overlay.setAttribute("aria-hidden", active ? "false" : "true");

        if (active) {
          overlay.removeAttribute("data-disabled-link");
        } else {
          overlay.setAttribute("data-disabled-link", "true");
        }
      }

      if (cta) {
        if (url) {
          cta.setAttribute("href", url);
        }
        cta.tabIndex = active ? 0 : -1;
        cta.setAttribute("aria-hidden", active ? "false" : "true");
      }

      if (flagLink) {
        flagLink.tabIndex = active ? 0 : -1;
        flagLink.setAttribute("aria-hidden", active ? "false" : "true");
      }
    }
  };

  ABPromoSlider.prototype.go = function (delta) {
    var n = this.slides.length;
    if (!n) {
      return;
    }

    var isRTL =
      document.documentElement.getAttribute("dir") === "rtl" ||
      getComputedStyle(this.root).direction === "rtl";
    var moveDelta = isRTL ? -delta : delta;

    this.index = (this.index + moveDelta + n) % n;
    this.syncSlides();
  };

  ABPromoSlider.prototype.startAutoplay = function () {
    var self = this;
    this.stopAutoplay();
    this.autoplayTimer = setInterval(function () {
      self.go(1);
    }, this.autoplayDelay);
  };

  ABPromoSlider.prototype.stopAutoplay = function () {
    if (this.autoplayTimer) {
      clearInterval(this.autoplayTimer);
      this.autoplayTimer = null;
    }
  };

  ABPromoSlider.prototype.bindEvents = function () {
    var self = this;

    if (this.prevBtn) {
      this.prevBtn.addEventListener("click", function () {
        self.go(-1);
        self.startAutoplay();
      });
    }
    if (this.nextBtn) {
      this.nextBtn.addEventListener("click", function () {
        self.go(1);
        self.startAutoplay();
      });
    }

    this.root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        self.go(-1);
        self.startAutoplay();
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        self.go(1);
        self.startAutoplay();
      }
    });

    this.root.addEventListener("mouseenter", function () {
      self.stopAutoplay();
    });
    this.root.addEventListener("mouseleave", function () {
      self.startAutoplay();
    });

    if (!this.viewport) {
      return;
    }

    this.viewport.addEventListener(
      "touchstart",
      function (e) {
        self.stopAutoplay();
        if (e.touches.length !== 1) {
          return;
        }
        self.startX = e.touches[0].clientX;
        self.startY = e.touches[0].clientY;
        self.tracking = true;
      },
      { passive: true },
    );

    this.viewport.addEventListener(
      "touchmove",
      function (e) {
        if (!self.tracking || e.touches.length !== 1) {
          return;
        }
        var dx = e.touches[0].clientX - self.startX;
        var dy = e.touches[0].clientY - self.startY;
        if (
          Math.abs(dx) > self.SWIPE_MIN_PX * 0.35 &&
          Math.abs(dx) > Math.abs(dy) * self.SWIPE_RATIO
        ) {
          e.preventDefault();
        }
      },
      { passive: false },
    );

    this.viewport.addEventListener(
      "touchend",
      function (e) {
        self.startAutoplay();
        if (!self.tracking || !e.changedTouches.length) {
          return;
        }
        self.tracking = false;
        var dx = e.changedTouches[0].clientX - self.startX;
        var dy = e.changedTouches[0].clientY - self.startY;
        if (
          Math.abs(dx) < self.SWIPE_MIN_PX ||
          Math.abs(dx) < Math.abs(dy) * self.SWIPE_RATIO
        ) {
          return;
        }
        self.suppressSlideClick = true;
        window.setTimeout(function () {
          self.suppressSlideClick = false;
        }, 400);
        self.go(dx < 0 ? 1 : -1);
      },
      { passive: true },
    );

    this.viewport.addEventListener("click", function (e) {
      var activeSlide = self.slides[self.index];
      if (!activeSlide) {
        return;
      }

      var promoLink = e.target.closest(
        ".ab-promo-slide-link, .ab-promo-btn, .ab-promo-flag-link",
      );
      if (!promoLink) {
        return;
      }

      if (self.suppressSlideClick) {
        e.preventDefault();
        return;
      }

      var slide = promoLink.closest("[data-promo-slide]");
      var activeUrl = self.getSlideUrl(activeSlide);

      if (!slide || slide !== activeSlide) {
        e.preventDefault();
        if (activeUrl && "#" !== activeUrl) {
          window.location.href = activeUrl;
        }
        return;
      }

      if (promoLink.classList.contains("ab-promo-slide-link")) {
        if (!activeUrl || "#" === activeUrl) {
          e.preventDefault();
        }
      }
    });

    this.viewport.addEventListener(
      "touchcancel",
      function () {
        self.tracking = false;
        self.startAutoplay();
      },
      { passive: true },
    );
  };

  function initSliders() {
    document.querySelectorAll("[data-ab-promo-slider]").forEach(function (el) {
      new ABPromoSlider(el);
    });
  }

  function isMobileOriginModal() {
    return window.matchMedia("(max-width: 768px)").matches;
  }

  function ensureOriginModalAnchor(modal) {
    if (!modal || modal._ccOriginAnchor) {
      return;
    }

    var anchor = document.createComment("cc-origin-modal-anchor");
    modal.parentNode.insertBefore(anchor, modal);
    modal._ccOriginAnchor = anchor;
  }

  function mountOriginModal(modal) {
    if (!modal || !isMobileOriginModal()) {
      return;
    }

    ensureOriginModalAnchor(modal);
    document.body.appendChild(modal);
  }

  function restoreOriginModal(modal) {
    if (!modal || !modal._ccOriginAnchor || !modal._ccOriginAnchor.parentNode) {
      return;
    }

    modal._ccOriginAnchor.parentNode.insertBefore(
      modal,
      modal._ccOriginAnchor.nextSibling,
    );
  }

  function syncOriginModalBodyState() {
    var hasOpen = document.querySelector(".ab-promo-origin-modal.is-open");
    document.body.classList.toggle("ab-promo-origin-modal-open", !!hasOpen);
  }

  function syncOriginPopoverViewportState() {
    if (isMobileOriginModal()) {
      document.querySelectorAll(".ab-promo-viewport").forEach(function (viewport) {
        viewport.classList.remove("is-origin-popover-open");
      });
      return;
    }

    document.querySelectorAll(".ab-promo-viewport").forEach(function (viewport) {
      var hasOpen = viewport.querySelector(".ab-promo-origin-popover.is-open");
      viewport.classList.toggle("is-origin-popover-open", !!hasOpen);
    });
  }

  function getOriginPopoverTrigger(popover) {
    if (!popover || !popover.id) {
      return null;
    }

    return document.querySelector('[aria-controls="' + popover.id + '"]');
  }

  function closeOriginPopover(popover, btn) {
    if (!popover) {
      return;
    }

    var modal = popover.closest(".ab-promo-origin-modal");

    popover.classList.remove("is-open");
    popover.setAttribute("hidden", "");

    if (modal) {
      modal.classList.remove("is-open");
      modal.setAttribute("hidden", "");
      restoreOriginModal(modal);
    }

    if (btn) {
      btn.setAttribute("aria-expanded", "false");
    }

    syncOriginPopoverViewportState();
    syncOriginModalBodyState();
  }

  function closeAllOriginPopovers(exceptPopover) {
    document.querySelectorAll(".ab-promo-origin-popover.is-open").forEach(function (popover) {
      if (popover === exceptPopover) {
        return;
      }

      closeOriginPopover(popover, getOriginPopoverTrigger(popover));
    });
  }

  function openOriginPopover(popover, btn) {
    if (!popover || !btn) {
      return;
    }

    var modal = popover.closest(".ab-promo-origin-modal");

    closeAllOriginPopovers(popover);

    if (modal) {
      mountOriginModal(modal);
      modal.classList.add("is-open");
      modal.removeAttribute("hidden");
    }

    popover.classList.add("is-open");
    popover.removeAttribute("hidden");
    btn.setAttribute("aria-expanded", "true");
    syncOriginPopoverViewportState();
    syncOriginModalBodyState();
  }

  function closeOriginPopoverFromEvent(event) {
    var closeTrigger = event.target.closest("[data-origin-close]");
    if (!closeTrigger) {
      return false;
    }

    var modal = closeTrigger.closest(".ab-promo-origin-modal");
    var popover = modal ? modal.querySelector(".ab-promo-origin-popover") : null;

    if (!popover) {
      return false;
    }

    event.preventDefault();
    event.stopPropagation();
    closeOriginPopover(popover, getOriginPopoverTrigger(popover));
    return true;
  }

  function bindOriginMoreButtons() {
    document.addEventListener(
      "click",
      function (event) {
        if (closeOriginPopoverFromEvent(event)) {
          return;
        }

        var btn = event.target.closest("[data-origin-more]");
        if (!btn) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        var popoverId = btn.getAttribute("aria-controls");
        var targetPopover = popoverId ? document.getElementById(popoverId) : null;

        if (!targetPopover) {
          return;
        }

        var isOpen = targetPopover.classList.contains("is-open");
        if (isOpen) {
          closeOriginPopover(targetPopover, btn);
        } else {
          openOriginPopover(targetPopover, btn);
        }
      },
      true,
    );

    document.addEventListener("click", function (event) {
      if (
        event.target.closest(
          "[data-origin-more], .ab-promo-origin-modal, .ab-promo-origin-popover, .ab-promo-origin-popover-item",
        )
      ) {
        return;
      }

      closeAllOriginPopovers(null);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") {
        return;
      }

      closeAllOriginPopovers(null);
    });
  }

  function initPromo() {
    initSliders();
    bindOriginMoreButtons();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPromo);
  } else {
    initPromo();
  }
})();
