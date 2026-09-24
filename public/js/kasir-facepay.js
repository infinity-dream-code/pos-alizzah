/**
 * Kasir FacePay — match wajah → NIS → inquiry/pay lewat Laravel (Malang_Alizzah).
 * Dipanggil dari resources/views/kasir/index.blade.php
 */
(function (global) {
  "use strict";

  var MODEL_URL =
    "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.38;
  var MIN_MATCH_MARGIN = 0.1;
  var STABLE_HITS = 2;
  var MIN_FACE_HEIGHT_RATIO = 0.09;
  var LIVE_MIN_CONFIDENCE = 0.42;
  var REF_MIN_CONFIDENCE = 0.35;
  var DETECT_MS = 350;
  var KET_MAX = 60;

  var cfg = null;
  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var pendingMatchId = "";
  var pendingHits = 0;
  var modalOpen = false;
  var confirmOpen = false;
  var matched = null; // { siswa, nokartu, namaMerchant, saldo }

  function $(id) {
    return document.getElementById(id);
  }

  function setStatus(html, kind) {
    var el = $("faceStatus");
    if (!el) return;
    el.innerHTML = html || "";
    el.className =
      "text-sm mt-2 " +
      (kind === "warn"
        ? "text-amber-700"
        : kind === "ok"
          ? "text-green-700"
          : "text-gray-600");
  }

  function normalizeFotoSrc(src) {
    var s = String(src || "").trim();
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
          ? arr
              .map(normalizeFotoSrc)
              .filter(function (src) {
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

  function detectDescriptor(source, minConf) {
    return faceapi
      .detectSingleFace(
        source,
        new faceapi.SsdMobilenetv1Options({
          minConfidence: minConf == null ? REF_MIN_CONFIDENCE : minConf,
        })
      )
      .withFaceLandmarks()
      .withFaceDescriptor();
  }

  function indexSiswaAllFotos(siswa) {
    var list = parseFotoList(siswa.fotoWajah);
    var chain = Promise.resolve();
    list.forEach(function (src) {
      chain = chain.then(function () {
        return new Promise(function (resolve) {
          var img = new Image();
          img.onload = function () {
            detectDescriptor(img, REF_MIN_CONFIDENCE)
              .then(function (det) {
                if (det && det.descriptor) {
                  refs.push({ siswa: siswa, descriptor: det.descriptor });
                }
                resolve();
              })
              .catch(function () {
                resolve();
              });
          };
          img.onerror = function () {
            resolve();
          };
          img.crossOrigin = "anonymous";
          img.src = src;
        });
      });
    });
    return chain;
  }

  function loadReferensi() {
    refs = [];
    setStatus("<strong>Memuat foto referensi…</strong>", "");
    return fetch(cfg.urls.refs, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok || !json || json.ok === false) {
            throw new Error((json && json.error) || "HTTP " + res.status);
          }
          return (json.data || []).filter(function (s) {
            return s && parseFotoList(s.fotoWajah).length > 0;
          });
        });
      })
      .then(function (list) {
        if (!list.length) {
          setStatus(
            "<strong>Belum ada foto referensi.</strong> Rekam wajah di panel FacePay Admin dulu.",
            "warn"
          );
          return 0;
        }
        setStatus(
          "<strong>Memproses " + list.length + " siswa…</strong>",
          ""
        );
        var chain = Promise.resolve();
        list.forEach(function (siswa) {
          chain = chain.then(function () {
            return indexSiswaAllFotos(siswa);
          });
        });
        return chain.then(function () {
          var n = refs.length;
          if (!n) {
            setStatus(
              "<strong>Tidak ada descriptor valid.</strong> Cek kualitas foto referensi.",
              "warn"
            );
            return 0;
          }
          setStatus(
            "<strong>Referensi siap (ambang 0.38):</strong> " + n + " wajah",
            "ok"
          );
          return n;
        });
      });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus(
        "<strong>face-api.js gagal dimuat.</strong> Periksa koneksi internet.",
        "warn"
      );
      return Promise.reject(new Error("no faceapi"));
    }
    if (modelsReady && refs.length) {
      return Promise.resolve(refs.length);
    }
    setStatus("<strong>Memuat model wajah…</strong>", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return loadReferensi();
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
      if (Object.prototype.hasOwnProperty.call(bestById, key)) {
        ranked.push(bestById[key]);
      }
    }
    ranked.sort(function (a, b) {
      return a.distance - b.distance;
    });
    if (!ranked.length) return null;
    var best = ranked[0];
    var secondD = ranked.length > 1 ? ranked[1].distance : Infinity;
    var uniqueEnough =
      ranked.length < 2 || secondD - best.distance >= MIN_MATCH_MARGIN;
    return {
      ref: best.ref,
      distance: best.distance,
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

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) {
        try {
          t.stop();
        } catch (e) {}
      });
      stream = null;
    }
    var vid = $("faceVideo");
    if (vid) {
      try {
        vid.srcObject = null;
      } catch (e) {}
    }
    busyDetect = false;
    resetPendingMatch();
  }

  function showMatchOverlay(nama) {
    var el = $("faceMatchOverlay");
    if (!el) return;
    el.textContent = nama || "";
    el.classList.toggle("hidden", !nama);
  }

  function showCameraStep() {
    confirmOpen = false;
    matched = null;
    var cam = $("faceCameraStep");
    var conf = $("faceConfirmStep");
    var proc = $("faceProcessing");
    if (cam) cam.classList.remove("hidden");
    if (conf) conf.classList.add("hidden");
    if (proc) proc.classList.add("hidden");
    showMatchOverlay("");
  }

  function showConfirmStep(payload) {
    confirmOpen = true;
    matched = payload;
    stopCamera();
    var cam = $("faceCameraStep");
    var conf = $("faceConfirmStep");
    if (cam) cam.classList.add("hidden");
    if (conf) conf.classList.remove("hidden");

    $("faceConfirmNama").textContent = payload.namaMerchant || payload.siswa.nama || "—";
    $("faceConfirmNis").textContent = payload.nokartu || "—";
    $("faceConfirmSaldo").textContent = cfg.formatRupiah(payload.saldo || 0);
    $("faceConfirmTotal").textContent = cfg.formatRupiah(cfg.getGrandTotal());

    var ketInput = $("faceKetInput");
    if (ketInput) {
      ketInput.value = cfg.buildKetFromCart().slice(0, KET_MAX);
      ketInput.focus();
    }

    var btn = $("btnFacePay");
    if (btn) {
      var enough = (payload.saldo || 0) >= cfg.getGrandTotal();
      btn.disabled = !enough;
      btn.className = enough
        ? "w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-semibold transition"
        : "w-full py-3 bg-gray-400 text-white rounded font-semibold cursor-not-allowed";
      var status = $("faceConfirmStatus");
      if (status) {
        status.textContent = enough
          ? "Saldo cukup. Konfirmasi keterangan lalu bayar."
          : "Saldo tidak mencukupi untuk total belanja.";
        status.className =
          "text-sm font-semibold mt-2 " +
          (enough ? "text-green-700" : "text-red-600");
      }
    }
  }

  function csrfToken() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : "";
  }

  function onStableMatch(siswa) {
    var nokartu = String(siswa.nis || "").replace(/\D/g, "");
    if (!nokartu) {
      setStatus("<strong>NIS siswa tidak valid.</strong>", "warn");
      resetPendingMatch();
      return;
    }
    showMatchOverlay(siswa.nama || nokartu);
    setStatus("<strong>Wajah cocok:</strong> " + (siswa.nama || "") + " — cek saldo…", "ok");
    stopCamera();

    fetch(cfg.urls.inquiry, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
      body: JSON.stringify({ nokartu: nokartu }),
    })
      .then(function (res) {
        return res.json().then(function (json) {
          return { res: res, json: json };
        });
      })
      .then(function (pack) {
        var json = pack.json || {};
        if (!pack.res.ok || !json.ok) {
          setStatus(
            "<strong>Inquiry gagal:</strong> " +
              (json.error || "NIS tidak terdaftar"),
            "warn"
          );
          showCameraStep();
          startCamera();
          return;
        }
        showConfirmStep({
          siswa: siswa,
          nokartu: nokartu,
          namaMerchant: json.nama || siswa.nama,
          saldo: json.saldo || 0,
        });
      })
      .catch(function (err) {
        setStatus(
          "<strong>Inquiry error:</strong> " + (err.message || err),
          "warn"
        );
        showCameraStep();
        startCamera();
      });
  }

  function tickDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length || confirmOpen) {
      return;
    }
    var vid = $("faceVideo");
    if (!vid || vid.readyState < 2) return;
    busyDetect = true;
    detectDescriptor(vid, LIVE_MIN_CONFIDENCE)
      .then(function (det) {
        if (!det || !det.detection) {
          resetPendingMatch();
          showMatchOverlay("");
          return;
        }
        if (!faceLargeEnough(det.detection.box, vid)) {
          resetPendingMatch();
          showMatchOverlay("");
          return;
        }
        var m = bestMatch(det.descriptor);
        if (!m || !m.accepted || !m.ref || !m.ref.siswa) {
          resetPendingMatch();
          showMatchOverlay("");
          return;
        }
        showMatchOverlay(m.ref.siswa.nama || "");
        if (confirmStableMatch(m.ref.siswa.id)) {
          onStableMatch(m.ref.siswa);
        }
      })
      .catch(function () {})
      .then(function () {
        busyDetect = false;
      });
  }

  function startCamera() {
    stopCamera();
    showCameraStep();
    setStatus("<strong>Mengaktifkan kamera…</strong>", "");
    return navigator.mediaDevices
      .getUserMedia({
        video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } },
        audio: false,
      })
      .then(function (s) {
        stream = s;
        var vid = $("faceVideo");
        if (!vid) throw new Error("Video element missing");
        vid.srcObject = s;
        return vid.play();
      })
      .then(function () {
        setStatus(
          "<strong>Kamera aktif (ambang 0.38).</strong> Hadapkan wajah siswa.",
          "ok"
        );
        detectTimer = setInterval(tickDetect, DETECT_MS);
      })
      .catch(function (err) {
        setStatus(
          "<strong>Kamera gagal:</strong> " +
            (err && err.message ? err.message : "izin ditolak / tidak tersedia"),
          "warn"
        );
      });
  }

  function openModal() {
    modalOpen = true;
    matched = null;
    confirmOpen = false;
    var modal = $("faceModal");
    if (modal) modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    $("faceTotal").textContent = cfg.formatRupiah(cfg.getGrandTotal());
    var diskEl = $("faceDiskon");
    var disk = cfg.getDiskonTotal ? cfg.getDiskonTotal() : 0;
    if (diskEl) {
      if (disk > 0) {
        diskEl.textContent = "Hemat " + cfg.formatRupiah(disk);
        diskEl.classList.remove("hidden");
      } else {
        diskEl.textContent = "";
        diskEl.classList.add("hidden");
      }
    }
    showCameraStep();
    loadModels()
      .then(function (n) {
        if (n > 0) return startCamera();
      })
      .catch(function () {});
  }

  function closeModal() {
    modalOpen = false;
    confirmOpen = false;
    matched = null;
    stopCamera();
    var modal = $("faceModal");
    if (modal) modal.classList.add("hidden");
    document.body.style.overflow = "auto";
    if (typeof cfg.onClose === "function") cfg.onClose();
  }

  function submitPay() {
    if (!matched || !matched.nokartu) return;
    var ketInput = $("faceKetInput");
    var ket = ketInput ? String(ketInput.value || "").trim() : "";
    ket = ket.replace(/\s+/g, " ").slice(0, KET_MAX).trim();
    if (!ket) {
      if (typeof cfg.showNotification === "function") {
        cfg.showNotification("Keterangan barang wajib diisi", "error");
      }
      if (ketInput) ketInput.focus();
      return;
    }
    if (typeof cfg.submitCheckout === "function") {
      var proc = $("faceProcessing");
      var conf = $("faceConfirmStep");
      if (conf) conf.classList.add("hidden");
      if (proc) proc.classList.remove("hidden");
      cfg.submitCheckout(matched.nokartu, ket);
    }
  }

  function init(config) {
    cfg = config || {};
    var btnClose = $("btnCloseFace");
    if (btnClose) btnClose.addEventListener("click", closeModal);
    var btnPay = $("btnFacePay");
    if (btnPay) btnPay.addEventListener("click", submitPay);
    var btnBack = $("btnFaceBack");
    if (btnBack) {
      btnBack.addEventListener("click", function () {
        showCameraStep();
        startCamera();
      });
    }
  }

  global.KasirFacePay = {
    init: init,
    open: openModal,
    close: closeModal,
    isOpen: function () {
      return modalOpen;
    },
  };
})(window);
