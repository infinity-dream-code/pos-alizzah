(function () {
  var SISWA_KEY = "presensi_data_siswa";
  var FACE_LOG_KEY = "presensi_face_log";
  var MODUL_CONTEXT_KEY = "presensi_modul_context";
  var DEFAULT_KEGIATAN = "Presensi harian";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  /**
   * 1 foto/orang: gallery diaugmentasi (cermin, blur kacamata, crop jarak).
   * Ambang 0.38 menoleransi kacamata/jarak; margin 0.10 menolak wajah asing.
   */
  var MATCH_THRESHOLD = 0.38;
  var MIN_MATCH_MARGIN = 0.1;
  var STABLE_HITS = 2;
  var MIN_FACE_HEIGHT_RATIO = 0.09;
  var LIVE_MIN_CONFIDENCE = 0.42;
  var REF_MIN_CONFIDENCE = 0.35;
  var COOLDOWN_MS = 5000;
  var DETECT_MS = 350;
  var MAX_LOG_ROWS = 30;

  var root;
  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastAnnounceById = {};
  var pendingMatchId = "";
  var pendingHits = 0;
  /** Log hari ini dari DB (presensi_log) bila API aktif */
  var faceLogsFromDb = [];

  function $(id) {
    return root ? root.querySelector("#" + id) : null;
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function loadRows(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function loadFaceLogs() {
    return loadRows(FACE_LOG_KEY).filter(function (r) {
      return r && r.id && (r.waktu || r.waktuMasuk);
    });
  }

  function useDbPresensiLog() {
    return Boolean(
      window.PresensiApiHttp &&
        window.PresensiApiHttp.isEnabled() &&
        window.PresensiApiExtras &&
        window.PresensiApiExtras.listPresensiLog
    );
  }

  function todayDateKey() {
    return localDateKeyFromIso(new Date().toISOString());
  }

  function normalizeDbLogRow(row) {
    if (!row || typeof row !== "object") return row;
    var out = Object.assign({}, row);
    if (out.waktuMasuk && typeof out.waktuMasuk === "string" && out.waktuMasuk.indexOf("T") === -1) {
      out.waktuMasuk = out.waktuMasuk.replace(" ", "T");
    }
    if (out.waktuKeluar && typeof out.waktuKeluar === "string" && out.waktuKeluar.indexOf("T") === -1) {
      out.waktuKeluar = out.waktuKeluar.replace(" ", "T");
    }
    if (!out.waktu && out.waktuMasuk) out.waktu = out.waktuMasuk;
    return out;
  }

  function getLogsForTable() {
    if (useDbPresensiLog()) {
      return faceLogsFromDb;
    }
    return loadFaceLogs();
  }

  function fetchFaceLogsFromDb() {
    if (!useDbPresensiLog()) {
      return Promise.resolve(getLogsForTable());
    }
    var keg = getKegiatanPresensi();
    return window.PresensiApiExtras
      .listPresensiLog({
        limit: 200,
        date: todayDateKey(),
        kegiatan: String(keg || "").trim() || DEFAULT_KEGIATAN,
      })
      .then(function (rows) {
        faceLogsFromDb = (rows || []).map(normalizeDbLogRow).filter(function (r) {
          return r && r.id && (r.waktu || r.waktuMasuk);
        });
        return faceLogsFromDb;
      })
      .catch(function (e) {
        console.warn("[face] muat log presensi DB:", e && e.message ? e.message : e);
        return faceLogsFromDb;
      });
  }

  function saveFaceLogs(rows) {
    try {
      localStorage.setItem(FACE_LOG_KEY, JSON.stringify(rows || []));
    } catch (e) {}
  }

  function pad2(n) {
    return n < 10 ? "0" + n : String(n);
  }

  /** Hari & tanggal untuk judul tabel (bukan kolom). */
  function formatJudulHariTanggal(d) {
    d = d || new Date();
    try {
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    } catch (e) {
      return "";
    }
  }

  function formatLogJamDetik(iso) {
    if (!iso) return "—";
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return "—";
      return pad2(d.getHours()) + ":" + pad2(d.getMinutes()) + ":" + pad2(d.getSeconds());
    } catch (e) {
      return "—";
    }
  }

  function isoMasukUtama(row) {
    return row.waktuMasuk != null ? row.waktuMasuk : row.waktu;
  }

  function buildLogMasukCell(row) {
    return '<td class="presensi-log__jam">' + escapeHtml(formatLogJamDetik(isoMasukUtama(row))) + "</td>";
  }

  function buildLogKeluarCell(row) {
    var v = row.waktuKeluar;
    return '<td class="presensi-log__jam">' + escapeHtml(v ? formatLogJamDetik(v) : "—") + "</td>";
  }

  function getKegiatanPresensi() {
    try {
      var raw = localStorage.getItem(MODUL_CONTEXT_KEY);
      if (!raw) return DEFAULT_KEGIATAN;
      var data = JSON.parse(raw);
      if (!data || typeof data !== "object") return DEFAULT_KEGIATAN;
      var c = data.catatan;
      if (c != null && String(c).trim() !== "") return String(c).trim();
      return DEFAULT_KEGIATAN;
    } catch (e) {
      return DEFAULT_KEGIATAN;
    }
  }

  function rowKegiatanNorm(row) {
    var k = row && row.kegiatan;
    if (k != null && String(k).trim() !== "") return String(k).trim();
    return DEFAULT_KEGIATAN;
  }

  function localDateKeyFromIso(iso) {
    if (!iso) return "";
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return "";
      return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
    } catch (e) {
      return "";
    }
  }

  /** Satu baris per siswa per hari kalender (lokal) per kegiatan; deteksi berikutnya memperbarui keluar saja. */
  function findPresensiRowToday(logs, siswaId, kegiatanNorm) {
    var todayKey = localDateKeyFromIso(new Date().toISOString());
    for (var i = 0; i < logs.length; i++) {
      var r = logs[i];
      if (!r) continue;
      if (String(r.siswaId || "") !== String(siswaId)) continue;
      if (rowKegiatanNorm(r) !== kegiatanNorm) continue;
      if (localDateKeyFromIso(r.waktuMasuk || r.waktu) === todayKey) return i;
    }
    return -1;
  }

  function updateFaceLogHeading() {
    var h = $("face-log-heading");
    if (!h) return;
    h.textContent =
      "Data penyimpanan — " + getKegiatanPresensi() + " · " + formatJudulHariTanggal(new Date());
  }

  function renderFaceLogTable() {
    var body = $("face-log-body");
    var meta = $("face-log-meta");
    if (!body) return;
    var logs = getLogsForTable();
    if (!logs.length) {
      body.innerHTML =
        '<tr><td class="data-table__empty" colspan="3">Belum ada presensi wajah yang tersimpan.</td></tr>';
      if (meta) meta.textContent = "Belum ada rekam presensi wajah.";
      updateFaceLogHeading();
      return;
    }
    if (meta) {
      meta.textContent = "Total rekam tersimpan: " + logs.length + " presensi.";
    }
    body.innerHTML = logs
      .map(function (row) {
        var masukCell = buildLogMasukCell(row);
        var keluarCell = buildLogKeluarCell(row);
        var namaNis =
          '<td class="presensi-log__stack">' +
          '<span class="presensi-log__stack-main">' +
          escapeHtml(row.nama || "Tidak dikenal") +
          "</span>" +
          '<span class="presensi-log__stack-sub">' +
          escapeHtml(row.nis || "—") +
          "</span></td>";
        return (
          "<tr>" +
          namaNis +
          masukCell +
          keluarCell +
          "</tr>"
        );
      })
      .join("");
    updateFaceLogHeading();
  }

  function newFaceLogId() {
    return "face-log-" + Date.now() + "-" + Math.random().toString(16).slice(2, 8);
  }

  /**
   * Satu baris per siswa per hari kalender (lokal) per kegiatan; deteksi berikutnya hanya memperbarui waktu keluar.
   */
  function appendFaceLog(siswa, metode) {
    if (!siswa || !siswa.id) return;
    var sid = siswa.id;
    var logs = getLogsForTable().slice();
    var now = new Date().toISOString();
    var keg = getKegiatanPresensi();
    var kegNorm = String(keg || "").trim() || DEFAULT_KEGIATAN;
    var base = {
      metode: metode || "face recognition",
      nama: siswa.nama || "Tidak dikenal",
      nis: siswa.nis || "—",
      siswaId: sid,
      kegiatan: keg,
    };

    var idx = findPresensiRowToday(logs, sid, kegNorm);
    if (idx !== -1) {
      var row = logs.splice(idx, 1)[0];
      row.waktuKeluar = now;
      row.waktu = row.waktuMasuk || row.waktu;
      row.metode = base.metode;
      row.nama = base.nama;
      row.nis = base.nis;
      row.kegiatan = getKegiatanPresensi();
      logs.unshift(row);
    } else {
      var rid = newFaceLogId();
      logs.unshift({
        id: rid,
        waktu: now,
        waktuMasuk: now,
        waktuKeluar: null,
        metode: base.metode,
        nama: base.nama,
        nis: base.nis,
        siswaId: base.siswaId,
        kegiatan: base.kegiatan,
      });
      if (typeof window.presensiShowMasukModal === "function") {
        window.presensiShowMasukModal({
          nama: base.nama,
          nis: base.nis,
          durationMs: 4000,
        });
      }
    }

    if (logs.length > MAX_LOG_ROWS) logs = logs.slice(0, MAX_LOG_ROWS);

    var head = logs[0];
    if (useDbPresensiLog()) {
      faceLogsFromDb = logs;
      if (head && window.PresensiApiExtras && window.PresensiApiExtras.savePresensiLog) {
        window.PresensiApiExtras
          .savePresensiLog(head)
          .then(function () {
            return fetchFaceLogsFromDb();
          })
          .then(function () {
            renderFaceLogTable();
          })
          .catch(function (e) {
            console.warn("[face] log presensi DB:", e && e.message ? e.message : e);
            renderFaceLogTable();
          });
        return;
      }
    } else {
      saveFaceLogs(logs);
    }
    renderFaceLogTable();
  }

  function loadSiswaAktifDenganFoto() {
    return loadRows(SISWA_KEY).filter(function (s) {
      return s.aktif && parseFotoList(s.fotoWajah).length > 0;
    });
  }

  function useRekamService() {
    return Boolean(
      window.PresensiRekamService &&
        window.PresensiRekamService.isEnabled() &&
        window.PresensiRekamService.listWithFoto
    );
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
    if (s.indexOf("UklGR") === 0) return "data:image/webp;base64," + s;
    return s;
  }

  function parseFotoList(foto) {
    if (window.PresensiRekamService && window.PresensiRekamService.parseFotoList) {
      return window.PresensiRekamService.parseFotoList(foto);
    }
    if (Array.isArray(foto)) {
      return foto.map(normalizeFotoSrc).filter(function (src) {
        return src && src.length > 80 && src.charAt(0) !== "[";
      });
    }
    var s = foto == null ? "" : String(foto).trim();
    if (!s) return [];
    if (s.charAt(0) === "[") {
      try {
        var arr = JSON.parse(s);
        if (!Array.isArray(arr)) return [];
        return arr.map(normalizeFotoSrc).filter(function (src) {
          return src && src.length > 80 && src.charAt(0) !== "[";
        });
      } catch (e) {
        return [];
      }
    }
    var one = normalizeFotoSrc(s);
    return one && one.length > 80 ? [one] : [];
  }

  /**
   * Daftar siswa aktif berfoto. Sumber: DB (rekam-data.php?withFoto=1) bila API aktif,
   * jatuh ke cache localStorage bila offline. Cache localStorage sengaja membuang base64
   * untuk hemat kuota, jadi mode remote WAJIB ambil foto langsung dari server.
   * fotoWajah boleh 1 data-URL atau JSON array (maks. 3 foto).
   */
  function loadReferensiSiswa() {
    if (useRekamService()) {
      return window.PresensiRekamService.listWithFoto()
        .then(function (rows) {
          return (rows || []).filter(function (s) {
            return parseFotoList(s.fotoWajah).length > 0;
          });
        })
        .catch(function (e) {
          console.warn("[face] muat foto referensi dari DB:", e && e.message ? e.message : e);
          return loadSiswaAktifDenganFoto();
        });
    }
    return Promise.resolve(loadSiswaAktifDenganFoto());
  }

  function setStatus(html, kind) {
    var el = $("face-status");
    if (!el) return;
    el.className = "face-status" + (kind ? " face-status--" + kind : "");
    el.innerHTML = html;
  }

  function speakHasil(siswa) {
    if (!window.speechSynthesis) return;
    try {
      window.speechSynthesis.cancel();
    } catch (e) {}
    var text = "Presensi wajah berhasil. " + (siswa.nama || "Siswa") + ".";
    var u = new SpeechSynthesisUtterance(text);
    u.lang = "id-ID";
    u.rate = 0.95;
    var voices = window.speechSynthesis.getVoices();
    if (voices && voices.length) {
      for (var i = 0; i < voices.length; i++) {
        if (voices[i].lang && voices[i].lang.toLowerCase().indexOf("id") === 0) {
          u.voice = voices[i];
          break;
        }
      }
    }
    window.speechSynthesis.speak(u);
  }

  /**
   * Memetakan kotak deteksi (koordinat video intrinsik) ke piksel relatif terhadap elemen video,
   * dengan koreksi object-fit: cover dan cermin horizontal (video memakai scaleX(-1)).
   */
  function mapFaceBoxToOverlayPixels(vid, box) {
    if (!vid || !box) return null;
    var vw = vid.videoWidth;
    var vh = vid.videoHeight;
    if (!vw || !vh) return null;
    var cw = vid.clientWidth;
    var ch = vid.clientHeight;
    if (!cw || !ch) return null;
    var scale = Math.max(cw / vw, ch / vh);
    var dw = vw * scale;
    var dh = vh * scale;
    var offX = (cw - dw) / 2;
    var offY = (ch - dh) / 2;
    var left = offX + box.x * scale;
    var top = offY + box.y * scale;
    var w = box.width * scale;
    var h = box.height * scale;
    left = cw - left - w;
    return { left: left, top: top, width: w, height: h };
  }

  function hideFaceTarget() {
    var boxEl = $("face-target-box");
    var labelEl = $("face-target-label");
    if (boxEl) {
      boxEl.hidden = true;
      boxEl.classList.remove("face-target-box--matched", "face-target-box--unknown");
    }
    if (labelEl) {
      labelEl.hidden = true;
      labelEl.textContent = "";
      labelEl.classList.remove("face-target-label--unknown");
    }
  }

  function updateFaceTarget(vid, box, labelText, isMatch) {
    var boxEl = $("face-target-box");
    var labelEl = $("face-target-label");
    if (!boxEl || !vid || !box) {
      hideFaceTarget();
      return;
    }
    var r = mapFaceBoxToOverlayPixels(vid, box);
    if (!r) {
      hideFaceTarget();
      return;
    }
    boxEl.hidden = false;
    boxEl.style.left = r.left + "px";
    boxEl.style.top = r.top + "px";
    boxEl.style.width = r.width + "px";
    boxEl.style.height = r.height + "px";
    boxEl.classList.toggle("face-target-box--matched", Boolean(isMatch));
    boxEl.classList.toggle("face-target-box--unknown", Boolean(labelText) && !isMatch);
    if (labelEl) {
      if (labelText) {
        labelEl.textContent = labelText;
        labelEl.hidden = false;
        labelEl.classList.toggle("face-target-label--unknown", !isMatch);
      } else {
        labelEl.hidden = true;
        labelEl.textContent = "";
        labelEl.classList.remove("face-target-label--unknown");
      }
    }
  }

  /**
   * Min-distance per siswa (bukan per-descriptor), lalu uji keunikan antar orang.
   * Wajib: beberapa descriptor 1 orang tidak boleh saling men-diskualifikasi margin.
   */
  function bestMatch(descriptor) {
    var bestById = {};
    for (var i = 0; i < refs.length; i++) {
      var r = refs[i];
      if (!r || !r.siswa || !r.descriptor) continue;
      var id = String(r.siswa.id || "");
      if (!id) continue;
      var d = faceapi.euclideanDistance(descriptor, r.descriptor);
      if (!bestById[id] || d < bestById[id].distance) {
        bestById[id] = { ref: r, distance: d };
      }
    }
    var ranked = [];
    for (var key in bestById) {
      if (Object.prototype.hasOwnProperty.call(bestById, key)) ranked.push(bestById[key]);
    }
    ranked.sort(function (a, b) {
      return a.distance - b.distance;
    });
    if (!ranked.length) return null;
    var best = ranked[0];
    var secondD = ranked.length > 1 ? ranked[1].distance : Infinity;
    var uniqueEnough = ranked.length < 2 || secondD - best.distance >= MIN_MATCH_MARGIN;
    return {
      ref: best.ref,
      distance: best.distance,
      secondDistance: secondD === Infinity ? null : secondD,
      accepted: best.distance <= MATCH_THRESHOLD && uniqueEnough,
    };
  }

  function resetPendingMatch() {
    pendingMatchId = "";
    pendingHits = 0;
  }

  function confirmStableMatch(siswaId) {
    var id = String(siswaId || "");
    if (!id) {
      resetPendingMatch();
      return false;
    }
    if (pendingMatchId !== id) {
      pendingMatchId = id;
      pendingHits = 1;
      return false;
    }
    pendingHits += 1;
    return pendingHits >= STABLE_HITS;
  }

  function faceLargeEnough(box, vid) {
    if (!box || !vid || !vid.videoHeight) return false;
    return box.height / vid.videoHeight >= MIN_FACE_HEIGHT_RATIO;
  }

  function canAnnounce(siswaId) {
    var t = Date.now();
    var prev = lastAnnounceById[siswaId] || 0;
    if (t - prev < COOLDOWN_MS) return false;
    lastAnnounceById[siswaId] = t;
    return true;
  }

  function setDetectHint(text) {
    var hint = $("face-hint");
    if (hint) hint.textContent = text;
  }

  function onUnknown(descriptorDistance) {
    setDetectHint(
      "Tidak dikenali (jarak " +
        descriptorDistance.toFixed(2) +
        ", perlu ≤ " +
        MATCH_THRESHOLD.toFixed(2) +
        " dan unik)."
    );
  }

  function onMatch(siswa, descriptorDistance) {
    setDetectHint(
      "Cocok: " + (siswa.nama || "Siswa") + " (jarak " + descriptorDistance.toFixed(2) + ")."
    );
    if (canAnnounce(siswa.id)) {
      speakHasil(siswa);
      appendFaceLog(siswa, "face recognition");
      var wrap = $("face-video-wrap");
      if (wrap) {
        wrap.classList.add("face-video-wrap--pulse");
        window.setTimeout(function () {
          wrap.classList.remove("face-video-wrap--pulse");
        }, 900);
      }
    }
  }

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    var vid = $("face-video");
    if (typeof window.presensiStopWebcam === "function") {
      window.presensiStopWebcam(vid);
    } else if (stream) {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
      if (vid) vid.srcObject = null;
    }
    stream = null;
    busyDetect = false;
    resetPendingMatch();
    hideFaceTarget();
    var flip = $("face-btn-flip");
    if (flip) flip.hidden = true;
  }

  function syncFlipLabel() {
    var flip = $("face-btn-flip");
    if (!flip || typeof window.presensiGetFacingMode !== "function") return;
    var back = window.presensiGetFacingMode() === "environment";
    flip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
    flip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
  }

  function flipCamera() {
    var vid = $("face-video");
    if (!vid || !stream || typeof window.presensiSwitchCamera !== "function") return;
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    setStatus("<strong>Mengganti kamera…</strong>", "");
    window
      .presensiSwitchCamera(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        syncFlipLabel();
        var label =
          typeof window.presensiFacingLabel === "function"
            ? window.presensiFacingLabel()
            : "kamera";
        setStatus(
          "<strong>Kamera aktif (" + escapeHtml(label) + ").</strong> Posisikan wajah.",
          "ok"
        );
      })
      .catch(function (err) {
        setStatus(
          "<strong>Gagal ganti kamera.</strong> " +
            escapeHtml((err && err.message) || String(err)),
          "warn"
        );
      });
  }

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length) return;
    var vid = $("face-video");
    if (!vid || vid.readyState < 2) return;
    busyDetect = true;
    faceapi
      .detectSingleFace(vid, new faceapi.SsdMobilenetv1Options({ minConfidence: LIVE_MIN_CONFIDENCE }))
      .withFaceLandmarks()
      .withFaceDescriptor()
      .then(function (res) {
        busyDetect = false;
        if (!res || !res.detection || !res.detection.box) {
          resetPendingMatch();
          hideFaceTarget();
          return;
        }
        var box = res.detection.box;
        var labelText = null;
        var isMatch = false;
        if (!faceLargeEnough(box, vid)) {
          resetPendingMatch();
          labelText = "Dekatkan wajah";
          setDetectHint("Wajah agak jauh. Maju sedikit sampai kotak lebih besar — tidak perlu menempel kamera.");
          updateFaceTarget(vid, box, labelText, false);
          return;
        }
        if (res.descriptor) {
          var m = bestMatch(res.descriptor);
          if (m && m.accepted) {
            if (confirmStableMatch(m.ref.siswa && m.ref.siswa.id)) {
              isMatch = true;
              labelText = (m.ref.siswa.nama || "Siswa") + " · " + m.distance.toFixed(2);
              onMatch(m.ref.siswa, m.distance);
            } else {
              labelText = "Memeriksa… · " + m.distance.toFixed(2);
              setDetectHint(
                "Memeriksa kecocokan (skor " +
                  m.distance.toFixed(2) +
                  "). Tahan posisi sebentar."
              );
            }
          } else if (m) {
            resetPendingMatch();
            labelText = "Tidak dikenali · " + m.distance.toFixed(2);
            onUnknown(m.distance);
          } else {
            resetPendingMatch();
          }
        } else {
          resetPendingMatch();
        }
        updateFaceTarget(vid, box, labelText, isMatch);
      })
      .catch(function () {
        busyDetect = false;
        resetPendingMatch();
        hideFaceTarget();
      });
  }

  function startCamera() {
    stopCamera();
    var vid = $("face-video");
    if (!vid) {
      setStatus("<strong>Elemen video tidak ditemukan.</strong>", "warn");
      return Promise.reject(new Error("no video"));
    }
    if (typeof window.presensiOpenWebcam !== "function") {
      setStatus(
        "<strong>Skrip kamera tidak dimuat.</strong> Muat ulang halaman sepenuhnya (segarkan cache jika perlu).",
        "warn"
      );
      return Promise.reject(new Error("no presensi camera"));
    }
    return window
      .presensiOpenWebcam(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        setStatus(
          "<strong>Kamera aktif (ambang 0.38).</strong> 1 foto + augmentasi; kacamata/jarak tidak harus identik.",
          "ok"
        );
        var btnStart = $("face-btn-start");
        var btnStop = $("face-btn-stop");
        var btnFlip = $("face-btn-flip");
        if (btnStart) btnStart.hidden = true;
        if (btnStop) btnStop.hidden = false;
        if (btnFlip) btnFlip.hidden = false;
        syncFlipLabel();
        return s;
      })
      .catch(function (err) {
        var msg = err && (err.message || err.name) ? err.message || err.name : String(err);
        setStatus(
          "<strong>Tidak bisa mengaktifkan kamera.</strong> " +
            escapeHtml(msg) +
            " Pastikan izin kamera untuk situs ini, gunakan HTTPS atau localhost, dan coba tutup aplikasi lain yang memakai webcam.",
          "warn"
        );
        throw err;
      });
  }

  function sourceSize(src) {
    return {
      w: Number(src && (src.naturalWidth || src.videoWidth || src.width)) || 0,
      h: Number(src && (src.naturalHeight || src.videoHeight || src.height)) || 0,
    };
  }

  function detectDescriptor(source, minConf) {
    return faceapi
      .detectSingleFace(
        source,
        new faceapi.SsdMobilenetv1Options({ minConfidence: minConf == null ? REF_MIN_CONFIDENCE : minConf })
      )
      .withFaceLandmarks()
      .withFaceDescriptor();
  }

  function tryCanvas(fn) {
    try {
      return fn();
    } catch (e) {
      console.warn("[face] canvas", e);
      return null;
    }
  }

  function canvasFromDraw(src, drawFn) {
    var sz = sourceSize(src);
    if (!sz.w || !sz.h) return null;
    var maxEdge = 800;
    var scale = Math.min(1, maxEdge / Math.max(sz.w, sz.h));
    var w = Math.max(1, Math.round(sz.w * scale));
    var h = Math.max(1, Math.round(sz.h * scale));
    var c = document.createElement("canvas");
    c.width = w;
    c.height = h;
    var ctx = c.getContext("2d");
    if (!ctx) return null;
    drawFn(ctx, w, h);
    return c;
  }

  function canvasFlipH(src) {
    return canvasFromDraw(src, function (ctx, w, h) {
      ctx.translate(w, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(src, 0, 0, w, h);
    });
  }

  function canvasBlur(src, px) {
    return canvasFromDraw(src, function (ctx, w, h) {
      try {
        ctx.filter = "blur(" + px + "px)";
      } catch (e) {}
      ctx.drawImage(src, 0, 0, w, h);
    });
  }

  function canvasFacePad(src, box, pad) {
    try {
      var sz = sourceSize(src);
      if (!sz.w || !sz.h || !box) return null;
      var x = Math.max(0, box.x - box.width * pad);
      var y = Math.max(0, box.y - box.height * pad);
      var w = Math.min(sz.w - x, box.width * (1 + 2 * pad));
      var h = Math.min(sz.h - y, box.height * (1 + 2 * pad));
      if (w < 32 || h < 32) return null;
      var c = document.createElement("canvas");
      c.width = Math.round(w);
      c.height = Math.round(h);
      var ctx = c.getContext("2d");
      if (!ctx) return null;
      ctx.drawImage(src, x, y, w, h, 0, 0, c.width, c.height);
      return c;
    } catch (e) {
      console.warn("[face] facePad", e);
      return null;
    }
  }

  function pushRef(siswa, descriptor) {
    if (!descriptor) return;
    refs.push({ siswa: siswa, descriptor: descriptor });
  }

  function indexSiswaFoto(siswa, img) {
    return detectDescriptor(img)
      .then(function (det) {
        if (!det || !det.descriptor) return false;
        pushRef(siswa, det.descriptor);
        var extras = [];
        try {
          var flipped = tryCanvas(function () {
            return canvasFlipH(img);
          });
          if (flipped) extras.push(detectDescriptor(flipped).catch(function () { return null; }));
          var blurred = tryCanvas(function () {
            return canvasBlur(img, 1.4);
          });
          if (blurred) extras.push(detectDescriptor(blurred).catch(function () { return null; }));
          var padded =
            det.detection && det.detection.box
              ? tryCanvas(function () {
                  return canvasFacePad(img, det.detection.box, 0.35);
                })
              : null;
          if (padded) extras.push(detectDescriptor(padded).catch(function () { return null; }));
        } catch (e) {
          console.warn("[face] augment", siswa && siswa.nama, e);
          return true;
        }
        if (!extras.length) return true;
        return Promise.all(extras)
          .then(function (dets) {
            dets.forEach(function (d) {
              if (d && d.descriptor) pushRef(siswa, d.descriptor);
            });
            return true;
          })
          .catch(function () {
            return true;
          });
      })
      .catch(function (err) {
        console.warn("[face] detect", siswa && siswa.nama, err);
        return false;
      });
  }

  function indexOneSrc(siswa, src, augment) {
    if (!src || src.charAt(0) === "[") return Promise.resolve(false);
    return faceapi
      .fetchImage(src)
      .then(function (img) {
        if (augment) return indexSiswaFoto(siswa, img);
        return detectDescriptor(img)
          .then(function (det) {
            if (!det || !det.descriptor) return false;
            pushRef(siswa, det.descriptor);
            var flipped = tryCanvas(function () {
              return canvasFlipH(img);
            });
            if (!flipped) return true;
            return detectDescriptor(flipped)
              .catch(function () {
                return null;
              })
              .then(function (d) {
                if (d && d.descriptor) pushRef(siswa, d.descriptor);
                return true;
              });
          })
          .catch(function (err) {
            console.warn("[face] detect", siswa && siswa.nama, err);
            return false;
          });
      })
      .catch(function (err) {
        console.warn("[face] fetchImage", siswa && siswa.nama, err);
        return false;
      });
  }

  function indexSiswaAllFotos(siswa) {
    var list = parseFotoList(siswa.fotoWajah);
    if (!list.length) return Promise.resolve({ foto: 0 });
    var augment = list.length === 1;
    var okFoto = 0;
    var chain = Promise.resolve();
    list.forEach(function (src) {
      chain = chain.then(function () {
        return indexOneSrc(siswa, src, augment)
          .then(function (ok) {
            if (ok) okFoto++;
          })
          .catch(function () {});
      });
    });
    return chain.then(function () {
      return { foto: okFoto };
    });
  }

  function loadFotoIndex() {
    if (useRekamService() && window.PresensiRekamService.listFotoIndex) {
      return window.PresensiRekamService.listFotoIndex()
        .then(function (rows) {
          return (rows || []).filter(function (s) {
            return s.aktif !== false && s.hasFoto;
          });
        })
        .catch(function () {
          return loadReferensiSiswa().then(attachClientFotoFp);
        });
    }
    return loadReferensiSiswa().then(attachClientFotoFp);
  }

  function attachClientFotoFp(rows) {
    return (rows || []).map(function (s) {
      var next = Object.assign({}, s);
      if (!next.fotoFp && window.FaceRefCache) {
        next.fotoFp = window.FaceRefCache.clientFotoFp(next.fotoWajah);
      }
      next.hasFoto = true;
      return next;
    });
  }

  function loadFotosByIds(ids) {
    if (useRekamService() && window.PresensiRekamService.listWithFotoByIds) {
      return window.PresensiRekamService.listWithFotoByIds(ids);
    }
    return loadReferensiSiswa().then(function (rows) {
      var want = {};
      (ids || []).forEach(function (id) {
        want[String(id)] = true;
      });
      return (rows || []).filter(function (s) {
        return want[String(s.id)];
      });
    });
  }

  function applyCachedRef(entry) {
    var siswa = entry && entry.siswa;
    if (!siswa) return;
    (entry.descriptors || []).forEach(function (arr) {
      if (!arr || !arr.length) return;
      refs.push({ siswa: siswa, descriptor: new Float32Array(arr) });
    });
  }

  function indexSiswaForCache(siswa) {
    var start = refs.length;
    return indexSiswaAllFotos(siswa).then(function (res) {
      var descriptors = [];
      for (var i = start; i < refs.length; i++) {
        if (refs[i] && refs[i].descriptor) {
          descriptors.push(Array.from(refs[i].descriptor));
        }
      }
      return { foto: res && res.foto ? res.foto : 0, descriptors: descriptors };
    });
  }

  function buildRefsLegacy() {
    refs = [];
    setStatus("<strong>Memuat foto referensi…</strong> Mohon tunggu.", "");
    return loadReferensiSiswa().then(function (list) {
      if (!list.length) {
        setStatus(
          '<strong>Belum ada foto referensi.</strong> Siswa aktif perlu foto wajah di ' +
            '<a href="../settings/rekam-data.html">Rekam data</a>.',
          "warn"
        );
        return 0;
      }
      var totalFoto = 0;
      list.forEach(function (s) {
        totalFoto += parseFotoList(s.fotoWajah).length;
      });
      setStatus(
        "<strong>Memproses " +
          list.length +
          " siswa (" +
          totalFoto +
          " foto)…</strong> Semua foto per anak dipakai untuk deteksi.",
        ""
      );
      var okSiswa = 0;
      var okFoto = 0;
      var fail = 0;
      var chain = Promise.resolve();
      list.forEach(function (siswa) {
        chain = chain.then(function () {
          return indexSiswaAllFotos(siswa)
            .then(function (res) {
              var n = res && res.foto ? res.foto : 0;
              if (n) {
                okSiswa++;
                okFoto += n;
              } else {
                fail++;
              }
            })
            .catch(function () {
              fail++;
            });
        });
      });
      return chain.then(function () {
        var parts = [];
        parts.push(
          "<strong>Referensi siap:</strong> " +
            okSiswa +
            " siswa, " +
            okFoto +
            " foto terindeks."
        );
        if (fail) parts.push(" " + fail + " siswa fotonya tidak terbaca (ganti foto di Rekam data).");
        setStatus(parts.join(""), okSiswa ? "ok" : "warn");
        return okSiswa;
      });
    });
  }

  function buildRefs(force) {
    refs = [];
    if (!window.FaceRefCache || !window.FaceRefCache.hydrate) {
      return buildRefsLegacy();
    }
    setStatus("<strong>Memeriksa cache wajah…</strong> Foto tidak diunduh ulang jika masih sama.", "");
    return window.FaceRefCache.hydrate({
      force: Boolean(force),
      setStatus: setStatus,
      loadIndex: loadFotoIndex,
      loadFotos: loadFotosByIds,
      loadAllFotos: loadReferensiSiswa,
      indexOne: indexSiswaForCache,
      applyCached: applyCachedRef,
    })
      .then(function (stats) {
        if (!stats || !stats.okSiswa) {
          setStatus(
            '<strong>Belum ada foto referensi.</strong> Siswa aktif perlu foto wajah di ' +
              '<a href="../settings/rekam-data.html">Rekam data</a>.',
            "warn"
          );
          return 0;
        }
        return stats.okSiswa;
      })
      .catch(function (err) {
        console.warn("[face] cache, hitung ulang penuh:", err);
        return buildRefsLegacy();
      });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus("<strong>face-api.js gagal dimuat.</strong> Periksa koneksi internet.", "warn");
      return Promise.reject(new Error("no faceapi"));
    }
    setStatus("<strong>Memuat model pengenalan wajah…</strong> Butuh unduhan sekali.", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return buildRefs();
    });
  }

  function onClick(e) {
    var t = e.target;
    if (t.id === "face-btn-start") {
      if (!modelsReady) {
        setStatus("Model belum siap. Tunggu atau muat ulang halaman.", "warn");
        return;
      }
      if (!refs.length) {
        buildRefs().then(function (n) {
          if (n) startCamera().catch(function () {});
        });
        return;
      }
      startCamera().catch(function () {});
      return;
    }
    if (t.id === "face-btn-stop") {
      stopCamera();
      var btnStart = $("face-btn-start");
      var btnStop = $("face-btn-stop");
      if (btnStart) btnStart.hidden = false;
      if (btnStop) btnStop.hidden = true;
      setStatus(
        "<strong>Kamera dihentikan.</strong> Tekan Aktifkan kamera untuk melanjutkan.",
        ""
      );
      return;
    }
    if (t.id === "face-btn-flip") {
      flipCamera();
      return;
    }
    if (t.id === "face-btn-reload") {
      stopCamera();
      var btnStart = $("face-btn-start");
      var btnStop = $("face-btn-stop");
      if (btnStart) btnStart.hidden = false;
      if (btnStop) btnStop.hidden = true;
      buildRefs(false).catch(function (err) {
        setStatus(escapeHtml(err.message || String(err)), "warn");
      });
      return;
    }
  }

  function init() {
    root = document.getElementById("face-root");
    if (!root || root.dataset.faceBound) return;
    root.dataset.faceBound = "1";

    function afterLogsReady() {
      renderFaceLogTable();
    }

    if (useDbPresensiLog()) {
      fetchFaceLogsFromDb().then(afterLogsReady).catch(afterLogsReady);
    } else {
      afterLogsReady();
    }

    if (window.speechSynthesis) {
      var warmVoices = function () {
        try {
          window.speechSynthesis.getVoices();
        } catch (e) {}
      };
      warmVoices();
      window.speechSynthesis.addEventListener("voiceschanged", warmVoices);
    }

    root.addEventListener("click", onClick);
    window.addEventListener("beforeunload", stopCamera);

    function bootModels() {
      loadModels()
        .then(function (n) {
          if (n > 0) {
            setStatus(
              "<strong>Model dan referensi siap (ambang 0.38).</strong> " +
                n +
                " siswa. Tekan «Aktifkan kamera». Kunjungan berikutnya memakai cache lokal, tanpa unduh ulang semua foto.",
              "ok"
            );
          }
        })
        .catch(function (err) {
          setStatus("<strong>Gagal memuat model.</strong> " + escapeHtml(err.message || String(err)), "warn");
        });
    }

    if (window.PresensiData && window.PresensiData.isRemote()) {
      if (window.PresensiLoading) {
        window.PresensiLoading.show(root, "Memuat data siswa & saldo…");
      }
      setStatus("<strong>Menyinkronkan data siswa dari server…</strong>", "");
      window.PresensiData.pullAll()
        .then(bootModels)
        .catch(function () {
          bootModels();
        })
        .finally(function () {
          if (window.PresensiLoading) {
            window.PresensiLoading.hide(root);
          }
        });
    } else {
      bootModels();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
