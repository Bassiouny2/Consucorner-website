(function () {
  var navWrap = document.querySelector('.product-vendor-nav-btn-new-arrivals');
  if (!navWrap) return;

  var prevBtn = navWrap.querySelector('.slider-prev');
  var nextBtn = navWrap.querySelector('.slider-next');
  var banner = document.querySelector('.best-saler-section.slider');
  if (!banner || !prevBtn || !nextBtn) return;

  var imageWrapper = banner.querySelector('.product-slider-image-wrapper');
  var vendorItem = banner.querySelector('.vendor-slider-item');
  var titleEl = banner.querySelector('.product-slider-item-title');
  var bgWrapper = banner.querySelector('.product-slider-image-background-wrapper');
  if (!imageWrapper || !vendorItem || !titleEl || !bgWrapper) return;

  var rectBlue = bgWrapper.querySelector('img:first-child');
  var rectBaby = bgWrapper.querySelector('img:last-child');
  var productImg = imageWrapper.querySelector('img');
  var shopNowLink = banner.querySelector('.btn-shop-now');
  var assetBase = (window.consuNewArrivalData && window.consuNewArrivalData.assetBase) || 'assets/images/';

  var fallbackSlides = [
    {
      bg1: assetBase + 'Rectangle blue.svg',
      bg2: assetBase + 'Rectangle baby-blue.svg',
      productImage: assetBase + 'product-image-vendor.png',
      vendorLogo: assetBase + 'vendor-log.png',
      vendorName: 'LifeCare Surgical',
      title: 'Illumination head for Disposable Proctoscopy',
      btnText: 'Shop Now',
      link: '#'
    },
    {
      bg1: assetBase + 'Rectangle blue.svg',
      bg2: assetBase + 'Rectangle baby-blue.svg',
      productImage: assetBase + 'product-image-vendor 2.png',
      vendorLogo: assetBase + 'vendor-log 2.png',
      vendorName: 'MedTech Solutions',
      title: 'Advanced Surgical Forceps Kit',
      btnText: 'Shop Now',
      link: '#'
    },
    {
      bg1: assetBase + 'Rectangle blue.svg',
      bg2: assetBase + 'Rectangle baby-blue.svg',
      productImage: assetBase + 'product-image-vendor 3.png',
      vendorLogo: assetBase + 'vendor-log 3.png',
      vendorName: 'ProMed Instruments',
      title: 'Digital Otoscope System',
      btnText: 'Shop Now',
      link: '#'
    },
    {
      bg1: assetBase + 'Rectangle blue.svg',
      bg2: assetBase + 'Rectangle baby-blue.svg',
      productImage: assetBase + 'product-image-vendor 4.png',
      vendorLogo: assetBase + 'vendor-log 4.png',
      vendorName: 'SurgiCare Plus',
      title: 'Precision Dental Mirror Set',
      btnText: 'Shop Now',
      link: '#'
    }
  ];
  var dynamicSlides = window.consuNewArrivalData && Array.isArray(window.consuNewArrivalData.slides)
    ? window.consuNewArrivalData.slides
    : [];
  var slides = dynamicSlides.length ? dynamicSlides : fallbackSlides;

  var currentIndex = 0;
  var ANIM_IN = 2000;
  var STAGGER = 0;
  var CLICK_COOLDOWN = 420;
  var lastClickTime = 0;
  var timers = [];

  function setTimer(fn, ms) {
    var id = setTimeout(fn, ms);
    timers.push(id);
    return id;
  }

  function clearTimers() {
    while (timers.length) {
      clearTimeout(timers.pop());
    }
  }

  function clearAll(el) {
    el.classList.remove(
      'na-slide-out-left', 'na-slide-out-right',
      'na-slide-in-right', 'na-slide-in-left',
      'na-rect-out-top', 'na-rect-out-bottom',
      'na-rect-in-top', 'na-rect-in-bottom',
      'na-product-out', 'na-product-in', 'na-product-prep',
      'na-image-wrap-in'
    );
  }

  function restartAnimation(el, className) {
    el.classList.remove(className);
    // Force reflow so adding class always restarts once cleanly.
    void el.offsetWidth;
    el.classList.add(className);
  }

  function goToSlide(newIndex, direction) {
    var now = Date.now();
    if (now - lastClickTime < CLICK_COOLDOWN) return;
    lastClickTime = now;
    if (newIndex < 0 || newIndex >= slides.length) return;
    if (newIndex === currentIndex) return;
    clearTimers();

    var slide = slides[newIndex];
    var rightIn = 'na-slide-in-right';

    // Clear any previous animation classes first.
    clearAll(rectBlue);
    clearAll(rectBaby);
    clearAll(imageWrapper);
    clearAll(productImg);
    clearAll(vendorItem);
    clearAll(titleEl);

    // Swap content.
    if (slide.bg1) {
      rectBlue.src = slide.bg1;
    }
    if (slide.bg2) {
      rectBaby.src = slide.bg2;
    }
    productImg.src = slide.productImage;
    productImg.alt = slide.title;
    var vendorImg = vendorItem.querySelector('img');
    vendorImg.src = slide.vendorLogo;
    vendorImg.alt = slide.vendorName || '';
    vendorItem.querySelector('.vendor-slider-item-name').textContent = slide.vendorName;
    titleEl.textContent = slide.title;
    if (shopNowLink && slide.link) {
      shopNowLink.href = slide.link;
    }
    if (shopNowLink && slide.btnText) {
      shopNowLink.textContent = slide.btnText;
    }

    // --- IN phase only: all animations start together ---
    restartAnimation(rectBlue, 'na-rect-in-top');
    restartAnimation(rectBaby, 'na-rect-in-bottom');
    restartAnimation(imageWrapper, 'na-image-wrap-in');
    restartAnimation(vendorItem, rightIn);
    restartAnimation(titleEl, rightIn);

    currentIndex = newIndex;

    setTimer(function () {
      clearAll(rectBlue);
      clearAll(rectBaby);
      clearAll(imageWrapper);
      clearAll(productImg);
      clearAll(vendorItem);
      clearAll(titleEl);
    }, ANIM_IN + 120);
  }

  nextBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var next = (currentIndex + 1) % slides.length;
    goToSlide(next, 'next');
  });

  prevBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var prev = (currentIndex - 1 + slides.length) % slides.length;
    goToSlide(prev, 'prev');
  });
})();
