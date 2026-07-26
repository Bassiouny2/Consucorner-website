(function () {
  "use strict";

  var config = window.consuSearchData || {};
  var minLength = parseInt(config.minLength, 10) || 3;
  var forms = Array.prototype.slice.call(
    document.querySelectorAll("[data-cc-live-search-form]"),
  );

  if (!forms.length || !config.ajaxUrl || !config.nonce) return;

  function escapeHtml(value) {
    return String(value || "").replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      }[char];
    });
  }

  function getSearchInput(form) {
    return form.querySelector('input[name="s"]');
  }

  function getPanel(form) {
    if (form.__ccPanelEl) {
      return form.__ccPanelEl;
    }
    var panel = form.querySelector("[data-cc-live-search-panel]");
    if (panel) {
      form.__ccPanelEl = panel;
    }
    return panel;
  }

  function isMobilePanel(panel) {
    return !!(
      panel && panel.classList.contains("cc-live-search-panel--mobile")
    );
  }

  function resetPanelViewportStyles(panel) {
    if (!panel) return;
    panel.style.position = "";
    panel.style.top = "";
    panel.style.left = "";
    panel.style.right = "";
    panel.style.width = "";
    panel.style.maxHeight = "";
    panel.style.overflowY = "";
    panel.style.transform = "";
    panel.classList.remove(
      "is-keyboard-open",
      "cc-live-search-panel--empty",
      "cc-live-search-panel--fixed",
    );
  }

  function portalMobilePanel(form) {
    var panel = getPanel(form);
    if (!panel || !isMobilePanel(panel) || panel.parentNode === document.body) {
      return;
    }

    if (!form.__ccPanelPlaceholder) {
      form.__ccPanelPlaceholder = document.createComment(
        "cc-live-search-panel-anchor",
      );
      panel.parentNode.insertBefore(form.__ccPanelPlaceholder, panel);
    }

    document.body.appendChild(panel);
    panel.classList.add("cc-live-search-panel--fixed");
  }

  function restoreMobilePanel(form) {
    var panel = form.__ccPanelEl;
    if (!panel || panel.parentNode !== document.body) {
      return;
    }

    panel.classList.remove("cc-live-search-panel--fixed");

    if (form.__ccPanelPlaceholder && form.__ccPanelPlaceholder.parentNode) {
      form.__ccPanelPlaceholder.parentNode.insertBefore(
        panel,
        form.__ccPanelPlaceholder.nextSibling,
      );
      return;
    }

    form.appendChild(panel);
  }

  function adjustPanelForViewport(form) {
    var panel = getPanel(form);
    var input = getSearchInput(form);
    if (!panel || panel.hidden || !input) return;

    var isMobile = isMobilePanel(panel);

    if (isMobile) {
      portalMobilePanel(form);
    }

    var vv = window.visualViewport;
    var inputRect = input.getBoundingClientRect();
    var gap = 8;
    var visibleBottom = vv ? vv.offsetTop + vv.height : window.innerHeight;
    var available = visibleBottom - inputRect.bottom - gap;
    var fallbackCap = isMobile ? 260 : 560;
    var maxH = Math.max(148, Math.min(available, fallbackCap));

    if (isMobile) {
      panel.style.top = inputRect.bottom + gap + "px";
      panel.style.position = "";
      panel.style.width = "";
      panel.style.left = "";
      panel.style.right = "";
      panel.style.transform = "";
    }

    panel.style.maxHeight = maxH + "px";
    panel.style.overflowY = "auto";

    if (panel.scrollTop > 0) {
      panel.scrollTop = 0;
    }
  }

  function bindViewportSync(form) {
    if (form.__ccViewportBound) return;
    form.__ccViewportBound = true;

    var sync = function () {
      adjustPanelForViewport(form);
    };

    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", sync);
      window.visualViewport.addEventListener("scroll", sync);
    }
    window.addEventListener("resize", sync);
    window.addEventListener("scroll", sync, true);
  }

  function closePanel(form) {
    var panel = getPanel(form);
    var input = getSearchInput(form);
    if (!panel) return;
    panel.hidden = true;
    panel.innerHTML = "";
    resetPanelViewportStyles(panel);
    restoreMobilePanel(form);
    if (input) input.setAttribute("aria-expanded", "false");
  }

  function closeAll(exceptForm) {
    forms.forEach(function (form) {
      if (form !== exceptForm) closePanel(form);
    });
  }

  function getSearchUrl(query) {
    var url = new URL(config.searchUrl || "/", window.location.origin);
    url.searchParams.set("s", query);
    return url.toString();
  }

  function navigateToFullSearch(form, input) {
    var query = input.value.trim();

    if (!query) {
      return false;
    }

    closePanel(form);
    input.value = query;
    window.location.assign(getSearchUrl(query));
    return true;
  }

  function renderLoading(form) {
    var panel = getPanel(form);
    var input = getSearchInput(form);
    if (!panel) return;
    panel.hidden = false;
    resetPanelViewportStyles(panel);
    if (input) input.setAttribute("aria-expanded", "true");
    panel.innerHTML =
      '<div class="cc-live-search-loading">Searching products and categories...</div>';
    bindViewportSync(form);
    window.requestAnimationFrame(function () {
      adjustPanelForViewport(form);
    });
  }

  function renderResults(form, query, data) {
    var panel = getPanel(form);
    var input = getSearchInput(form);
    if (!panel) return;

    var categories = Array.isArray(data.categories) ? data.categories : [];
    var specialties = Array.isArray(data.specialties) ? data.specialties : [];
    var products = Array.isArray(data.products) ? data.products : [];
    var productTotal = parseInt(data.productTotal, 10) || products.length;
    var isEmpty =
      !categories.length && !specialties.length && !products.length;
    var html = "";

    if (isEmpty) {
      html += '<div class="cc-live-search-empty">';
      html += "<strong>No quick matches</strong>";
      html +=
        '<span>Tap <strong>View all results</strong> or press Go to search the full catalog for "' +
        escapeHtml(query) +
        '".</span>';
      html += "</div>";
    } else {
      if (specialties.length) {
        html += '<div class="cc-live-search-section">';
        html +=
          '<div class="cc-live-search-heading"><span>Specialties</span><em>' +
          specialties.length +
          "</em></div>";
        html +=
          '<div class="cc-live-search-scroll cc-live-search-scroll--categories">';
        html += specialties
          .map(function (item) {
            return (
              '<a class="cc-live-search-category cc-live-search-category--specialty" href="' +
              escapeHtml(item.url) +
              '">' +
              '<span class="cc-live-search-category-icon" aria-hidden="true">+</span>' +
              "<span><strong>" +
              escapeHtml(item.title) +
              "</strong><small>" +
              escapeHtml(item.count || 0) +
              " products</small></span>" +
              "</a>"
            );
          })
          .join("");
        html += "</div>";
        html += "</div>";
      }

      if (categories.length) {
        html += '<div class="cc-live-search-section">';
        html +=
          '<div class="cc-live-search-heading"><span>Categories</span><em>' +
          categories.length +
          "</em></div>";
        html +=
          '<div class="cc-live-search-scroll cc-live-search-scroll--categories">';
        html += categories
          .map(function (item) {
            return (
              '<a class="cc-live-search-category" href="' +
              escapeHtml(item.url) +
              '">' +
              '<span class="cc-live-search-category-icon" aria-hidden="true">#</span>' +
              "<span><strong>" +
              escapeHtml(item.title) +
              "</strong><small>" +
              (item.parent ? escapeHtml(item.parent) + " • " : "") +
              escapeHtml(item.count || 0) +
              " products</small></span>" +
              "</a>"
            );
          })
          .join("");
        html += "</div>";
        html += "</div>";
      }

      if (products.length) {
        html += '<div class="cc-live-search-section">';
        html +=
          '<div class="cc-live-search-heading"><span>Products</span><em>' +
          productTotal +
          "</em></div>";
        html +=
          '<div class="cc-live-search-scroll cc-live-search-scroll--products">';
        html += products
          .map(function (item) {
            var priceClass = item.is_quote
              ? "cc-live-search-price cc-live-search-price--quote"
              : "cc-live-search-price";
            return (
              '<a class="cc-live-search-product' +
              (item.is_quote ? " cc-live-search-product--quote" : "") +
              '" href="' +
              escapeHtml(item.url) +
              '">' +
              '<span class="cc-live-search-thumb"><img src="' +
              escapeHtml(item.image) +
              '" alt="" loading="lazy"></span>' +
              '<span class="cc-live-search-product-copy"><strong>' +
              escapeHtml(item.title) +
              "</strong><small>" +
              escapeHtml(item.category || item.stock || "") +
              "</small></span>" +
              '<span class="' +
              priceClass +
              '">' +
              escapeHtml(item.price || "") +
              "</span>" +
              "</a>"
            );
          })
          .join("");
        html += "</div>";
        html += "</div>";
      }
    }

    html +=
      '<a class="cc-live-search-all" href="' +
      escapeHtml(getSearchUrl(query)) +
      '">View all ' +
      (productTotal ? productTotal + " " : "") +
      'results for <strong>' +
      escapeHtml(query) +
      "</strong></a>";

    panel.innerHTML = html;
    panel.hidden = false;
    panel.classList.toggle("cc-live-search-panel--empty", isEmpty);
    if (input) input.setAttribute("aria-expanded", "true");
    bindViewportSync(form);
    window.requestAnimationFrame(function () {
      adjustPanelForViewport(form);
    });
  }

  function initForm(form) {
    var input = getSearchInput(form);
    if (!input) return;

    var timer = null;
    var controller = null;
    var submitLock = false;

    function searchNow() {
      var query = input.value.trim();
      closeAll(form);

      if (query.length < minLength) {
        closePanel(form);
        return;
      }

      if (controller) controller.abort();
      controller = new AbortController();
      renderLoading(form);

      var url = new URL(config.ajaxUrl, window.location.origin);
      url.searchParams.set("action", "consucorner_live_search");
      url.searchParams.set("nonce", config.nonce);
      url.searchParams.set("q", query);

      fetch(url.toString(), {
        method: "GET",
        credentials: "same-origin",
        signal: controller.signal,
      })
        .then(function (response) {
          if (!response.ok) throw new Error("Search request failed");
          return response.json();
        })
        .then(function (payload) {
          if (!payload || !payload.success)
            throw new Error("Search response failed");
          renderResults(form, query, payload.data || {});
        })
        .catch(function (error) {
          if (error && error.name === "AbortError") return;
          var panel = getPanel(form);
          if (panel) {
            panel.hidden = false;
            panel.classList.add("cc-live-search-panel--empty");
            panel.innerHTML =
              '<div class="cc-live-search-empty"><strong>Search is unavailable</strong><span>Tap View all results or press Go to search the catalog.</span></div>' +
              '<a class="cc-live-search-all" href="' +
              escapeHtml(getSearchUrl(input.value.trim())) +
              '">View all results</a>';
            bindViewportSync(form);
            window.requestAnimationFrame(function () {
              adjustPanelForViewport(form);
            });
          }
        });
    }

    function runFullSearch() {
      if (submitLock) return;
      submitLock = true;
      window.setTimeout(function () {
        submitLock = false;
      }, 400);
      navigateToFullSearch(form, input);
    }

    input.addEventListener("input", function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(searchNow, 240);
    });

    input.addEventListener("focus", function () {
      bindViewportSync(form);
      if (input.value.trim().length >= minLength) {
        window.clearTimeout(timer);
        timer = window.setTimeout(searchNow, 120);
      }
    });

    input.addEventListener("blur", function () {
      window.setTimeout(function () {
        var panel = getPanel(form);
        if (!panel || panel.hidden) return;
        if (panel.contains(document.activeElement)) return;
        adjustPanelForViewport(form);
      }, 120);
    });

    input.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closePanel(form);
        input.blur();
        return;
      }

      if (event.key === "Enter") {
        event.preventDefault();
        runFullSearch();
      }
    });

    input.addEventListener("search", function () {
      runFullSearch();
    });

    form.addEventListener("submit", function (event) {
      var query = input.value.trim();

      if (!query) {
        event.preventDefault();
        closePanel(form);
        return;
      }

      event.preventDefault();
      runFullSearch();
    });
  }

  forms.forEach(initForm);

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-cc-live-search-form]")) return;
    if (event.target.closest("[data-cc-live-search-panel]")) return;
    closeAll(null);
  });
})();
