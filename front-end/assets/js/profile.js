/**
 * profile.js — ConsuCorner My Account
 *
 * ── WooCommerce REST API field names are used as `name` attributes ──────────
 *   Account details  →  PUT  /wp-json/wc/v3/customers/{id}
 *   Top-up           →  POST /wp-json/custom/v1/wallet/topup
 *   Privacy prefs    →  POST /wp-json/custom/v1/privacy
 *   Notifications    →  POST /wp-json/custom/v1/notifications
 *   Password change  →  POST /wp-json/custom/v1/account/password
 *   Support ticket   →  POST /wp-json/custom/v1/support/ticket
 *
 * ── Plugin / extension API ──────────────────────────────────────────────────
 *   window.CCProfile.registerMenuItem(options)   — add a menu item
 *   window.CCProfile.registerModal(options)      — add a full popup modal
 *   window.CCProfile.openModal(id)               — open any modal by id
 *   window.CCProfile.closeModal(id)              — close any modal by id
 *   window.CCProfile.showToast(msg, type)        — show success/error toast
 *   window.CCProfile.getState()                  — read current profile state
 *   window.CCProfile.setState(partial)           — merge & persist state
 * ────────────────────────────────────────────────────────────────────────────
 */

(() => {
  "use strict";

  /* ─────────────────────────────────────────────
     STATE (localStorage-backed)
  ───────────────────────────────────────────── */

  const STORAGE_KEY = "cc_profile_v2";

  const DEFAULT_STATE = {
    first_name: "Alexa",
    last_name: "Rawles",
    display_name: "Alexa Rawles",
    username: "alexa.rawles",
    email: "alexarawles@gmail.com",
    billing_phone: "+20 100 000 0000",
    meta_birth_date: "",
    meta_gender: "",
    meta_specialty: "ophthalmology",
    meta_role_title: "doctor",
    billing_company: "",
    billing_first_name: "Alexa",
    billing_last_name: "Rawles",
    billing_address_1: "",
    billing_address_2: "",
    billing_city: "Cairo",
    billing_state: "Cairo Governorate",
    billing_postcode: "11511",
    billing_country: "EG",
    billing_email: "",
    billing_phone_billing: "",
    shipping_first_name: "Alexa",
    shipping_last_name: "Rawles",
    shipping_company: "",
    shipping_address_1: "",
    shipping_address_2: "",
    shipping_city: "Cairo",
    shipping_state: "Cairo Governorate",
    shipping_postcode: "11511",
    shipping_country: "EG",
    shipping_phone: "",
    avatarDataUrl: "",
  };

  function loadState() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? { ...DEFAULT_STATE, ...JSON.parse(raw) } : { ...DEFAULT_STATE };
    } catch (_) {
      return { ...DEFAULT_STATE };
    }
  }

  const state = loadState();

  function saveState(partial) {
    Object.assign(state, partial);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  }

  /* ─────────────────────────────────────────────
     HELPERS
  ───────────────────────────────────────────── */

  function getInitials(name) {
    const parts = String(name || "").trim().split(/\s+/).slice(0, 2);
    return parts.map((p) => p[0] && p[0].toUpperCase()).join("") || "CC";
  }

  function formDataToObject(form) {
    const obj = {};
    new FormData(form).forEach((v, k) => { obj[k] = v; });
    return obj;
  }

  /* ─────────────────────────────────────────────
     TOAST NOTIFICATION
  ───────────────────────────────────────────── */

  function showToast(msg, type = "success") {
    const old = document.getElementById("cc-toast");
    if (old) old.remove();
    const t = document.createElement("div");
    t.id = "cc-toast";
    t.textContent = msg;
    t.style.cssText = [
      "position:fixed;bottom:26px;right:22px;z-index:3000",
      `background:${type === "success" ? "#1db39a" : "#e04040"}`,
      "color:#fff;border-radius:12px;padding:12px 20px",
      "font-size:14px;font-weight:700",
      "box-shadow:0 8px 28px -8px rgba(0,0,0,0.45)",
      "max-width:320px;line-height:1.4",
      "animation:pmodal-enter .28s cubic-bezier(.34,1.1,.64,1)",
    ].join(";");
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  /* ─────────────────────────────────────────────
     MODAL OPEN / CLOSE
  ───────────────────────────────────────────── */

  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    requestAnimationFrame(() => {
      const focusTarget =
        modal.querySelector(".pmodal-close") ||
        modal.querySelector("button, [href], input, select, textarea");
      if (focusTarget) focusTarget.focus({ preventScroll: true });
    });
  }

  function closeModal(modalOrId) {
    const modal =
      typeof modalOrId === "string"
        ? document.getElementById(modalOrId)
        : modalOrId instanceof HTMLElement
        ? modalOrId
        : document.querySelector(".pmodal.is-open");
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".pmodal.is-open")) {
      document.body.style.overflow = "";
    }
  }

  function closeAllModals() {
    document.querySelectorAll(".pmodal.is-open").forEach(closeModal);
    document.body.style.overflow = "";
  }

  /* ── Wire close triggers ── */

  // 1. Header ✕ buttons (.pmodal-close) — THIS was the bug: they were missing handlers
  document.addEventListener("click", (e) => {
    const closeBtn = e.target.closest(".pmodal-close");
    if (closeBtn) {
      const modal = closeBtn.closest(".pmodal");
      if (modal) closeModal(modal);
    }
  });

  // 2. [data-dismiss] footer Cancel/Close buttons
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-dismiss]");
    if (btn) {
      const modal = btn.closest(".pmodal");
      if (modal) closeModal(modal);
    }
  });

  // 3. Backdrop click
  document.addEventListener("click", (e) => {
    if (e.target.classList.contains("pmodal-backdrop")) {
      const modal = e.target.closest(".pmodal");
      if (modal) closeModal(modal);
    }
  });

  // 4. Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeAllModals();
  });

  /* ── Menu items → open modal ── */
  document.addEventListener("click", (e) => {
    const item = e.target.closest(".profile-item[data-modal]");
    if (!item) return;
    e.preventDefault();
    const id = item.dataset.modal;
    if (id === "modal-account") fillAccountForm();
    openModal(id);
  });

  /* ─────────────────────────────────────────────
     TAB SWITCHING (inside modals)
  ───────────────────────────────────────────── */

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".pmodal-tab-btn");
    if (!btn) return;
    const dialog = btn.closest(".pmodal-dialog");
    if (!dialog) return;
    const target = btn.dataset.tab;
    dialog.querySelectorAll(".pmodal-tab-btn").forEach((b) => {
      b.classList.toggle("is-active", b === btn);
      b.setAttribute("aria-selected", b === btn ? "true" : "false");
    });
    dialog.querySelectorAll(".pmodal-tab-panel").forEach((p) => {
      p.classList.toggle("is-active", p.dataset.tabPanel === target);
    });
  });

  /* ─────────────────────────────────────────────
     ORDER STATUS FILTER
  ───────────────────────────────────────────── */

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".porder-filter-btn");
    if (!btn) return;
    document.querySelectorAll(".porder-filter-btn").forEach((b) => {
      b.classList.remove("is-active");
      b.setAttribute("aria-selected", "false");
    });
    btn.classList.add("is-active");
    btn.setAttribute("aria-selected", "true");
    const filter = btn.dataset.filter;
    document.querySelectorAll("#orders-tbody tr[data-status]").forEach((row) => {
      row.style.display = filter === "all" || row.dataset.status === filter ? "" : "none";
    });
  });

  /* ─────────────────────────────────────────────
     PROFILE HEADER — render from state
  ───────────────────────────────────────────── */

  function renderHeader() {
    const nameEl  = document.getElementById("profile-user-name");
    const emailEl = document.getElementById("profile-user-email");
    const avatarEl = document.getElementById("profile-avatar-display");

    const displayName =
      state.display_name ||
      `${state.first_name} ${state.last_name}`.trim() ||
      "My Account";

    if (nameEl)  nameEl.textContent  = displayName;
    if (emailEl) emailEl.textContent = state.email;

    if (avatarEl) {
      if (state.avatarDataUrl) {
        avatarEl.style.backgroundImage = `url("${state.avatarDataUrl}")`;
        avatarEl.style.backgroundSize  = "cover";
        avatarEl.textContent = "";
      } else {
        avatarEl.style.backgroundImage = "none";
        avatarEl.textContent = getInitials(displayName);
      }
    }
  }

  /* ─────────────────────────────────────────────
     AVATAR UPLOAD
  ───────────────────────────────────────────── */

  const avatarBtn   = document.getElementById("profile-avatar-btn");
  const avatarInput = document.getElementById("profile-avatar-input");

  if (avatarBtn && avatarInput) {
    avatarBtn.addEventListener("click", () => avatarInput.click());
    avatarInput.addEventListener("change", () => {
      const file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        saveState({ avatarDataUrl: String(reader.result) });
        renderHeader();
        showToast("Profile photo updated.");
      };
      reader.readAsDataURL(file);
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 1 — ACCOUNT DETAILS
  ───────────────────────────────────────────── */

  const ACCOUNT_FIELDS = [
    "first_name","last_name","display_name","username","email",
    "billing_phone","meta_birth_date","meta_gender","meta_specialty",
    "meta_role_title","billing_company",
    "billing_first_name","billing_last_name","billing_address_1",
    "billing_address_2","billing_city","billing_state","billing_postcode",
    "billing_country","billing_email",
    "shipping_first_name","shipping_last_name","shipping_company",
    "shipping_address_1","shipping_address_2","shipping_city",
    "shipping_state","shipping_postcode","shipping_country","shipping_phone",
  ];

  function fillAccountForm() {
    const form = document.getElementById("form-account");
    if (!form) return;
    ACCOUNT_FIELDS.forEach((key) => {
      const el = form.elements.namedItem(key);
      if (el) el.value = state[key] || "";
    });
  }

  const formAccount = document.getElementById("form-account");
  if (formAccount) {
    formAccount.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = formDataToObject(formAccount);
      saveState(data);
      renderHeader();
      closeModal("modal-account");
      showToast("Account details saved.");
      /*
       * ── WooCommerce backend wiring ────────────────────────────
       * const endpoint = formAccount.dataset.wcEndpoint
       *   .replace("{id}", window.CC_CUSTOMER_ID);
       * fetch(endpoint, {
       *   method: "PUT",
       *   headers: {
       *     "Content-Type": "application/json",
       *     Authorization: "Bearer " + window.CC_TOKEN,
       *   },
       *   body: JSON.stringify(data),
       * }).then(r => r.json()).then(handleWCResponse);
       * ─────────────────────────────────────────────────────────
       */
    });
  }

  /* "Same as billing" toggle for shipping fields */
  const sameAsBilling  = document.getElementById("ship-same-as-billing");
  const shippingWrap   = document.getElementById("shipping-fields-wrap");

  if (sameAsBilling && shippingWrap) {
    sameAsBilling.addEventListener("change", () => {
      const lock = sameAsBilling.checked;
      shippingWrap.style.opacity = lock ? "0.4" : "1";
      shippingWrap.querySelectorAll("input, select").forEach((el) => {
        el.disabled = lock;
      });
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 2 — WALLET
  ───────────────────────────────────────────── */

  const btnShowTopup   = document.getElementById("btn-show-topup");
  const btnCancelTopup = document.getElementById("btn-cancel-topup");
  const topupSection   = document.getElementById("wallet-topup-section");

  if (btnShowTopup && topupSection) {
    btnShowTopup.addEventListener("click", () => {
      topupSection.hidden = false;
      topupSection.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  }
  if (btnCancelTopup && topupSection) {
    btnCancelTopup.addEventListener("click", () => { topupSection.hidden = true; });
  }

  const formTopup = document.getElementById("form-topup");
  if (formTopup) {
    formTopup.addEventListener("submit", (e) => {
      e.preventDefault();
      const d = formDataToObject(formTopup);
      if (!d.amount || Number(d.amount) < 10) {
        showToast("Minimum top-up amount is 10 EGP.", "error");
        return;
      }
      formTopup.reset();
      if (topupSection) topupSection.hidden = true;
      showToast(`Top-up of ${Number(d.amount).toLocaleString()} EGP requested.`);
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 4 — PRIVACY
  ───────────────────────────────────────────── */

  const formPrivacy = document.getElementById("form-privacy");
  if (formPrivacy) {
    formPrivacy.addEventListener("submit", (e) => {
      e.preventDefault();
      closeModal("modal-privacy");
      showToast("Privacy preferences saved.");
    });
  }

  const btnDeleteAccount = document.getElementById("btn-delete-account");
  if (btnDeleteAccount) {
    btnDeleteAccount.addEventListener("click", () => {
      const ok = window.confirm(
        "Are you sure you want to permanently delete your account?\nThis cannot be undone."
      );
      if (!ok) return;
      localStorage.removeItem(STORAGE_KEY);
      closeAllModals();
      showToast("Account deletion request submitted. You will receive a confirmation email.");
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 5 — WISHLIST
  ───────────────────────────────────────────── */

  function checkWishlistEmpty() {
    const grid  = document.getElementById("wishlist-grid");
    const empty = document.getElementById("wishlist-empty");
    if (!grid || !empty) return;
    const count = grid.querySelectorAll(".pwishlist-item").length;
    grid.hidden  = count === 0;
    empty.hidden = count !== 0;
  }

  // Delegated remove & add-to-cart (works for dynamically added items too)
  document.addEventListener("click", (e) => {
    const removeBtn = e.target.closest(".pwishlist-remove");
    if (removeBtn) {
      const item = removeBtn.closest(".pwishlist-item");
      if (item) {
        item.remove();
        checkWishlistEmpty();
        showToast("Removed from wishlist.");
      }
    }
    const cartBtn = e.target.closest(".pwishlist-add-cart");
    if (cartBtn) {
      showToast("Added to cart.");
      /*
       * ── WooCommerce wiring ──────────────────────────────────
       * const productId = cartBtn.dataset.productId;
       * fetch("/wp-json/wc/store/v1/cart/add-item", {
       *   method: "POST",
       *   headers: { "Content-Type": "application/json" },
       *   body: JSON.stringify({ id: productId, quantity: 1 }),
       * });
       * ─────────────────────────────────────────────────────────
       */
    }
  });

  /* ─────────────────────────────────────────────
     MODAL 6 — NOTIFICATIONS
  ───────────────────────────────────────────── */

  const formNotifications = document.getElementById("form-notifications");
  if (formNotifications) {
    formNotifications.addEventListener("submit", (e) => {
      e.preventDefault();
      closeModal("modal-notifications");
      showToast("Notification preferences saved.");
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 7 — PASSWORD  (strength + eye toggle + match)
  ───────────────────────────────────────────── */

  /* Eye (show/hide) — delegated so plugin modals also benefit */
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-eye]");
    if (!btn) return;
    const input = document.getElementById(btn.dataset.eye);
    if (!input) return;
    input.type = input.type === "password" ? "text" : "password";
    btn.style.opacity = input.type === "text" ? "0.45" : "1";
  });

  /* Password strength */
  const pwdNew      = document.getElementById("pwd-new");
  const strFill     = document.getElementById("pwd-strength-fill");
  const strLabel    = document.getElementById("pwd-strength-label");
  const pwdRuleList = document.getElementById("pwd-rules");

  const STRENGTH = [
    { label: "Weak",   color: "#e04040", w: "25%"  },
    { label: "Fair",   color: "#e09500", w: "50%"  },
    { label: "Good",   color: "#2597e0", w: "75%"  },
    { label: "Strong", color: "#198754", w: "100%" },
  ];

  function evalRules(pwd) {
    return {
      length:  pwd.length >= 8,
      upper:   /[A-Z]/.test(pwd),
      digit:   /\d/.test(pwd),
      special: /[!@#$%^&*()\-_=+[\]{};:'",.<>/?\\|`~]/.test(pwd),
    };
  }

  if (pwdNew) {
    pwdNew.addEventListener("input", () => {
      const pwd    = pwdNew.value;
      const rules  = evalRules(pwd);
      const passed = Object.values(rules).filter(Boolean).length;

      if (pwdRuleList) {
        pwdRuleList.querySelectorAll("li[data-rule]").forEach((li) => {
          li.classList.toggle("passed", Boolean(rules[li.dataset.rule]));
        });
      }

      const lvl = STRENGTH[Math.max(0, passed - 1)];
      if (strFill) {
        strFill.style.width      = pwd.length === 0 ? "0%" : lvl.w;
        strFill.style.background = lvl.color;
      }
      if (strLabel) {
        strLabel.textContent = pwd.length === 0 ? "" : lvl.label;
        strLabel.style.color  = lvl.color;
      }
    });
  }

  /* Password match */
  const pwdConfirm  = document.getElementById("pwd-confirm");
  const matchMsg    = document.getElementById("pwd-match-msg");

  function checkMatch() {
    if (!pwdNew || !pwdConfirm || !matchMsg) return;
    const has = pwdConfirm.value.length > 0;
    matchMsg.hidden = !has;
    if (has) {
      const ok = pwdNew.value === pwdConfirm.value;
      matchMsg.textContent = ok ? "✓ Passwords match" : "✗ Passwords do not match";
      matchMsg.className   = "pf-match-msg " + (ok ? "is-match" : "is-mismatch");
    }
  }

  if (pwdNew)     pwdNew.addEventListener("input", checkMatch);
  if (pwdConfirm) pwdConfirm.addEventListener("input", checkMatch);

  const formPassword = document.getElementById("form-password");
  if (formPassword) {
    formPassword.addEventListener("submit", (e) => {
      e.preventDefault();
      const d = formDataToObject(formPassword);
      const rules  = evalRules(d.new_password || "");
      const passed = Object.values(rules).filter(Boolean).length;

      if (passed < 2) {
        showToast("Password is too weak. Use at least 8 chars, one number.", "error");
        return;
      }
      if (d.new_password !== d.confirm_password) {
        showToast("Passwords do not match.", "error");
        return;
      }
      formPassword.reset();
      if (strFill)  strFill.style.width = "0%";
      if (strLabel) strLabel.textContent = "";
      if (matchMsg) matchMsg.hidden = true;
      if (pwdRuleList)
        pwdRuleList.querySelectorAll("li").forEach((li) => li.classList.remove("passed"));

      closeModal("modal-password");
      showToast("Password updated successfully.");
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 8 — REPORT
  ───────────────────────────────────────────── */

  const formReport = document.getElementById("form-report");
  if (formReport) {
    formReport.addEventListener("submit", (e) => {
      e.preventDefault();
      formReport.reset();
      closeModal("modal-report");
      showToast("Support ticket submitted. Our team will contact you shortly.");
    });
  }

  /* ─────────────────────────────────────────────
     LOGOUT
  ───────────────────────────────────────────── */

  const logoutBtn = document.getElementById("profile-logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault();
      const ok = window.confirm("Log out of your ConsuCorner account?");
      if (!ok) return;
      localStorage.removeItem(STORAGE_KEY);
      showToast("Logged out successfully.");
      /*
       * WooCommerce wiring:
       * window.location.href = window.CC_LOGOUT_URL || "/my-account/customer-logout/";
       */
    });
  }

  /* ─────────────────────────────────────────────
     PLUGIN / EXTENSION API
     window.CCProfile — public surface for WP plugins
  ───────────────────────────────────────────── */

  /**
   * registerMenuItem(options)
   *   options.id          — unique string used as modal ID
   *   options.label       — text shown in the menu
   *   options.icon        — SVG <use> href (e.g. "#pi-star") or raw SVG string
   *   options.modalId     — id of the modal to open (defaults to options.id)
   *   options.iconColor   — optional CSS color for the icon badge gradient
   *
   * Example (Smart Coupons plugin):
   *   window.CCProfile.registerMenuItem({
   *     id: "coupons",
   *     label: "My Coupons",
   *     icon: "#pi-heart",
   *     modalId: "modal-coupons",
   *   });
   */
  function registerMenuItem({ id, label, icon, modalId, iconColor }) {
    const slot = document.getElementById("profile-menu-plugins");
    if (!slot) return;

    const resolvedModal = modalId || id;
    const iconMarkup = icon && icon.startsWith("#")
      ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="${icon}"/></svg>`
      : icon || "";

    const a = document.createElement("a");
    a.href = "#";
    a.className = "profile-item";
    a.setAttribute("data-modal", resolvedModal);
    a.setAttribute("role", "listitem");
    if (iconColor) {
      a.style.setProperty("--plugin-icon-color", iconColor);
    }
    a.innerHTML = `
      <span class="profile-item-icon${iconColor ? ' profile-item-icon--custom' : ''}">
        ${iconMarkup}
      </span>
      ${label}
    `;
    slot.appendChild(a);
  }

  /**
   * registerModal(options)
   *   options.id          — modal element id (must match registerMenuItem.modalId)
   *   options.title       — heading text
   *   options.subtitle    — sub-heading text
   *   options.icon        — SVG use href (e.g. "#pi-star") or inline SVG
   *   options.iconColor   — one of: blue|teal|indigo|green|rose|amber|slate|orange
   *                         or a custom CSS background color string
   *   options.wide        — boolean, adds pmodal-wide class
   *   options.bodyHTML    — inner HTML for the modal body
   *   options.footerHTML  — inner HTML for the modal footer
   *                         (defaults to a Close button)
   *   options.onOpen      — function called when modal opens
   *   options.onSubmit    — function(formData) called on the default form submit
   *
   * Example (Smart Coupons plugin):
   *   window.CCProfile.registerModal({
   *     id: "modal-coupons",
   *     title: "My Coupons",
   *     subtitle: "Your available discounts and promo codes",
   *     icon: "#pi-heart",
   *     iconColor: "rose",
   *     bodyHTML: `<div class="pf-section">...</div>`,
   *     footerHTML: `<button type="button" class="pmodal-btn-ghost" data-dismiss>Close</button>`,
   *   });
   */
  function registerModal({
    id,
    title,
    subtitle = "",
    icon = "#pi-user",
    iconColor = "blue",
    wide = false,
    bodyHTML = "",
    footerHTML = null,
    onOpen = null,
    onSubmit = null,
  }) {
    const slot = document.getElementById("profile-modals-plugins");
    if (!slot) return;

    const isBuiltinColor = ["blue","teal","indigo","green","rose","amber","slate","orange"]
      .includes(iconColor);

    const iconMarkup = icon.startsWith("#")
      ? `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="${icon}"/></svg>`
      : icon;

    const hIconStyle = isBuiltinColor
      ? `class="pmodal-hicon pmodal-hicon--${iconColor}"`
      : `class="pmodal-hicon" style="background:${iconColor}20;color:${iconColor}"`;

    const defaultFooter = `<button type="button" class="pmodal-btn-ghost" data-dismiss>Close</button>`;
    const ariaId = `${id}-title`;

    const div = document.createElement("div");
    div.className = "pmodal";
    div.id = id;
    div.setAttribute("aria-hidden", "true");
    div.innerHTML = `
      <div class="pmodal-backdrop"></div>
      <div class="pmodal-scroll">
        <div class="pmodal-dialog${wide ? " pmodal-wide" : ""}" role="dialog" aria-modal="true" aria-labelledby="${ariaId}">
          <header class="pmodal-header">
            <div class="pmodal-header-inner">
              <span ${hIconStyle}>${iconMarkup}</span>
              <div>
                <h2 class="pmodal-title" id="${ariaId}">${title}</h2>
                ${subtitle ? `<p class="pmodal-subtitle">${subtitle}</p>` : ""}
              </div>
            </div>
            <button class="pmodal-close" type="button" aria-label="Close dialog">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </header>
          <div class="pmodal-body">${bodyHTML}</div>
          <footer class="pmodal-footer">${footerHTML || defaultFooter}</footer>
        </div>
      </div>
    `;
    slot.appendChild(div);

    // Wire open callback
    if (typeof onOpen === "function") {
      div._ccOnOpen = onOpen;
    }

    // Wire form submit callback
    if (typeof onSubmit === "function") {
      const form = div.querySelector("form");
      if (form) {
        form.addEventListener("submit", (e) => {
          e.preventDefault();
          onSubmit(formDataToObject(form), form);
        });
      }
    }
  }

  /* Override openModal to support plugin onOpen callbacks */
  const _nativeOpenModal = openModal;

  /* ─────────────────────────────────────────────
     INIT
  ───────────────────────────────────────────── */

  renderHeader();
  fillAccountForm();
  checkWishlistEmpty();

  /* ─────────────────────────────────────────────
     PUBLIC API  (window.CCProfile)
  ───────────────────────────────────────────── */

  window.CCProfile = {
    /** Open any profile modal by id */
    openModal,
    /** Close any profile modal by id (or the currently open one) */
    closeModal,
    /** Show a toast notification */
    showToast,
    /** Read current profile state object */
    getState: () => ({ ...state }),
    /** Merge partial data into state and persist to localStorage */
    setState: (partial) => {
      saveState(partial);
      renderHeader();
    },
    /** Register a new menu item linked to a modal */
    registerMenuItem,
    /** Register a new full modal popup */
    registerModal,
    /**
     * Quick plugin helper — registers both a menu item and its modal in one call.
     * Accepts all options from both registerMenuItem and registerModal merged.
     */
    addFeature(options) {
      const { id, label, icon, iconColor, wide, bodyHTML, footerHTML, onOpen, onSubmit } = options;
      registerModal({ id: `modal-${id}`, title: label, icon, iconColor, wide, bodyHTML, footerHTML, onOpen, onSubmit });
      registerMenuItem({ id, label, icon, modalId: `modal-${id}`, iconColor });
    },
  };

  /*
   * ═══════════════════════════════════════════════════════════════════════════
   *  PLUGIN USAGE EXAMPLES
   *  Put these in your plugin's JS file (enqueued via wp_enqueue_scripts).
   *  They run after profile.js so window.CCProfile is already available.
   * ───────────────────────────────────────────────────────────────────────────
   *
   *  // Example 1 — Smart Coupons
   *  document.addEventListener("DOMContentLoaded", () => {
   *    window.CCProfile.addFeature({
   *      id: "coupons",
   *      label: "My Coupons",
   *      icon: "#pi-heart",
   *      iconColor: "rose",
   *      bodyHTML: `
   *        <div class="pf-section">
   *          <h3 class="pf-section-title">Available Coupons</h3>
   *          <div id="coupons-list"><!-- loaded via AJAX --></div>
   *        </div>`,
   *      onOpen: () => {
   *        fetch("/wp-json/wc/v3/coupons?customer=" + window.CC_CUSTOMER_ID)
   *          .then(r => r.json()).then(renderCoupons);
   *      },
   *    });
   *  });
   *
   *  // Example 2 — Referral Discounts
   *  document.addEventListener("DOMContentLoaded", () => {
   *    window.CCProfile.addFeature({
   *      id: "referral",
   *      label: "Referral Code",
   *      icon: "#pi-flag",
   *      iconColor: "amber",
   *      bodyHTML: `
   *        <div class="pf-section">
   *          <h3 class="pf-section-title">Your Referral Code</h3>
   *          <p class="pf-section-desc">Share this code to earn rewards.</p>
   *          <div class="pf-field">
   *            <label class="pf-label">Referral Code</label>
   *            <input class="pf-input pf-readonly" value="CC-ALEXA2024" readonly />
   *          </div>
   *        </div>`,
   *    });
   *  });
   *
   * ═══════════════════════════════════════════════════════════════════════════
   */
})();
