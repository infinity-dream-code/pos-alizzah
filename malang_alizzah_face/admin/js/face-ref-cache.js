/**
 * Cache descriptor wajah di IndexedDB (satu origin: admin + kantin).
 * Kunjungan pertama menghitung; berikutnya hanya siswa baru / foto berubah.
 */
(function (global) {
  "use strict";

  var DB_NAME = "malang_alizzah_face_refs";
  var DB_VERSION = 1;
  var STORE = "descriptors";
  var CACHE_V = 1;
  var ALGO = "aug-v1";
  var FULL_FETCH_MIN = 60;

  function clientFotoFp(foto) {
    var s = String(foto || "");
    var len = s.length;
    if (len < 30) return "";
    var start = Math.max(0, Math.floor(len * 0.35));
    var mix = len + ":" + s.slice(start, start + 96) + ":" + s.slice(-48);
    var h = 2166136261;
    for (var i = 0; i < mix.length; i++) {
      h ^= mix.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return len + ":" + (h >>> 0).toString(16);
  }

  function slimSiswa(s) {
    s = s || {};
    return {
      id: String(s.id || ""),
      nis: s.nis || "",
      nisn: s.nisn || "",
      nama: s.nama || "",
      kelasId: s.kelasId || "",
      jenisKelamin: s.jenisKelamin || "",
      aktif: s.aktif !== false,
      rfidUid: s.rfidUid || "",
    };
  }

  var dbPromise = null;

  function openDb() {
    if (!global.indexedDB) {
      return Promise.reject(new Error("IndexedDB tidak tersedia"));
    }
    if (dbPromise) return dbPromise;
    dbPromise = new Promise(function (resolve, reject) {
      var req = global.indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains(STORE)) {
          db.createObjectStore(STORE, { keyPath: "id" });
        }
      };
      req.onsuccess = function () {
        var db = req.result;
        db.onclose = function () {
          dbPromise = null;
        };
        db.onversionchange = function () {
          db.close();
          dbPromise = null;
        };
        resolve(db);
      };
      req.onerror = function () {
        dbPromise = null;
        reject(req.error || new Error("Gagal membuka cache wajah"));
      };
    });
    return dbPromise;
  }

  function withStore(mode, fn) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, mode);
        var store = tx.objectStore(STORE);
        var done = false;
        tx.oncomplete = function () {
          if (!done) resolve(undefined);
        };
        tx.onerror = function () {
          reject(tx.error || new Error("Transaksi cache gagal"));
        };
        try {
          var out = fn(store);
          if (out && typeof out.then === "function") {
            out.then(function (val) {
              done = true;
              resolve(val);
            }, reject);
          } else if (out && typeof out.onsuccess !== "undefined") {
            out.onsuccess = function () {
              done = true;
              resolve(out.result);
            };
            out.onerror = function () {
              reject(out.error);
            };
          } else if (out !== undefined) {
            done = true;
            resolve(out);
          }
        } catch (e) {
          reject(e);
        }
      });
    });
  }

  function getAllMap() {
    return withStore("readonly", function (store) {
      return store.getAll();
    })
      .then(function (rows) {
        var map = {};
        (rows || []).forEach(function (row) {
          if (row && row.id) map[String(row.id)] = row;
        });
        return map;
      })
      .catch(function () {
        return {};
      });
  }

  function putEntry(entry) {
    if (!entry || !entry.id) return Promise.resolve();
    return withStore("readwrite", function (store) {
      return store.put(entry);
    }).catch(function (e) {
      console.warn("[face-cache] simpan", e);
    });
  }

  function removeIds(ids) {
    var list = (ids || []).map(String).filter(Boolean);
    if (!list.length) return Promise.resolve();
    return withStore("readwrite", function (store) {
      list.forEach(function (id) {
        store.delete(id);
      });
    }).catch(function (e) {
      console.warn("[face-cache] hapus", e);
    });
  }

  function clearAll() {
    return withStore("readwrite", function (store) {
      return store.clear();
    }).catch(function (e) {
      console.warn("[face-cache] clear", e);
    });
  }

  function cacheOk(entry, fotoFp) {
    return Boolean(
      entry &&
        entry.v === CACHE_V &&
        entry.algo === ALGO &&
        entry.descriptors &&
        entry.descriptors.length &&
        ((fotoFp && entry.fotoFp === fotoFp) || (!fotoFp && entry.fotoFp))
    );
  }

  function countDesc(rows) {
    var n = 0;
    (rows || []).forEach(function (r) {
      n += (r.descriptors && r.descriptors.length) || 0;
    });
    return n;
  }

  /**
   * @param {{
   *   force?: boolean,
   *   setStatus?: function,
   *   loadIndex: function(): Promise<Array>,
   *   loadFotos: function(string[]): Promise<Array>,
   *   loadAllFotos: function(): Promise<Array>,
   *   indexOne: function(object): Promise<{foto:number, descriptors:number[][]}>,
   *   applyCached: function({siswa:object, descriptors:number[][]})
   * }} opts
   */
  function hydrate(opts) {
    var setStatus = opts.setStatus || function () {};
    var force = Boolean(opts.force);
    var boot = force ? clearAll() : Promise.resolve();

    return boot
      .then(function () {
        return Promise.all([opts.loadIndex(), getAllMap()]);
      })
      .then(function (pair) {
        var index = (pair[0] || []).filter(function (s) {
          return s && s.id && s.aktif !== false && (s.hasFoto || s.fotoFp || s.fotoWajah);
        });
        var cached = pair[1] || {};
        var reuse = [];
        var need = [];
        var keep = {};

        index.forEach(function (s) {
          var id = String(s.id);
          keep[id] = true;
          var fp = s.fotoFp || (s.fotoWajah ? clientFotoFp(s.fotoWajah) : "");
          var hit = cached[id];
          if (cacheOk(hit, fp)) {
            reuse.push({
              siswa: Object.assign(slimSiswa(hit.siswa), slimSiswa(s)),
              descriptors: hit.descriptors,
              fotoFp: fp || hit.fotoFp,
            });
          } else {
            need.push(s);
          }
        });

        var stale = [];
        Object.keys(cached).forEach(function (id) {
          if (!keep[id]) stale.push(id);
        });

        reuse.forEach(function (row) {
          opts.applyCached(row);
        });

        var prune = stale.length ? removeIds(stale) : Promise.resolve();

        if (!need.length) {
          return prune.then(function () {
            var foto = countDesc(reuse);
            setStatus(
              "<strong>Referensi siap dari cache:</strong> " +
                reuse.length +
                " siswa, " +
                foto +
                " descriptor. Tidak perlu unduh ulang foto.",
              reuse.length ? "ok" : "warn"
            );
            return {
              okSiswa: reuse.length,
              okFoto: foto,
              fromCache: reuse.length,
              computed: 0,
              fail: 0,
            };
          });
        }

        setStatus(
          "<strong>Cache " +
            reuse.length +
            " siswa.</strong> Menghitung " +
            need.length +
            " wajah baru / foto berubah…",
          ""
        );

        var useFull = need.length >= FULL_FETCH_MIN || need.length > index.length * 0.35;
        var loadFotos = useFull
          ? opts.loadAllFotos()
          : opts.loadFotos(
              need.map(function (s) {
                return String(s.id);
              })
            );

        return prune.then(function () {
          return loadFotos;
        }).then(function (rows) {
          var byId = {};
          (rows || []).forEach(function (r) {
            if (r && r.id) byId[String(r.id)] = r;
          });

          var fail = 0;
          var okSiswa = 0;
          var okFoto = 0;
          var computed = 0;
          var done = 0;
          var chain = Promise.resolve();

          need.forEach(function (meta) {
            chain = chain.then(function () {
              var siswa = byId[String(meta.id)] || meta;
              if (!siswa.fotoWajah && !siswa.hasFoto) {
                fail++;
                done++;
                return;
              }
              return opts
                .indexOne(siswa)
                .then(function (res) {
                  done++;
                  var n = res && res.foto ? res.foto : 0;
                  var desc = (res && res.descriptors) || [];
                  if (desc.length) {
                    okSiswa++;
                    okFoto += n;
                    computed++;
                    var fp =
                      siswa.fotoFp || meta.fotoFp || clientFotoFp(siswa.fotoWajah);
                    return putEntry({
                      v: CACHE_V,
                      algo: ALGO,
                      id: String(siswa.id),
                      fotoFp: fp,
                      siswa: slimSiswa(siswa),
                      descriptors: desc,
                    });
                  }
                  fail++;
                })
                .catch(function () {
                  fail++;
                  done++;
                })
                .then(function () {
                  if (done % 4 === 0 || done === need.length) {
                    setStatus(
                      "<strong>Menghitung wajah " +
                        done +
                        "/" +
                        need.length +
                        "</strong> (cache " +
                        reuse.length +
                        ")…",
                      ""
                    );
                  }
                });
            });
          });

          return chain.then(function () {
            var total = reuse.length + okSiswa;
            var parts = [
              "<strong>Referensi siap:</strong> " +
                total +
                " siswa (" +
                reuse.length +
                " dari cache, " +
                computed +
                " dihitung).",
            ];
            if (fail) parts.push(" " + fail + " siswa fotonya tidak terbaca.");
            setStatus(parts.join(""), total ? "ok" : "warn");
            return {
              okSiswa: total,
              okFoto: okFoto + countDesc(reuse),
              fromCache: reuse.length,
              computed: computed,
              fail: fail,
            };
          });
        });
      });
  }

  global.FaceRefCache = {
    VERSION: CACHE_V,
    ALGO: ALGO,
    clientFotoFp: clientFotoFp,
    slimSiswa: slimSiswa,
    hydrate: hydrate,
    clear: clearAll,
    getAll: getAllMap,
  };
})(typeof window !== "undefined" ? window : globalThis);
