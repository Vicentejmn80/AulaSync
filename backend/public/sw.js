const CACHE_NAME = "laravel-pwa-v3-no-auth-cache";
const OFFLINE_URL = "/offline.html";

/** Rutas que el SW NO debe interceptar (auth + CSRF). */
const AUTH_PREFIXES = [
    "/login",
    "/register",
    "/forgot-password",
    "/reset-password",
    "/onboarding",
    "/logout",
    "/sanctum",
];

const FILES_TO_CACHE = [OFFLINE_URL];

function isAuthPath(pathname) {
    return AUTH_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

function isStaticAsset(request) {
    return (
        request.destination === "style" ||
        request.destination === "script" ||
        request.destination === "image" ||
        request.destination === "font"
    );
}

// Pre-cache solo offline page (nunca cachear "/" ni login)
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(FILES_TO_CACHE))
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            )
        )
    );
    self.clients.claim();
});

self.addEventListener("message", (event) => {
    if (event.data && event.data.type === "SKIP_WAITING") {
        self.skipWaiting();
    }
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (url.protocol !== "http:" && url.protocol !== "https:") {
        return;
    }

    // POST/PUT/DELETE: dejar que el navegador maneje directo (login, forms, CSRF)
    if (request.method !== "GET") {
        return;
    }

    // Auth pages: NO interceptar — evita Failed to fetch y CSRF stale
    if (url.origin === self.location.origin && isAuthPath(url.pathname)) {
        return;
    }

    // Navegación: red primero, fallback offline (sin cachear HTML)
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Assets estáticos: cache-first
    if (isStaticAsset(request)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Todo lo demás: red directa, sin guardar en cache
    event.respondWith(fetch(request));
});

self.addEventListener("sync", (event) => {
    if (event.tag === "laravel-pwa-sync") {
        event.waitUntil(syncRequests());
    }
});

async function syncRequests() {
    const db = await openDB();
    const tx = db.transaction("offline-requests", "readonly");
    const store = tx.objectStore("offline-requests");
    const requests = await getAllRequests(store);

    for (const req of requests) {
        try {
            const response = await fetch(req.url, {
                method: req.method,
                headers: req.headers,
                body: req.body,
            });

            if (response.ok) {
                const deleteTx = db.transaction("offline-requests", "readwrite");
                deleteTx.objectStore("offline-requests").delete(req.id);
            }
        } catch (err) {
            console.error("[Laravel PWA] Sync failed for:", req.url, err);
        }
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open("laravel-pwa-sync", 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getAllRequests(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}
