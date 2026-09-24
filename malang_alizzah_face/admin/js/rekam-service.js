/**
 * Layanan rekam wajah — admin & ortu memakai API yang sama (rekam-data.php).
 */
(function (global) {
  "use strict";

  function getProjectBase() {
    if (typeof location === "undefined") return "";
    var p = location.pathname || "";
    var idx = p.indexOf("/ortu/");
    if (idx >= 0) return p.slice(0, idx);
    if (/\/ortu\/?$/i.test(p)) return p.replace(/\/ortu\/?$/i, "");
    var adminIdx = p.indexOf("/admin/");
    if (adminIdx >= 0) return p.slice(0, adminIdx + "/admin".length);
    if (/\/admin$/i.test(p)) return p;
    if (global.PresensiApiHttp && global.PresensiApiHttp.buildUrl) {
      var test = global.PresensiApiHttp.buildUrl("api/rekam-data.php");
      var cut = test.indexOf("/api/rekam-data.php");
      if (cut > 0) return test.slice(0, cut);
    }
    return "";
  }

  function apiUrl(file) {
    var name = String(file || "rekam-data.php").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    if (global.OrtuApp && global.OrtuApp.adminApi) {
      return global.OrtuApp.adminApi(name);
    }
    if (global.PresensiApiHttp && global.PresensiApiHttp.buildUrl) {
      var c = global.PresensiApiConfig || {};
      var key =
        name.indexOf("hapus") >= 0
          ? "rekamHapusFoto"
          : name.indexOf("rekam-data") >= 0
            ? "rekamData"
            : "rekamSimpan";
      var path = (c.paths && c.paths[key]) || "api/" + name;
      return global.PresensiApiHttp.buildUrl(path);
    }
    var base = getProjectBase();
    return (base ? base + "/" : "") + "api/" + name.replace(/^admin\/api\//, "");
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
            "HTTP " + res.status + " " + res.statusText;
          throw new Error(String(msg));
        }
        return parsed;
      });
    });
  }

  var MAX_FOTO_WAJAH = 3;

  function normalizeFotoSrc(foto) {
    if (foto == null) return "";
    var s = String(foto).trim();
    if (!s) return "";
    if (s.indexOf("data:") === 0) return s;
    if (s.indexOf("/9j/") === 0) return "data:image/jpeg;base64," + s;
    if (s.indexOf("iVBOR") === 0) return "data:image/png;base64," + s;
    if (s.indexOf("UklGR") === 0) return "data:image/webp;base64," + s;
    return s;
  }

  /** Satu data-URL lama, atau JSON array beberapa foto (kacamata / tanpa kacamata). */
  function parseFotoList(foto) {
    if (Array.isArray(foto)) {
      return foto
        .map(function (item) {
          return normalizeFotoSrc(item);
        })
        .filter(function (src) {
          return src && src.length > 80 && src.charAt(0) !== "[";
        });
    }
    if (foto == null) return [];
    var s = String(foto).trim();
    if (!s) return [];
    if (s.charAt(0) === "[") {
      try {
        var arr = JSON.parse(s);
        if (!Array.isArray(arr)) return [];
        return arr
          .map(function (item) {
            return normalizeFotoSrc(item);
          })
          .filter(function (src) {
            return src && src.length > 80 && src.charAt(0) !== "[";
          });
      } catch (e) {
        return [];
      }
    }
    var one = normalizeFotoSrc(s);
    return one && one.length > 80 ? [one] : [];
  }

  function serializeFotoList(list) {
    var out = (list || [])
      .map(function (item) {
        return normalizeFotoSrc(item);
      })
      .filter(function (src) {
        return src && src.length > 80;
      });
    if (!out.length) return "";
    if (out.length === 1) return out[0];
    return JSON.stringify(out.slice(0, MAX_FOTO_WAJAH));
  }

  function appendFoto(existingRaw, dataUrl) {
    var list = parseFotoList(existingRaw);
    var src = normalizeFotoSrc(dataUrl);
    if (!src) return serializeFotoList(list);
    if (list.length >= MAX_FOTO_WAJAH) {
      var err = new Error(
        "Maksimal " + MAX_FOTO_WAJAH + " foto. Hapus salah satu sebelum menambah variasi."
      );
      err.code = "FOTO_MAX";
      throw err;
    }
    list.push(src);
    return serializeFotoList(list);
  }

  function removeFotoAt(existingRaw, index) {
    var list = parseFotoList(existingRaw);
    var i = Number(index);
    if (!isFinite(i) || i < 0 || i >= list.length) return serializeFotoList(list);
    list.splice(i, 1);
    return serializeFotoList(list);
  }

  function normalizeRow(row) {
    if (!row || typeof row !== "object") return row;
    var foto = row.fotoWajah != null ? String(row.fotoWajah) : "";
    row.hasFoto = Boolean(row.hasFoto || parseFotoList(foto).length > 0 || foto.length > 30);
    if (foto.charAt(0) === "[") row.fotoWajah = foto;
    else if (foto.length > 30) row.fotoWajah = normalizeFotoSrc(foto);
    else row.fotoWajah = foto;
    return row;
  }

  function isEnabled() {
    var c = global.PresensiApiConfig || {};
    return c.enabled !== false;
  }

  function listSummary() {
    return requestJson("GET", apiUrl("rekam-data.php")).then(function (body) {
      var rows = (body && body.data) || [];
      return Array.isArray(rows) ? rows.map(normalizeRow) : [];
    });
  }

  function listWithFoto() {
    var base = apiUrl("rekam-data.php");
    var sep = base.indexOf("?") >= 0 ? "&" : "?";
    return requestJson("GET", base + sep + "withFoto=1").then(function (body) {
      var rows = (body && body.data) || [];
      return Array.isArray(rows) ? rows.map(normalizeRow) : [];
    });
  }

  function listFotoIndex() {
    var base = apiUrl("rekam-data.php");
    var sep = base.indexOf("?") >= 0 ? "&" : "?";
    return requestJson("GET", base + sep + "fotoIndex=1").then(function (body) {
      var rows = (body && body.data) || [];
      return Array.isArray(rows) ? rows.map(normalizeRow) : [];
    });
  }

  function listWithFotoByIds(ids) {
    var list = (ids || []).map(String).filter(Boolean);
    if (!list.length) return Promise.resolve([]);
    var chunks = [];
    var i;
    for (i = 0; i < list.length; i += 80) chunks.push(list.slice(i, i + 80));
    var acc = [];
    var chain = Promise.resolve();
    chunks.forEach(function (chunk) {
      chain = chain.then(function () {
        var base = apiUrl("rekam-data.php");
        var sep = base.indexOf("?") >= 0 ? "&" : "?";
        var url = base + sep + "withFoto=1&ids=" + encodeURIComponent(chunk.join(","));
        return requestJson("GET", url).then(function (body) {
          var rows = (body && body.data) || [];
          acc = acc.concat(Array.isArray(rows) ? rows.map(normalizeRow) : []);
        });
      });
    });
    return chain.then(function () {
      return acc;
    });
  }

  function getDetail(id) {
    if (!id) return Promise.reject(new Error("ID siswa wajib"));
    var base = apiUrl("rekam-data.php");
    var sep = base.indexOf("?") >= 0 ? "&" : "?";
    var url = base + sep + "id=" + encodeURIComponent(String(id));
    return requestJson("GET", url).then(function (body) {
      return normalizeRow(body && body.data);
    });
  }

  function save(payload) {
    return requestJson("POST", apiUrl("rekam-data.php"), payload || {}).then(function (body) {
      return normalizeRow(body && body.data);
    });
  }

  function hapusFoto(nis) {
    var n = String(nis || "").trim();
    if (!n) return Promise.reject(new Error("NIS wajib"));
    return requestJson("POST", apiUrl("rekam-hapus-foto.php"), { nis: n }).then(function (body) {
      return normalizeRow(body && body.data);
    });
  }

  global.PresensiRekamService = {
    isEnabled: isEnabled,
    apiUrl: apiUrl,
    listSummary: listSummary,
    listFotoIndex: listFotoIndex,
    listWithFoto: listWithFoto,
    listWithFotoByIds: listWithFotoByIds,
    getDetail: getDetail,
    save: save,
    hapusFoto: hapusFoto,
    normalizeRow: normalizeRow,
    normalizeFotoSrc: normalizeFotoSrc,
    parseFotoList: parseFotoList,
    serializeFotoList: serializeFotoList,
    appendFoto: appendFoto,
    removeFotoAt: removeFotoAt,
    MAX_FOTO_WAJAH: MAX_FOTO_WAJAH,
  };
})(typeof window !== "undefined" ? window : globalThis);
