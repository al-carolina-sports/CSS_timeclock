/**
 * Mark Pro-only rows and leave bookmarked upsell tabs.
 * CSS hides the stable classes; this covers browsers without :has()
 * and sends Get Pro / Advanced (Pro-only) tabs back to real Lite screens.
 */
(function () {
  "use strict";

  var buttons = document.querySelectorAll("a.aio-pro-button");
  Array.prototype.forEach.call(buttons, function (el) {
    var row = el.closest ? el.closest("tr") : null;
    if (row) {
      row.classList.add("css-tc-hide-pro-row");
      return;
    }
    if (el.parentNode && el.parentNode.classList) {
      el.parentNode.classList.add("css-tc-hide-pro-row");
    }
  });

  var params;
  try {
    params = new URLSearchParams(window.location.search);
  } catch (err) {
    return;
  }

  var page = params.get("page") || "";
  var tab = params.get("tab") || "";
  var next = "";
  if (page === "aio-tc-lite" && tab === "get_pro") {
    next = "general_settings";
  } else if (page === "aio-reports-sub" && tab === "custom_reports") {
    next = "simple_report";
  }
  if (!next) {
    return;
  }
  params.set("tab", next);
  window.location.replace(window.location.pathname + "?" + params.toString());
})();
