(function () {
  "use strict";

  var AUTO_DELAY_MS = 6000;
  var TRANSITION_MS = 450;

  function initSlider(root) {
    var track = root.querySelector(".cc-offers-banner-slider__track");
    var slides = root.querySelectorAll(".cc-offers-banner-slider__slide");
    var prev = root.querySelector(".cc-offers-banner-slider__arrow--prev");
    var next = root.querySelector(".cc-offers-banner-slider__arrow--next");
    var dotsWrap = root.querySelector(".cc-offers-banner-slider__dots");

    if (!track || !slides.length || slides.length < 2 || !prev || !next || !dotsWrap) {
      return;
    }

    var index = 0;
    var timer = null;
    var dots = [];

    function setActiveDot(activeIndex) {
      dots.forEach(function (dot, dotIndex) {
        var isActive = dotIndex === activeIndex;
        dot.classList.toggle("is-active", isActive);
        dot.setAttribute("aria-selected", isActive ? "true" : "false");
        dot.setAttribute("tabindex", isActive ? "0" : "-1");
      });
    }

    function goTo(nextIndex) {
      index = (nextIndex + slides.length) % slides.length;
      track.style.transform = "translateX(-" + index * 100 + "%)";
      setActiveDot(index);
    }

    function stopAuto() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function startAuto() {
      stopAuto();
      timer = window.setInterval(function () {
        goTo(index + 1);
      }, AUTO_DELAY_MS);
    }

    slides.forEach(function (_, slideIndex) {
      var dot = document.createElement("button");
      dot.type = "button";
      dot.className = "cc-offers-banner-slider__dot" + (slideIndex === 0 ? " is-active" : "");
      dot.setAttribute("role", "tab");
      dot.setAttribute("aria-label", "Campaign " + (slideIndex + 1));
      dot.setAttribute("aria-selected", slideIndex === 0 ? "true" : "false");
      dot.setAttribute("tabindex", slideIndex === 0 ? "0" : "-1");
      dot.addEventListener("click", function () {
        goTo(slideIndex);
        startAuto();
      });
      dotsWrap.appendChild(dot);
      dots.push(dot);
    });

    track.style.transition = "transform " + TRANSITION_MS + "ms ease";

    prev.addEventListener("click", function () {
      goTo(index - 1);
      startAuto();
    });

    next.addEventListener("click", function () {
      goTo(index + 1);
      startAuto();
    });

    root.addEventListener("mouseenter", stopAuto);
    root.addEventListener("mouseleave", startAuto);
    root.addEventListener("focusin", stopAuto);
    root.addEventListener("focusout", startAuto);

    startAuto();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".cc-offers-banner-slider").forEach(initSlider);
  });
})();
