/**
 * Kantin FacePay — deteksi wajah → modal → InquirySALDO → PaymentBELANJAKantinWithKeterangan
 */
(function () {
  "use strict";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.38;
  var MIN_MATCH_MARGIN = 0.1;
  var STABLE_HITS = 2;
  var MIN_FACE_HEIGHT_RATIO = 0.09;
  var LIVE_MIN_CONFIDENCE = 0.42;
  var REF_MIN_CONFIDENCE = 0.35;
  var COOLDOWN_MS = 5000;
  var DETECT_MS = 350;
  var NOMINAL_MIN = 100;
  var KET_MAX = 60;

  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastAnnounceById = {};
  var pendingMatchId = "";
  var pendingHits = 0;
  var modalOpen = false;
  var payInFlight = false;
  var pendingPay = null; // { siswa, nokartu, nama, saldo, nominal, ket }

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function formatRupiah(n) {
    if (window.KantinLog && window.KantinLog.formatRupiah) {
      return window.KantinLog.formatRupiah(n);
    }
    var v = Number(n) || 0;
    try {
      return v.toLocaleString("id-ID");
    } catch (e) {
      return String(v);
    }
  }

  function adminApi(file) {
    var p = location.pathname || "";
    var idx = p.indexOf("/kantin/");
    var base = idx >= 0 ? p.slice(0, idx) : "";
    var name = String(file || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    return base + "/admin/api/" + name;
  }

  function setStatus(html, kind) {
    var el = $("kantin-status");
    if (!el) return;
    el.className = "k-status" + (kind ? " k-status--" + kind : "");
    el.innerHTML = html;
  }

  function toast(msg, kind) {
    var el = $("kantin-toast");
    if (!el) return;
    el.hidden = false;
    el.className = "k-toast" + (kind ? " k-toast--" + kind : "");
    el.textContent = msg;
    window.clearTimeout(toast._t);
    toast._t = window.setTimeout(function () {
      el.hidden = true;
    }, 3200);
  }

  function getNominal() {
    var inp = $("kantin-pay-nominal-input");
    var v = inp ? Number(inp.value) : 0;
    if (isNaN(v)) v = 0;
    return Math.floor(v);
  }

  function setNominal(v) {
    var n = Math.max(0, Math.floor(Number(v) || 0));
    var inp = $("kantin-pay-nominal-input");
    if (inp) inp.value = String(n);
    if (pendingPay) pendingPay.nominal = n;
    refreshPayValidation();
  }

  function getKeterangan() {
    var inp = $("kantin-pay-ket-input");
    var s = inp ? String(inp.value || "") : "";
    s = s.replace(/\s+/g, " ").trim();
    if (s.length > KET_MAX) s = s.slice(0, KET_MAX);
    return s;
  }

  function setKeterangan(v) {
    var s = String(v || "").replace(/\s+/g, " ");
    if (s.length > KET_MAX) s = s.slice(0, KET_MAX);
    var inp = $("kantin-pay-ket-input");
    if (inp) inp.value = s;
    updateKetCount();
    if (pendingPay) pendingPay.ket = getKeterangan();
    refreshPayValidation();
  }

  function updateKetCount() {
    var el = $("kantin-pay-ket-count");
    var inp = $("kantin-pay-ket-input");
    var n = inp ? String(inp.value || "").length : 0;
    if (el) el.textContent = n + " / " + KET_MAX;
  }

  function refreshPayValidation() {
    if (!pendingPay) return;
    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    var sisaEl = $("kantin-pay-sisa");
    var nominal = getNominal();
    pendingPay.nominal = nominal;
    pendingPay.ket = getKeterangan();

    if (pendingPay.saldo == null) {
      if (conf) conf.disabled = true;
      if (sisaEl) sisaEl.textContent = "—";
      return;
    }

    var sisa = pendingPay.saldo - nominal;
    if (sisaEl) {
      if (nominal < NOMINAL_MIN) {
        sisaEl.textContent = "—";
      } else if (sisa < 0) {
        sisaEl.textContent = "Tidak cukup";
        sisaEl.classList.add("k-modal__sisa--bad");
      } else {
        sisaEl.textContent = "Rp " + formatRupiah(sisa);
        sisaEl.classList.remove("k-modal__sisa--bad");
      }
    }

    if (nominal < NOMINAL_MIN) {
      if (conf) conf.disabled = true;
      if (err) {
        err.hidden = false;
        err.textContent = "Nominal minimal Rp " + formatRupiah(NOMINAL_MIN) + ".";
      }
      return;
    }

    if (!pendingPay.ket) {
      if (conf) conf.disabled = true;
      if (err) {
        err.hidden = false;
        err.textContent = "Isi deskripsi barang (maks. " + KET_MAX + " karakter).";
      }
      return;
    }

    // Kantin = selalu pengurangan: nominal tidak boleh melebihi saldo
    if (pendingPay.saldo < nominal) {
      if (conf) conf.disabled = true;
      if (err) {
        err.hidden = false;
        err.textContent = "Saldo tidak cukup untuk pengurangan ini.";
      }
      return;
    }

    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    if (conf && !payInFlight) conf.disabled = false;
  }

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

  function parseFotoList(foto) {
    if (Array.isArray(foto)) {
      return foto
        .map(normalizeFotoSrc)
        .filter(function (src) {
          return src && src.length > 80 && src.charAt(0) !== "[";
        });
    }
    var s = String(foto || "").trim();
    if (!s) return [];
    if (s.charAt(0) === "[") {
      try {
        var arr = JSON.parse(s);
        return Array.isArray(arr)
          ? arr.map(normalizeFotoSrc).filter(function (src) {
              return src && src.length > 80 && src.charAt(0) !== "[";
            })
          : [];
      } catch (e) {
        return [];
      }
    }
    var one = normalizeFotoSrc(s);
    return one && one.length > 80 ? [one] : [];
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
      console.warn("[kantin-face] canvas", e);
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
      console.warn("[kantin-face] facePad", e);
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
          console.warn("[kantin-face] augment", siswa && siswa.nama, e);
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
        console.warn("[kantin-face] detect", siswa && siswa.nama, err);
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
            console.warn("[kantin-face] detect", siswa && siswa.nama, err);
            return false;
          });
      })
      .catch(function (err) {
        console.warn("[kantin-face] fetchImage", siswa && siswa.nama, err);
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

  function fetchRekamJson(query) {
    var url = adminApi("rekam-data.php") + (query || "");
    return fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } }).then(
      function (res) {
        return res.text().then(function (text) {
          var parsed = null;
          try {
            parsed = text ? JSON.parse(text) : null;
          } catch (e) {
            throw new Error("Respons foto referensi tidak valid");
          }
          if (!res.ok || (parsed && parsed.ok === false)) {
            throw new Error((parsed && parsed.error) || "HTTP " + res.status);
          }
          return (parsed && parsed.data) || [];
        });
      }
    );
  }

  function loadReferensiSiswa() {
    return fetchRekamJson("?withFoto=1").then(function (rows) {
      return (rows || [])
        .map(function (s) {
          return Object.assign({}, s);
        })
        .filter(function (s) {
          return s.aktif !== false && parseFotoList(s.fotoWajah).length > 0;
        });
    });
  }

  function loadFotoIndex() {
    return fetchRekamJson("?fotoIndex=1")
      .then(function (rows) {
        return (rows || []).filter(function (s) {
          return s.aktif !== false && s.hasFoto;
        });
      })
      .catch(function () {
        return loadReferensiSiswa().then(function (rows) {
          return (rows || []).map(function (s) {
            var next = Object.assign({}, s);
            if (!next.fotoFp && window.FaceRefCache) {
              next.fotoFp = window.FaceRefCache.clientFotoFp(next.fotoWajah);
            }
            next.hasFoto = true;
            return next;
          });
        });
      });
  }

  function loadFotosByIds(ids) {
    var list = (ids || []).map(String).filter(Boolean);
    if (!list.length) return Promise.resolve([]);
    var chunks = [];
    var i;
    for (i = 0; i < list.length; i += 80) chunks.push(list.slice(i, i + 80));
    var acc = [];
    var chain = Promise.resolve();
    chunks.forEach(function (chunk) {
      chain = chain.then(function () {
        return fetchRekamJson("?withFoto=1&ids=" + encodeURIComponent(chunk.join(","))).then(
          function (rows) {
            acc = acc.concat(rows || []);
          }
        );
      });
    });
    return chain.then(function () {
      return acc;
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
    setStatus("<strong>Memuat foto referensi…</strong>", "");
    return loadReferensiSiswa().then(function (list) {
      if (!list.length) {
        setStatus(
          "<strong>Belum ada foto referensi.</strong> Rekam wajah siswa di panel Admin terlebih dahulu.",
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
          " foto)…</strong> Semua foto per anak dipakai.",
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
        var parts = [
          "<strong>Referensi siap (ambang 0.38):</strong> " +
            okSiswa +
            " siswa, " +
            okFoto +
            " foto.",
        ];
        if (fail) parts.push(" " + fail + " siswa fotonya gagal dibaca.");
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
    setStatus("<strong>Memeriksa cache wajah…</strong>", "");
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
            "<strong>Belum ada foto referensi.</strong> Rekam wajah siswa di panel Admin terlebih dahulu.",
            "warn"
          );
          return 0;
        }
        return stats.okSiswa;
      })
      .catch(function (err) {
        console.warn("[kantin-face] cache, hitung ulang penuh:", err);
        return buildRefsLegacy();
      });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus("<strong>face-api.js gagal dimuat.</strong> Periksa koneksi internet.", "warn");
      return Promise.reject(new Error("no faceapi"));
    }
    setStatus("<strong>Memuat model wajah…</strong>", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return buildRefs();
    });
  }

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

  function setDetectHint(text) {
    var hint = $("kantin-hint");
    if (hint) hint.textContent = text;
  }

  function canAnnounce(siswaId) {
    if (modalOpen || payInFlight) return false;
    var t = Date.now();
    var prev = lastAnnounceById[siswaId] || 0;
    if (t - prev < COOLDOWN_MS) return false;
    lastAnnounceById[siswaId] = t;
    return true;
  }

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
    var boxEl = $("kantin-target-box");
    var labelEl = $("kantin-target-label");
    if (boxEl) {
      boxEl.hidden = true;
      boxEl.classList.remove("k-target--matched", "k-target--unknown");
    }
    if (labelEl) {
      labelEl.hidden = true;
      labelEl.textContent = "";
      labelEl.classList.remove("k-target__label--unknown");
    }
  }

  function updateFaceTarget(vid, box, labelText, isMatch) {
    var boxEl = $("kantin-target-box");
    var labelEl = $("kantin-target-label");
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
    boxEl.classList.toggle("k-target--matched", Boolean(isMatch));
    boxEl.classList.toggle("k-target--unknown", Boolean(labelText) && !isMatch);
    if (labelEl) {
      if (labelText) {
        labelEl.textContent = labelText;
        labelEl.hidden = false;
        labelEl.classList.toggle("k-target__label--unknown", !isMatch);
      } else {
        labelEl.hidden = true;
        labelEl.textContent = "";
        labelEl.classList.remove("k-target__label--unknown");
      }
    }
  }

  function closePayModal() {
    var modal = $("kantin-pay-modal");
    if (modal) modal.hidden = true;
    modalOpen = false;
    pendingPay = null;
    var err = $("kantin-pay-error");
    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    var hint = $("kantin-hint");
    if (hint) {
      hint.textContent =
        "Hadapkan wajah. Nama hanya tampil jika skor ≤ 0.38 dan unik. Setelah cocok, isi nominal di modal.";
    }
    // Lanjut deteksi siswa berikutnya
    if (modelsReady && refs.length) {
      startCamera().catch(function () {});
    }
  }

  function openPayModal(siswa) {
    var nokartu = String(siswa.nis || "").replace(/\D/g, "");
    if (!nokartu) {
      toast("NIS siswa tidak valid untuk pembayaran", "danger");
      return;
    }

    modalOpen = true;
    // Matikan kamera agar tidak deteksi ulang saat modal terbuka
    stopCamera();
    setStatus("<strong>Kamera dijeda.</strong> Selesaikan atau batalkan pembayaran di modal.", "");

    pendingPay = {
      siswa: siswa,
      nokartu: nokartu,
      nama: siswa.nama || "Siswa",
      saldo: null,
      nominal: getNominal() || 1000,
      ket: getKeterangan(),
    };

    var modal = $("kantin-pay-modal");
    var namaEl = $("kantin-pay-nama");
    var saldoEl = $("kantin-pay-saldo");
    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    var nomInp = $("kantin-pay-nominal-input");
    var ketInp = $("kantin-pay-ket-input");

    if (namaEl) namaEl.textContent = pendingPay.nama;
    if (saldoEl) saldoEl.textContent = "Memuat…";
    if (nomInp) {
      nomInp.value = String(pendingPay.nominal);
      window.setTimeout(function () {
        nomInp.focus();
        nomInp.select();
      }, 80);
    }
    if (ketInp) {
      ketInp.value = pendingPay.ket || ketInp.value || "";
      updateKetCount();
    }
    if (conf) conf.disabled = true;
    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    if (modal) modal.hidden = false;

    window.KantinAuth.requestJson("POST", window.KantinAuth.apiUrl("saldo-inquiry.php"), {
      nokartu: nokartu,
      siswaId: siswa.id,
    })
      .then(function (body) {
        if (!pendingPay || pendingPay.nokartu !== nokartu) return;
        var data = (body && body.data) || {};
        pendingPay.saldo = Number(data.saldo) || 0;
        if (data.nama) {
          pendingPay.nama = data.nama;
          if (namaEl) namaEl.textContent = data.nama;
        }
        if (saldoEl) saldoEl.textContent = "Rp " + formatRupiah(pendingPay.saldo);
        refreshPayValidation();
      })
      .catch(function (e) {
        if (!pendingPay || pendingPay.nokartu !== nokartu) return;
        if (saldoEl) saldoEl.textContent = "Gagal";
        if (err) {
          err.hidden = false;
          err.textContent = (e && e.message) || "Gagal inquiry saldo";
        }
        if (conf) conf.disabled = true;
      });
  }

  function confirmPay() {
    if (!pendingPay || payInFlight) return;
    var nominal = getNominal();
    var ket = getKeterangan();
    pendingPay.nominal = nominal;
    pendingPay.ket = ket;
    refreshPayValidation();
    if (nominal < NOMINAL_MIN) {
      toast("Nominal minimal Rp " + formatRupiah(NOMINAL_MIN), "warn");
      return;
    }
    if (!ket) {
      toast("Isi deskripsi barang", "warn");
      return;
    }
    if (pendingPay.saldo != null && pendingPay.saldo < nominal) {
      toast("Saldo tidak cukup untuk pengurangan", "warn");
      return;
    }

    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    payInFlight = true;
    if (conf) {
      conf.disabled = true;
      conf.textContent = "Memotong…";
    }
    window.KantinAuth.requestJson("POST", window.KantinAuth.apiUrl("payment.php"), {
      nokartu: pendingPay.nokartu,
      nominal: pendingPay.nominal,
      ket: pendingPay.ket,
    })
      .then(function () {
        var wrap = $("kantin-video-wrap");
        if (wrap) {
          wrap.classList.add("k-stage--pulse");
          window.setTimeout(function () {
            wrap.classList.remove("k-stage--pulse");
          }, 900);
        }
        toast(
          "Dipotong Rp " +
            formatRupiah(pendingPay.nominal) +
            " · " +
            (pendingPay.ket || "") +
            " · " +
            (pendingPay.nama || "Siswa"),
          "ok"
        );
        try {
          sessionStorage.setItem("kantin_last_nominal", String(pendingPay.nominal));
          sessionStorage.setItem("kantin_last_ket", pendingPay.ket || "");
        } catch (eSave) {}
        closePayModal();
        if (window.KantinLog) window.KantinLog.load();
      })
      .catch(function (e) {
        if (err) {
          err.hidden = false;
          err.textContent = (e && e.message) || "Pengurangan gagal";
        }
        toast((e && e.message) || "Pengurangan gagal", "danger");
      })
      .finally(function () {
        payInFlight = false;
        if (conf) {
          conf.textContent = "Potong saldo";
        }
        refreshPayValidation();
      });
  }

  function onMatch(siswa, descriptorDistance) {
    setDetectHint(
      "Cocok: " +
        (siswa.nama || "Siswa") +
        " (jarak " +
        descriptorDistance.toFixed(2) +
        "). Menyiapkan konfirmasi…"
    );
    if (canAnnounce(siswa.id)) {
      openPayModal(siswa);
    }
  }

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length || modalOpen) return;
    var vid = $("kantin-video");
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
          setDetectHint("Wajah agak jauh. Maju sedikit — tidak perlu menempel kamera.");
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
              setDetectHint("Memeriksa kecocokan (skor " + m.distance.toFixed(2) + "). Tahan posisi sebentar.");
            }
          } else if (m) {
            resetPendingMatch();
            labelText = "Tidak dikenali · " + m.distance.toFixed(2);
            setDetectHint(
              "Tidak dikenali (jarak " +
                m.distance.toFixed(2) +
                ", perlu ≤ " +
                MATCH_THRESHOLD.toFixed(2) +
                " dan unik)."
            );
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

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    var vid = $("kantin-video");
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
    var btnStart = $("kantin-cam-start");
    var btnStop = $("kantin-cam-stop");
    var btnFlip = $("kantin-cam-flip");
    if (btnStart) btnStart.hidden = false;
    if (btnStop) btnStop.hidden = true;
    if (btnFlip) btnFlip.hidden = true;
  }

  function syncFlipLabel() {
    var flip = $("kantin-cam-flip");
    if (!flip || typeof window.presensiGetFacingMode !== "function") return;
    var back = window.presensiGetFacingMode() === "environment";
    flip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
    flip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
  }

  function flipCamera() {
    var vid = $("kantin-video");
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
          "<strong>Kamera aktif (" + escapeHtml(label) + ", ambang 0.38).</strong> Hadapkan wajah siswa.",
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

  function startCamera() {
    stopCamera();
    var vid = $("kantin-video");
    if (!vid) return Promise.reject(new Error("no video"));
    if (typeof window.presensiOpenWebcam !== "function") {
      setStatus("<strong>Skrip kamera tidak dimuat.</strong>", "warn");
      return Promise.reject(new Error("no camera helper"));
    }
    return window
      .presensiOpenWebcam(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        setStatus(
          "<strong>Kamera aktif (ambang 0.38).</strong> Nama hanya jika unik; nominal diisi di modal.",
          "ok"
        );
        var btnStart = $("kantin-cam-start");
        var btnStop = $("kantin-cam-stop");
        var btnFlip = $("kantin-cam-flip");
        if (btnStart) btnStart.hidden = true;
        if (btnStop) btnStop.hidden = false;
        if (btnFlip) btnFlip.hidden = false;
        syncFlipLabel();
      })
      .catch(function (err) {
        setStatus(
          "<strong>Kamera gagal.</strong> " + escapeHtml((err && err.message) || String(err)),
          "warn"
        );
        throw err;
      });
  }

  function bindUi() {
    var lastNominal = 1000;
    try {
      var saved = Number(sessionStorage.getItem("kantin_last_nominal"));
      if (!isNaN(saved) && saved >= NOMINAL_MIN) lastNominal = saved;
    } catch (e) {}
    setNominal(lastNominal);

    var lastKet = "";
    try {
      lastKet = String(sessionStorage.getItem("kantin_last_ket") || "");
    } catch (eKet) {}
    if (lastKet) setKeterangan(lastKet);
    else updateKetCount();

    var nom = $("kantin-pay-nominal-input");
    if (nom) {
      nom.addEventListener("input", function () {
        if (pendingPay) {
          pendingPay.nominal = getNominal();
          refreshPayValidation();
        }
      });
      nom.addEventListener("change", function () {
        var n = getNominal();
        if (n < NOMINAL_MIN && n !== 0) setNominal(NOMINAL_MIN);
        else if (pendingPay) refreshPayValidation();
        try {
          sessionStorage.setItem("kantin_last_nominal", String(getNominal() || lastNominal));
        } catch (e2) {}
      });
      nom.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          confirmPay();
        }
      });
    }

    var ketInpBind = $("kantin-pay-ket-input");
    if (ketInpBind) {
      ketInpBind.addEventListener("input", function () {
        updateKetCount();
        if (pendingPay) {
          pendingPay.ket = getKeterangan();
          refreshPayValidation();
        }
      });
      ketInpBind.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          confirmPay();
        }
      });
    }

    var start = $("kantin-cam-start");
    if (start) {
      start.addEventListener("click", function () {
        if (!modelsReady) {
          setStatus("Model belum siap. Tunggu sebentar.", "warn");
          return;
        }
        if (!refs.length) {
          buildRefs().then(function (n) {
            if (n) startCamera().catch(function () {});
          });
          return;
        }
        startCamera().catch(function () {});
      });
    }

    var stop = $("kantin-cam-stop");
    if (stop) {
      stop.addEventListener("click", function () {
        stopCamera();
        setStatus("<strong>Kamera dihentikan.</strong>", "");
      });
    }

    var flip = $("kantin-cam-flip");
    if (flip) flip.addEventListener("click", flipCamera);

    var reload = $("kantin-reload-faces");
    if (reload) {
      reload.addEventListener("click", function () {
        stopCamera();
        buildRefs().catch(function (err) {
          setStatus(escapeHtml((err && err.message) || String(err)), "warn");
        });
      });
    }

    var cancel = $("kantin-pay-cancel");
    if (cancel) cancel.addEventListener("click", closePayModal);

    var backdrop = document.querySelector("#kantin-pay-modal .k-modal__backdrop");
    if (backdrop) {
      backdrop.addEventListener("click", closePayModal);
    }

    var confirmBtn = $("kantin-pay-confirm");
    if (confirmBtn) confirmBtn.addEventListener("click", confirmPay);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modalOpen) closePayModal();
    });

    var logout = $("kantin-logout");
    if (logout) {
      logout.addEventListener("click", function () {
        stopCamera();
        window.KantinAuth.logout()
          .catch(function () {})
          .then(function () {
            location.replace("login.html");
          });
      });
    }

    window.addEventListener("beforeunload", stopCamera);
  }

  function init() {
    if (!window.KantinAuth) return;

    window.KantinAuth.requireSession().then(function (user) {
      if (!user) return;
      var label = $("kantin-user-label");
      if (label) {
        label.textContent = user.displayName || user.username || "Kantin";
      }
      bindUi();
      if (window.KantinLog) {
        window.KantinLog.init();
        window.KantinLog.load();
      }
      loadModels().catch(function (err) {
        setStatus("<strong>Gagal memuat model.</strong> " + escapeHtml((err && err.message) || err), "warn");
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
