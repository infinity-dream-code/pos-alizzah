/**
 * Webcam helper — depan (user) / belakang (environment).
 * Dipakai admin & kantin lewat /admin/js/presensi-camera.js
 */
(function (g) {
  "use strict";

  var currentStream = null;
  var currentFacing = "user";

  function stopTracks(stream) {
    if (!stream) return;
    try {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
    } catch (e) {}
  }

  function stopCurrent() {
    stopTracks(currentStream);
    currentStream = null;
  }

  function constraintsFor(facing) {
    var mode = facing === "environment" ? "environment" : "user";
    return {
      audio: false,
      video: {
        facingMode: { ideal: mode },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    };
  }

  /**
   * @param {HTMLVideoElement} videoEl
   * @param {{ facingMode?: 'user'|'environment' }} [opts]
   * @returns {Promise<MediaStream>}
   */
  g.presensiOpenWebcam = function (videoEl, opts) {
    opts = opts || {};
    if (opts.facingMode === "environment" || opts.facingMode === "user") {
      currentFacing = opts.facingMode;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      return Promise.reject(new Error("Kamera tidak didukung di browser ini."));
    }
    if (!videoEl) {
      return Promise.reject(new Error("Elemen video tidak ditemukan."));
    }

    stopCurrent();

    return navigator.mediaDevices
      .getUserMedia(constraintsFor(currentFacing))
      .then(function (stream) {
        currentStream = stream;
        videoEl.srcObject = stream;
        videoEl.setAttribute("playsinline", "true");
        videoEl.muted = true;
        var p = videoEl.play();
        if (p && typeof p.catch === "function") {
          p.catch(function () {});
        }
        return stream;
      })
      .catch(function (err) {
        // Fallback tanpa ideal facing (beberapa desktop/browser)
        return navigator.mediaDevices
          .getUserMedia({ audio: false, video: true })
          .then(function (stream) {
            currentStream = stream;
            videoEl.srcObject = stream;
            videoEl.setAttribute("playsinline", "true");
            videoEl.muted = true;
            var p = videoEl.play();
            if (p && typeof p.catch === "function") {
              p.catch(function () {});
            }
            return stream;
          })
          .catch(function () {
            throw err;
          });
      });
  };

  /** Tutup stream aktif. */
  g.presensiStopWebcam = function (videoEl) {
    stopCurrent();
    if (videoEl) {
      try {
        videoEl.srcObject = null;
      } catch (e) {}
    }
  };

  /** Mode saat ini: 'user' | 'environment' */
  g.presensiGetFacingMode = function () {
    return currentFacing;
  };

  /**
   * Tukar kamera depan ↔ belakang, stream ulang ke video yang sama.
   * @param {HTMLVideoElement} videoEl
   * @returns {Promise<MediaStream>}
   */
  g.presensiSwitchCamera = function (videoEl) {
    currentFacing = currentFacing === "user" ? "environment" : "user";
    return g.presensiOpenWebcam(videoEl, { facingMode: currentFacing });
  };

  /**
   * Set mode lalu buka ulang.
   * @param {HTMLVideoElement} videoEl
   * @param {'user'|'environment'} facing
   */
  g.presensiSetFacingMode = function (videoEl, facing) {
    currentFacing = facing === "environment" ? "environment" : "user";
    return g.presensiOpenWebcam(videoEl, { facingMode: currentFacing });
  };

  /** Label tombol berdasarkan mode aktif. */
  g.presensiFacingLabel = function () {
    return currentFacing === "environment" ? "Kamera belakang" : "Kamera depan";
  };
})(typeof window !== "undefined" ? window : this);
