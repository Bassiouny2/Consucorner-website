/**
 * profile.js — ConsuCorner My Account
 *
 * ── WooCommerce REST API field names are used as `name` attributes ──────────
 *   Account details  →  PUT  /wp-json/wc/v3/customers/{id}
 *   Top-up           →  POST /wp-json/custom/v1/wallet/topup
 *   Privacy prefs    →  POST /wp-json/custom/v1/privacy
 *   Notifications    →  POST /wp-json/custom/v1/notifications
 *   Password change  →  POST /wp-json/custom/v1/account/password
 *   Support ticket   →  rendered & submitted by the Forminator plugin
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

  const profileConfig =
    typeof window !== "undefined" && window.consuProfileData
      ? window.consuProfileData
      : {};

  /* ─────────────────────────────────────────────
     STATE (localStorage-backed)
  ───────────────────────────────────────────── */

  const STORAGE_KEY = "cc_profile_v2";
  const WISHLIST_KEY = "cc_saved_products";
  const WISHLIST_DETAILS_KEY = "cc_saved_product_details";

  const DEFAULT_STATE = {
    first_name: profileConfig.firstName || "Alexa",
    last_name: profileConfig.lastName || "Rawles",
    display_name:
      profileConfig.displayName ||
      [profileConfig.firstName || "Alexa", profileConfig.lastName || "Rawles"].join(" ").trim(),
    username: profileConfig.username || "alexa.rawles",
    email: profileConfig.email || "alexarawles@gmail.com",
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
    member_since: profileConfig.memberSince || "Member since March 2024",
    avatar_url: profileConfig.avatarUrl || "",
    __profile_user_id: profileConfig.userId ? String(profileConfig.userId) : "",
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

  function hydrateStateFromServerProfile() {
    if (!profileConfig.userId) {
      return;
    }

    const serverUserId = String(profileConfig.userId);
    if (state.__profile_user_id !== serverUserId) {
      Object.assign(state, { ...DEFAULT_STATE });
    }

    state.__profile_user_id = serverUserId;
    state.first_name = profileConfig.firstName || state.first_name;
    state.last_name = profileConfig.lastName || state.last_name;
    state.display_name =
      profileConfig.displayName ||
      state.display_name ||
      `${state.first_name} ${state.last_name}`.trim();
    state.username = profileConfig.username || state.username;
    state.email = profileConfig.email || state.email;
    state.member_since = profileConfig.memberSince || state.member_since;
    state.avatar_url = profileConfig.avatarUrl || state.avatar_url;
    state.avatarDataUrl = "";

    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  }

  hydrateStateFromServerProfile();

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
    new FormData(form).forEach((v, k) => {
      const value = typeof v === "string" ? v : "";
      const normalized = value.trim();

      if (!(k in obj)) {
        obj[k] = value;
        return;
      }

      // Some account fields appear more than once across tabs (same name).
      // Keep the latest non-empty value to avoid empty duplicates overwriting data.
      if (normalized !== "") {
        obj[k] = value;
      }
    });
    return obj;
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getWishlistIds() {
    try {
      const parsed = JSON.parse(localStorage.getItem(WISHLIST_KEY) || "[]");
      return Array.isArray(parsed)
        ? [...new Set(parsed.map((id) => String(parseInt(id, 10))).filter((id) => id !== "NaN" && id !== "0"))]
        : [];
    } catch (_) {
      return [];
    }
  }

  function setWishlistIds(ids) {
    const cleaned = [...new Set((ids || []).map((id) => String(parseInt(id, 10))).filter((id) => id !== "NaN" && id !== "0"))];
    localStorage.setItem(WISHLIST_KEY, JSON.stringify(cleaned));
    window.dispatchEvent(new CustomEvent("cc:wishlist-updated", { detail: { ids: cleaned } }));
  }

  function getWishlistDetails() {
    try {
      const parsed = JSON.parse(localStorage.getItem(WISHLIST_DETAILS_KEY) || "{}");
      return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
    } catch (_) {
      return {};
    }
  }

  function setWishlistDetails(details) {
    localStorage.setItem(WISHLIST_DETAILS_KEY, JSON.stringify(details || {}));
  }

  function apiRequest(action, payload = {}) {
    if (!profileConfig.ajaxUrl || !profileConfig.nonce) {
      return Promise.reject(new Error("Profile API is not configured."));
    }

    const body = new URLSearchParams();
    body.set("action", action);
    body.set("nonce", profileConfig.nonce);
    body.set("payload", JSON.stringify(payload));

    return fetch(profileConfig.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    })
      .then((res) =>
        res.text().then((text) => {
          if (text === "-1") {
            throw new Error("Your session expired. Please refresh the page and try again.");
          }
          let parsed = null;
          try {
            parsed = JSON.parse(text);
          } catch (_) {
            throw new Error("Unexpected server response. Please try again.");
          }

          if (!parsed || !parsed.success) {
            const message =
              parsed && parsed.data && parsed.data.message
                ? String(parsed.data.message)
                : "Request failed.";
            throw new Error(message);
          }
          return parsed.data || {};
        })
      )
      .then((data) => {
        if (data && data.message && /session expired/i.test(String(data.message))) {
          throw new Error(String(data.message));
        }
        return data;
      });
  }

  function applyServerProfile(profile) {
    if (!profile || typeof profile !== "object") {
      return;
    }

    Object.assign(state, profile);
    if (profile.avatar_url) {
      state.avatarDataUrl = "";
    }
    if (profile.user_id) {
      state.__profile_user_id = String(profile.user_id);
    }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    renderHeader();
    fillAccountForm();
    fillPreferenceForms();
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
    if (id === "modal-wallet") loadWalletData();
    if (id === "modal-orders") loadOrders();
    if (id === "modal-favorites") renderWishlist();
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
     ORDERS DATA (WooCommerce)
  ───────────────────────────────────────────── */

  const orderLinkArgs = profileConfig.orderLinkArgs || { order: "cc_order", key: "cc_key" };
  let activeOrderKey = "";

  function statusClass(status) {
    const map = {
      completed: "pstatus-completed",
      processing: "pstatus-processing",
      cancelled: "pstatus-cancelled",
      refunded: "pstatus-refunded",
      "on-hold": "pstatus-on-hold",
    };
    return map[status] || "pstatus-processing";
  }

  function fulfillmentClass(status) {
    if (status === "ordered") {
      status = "confirmed";
    }
    const map = {
      confirmed: "pops-confirmed",
      preparing: "pops-preparing",
      shipped: "pops-shipped",
      out_for_delivery: "pops-out",
      delivered: "pops-delivered",
      cancelled: "pops-cancelled",
    };
    return map[status] || "pops-confirmed";
  }

  function getPrimaryStatusLabel(order) {
    return order.fulfillment_label || order.status_name || order.status || "";
  }

  function getPrimaryStatusClass(order) {
    if (order.fulfillment_status) {
      return fulfillmentClass(order.fulfillment_status);
    }
    return statusClass(order.status);
  }

  function renderPrimaryStatusChip(order) {
    const label = getPrimaryStatusLabel(order);
    const klass = order.fulfillment_status
      ? `pops-chip ${getPrimaryStatusClass(order)}`
      : `pstatus-chip ${getPrimaryStatusClass(order)}`;
    return `<span class="${klass}">${escapeHtml(label)}</span>`;
  }

  function renderCancelRequestMarkup(order) {
    if (!order.can_request_cancel) {
      if (order.cancel_blocked_reason) {
        return `<p class="porder-return-notice">${escapeHtml(order.cancel_blocked_reason)}</p>`;
      }
      return "";
    }

    const items = Array.isArray(order.cancel_items) ? order.cancel_items.filter((item) => item.can_cancel) : [];
    const itemsHtml = items.length
      ? items
          .map(
            (item) => `
              <label class="porder-cancel-item">
                <input type="checkbox" class="porder-cancel-item-check" value="${escapeHtml(String(item.item_id))}" checked>
                <span>${escapeHtml(item.name || "")}</span>
                <span class="porder-cancel-item-qty">Qty: ${escapeHtml(String(item.max_qty || item.qty || 1))}</span>
              </label>
            `
          )
          .join("")
      : "";

    return `
      <div class="porder-cancel-panel" data-order-id="${escapeHtml(String(order.id || ""))}">
        <h3 class="porder-detail-panel-title">Request cancellation</h3>
        <label class="porder-cancel-whole">
          <input type="checkbox" class="porder-cancel-whole-check" checked>
          Cancel whole order
        </label>
        <div class="porder-cancel-items">${itemsHtml}</div>
        <label class="porder-cancel-reason">
          <span>Reason (optional)</span>
          <textarea class="porder-cancel-reason-input" rows="2" placeholder="Tell us why you want to cancel"></textarea>
        </label>
        <button type="button" class="pmodal-btn-sm pmodal-btn-sm--danger" data-order-detail-action="request-cancel">Submit cancellation request</button>
      </div>
    `;
  }

  function renderOpsStatusMarkup(order) {
    const returnStatus = order.return_label
      ? `<span class="pops-chip pops-return">${escapeHtml(`Return: ${order.return_label}`)}</span>`
      : "";
    if (!returnStatus) {
      return "";
    }
    return `<div class="porder-ops-status">${returnStatus}</div>`;
  }

  function getOrderDeepLinkFromUrl() {
    const params = new URLSearchParams(window.location.search || "");
    const orderId = params.get(orderLinkArgs.order || "cc_order");
    const orderKey = params.get(orderLinkArgs.key || "cc_key");
    if (!orderId) return null;
    return { orderId: String(orderId), orderKey: orderKey ? String(orderKey) : "" };
  }

  function setOrderDeepLinkInUrl(orderId, orderKey) {
    if (!window.history || !window.history.replaceState) return;
    const url = new URL(window.location.href);
    const orderArg = orderLinkArgs.order || "cc_order";
    const keyArg = orderLinkArgs.key || "cc_key";
    if (orderId) {
      url.searchParams.set(orderArg, String(orderId));
      if (orderKey) url.searchParams.set(keyArg, String(orderKey));
    } else {
      url.searchParams.delete(orderArg);
      url.searchParams.delete(keyArg);
    }
    window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
  }

  function orderApiRequest(orderId, orderKey = "") {
    const payload = { order_id: orderId };
    if (orderKey) payload.order_key = orderKey;
    return apiRequest("consucorner_profile_get_order", payload);
  }

  function getOrdersModal() {
    return document.getElementById("modal-orders");
  }

  function showOrdersListView() {
    const listView = document.getElementById("orders-list-view");
    const detailView = document.getElementById("orders-detail-view");
    const title = document.getElementById("modal-orders-title");
    const subtitle = document.querySelector("#modal-orders .pmodal-subtitle");
    if (listView) listView.hidden = false;
    if (detailView) detailView.hidden = true;
    if (title) title.textContent = "Order History";
    if (subtitle) subtitle.textContent = "Track status and details of all your orders";
    activeOrderKey = "";
    setOrderDeepLinkInUrl("", "");
  }

  function renderOrderDetailMarkup(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    const totals = Array.isArray(order.totals) ? order.totals : [];
    const itemsHtml = items.length
      ? items
          .map((item) => {
            const meta = Array.isArray(item.meta)
              ? item.meta
                  .map((entry) => `<span class="porder-item-meta">${escapeHtml(entry.label)}: ${escapeHtml(entry.value)}</span>`)
                  .join("")
              : "";
            const image = item.image
              ? `<img class="porder-item-image" src="${escapeHtml(item.image)}" alt="" loading="lazy" />`
              : `<span class="porder-item-image porder-item-image--placeholder" aria-hidden="true"></span>`;
            const name = item.url
              ? `<a href="${escapeHtml(item.url)}" class="porder-item-name">${escapeHtml(item.name || "")}</a>`
              : `<span class="porder-item-name">${escapeHtml(item.name || "")}</span>`;
            return `
              <article class="porder-item">
                ${image}
                <div class="porder-item-body">
                  ${name}
                  ${meta}
                  <div class="porder-item-qty">Qty: ${escapeHtml(String(item.qty || 0))}</div>
                </div>
                <div class="porder-item-total">${escapeHtml(item.total || "")}</div>
              </article>
            `;
          })
          .join("")
      : `<p class="porder-detail-empty">${escapeHtml("No items found for this order.")}</p>`;

    const totalsHtml = totals
      .map(
        (row) => `
          <div class="porder-total-row">
            <span>${escapeHtml(row.label || "")}</span>
            <strong>${escapeHtml(row.value || "")}</strong>
          </div>
        `
      )
      .join("");

    const cancelBtn = order.can_request_cancel
      ? `<button type="button" class="pmodal-btn-sm pmodal-btn-sm--danger" data-order-detail-action="toggle-cancel">Request cancellation</button>`
      : "";

    const returnBtn = order.can_request_return && order.return_request_url
      ? `<a href="${escapeHtml(order.return_request_url)}" class="pmodal-btn-sm pmodal-btn-sm--outline">Request return</a>`
      : "";

    const returnNotice = !order.can_request_return && order.return_blocked_reason
      ? `<p class="porder-return-notice">${escapeHtml(order.return_blocked_reason)}</p>`
      : "";

    const bostaTrackingHtml = order.bosta_tracking_number
      ? `
        <div class="porder-bosta-banner">
          <div class="porder-bosta-banner-main">
            <p class="porder-bosta-label">Bosta tracking number</p>
            <a
              href="${escapeHtml(order.bosta_tracking_url || "#")}"
              class="porder-bosta-link"
              target="_blank"
              rel="noopener noreferrer"
            >#${escapeHtml(order.bosta_tracking_number)}</a>
            <p class="porder-bosta-hint">Track your shipment on Bosta</p>
          </div>
        </div>
      `
      : "";

    const cancelPanel = order.can_request_cancel ? renderCancelRequestMarkup(order) : (
      order.cancel_blocked_reason ? `<p class="porder-return-notice">${escapeHtml(order.cancel_blocked_reason)}</p>` : ""
    );

    return `
      <div class="porder-detail-head">
        <div>
          <p class="porder-detail-id">Order #${escapeHtml(order.number || order.id || "")}</p>
          <p class="porder-detail-date">${escapeHtml(order.date_full || order.date || "")}</p>
          ${renderOpsStatusMarkup(order)}
        </div>
        ${renderPrimaryStatusChip(order)}
      </div>
      ${bostaTrackingHtml}
      <div class="porder-detail-grid">
        <section class="porder-detail-panel">
          <h3 class="porder-detail-panel-title">Customer</h3>
          <p><strong>${escapeHtml(order.billing_name || "")}</strong></p>
          ${order.billing_email ? `<p>${escapeHtml(order.billing_email)}</p>` : ""}
          ${order.billing_phone ? `<p>${escapeHtml(order.billing_phone)}</p>` : ""}
        </section>
        <section class="porder-detail-panel">
          <h3 class="porder-detail-panel-title">Shipping</h3>
          <p><strong>${escapeHtml(order.shipping_name || order.billing_name || "")}</strong></p>
          <p class="porder-detail-address">${escapeHtml(order.shipping_address || "")}</p>
        </section>
        <section class="porder-detail-panel">
          <h3 class="porder-detail-panel-title">Payment</h3>
          <p>${escapeHtml(order.payment_method || "")}</p>
        </section>
      </div>
      <section class="porder-detail-items">
        <h3 class="porder-detail-panel-title">Items (${escapeHtml(String(order.items_count || items.length))})</h3>
        <div class="porder-items-list">${itemsHtml}</div>
      </section>
      <section class="porder-detail-totals">${totalsHtml}</section>
      ${returnNotice}
      <div class="porder-detail-actions">${returnBtn}${cancelBtn}</div>
      <div class="porder-cancel-panel-wrap" hidden>${cancelPanel}</div>
    `;
  }

  function showOrderDetailView(orderId, orderKey = "", options = {}) {
    const modal = getOrdersModal();
    const listView = document.getElementById("orders-list-view");
    const detailView = document.getElementById("orders-detail-view");
    const detailContent = document.getElementById("orders-detail-content");
    const title = document.getElementById("modal-orders-title");
    const subtitle = document.querySelector("#modal-orders .pmodal-subtitle");
    if (!modal || !detailView || !detailContent) return false;

    const openCancel = !!(options && options.openCancel);

    activeOrderKey = orderKey || activeOrderKey;
    if (listView) listView.hidden = true;
    detailView.hidden = false;
    if (title) title.textContent = "Order Details";
    if (subtitle) subtitle.textContent = "Review items, totals, and delivery information";
    detailContent.innerHTML = '<p class="porder-detail-loading">Loading order details...</p>';
    setOrderDeepLinkInUrl(orderId, activeOrderKey);

    return orderApiRequest(orderId, activeOrderKey)
      .then((data) => {
        const order = data.order || null;
        if (!order) throw new Error("Order not found.");
        detailContent.innerHTML = renderOrderDetailMarkup(order);
        detailContent.dataset.orderId = String(order.id || orderId);
        if (openCancel) {
          const panelWrap = detailContent.querySelector(".porder-cancel-panel-wrap");
          if (panelWrap) {
            panelWrap.hidden = false;
            panelWrap.scrollIntoView({ behavior: "smooth", block: "nearest" });
          } else if (!order.can_request_cancel) {
            showToast(order.cancel_blocked_reason || "This order cannot be cancelled online.", "error");
          }
        }
        return order;
      })
      .catch((error) => {
        detailContent.innerHTML = `<p class="porder-detail-error">${escapeHtml(error.message || "Unable to load order.")}</p>`;
        throw error;
      });
  }

  function openTrackedOrder(orderId, orderKey = "") {
    const deepLink = orderId ? { orderId: String(orderId), orderKey: orderKey || "" } : getOrderDeepLinkFromUrl();
    if (!deepLink || !deepLink.orderId) return;

    const guestModal = document.getElementById("modal-order-track");
    const ordersModal = getOrdersModal();

    if (ordersModal) {
      openModal("modal-orders");
      showOrderDetailView(deepLink.orderId, deepLink.orderKey).catch(() => {});
      return;
    }

    if (guestModal) {
      const guestContent = document.getElementById("guest-order-detail-content");
      if (guestContent) {
        guestContent.innerHTML = '<p class="porder-detail-loading">Loading order details...</p>';
      }
      openModal("modal-order-track");
      orderApiRequest(deepLink.orderId, deepLink.orderKey)
        .then((data) => {
          const order = data.order || null;
          if (!order) throw new Error("Order not found.");
          if (guestContent) guestContent.innerHTML = renderOrderDetailMarkup(order);
        })
        .catch((error) => {
          if (guestContent) {
            guestContent.innerHTML = `<p class="porder-detail-error">${escapeHtml(error.message || "Unable to load order.")}</p>`;
          }
        });
    }
  }

  function loadOrders() {
    const tbody = document.getElementById("orders-tbody");
    if (!tbody) return;

    showOrdersListView();
    tbody.innerHTML = '<tr><td colspan="6">Loading orders...</td></tr>';
    apiRequest("consucorner_profile_get_orders")
      .then((data) => {
        const orders = Array.isArray(data.orders) ? data.orders : [];
        if (!orders.length) {
          tbody.innerHTML = '<tr><td colspan="6">No orders found yet.</td></tr>';
          return;
        }

        tbody.innerHTML = orders
          .map((order) => {
            const orderId = escapeHtml(order.id || "");
            const orderNumber = escapeHtml(order.number || order.id || "");
            const orderStatus = escapeHtml(order.status || "");
            const statusName = escapeHtml(getPrimaryStatusLabel(order));
            const itemCount = Number(order.items_count || 0);
            const itemLabel = itemCount === 1 ? "1 item" : `${itemCount} items`;
            const canCancel = !!order.can_request_cancel;
            const returnBtn = order.can_request_return && order.return_request_url
              ? `<a href="${escapeHtml(order.return_request_url)}" class="pmodal-btn-sm pmodal-btn-sm--outline">Request return</a>`
              : "";
            const opsStatus = renderOpsStatusMarkup(order);
            return `
              <tr data-status="${orderStatus}" data-order-id="${orderId}">
                <td class="ptable-id">
                  <button type="button" class="porder-link-btn" data-order-action="view">#${orderNumber}</button>
                </td>
                <td>${escapeHtml(order.date || "")}</td>
                <td>${escapeHtml(itemLabel)}</td>
                <td>
                  ${renderPrimaryStatusChip(order)}
                  ${opsStatus}
                </td>
                <td>${escapeHtml(order.total || "")}</td>
                <td class="ptable-actions">
                  <button type="button" class="pmodal-btn-sm" data-order-action="view">View</button>
                  ${canCancel ? '<button type="button" class="pmodal-btn-sm pmodal-btn-sm--danger" data-order-action="request-cancel">Cancel order</button>' : ""}
                  ${returnBtn}
                </td>
              </tr>
            `;
          })
          .join("");
      })
      .catch((error) => {
        tbody.innerHTML = `<tr><td colspan="6">${escapeHtml(error.message)}</td></tr>`;
      });
  }

  document.addEventListener("click", (e) => {
    const actionBtn = e.target.closest("[data-order-action]");
    if (actionBtn) {
      const row = actionBtn.closest("tr[data-order-id]");
      if (!row) return;
      const orderId = row.getAttribute("data-order-id");
      if (!orderId) return;

      if (actionBtn.dataset.orderAction === "view") {
        e.preventDefault();
        showOrderDetailView(orderId).catch((error) => showToast(error.message, "error"));
        return;
      }

      if (actionBtn.dataset.orderAction === "request-cancel") {
        e.preventDefault();
        showOrderDetailView(orderId, "", { openCancel: true }).catch((error) => showToast(error.message, "error"));
        return;
      }

      if (actionBtn.dataset.orderAction === "cancel") {
        apiRequest("consucorner_profile_cancel_order", { order_id: orderId })
          .then((data) => {
            showToast(data.message || "Order cancelled.");
            loadOrders();
          })
          .catch((error) => showToast(error.message, "error"));
        return;
      }

      if (actionBtn.dataset.orderAction === "reorder") {
        showToast("Re-order will be enabled once product mapping is synced.", "error");
        return;
      }
    }

    const detailAction = e.target.closest("[data-order-detail-action]");
    if (detailAction) {
      e.preventDefault();
      const detailContent = document.getElementById("orders-detail-content");
      const orderId = detailContent ? detailContent.dataset.orderId : "";
      if (detailAction.dataset.orderDetailAction === "toggle-cancel") {
        const panelWrap = detailContent
          ? detailContent.querySelector(".porder-cancel-panel-wrap")
          : document.querySelector(".porder-cancel-panel-wrap");
        if (panelWrap) {
          panelWrap.hidden = !panelWrap.hidden;
          if (!panelWrap.hidden) {
            panelWrap.scrollIntoView({ behavior: "smooth", block: "nearest" });
          }
        }
        return;
      }
      if (detailAction.dataset.orderDetailAction === "request-cancel") {
        const panel = detailContent
          ? detailContent.querySelector(".porder-cancel-panel")
          : document.querySelector(".porder-cancel-panel");
        if (!panel || !orderId) {
          showToast("Unable to submit cancellation. Please reopen the order and try again.", "error");
          return;
        }
        if (detailAction.disabled) {
          return;
        }

        let wholeOrder = !!panel.querySelector(".porder-cancel-whole-check")?.checked;
        const reason = panel.querySelector(".porder-cancel-reason-input")?.value || "";
        const items = {};
        panel.querySelectorAll(".porder-cancel-item-check:checked").forEach((input) => {
          const itemId = input.value;
          const row = input.closest(".porder-cancel-item");
          const qtyText = row ? row.querySelector(".porder-cancel-item-qty")?.textContent || "" : "";
          const qtyMatch = qtyText.match(/(\d+(?:\.\d+)?)/);
          items[itemId] = qtyMatch ? qtyMatch[1] : 1;
        });

        // If nothing selected, treat as whole-order cancel when items exist.
        if (!wholeOrder && !Object.keys(items).length) {
          const cancelableChecks = panel.querySelectorAll(".porder-cancel-item-check");
          if (cancelableChecks.length) {
            wholeOrder = true;
          } else {
            showToast("Select at least one item, or choose Cancel whole order.", "error");
            return;
          }
        }

        const originalLabel = detailAction.textContent;
        detailAction.disabled = true;
        detailAction.textContent = "Submitting...";

        apiRequest("consucorner_profile_request_cancel", {
          order_id: orderId,
          whole_order: wholeOrder ? 1 : 0,
          reason,
          items,
        })
          .then((data) => {
            showToast(data.message || "Cancellation request submitted.");
            return showOrderDetailView(orderId);
          })
          .then(() => loadOrders())
          .catch((error) => {
            showToast(error.message || "Cancellation request failed.", "error");
            detailAction.disabled = false;
            detailAction.textContent = originalLabel;
          });
      }
    }
  });

  const ordersBackBtn = document.getElementById("orders-back-btn");
  if (ordersBackBtn) {
    ordersBackBtn.addEventListener("click", () => {
      showOrdersListView();
    });
  }

  document.addEventListener("click", (e) => {
    const ordersModal = e.target.closest("#modal-orders");
    if (!ordersModal) return;
    const dismiss = e.target.closest("[data-dismiss], .pmodal-close");
    if (!dismiss) return;
    showOrdersListView();
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
    const badgeEl = document.querySelector(".profile-member-badge");
    const avatarEl = document.getElementById("profile-avatar-display");

    const displayName =
      state.display_name ||
      `${state.first_name} ${state.last_name}`.trim() ||
      "My Account";

    if (nameEl)  nameEl.textContent  = displayName;
    if (emailEl) emailEl.textContent = state.email;
    if (badgeEl && state.member_since) badgeEl.textContent = state.member_since;

    if (avatarEl) {
      if (state.avatarDataUrl) {
        avatarEl.style.backgroundImage = `url("${state.avatarDataUrl}")`;
        avatarEl.style.backgroundSize  = "cover";
        avatarEl.style.backgroundPosition = "center";
        avatarEl.textContent = "";
      } else if (state.avatar_url) {
        avatarEl.style.backgroundImage = `url("${state.avatar_url}")`;
        avatarEl.style.backgroundSize  = "cover";
        avatarEl.style.backgroundPosition = "center";
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
        const dataUrl = String(reader.result || "");
        if (!dataUrl.startsWith("data:image/")) {
          showToast("Please choose an image file.", "error");
          avatarInput.value = "";
          return;
        }
        saveState({ avatarDataUrl: dataUrl });
        renderHeader();
        avatarBtn.disabled = true;
        apiRequest("consucorner_profile_save_avatar", { avatar_data: dataUrl })
          .then((res) => {
            if (res.profile) {
              applyServerProfile(res.profile);
            }
            saveState({ avatarDataUrl: "" });
            renderHeader();
            showToast(res.message || "Profile photo saved.");
          })
          .catch((error) => {
            saveState({ avatarDataUrl: "" });
            renderHeader();
            showToast(error.message, "error");
          })
          .finally(() => {
            avatarBtn.disabled = false;
            avatarInput.value = "";
          });
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
      const fields = form.querySelectorAll(`[name="${key}"]`);
      if (!fields.length) return;
      fields.forEach((field) => {
        if ("value" in field) {
          field.value = state[key] || "";
        }
      });
    });
  }

  function bindLinkedAccountFields() {
    const pairs = [
      ["acc-first-name", "bill-first"],
      ["acc-last-name", "bill-last"],
      ["acc-email", "bill-email"],
      ["acc-phone", "bill-phone"],
      ["acc-company", "bill-company"],
    ];

    pairs.forEach(([leftId, rightId]) => {
      const left = document.getElementById(leftId);
      const right = document.getElementById(rightId);
      if (!left || !right) return;

      let syncing = false;
      const syncValue = (source, target) => {
        if (syncing) return;
        syncing = true;
        target.value = source.value;
        syncing = false;
      };

      left.addEventListener("input", () => syncValue(left, right));
      right.addEventListener("input", () => syncValue(right, left));

      // Initial alignment so both tabs display the same loaded value.
      if (left.value && !right.value) {
        right.value = left.value;
      } else if (right.value && !left.value) {
        left.value = right.value;
      }
    });
  }

  function fillPreferenceForms() {
    const defaultEnabledKeys = [
      "notif_order_confirmed",
      "notif_shipping",
      "notif_delivered",
      "notif_refund",
      "notif_login_alert",
      "notif_password_change",
      "notif_offers",
      "notif_new_products",
    ];
    const prefMap = {
      marketing_email_consent:
        state.marketing_email_consent ||
        state.privacy_marketing_email_consent,
      notif_order_confirmed: state.notif_order_confirmed,
      notif_shipping: state.notif_shipping,
      notif_delivered: state.notif_delivered,
      notif_refund: state.notif_refund,
      notif_login_alert: state.notif_login_alert,
      notif_password_change: state.notif_password_change,
      notif_offers: state.notif_offers,
      notif_new_products: state.notif_new_products,
    };

    Object.keys(prefMap).forEach((key) => {
      const input = document.querySelector(`input[name="${key}"]`);
      if (!input) return;
      const value = prefMap[key];
      input.checked =
        defaultEnabledKeys.includes(key) && (value === undefined || value === null || value === "")
          ? true
          : String(value) === "1";
    });
  }

  const formAccount = document.getElementById("form-account");
  if (formAccount) {
    formAccount.setAttribute("novalidate", "novalidate");
    formAccount.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = formDataToObject(formAccount);
      if (!data.email || !String(data.email).includes("@")) {
        showToast("Please enter a valid email address.", "error");
        return;
      }

      const submitBtn = formAccount.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      apiRequest("consucorner_profile_save_account", data)
        .then((res) => {
          if (res.profile) {
            applyServerProfile(res.profile);
          } else {
            saveState(data);
            renderHeader();
          }
          closeModal("modal-account");
          showToast(res.message || "Account details saved.");
        })
        .catch((error) => showToast(error.message, "error"))
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
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

  function walletTransactionsBody() {
    return (
      document.getElementById("wallet-transactions-tbody") ||
      document.querySelector("#modal-wallet table[aria-label='Wallet transactions'] tbody")
    );
  }

  function walletRow(txn) {
    const txnId = txn.id ? `#${escapeHtml(txn.id)}` : "-";
    const type = txn.type ? escapeHtml(String(txn.type).replace(/_/g, " ")) : "wallet";
    const description = txn.description || "Wallet balance updated.";
    const order = txn.order_id ? ` <span class="pwallet-order-ref">Order #${escapeHtml(txn.order_id)}</span>` : "";

    return `
      <tr>
        <td class="ptable-id">${txnId}</td>
        <td>
          <strong class="pwallet-txn-type">${type}</strong>
          <span class="pwallet-txn-note">${escapeHtml(description)}${order}</span>
        </td>
        <td>${escapeHtml(txn.date || "-")}</td>
        <td class="${escapeHtml(txn.amount_class || "")}">${escapeHtml(txn.amount_html || "")}</td>
        <td>${escapeHtml(txn.balance_html || "")}</td>
      </tr>
    `;
  }

  function loadWalletData() {
    const balanceEl = document.getElementById("wallet-balance-display");
    const tbody = walletTransactionsBody();

    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="5">Loading wallet activity...</td></tr>';
    }

    apiRequest("consucorner_profile_get_wallet_data")
      .then((data) => {
        if (balanceEl && data.balance_html) {
          balanceEl.innerHTML = data.balance_html;
        }

        const transactions = Array.isArray(data.transactions) ? data.transactions : [];
        if (!tbody) return;

        if (!transactions.length) {
          tbody.innerHTML = '<tr><td colspan="5">No wallet transactions yet.</td></tr>';
          return;
        }

        tbody.innerHTML = transactions.map(walletRow).join("");
      })
      .catch((error) => {
        if (tbody) {
          tbody.innerHTML = `<tr><td colspan="5">${escapeHtml(error.message)}</td></tr>`;
        }
      });
  }

  loadWalletData();

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
      apiRequest("consucorner_profile_wallet_topup", d)
        .then((res) => {
          formTopup.reset();
          if (topupSection) topupSection.hidden = true;
          showToast(res.message || `Top-up of ${Number(d.amount).toLocaleString()} EGP requested.`);
          loadWalletData();
        })
        .catch((error) => showToast(error.message, "error"));
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 4 — PRIVACY
  ───────────────────────────────────────────── */

  const formPrivacy = document.getElementById("form-privacy");
  if (formPrivacy) {
    formPrivacy.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = formDataToObject(formPrivacy);
      apiRequest("consucorner_profile_save_privacy", data)
        .then((res) => {
          if (res.profile) {
            applyServerProfile(res.profile);
          }
          closeModal("modal-privacy");
          showToast(res.message || "Privacy preferences saved.");
        })
        .catch((error) => showToast(error.message, "error"));
    });
  }

  const btnDeleteAccount = document.getElementById("btn-delete-account");
  if (btnDeleteAccount) {
    btnDeleteAccount.addEventListener("click", () => {
      const ok = window.confirm(
        "Are you sure you want to permanently delete your account?\nThis cannot be undone."
      );
      if (!ok) return;
      apiRequest("consucorner_profile_request_delete")
        .then((res) => {
          closeAllModals();
          showToast(res.message || "Account deletion request submitted.");
        })
        .catch((error) => showToast(error.message, "error"));
    });
  }

  document.addEventListener("click", (e) => {
    const actionBtn = e.target.closest("[data-wc-action='export_data']");
    if (!actionBtn) return;
    e.preventDefault();
    showToast("Data export request received. We will email your export file.");
  });

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

  function setWishlistLoading(isLoading) {
    const grid = document.getElementById("wishlist-grid");
    const empty = document.getElementById("wishlist-empty");
    if (!grid || !empty) return;

    if (isLoading) {
      grid.hidden = false;
      empty.hidden = true;
      grid.innerHTML = '<div class="pwishlist-loading">Loading saved products...</div>';
    }
  }

  function wishlistCard(product) {
    const id = String(product.id || "");
    const permalink = product.permalink || "#";
    const image = product.image || "";
    const category = product.category || "Product";
    const meta = product.meta || "";
    const priceHtml = product.price_html || "";
    const disabled = product.is_purchasable ? "" : " disabled";
    const buttonText = product.is_purchasable ? "Add to Cart" : "Unavailable";

    return `
      <article class="pwishlist-item" data-product-id="${escapeHtml(id)}" data-product-url="${escapeHtml(permalink)}">
        <a class="pwishlist-img-link" href="${escapeHtml(permalink)}" aria-label="View ${escapeHtml(product.name || "product")}">
          <div class="pwishlist-img">
            <img src="${escapeHtml(image)}" alt="${escapeHtml(product.name || "")}" loading="lazy" decoding="async" />
          </div>
        </a>
        <div class="pwishlist-body">
          <p class="pwishlist-cat">${escapeHtml(category)}</p>
          <a class="pwishlist-name-link" href="${escapeHtml(permalink)}">
            <h4 class="pwishlist-name">${escapeHtml(product.name || "Product")}</h4>
          </a>
          ${meta ? `<p class="pwishlist-meta">${escapeHtml(meta)}</p>` : ""}
          <p class="pwishlist-price">${priceHtml}</p>
          <div class="pwishlist-actions">
            <button type="button" class="pmodal-btn-primary pwishlist-add-cart" data-product-id="${escapeHtml(id)}"${disabled}>${buttonText}</button>
            <button type="button" class="pmodal-btn-icon pwishlist-remove" aria-label="Remove from wishlist" data-product-id="${escapeHtml(id)}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </div>
      </article>
    `;
  }

  function renderWishlist() {
    const grid = document.getElementById("wishlist-grid");
    const empty = document.getElementById("wishlist-empty");
    if (!grid || !empty) return;

    const ids = getWishlistIds();
    if (!ids.length) {
      grid.innerHTML = "";
      checkWishlistEmpty();
      return;
    }

    setWishlistLoading(true);
    const localDetails = getWishlistDetails();

    apiRequest("consucorner_profile_get_wishlist_products", { ids })
      .then((data) => {
        const serverProducts = Array.isArray(data.products) ? data.products : [];
        const serverById = serverProducts.reduce((carry, product) => {
          carry[String(product.id)] = product;
          return carry;
        }, {});
        const products = ids
          .map((id) => serverById[id] || localDetails[id])
          .filter(Boolean)
          .map((product) => ({
            ...product,
            id: product.id || product.product_id,
            is_purchasable: typeof product.is_purchasable === "boolean" ? product.is_purchasable : true,
          }));

        const validIds = products.map((product) => String(product.id || product.product_id)).filter(Boolean);
        if (validIds.length && validIds.join(",") !== ids.join(",")) {
          setWishlistIds(validIds);
        }

        grid.innerHTML = products.map(wishlistCard).join("");
        checkWishlistEmpty();
      })
      .catch((error) => {
        grid.innerHTML = `<div class="pwishlist-loading pwishlist-loading--error">${escapeHtml(error.message)}</div>`;
        empty.hidden = true;
      });
  }

  function addWishlistProductToCart(productId, button) {
    if (!productId || !button || button.disabled) return;

    button.disabled = true;
    const original = button.textContent;
    button.textContent = "Adding...";

    const body = new URLSearchParams();
    body.set("product_id", String(productId));
    body.set("quantity", "1");

    fetch(`${window.location.origin}/?wc-ajax=add_to_cart`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body.toString(),
    })
      .then((res) => {
        if (!res.ok) throw new Error("Could not add this product to cart.");
        return res.json();
      })
      .then(() => {
        button.textContent = "Added";
        showToast("Product added to cart.");
        if (window.ccGtm && window.ccGtm.pushAddToCart) {
          window.ccGtm.pushAddToCart({
            item_id: String(productId),
            item_sku: "",
            item_name: "",
            price: 0,
            quantity: 1,
          });
        }
        setTimeout(() => {
          button.textContent = original;
          button.disabled = false;
        }, 1200);
      })
      .catch((error) => {
        button.textContent = original;
        button.disabled = false;
        showToast(error.message, "error");
      });
  }

  renderWishlist();
  window.addEventListener("cc:wishlist-updated", renderWishlist);
  window.addEventListener("storage", (event) => {
    if (event.key === WISHLIST_KEY) renderWishlist();
  });

  // Delegated remove & add-to-cart (works for dynamically added items too)
  document.addEventListener("click", (e) => {
    const removeBtn = e.target.closest(".pwishlist-remove");
    if (removeBtn) {
      e.preventDefault();
      const productId = removeBtn.getAttribute("data-product-id");
      setWishlistIds(getWishlistIds().filter((id) => id !== String(productId)));
      const details = getWishlistDetails();
      delete details[String(productId)];
      setWishlistDetails(details);
      const item = removeBtn.closest(".pwishlist-item");
      if (item) {
        item.remove();
        checkWishlistEmpty();
        showToast("Removed from wishlist.");
      }
    }
    const cartBtn = e.target.closest(".pwishlist-add-cart");
    if (cartBtn) {
      e.preventDefault();
      addWishlistProductToCart(cartBtn.dataset.productId, cartBtn);
    }
  });

  /* ─────────────────────────────────────────────
     MODAL 6 — NOTIFICATIONS
  ───────────────────────────────────────────── */

  const formNotifications = document.getElementById("form-notifications");
  if (formNotifications) {
    formNotifications.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = formDataToObject(formNotifications);
      apiRequest("consucorner_profile_save_notifications", data)
        .then((res) => {
          if (res.profile) {
            applyServerProfile(res.profile);
          }
          closeModal("modal-notifications");
          showToast(res.message || "Notification preferences saved.");
        })
        .catch((error) => showToast(error.message, "error"));
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

      apiRequest("consucorner_profile_change_password", d)
        .then((res) => {
          formPassword.reset();
          if (strFill) strFill.style.width = "0%";
          if (strLabel) strLabel.textContent = "";
          if (matchMsg) matchMsg.hidden = true;
          if (pwdRuleList) {
            pwdRuleList.querySelectorAll("li").forEach((li) => li.classList.remove("passed"));
          }

          closeModal("modal-password");
          showToast(res.message || "Password updated successfully.");
        })
        .catch((error) => showToast(error.message, "error"));
    });
  }

  /* ─────────────────────────────────────────────
     MODAL 8 — REPORT
     Submissions are now handled by the Forminator plugin (the report
     form is rendered server-side via [forminator_form id="…"]). No
     extra JS wiring is required here; Forminator manages validation,
     AJAX submission, and the success/error response inside the modal.
  ───────────────────────────────────────────── */

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
      if (profileConfig.logoutUrl) {
        window.location.href = profileConfig.logoutUrl;
        return;
      }
      showToast("Logged out successfully.");
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
     LOGIN / REGISTER SWITCHER
  ───────────────────────────────────────────── */

  function initAccountAuthSwitcher() {
    const card = document.querySelector("[data-auth-account-card]");
    if (!card) return;

    const stage = card.querySelector(".cc-auth-form-stage");
    const tabs = Array.from(card.querySelectorAll("[data-account-auth-tab]"));
    const panels = Array.from(card.querySelectorAll("[data-account-auth-panel]"));
    if (!stage || !tabs.length || panels.length < 2) return;

    let active = "login";
    let switching = false;

    function getPanel(view) {
      return panels.find((panel) => panel.dataset.accountAuthPanel === view);
    }

    function setActive(view, focusPanel = false) {
      if (switching || view === active || !getPanel(view)) return;

      const currentPanel = getPanel(active);
      const nextPanel = getPanel(view);
      switching = true;
      stage.style.minHeight = `${stage.offsetHeight}px`;

      tabs.forEach((tab) => {
        const isActive = tab.dataset.accountAuthTab === view;
        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
        tab.tabIndex = isActive ? 0 : -1;
      });

      card.classList.toggle("cc-auth-card--show-register", view === "register");

      if (currentPanel) {
        currentPanel.classList.remove("is-active");
      }

      window.setTimeout(() => {
        if (currentPanel) {
          currentPanel.hidden = true;
        }
        nextPanel.hidden = false;
        requestAnimationFrame(() => {
          nextPanel.classList.add("is-active");
          active = view;
          switching = false;
          window.setTimeout(() => {
            stage.style.minHeight = "";
          }, 260);

          if (focusPanel) {
            const firstField = nextPanel.querySelector("input, select, textarea, button, a");
            if (firstField) firstField.focus({ preventScroll: true });
          }
        });
      }, 190);
    }

    tabs.forEach((tab, index) => {
      tab.tabIndex = tab.classList.contains("is-active") ? 0 : -1;

      tab.addEventListener("click", () => {
        setActive(tab.dataset.accountAuthTab);
      });

      tab.addEventListener("keydown", (event) => {
        if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
        event.preventDefault();
        const nextIndex =
          event.key === "Home"
            ? 0
            : event.key === "End"
            ? tabs.length - 1
            : event.key === "ArrowRight"
            ? (index + 1) % tabs.length
            : (index - 1 + tabs.length) % tabs.length;

        tabs[nextIndex].focus();
        setActive(tabs[nextIndex].dataset.accountAuthTab);
      });
    });
  }

  /* ─────────────────────────────────────────────
     INIT
  ───────────────────────────────────────────── */

  initAccountAuthSwitcher();
  renderHeader();
  fillAccountForm();
  bindLinkedAccountFields();
  fillPreferenceForms();
  checkWishlistEmpty();

  const deepLinkOrder = getOrderDeepLinkFromUrl();
  if (deepLinkOrder) {
    openTrackedOrder(deepLinkOrder.orderId, deepLinkOrder.orderKey);
  }

  apiRequest("consucorner_profile_get_data")
    .then((res) => {
      if (res.profile) {
        applyServerProfile(res.profile);
      }
    })
    .catch(() => {
      /* keep localized fallback data */
    });

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
    /** Open order history or a specific order via tracking link params */
    openTrackedOrder,
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

  /* ─────────────────────────────────────────────
     COUPON APPLY (rdfw-referral page)
  ───────────────────────────────────────────── */

  const couponSection = document.querySelector(".cc-coupon-section");
  if (couponSection) {
    const couponInput    = couponSection.querySelector(".cc-coupon-input");
    const couponBtn      = couponSection.querySelector(".cc-coupon-apply-btn");
    const couponFeedback = couponSection.querySelector(".cc-coupon-feedback");

    function setCouponFeedback(msg, type) {
      if (!couponFeedback) return;
      couponFeedback.textContent = msg;
      couponFeedback.className   = "cc-coupon-feedback" + (type ? " " + type : "");
    }

    function applyCoupon() {
      const code = couponInput ? couponInput.value.trim() : "";

      if (!code) {
        setCouponFeedback("Please enter a coupon code.", "error");
        if (couponInput) couponInput.focus();
        return;
      }

      if (couponBtn) {
        couponBtn.disabled    = true;
        couponBtn.textContent = "Applying\u2026";
      }
      setCouponFeedback("", "");

      fetch(profileConfig.ajaxUrl, {
        method:      "POST",
        credentials: "same-origin",
        headers:     { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action:      "consucorner_apply_coupon",
          nonce:       profileConfig.couponNonce || "",
          coupon_code: code,
        }),
      })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          const msg = data.data && data.data.message
            ? data.data.message
            : (data.success ? "Coupon applied!" : "Invalid coupon code.");
          setCouponFeedback(msg, data.success ? "success" : "error");
          if (data.success && couponInput) couponInput.value = "";
        })
        .catch(function() {
          setCouponFeedback("Something went wrong. Please try again.", "error");
        })
        .finally(function() {
          if (couponBtn) {
            couponBtn.disabled    = false;
            couponBtn.textContent = "Apply";
          }
        });
    }

    if (couponBtn) {
      couponBtn.addEventListener("click", applyCoupon);
    }
    if (couponInput) {
      couponInput.addEventListener("keydown", function(e) {
        if (e.key === "Enter") { e.preventDefault(); applyCoupon(); }
      });
    }
  }

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
