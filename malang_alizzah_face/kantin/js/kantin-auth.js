/**
 * Auth kantin — session PHP via /kantin/api/*
 */
(function (global) {
  "use strict";

  function apiUrl(file) {
    var name = String(file || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    var path = location.pathname || "";
    var base = path.replace(/\/[^/]*$/, "/");
    if (/\/kantin\/?$/i.test(path)) base = path.replace(/\/?$/, "/");
    // login.html & index.html di /kantin/
    if (path.indexOf("/kantin/") >= 0) {
      var root = path.slice(0, path.indexOf("/kantin/") + "/kantin/".length);
      return root + "api/" + name;
    }
    return base + "api/" + name;
  }

  function requestJson(method, url, body) {
    var init = {
      method: method,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    };
    if (body != null && method !== "GET" && method !== "HEAD") {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    return fetch(url, init).then(function (res) {
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
          var msg =
            (parsed && parsed.error) ||
            (parsed && parsed.message) ||
            "HTTP " + res.status;
          var err = new Error(String(msg));
          err.status = res.status;
          err.body = parsed;
          throw err;
        }
        return parsed;
      });
    });
  }

  function me() {
    return requestJson("GET", apiUrl("me.php")).then(function (body) {
      return body && body.data ? body.data : null;
    });
  }

  function login(username, password) {
    return requestJson("POST", apiUrl("login.php"), {
      username: username,
      password: password,
    }).then(function (body) {
      return body && body.data ? body.data : null;
    });
  }

  function logout() {
    return requestJson("POST", apiUrl("logout.php"), {});
  }

  function requireSession() {
    return me().catch(function () {
      location.replace("login.html");
      return null;
    });
  }

  function initLoginPage() {
    var form = document.getElementById("kantin-login-form");
    var errEl = document.getElementById("kantin-login-error");
    var btn = document.getElementById("kantin-login-submit");
    if (!form) return;

    // Sudah login? → index
    me()
      .then(function () {
        location.replace("index.html");
      })
      .catch(function () {});

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (errEl) {
        errEl.hidden = true;
        errEl.textContent = "";
      }
      var user = (document.getElementById("kantin-username") || {}).value || "";
      var pass = (document.getElementById("kantin-password") || {}).value || "";
      user = String(user).trim();
      if (!user || !pass) {
        if (errEl) {
          errEl.hidden = false;
          errEl.textContent = "Isi username dan kata sandi.";
        }
        return;
      }
      if (btn) {
        btn.disabled = true;
        btn.textContent = "Masuk…";
      }
      login(user, pass)
        .then(function () {
          location.replace("index.html");
        })
        .catch(function (err) {
          if (errEl) {
            errEl.hidden = false;
            errEl.textContent = (err && err.message) || "Login gagal";
          }
        })
        .finally(function () {
          if (btn) {
            btn.disabled = false;
            btn.textContent = "Masuk";
          }
        });
    });
  }

  global.KantinAuth = {
    apiUrl: apiUrl,
    requestJson: requestJson,
    me: me,
    login: login,
    logout: logout,
    requireSession: requireSession,
    initLoginPage: initLoginPage,
  };
})(typeof window !== "undefined" ? window : globalThis);
