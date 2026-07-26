/* ------------------------------------------------------------------ */
/*  AJAX cart badge updater (global)                                   */
/* ------------------------------------------------------------------ */
(function () {
  "use strict";

  /* Use the PHP-localized site URL (set by consuSiteData in functions.php).
     Falls back to the current page's origin so add-to-cart always hits
     this site, not a hardcoded staging/production domain. */
  var STORE_URL =
    window.consuSiteData && window.consuSiteData.siteUrl
      ? window.consuSiteData.siteUrl.replace(/\/$/, "")
      : window.location.origin;
  var WC_API_BASE = STORE_URL + "/wp-json/wc/v3";
  var WC_CONSUMER_KEY = "";
  var WC_CONSUMER_SECRET = "";
  var COUNT_KEY = "cc_cart_count";
  var WISHLIST_KEY = "cc_saved_products";
  var WISHLIST_DETAILS_KEY = "cc_saved_product_details";
  var WISHLIST_BUTTON_SELECTOR =
    ".btn-save, .oow-btn-save, .sp-wishlist-btn, .card-wish-icon";
  var productIdCache = {};

  function toInt(val, fallback) {
    var n = parseInt(val, 10);
    return isNaN(n) ? fallback || 0 : n;
  }

  function getStoredCount() {
    return toInt(localStorage.getItem(COUNT_KEY), 0);
  }

  function storeCount(count) {
    localStorage.setItem(COUNT_KEY, String(Math.max(0, count)));
  }

  function getWishlist() {
    try {
      var parsed = JSON.parse(localStorage.getItem(WISHLIST_KEY) || "[]");
      return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (_e) {
      return [];
    }
  }

  function storeWishlist(ids) {
    var cleaned = Array.from(new Set(ids.map(String)));
    localStorage.setItem(WISHLIST_KEY, JSON.stringify(cleaned));
    window.dispatchEvent(
      new CustomEvent("cc:wishlist-updated", { detail: { ids: cleaned } }),
    );
  }

  function getWishlistDetails() {
    try {
      var parsed = JSON.parse(
        localStorage.getItem(WISHLIST_DETAILS_KEY) || "{}",
      );
      return parsed && typeof parsed === "object" && !Array.isArray(parsed)
        ? parsed
        : {};
    } catch (_e) {
      return {};
    }
  }

  function storeWishlistDetails(details) {
    localStorage.setItem(WISHLIST_DETAILS_KEY, JSON.stringify(details || {}));
  }

  function setWishlistButtonState(button, saved) {
    if (!button) return;
    button.classList.toggle("is-saved", saved);
    button.setAttribute("aria-pressed", saved ? "true" : "false");
    button.setAttribute("title", saved ? "Saved" : "Save");

    var label = saved ? "Saved" : "Save";
    if (
      button.classList.contains("sp-wishlist-btn") ||
      button.classList.contains("card-wish-icon")
    ) {
      button.setAttribute("aria-label", label);
    }
  }

  function setBadge(count) {
    var safe = Math.max(0, toInt(count, 0));
    document.querySelectorAll(".cart-badge").forEach(function (badge) {
      if (!badge) return;
      badge.textContent = safe > 0 ? String(safe) : "";
      badge.style.display = safe > 0 ? "inline-flex" : "none";
    });
    storeCount(safe);
  }

  function parseCountFromFragments(data) {
    if (!data || !data.fragments) return null;
    var html = Object.keys(data.fragments)
      .map(function (k) {
        return String(data.fragments[k]);
      })
      .join(" ");

    var m = html.match(/cart-badge[^>]*>(\d+)</i);
    if (m && m[1]) return toInt(m[1], null);

    m = html.match(/cart-contents-count[^>]*>(\d+)</i);
    if (m && m[1]) return toInt(m[1], null);

    return null;
  }

  function buildWooUrl(endpoint, query) {
    var url = new URL(WC_API_BASE + endpoint);
    var params = query || {};
    Object.keys(params).forEach(function (key) {
      if (
        params[key] !== undefined &&
        params[key] !== null &&
        params[key] !== ""
      ) {
        url.searchParams.set(key, String(params[key]));
      }
    });
    url.searchParams.set("consumer_key", WC_CONSUMER_KEY);
    url.searchParams.set("consumer_secret", WC_CONSUMER_SECRET);
    return url.toString();
  }

  function resolveProductIdFromButton(button) {
    if (!button) return Promise.resolve(null);

    var idFromData =
      button.getAttribute("data-product-id") ||
      button.getAttribute("data-product_id");
    if (idFromData) return Promise.resolve(toInt(idFromData, null));

    var idFromValue = button.getAttribute("value");
    if (
      idFromValue &&
      (button.classList.contains("sp-btn-cart") ||
        button.getAttribute("name") === "add-to-cart")
    ) {
      return Promise.resolve(toInt(idFromValue, null));
    }

    var form = button.closest("form.cart");
    var formProductInput = form
      ? form.querySelector('[name="add-to-cart"], [name="product_id"]')
      : null;
    if (formProductInput && formProductInput.value) {
      return Promise.resolve(toInt(formProductInput.value, null));
    }

    var parentCard = button.closest(
      ".card-shop, .oow-card, .fp-card, .ap-card, .sp-product",
    );
    var relatedCartButton = parentCard
      ? parentCard.querySelector(
          '[data-product-id], [data-product_id], [name="add-to-cart"]',
        )
      : null;
    var relatedId = relatedCartButton
      ? relatedCartButton.getAttribute("data-product-id") ||
        relatedCartButton.getAttribute("data-product_id") ||
        relatedCartButton.getAttribute("value")
      : "";
    if (relatedId) {
      return Promise.resolve(toInt(relatedId, null));
    }
    if (relatedCartButton) {
      try {
        var relatedUrl = new URL(
          relatedCartButton.getAttribute("href") || "",
          window.location.href,
        );
        var relatedQueryId = relatedUrl.searchParams.get("add-to-cart");
        if (relatedQueryId) return Promise.resolve(toInt(relatedQueryId, null));
      } catch (_relatedError) {
        // no-op
      }
    }

    var href = button.getAttribute("href") || "";
    try {
      var url = new URL(href, window.location.href);
      var qId = url.searchParams.get("add-to-cart");
      if (qId) return Promise.resolve(toInt(qId, null));
    } catch (_e) {
      // no-op
    }

    var card = button.closest(".card-shop, .oow-card");
    var titleEl = card
      ? card.querySelector(".product-card-title") ||
        card.querySelector(".oow-card-title")
      : document.querySelector(".sp-title");
    var productName = titleEl ? titleEl.textContent.trim() : "";
    if (!productName) return Promise.resolve(null);

    if (productIdCache[productName]) {
      return Promise.resolve(productIdCache[productName]);
    }

    var lookupUrl = buildWooUrl("/products", {
      search: productName,
      per_page: 10,
      status: "publish",
    });

    return fetch(lookupUrl)
      .then(function (res) {
        if (!res.ok) throw new Error("Product lookup failed: " + res.status);
        return res.json();
      })
      .then(function (products) {
        if (!Array.isArray(products) || !products.length) return null;

        var exact = products.find(function (p) {
          return (
            (p.name || "").trim().toLowerCase() === productName.toLowerCase()
          );
        });
        var chosen = exact || products[0];
        var resolved = chosen && chosen.id ? toInt(chosen.id, null) : null;
        if (resolved) productIdCache[productName] = resolved;
        return resolved;
      })
      .catch(function () {
        return null;
      });
  }

  function addToCartAjax(productId, qty) {
    var endpoint = STORE_URL.replace(/\/$/, "") + "/?wc-ajax=add_to_cart";
    var body = new URLSearchParams();
    body.set("product_id", String(productId));
    body.set("quantity", String(qty || 1));

    return fetch(endpoint, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body.toString(),
    }).then(function (res) {
      if (!res.ok) throw new Error("Add-to-cart failed: " + res.status);
      return res.json();
    });
  }

  function reflectAddedState(button) {
    if (!button) return;
    var original = button.textContent;
    button.textContent = "Added!";
    setTimeout(function () {
      button.textContent = original;
    }, 1200);
  }

  function getRequestedQuantity(button) {
    if (!button) return 1;

    // Single product page quantity input.
    var qtyInput = document.getElementById("spQtyVal");
    if (button.classList.contains("sp-btn-cart") && qtyInput) {
      return Math.max(1, toInt(qtyInput.value, 1));
    }

    // Archive/offer cards render data-quantity for bundle deals (e.g. "Add 10
    // for 18.00 EGP") so the advertised bundle size is actually what gets
    // added, instead of always defaulting to a single unit.
    var dataQty = button.getAttribute("data-quantity");
    if (dataQty !== null) {
      return Math.max(1, toInt(dataQty, 1));
    }

    return 1;
  }

  /**
   * Extract product display data from the card element containing the button.
   * Reads data-product-* attributes first, then falls back to DOM extraction.
   * @param {HTMLElement} button
   * @returns {{ name: string, price: number, image: string, permalink: string }}
   */
  function extractItemData(button) {
    /* Prefer explicit data attributes (set by browse-specialty.js etc.) */
    var name = button.getAttribute("data-product-name") || "";
    var price = parseFloat(button.getAttribute("data-product-price") || 0);
    var image = button.getAttribute("data-product-image") || "";
    var permalink = button.getAttribute("data-product-permalink") || "#";

    /* Fall back to DOM extraction from the parent card */
    var card = button.closest(
      ".card-shop, .oow-card, .fp-card, .ap-card, .sp-product",
    );
    if (card) {
      if (!name) {
        var nameEl = card.querySelector(
          ".product-card-title, .oow-card-title, .fp-card-title, .ap-card-name, .sp-title, h3, h2",
        );
        if (nameEl) name = nameEl.textContent.trim();
      }
      if (!price) {
        var priceEl = card.querySelector(
          ".price, .oow-price, .fp-card-price, .ap-card-price, .sp-price",
        );
        if (priceEl) {
          /* For sale items WC renders <del>old</del><ins>new</ins>.
             Prefer the <ins> (current/sale price), fall back to full text. */
          var insEl = priceEl.querySelector("ins");
          var rawText = (insEl || priceEl).textContent;
          price = parseFloat(rawText.replace(/[^0-9.]/g, "")) || 0;
        }
      }
      if (!image) {
        var imgEl = card.querySelector(
          ".card-shop-img-wrapper img, .oow-card-img-wrapper img, img",
        );
        if (imgEl) image = imgEl.getAttribute("src") || "";
      }
      if (permalink === "#") {
        var viewLink = card.querySelector(
          'a[href*="product"], a[href*="single-product"], a[href*="single_product"]',
        );
        if (viewLink) permalink = viewLink.href || "#";
      }
    }

    /* Last resort: single-product page */
    if (!name) {
      var spTitle = document.querySelector(".sp-title");
      if (spTitle) name = spTitle.textContent.trim();
    }
    if (!image) {
      var spImg = document.querySelector(".sp-main-img, .sp-gallery img");
      if (spImg) image = spImg.src || "";
    }
    if (
      permalink === "#" &&
      document.body.classList.contains("single-product")
    ) {
      permalink = window.location.href;
    }
    if (!price && document.body.classList.contains("single-product")) {
      var spPriceEl = document.querySelector(
        "#spProductPrice .sp-price, #spProductPrice ins .woocommerce-Price-amount, #spProductPrice .woocommerce-Price-amount, #spStickyPrice .sp-price, .sp-price-current .sp-price",
      );
      if (spPriceEl) {
        price = parseFloat(spPriceEl.textContent.replace(/[^0-9.]/g, "")) || 0;
      }
    }

    return { name: name, price: price, image: image, permalink: permalink };
  }

  function showWishlistNotice(message) {
    var notice = document.querySelector(".cc-wishlist-toast");
    if (!notice) {
      notice = document.createElement("div");
      notice.className = "cc-wishlist-toast";
      notice.setAttribute("role", "status");
      notice.setAttribute("aria-live", "polite");
      document.body.appendChild(notice);
    }

    notice.textContent = message;
    notice.classList.add("is-visible");
    clearTimeout(showWishlistNotice.timer);
    showWishlistNotice.timer = setTimeout(function () {
      notice.classList.remove("is-visible");
    }, 1800);
  }

  function initWishlistButtons() {
    var savedIds = getWishlist();
    document
      .querySelectorAll(WISHLIST_BUTTON_SELECTOR)
      .forEach(function (button) {
        resolveProductIdFromButton(button).then(function (productId) {
          if (!productId) return;
          button.setAttribute("data-cc-wishlist-id", String(productId));
          setWishlistButtonState(
            button,
            savedIds.indexOf(String(productId)) !== -1,
          );
        });
      });
  }

  function bindWishlistDelegation() {
    document.addEventListener(
      "click",
      function (e) {
        var button = e.target.closest(WISHLIST_BUTTON_SELECTOR);
        if (!button) return;

        e.preventDefault();
        e.stopPropagation();
        var itemData = extractItemData(button);

        resolveProductIdFromButton(button).then(function (productId) {
          if (!productId) {
            showWishlistNotice("Unable to save this product.");
            return;
          }

          var id = String(productId);
          var savedIds = getWishlist();
          var existingIndex = savedIds.indexOf(id);
          var isSaved = existingIndex === -1;

          if (isSaved) {
            savedIds.push(id);
          } else {
            savedIds.splice(existingIndex, 1);
          }

          storeWishlist(savedIds);
          var details = getWishlistDetails();
          if (isSaved) {
            details[id] = {
              id: productId,
              name: itemData.name,
              image: itemData.image,
              permalink: itemData.permalink,
              price_html: itemData.price ? String(itemData.price) + " EGP" : "",
              category: "Saved Product",
              meta: "",
            };
          } else {
            delete details[id];
          }
          storeWishlistDetails(details);
          document
            .querySelectorAll(WISHLIST_BUTTON_SELECTOR)
            .forEach(function (candidate) {
              resolveProductIdFromButton(candidate).then(
                function (candidateId) {
                  if (String(candidateId) === id) {
                    candidate.setAttribute("data-cc-wishlist-id", id);
                    setWishlistButtonState(candidate, isSaved);
                  }
                },
              );
            });
          showWishlistNotice(
            isSaved ? "Saved to wishlist." : "Removed from wishlist.",
          );

          if (isSaved) {
            window.setTimeout(function () {
              window.dispatchEvent(
                new CustomEvent("cc:wishlist-saved", {
                  detail: { productId: id },
                }),
              );
              if (window.ccTourAnalytics) {
                window.ccTourAnalytics.wishlistSaved(id, true);
              }
            }, 400);
          }
        });
      },
      true,
    );
  }

  function bindAddToCartDelegation() {
    document.addEventListener(
      "click",
      function (e) {
        var button = e.target.closest(
          ".btn-add-cart, .oow-btn-add-cart, .sp-btn-cart",
        );
        if (!button) return;

        if (
          button.classList.contains("btn-add-cart--quote") ||
          button.classList.contains("js-cc-quote-trigger")
        ) {
          return;
        }

        if (button.classList.contains("btn-add-cart--disabled")) {
          e.preventDefault();
          return;
        }

        if (
          button.classList.contains("btn-add-cart--variable") ||
          button.closest(".card-variation-panel")
        ) {
          return;
        }

        if (
          button.closest(".variations_form") ||
          button.id === "spStickyAddCart"
        ) {
          return;
        }

        e.preventDefault();

        var qty = getRequestedQuantity(button);
        var itemData = extractItemData(button); /* capture before async */

        resolveProductIdFromButton(button)
          .then(function (productId) {
            if (!productId) {
              throw new Error("No product ID could be resolved.");
            }

            /* Feed mini-cart immediately (optimistic, matches WoodMart UX) */
            if (window.CCMiniCart) {
              window.CCMiniCart.addItem({
                id: productId,
                name: itemData.name,
                price: itemData.price,
                qty: qty,
                image: itemData.image,
                permalink: itemData.permalink,
              });
              window.CCMiniCart.open();
            }

            return addToCartAjax(productId, qty);
          })
          .then(function (data) {
            var parsed = parseCountFromFragments(data);
            var current = getStoredCount();
            setBadge(parsed !== null ? parsed : current + qty);
            reflectAddedState(button);
            if (window.ccGtm && window.ccGtm.pushAddToCart) {
              var gtmItem = window.ccGtm.readItemFromEl(button);
              if (gtmItem) window.ccGtm.pushAddToCart(gtmItem);
            }
            /* Sync mini-cart drawer from WC session — gets authoritative price
               and the wcKey needed for subsequent remove / qty updates. */
            if (window.CCMiniCart && window.CCMiniCart.loadFromWC) {
              window.CCMiniCart.loadFromWC();
            } else if (window.CCMiniCart) {
              window.CCMiniCart.syncBadge();
            }
          })
          .catch(function (err) {
            if (window.CC_DEBUG) {
              console.warn("[ConsuCorner cart] " + err.message);
            }
          });
      },
      true,
    );
  }

  function initBadgeFromCache() {
    /* PHP-rendered WC cart count is the authoritative source.
       This prevents a stale localStorage value showing the wrong count
       after the server-side cart is cleared/modified. */
    if (
      window.consuSiteData &&
      typeof window.consuSiteData.cartCount !== "undefined"
    ) {
      setBadge(window.consuSiteData.cartCount);
    } else {
      setBadge(getStoredCount());
    }
  }

  initBadgeFromCache();
  bindAddToCartDelegation();
  initWishlistButtons();
  bindWishlistDelegation();
})();
