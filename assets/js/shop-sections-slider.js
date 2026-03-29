(function () {
  "use strict";

  function initLoopSlider(config) {
    var track = document.getElementById(config.trackId);
    var prev = document.getElementById(config.prevId);
    var next = document.getElementById(config.nextId);
    if (!track || !prev || !next) return;

    var moving = false;
    var duration = 340;

    function getStep() {
      var first = track.children[0];
      if (!first) return 0;
      var gap = parseFloat(window.getComputedStyle(track).gap || "0") || 0;
      return first.getBoundingClientRect().width + gap;
    }

    function slideNext() {
      if (moving || !track.children.length) return;
      var step = getStep();
      if (!step) return;
      moving = true;
      track.style.transition = "transform " + duration + "ms ease";
      track.style.transform = "translateX(-" + step + "px)";
      window.setTimeout(function () {
        track.style.transition = "none";
        track.appendChild(track.firstElementChild);
        track.style.transform = "translateX(0)";
        void track.offsetWidth;
        track.style.transition = "";
        moving = false;
      }, duration);
    }

    function slidePrev() {
      if (moving || !track.children.length) return;
      var step = getStep();
      if (!step) return;
      moving = true;
      track.style.transition = "none";
      track.insertBefore(track.lastElementChild, track.firstElementChild);
      track.style.transform = "translateX(-" + step + "px)";
      void track.offsetWidth;
      track.style.transition = "transform " + duration + "ms ease";
      track.style.transform = "translateX(0)";
      window.setTimeout(function () {
        track.style.transition = "";
        moving = false;
      }, duration);
    }

    prev.addEventListener("click", slidePrev);
    next.addEventListener("click", slideNext);
  }

  document.addEventListener("DOMContentLoaded", function () {
    initLoopSlider({
      trackId: "shop-category-track",
      prevId: "shop-cat-prev",
      nextId: "shop-cat-next"
    });

    initLoopSlider({
      trackId: "shop-specialty-track",
      prevId: "shop-spec-prev",
      nextId: "shop-spec-next"
    });

    initLoopSlider({
      trackId: "shop-instrument-track",
      prevId: "shop-inst-prev",
      nextId: "shop-inst-next"
    });
  });
})();
