(function () {
  "use strict";

  var cfg = window.cssTcAdmin || {};

  function notice(message, isError) {
    var box = document.querySelector(".css-tc-notice");
    if (!box) {
      window.alert(message);
      return;
    }
    box.hidden = false;
    box.textContent = message;
    box.classList.toggle("is-error", !!isError);
    box.classList.toggle("is-success", !isError);
  }

  function post(action, payload) {
    var body = new URLSearchParams();
    body.set("action", action);
    body.set("nonce", cfg.nonce || "");
    Object.keys(payload || {}).forEach(function (key) {
      body.set(key, String(payload[key]));
    });

    return fetch(cfg.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!json || !json.success) {
          throw new Error((json && json.data && json.data.message) || (cfg.strings && cfg.strings.error) || "Error");
        }
        return json.data || {};
      });
    });
  }

  function bindSettings() {
    var form = document.querySelector(".css-tc-settings-form");
    if (!form) {
      return;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var data = {
        pin_kiosk_enabled: form.pin_kiosk_enabled && form.pin_kiosk_enabled.checked ? 1 : 0,
        name_kiosk_enabled: form.name_kiosk_enabled && form.name_kiosk_enabled.checked ? 1 : 0,
        pin_min_length: form.pin_min_length.value,
        pin_max_length: form.pin_max_length.value,
        rate_limit_max: form.rate_limit_max.value,
        rate_limit_window: form.rate_limit_window.value,
        idle_reset_ms: form.idle_reset_ms.value,
      };
      post("css_tc_save_settings", data)
        .then(function (result) {
          notice(result.message || (cfg.strings && cfg.strings.saved));
        })
        .catch(function (err) {
          notice(err.message, true);
        });
    });

    var createBtn = document.querySelector(".css-tc-create-pages");
    if (createBtn) {
      createBtn.addEventListener("click", function () {
        post("css_tc_create_pages", {})
          .then(function (result) {
            notice(result.message || (cfg.strings && cfg.strings.saved));
          })
          .catch(function (err) {
            notice(err.message, true);
          });
      });
    }
  }

  function updateStatus(row, hasPin) {
    var cell = row.querySelector(".css-tc-pin-status");
    var clearBtn = row.querySelector(".css-tc-clear-pin");
    if (cell) {
      cell.innerHTML = hasPin
        ? '<span class="css-tc-pill css-tc-pill-set">' + ((cfg.strings && cfg.strings.set) || "Set") + "</span>"
        : '<span class="css-tc-pill css-tc-pill-unset">' + ((cfg.strings && cfg.strings.notSet) || "Not set") + "</span>";
    }
    if (clearBtn) {
      clearBtn.disabled = !hasPin;
    }
  }

  function bindPins() {
    document.querySelectorAll(".css-tc-pin-form").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        var input = form.querySelector(".css-tc-pin-input");
        var pin = input ? input.value : "";
        post("css_tc_save_pin", { user_id: form.getAttribute("data-user-id"), pin: pin })
          .then(function (result) {
            if (input) {
              input.value = "";
            }
            updateStatus(form.closest("tr"), true);
            notice(result.message || (cfg.strings && cfg.strings.saved));
          })
          .catch(function (err) {
            notice(err.message, true);
          });
      });

      var clearBtn = form.querySelector(".css-tc-clear-pin");
      if (clearBtn) {
        clearBtn.addEventListener("click", function () {
          if (!window.confirm((cfg.strings && cfg.strings.confirmClear) || "Clear this PIN?")) {
            return;
          }
          post("css_tc_clear_pin", { user_id: form.getAttribute("data-user-id") })
            .then(function (result) {
              updateStatus(form.closest("tr"), false);
              notice(result.message || (cfg.strings && cfg.strings.saved));
            })
            .catch(function (err) {
              notice(err.message, true);
            });
        });
      }
    });

    var filter = document.getElementById("css-tc-pin-filter");
    if (filter) {
      filter.addEventListener("input", function () {
        var q = filter.value.toLowerCase();
        document.querySelectorAll(".css-tc-pin-row").forEach(function (row) {
          var name = row.getAttribute("data-name") || "";
          row.hidden = q !== "" && name.indexOf(q) === -1;
        });
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindSettings();
    bindPins();
  });
})();
