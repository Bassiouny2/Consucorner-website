(function () {
  /* ── Payment method toggle ─────────────────────────────── */
  var payCard = document.getElementById('pay-card');
  var payCod  = document.getElementById('pay-cod');
  var cardDetails = document.getElementById('co-card-details');
  var submitBtn   = document.getElementById('co-submit-btn');

  function setMethod(method) {
    if (method === 'card') {
      payCard.classList.add('co-pay-btn--active');
      payCod.classList.remove('co-pay-btn--active');
      cardDetails.classList.remove('co-card-details--hidden');
      submitBtn.textContent = 'Pay 5,118 EGP';
    } else {
      payCod.classList.add('co-pay-btn--active');
      payCard.classList.remove('co-pay-btn--active');
      cardDetails.classList.add('co-card-details--hidden');
      submitBtn.textContent = 'Place Order \u2013 Cash on Delivery';
    }
  }

  if (payCard) payCard.addEventListener('click', function () { setMethod('card'); });
  if (payCod)  payCod.addEventListener('click',  function () { setMethod('cod');  });

  /* ── Helper: detect browser autofill (fills many chars at once) ── */
  function isAutofill(el, prevLen) {
    return Math.abs(el.value.length - prevLen) > 1;
  }

  /* ── Card number formatting (skip if autofilled) ────────── */
  var cardNumberInput = document.getElementById('co-card-number');
  if (cardNumberInput) {
    var prevCardLen = 0;
    cardNumberInput.addEventListener('focus', function () {
      prevCardLen = this.value.length;
    });
    cardNumberInput.addEventListener('input', function () {
      if (isAutofill(this, prevCardLen)) {
        prevCardLen = this.value.length;
        return;
      }
      var raw = this.value.replace(/\D/g, '').slice(0, 16);
      this.value = raw.replace(/(.{4})/g, '$1 ').trim();
      prevCardLen = this.value.length;
    });
  }

  /* ── Expiry formatting (skip if autofilled) ─────────────── */
  var expiryInput = document.getElementById('co-expiry');
  if (expiryInput) {
    var prevExpLen = 0;
    expiryInput.addEventListener('focus', function () {
      prevExpLen = this.value.length;
    });
    expiryInput.addEventListener('input', function () {
      if (isAutofill(this, prevExpLen)) {
        prevExpLen = this.value.length;
        return;
      }
      var val = this.value.replace(/\D/g, '').slice(0, 4);
      if (val.length > 2) val = val.slice(0, 2) + '/' + val.slice(2);
      this.value = val;
      prevExpLen = this.value.length;
    });
  }

  /* ── CVV: digits only (skip if autofilled) ──────────────── */
  var cvvInput = document.getElementById('co-cvv');
  if (cvvInput) {
    var prevCvvLen = 0;
    cvvInput.addEventListener('focus', function () {
      prevCvvLen = this.value.length;
    });
    cvvInput.addEventListener('input', function () {
      if (isAutofill(this, prevCvvLen)) {
        prevCvvLen = this.value.length;
        return;
      }
      this.value = this.value.replace(/\D/g, '').slice(0, 4);
      prevCvvLen = this.value.length;
    });
  }

  /* ── Handle Chrome/Safari autofill change event ─────────── */
  document.querySelectorAll('#checkout-form .co-input').forEach(function (el) {
    el.addEventListener('change', function () {
      this.style.borderColor = '';
      this.classList.add('co-autofilled');
    });
  });

  /* ── Form validation ────────────────────────────────────── */
  var form = document.getElementById('checkout-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var required = [
        { id: 'co-email',      label: 'Email' },
        { id: 'co-phone',      label: 'Phone Number' },
        { id: 'co-first-name', label: 'First Name' },
        { id: 'co-last-name',  label: 'Last Name' },
        { id: 'co-address',    label: 'Shipping Address' },
        { id: 'co-city',       label: 'City' },
        { id: 'co-gov',        label: 'Governorate' }
      ];

      var isCard = payCard && payCard.classList.contains('co-pay-btn--active');
      if (isCard) {
        required.push(
          { id: 'co-card-number', label: 'Card Number' },
          { id: 'co-expiry',      label: 'Expiry Date' },
          { id: 'co-cvv',         label: 'CVV' }
        );
      }

      var firstError = null;
      required.forEach(function (field) {
        var el = document.getElementById(field.id);
        if (!el) return;
        var empty = el.value.trim() === '' || el.value === 'Select' || el.value === '';
        el.style.borderColor = empty ? '#ef4444' : '';
        if (empty && !firstError) firstError = el;
      });

      if (firstError) {
        firstError.focus();
        return;
      }

      var btn = document.getElementById('co-submit-btn');
      if (btn) {
        btn.textContent = 'Order Placed \u2713';
        btn.style.background = '#10b981';
        btn.disabled = true;
      }
    });

    form.querySelectorAll('.co-input').forEach(function (el) {
      el.addEventListener('input', function () {
        this.style.borderColor = '';
      });
    });
  }
})();
