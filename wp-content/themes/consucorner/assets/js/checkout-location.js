/* ============================================================
 * Checkout delivery location — GPS + Leaflet + reverse geocode
 * Optional helper; never blocks manual checkout.
 * ============================================================ */
(function () {
  "use strict";

  var cfg = window.ccCheckoutLocation || {};
  var strings = cfg.strings || {};
  var statesMap = cfg.states || {};
  var stateOptions = Array.isArray(cfg.stateOptions) ? cfg.stateOptions : [];
  var maxRetries = Number(cfg.maxRetries) > 0 ? Number(cfg.maxRetries) : 3;
  var retryDelay = Number(cfg.retryDelay) > 0 ? Number(cfg.retryDelay) : 900;
  var DEFAULT_CENTER = [30.0444, 31.2357];
  var DEFAULT_ZOOM = 15;

  var map = null;
  var marker = null;
  var mapReady = false;
  var geocodeTimer = null;
  var busy = false;
  var activeController = null;
  var geocodeRequestId = 0;

  function $(id) {
    return document.getElementById(id);
  }

  function normalizeLabel(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .replace(/ governorate/g, "")
      .trim();
  }

  function dispatchFieldChange(el) {
    if (!el) return;
    el.dispatchEvent(new Event("change", { bubbles: true }));
    el.dispatchEvent(new Event("input", { bubbles: true }));
  }

  function setStatus(text, type) {
    var el = $("co-location-status");
    if (!el) return;
    el.textContent = text || "";
    el.classList.remove(
      "co-location-status--loading",
      "co-location-status--success",
      "co-location-status--soft-error",
    );
    if (type) el.classList.add("co-location-status--" + type);
  }

  function setCardSuccess(on) {
    var card = $("co-location-card");
    if (card) card.classList.toggle("co-location-card--success", !!on);
  }

  function setCoords(lat, lng) {
    var latEl = $("cc_delivery_lat");
    var lngEl = $("cc_delivery_lng");
    if (latEl) latEl.value = String(lat);
    if (lngEl) lngEl.value = String(lng);
  }

  function showMap() {
    var mapEl = $("co-location-map");
    var btn = $("co-use-location");
    if (!mapEl) return;
    mapEl.hidden = false;
    if (btn) btn.setAttribute("aria-expanded", "true");
  }

  function ensureMap(center) {
    if (mapReady || typeof window.L === "undefined") return;
    var mapEl = $("co-location-map");
    if (!mapEl) return;

    var latLng = center || DEFAULT_CENTER;
    map = window.L.map(mapEl, {
      scrollWheelZoom: false,
      attributionControl: true,
    }).setView(latLng, DEFAULT_ZOOM);

    window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    marker = window.L.marker(latLng, { draggable: true }).addTo(map);
    marker.on("dragend", function () {
      var pos = marker.getLatLng();
      setCoords(pos.lat, pos.lng);
      debouncedGeocode(pos.lat, pos.lng);
    });

    mapReady = true;
    window.setTimeout(function () {
      if (map) map.invalidateSize();
    }, 120);
  }

  function moveMarker(lat, lng) {
    setCoords(lat, lng);
    if (!mapReady) {
      ensureMap([lat, lng]);
    }
    if (marker) marker.setLatLng([lat, lng]);
    if (map) map.setView([lat, lng], DEFAULT_ZOOM);
  }

  function optionExists(selectEl, value) {
    if (!selectEl || !value) return false;
    return Array.prototype.some.call(selectEl.options, function (opt) {
      return opt.value === value;
    });
  }

  function fuzzyMatchGovernorateCode(stateName) {
    var needle = normalizeLabel(stateName);
    if (!needle) return "";

    var i;
    for (i = 0; i < stateOptions.length; i++) {
      var opt = stateOptions[i];
      if (!opt || !opt.code) continue;
      var label = normalizeLabel(opt.label);
      if (!label) continue;
      if (needle === label || needle.indexOf(label) !== -1 || label.indexOf(needle) !== -1) {
        return opt.code;
      }
    }

    var code;
    for (code in statesMap) {
      if (!Object.prototype.hasOwnProperty.call(statesMap, code)) continue;
      var mapLabel = normalizeLabel(statesMap[code]);
      if (!mapLabel) continue;
      if (needle === mapLabel || needle.indexOf(mapLabel) !== -1 || mapLabel.indexOf(needle) !== -1) {
        return code;
      }
    }
    return "";
  }

  function resolveGovernorateCode(stateCode, stateName, displayName) {
    if (stateCode) {
      return stateCode;
    }

    var fromName = fuzzyMatchGovernorateCode(stateName);
    if (fromName) return fromName;

    if (displayName) {
      var parts = String(displayName).split(",");
      var p;
      for (p = 0; p < parts.length; p++) {
        var chunk = parts[p].replace(/\d+/g, "").trim();
        if (!chunk) continue;
        var fromChunk = fuzzyMatchGovernorateCode(chunk);
        if (fromChunk) return fromChunk;
      }
    }

    return stateCode || "";
  }

  function applySelectValue(selectEl, code) {
    if (!selectEl || !code) return false;

    if (window.jQuery) {
      var $el = window.jQuery(selectEl);
      if ($el.find('option[value="' + code + '"]').length) {
        $el.val(code).trigger("change");
        if (String($el.val()) === String(code)) {
          return true;
        }
      }
    }

    var i;
    for (i = 0; i < selectEl.options.length; i++) {
      if (selectEl.options[i].value === code) {
        selectEl.selectedIndex = i;
        selectEl.value = code;
        dispatchFieldChange(selectEl);
        return String(selectEl.value) === String(code);
      }
    }

    return false;
  }

  function setGovernorate(stateCode, stateName, displayName) {
    var stateEl = $("billing_state");
    if (!stateEl) return false;

    var resolvedCode = resolveGovernorateCode(stateCode, stateName, displayName);

    if (stateEl.tagName === "SELECT") {
      if (resolvedCode && applySelectValue(stateEl, resolvedCode)) {
        stateEl.classList.remove("co-input--attention");
        return true;
      }

      if (stateName) {
        var matchedByLabel = "";
        var needle = normalizeLabel(stateName);
        Array.prototype.forEach.call(stateEl.options, function (opt) {
          if (matchedByLabel || !opt.value) return;
          var label = normalizeLabel(opt.textContent);
          if (needle === label || needle.indexOf(label) !== -1 || label.indexOf(needle) !== -1) {
            matchedByLabel = opt.value;
          }
        });
        if (matchedByLabel && applySelectValue(stateEl, matchedByLabel)) {
          stateEl.classList.remove("co-input--attention");
          return true;
        }
      }

      return false;
    }

    if (resolvedCode || stateName) {
      stateEl.value = resolvedCode || stateName;
      stateEl.classList.remove("co-input--attention");
      dispatchFieldChange(stateEl);
      return true;
    }

    return false;
  }

  function fillFields(data) {
    if (!data) return { addressFilled: false, governorateFilled: false };

    var addressEl = $("billing_address_1");
    var addressFilled = false;

    var governorateFilled = setGovernorate(
      data.state_code,
      data.state_name,
      data.display_name,
    );

    if (addressEl && data.address) {
      addressEl.value = data.address;
      dispatchFieldChange(addressEl);
      addressFilled = true;
    }

    return { addressFilled: addressFilled, governorateFilled: governorateFilled };
  }

  function parseJsonResponse(res) {
    return res.text().then(function (text) {
      if (!text) {
        throw new Error(strings.networkError || "Connection issue.");
      }
      try {
        return JSON.parse(text);
      } catch (err) {
        throw new Error(strings.geocodeFailed || "Geocode failed");
      }
    });
  }

  function reverseGeocode(lat, lng, attempt) {
    attempt = attempt || 0;
    var requestId = ++geocodeRequestId;

    if (!cfg.ajaxUrl || !cfg.nonce) {
      return Promise.resolve({ ok: false });
    }

    if (activeController) {
      activeController.abort();
    }
    activeController = typeof AbortController !== "undefined" ? new AbortController() : null;

    var payload = new window.FormData();
    payload.append("action", cfg.action || "consucorner_reverse_geocode");
    payload.append("nonce", cfg.nonce);
    payload.append("lat", String(lat));
    payload.append("lng", String(lng));

    var fetchOptions = {
      method: "POST",
      credentials: "same-origin",
      body: payload,
    };
    if (activeController) {
      fetchOptions.signal = activeController.signal;
    }

    return window
      .fetch(cfg.ajaxUrl, fetchOptions)
      .then(function (res) {
        return parseJsonResponse(res).then(function (body) {
          return { httpStatus: res.status, body: body };
        });
      })
      .then(function (result) {
        if (requestId !== geocodeRequestId) {
          return { ok: false, stale: true };
        }

        var body = result.body;
        if (!body || !body.success || !body.data) {
          var retryable = body && body.data && body.data.retryable;
          var message =
            (body && body.data && body.data.message) ||
            strings.geocodeFailed ||
            "Geocode failed";

          if (retryable && attempt < maxRetries) {
            setStatus(strings.geocodeRetry || message, "loading");
            return new Promise(function (resolve) {
              window.setTimeout(function () {
                resolve(reverseGeocode(lat, lng, attempt + 1));
              }, retryDelay * (attempt + 1));
            });
          }

          throw new Error(message);
        }

        var filled = fillFields(body.data);
        if (filled.addressFilled && filled.governorateFilled) {
          setStatus(strings.success || "Address updated.", "success");
          setCardSuccess(true);
        } else if (filled.governorateFilled) {
          setStatus(strings.success || "Governorate selected.", "success");
          setCardSuccess(true);
          if (filled.addressFilled) {
            setStatus(strings.success || "Address updated.", "success");
          }
        } else if (filled.addressFilled) {
          setStatus(
            strings.partialSuccess ||
              strings.governorateFailed ||
              "Please select governorate.",
            "success",
          );
          setCardSuccess(true);
          var stateEl = $("billing_state");
          if (stateEl) {
            stateEl.classList.add("co-input--attention");
            stateEl.focus();
          }
        } else if (filled.governorateFilled) {
          setStatus(strings.partialSuccess || strings.success || "Area detected.", "success");
          setCardSuccess(true);
        } else {
          setStatus(strings.geocodeFailed || "Could not detect address.", "soft-error");
        }

        return { ok: true, filled: filled };
      })
      .catch(function (err) {
        if (err && err.name === "AbortError") {
          return { ok: false, aborted: true };
        }

        if (attempt < maxRetries) {
          setStatus(strings.networkError || strings.geocodeRetry || "Retrying…", "loading");
          return new Promise(function (resolve) {
            window.setTimeout(function () {
              resolve(reverseGeocode(lat, lng, attempt + 1));
            }, retryDelay * (attempt + 1));
          });
        }

        setStatus(
          (err && err.message) ||
            strings.geocodeFailed ||
            "Could not detect address.",
          "soft-error",
        );
        return { ok: false };
      });
  }

  function debouncedGeocode(lat, lng) {
    window.clearTimeout(geocodeTimer);
    geocodeTimer = window.setTimeout(function () {
      setStatus(strings.locating || "Updating address…", "loading");
      reverseGeocode(lat, lng, 0);
    }, 450);
  }

  function geoErrorMessage(error) {
    if (!error) return strings.unavailable || "Location unavailable.";
    if (error.code === 1) return strings.denied || "Permission denied.";
    if (error.code === 3) return strings.timeout || "Timed out.";
    return strings.unavailable || "Location unavailable.";
  }

  function onDetectClick() {
    if (busy) return;

    try {
      if (!window.isSecureContext) {
        setStatus(strings.httpsRequired || "HTTPS required.", "soft-error");
        var addrHttps = $("billing_address_1");
        if (addrHttps) addrHttps.focus();
        return;
      }

      if (!navigator.geolocation) {
        setStatus(strings.unavailable || "Location unavailable.", "soft-error");
        return;
      }

      busy = true;
      var btn = $("co-use-location");
      if (btn) btn.classList.add("co-use-location-btn--loading");
      setStatus(strings.locating || "Finding your location…", "loading");
      setCardSuccess(false);

      navigator.geolocation.getCurrentPosition(
        function (pos) {
          busy = false;
          if (btn) btn.classList.remove("co-use-location-btn--loading");
          var lat = pos.coords.latitude;
          var lng = pos.coords.longitude;
          showMap();
          ensureMap([lat, lng]);
          moveMarker(lat, lng);
          reverseGeocode(lat, lng, 0);
        },
        function (err) {
          busy = false;
          if (btn) btn.classList.remove("co-use-location-btn--loading");
          setStatus(geoErrorMessage(err), "soft-error");
          var addr = $("billing_address_1");
          if (addr) addr.focus();
        },
        {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 60000,
        },
      );
    } catch (err) {
      busy = false;
      var btnErr = $("co-use-location");
      if (btnErr) btnErr.classList.remove("co-use-location-btn--loading");
      setStatus(strings.unavailable || "Location unavailable.", "soft-error");
    }
  }

  function boot() {
    var btn = $("co-use-location");
    if (!btn) return;
    btn.addEventListener("click", onDetectClick);

    var stateEl = $("billing_state");
    if (stateEl) {
      stateEl.addEventListener("change", function () {
        if (String(stateEl.value || "").trim()) {
          stateEl.classList.remove("co-input--attention");
        }
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
