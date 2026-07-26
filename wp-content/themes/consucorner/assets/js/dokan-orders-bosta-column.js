/**
 * Add a Bosta status column to Dokan's React vendor Orders DataViews table.
 *
 * Filter: dokan_orders_data_view_dataviews_fields / _view
 * (namespace "dokan-orders-data-view" → snake_case hook prefix)
 */
(function (wp) {
  "use strict";

  if (!wp || !wp.hooks || !wp.element) {
    return;
  }

  var el = wp.element.createElement;
  var FIELD_ID = "bosta_status";
  var HOOK_NS = "consucorner/dokan-orders-bosta";

  function renderBostaCell(props) {
    var item = (props && props.item) || {};
    var status = item.bosta_status || "";
    var tracking = item.bosta_tracking_number || "";
    var fulfillment = item.cc_fulfillment_label || "";

    if (!status && !tracking && !fulfillment) {
      return el("span", { className: "text-gray-400" }, "—");
    }

    var children = [];
    if (status) {
      children.push(
        el(
          "div",
          { key: "bosta", className: "font-medium text-sm" },
          status
        )
      );
    }
    if (tracking) {
      children.push(
        el(
          "div",
          { key: "track", className: "text-xs text-gray-500 mt-0.5" },
          tracking
        )
      );
    }
    if (!status && fulfillment) {
      children.push(
        el(
          "div",
          { key: "ff", className: "text-xs text-gray-500" },
          fulfillment
        )
      );
    }

    return el("div", { className: "cc-dokan-bosta-cell" }, children);
  }

  wp.hooks.addFilter(
    "dokan_orders_data_view_dataviews_fields",
    HOOK_NS,
    function (fields) {
      var list = Array.isArray(fields) ? fields.slice() : [];
      if (list.some(function (f) { return f && f.id === FIELD_ID; })) {
        return list;
      }

      var column = {
        id: FIELD_ID,
        label: (wp.i18n && wp.i18n.__) ? wp.i18n.__("Bosta status", "consucorner") : "Bosta status",
        enableSorting: false,
        enableHiding: true,
        render: renderBostaCell,
      };

      var statusIdx = -1;
      for (var i = 0; i < list.length; i++) {
        if (list[i] && list[i].id === "status") {
          statusIdx = i;
          break;
        }
      }

      if (statusIdx >= 0) {
        list.splice(statusIdx + 1, 0, column);
      } else {
        list.push(column);
      }

      return list;
    }
  );

  wp.hooks.addFilter(
    "dokan_orders_data_view_dataviews_view",
    HOOK_NS + "-view",
    function (view) {
      if (!view || typeof view !== "object") {
        return view;
      }

      var fields = Array.isArray(view.fields) ? view.fields.slice() : [];
      if (fields.indexOf(FIELD_ID) === -1) {
        var statusIdx = fields.indexOf("status");
        if (statusIdx >= 0) {
          fields.splice(statusIdx + 1, 0, FIELD_ID);
        } else {
          fields.push(FIELD_ID);
        }
      }

      var next = Object.assign({}, view, { fields: fields });
      var layout = view.layout && typeof view.layout === "object" ? Object.assign({}, view.layout) : {};
      var styles = layout.styles && typeof layout.styles === "object" ? Object.assign({}, layout.styles) : {};
      if (!styles[FIELD_ID]) {
        styles[FIELD_ID] = { width: "15%" };
        layout.styles = styles;
        next.layout = layout;
      }

      return next;
    }
  );
})(window.wp);
