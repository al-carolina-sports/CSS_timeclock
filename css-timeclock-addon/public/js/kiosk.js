(function () {
  "use strict";

  var cfg = window.cssTcKiosk || {};
  var strings = cfg.strings || {};

  function $(root, sel) {
    return root.querySelector(sel);
  }

  function show(el, on) {
    if (!el) {
      return;
    }
    el.hidden = !on;
  }

  function text(el, value) {
    if (el) {
      el.textContent = value || "";
    }
  }

  function post(action, payload) {
    var body = new URLSearchParams();
    body.set("action", action);
    body.set("nonce", cfg.nonce || "");
    Object.keys(payload || {}).forEach(function (key) {
      if (payload[key] !== undefined && payload[key] !== null) {
        body.set(key, String(payload[key]));
      }
    });

    return fetch(cfg.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!json || !json.success) {
          var message =
            (json && json.data && json.data.message) || strings.network || "Request failed.";
          var err = new Error(message);
          err.status = res.status;
          throw err;
        }
        return json.data || {};
      });
    });
  }

  function liveClock(el) {
    if (!el) {
      return;
    }
    function tick() {
      el.textContent = new Date().toLocaleString();
    }
    tick();
    window.setInterval(tick, 1000);
  }

  function renderDots(el, length) {
    if (!el) {
      return;
    }
    el.textContent = length ? new Array(length + 1).join("•") : "○";
  }

  function Kiosk(root) {
    this.root = root;
    this.mode = root.getAttribute("data-kiosk") || "pin";
    this.pin = "";
    this.userId = 0;
    this.busy = false;
    this.resetTimer = null;
    this.selectedName = "";

    this.stage = $(root, '[data-role="stage"]');
    this.screens = {
      list: $(root, '[data-screen="list"]'),
      pin: $(root, '[data-screen="pin"]'),
      action: $(root, '[data-screen="action"]'),
      success: $(root, '[data-screen="success"]'),
    };

    liveClock($(root, '[data-role="live-clock"]'));
    this.bind();
    this.reset();
    if (this.mode === "name") {
      this.loadEmployees();
    }
  }

  Kiosk.prototype.bind = function () {
    var self = this;

    this.root.addEventListener("click", function (event) {
      var button = event.target.closest("button");
      if (!button || !self.root.contains(button) || self.busy) {
        return;
      }

      var digit = button.getAttribute("data-digit");
      var action = button.getAttribute("data-action");
      var employee = button.getAttribute("data-employee");

      if (digit) {
        self.addDigit(digit);
      } else if (action === "clear") {
        self.pin = "";
        self.updateDots();
      } else if (action === "back") {
        self.pin = self.pin.slice(0, -1);
        self.updateDots();
      } else if (action === "submit-pin") {
        self.resolvePin();
      } else if (action === "cancel") {
        self.reset();
      } else if (action === "clock_in" || action === "clock_out") {
        self.punch(action);
      } else if (employee) {
        self.chooseEmployee(parseInt(employee, 10), button.getAttribute("data-name") || "");
      }
    });

    var search = $(this.root, '[data-role="search"]');
    if (search) {
      search.addEventListener("input", function () {
        self.filterNames(search.value);
      });
    }
  };

  Kiosk.prototype.showScreen = function (name) {
    Object.keys(this.screens).forEach(function (key) {
      show(this.screens[key], key === name);
    }, this);
  };

  Kiosk.prototype.updateDots = function () {
    renderDots($(this.root, '[data-role="pin-dots"]'), this.pin.length);
  };

  Kiosk.prototype.addDigit = function (digit) {
    var max = cfg.pinMax || 8;
    if (this.pin.length >= max) {
      return;
    }
    this.pin += digit;
    this.updateDots();
    text($(this.root, '[data-role="error"]'), "");
    show($(this.root, '[data-role="error"]'), false);
    if (this.pin.length >= (cfg.pinMax || 8)) {
      this.resolvePin();
    }
  };

  Kiosk.prototype.resolvePin = function () {
    var self = this;
    var min = cfg.pinMin || 4;
    if (this.busy) {
      return;
    }
    if (this.pin.length < min) {
      text($(this.root, '[data-role="error"]'), strings.enterPin || "Enter your PIN");
      show($(this.root, '[data-role="error"]'), true);
      return;
    }
    this.busy = true;
    var payload = { pin: this.pin, kiosk: this.mode };
    if (this.userId) {
      payload.user_id = this.userId;
    }

    post("css_tc_resolve_pin", payload)
      .then(function (data) {
        self.userId = data.user_id;
        self.busy = false;
        self.showAction(data);
      })
      .catch(function (err) {
        self.busy = false;
        self.pin = "";
        self.updateDots();
        text($(self.root, '[data-role="error"]'), err.message || strings.badPin);
        show($(self.root, '[data-role="error"]'), true);
      });
  };

  Kiosk.prototype.showAction = function (data) {
    this.showScreen("action");
    var name = data.name || this.selectedName;
    text($(this.root, '[data-role="hello"]'), (strings.hello || "Hello") + ", " + name);
    if (data.is_clocked_in && data.clock_in_time) {
      text(
        $(this.root, '[data-role="status"]'),
        (strings.workingSince || "Clocked in since") + " " + data.clock_in_time
      );    } else {
      text($(this.root, '[data-role="status"]'), "");
    }
    show($(this.root, '[data-role="action-error"]'), false);

    var inBtn = $(this.root, '[data-action="clock_in"]');
    var outBtn = $(this.root, '[data-action="clock_out"]');
    if (inBtn) {
      inBtn.disabled = !!data.is_clocked_in;
    }
    if (outBtn) {
      outBtn.disabled = !data.is_clocked_in;
    }
  };

  Kiosk.prototype.punch = function (clockAction) {
    var self = this;
    if (this.busy) {
      return;
    }
    this.busy = true;
    var payload = {
      pin: this.pin,
      kiosk: this.mode,
      clock_action: clockAction,
    };
    if (this.userId) {
      payload.user_id = this.userId;
    }

    post("css_tc_punch", payload)
      .then(function (data) {
        self.busy = false;
        self.showSuccess(data);
      })
      .catch(function (err) {
        self.busy = false;
        text($(self.root, '[data-role="action-error"]'), err.message || strings.network);
        show($(self.root, '[data-role="action-error"]'), true);
      });
  };

  Kiosk.prototype.showSuccess = function (data) {
    this.showScreen("success");
    var title =
      data.action === "clock_out" ? strings.successOut || "You are clocked out." : strings.successIn || "You are clocked in.";
    text($(this.root, '[data-role="success-title"]'), title);
    var detail = data.name || "";
    if (data.time_total) {
      detail += (detail ? " · " : "") + (strings.shiftTotal || "Shift time") + " " + data.time_total;
    } else if (data.clock_in_time) {
      detail += (detail ? " · " : "") + (strings.workingSince || "Clocked in since") + " " + data.clock_in_time;
    }
    text($(this.root, '[data-role="success-detail"]'), detail);
    this.scheduleReset();
  };

  Kiosk.prototype.scheduleReset = function () {
    var self = this;
    window.clearTimeout(this.resetTimer);
    this.resetTimer = window.setTimeout(function () {
      self.reset();
    }, cfg.idleResetMs || 8000);
  };

  Kiosk.prototype.reset = function () {
    window.clearTimeout(this.resetTimer);
    this.pin = "";
    this.userId = 0;
    this.busy = false;
    this.selectedName = "";
    this.updateDots();
    show($(this.root, '[data-role="error"]'), false);
    show($(this.root, '[data-role="action-error"]'), false);
    var search = $(this.root, '[data-role="search"]');
    if (search) {
      search.value = "";
      this.filterNames("");
    }
    if (this.mode === "name") {
      this.showScreen("list");
    } else {
      this.showScreen("pin");
    }
  };

  Kiosk.prototype.chooseEmployee = function (userId, name) {
    this.userId = userId;
    this.selectedName = name;
    this.pin = "";
    this.updateDots();
    text($(this.root, '[data-role="selected-name"]'), name);
    text($(this.root, '[data-role="pin-prompt"]'), strings.confirmPin || "Confirm with your PIN");
    show($(this.root, '[data-role="error"]'), false);
    this.showScreen("pin");
  };

  Kiosk.prototype.loadEmployees = function () {
    var self = this;
    var list = $(this.root, '[data-role="names"]');
    var error = $(this.root, '[data-role="list-error"]');
    if (!list) {
      return;
    }

    post("css_tc_employees", { kiosk: "name" })
      .then(function (data) {
        var employees = data.employees || [];
        list.innerHTML = "";
        if (!employees.length) {
          text(error, strings.noEmployees || "");
          show(error, true);
          return;
        }
        show(error, false);
        employees.forEach(function (emp) {
          var button = document.createElement("button");
          button.type = "button";
          button.className = "css-tc-name";
          button.setAttribute("data-employee", String(emp.id));
          button.setAttribute("data-name", emp.greeting || emp.name || "");
          var strong = document.createElement("strong");
          strong.textContent = emp.name || "";
          button.appendChild(strong);
          if (emp.department) {
            var span = document.createElement("span");
            span.textContent = emp.department;
            button.appendChild(span);
          }
          list.appendChild(button);
        });
      })
      .catch(function (err) {
        text(error, err.message || strings.network);
        show(error, true);
      });
  };

  Kiosk.prototype.filterNames = function (query) {
    var q = (query || "").toLowerCase();
    this.root.querySelectorAll(".css-tc-name").forEach(function (button) {
      var label = (button.textContent || "").toLowerCase();
      button.hidden = q !== "" && label.indexOf(q) === -1;
    });
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".css-tc-kiosk[data-enabled='1']").forEach(function (root) {
      new Kiosk(root);
    });
  });
})();
