(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /*  Elements                                                            */
  /* ------------------------------------------------------------------ */
  var navItem = document.getElementById('nav-item-shop');
  var shopLink = navItem ? navItem.querySelector('.nav-link') : null;
  var megaMenu = document.getElementById('mega-menu');
  if (!navItem || !megaMenu) return;
  var navItemExplore = document.getElementById('nav-item-explore');
  var exploreLink = navItemExplore ? navItemExplore.querySelector('.nav-link') : null;
  var exploreMenu = document.getElementById('explore-mega-menu');
  var specialtyTrack = document.getElementById('specialty-track');
  var instrumentTrack = document.getElementById('instrument-track');
  var specialtyLabel = document.querySelector('.mega-specialty-label');
  var categoryItems = Array.prototype.slice.call(
    megaMenu.querySelectorAll('.mega-cat-item')
  );

  if (!specialtyTrack || !instrumentTrack || !specialtyLabel || !categoryItems.length) {
    return;
  }

  /* ------------------------------------------------------------------ */
  /*  Show / Hide with a tiny delay so mouse can travel header → panel   */
  /* ------------------------------------------------------------------ */
  var hideTimer;
  var exploreHideTimer;
  var isPinnedOpen = false;
  var isExplorePinnedOpen = false;

  var menuData = {
    surgical: {
      label: 'OPHTHALMOLOGY',
      specialties: [
        { text: 'OPHTHALMOLOGY', color: 'green' },
        { text: 'ENT', color: 'green' },
        { text: 'GYNECOLOGY', color: 'blue' },
        { text: 'GENERAL SURGERY', color: 'blue' },
        { text: 'ORTHOPEDICS', color: 'blue' },
        { text: 'NEUROSURGERY', color: 'blue' },
        { text: 'UROLOGY', color: 'green' },
        { text: 'CARDIAC SURGERY', color: 'blue' }
      ],
      instruments: [
        { text: 'LACRIMAL INSTRUMENTS', color: 'green' },
        { text: 'MARKERS', color: 'green' },
        { text: 'CANNULAS', color: 'blue' },
        { text: 'SPECULUMS & RETRACTORS', color: 'blue' },
        { text: 'FORCEPS', color: 'blue' },
        { text: 'SCISSORS', color: 'blue' },
        { text: 'NEEDLE HOLDERS', color: 'green' },
        { text: 'HOOKS & MANIPULATORS', color: 'blue' }
      ]
    },
    diagnostic: {
      label: 'DIAGNOSTIC',
      specialties: [
        { text: 'RADIOLOGY', color: 'green' },
        { text: 'CARDIOLOGY', color: 'green' },
        { text: 'LAB MEDICINE', color: 'blue' },
        { text: 'PATHOLOGY', color: 'blue' },
        { text: 'PULMONOLOGY', color: 'blue' },
        { text: 'NEUROLOGY', color: 'blue' },
        { text: 'DERMATOLOGY', color: 'green' },
        { text: 'EMERGENCY', color: 'blue' }
      ],
      instruments: [
        { text: 'OTOSCOPES', color: 'green' },
        { text: 'DERMATOSCOPES', color: 'green' },
        { text: 'STETHOSCOPES', color: 'blue' },
        { text: 'ECG ACCESSORIES', color: 'blue' },
        { text: 'PULSE OXIMETERS', color: 'blue' },
        { text: 'BP MONITORS', color: 'blue' },
        { text: 'THERMOMETERS', color: 'green' },
        { text: 'EXAM LIGHTS', color: 'blue' }
      ]
    },
    equipment: {
      label: 'MEDICAL EQUIPMENT',
      specialties: [
        { text: 'ICU', color: 'green' },
        { text: 'ANESTHESIA', color: 'green' },
        { text: 'NEONATAL CARE', color: 'blue' },
        { text: 'DIALYSIS', color: 'blue' },
        { text: 'IMAGING', color: 'blue' },
        { text: 'STERILIZATION', color: 'blue' },
        { text: 'LAB SETUP', color: 'green' },
        { text: 'REHABILITATION', color: 'blue' }
      ],
      instruments: [
        { text: 'PATIENT MONITORS', color: 'green' },
        { text: 'INFUSION PUMPS', color: 'green' },
        { text: 'SUCTION UNITS', color: 'blue' },
        { text: 'DEFIBRILLATORS', color: 'blue' },
        { text: 'AUTOCLAVES', color: 'blue' },
        { text: 'OPERATING LIGHTS', color: 'blue' },
        { text: 'ECG MACHINES', color: 'green' },
        { text: 'VENTILATORS', color: 'blue' }
      ]
    },
    endoscopy: {
      label: 'ENDOSCOPY',
      specialties: [
        { text: 'GASTROENTEROLOGY', color: 'green' },
        { text: 'URO ENDOSCOPY', color: 'green' },
        { text: 'LAPAROSCOPY', color: 'blue' },
        { text: 'ENT ENDOSCOPY', color: 'blue' },
        { text: 'ARTHROSCOPY', color: 'blue' },
        { text: 'BRONCHOSCOPY', color: 'blue' },
        { text: 'HYSTEROSCOPY', color: 'green' },
        { text: 'COLONOSCOPY', color: 'blue' }
      ],
      instruments: [
        { text: 'ENDOSCOPIC SCISSORS', color: 'green' },
        { text: 'GRASPERS', color: 'green' },
        { text: 'TROCARS', color: 'blue' },
        { text: 'LIGHT CABLES', color: 'blue' },
        { text: 'CAMERA HEADS', color: 'blue' },
        { text: 'COAGULATION PROBES', color: 'blue' },
        { text: 'IRRIGATION SETS', color: 'green' },
        { text: 'BIOPSY FORCEPS', color: 'blue' }
      ]
    },
    dental: {
      label: 'DENTAL',
      specialties: [
        { text: 'ORTHODONTICS', color: 'green' },
        { text: 'ENDODONTICS', color: 'green' },
        { text: 'PROSTHODONTICS', color: 'blue' },
        { text: 'PERIODONTICS', color: 'blue' },
        { text: 'ORAL SURGERY', color: 'blue' },
        { text: 'PEDIATRIC DENTAL', color: 'blue' },
        { text: 'DENTAL IMPLANTS', color: 'green' },
        { text: 'ESTHETIC DENTAL', color: 'blue' }
      ],
      instruments: [
        { text: 'DENTAL MIRRORS', color: 'green' },
        { text: 'SCALERS', color: 'green' },
        { text: 'EXCAVATORS', color: 'blue' },
        { text: 'BURS & DRILLS', color: 'blue' },
        { text: 'MATRIX BANDS', color: 'blue' },
        { text: 'ROOT CANAL FILES', color: 'blue' },
        { text: 'DENTAL FORCEPS', color: 'green' },
        { text: 'IMPRESSION TRAYS', color: 'blue' }
      ]
    },
    orthopedic: {
      label: 'ORTHOPEDIC',
      specialties: [
        { text: 'TRAUMA CARE', color: 'green' },
        { text: 'SPINE', color: 'green' },
        { text: 'ARTHROPLASTY', color: 'blue' },
        { text: 'SPORTS MEDICINE', color: 'blue' },
        { text: 'BONE FIXATION', color: 'blue' },
        { text: 'RECONSTRUCTION', color: 'blue' },
        { text: 'PEDIATRIC ORTHO', color: 'green' },
        { text: 'LIMB SALVAGE', color: 'blue' }
      ],
      instruments: [
        { text: 'BONE CUTTERS', color: 'green' },
        { text: 'ORTHO DRILLS', color: 'green' },
        { text: 'REAMERS', color: 'blue' },
        { text: 'K-WIRES', color: 'blue' },
        { text: 'PLATES & SCREWS', color: 'blue' },
        { text: 'EXTERNAL FIXATORS', color: 'blue' },
        { text: 'BONE HOLDING FORCEPS', color: 'green' },
        { text: 'CAST SAWS', color: 'blue' }
      ]
    },
    consumables: {
      label: 'CONSUMABLES',
      specialties: [
        { text: 'OT CONSUMABLES', color: 'green' },
        { text: 'ICU CONSUMABLES', color: 'green' },
        { text: 'WARD SUPPLIES', color: 'blue' },
        { text: 'LAB CONSUMABLES', color: 'blue' },
        { text: 'INFECTION CONTROL', color: 'blue' },
        { text: 'WOUND CARE', color: 'blue' },
        { text: 'ANESTHESIA CONSUMABLES', color: 'green' },
        { text: 'EMERGENCY SUPPLIES', color: 'blue' }
      ],
      instruments: [
        { text: 'SYRINGES', color: 'green' },
        { text: 'GLOVES', color: 'green' },
        { text: 'IV CANNULAS', color: 'blue' },
        { text: 'DRESSINGS', color: 'blue' },
        { text: 'SUTURES', color: 'blue' },
        { text: 'MASKS & CAPS', color: 'blue' },
        { text: 'URINE BAGS', color: 'green' },
        { text: 'BLOOD COLLECTION SETS', color: 'blue' }
      ]
    }
  };

  function showMenu() {
    clearTimeout(hideTimer);
    clearTimeout(exploreHideTimer);
    if (exploreMenu) {
      exploreMenu.classList.remove('active');
      exploreMenu.setAttribute('aria-hidden', 'true');
      isExplorePinnedOpen = false;
    }
    var openingFromClosed = !megaMenu.classList.contains('active');
    megaMenu.classList.add('active');
    megaMenu.setAttribute('aria-hidden', 'false');
    if (openingFromClosed) {
      megaMenu.classList.add('awaiting-selection');
      categoryItems.forEach(function (item) {
        item.classList.remove('active');
      });
    }
  }

  function scheduleHide() {
    if (isPinnedOpen) return;
    hideTimer = setTimeout(function () {
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
    }, 130);
  }

  function showExploreMenu() {
    if (!exploreMenu) return;
    clearTimeout(exploreHideTimer);
    clearTimeout(hideTimer);
    megaMenu.classList.remove('active');
    megaMenu.setAttribute('aria-hidden', 'true');
    isPinnedOpen = false;
    exploreMenu.classList.add('active');
    exploreMenu.setAttribute('aria-hidden', 'false');
  }

  function scheduleExploreHide() {
    if (!exploreMenu || isExplorePinnedOpen) return;
    exploreHideTimer = setTimeout(function () {
      exploreMenu.classList.remove('active');
      exploreMenu.setAttribute('aria-hidden', 'true');
    }, 130);
  }

  navItem.addEventListener('mouseenter', showMenu);
  navItem.addEventListener('mouseleave', function () {
    if (!isPinnedOpen) scheduleHide();
  });
  megaMenu.addEventListener('mouseenter', showMenu);
  megaMenu.addEventListener('mouseleave', scheduleHide);

  if (navItemExplore && exploreMenu) {
    navItemExplore.addEventListener('mouseenter', showExploreMenu);
    navItemExplore.addEventListener('mouseleave', function () {
      if (!isExplorePinnedOpen) scheduleExploreHide();
    });
    exploreMenu.addEventListener('mouseenter', showExploreMenu);
    exploreMenu.addEventListener('mouseleave', scheduleExploreHide);
  }

  if (shopLink) {
    shopLink.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 1024px)').matches) return;
      e.preventDefault();
      clearTimeout(hideTimer);
      if (megaMenu.classList.contains('active') && isPinnedOpen) {
        isPinnedOpen = false;
        megaMenu.classList.remove('active');
        megaMenu.setAttribute('aria-hidden', 'true');
        return;
      }
      isPinnedOpen = true;
      isExplorePinnedOpen = false;
      showMenu();
    });
  }

  if (exploreLink && exploreMenu) {
    exploreLink.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 1024px)').matches) return;
      e.preventDefault();
      clearTimeout(exploreHideTimer);
      if (exploreMenu.classList.contains('active') && isExplorePinnedOpen) {
        isExplorePinnedOpen = false;
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
        return;
      }
      isExplorePinnedOpen = true;
      isPinnedOpen = false;
      showExploreMenu();
    });
  }

  document.addEventListener('click', function (e) {
    if (!isPinnedOpen && !isExplorePinnedOpen) return;
    var clickInsideMenu = megaMenu.contains(e.target);
    var clickInsideExplore = exploreMenu ? exploreMenu.contains(e.target) : false;
    var clickOnNavItem = navItem.contains(e.target);
    var clickOnExploreNavItem = navItemExplore ? navItemExplore.contains(e.target) : false;
    if (!clickInsideMenu && !clickInsideExplore && !clickOnNavItem && !clickOnExploreNavItem) {
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
      if (exploreMenu) {
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
      }
    }
  });

  /* ------------------------------------------------------------------ */
  /*  Slider factory                                                      */
  /* ------------------------------------------------------------------ */
  function createSlider(track, prevId, nextId) {
    var prevBtn = document.getElementById(prevId);
    var nextBtn = document.getElementById(nextId);
    if (!track || !prevBtn || !nextBtn) return null;

    var index = 0;

    /* Width of one card + its trailing gap */
    function step() {
      var card = track.querySelector('.mega-card');
      if (!card) return 205;
      var gapStr = window.getComputedStyle(track).gap || '12px';
      var gap = parseFloat(gapStr) || 12;
      return card.offsetWidth + gap;
    }

    /* How many cards fit inside the viewport at once */
    function visible() {
      var vp = track.parentElement;
      return Math.max(1, Math.floor(vp.offsetWidth / step()));
    }

    function total() {
      return track.querySelectorAll('.mega-card').length;
    }

    function maxIdx() {
      return Math.max(0, total() - visible());
    }

    function go(newIdx) {
      index = Math.max(0, Math.min(newIdx, maxIdx()));
      track.style.transform = 'translateX(-' + index * step() + 'px)';

      /* Update button states */
      var atStart = index === 0;
      var atEnd = index >= maxIdx();

      prevBtn.disabled = atStart;
      prevBtn.setAttribute('aria-disabled', String(atStart));
      nextBtn.disabled = atEnd;
      nextBtn.setAttribute('aria-disabled', String(atEnd));
    }

    prevBtn.addEventListener('click', function () { go(index - 1); });
    nextBtn.addEventListener('click', function () { go(index + 1); });

    /* Reset to first card */
    function reset() { go(0); }

    function refresh() {
      go(index);
    }

    /* Initial render */
    reset();

    return { go: go, reset: reset, refresh: refresh };
  }

  function cardMarkup(item) {
    var cls = item.color === 'green' ? 'mega-card-green' : 'mega-card-blue';
    return '<a href="#" class="mega-card ' + cls + '">' + item.text + '</a>';
  }

  function setCards(track, cards) {
    track.innerHTML = cards.map(cardMarkup).join('');
  }

  function setActiveCategory(categoryId) {
    var data = menuData[categoryId];
    if (!data) return;
    categoryItems.forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-cat') === categoryId);
    });
    megaMenu.classList.remove('awaiting-selection');

    specialtyLabel.textContent = data.label;
    setCards(specialtyTrack, data.specialties);
    setCards(instrumentTrack, data.instruments);

    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  }

  /* ------------------------------------------------------------------ */
  /*  Initialise both sliders                                             */
  /* ------------------------------------------------------------------ */
  var specialtySlider = createSlider(
    specialtyTrack, 'specialty-prev', 'specialty-next'
  );
  var instrumentSlider = createSlider(
    instrumentTrack, 'instrument-prev', 'instrument-next'
  );

  categoryItems.forEach(function (item) {
    var categoryId = item.getAttribute('data-cat');
    if (!categoryId) return;
    item.addEventListener('mouseenter', function () {
      setActiveCategory(categoryId);
    });
    item.addEventListener('click', function () {
      setActiveCategory(categoryId);
    });
  });

  /* Reset sliders to position 0 every time the menu opens */
  navItem.addEventListener('mouseenter', function () {
    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  });

  /* Re-evaluate button states on resize */
  window.addEventListener('resize', function () {
    if (specialtySlider) specialtySlider.reset();
    if (instrumentSlider) instrumentSlider.reset();
  });

  /* ------------------------------------------------------------------ */
  /*  Hide mega menu on Escape key                                        */
  /* ------------------------------------------------------------------ */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
      megaMenu.classList.remove('active');
      megaMenu.setAttribute('aria-hidden', 'true');
      if (exploreMenu) {
        exploreMenu.classList.remove('active');
        exploreMenu.setAttribute('aria-hidden', 'true');
      }
    }
  });
})();
