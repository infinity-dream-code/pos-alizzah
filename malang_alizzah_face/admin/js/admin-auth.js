/**
 * Autentikasi admin (sisi klien).
 *
 * - Pada halaman biasa: bertindak sebagai penjaga (guard) — bila belum login,
 *   alihkan ke login.html. Bila login, sisipkan nama admin + tombol keluar.
 * - Pada login.html (body[data-admin-login]): tangani form login.
 *
 * Memakai sesi cookie same-origin (credentials: same-origin).
 */
(function (global) {
  "use strict";

  function appPrefix() {
    if (typeof document === "undefined" || !document.body) return "";
    var root = document.body.getAttribute("data-app-root");
    if (root == null || String(root).trim() === "") return "";
    return String(root).replace(/\/?$/, "/");
  }

  function apiUrl(name) {
    var n = String(name || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(n)) n += ".php";
    if (global.PresensiApiHttp && global.PresensiApiHttp.buildUrl) {
      return global.PresensiApiHttp.buildUrl("api/" + n);
    }
    return appPrefix() + "api/" + n;
  }

  function pageUrl(name) {
    return appPrefix() + name;
  }

  function requestJson(method, name, body) {
    var init = {
      method: method,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    };
    if (body != null && method !== "GET") {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    return fetch(apiUrl(name), init).then(function (res) {
      return res.text().then(function (text) {
        var parsed = null;
        if (text) {
          try {
            parsed = JSON.parse(text);
          } catch (e) {
            parsed = { ok: false, error: text.slice(0, 200) };
          }
        }
        if (!res.ok || (parsed && parsed.ok === false)) {
          var msg = (parsed && parsed.error) || "HTTP " + res.status + " " + res.statusText;
          var err = new Error(String(msg));
          err.status = res.status;
          throw err;
        }
        return parsed;
      });
    });
  }

  function me() {
    return requestJson("GET", "auth-me.php").then(function (b) {
      return b && b.data;
    });
  }

  function login(username, password) {
    return requestJson("POST", "auth-login.php", {
      username: username,
      password: password,
    }).then(function (b) {
      return b && b.data;
    });
  }

  function logout() {
    return requestJson("POST", "auth-logout.php", {}).catch(function () {
      return null;
    });
  }

  function changePassword(oldPassword, newPassword) {
    return requestJson("POST", "auth-change-password.php", {
      oldPassword: oldPassword,
      newPassword: newPassword,
    });
  }

  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : s;
    return d.innerHTML;
  }

  function redirectToLogin() {
    var here = location.pathname + location.search;
    location.replace(pageUrl("login.html") + "?next=" + encodeURIComponent(here));
  }

  function injectHeaderUser(user) {
    var bar = document.querySelector(".top-bar__inner");
    if (!bar || bar.querySelector(".admin-user")) return;
    var box = document.createElement("div");
    box.className = "admin-user";
    box.innerHTML =
      '<span class="admin-user__name" title="' +
      esc(user.username) +
      '">' +
      esc(user.nama || user.username) +
      "</span>" +
      '<button type="button" class="btn btn--ghost btn--small admin-user__logout" id="admin-logout">Keluar</button>';
    bar.appendChild(box);
    var btn = box.querySelector("#admin-logout");
    if (btn) {
      btn.addEventListener("click", function () {
        btn.disabled = true;
        logout().then(function () {
          location.replace(pageUrl("login.html"));
        });
      });
    }
  }

  function guard() {
    me()
      .then(function (user) {
        if (!user) {
          redirectToLogin();
          return;
        }
        document.documentElement.dataset.adminAuthed = "1";
        injectHeaderUser(user);
      })
      .catch(function () {
        redirectToLogin();
      });
  }

  function getNextTarget() {
    try {
      var params = new URLSearchParams(location.search);
      var next = params.get("next");
      if (next && /^[^/]/.test(next) === false && next.indexOf("//") === -1) {
        return next;
      }
    } catch (e) {}
    return pageUrl("index.html");
  }

  function initLoginPage() {
    var form = document.getElementById("admin-login-form");
    var errEl = document.getElementById("admin-login-error");
    var submitBtn = document.getElementById("admin-login-submit");

    function showError(msg) {
      if (!errEl) return;
      errEl.textContent = msg || "";
      errEl.hidden = !msg;
    }

    // Bila sudah login, langsung masuk.
    me()
      .then(function (user) {
        if (user) location.replace(getNextTarget());
      })
      .catch(function () {});

    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      showError("");
      var fd = new FormData(form);
      var username = String(fd.get("username") || "").trim();
      var password = String(fd.get("password") || "");
      if (!username || !password) {
        showError("Username dan kata sandi wajib diisi.");
        return;
      }
      if (submitBtn) submitBtn.disabled = true;
      login(username, password)
        .then(function () {
          location.replace(getNextTarget());
        })
        .catch(function (err) {
          showError((err && err.message) || "Gagal masuk.");
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  global.AdminAuth = {
    apiUrl: apiUrl,
    me: me,
    login: login,
    logout: logout,
    changePassword: changePassword,
    guard: guard,
  };

  function boot() {
    if (document.body && document.body.hasAttribute("data-admin-login")) {
      initLoginPage();
    } else {
      guard();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(typeof window !== "undefined" ? window : globalThis);
