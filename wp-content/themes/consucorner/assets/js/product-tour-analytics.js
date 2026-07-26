/**
 * ConsuCorner Tours v2 — DataLayer analytics contract.
 */
(function (window) {
  "use strict";

  var ATTRIBUTION_CART_KEY = "cc_tour_cart_attribution";
  var ATTRIBUTION_ORDER_KEY = "cc_tour_order_attribution";

  function getSessionId() {
    if (window.ccTracker && window.ccTracker.sessionId) {
      return window.ccTracker.sessionId;
    }
    return "";
  }

  /**
   * Push a consucorner_tour event to dataLayer.
   *
   * @param {string} action tour_action value.
   * @param {Object} extra Additional fields.
   */
  function pushTourEvent(action, extra) {
    window.dataLayer = window.dataLayer || [];
    var payload = {
      event: "consucorner_tour",
      tour_action: action,
      user_logged_in: !!(window.ccProductTour && window.ccProductTour.isLoggedIn),
      global_disabled: !!(extra && extra.global_disabled),
      session_id: getSessionId(),
    };
    if (extra) {
      Object.keys(extra).forEach(function (key) {
        if (key !== "global_disabled") {
          payload[key] = extra[key];
        }
      });
    }
    window.dataLayer.push(payload);
  }

  function markProductTourComplete(tourId) {
    try {
      var raw = sessionStorage.getItem(ATTRIBUTION_ORDER_KEY);
      var data = raw ? JSON.parse(raw) : {};
      data.last_tour_id = tourId;
      data.completed_at = Date.now();
      sessionStorage.setItem(ATTRIBUTION_ORDER_KEY, JSON.stringify(data));
    } catch (_e) {
      /* ignore */
    }
  }

  function markCartAttributionWindow(tourId, productId) {
    try {
      sessionStorage.setItem(
        ATTRIBUTION_CART_KEY,
        JSON.stringify({
          tour_id: tourId,
          product_id: productId || null,
          expires_at: Date.now() + 30 * 60 * 1000,
        }),
      );
    } catch (_e) {
      /* ignore */
    }
  }

  function tryFireCartAfterTour(productId) {
    try {
      var raw = sessionStorage.getItem(ATTRIBUTION_CART_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (!data || !data.expires_at || Date.now() > data.expires_at) {
        sessionStorage.removeItem(ATTRIBUTION_CART_KEY);
        return;
      }
      pushTourEvent("cart_after_tour", {
        tour_id: data.tour_id || "product",
        product_id: productId || data.product_id || null,
      });
      sessionStorage.removeItem(ATTRIBUTION_CART_KEY);
    } catch (_e) {
      /* ignore */
    }
  }

  function tryFireOrderAfterTour(orderId) {
    try {
      var raw = sessionStorage.getItem(ATTRIBUTION_ORDER_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (!data || !data.completed_at) return;
      var sevenDays = 7 * 24 * 60 * 60 * 1000;
      if (Date.now() - data.completed_at > sevenDays) {
        sessionStorage.removeItem(ATTRIBUTION_ORDER_KEY);
        return;
      }
      pushTourEvent("order_after_tour", {
        order_id: orderId,
        last_tour_id: data.last_tour_id || "",
      });
    } catch (_e) {
      /* ignore */
    }
  }

  window.ccTourAnalytics = {
    push: pushTourEvent,
    tourStarted: function (tourId, total, tourPath) {
      pushTourEvent("tour_started", {
        tour_id: tourId,
        tour_step_total: total,
        tour_path: tourPath || null,
      });
    },
    stepViewed: function (tourId, step, total, tourPath) {
      pushTourEvent("step_viewed", {
        tour_id: tourId,
        tour_step: step,
        tour_step_total: total,
        tour_path: tourPath || null,
      });
    },
    tourCompleted: function (tourId, total, durationMs, tourPath) {
      markProductTourComplete(tourId);
      if (tourId === "product") {
        markCartAttributionWindow("product", null);
      }
      pushTourEvent("tour_completed", {
        tour_id: tourId,
        tour_step_total: total,
        duration_ms: durationMs,
        tour_path: tourPath || null,
      });
    },
    tourSkipped: function (tourId, step, skipType, tourPath) {
      pushTourEvent("tour_skipped", {
        tour_id: tourId,
        tour_step: step,
        skip_type: skipType || "step",
        tour_path: tourPath || null,
      });
    },
    wishlistSaved: function (productId, tourEligible) {
      pushTourEvent("wishlist_saved", {
        product_id: productId,
        tour_eligible: !!tourEligible,
      });
    },
    checkoutHelpOpened: function () {
      pushTourEvent("checkout_help_opened", {
        tour_id: "checkout",
      });
    },
    tryFireCartAfterTour: tryFireCartAfterTour,
    tryFireOrderAfterTour: tryFireOrderAfterTour,
    markCartAttributionWindow: markCartAttributionWindow,
  };
})(window);
