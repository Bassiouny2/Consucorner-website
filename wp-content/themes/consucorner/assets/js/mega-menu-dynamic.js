(function () {
  'use strict';

  var navItem = document.getElementById('nav-item-shop');
  var shopLink = navItem ? navItem.querySelector('.nav-link') : null;
  var megaMenu = document.getElementById('mega-menu');

  if (!navItem || !megaMenu) {
    return;
  }

  var navItemExplore = document.getElementById('nav-item-explore');
  var exploreLink = navItemExplore ? navItemExplore.querySelector('.nav-link') : null;
  var exploreMenu = document.getElementById('explore-mega-menu');
  var categoryItems = Array.prototype.slice.call(
    megaMenu.querySelectorAll('.mega-cat-item[data-category-id]')
  );
  var panels = Array.prototype.slice.call(
    megaMenu.querySelectorAll('.mega-category-panel[data-category-id]')
  );

  if (!categoryItems.length || !panels.length) {
    return;
  }

  var hideTimer;
  var exploreHideTimer;
  var hoverIntentTimer;
  var isPinnedOpen = false;
  var isExplorePinnedOpen = false;

  function hideExploreMenu() {
    if (!exploreMenu) {
      return;
    }

    exploreMenu.classList.remove('active');
    exploreMenu.setAttribute('aria-hidden', 'true');
    isExplorePinnedOpen = false;
  }

  function showMenu() {
    clearTimeout(hideTimer);
    clearTimeout(exploreHideTimer);
    hideExploreMenu();

    megaMenu.classList.add('active');
    megaMenu.setAttribute('aria-hidden', 'false');
  }

  function hideMenu() {
    megaMenu.classList.remove('active');
    megaMenu.setAttribute('aria-hidden', 'true');
  }

  function ensureDefaultCategoryActive() {
    var hasActive = categoryItems.some(function (item) {
      return item.classList.contains('active');
    });

    if (hasActive) {
      return;
    }

    var first = categoryItems[0];
    if (!first) {
      return;
    }

    var firstId = first.getAttribute('data-category-id');
    if (firstId) {
      setActiveCategory(firstId);
    }
  }

  function scheduleHide() {
    if (isPinnedOpen) {
      return;
    }

    hideTimer = setTimeout(hideMenu, 130);
  }

  function showExploreMenu() {
    if (!exploreMenu) {
      return;
    }

    clearTimeout(exploreHideTimer);
    clearTimeout(hideTimer);
    megaMenu.classList.remove('active');
    megaMenu.setAttribute('aria-hidden', 'true');
    isPinnedOpen = false;
    exploreMenu.classList.add('active');
    exploreMenu.setAttribute('aria-hidden', 'false');
  }

  function scheduleExploreHide() {
    if (!exploreMenu || isExplorePinnedOpen) {
      return;
    }

    exploreHideTimer = setTimeout(function () {
      exploreMenu.classList.remove('active');
      exploreMenu.setAttribute('aria-hidden', 'true');
    }, 130);
  }

  function setActiveCategory(categoryId) {
    categoryItems.forEach(function (item) {
      item.classList.toggle(
        'active',
        item.getAttribute('data-category-id') === categoryId
      );
    });

    panels.forEach(function (panel) {
      var isActive = panel.getAttribute('data-category-id') === categoryId;
      panel.classList.toggle('active', isActive);
      panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');

      if (isActive) {
        panel.querySelectorAll('.mega-viewport').forEach(function (viewport) {
          viewport.scrollLeft = 0;
        });
      }
    });
  }

  function setActiveCategoryWithIntent(categoryId) {
    clearTimeout(hoverIntentTimer);
    hoverIntentTimer = setTimeout(function () {
      setActiveCategory(categoryId);
    }, 80);
  }

  function bindScrollArrows(panel) {
    panel.querySelectorAll('.mega-slider-row').forEach(function (row) {
      var viewport = row.querySelector('.mega-viewport');
      var arrows = Array.prototype.slice.call(
        row.querySelectorAll('.mega-scroll-arrow[data-scroll-direction]')
      );

      if (!viewport || !arrows.length) {
        return;
      }

      arrows.forEach(function (arrow) {
        arrow.addEventListener('click', function () {
          var direction = arrow.getAttribute('data-scroll-direction') === 'prev' ? -1 : 1;
          var card = viewport.querySelector('.mega-card');
          var distance = card ? Math.max(card.offsetWidth + 16, viewport.clientWidth * 0.7) : 220;

          viewport.scrollBy({
            left: direction * distance,
            behavior: 'smooth'
          });
        });
      });
    });
  }

  navItem.addEventListener('mouseenter', showMenu);
  navItem.addEventListener('mouseleave', function () {
    if (!isPinnedOpen) {
      scheduleHide();
    }
  });
  megaMenu.addEventListener('mouseenter', showMenu);
  megaMenu.addEventListener('mouseleave', scheduleHide);

  if (shopLink) {
    shopLink.addEventListener('click', function (event) {
      if (window.matchMedia('(max-width: 1024px)').matches) {
        return;
      }

      // Keep the Shop label as a real link. Hover/focus opens the mega menu;
      // click navigates to the shop archive.
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
    });
  }

  if (navItemExplore && exploreMenu) {
    navItemExplore.addEventListener('mouseenter', showExploreMenu);
    navItemExplore.addEventListener('mouseleave', function () {
      if (!isExplorePinnedOpen) {
        scheduleExploreHide();
      }
    });
    exploreMenu.addEventListener('mouseenter', showExploreMenu);
    exploreMenu.addEventListener('mouseleave', scheduleExploreHide);
  }

  if (exploreLink && exploreMenu) {
    exploreLink.addEventListener('click', function (event) {
      if (window.matchMedia('(max-width: 1024px)').matches) {
        return;
      }

      event.preventDefault();
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

  categoryItems.forEach(function (item) {
    var categoryId = item.getAttribute('data-category-id');

    item.addEventListener('mouseenter', function () {
      setActiveCategoryWithIntent(categoryId);
    });

    item.addEventListener('focus', function () {
      clearTimeout(hoverIntentTimer);
      setActiveCategory(categoryId);
    });

    item.addEventListener('keydown', function (event) {
      if (event.key === ' ') {
        event.preventDefault();
        clearTimeout(hoverIntentTimer);
        setActiveCategory(categoryId);
      }
    });
  });

  panels.forEach(function (panel) {
    bindScrollArrows(panel);
  });

  ensureDefaultCategoryActive();

  document.addEventListener('click', function (event) {
    if (!isPinnedOpen && !isExplorePinnedOpen) {
      return;
    }

    var clickInsideMenu = megaMenu.contains(event.target);
    var clickInsideExplore = exploreMenu ? exploreMenu.contains(event.target) : false;
    var clickOnNavItem = navItem.contains(event.target);
    var clickOnExploreNavItem = navItemExplore ? navItemExplore.contains(event.target) : false;

    if (!clickInsideMenu && !clickInsideExplore && !clickOnNavItem && !clickOnExploreNavItem) {
      isPinnedOpen = false;
      isExplorePinnedOpen = false;
      hideMenu();
      hideExploreMenu();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }

    isPinnedOpen = false;
    isExplorePinnedOpen = false;
    hideMenu();
    hideExploreMenu();
  });
})();
