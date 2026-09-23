const CACHE_NAME = "laravel-pwa-v4-auth-network-only";
const OFFLINE_URL = "/offline.html";

/** Auth nunca se cachea. El navegador (o navigation preload) va directo a red. */
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
    return AUTH_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(prefix + "/"));
}

function isStaticAsset(request) {
    return (
        request.destination === "style" ||
        request.destination === "script" ||
        request.destination === "image" ||
        request.destination === "font"
    );
}

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(FILES_TO_CACHE))
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil((async () => {
        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable();
        }
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)));
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.keys();
        await Promise.all(cached.map((request) => {
            try {
                if (isAuthPath(new URL(request.url).pathname)) {
                    return cache.delete(request);
                }
            } catch (e) {}
            return Promise.resolve();
        }));
    })());
    self.clients.claim();
});

self.addEventListener("message", (event) => {
    if (event.data && event.data.type === "SKIP_WAITING") {
        self.skipWaiting();
    }
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    let url;
    try {
        url = new URL(request.url);
    } catch (e) {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isAuthPath(url.pathname)) {
        if (!event.preloadResponse) {
            return;
        }
        event.respondWith((async () => {
            try {
                const preloaded = await Promise.race([
                    event.preloadResponse,
                    new Promise((resolve) => setTimeout(() => resolve(undefined), 400)),
                ]);
                if (preloaded) {
                    return preloaded;
                }
            } catch (e) {}
            return fetch(request, { cache: "no-store", credentials: "same-origin" });
        })());
        return;
    }

    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

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
