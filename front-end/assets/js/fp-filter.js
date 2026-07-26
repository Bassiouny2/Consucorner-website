(function () {
  var BRAND_LABEL = { 'acmed': 'ACMED', 'madhu': 'Madhu', 'katena': 'Katena', 'moria': 'Moria', 'duckworth': 'Duckworth' };

  var PRODUCTS = [
    // ── Collection 1 — 20 products ──
    { id:  1, collection:'1', name:'Capsulorhexis Forceps 0.12mm',    price: 1800, usageType:'grasping',        tipType:'standard', teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'spring-handle', material:'stainless-steel', tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id:  2, collection:'1', name:'Tying Forceps Standard',           price: 2100, usageType:'tying',           tipType:'standard', teeth:'no',  length:'120mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'cataract'  },
    { id:  3, collection:'1', name:'Tissue Forceps Angled',            price: 1650, usageType:'tissue-handling', tipType:'angled',   teeth:'yes', length:'100mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'glaucoma'  },
    { id:  4, collection:'1', name:'Holding Forceps Curved',           price: 2400, usageType:'holding',         tipType:'curved',   teeth:'no',  length:'140mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'cataract'  },
    { id:  5, collection:'1', name:'Dressing Forceps Micro',           price: 1950, usageType:'dressing',        tipType:'micro',    teeth:'yes', length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'glaucoma'  },
    { id:  6, collection:'1', name:'Grasping Forceps 100mm',           price: 2250, usageType:'grasping',        tipType:'standard', teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'cataract'  },
    { id:  7, collection:'1', name:'Micro Tip Angled Forceps',         price: 3100, usageType:'tissue-handling', tipType:'angled',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'glaucoma'  },
    { id:  8, collection:'1', name:'Spring Handle Tying Forceps',      price: 2800, usageType:'tying',           tipType:'curved',   teeth:'yes', length:'160mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'moria',     surgApp:'cataract'  },
    { id:  9, collection:'1', name:'Curved Tip Holding Forceps',       price: 2550, usageType:'holding',         tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'cataract'  },
    { id: 10, collection:'1', name:'Fine Dressing Standard 100mm',     price: 1750, usageType:'dressing',        tipType:'standard', teeth:'yes', length:'100mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 11, collection:'1', name:'Angled Grasping Titanium',         price: 3300, usageType:'grasping',        tipType:'angled',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 12, collection:'1', name:'Round Handle Tissue Forceps',      price: 2000, usageType:'tissue-handling', tipType:'standard', teeth:'yes', length:'120mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 13, collection:'1', name:'Micro Grasping Forceps 0.12mm',   price: 2700, usageType:'grasping',        tipType:'micro',    teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id: 14, collection:'1', name:'Tying Forceps Curved 140mm',       price: 2350, usageType:'tying',           tipType:'curved',   teeth:'yes', length:'140mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'katena',    surgApp:'glaucoma'  },
    { id: 15, collection:'1', name:'Holding Forceps Angled 160mm',     price: 2950, usageType:'holding',         tipType:'angled',   teeth:'no',  length:'160mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 16, collection:'1', name:'Standard Dressing Forceps',        price: 1600, usageType:'dressing',        tipType:'standard', teeth:'no',  length:'120mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 17, collection:'1', name:'Spring Handle Tissue 120mm',       price: 2200, usageType:'tissue-handling', tipType:'curved',   teeth:'yes', length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'duckworth', surgApp:'cataract'  },
    { id: 18, collection:'1', name:'Micro Tying Forceps 160mm',        price: 3050, usageType:'tying',           tipType:'micro',    teeth:'no',  length:'160mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 19, collection:'1', name:'Angled Dressing Titanium',         price: 2650, usageType:'dressing',        tipType:'angled',   teeth:'yes', length:'100mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'cataract'  },
    { id: 20, collection:'1', name:'Grasping Forceps 160mm Steel',     price: 2450, usageType:'grasping',        tipType:'standard', teeth:'no',  length:'160mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'moria',     surgApp:'glaucoma'  },

    // ── Collection 2 — 20 products ──
    { id: 21, collection:'2', name:'Titanium Capsulorhexis Pro',       price: 3500, usageType:'grasping',        tipType:'micro',    teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id: 22, collection:'2', name:'Standard Holding Forceps',         price: 1700, usageType:'holding',         tipType:'standard', teeth:'yes', length:'120mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 23, collection:'2', name:'Angled Grasping Forceps',          price: 2600, usageType:'grasping',        tipType:'angled',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'cataract'  },
    { id: 24, collection:'2', name:'Round Handle Dressing',            price: 1550, usageType:'dressing',        tipType:'standard', teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 25, collection:'2', name:'Tissue Handling Curved',           price: 2900, usageType:'tissue-handling', tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 26, collection:'2', name:'Micro Tying Forceps',              price: 3200, usageType:'tying',           tipType:'micro',    teeth:'yes', length:'160mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 27, collection:'2', name:'Curved Holding 140mm',             price: 2050, usageType:'holding',         tipType:'curved',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'duckworth', surgApp:'cataract'  },
    { id: 28, collection:'2', name:'Titanium Dressing Forceps',        price: 3750, usageType:'dressing',        tipType:'angled',   teeth:'yes', length:'120mm', func:'tying',         handle:'spring-handle', material:'titanium',        tipDim:'0.3mm',  brand:'katena',    surgApp:'glaucoma'  },
    { id: 29, collection:'2', name:'Spring Handle Grasping',           price: 2300, usageType:'grasping',        tipType:'standard', teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'spring-handle', material:'stainless-steel', tipDim:'0.12mm', brand:'madhu',     surgApp:'cataract'  },
    { id: 30, collection:'2', name:'Micro Dressing 100mm',             price: 2750, usageType:'dressing',        tipType:'micro',    teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'titanium',        tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 31, collection:'2', name:'Angled Tissue Handling',           price: 2150, usageType:'tissue-handling', tipType:'angled',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'spring-handle', material:'stainless-steel', tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 32, collection:'2', name:'Standard Tying Forceps',           price: 1850, usageType:'tying',           tipType:'standard', teeth:'yes', length:'120mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 33, collection:'2', name:'Titanium Holding 160mm',           price: 3100, usageType:'holding',         tipType:'micro',    teeth:'no',  length:'160mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'cataract'  },
    { id: 34, collection:'2', name:'Curved Grasping Titanium',         price: 2850, usageType:'grasping',        tipType:'curved',   teeth:'yes', length:'120mm', func:'tying',         handle:'round-handle',  material:'titanium',        tipDim:'0.3mm',  brand:'duckworth', surgApp:'glaucoma'  },
    { id: 35, collection:'2', name:'Standard Tissue Forceps',          price: 1950, usageType:'tissue-handling', tipType:'standard', teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'round-handle',  material:'stainless-steel', tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id: 36, collection:'2', name:'Angled Holding Forceps',           price: 2400, usageType:'holding',         tipType:'angled',   teeth:'yes', length:'140mm', func:'tying',         handle:'spring-handle', material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 37, collection:'2', name:'Micro Grasping 140mm',             price: 3350, usageType:'grasping',        tipType:'micro',    teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'round-handle',  material:'titanium',        tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 38, collection:'2', name:'Round Handle Tying 160mm',         price: 2200, usageType:'tying',           tipType:'curved',   teeth:'yes', length:'160mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'katena',    surgApp:'glaucoma'  },
    { id: 39, collection:'2', name:'Curved Dressing Forceps',          price: 2500, usageType:'dressing',        tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id: 40, collection:'2', name:'Fine Tissue Angled 160mm',         price: 3000, usageType:'tissue-handling', tipType:'angled',   teeth:'yes', length:'160mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'duckworth', surgApp:'glaucoma'  },

    // ── Collection 3 — 20 products ──
    { id: 41, collection:'3', name:'Premium Capsulorhexis Pro',        price: 4200, usageType:'grasping',        tipType:'micro',    teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'cataract'  },
    { id: 42, collection:'3', name:'Heavy Duty Tying Forceps',         price: 2750, usageType:'tying',           tipType:'standard', teeth:'yes', length:'140mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 43, collection:'3', name:'Glaucoma Tissue Forceps',          price: 3100, usageType:'tissue-handling', tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'glaucoma'  },
    { id: 44, collection:'3', name:'Angled Holding Forceps',           price: 1900, usageType:'holding',         tipType:'angled',   teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'moria',     surgApp:'cataract'  },
    { id: 45, collection:'3', name:'Fine Dressing Micro Tip',          price: 2300, usageType:'dressing',        tipType:'micro',    teeth:'no',  length:'160mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'duckworth', surgApp:'glaucoma'  },
    { id: 46, collection:'3', name:'Stainless Grasping 160mm',         price: 2600, usageType:'grasping',        tipType:'standard', teeth:'yes', length:'160mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'cataract'  },
    { id: 47, collection:'3', name:'Titanium Tying 120mm',             price: 3900, usageType:'tying',           tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'acmed',     surgApp:'glaucoma'  },
    { id: 48, collection:'3', name:'Ergonomic Tissue Forceps',         price: 2850, usageType:'tissue-handling', tipType:'angled',   teeth:'yes', length:'140mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'katena',    surgApp:'cataract'  },
    { id: 49, collection:'3', name:'Curved Grasping Pro 120mm',        price: 3450, usageType:'grasping',        tipType:'curved',   teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 50, collection:'3', name:'Standard Holding 100mm',           price: 2050, usageType:'holding',         tipType:'standard', teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'madhu',     surgApp:'glaucoma'  },
    { id: 51, collection:'3', name:'Micro Tissue Forceps',             price: 3250, usageType:'tissue-handling', tipType:'micro',    teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'duckworth', surgApp:'cataract'  },
    { id: 52, collection:'3', name:'Angled Tying 160mm',               price: 2900, usageType:'tying',           tipType:'angled',   teeth:'yes', length:'160mm', func:'tying',         handle:'round-handle',  material:'stainless-steel', tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 53, collection:'3', name:'Spring Handle Dressing 140mm',     price: 2450, usageType:'dressing',        tipType:'curved',   teeth:'no',  length:'140mm', func:'capsulorhexis', handle:'spring-handle', material:'titanium',        tipDim:'0.12mm', brand:'katena',    surgApp:'cataract'  },
    { id: 54, collection:'3', name:'Round Handle Grasping Micro',      price: 3600, usageType:'grasping',        tipType:'micro',    teeth:'yes', length:'100mm', func:'tying',         handle:'round-handle',  material:'titanium',        tipDim:'0.3mm',  brand:'moria',     surgApp:'glaucoma'  },
    { id: 55, collection:'3', name:'Standard Dressing 160mm',          price: 1800, usageType:'dressing',        tipType:'standard', teeth:'no',  length:'160mm', func:'capsulorhexis', handle:'round-handle',  material:'stainless-steel', tipDim:'0.12mm', brand:'madhu',     surgApp:'cataract'  },
    { id: 56, collection:'3', name:'Curved Holding Titanium',          price: 3150, usageType:'holding',         tipType:'curved',   teeth:'yes', length:'120mm', func:'tying',         handle:'spring-handle', material:'titanium',        tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  },
    { id: 57, collection:'3', name:'Angled Grasping 100mm',            price: 2700, usageType:'grasping',        tipType:'angled',   teeth:'no',  length:'100mm', func:'capsulorhexis', handle:'round-handle',  material:'stainless-steel', tipDim:'0.12mm', brand:'duckworth', surgApp:'cataract'  },
    { id: 58, collection:'3', name:'Micro Holding Forceps',            price: 3800, usageType:'holding',         tipType:'micro',    teeth:'yes', length:'140mm', func:'tying',         handle:'spring-handle', material:'titanium',        tipDim:'0.3mm',  brand:'katena',    surgApp:'glaucoma'  },
    { id: 59, collection:'3', name:'Standard Tissue 120mm',            price: 2100, usageType:'tissue-handling', tipType:'standard', teeth:'no',  length:'120mm', func:'capsulorhexis', handle:'round-handle',  material:'stainless-steel', tipDim:'0.12mm', brand:'moria',     surgApp:'cataract'  },
    { id: 60, collection:'3', name:'Titanium Dressing Angled',         price: 3550, usageType:'dressing',        tipType:'angled',   teeth:'yes', length:'160mm', func:'tying',         handle:'spring-handle', material:'titanium',        tipDim:'0.3mm',  brand:'acmed',     surgApp:'glaucoma'  }
  ];

  // Filter state
  var state = {
    collection: '1',
    usageType: '',
    tipType: '',
    teeth: '',
    length: '',
    func: [],
    handle: [],
    material: [],
    tipDim: [],
    brand: [],
    surgApp: [],
    maxPrice: 150000
  };

  function cardHTML(p) {
    var brandLabel = BRAND_LABEL[p.brand] || p.brand;
    return '<div class="card-shop" data-href="single-product.html">' +
      '<div class="card-shop-img-wrapper">' +
        '<img src="assets/images/blog image s.png" alt="' + p.name + '" />' +
        '<span class="fp-card-company-badge">' + brandLabel + '</span>' +
      '</div>' +
      '<div class="card-shop-body">' +
        '<h3 class="product-card-title">' + p.name + '</h3>' +
        '<div class="priceing">' +
          '<p class="price">' + p.price.toLocaleString() + '</p>' +
          '<p class="currency">EGP</p>' +
        '</div>' +
        '<div class="product-card-btn">' +
          '<div class="product-card-btn-left">' +
            '<a href="#" class="btn-add-cart"' +
            ' data-product-id="' + p.id + '"' +
            ' data-product-name="' + p.name.replace(/"/g, '&quot;') + '"' +
            ' data-product-price="' + p.price + '"' +
            ' data-product-image="assets/images/blog image s.png"' +
            ' onclick="event.stopPropagation()">Add to cart</a>' +
            '<a href="#" class="btn-save" onclick="event.stopPropagation()"><img src="assets/images/save-product-icon.svg" alt="Save" /></a>' +
          '</div>' +
          '<a href="#" class="btn-compare" onclick="event.stopPropagation()"><img src="assets/images/Show-icon.svg" alt="Quick View" /></a>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function renderGrid() {
    var grid = document.getElementById('fpGrid');
    if (!grid) return;

    var filtered = PRODUCTS.filter(function (p) {
      if (p.collection !== state.collection) return false;
      if (state.usageType && p.usageType !== state.usageType) return false;
      if (state.tipType  && p.tipType  !== state.tipType)  return false;
      if (state.teeth    && p.teeth    !== state.teeth)    return false;
      if (state.length   && p.length   !== state.length)   return false;
      if (state.func.length     && state.func.indexOf(p.func)         === -1) return false;
      if (state.handle.length   && state.handle.indexOf(p.handle)     === -1) return false;
      if (state.material.length && state.material.indexOf(p.material) === -1) return false;
      if (state.tipDim.length   && state.tipDim.indexOf(p.tipDim)     === -1) return false;
      if (state.brand.length    && state.brand.indexOf(p.brand)       === -1) return false;
      if (state.surgApp.length  && state.surgApp.indexOf(p.surgApp)   === -1) return false;
      if (p.price > state.maxPrice) return false;
      return true;
    });

    if (filtered.length === 0) {
      grid.innerHTML = '<p class="fp-no-results">No products match your filters.</p>';
    } else {
      grid.innerHTML = filtered.map(cardHTML).join('');
    }
  }

  function init() {
    // Collection tabs
    document.querySelectorAll('.fp-collection-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.fp-collection-tab').forEach(function (t) {
          t.classList.remove('fp-collection-tab--active');
        });
        this.classList.add('fp-collection-tab--active');
        state.collection = this.dataset.collection;
        renderGrid();
      });
    });

    // Top filter tag buttons — toggle on/off
    document.querySelectorAll('.fp-filter-tag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = this.dataset.filterKey;
        var val = this.dataset.filterVal;
        if (!key || !val) return;

        if (this.classList.contains('fp-filter-tag-btn--active')) {
          this.classList.remove('fp-filter-tag-btn--active');
          state[key] = '';
        } else {
          document.querySelectorAll('.fp-filter-tag-btn[data-filter-key="' + key + '"]').forEach(function (b) {
            b.classList.remove('fp-filter-tag-btn--active');
          });
          this.classList.add('fp-filter-tag-btn--active');
          state[key] = val;
        }
        renderGrid();
      });
    });

    // Advanced filter checkboxes
    document.querySelectorAll('.fp-checkbox').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var key = this.dataset.filterKey;
        var val = this.dataset.filterVal;
        if (!key || !val) return;
        var arr = state[key];
        if (!Array.isArray(arr)) return;

        if (this.checked) {
          if (arr.indexOf(val) === -1) arr.push(val);
        } else {
          var idx = arr.indexOf(val);
          if (idx !== -1) arr.splice(idx, 1);
        }
        renderGrid();
      });
    });

    // Price slider
    var slider = document.getElementById('fpPriceSlider');
    var display = document.getElementById('fpPriceDisplay');
    if (slider && display) {
      slider.addEventListener('input', function () {
        state.maxPrice = Number(this.value);
        display.textContent = 'Price: ' + Number(this.value).toLocaleString('en-EG') + ' EGP';
        renderGrid();
      });
    }

    // Clear All
    var clearBtn = document.querySelector('.fp-clear-all');
    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        state.usageType = '';
        state.tipType = '';
        state.teeth = '';
        state.length = '';
        state.func = [];
        state.handle = [];
        state.material = [];
        state.tipDim = [];
        state.brand = [];
        state.surgApp = [];
        state.maxPrice = 150000;

        document.querySelectorAll('.fp-filter-tag-btn').forEach(function (b) {
          b.classList.remove('fp-filter-tag-btn--active');
        });
        document.querySelectorAll('.fp-checkbox').forEach(function (cb) {
          cb.checked = false;
        });
        if (slider && display) {
          slider.value = 150000;
          display.textContent = 'Price: 150,000 EGP';
        }
        renderGrid();
      });
    }

    // Filter bar scroll shadows
    var filterBarLeft = document.querySelector('.fp-filter-bar-left');
    function updateFilterBarMask() {
      if (!filterBarLeft) return;
      var scrollLeft = filterBarLeft.scrollLeft;
      var maxScroll = filterBarLeft.scrollWidth - filterBarLeft.clientWidth;
      var atStart = scrollLeft <= 2;
      var atEnd = scrollLeft >= maxScroll - 2;

      var mask;
      if (atStart && atEnd) {
        mask = 'none';
      } else if (atStart) {
        mask = 'linear-gradient(to right, black 75%, transparent 100%)';
      } else if (atEnd) {
        mask = 'linear-gradient(to right, transparent 0%, black 25%, black 100%)';
      } else {
        mask = 'linear-gradient(to right, transparent 0%, black 15%, black 75%, transparent 100%)';
      }
      filterBarLeft.style.maskImage = mask;
      filterBarLeft.style.webkitMaskImage = mask;
    }

    if (filterBarLeft) {
      filterBarLeft.addEventListener('scroll', updateFilterBarMask);
      window.addEventListener('resize', updateFilterBarMask);
      updateFilterBarMask();
    }

    // Collection tabs scroll shadows
    var collectionTabs = document.querySelector('.fp-collection-tabs');
    function updateTabsMask() {
      if (!collectionTabs) return;
      var scrollLeft = collectionTabs.scrollLeft;
      var maxScroll = collectionTabs.scrollWidth - collectionTabs.clientWidth;
      var atStart = scrollLeft <= 2;
      var atEnd = scrollLeft >= maxScroll - 2;

      var mask;
      if (atStart && atEnd) {
        mask = 'none';
      } else if (atStart) {
        mask = 'linear-gradient(to right, black 75%, transparent 100%)';
      } else if (atEnd) {
        mask = 'linear-gradient(to right, transparent 0%, black 25%, black 100%)';
      } else {
        mask = 'linear-gradient(to right, transparent 0%, black 15%, black 75%, transparent 100%)';
      }
      collectionTabs.style.maskImage = mask;
      collectionTabs.style.webkitMaskImage = mask;
    }

    if (collectionTabs) {
      collectionTabs.addEventListener('scroll', updateTabsMask);
      window.addEventListener('resize', updateTabsMask);
      updateTabsMask();
    }

    // Mobile sidebar toggle
    var filterToggle = document.getElementById('fpFilterToggle');
    var sidebar = document.querySelector('.fp-sidebar');
    var sidebarOverlay = document.getElementById('fpSidebarOverlay');

    function openSidebar() {
      if (sidebar) sidebar.classList.add('open');
      if (sidebarOverlay) sidebarOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      if (sidebar) sidebar.classList.remove('open');
      if (sidebarOverlay) sidebarOverlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    if (filterToggle) filterToggle.addEventListener('click', openSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Sidebar banner slider (same behaviour as hero-banner in index.html)
    var heroSlides = document.querySelectorAll('.fp-sidebar-banner .hero-slide');
    if (heroSlides.length > 0) {
      var heroIndex = 0;
      setInterval(function () {
        heroSlides[heroIndex].classList.remove('active');
        heroIndex = (heroIndex + 1) % heroSlides.length;
        heroSlides[heroIndex].classList.add('active');
      }, 4500);
    }

    // Card click — navigate to product page via data-href
    var fpGrid = document.getElementById('fpGrid');
    if (fpGrid) {
      fpGrid.addEventListener('click', function (e) {
        var card = e.target.closest('[data-href]');
        if (card) {
          window.location.href = card.dataset.href;
        }
      });
    }

    renderGrid();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
