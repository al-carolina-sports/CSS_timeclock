(function () {
  "use strict";

  document.addEventListener("change", function (event) {
    var el = event.target;
    if (!el || !el.hasAttribute || !el.hasAttribute("data-css-tc-jump")) {
      return;
    }
    if (el.value) {
      window.location.href = el.value;
    }
  });

  document.addEventListener("click", function (event) {
    var button = event.target && event.target.closest ? event.target.closest("[data-add-punch]") : null;
    if (!button) {
      return;
    }
    var day = button.closest("[data-day]");
    if (!day) {
      return;
    }
    var tmpl = day.querySelector("template");
    var lines = day.querySelector("[data-lines]");
    if (!tmpl || !lines) {
      return;
    }
    var html = tmpl.innerHTML.replace(/__INDEX__/g, String(Date.now()));
    var holder = document.createElement("div");
    holder.innerHTML = html.trim();
    if (holder.firstElementChild) {
      lines.appendChild(holder.firstElementChild);
    }
  });
})();
