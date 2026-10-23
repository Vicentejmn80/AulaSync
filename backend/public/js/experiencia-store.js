(function (global) {
    var dbPromise = null;

    function dayKey(tabletId) {
        var now = new Date();
        var day = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0") + "-" + String(now.getDate()).padStart(2, "0");
        return "experiencia-cupos-" + tabletId + "-" + day;
    }

    function readQuotas(tabletId) {
        try {
            return JSON.parse(localStorage.getItem(dayKey(tabletId))) || {};
        } catch (error) {
            return {};
        }
    }

    function writeQuotas(tabletId, data) {
        localStorage.setItem(dayKey(tabletId), JSON.stringify(data));
    }

    function db() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise(function (resolve, reject) {
            var request = indexedDB.open("experiencia-kiosk", 1);
            request.onupgradeneeded = function () {
                var database = request.result;
                if (!database.objectStoreNames.contains("leads")) {
                    database.createObjectStore("leads", { keyPath: "id" });
                }
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
        return dbPromise;
    }

    function all() {
        return db().then(function (database) {
            return new Promise(function (resolve, reject) {
                var request = database.transaction("leads", "readonly").objectStore("leads").getAll();
                request.onsuccess = function () { resolve(request.result || []); };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    function put(record) {
        return db().then(function (database) {
            return new Promise(function (resolve, reject) {
                var request = database.transaction("leads", "readwrite").objectStore("leads").put(record);
                request.onsuccess = function () { resolve(record); };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    global.AulaStore = {
        used: function (tabletId, quotaKey) {
            return readQuotas(tabletId)[quotaKey] || 0;
        },
        reserve: function (tabletId, quotaKey) {
            if (!quotaKey) return;
            var data = readQuotas(tabletId);
            data[quotaKey] = (data[quotaKey] || 0) + 1;
            writeQuotas(tabletId, data);
        },
        release: function (tabletId, quotaKey) {
            if (!quotaKey) return;
            var data = readQuotas(tabletId);
            data[quotaKey] = Math.max(0, (data[quotaKey] || 0) - 1);
            writeQuotas(tabletId, data);
        },
        saveLead: function (record) {
            record.sync = "pending";
            return put(record);
        },
        all: all,
        pending: function () {
            return all().then(function (rows) {
                return rows.filter(function (row) { return row.sync !== "synced"; }).length;
            });
        },
        flush: function (webhookUrl) {
            if (!webhookUrl) return Promise.resolve();
            return all().then(function (rows) {
                var pending = rows.filter(function (row) { return row.sync !== "synced"; });
                return pending.reduce(function (chain, row) {
                    return chain.then(function () {
                        return fetch(webhookUrl, {
                            method: "POST",
                            mode: "cors",
                            headers: { "Content-Type": "text/plain;charset=utf-8" },
                            body: JSON.stringify(row)
                        }).then(function (response) {
                            if (!response.ok) throw new Error("webhook");
                            row.sync = "synced";
                            return put(row);
                        });
                    });
                }, Promise.resolve());
            }).catch(function () {});
        },
        toCsv: function () {
            return all().then(function (rows) {
                var headers = ["createdAt", "tabletId", "rol", "nombre", "colegio", "whatsapp", "puntaje", "premio", "codigo", "consentimiento", "reto_principal", "sync"];
                var lines = [headers.join(",")];
                rows.forEach(function (row) {
                    lines.push(headers.map(function (key) {
                        var value = row[key] == null ? "" : String(row[key]);
                        return '"' + value.replace(/"/g, '""') + '"';
                    }).join(","));
                });
                return lines.join("\n");
            });
        }
    };
})(window);
