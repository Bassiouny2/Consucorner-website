(function () {
  var subtotalEl = document.getElementById("summary-subtotal");
  var shippingEl = document.getElementById("summary-shipping");
  var vatEl = document.getElementById("summary-vat");
  var totalEl = document.getElementById("summary-total");
  var countEl = document.getElementById("cart-items-count");
  var clearButtons = Array.prototype.slice.call(
    document.querySelectorAll("[data-clear-cart]")
  );
  var pageCartEl = document.querySelector(".page-cart");
  var topPillEl = document.querySelector(".cart-top-pill");
  var emptyStateEl = document.getElementById("cart-empty-state");
  var listHeadEl = document.querySelector(".cart-list-head");
  var summaryCardEl = document.querySelector(".cart-summary-card");
  var checkoutBtn = document.querySelector(".cart-checkout-btn");

  var couponInput = document.getElementById("coupon-input");
  var couponApply = document.getElementById("coupon-apply");
  var couponRemove = document.getElementById("coupon-remove");
  var couponHint = document.getElementById("coupon-hint");
  var couponNote = document.getElementById("coupon-note");
  var preDiscountBlock = document.getElementById("summary-pre-discount");
  var beforeCouponEl = document.getElementById("summary-before-coupon");
  var couponDeductionEl = document.getElementById("summary-coupon-deduction");
  var couponDiscountLabel = document.getElementById("coupon-discount-label");

  var SHIPPING = 100;
  var VAT_RATE = 0.14;
  var VALID_COUPON = "MEDICAL10";
  var COUPON_DISCOUNT = 45;

  var couponApplied = false;
  var appliedCode = "";

  function num(text) {
    return Number(String(text).replace(/[^\d.]/g, "")) || 0;
  }

  function formatEgp(value) {
    return Math.round(value) + " EGP";
  }

  function formatEgpNegative(value) {
    return "-" + Math.round(value) + " EGP";
  }

  function setHint(msg, isError) {
    if (!couponHint) return;
    couponHint.textContent = msg || "";
    couponHint.hidden = !msg;
    couponHint.classList.toggle("coupon-hint--error", !!isError);
  }

  function updateCouponUI() {
    if (!couponInput || !couponApply || !couponRemove) return;

    if (couponApplied) {
      couponInput.value = appliedCode;
      couponInput.readOnly = true;
      couponInput.placeholder = "";
      couponApply.hidden = true;
      couponRemove.hidden = false;
      if (couponNote) {
        couponNote.textContent =
          appliedCode + " applied (-" + COUPON_DISCOUNT + " EGP)";
        couponNote.hidden = false;
      }
      setHint("");
      if (preDiscountBlock) preDiscountBlock.hidden = false;
    } else {
      couponInput.readOnly = false;
      couponInput.placeholder = "Enter coupon code";
      if (!couponInput.matches(":focus")) couponInput.value = "";
      couponApply.hidden = false;
      couponRemove.hidden = true;
      if (couponNote) couponNote.hidden = true;
      if (preDiscountBlock) preDiscountBlock.hidden = true;
    }
  }

  function getCartItems() {
    return Array.prototype.slice.call(document.querySelectorAll(".cart-item"));
  }

  function clearCouponState() {
    couponApplied = false;
    appliedCode = "";
    if (couponInput) couponInput.value = "";
    setHint("");
    updateCouponUI();
  }

  function updateEmptyState(isEmpty) {
    if (isEmpty && topPillEl) {
      topPillEl.remove();
      topPillEl = null;
    }
    if (pageCartEl) {
      pageCartEl.classList.toggle("cart-is-empty", isEmpty);
    }
    if (emptyStateEl) emptyStateEl.hidden = !isEmpty;
    if (listHeadEl) listHeadEl.hidden = isEmpty;
    if (summaryCardEl) summaryCardEl.hidden = isEmpty;
    if (checkoutBtn) checkoutBtn.hidden = isEmpty;
  }

  function recalc() {
    var subtotal = 0;
    var totalItems = 0;
    var cartItems = getCartItems();

    cartItems.forEach(function (item) {
      var qtyEl = item.querySelector(".qty-value");
      var qty = qtyEl ? Number(qtyEl.textContent) || 0 : 0;
      var unit = num(item.getAttribute("data-price"));
      subtotal += qty * unit;
      totalItems += qty;
    });

    var vat = subtotal * VAT_RATE;
    var shippingValue = totalItems ? SHIPPING : 0;
    var preCouponTotal = subtotal + shippingValue + vat;
    var discount = couponApplied ? COUPON_DISCOUNT : 0;
    var total = Math.max(0, preCouponTotal - discount);

    if (subtotalEl) subtotalEl.textContent = formatEgp(subtotal);
    if (shippingEl) shippingEl.textContent = formatEgp(shippingValue);
    if (vatEl) vatEl.textContent = formatEgp(vat);
    if (totalEl) totalEl.textContent = formatEgp(total);
    if (countEl) countEl.textContent = totalItems + " items";

    if (couponApplied && beforeCouponEl) {
      beforeCouponEl.textContent = formatEgp(preCouponTotal);
    }
    if (couponApplied && couponDeductionEl) {
      couponDeductionEl.textContent = formatEgpNegative(COUPON_DISCOUNT);
    }
    if (couponApplied && couponDiscountLabel) {
      couponDiscountLabel.textContent =
        "Coupon (" + appliedCode + ")";
    }

    updateEmptyState(!cartItems.length);
  }

  if (couponApply && couponInput) {
    couponApply.addEventListener("click", function () {
      var code = (couponInput.value || "").trim().toUpperCase();
      if (!code) {
        setHint("Enter a coupon code.", true);
        return;
      }
      if (code !== VALID_COUPON) {
        setHint("Invalid coupon code.", true);
        return;
      }
      couponApplied = true;
      appliedCode = code;
      updateCouponUI();
      recalc();
    });
  }

  if (couponRemove) {
    couponRemove.addEventListener("click", function () {
      clearCouponState();
      recalc();
    });
  }

  document.addEventListener("click", function (event) {
    var removeBtn = event.target.closest(".cart-remove");
    if (removeBtn) {
      var cartItem = removeBtn.closest(".cart-item");
      if (cartItem) {
        cartItem.remove();
        clearCouponState();
        recalc();
      }
      return;
    }

    var minus = event.target.closest(".qty-minus");
    var plus = event.target.closest(".qty-plus");
    if (!minus && !plus) return;

    var cartItem = event.target.closest(".cart-item");
    if (!cartItem) return;
    var qtyEl = cartItem.querySelector(".qty-value");
    if (!qtyEl) return;

    if (minus) {
      var minusQty = Number(qtyEl.textContent) || 1;
      qtyEl.textContent = String(Math.max(1, minusQty - 1));
      recalc();
      return;
    }

    var plusQty = Number(qtyEl.textContent) || 1;
    qtyEl.textContent = String(plusQty + 1);
    recalc();
  });

  clearButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      getCartItems().forEach(function (item) {
        item.remove();
      });
      clearCouponState();
      recalc();
    });
  });

  updateCouponUI();
  recalc();
})();
