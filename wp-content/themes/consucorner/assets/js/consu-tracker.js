/**
 * consu-tracker.js
 *
 * Lightweight client-side personalization tracker.
 *
 * As a logged-out OR logged-in doctor browses the marketplace, this script
 * captures three weak signals and persists them to cookies (so PHP/AJAX can
 * read them) and to localStorage (richer history with timestamps for the JS
 * side, capped to a small window).
 *
 *   Cookie name                     Stores (max items)
 *   ------------------------------  ---------------------------------
 *   consu_pref_categories           comma-list of product_cat slugs (10)
 *   consu_pref_specialties          comma-list of specialty slugs   (10)
 *   consu_pref_searches             comma-list of search terms      (5)
 *   consu_last_category_slug        last viewed category slug       (1)
 *   consu_last_specialty_slug       last viewed specialty slug      (1)
 *
 * Each cookie lasts 60 days, path=/.
 *
 * The context for the current page is pushed into `window.consuTrackerContext`
 * by PHP (see functions.php enqueue logic). Example payloads:
 *
 *   { type: "product",  product_id: 123, categories: ["urology"], specialties: ["endoscopy"] }
 *   { type: "category", slug: "urology", taxonomy: "product_cat" }
 *   { type: "specialty", slug: "ophthalmology", taxonomy: "specialty" }
 *   { type: "search",   query: "ledger v63" }
 *
 * The tracker is intentionally tiny: no analytics calls, no external requests.
 */
(function () {
  "use strict";

  var COOKIE_DAYS = 60;
  var LIMITS = {
    categories: 10,
    specialties: 10,
    searches: 5,
  };

  var COOKIE_NAMES = {
    categories: "consu_pref_categories",
    specialties: "consu_pref_specialties",
    searches: "consu_pref_searches",
    lastCategory: "consu_last_category_slug",
    lastSpecialty: "consu_last_specialty_slug",
  };

  var LS_PREFIX = "consu_pref_history_";

  function isString(v) {
    return typeof v === "string" && v.length > 0;
  }

  function sanitizeSlug(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9-_]/g, "")
      .slice(0, 64);
  }

  function sanitizeSearch(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, " ")
      .slice(0, 60);
  }

  function readCookie(name) {
    try {
      var parts = document.cookie ? document.cookie.split("; ") : [];
      for (var i = 0; i < parts.length; i++) {
        var pair = parts[i].split("=");
        if (pair[0] === name) {
          return decodeURIComponent(pair.slice(1).join("=") || "");
        }
      }
    } catch (e) {
      /* noop */
    }
    return "";
  }

  function writeCookie(name, value) {
    try {
      var maxAge = 60 * 60 * 24 * COOKIE_DAYS;
      document.cookie =
        name +
        "=" +
        encodeURIComponent(value || "") +
        "; path=/; max-age=" +
        maxAge +
        "; SameSite=Lax";
    } catch (e) {
      /* noop */
    }
  }

  function readListCookie(name) {
    var raw = readCookie(name);
    if (!raw) return [];
    return raw
      .split(",")
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);
  }

  function writeListCookie(name, list) {
    writeCookie(name, list.join(","));
  }

  function readHistory(key) {
    try {
      var raw = window.localStorage.getItem(LS_PREFIX + key);
      if (!raw) return [];
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function writeHistory(key, list) {
    try {
      window.localStorage.setItem(LS_PREFIX + key, JSON.stringify(list));
    } catch (e) {
      /* noop */
    }
  }

  /**
   * Push one or more values into a key (categories | specialties | searches).
   * Most-recent first. De-duplicated. Capped to its configured limit.
   */
  function recordValues(key, values) {
    if (!Array.isArray(values) || !values.length) return;

    var clean = values
      .map(function (v) {
        return key === "searches" ? sanitizeSearch(v) : sanitizeSlug(v);
      })
      .filter(Boolean);

    if (!clean.length) return;

    var cookieName = COOKIE_NAMES[key];
    var existingCookie = readListCookie(cookieName);
    var history = readHistory(key);
    var now = Date.now();

    var merged = clean.slice();
    for (var i = 0; i < existingCookie.length; i++) {
      if (merged.indexOf(existingCookie[i]) === -1) {
        merged.push(existingCookie[i]);
      }
    }
    merged = merged.slice(0, LIMITS[key]);

    writeListCookie(cookieName, merged);

    var historyMap = {};
    for (var j = 0; j < history.length; j++) {
      if (history[j] && history[j].value) {
        historyMap[history[j].value] = history[j].ts || 0;
      }
    }
    clean.forEach(function (v) {
      historyMap[v] = now;
    });

    var newHistory = Object.keys(historyMap)
      .map(function (v) {
        return { value: v, ts: historyMap[v] };
      })
      .sort(function (a, b) {
        return b.ts - a.ts;
      })
      .slice(0, LIMITS[key]);

    writeHistory(key, newHistory);
  }

  function recordLast(key, value) {
    var clean = sanitizeSlug(value);
    if (!clean) return;
    writeCookie(COOKIE_NAMES[key], clean);
  }

  function ingestContext(ctx) {
    if (!ctx || typeof ctx !== "object") return;

    var type = String(ctx.type || "").toLowerCase();

    if (type === "product") {
      var cats = Array.isArray(ctx.categories) ? ctx.categories : [];
      var specs = Array.isArray(ctx.specialties) ? ctx.specialties : [];
      if (cats.length) {
        recordValues("categories", cats);
        recordLast("lastCategory", cats[0]);
      }
      if (specs.length) {
        recordValues("specialties", specs);
        recordLast("lastSpecialty", specs[0]);
      }
    } else if (type === "category") {
      if (isString(ctx.slug)) {
        var bucket = ctx.taxonomy === "specialty" ? "specialties" : "categories";
        recordValues(bucket, [ctx.slug]);
        recordLast(bucket === "specialties" ? "lastSpecialty" : "lastCategory", ctx.slug);
      }
    } else if (type === "specialty") {
      if (isString(ctx.slug)) {
        recordValues("specialties", [ctx.slug]);
        recordLast("lastSpecialty", ctx.slug);
      }
    } else if (type === "search") {
      if (isString(ctx.query)) {
        recordValues("searches", [ctx.query]);
      }
    }
  }

  /**
   * Public helper for other scripts (e.g. live search, specialty pills)
   * to push events into the tracker on demand.
   */
  function record(payload) {
    ingestContext(payload);
  }

  /**
   * Public helper for AJAX handlers to read the full interest profile.
   */
  function getProfile() {
    return {
      categories: readListCookie(COOKIE_NAMES.categories),
      specialties: readListCookie(COOKIE_NAMES.specialties),
      searches: readListCookie(COOKIE_NAMES.searches),
      lastCategory: readCookie(COOKIE_NAMES.lastCategory),
      lastSpecialty: readCookie(COOKIE_NAMES.lastSpecialty),
    };
  }

  window.consuTracker = {
    record: record,
    getProfile: getProfile,
  };

  function boot() {
    if (window.consuTrackerContext) {
      try {
        ingestContext(window.consuTrackerContext);
      } catch (e) {
        /* swallow tracker errors silently */
      }
    }

    var searchInputs = document.querySelectorAll('form[role="search"] input[name="s"]');
    Array.prototype.forEach.call(searchInputs, function (input) {
      var form = input.form;
      if (!form) return;
      form.addEventListener("submit", function () {
        var query = (input.value || "").trim();
        if (query.length >= 3) {
          record({ type: "search", query: query });
        }
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
