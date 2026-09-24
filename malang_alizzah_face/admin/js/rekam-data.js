(function () {
  var SISWA_KEY = "presensi_data_siswa";

  var state = {
    page: 1,
    pageSize: 8,
    /** Daftar ringkas dari DB (rekam-data.php) */
    rows: [],
    dbLoaded: false,
    /** Detail lengkap + foto per id */
    detailById: {},
    detailLoading: {},
    detailErrors: {},
  };

  function isRemote() {
    return window.PresensiRekamService && window.PresensiRekamService.isEnabled();
  }

  function loadRowsLocal(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function mapCacheToSummaryRows(rows) {
    return (rows || []).map(function (r) {
      var foto = r && r.fotoWajah ? String(r.fotoWajah) : "";
      var hasFoto = Boolean((r && (r.hasFoto || r._hasFoto)) || foto.length > 30);
      return {
        id: (r && r.id) || "",
        nis: (r && r.nis) || "",
        nisn: (r && r.nisn) || "",
        nama: (r && r.nama) || "",
        kelasId: (r && (r.kelasId || r.kelas_id)) || "",
        jenisKelamin: (r && (r.jenisKelamin || r.jenis_kelamin)) || "L",
        aktif: !r || r.aktif !== false,
        rfidUid: (r && (r.rfidUid || r.rfid_uid)) || "",
        kodeSuara: (r && (r.kodeSuara || r.kode_suara)) || "",
        fotoWajah: "",
        hasFoto: hasFoto,
        _hasFoto: hasFoto,
        code02: (r && (r.code02 || r.CODE02)) || "",
        desc02: (r && (r.desc02 || r.DESC02)) || "",
        desc03: (r && (r.desc03 || r.DESC03)) || "",
        desc04: (r && (r.desc04 || r.DESC04)) || "",
      };
    });
  }

  function hydrateFromCache() {
    var cached = loadRowsLocal(SISWA_KEY);
    if (!cached.length) return false;
    state.rows = mapCacheToSummaryRows(cached);
    state.dbLoaded = true;
    return true;
  }

  function getRows() {
    if (isRemote() && state.dbLoaded) return state.rows;
    if (isRemote() && !state.dbLoaded) return [];
    return loadRowsLocal(SISWA_KEY);
  }

  function findStudent(rows, id) {
    if (!id) return null;
    return rows.find(function (r) {
      return r.id === id;
    });
  }

  function patchRowInState(saved) {
    if (!saved || !saved.id) return;
    state.detailById[saved.id] = saved;
    var ix = state.rows.findIndex(function (r) {
      return r.id === saved.id;
    });
    var summary = {
      id: saved.id,
      nis: saved.nis,
      nisn: saved.nisn || "",
      nama: saved.nama,
      kelasId: saved.kelasId || "",
      jenisKelamin: saved.jenisKelamin || "L",
      aktif: saved.aktif !== false,
      rfidUid: saved.rfidUid || "",
      kodeSuara: saved.kodeSuara || "",
      fotoWajah: "",
      hasFoto: Boolean(saved.hasFoto || (saved.fotoWajah && String(saved.fotoWajah).length > 30)),
    };
    if (ix >= 0) state.rows[ix] = summary;
    else state.rows.push(summary);
  }

  function loadListFromDb(root) {
    if (!isRemote()) {
      state.dbLoaded = true;
      return Promise.resolve(getRows());
    }
    return window.PresensiRekamService.listSummary()
      .then(function (rows) {
        state.rows = rows || [];
        state.dbLoaded = true;
        return state.rows;
      })
      .catch(function (e) {
        state.dbLoaded = true;
        state.rows = [];
        console.warn("[rekam-data] muat DB:", e && e.message ? e.message : e);
        if (root) {
          var el = document.getElementById("rekam-sync-status");
          if (el) {
            el.hidden = false;
            el.textContent = "Gagal memuat dari database: " + (e.message || e);
          }
        }
        return [];
      });
  }

  function fetchDetailFromDb(siswa, root) {
    if (!siswa || !siswa.id || !isRemote()) return;
    if (state.detailById[siswa.id] && state.detailById[siswa.id].fotoWajah) return;
    if (state.detailLoading[siswa.id]) return;
    delete state.detailErrors[siswa.id];
    state.detailLoading[siswa.id] = true;
    if (root) render(root);
    window.PresensiRekamService.getDetail(siswa.id)
      .then(function (detail) {
        if (detail) patchRowInState(detail);
      })
      .catch(function (e) {
        state.detailErrors[siswa.id] = (e && e.message) || String(e);
        console.warn("[rekam-data] muat foto:", e && e.message ? e.message : e);
      })
      .finally(function () {
        delete state.detailLoading[siswa.id];
        if (root && root.dataset.selectedId === siswa.id) render(root);
      });
  }

  function saveFotoToDb(siswaId, dataUrl, root) {
    var curSummary = findStudent(getRows(), siswaId) || { id: siswaId };
    var existingRaw = getStoredFotoRaw(curSummary);
    var packed;
    try {
      packed =
        window.PresensiRekamService && window.PresensiRekamService.appendFoto
          ? window.PresensiRekamService.appendFoto(existingRaw, dataUrl)
          : dataUrl;
    } catch (e) {
      return Promise.reject(e);
    }
    if (isRemote()) {
      return window.PresensiRekamService.save({
        siswaId: siswaId,
        fotoWajah: packed,
      })
        .then(function (saved) {
          patchRowInState(saved);
          return saved;
        })
        .then(function () {
          if (root) render(root);
        });
    }
    var list = loadRowsLocal(SISWA_KEY);
    var cur = findStudent(list, siswaId);
    if (!cur) return Promise.resolve();
    cur.fotoWajah = packed;
    cur.hasFoto = true;
    localStorage.setItem(SISWA_KEY, JSON.stringify(list));
    if (root) render(root);
    return Promise.resolve();
  }

  function hapusSatuFotoFromDb(siswa, index, root) {
    var packed =
      window.PresensiRekamService && window.PresensiRekamService.removeFotoAt
        ? window.PresensiRekamService.removeFotoAt(getStoredFotoRaw(siswa), index)
        : "";
    if (!packed) {
      return hapusFotoFromDb(siswa.nis, siswa.id, root);
    }
    if (isRemote()) {
      return window.PresensiRekamService.save({
        siswaId: siswa.id,
        fotoWajah: packed,
      }).then(function (saved) {
        patchRowInState(saved);
        if (root) render(root);
      });
    }
    var list = loadRowsLocal(SISWA_KEY);
    var cur = findStudent(list, siswa.id);
    if (!cur) return Promise.resolve();
    cur.fotoWajah = packed;
    cur.hasFoto = true;
    localStorage.setItem(SISWA_KEY, JSON.stringify(list));
    if (root) render(root);
    return Promise.resolve();
  }

  function hapusFotoFromDb(nis, siswaId, root) {
    if (isRemote()) {
      return window.PresensiRekamService.hapusFoto(nis).then(function (saved) {
        if (saved) {
          saved.fotoWajah = "";
          saved.hasFoto = false;
          patchRowInState(saved);
        } else {
          delete state.detailById[siswaId];
          var ix = state.rows.findIndex(function (r) {
            return r.id === siswaId;
          });
          if (ix >= 0) {
            state.rows[ix].hasFoto = false;
            state.rows[ix].fotoWajah = "";
          }
        }
        if (root) render(root);
      });
    }
    var list = loadRowsLocal(SISWA_KEY);
    var cur = findStudent(list, siswaId);
    if (cur) {
      cur.fotoWajah = "";
      cur.hasFoto = false;
      localStorage.setItem(SISWA_KEY, JSON.stringify(list));
    }
    if (root) render(root);
    return Promise.resolve();
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function normSpeech(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, " ");
  }

  function fotoSizeApprox(dataUrl) {
    if (!dataUrl || typeof dataUrl !== "string") return 0;
    var base64 = dataUrl.split(",")[1];
    if (!base64) return dataUrl.length;
    return Math.floor((base64.length * 3) / 4);
  }

  var MAX_FOTO_BYTES = 750 * 1024;
  var rekamWebcamStream = null;

  function stopRekamWebcam() {
    if (rekamWebcamStream) {
      rekamWebcamStream.getTracks().forEach(function (t) {
        t.stop();
      });
      rekamWebcamStream = null;
    }
  }

  function siswaHasFoto(s) {
    if (!s) return false;
    if (s.hasFoto || s._hasFoto) return true;
    var detail = state.detailById[s.id];
    if (detail && detail.fotoWajah && String(detail.fotoWajah).length > 30) return true;
    return Boolean(s.fotoWajah && String(s.fotoWajah).length > 30);
  }

  function normalizeFotoSrc(foto) {
    if (window.PresensiRekamService && window.PresensiRekamService.normalizeFotoSrc) {
      return window.PresensiRekamService.normalizeFotoSrc(foto);
    }
    if (foto == null) return "";
    var s = String(foto).trim();
    if (!s) return "";
    if (s.indexOf("data:") === 0) return s;
    if (s.indexOf("/9j/") === 0) return "data:image/jpeg;base64," + s;
    if (s.indexOf("iVBOR") === 0) return "data:image/png;base64," + s;
    return s;
  }

  function parseFotoList(foto) {
    if (window.PresensiRekamService && window.PresensiRekamService.parseFotoList) {
      return window.PresensiRekamService.parseFotoList(foto);
    }
    var s = String(foto || "").trim();
    if (!s) return [];
    if (s.charAt(0) === "[") {
      try {
        var arr = JSON.parse(s);
        return Array.isArray(arr)
          ? arr.map(normalizeFotoSrc).filter(function (src) {
              return src && src.length > 80;
            })
          : [];
      } catch (e) {
        return [];
      }
    }
    var one = normalizeFotoSrc(s);
    return one && one.length > 80 ? [one] : [];
  }

  function maxFotoWajah() {
    if (window.PresensiRekamService && window.PresensiRekamService.MAX_FOTO_WAJAH) {
      return window.PresensiRekamService.MAX_FOTO_WAJAH;
    }
    return 3;
  }

  function getStoredFotoRaw(s) {
    if (!s) return "";
    var detail = state.detailById[s.id];
    if (detail && detail.fotoWajah) return String(detail.fotoWajah);
    if (s.fotoWajah) return String(s.fotoWajah);
    return "";
  }

  function getFotoList(s) {
    return parseFotoList(getStoredFotoRaw(s));
  }

  function getFotoDisplay(s) {
    var list = getFotoList(s);
    return list.length ? list[0] : "";
  }

  function presensiFlags(s) {
    var fotoOk = siswaHasFoto(s);
    return { fotoOk: fotoOk, lengkap: fotoOk, ada: fotoOk };
  }

  function presensiMiniHtml(s) {
    var f = presensiFlags(s);
    var tip = "Foto wajah: " + (f.fotoOk ? "sudah" : "belum");
    return (
      '<span class="siswa-presensi-mini" title="' +
      escapeHtml(tip) +
      '"><span class="' +
      (f.fotoOk ? "siswa-dot siswa-dot--ok" : "siswa-dot") +
      '" title="Foto wajah">F</span></span>'
    );
  }

  function buildRekamLegendHtml(compact) {
    var cls = compact ? "rekam-legend rekam-legend--compact" : "rekam-legend";
    return (
      '<div class="' +
      cls +
      '" role="note"><p class="rekam-legend__title">Keterangan status rekam</p>' +
      '<ul class="rekam-legend__list">' +
      "<li><span class=\"siswa-dot siswa-dot--ok\">F</span> <strong>Foto</strong> — sudah di database</li>" +
      "<li><span class=\"siswa-dot\">F</span> abu-abu = belum ada foto</li>" +
      "</ul></div>"
    );
  }

  function buildSummaryCard(label, hint, value, filterKey, active, extraClass) {
    return (
      '<button type="button" class="siswa-summary__card rekam-summary__card' +
      (extraClass ? " " + extraClass : "") +
      (active ? " rekam-summary__card--active" : "") +
      '" data-rekam-filter="' +
      escapeHtml(filterKey) +
      '"><span class="siswa-summary__label">' +
      escapeHtml(label) +
      '</span><span class="rekam-summary__hint">' +
      escapeHtml(hint) +
      '</span><span class="siswa-summary__value">' +
      escapeHtml(String(value)) +
      "</span></button>"
    );
  }

  function buildRekamFilterChips(filterKey) {
    function chip(key, label) {
      return (
        '<button type="button" class="rekam-filter-chip' +
        (filterKey === key ? " rekam-filter-chip--on" : "") +
        '" data-rekam-filter="' +
        escapeHtml(key) +
        '">' +
        escapeHtml(label) +
        "</button>"
      );
    }
    return (
      '<div class="rekam-filter-chips"><p class="rekam-filter-chips__title">Filter cepat</p>' +
      '<div class="rekam-filter-chip-group"><span class="rekam-filter-chip-group__label">Foto wajah</span>' +
      chip("foto-ada", "Sudah ada") +
      chip("foto-belum", "Belum ada") +
      '</div><div class="rekam-filter-chip-group rekam-filter-chip-group--reset">' +
      chip("all", "Tampilkan semua") +
      "</div></div>"
    );
  }

  function rekamStats(rows) {
    var lengkap = 0;
    var belum = 0;
    (rows || []).forEach(function (s) {
      if (presensiFlags(s).lengkap) lengkap++;
      else belum++;
    });
    return { total: rows.length, lengkap: lengkap, belum: belum };
  }

  function filterSiswa(rows, q) {
    var t = normSpeech(q).replace(/\s/g, "");
    if (!t) return rows.slice();
    return rows.filter(function (s) {
      var nama = normSpeech(s.nama).replace(/\s/g, "");
      var nis = String(s.nis || "")
        .trim()
        .toLowerCase();
      return nama.indexOf(t) !== -1 || nis.indexOf(t) !== -1;
    });
  }

  var REKAM_FILTER_OPTIONS = [
    { value: "all", label: "Semua siswa (tanpa filter)" },
    { value: "lengkap", label: "Sudah ada foto wajah" },
    { value: "belum", label: "Belum ada foto wajah" },
    { value: "foto-ada", label: "Sudah ada foto" },
    { value: "foto-belum", label: "Belum ada foto" },
  ];

  function getRekamFilterKey(root) {
    return (root && root.dataset.rekamFilterValue) || "all";
  }

  function setRekamFilterKey(root, key) {
    if (root) root.dataset.rekamFilterValue = key || "all";
  }

  function rekamFilterLabel(key) {
    var opt = REKAM_FILTER_OPTIONS.find(function (o) {
      return o.value === key;
    });
    return opt ? opt.label : "Semua siswa";
  }

  function matchesRekamFilter(s, key) {
    var f = presensiFlags(s);
    switch (key) {
      case "lengkap":
      case "foto-ada":
        return f.fotoOk;
      case "belum":
      case "foto-belum":
        return !f.fotoOk;
      default:
        return true;
    }
  }

  /** Filter kartu Sudah/Belum foto: lintas semua kelas (tanpa gate unit/kelas). */
  function isPhotoScopeFilter(key) {
    return (
      key === "lengkap" ||
      key === "foto-ada" ||
      key === "belum" ||
      key === "foto-belum"
    );
  }

  function filterByRekam(rows, key) {
    if (!key || key === "all") return rows.slice();
    return rows.filter(function (s) {
      return matchesRekamFilter(s, key);
    });
  }

  function getFilteredList(rows, root) {
    var searchQ = root.dataset.searchQ || "";
    var filterKey = getRekamFilterKey(root);
    var photoScope = isPhotoScopeFilter(filterKey);
    var dimRows = rows;

    if (window.PresensiSiswaFilter) {
      var sel = window.PresensiSiswaFilter.read(root);
      var gateOpen =
        !window.PresensiSiswaFilter.needsGate(rows.length) ||
        window.PresensiSiswaFilter.isOpen(sel, searchQ) ||
        photoScope;
      if (!gateOpen) return [];
      // Sudah/Belum foto: tampilkan semua kelas; filter unit/kelas diabaikan
      dimRows = photoScope
        ? rows.slice()
        : window.PresensiSiswaFilter.apply(rows, sel);
    }

    return filterByRekam(filterSiswa(dimRows, searchQ), filterKey);
  }

  function buildRekamFilterSelect(current) {
    return (
      '<div class="rekam-filter-select-wrap"><label class="settings-form__label" for="rekam-filter">Tampilkan siswa</label>' +
      '<select id="rekam-filter" class="settings-form__input rekam-filter-select">' +
      REKAM_FILTER_OPTIONS.map(function (o) {
        return (
          '<option value="' +
          escapeHtml(o.value) +
          '"' +
          (current === o.value ? " selected" : "") +
          ">" +
          escapeHtml(o.label) +
          "</option>"
        );
      }).join("") +
      "</select></div>"
    );
  }

  function bindSearchFocus(root) {
    if (root.dataset.searchFocus !== "1") return;
    root.dataset.searchFocus = "";
    var inp = root.querySelector("#rekam-search");
    if (!inp) return;
    var len = inp.value.length;
    try {
      inp.focus();
      inp.setSelectionRange(len, len);
    } catch (e) {}
  }

  function render(root) {
    stopRekamWebcam();
    var rows = getRows();

    if (isRemote() && !state.dbLoaded) {
      root.innerHTML =
        '<header class="page-head"><h1>Rekam data</h1><p>Memuat daftar siswa…</p></header>';
      return;
    }

    if (!rows.length) {
      root.innerHTML =
        '<header class="page-head"><h1>Rekam data</h1>' +
        (isRemote()
          ? "<p>Belum ada siswa. Sinkron di menu Data siswa.</p>"
          : "<p>Belum ada data siswa.</p>") +
        '</header><p class="rekam-data__empty-actions"><a class="btn btn--primary" href="data-siswa.html">Buka Data siswa</a></p>';
      return;
    }

    var params = new URLSearchParams(window.location.search);
    var fromUrl = params.get("siswa");
    if (fromUrl) {
      if (findStudent(rows, fromUrl)) {
        root.dataset.selectedId = fromUrl;
        root.dataset.rekamSyncPageTo = fromUrl;
        window.history.replaceState({}, "", "rekam-data.html?siswa=" + encodeURIComponent(fromUrl));
      } else {
        root.dataset.selectedId = "";
        window.history.replaceState({}, "", "rekam-data.html");
      }
    }

    var searchQ = root.dataset.searchQ || "";
    var filterKey = getRekamFilterKey(root);
    if (!root.dataset.rekamFilterValue) setRekamFilterKey(root, "all");
    var hasFilterMod = Boolean(window.PresensiSiswaFilter);
    var dimSel = hasFilterMod
      ? window.PresensiSiswaFilter.read(root)
      : { unit: "", kelas: "", tahun: "" };
    var photoScope = isPhotoScopeFilter(filterKey);
    var gateOpen =
      !hasFilterMod ||
      !window.PresensiSiswaFilter.needsGate(rows.length) ||
      window.PresensiSiswaFilter.isOpen(dimSel, searchQ) ||
      photoScope;
    var dimRows =
      hasFilterMod && !photoScope
        ? window.PresensiSiswaFilter.apply(rows, dimSel)
        : rows;
    var filtered = gateOpen ? filterByRekam(filterSiswa(dimRows, searchQ), filterKey) : [];
    var total = filtered.length;
    var totalAll = rows.length;
    var stats = rekamStats(rows);

    var syncPageTo = root.dataset.rekamSyncPageTo;
    if (syncPageTo) {
      var syncIx = filtered.findIndex(function (r) {
        return r.id === syncPageTo;
      });
      if (syncIx >= 0) state.page = Math.floor(syncIx / state.pageSize) + 1;
      delete root.dataset.rekamSyncPageTo;
    }

    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;
    var start = (state.page - 1) * state.pageSize;
    var pageRows = filtered.slice(start, start + state.pageSize);

    var selectedId = root.dataset.selectedId || "";
    var sel = findStudent(rows, selectedId);
    if (!sel) {
      selectedId = "";
      root.dataset.selectedId = "";
    }

    var tableRows;
    if (!gateOpen) {
      tableRows = window.PresensiSiswaFilter.gateRowHtml(4);
    } else if (!filtered.length) {
      tableRows =
        '<tr><td colspan="4" class="data-table__empty">Tidak ada siswa yang cocok dengan filter/pencarian.</td></tr>';
    } else {
      tableRows = pageRows
        .map(function (s) {
          var isSel = selectedId && s.id === selectedId;
          return (
            "<tr class='" +
            (isSel ? "rekam-table__row--selected " : "") +
            "rekam-table__row'><td>" +
            escapeHtml(s.nis) +
            "</td><td>" +
            escapeHtml(s.nama) +
            "</td><td class='siswa-rekam-cell'>" +
            presensiMiniHtml(s) +
            "</td><td class='data-table__actions'>" +
            '<button type="button" class="btn btn--primary btn--small" data-rekam-pilih="' +
            escapeHtml(s.id) +
            '">' +
            (isSel ? "Dipilih" : "Pilih") +
            "</button></td></tr>"
          );
        })
        .join("");
    }

    var pageInfo =
      !gateOpen
        ? "Pilih filter dulu (total " + totalAll + ")"
        : total === 0
        ? "0 data"
        : start +
          1 +
          "–" +
          Math.min(start + state.pageSize, total) +
          " dari " +
          total +
          (photoScope
            ? " · " + rekamFilterLabel(filterKey) + " (semua kelas)"
            : searchQ
            ? " (filter, total " + totalAll + ")"
            : "");

    var mappingSection = "";
    if (sel) {
      var fotoList = getFotoList(sel);
      var fotoVal = fotoList[0] || "";
      var hasFoto = siswaHasFoto(sel);
      var fotoLoading = Boolean(state.detailLoading[sel.id]);
      var fotoError = state.detailErrors[sel.id] || "";
      var previewHtml;
      if (fotoError) {
        previewHtml =
          '<div class="rekam-foto__placeholder rekam-foto__placeholder--error">Gagal memuat foto: ' +
          escapeHtml(fotoError) +
          '</div><button type="button" class="btn btn--ghost btn--small" id="rekam-foto-retry">Coba lagi</button>';
      } else if (fotoList.length) {
        previewHtml =
          '<div class="rekam-foto__gallery">' +
          fotoList
            .map(function (_src, i) {
              return (
                '<figure class="rekam-foto__thumb">' +
                '<img class="rekam-foto__preview" data-rekam-foto-idx="' +
                i +
                '" alt="Foto ' +
                (i + 1) +
                '" />' +
                '<figcaption>Foto ' +
                (i + 1) +
                " / " +
                maxFotoWajah() +
                "</figcaption>" +
                '<button type="button" class="btn btn--ghost btn--small" data-rekam-foto-del="' +
                i +
                '">Hapus</button>' +
                "</figure>"
              );
            })
            .join("") +
          "</div>";
      } else if (hasFoto && fotoLoading) {
        previewHtml = '<div class="rekam-foto__placeholder">Memuat foto dari database…</div>';
      } else if (hasFoto) {
        previewHtml = '<div class="rekam-foto__placeholder">Memuat pratinjau dari database…</div>';
      } else {
        previewHtml = '<div class="rekam-foto__placeholder">Belum ada foto tersimpan</div>';
      }

      mappingSection =
        '<div class="module-shell rekam-mapping-intro"><h3 class="rekam-mapping-intro__title">' +
        escapeHtml(sel.nama) +
        "</h3>" +
        "<p class='rekam-mapping-intro__meta'>NIS " +
        escapeHtml(sel.nis) +
        " · " +
        presensiMiniHtml(sel) +
        "</p></div>" +
        '<div class="rekam-data__grid"><section class="module-shell rekam-card">' +
        '<h3 class="rekam-card__title">Foto wajah</h3>' +
        "<p class=\"rekam-card__desc\">Rekam 1–" +
        maxFotoWajah() +
        " foto. Jika siswa memakai kacamata, rekam juga dengan kacamata (dan tanpa kacamata).</p>" +
        '<div class="rekam-webcam"><div class="rekam-webcam__wrap">' +
        '<video id="rekam-webcam-video" class="rekam-webcam__video" playsinline muted></video>' +
        '<div id="rekam-webcam-overlay" class="rekam-webcam__overlay">Tekan «Mulai kamera»</div></div>' +
        '<canvas id="rekam-webcam-canvas" hidden></canvas>' +
        '<div class="rekam-webcam__actions">' +
        '<button type="button" class="btn btn--primary" id="rekam-webcam-start">Mulai kamera</button>' +
        '<button type="button" class="btn btn--ghost" id="rekam-webcam-stop" hidden>Stop</button>' +
        '<button type="button" class="btn btn--ghost" id="rekam-webcam-flip" hidden title="Tukar kamera depan / belakang">Ganti kamera</button>' +
        '<button type="button" class="btn btn--primary" id="rekam-webcam-capture" hidden>Rekam foto</button>' +
        "</div>" +
        '<p id="rekam-webcam-status" class="rekam-webcam__status" aria-live="polite"></p></div>' +
        '<div class="rekam-foto">' +
        previewHtml +
        (hasFoto
          ? '<button type="button" class="btn btn--ghost btn--small" id="rekam-foto-hapus">Hapus semua foto</button>'
          : "") +
        "</div></section></div>";
    } else {
      mappingSection =
        '<div class="module-shell rekam-mapping-placeholder"><p>Pilih siswa di daftar untuk merekam foto.</p></div>';
    }

    root.innerHTML =
      '<header class="page-head"><h1>Rekam data</h1>' +
      "<p>Pilih siswa, lalu rekam 1–3 foto wajah (dengan dan tanpa kacamata jika perlu).</p>" +
      '<p id="rekam-sync-status" hidden class="settings-form__hint"></p></header>' +
      '<div class="siswa-summary rekam-summary">' +
      buildSummaryCard("Semua", "", stats.total, "all", filterKey === "all", "") +
      buildSummaryCard("Sudah foto", "", stats.lengkap, "lengkap", filterKey === "lengkap", "siswa-summary__card--ok") +
      buildSummaryCard("Belum foto", "", stats.belum, "belum", filterKey === "belum", "siswa-summary__card--muted") +
      "</div>" +
      '<div class="module-shell rekam-toolbar">' +
      '<div class="rekam-toolbar__row">' +
      '<label class="rekam-toolbar__per" for="rekam-page-size">Baris' +
      '<select id="rekam-page-size" class="settings-form__input siswa-toolbar__select">' +
      [5, 8, 10, 20]
        .map(function (n) {
          return (
            "<option value='" +
            n +
            "'" +
            (state.pageSize === n ? " selected" : "") +
            ">" +
            n +
            "</option>"
          );
        })
        .join("") +
      "</select></label>" +
      '<div class="rekam-toolbar__actions">' +
      (isRemote()
        ? '<button type="button" class="btn btn--ghost btn--small" id="rekam-refresh-db">Muat ulang</button>'
        : "") +
      "</div></div></div>" +
      (hasFilterMod ? window.PresensiSiswaFilter.controlsHtml(rows, dimSel, "rekam") : "") +
      '<div class="module-shell rekam-search-panel">' +
      '<label class="settings-form__label" for="rekam-search">Cari</label>' +
      '<input type="search" id="rekam-search" class="settings-form__input rekam-search-input" placeholder="Nama atau NIS…" value="' +
      escapeHtml(searchQ) +
      '" /><p class="rekam-search-meta">' +
      pageInfo +
      "</p></div>" +
      '<div class="module-shell data-settings__table-shell">' +
      '<div class="table-wrap"><table class="data-table rekam-search-table">' +
      "<thead><tr><th>NIS</th><th>Nama</th><th>Status</th><th class='data-table__actions'>Aksi</th></tr></thead><tbody>" +
      tableRows +
      "</tbody></table></div>" +
      '<div class="siswa-pagination"><span class="siswa-pagination__info">' +
      escapeHtml(pageInfo) +
      '</span><div class="siswa-pagination__nav">' +
      '<button type="button" class="btn btn--ghost btn--small" id="rekam-prev"' +
      (state.page <= 1 ? " disabled" : "") +
      ">Sebelumnya</button><span class=\"siswa-pagination__page\">" +
      state.page +
      " / " +
      totalPages +
      '</span><button type="button" class="btn btn--ghost btn--small" id="rekam-next"' +
      (state.page >= totalPages ? " disabled" : "") +
      ">Berikutnya</button></div></div></div>" +
      mappingSection;

    bindSearchFocus(root);

    root.querySelectorAll("[data-rekam-pilih]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-rekam-pilih");
        root.dataset.selectedId = id;
        root.dataset.rekamSyncPageTo = id;
        window.history.replaceState({}, "", "rekam-data.html?siswa=" + encodeURIComponent(id));
        render(root);
      });
    });

    var refreshBtn = root.querySelector("#rekam-refresh-db");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", function () {
        state.detailById = {};
        state.detailErrors = {};
        loadListFromDb(root).then(function () {
          render(root);
        });
      });
    }

    if (!sel) return;

    if (siswaHasFoto(sel) && !getFotoDisplay(sel) && !state.detailErrors[sel.id]) {
      fetchDetailFromDb(sel, root);
    }

    var previewImgs = root.querySelectorAll("[data-rekam-foto-idx]");
    if (previewImgs.length && sel) {
      var srcs = getFotoList(sel);
      previewImgs.forEach(function (img) {
        var ix = Number(img.getAttribute("data-rekam-foto-idx"));
        if (srcs[ix]) img.src = srcs[ix];
      });
    }

    var retryFoto = root.querySelector("#rekam-foto-retry");
    if (retryFoto) {
      retryFoto.addEventListener("click", function () {
        delete state.detailErrors[sel.id];
        delete state.detailById[sel.id];
        fetchDetailFromDb(sel, root);
      });
    }

    var selectedIdCapture = sel.id;

    function setWebcamStatus(msg) {
      var el = root.querySelector("#rekam-webcam-status");
      if (el) el.textContent = msg || "";
    }

    var wVid = root.querySelector("#rekam-webcam-video");
    var wOver = root.querySelector("#rekam-webcam-overlay");
    var wStart = root.querySelector("#rekam-webcam-start");
    var wStop = root.querySelector("#rekam-webcam-stop");
    var wFlip = root.querySelector("#rekam-webcam-flip");
    var wCap = root.querySelector("#rekam-webcam-capture");
    var wCan = root.querySelector("#rekam-webcam-canvas");

    function syncRekamFlipLabel() {
      if (!wFlip || typeof window.presensiGetFacingMode !== "function") return;
      var back = window.presensiGetFacingMode() === "environment";
      wFlip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
      wFlip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
    }

    if (wStart && wVid && typeof window.presensiOpenWebcam === "function") {
      wStart.addEventListener("click", function () {
        window.presensiOpenWebcam(wVid).then(function (stream) {
          rekamWebcamStream = stream;
          if (wOver) wOver.hidden = true;
          wStart.hidden = true;
          if (wStop) wStop.hidden = false;
          if (wFlip) wFlip.hidden = false;
          if (wCap) wCap.hidden = false;
          syncRekamFlipLabel();
        setWebcamStatus("Kamera aktif — tekan Rekam foto. Untuk kacamata, rekam dua kali (dengan dan tanpa).");
        }).catch(function (err) {
          setWebcamStatus("Kamera gagal: " + (err.message || err));
        });
      });
    } else if (wStart) {
      wStart.disabled = true;
    }

    if (wFlip && wVid && typeof window.presensiSwitchCamera === "function") {
      wFlip.addEventListener("click", function () {
        if (!rekamWebcamStream) return;
        setWebcamStatus("Mengganti kamera…");
        window
          .presensiSwitchCamera(wVid)
          .then(function (stream) {
            rekamWebcamStream = stream;
            syncRekamFlipLabel();
            var label =
              typeof window.presensiFacingLabel === "function"
                ? window.presensiFacingLabel()
                : "kamera";
            setWebcamStatus("Kamera aktif (" + label + ") — tekan Rekam foto.");
          })
          .catch(function (err) {
            setWebcamStatus("Gagal ganti kamera: " + (err.message || err));
          });
      });
    }

    if (wStop && wVid && wStart && wCap) {
      wStop.addEventListener("click", function () {
        stopRekamWebcam();
        if (typeof window.presensiStopWebcam === "function") {
          window.presensiStopWebcam(wVid);
        } else {
          wVid.srcObject = null;
        }
        if (wOver) wOver.hidden = false;
        wStart.hidden = false;
        wStop.hidden = true;
        if (wFlip) wFlip.hidden = true;
        wCap.hidden = true;
      });
    }

    if (wCap && wVid && wCan) {
      wCap.addEventListener("click", function () {
        var vw = wVid.videoWidth;
        var vh = wVid.videoHeight;
        if (!vw || !vh) {
          alert("Video belum siap.");
          return;
        }
        wCan.width = vw;
        wCan.height = vh;
        wCan.getContext("2d").drawImage(wVid, 0, 0, vw, vh);
        var dataUrl = wCan.toDataURL("image/jpeg", 0.88);
        if (fotoSizeApprox(dataUrl) > MAX_FOTO_BYTES) {
          alert("Foto terlalu besar (maks. ±750 KB).");
          return;
        }
        wCap.disabled = true;
        var doSave = function () {
          return saveFotoToDb(selectedIdCapture, dataUrl, root);
        };
        var selNow = findStudent(getRows(), selectedIdCapture);
        var detailNow = state.detailById[selectedIdCapture];
        var needDetail =
          isRemote() &&
          selNow &&
          siswaHasFoto(selNow) &&
          !(detailNow && detailNow.fotoWajah);
        var savePromise = needDetail
          ? window.PresensiRekamService.getDetail(selectedIdCapture).then(function (d) {
              if (d) patchRowInState(d);
              return doSave();
            })
          : doSave();
        savePromise
          .then(function () {
            stopRekamWebcam();
            wVid.srcObject = null;
            var n = getFotoList(findStudent(getRows(), selectedIdCapture) || sel).length;
            var maxN = maxFotoWajah();
            if (n >= maxN) {
              alert("Foto tersimpan (" + n + "/" + maxN + "). Maksimal tercapai.");
            } else {
              alert(
                "Foto tersimpan (" +
                  n +
                  "/" +
                  maxN +
                  "). Jika siswa memakai kacamata, rekam lagi dengan kacamata."
              );
            }
          })
          .catch(function (e) {
            alert("Gagal simpan: " + (e.message || e));
          })
          .finally(function () {
            wCap.disabled = false;
          });
      });
    }

    var hapusFoto = root.querySelector("#rekam-foto-hapus");
    if (hapusFoto) {
      hapusFoto.addEventListener("click", function () {
        if (!confirm("Hapus semua foto wajah " + sel.nama + " dari database?")) return;
        hapusFotoFromDb(sel.nis, sel.id, root).catch(function (e) {
          alert("Gagal hapus: " + (e.message || e));
        });
      });
    }

    root.querySelectorAll("[data-rekam-foto-del]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var ix = Number(btn.getAttribute("data-rekam-foto-del"));
        if (!confirm("Hapus foto " + (ix + 1) + " untuk " + sel.nama + "?")) return;
        hapusSatuFotoFromDb(sel, ix, root).catch(function (e) {
          alert("Gagal hapus: " + (e.message || e));
        });
      });
    });
  }

  function bindRekamEvents(root) {
    if (root.dataset.rekamEventsBound) return;
    root.dataset.rekamEventsBound = "1";

    root.addEventListener("input", function (e) {
      if (e.target.id === "rekam-search") {
        root.dataset.searchQ = e.target.value;
        root.dataset.searchFocus = "1";
        state.page = 1;
        render(root);
      }
    });

    root.addEventListener("change", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleChange(root, e.target, "rekam")) {
        state.page = 1;
        render(root);
        return;
      }
      if (e.target.id === "rekam-page-size") {
        state.pageSize = parseInt(e.target.value, 10) || 8;
        state.page = 1;
        render(root);
      }
      if (e.target.id === "rekam-filter") {
        setRekamFilterKey(root, e.target.value || "all");
        state.page = 1;
        render(root);
      }
    });

    root.addEventListener("click", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleClick(root, e.target, "rekam")) {
        state.page = 1;
        render(root);
        return;
      }
      var filterBtn = e.target.closest("button[data-rekam-filter]");
      if (filterBtn && root.contains(filterBtn)) {
        setRekamFilterKey(root, filterBtn.getAttribute("data-rekam-filter") || "all");
        state.page = 1;
        render(root);
        return;
      }
      if (e.target.closest("#rekam-prev")) {
        state.page = Math.max(1, state.page - 1);
        render(root);
        return;
      }
      if (e.target.closest("#rekam-next")) {
        var filtered = getFilteredList(getRows(), root);
        state.page = Math.min(Math.max(1, Math.ceil(filtered.length / state.pageSize)), state.page + 1);
        render(root);
      }
    });
  }

  function init() {
    var root = document.getElementById("rekam-root");
    if (!root) return;
    if (!root.dataset.rekamBound) {
      root.dataset.rekamBound = "1";
      bindRekamEvents(root);
      window.addEventListener("beforeunload", stopRekamWebcam);
    }

    var hasCache = hydrateFromCache();
    if (hasCache) {
      render(root);
    } else if (window.PresensiLoading) {
      window.PresensiLoading.show(root, "Memuat dari database…");
    }

    loadListFromDb(root)
      .then(function () {
        render(root);
      })
      .finally(function () {
        if (window.PresensiLoading) window.PresensiLoading.hide(root);
      });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
