const STATIC_CACHE = 'tarweaa-static-v1';
const PRECACHE_URLS = [
    '/manifest.json',
    '/icons/app-icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
    '/favicon.ico',
];

const SENSITIVE_PREFIXES = [
    '/admin',
    '/api',
    '/broadcasting',
    '/counter',
    '/counter-screen',
    '/kitchen',
    '/login',
    '/portal',
    '/pos',
    '/sanctum',
];

const STATIC_PREFIXES = [
    '/build/',
    '/css/',
    '/fonts/',
    '/icons/',
    '/js/',
];

self.addEventListener('install', (event) => {
    event.waitUntil(precacheStaticAssets());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(removeOldCaches());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        return;
    }

    if (isSensitivePath(url.pathname)) {
        return;
    }

    if (!isStaticAssetRequest(request, url.pathname)) {
        return;
    }

    event.respondWith(cacheFirst(request));
});

async function precacheStaticAssets() {
    const cache = await caches.open(STATIC_CACHE);

    await Promise.allSettled(
        PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' }))),
    );
}

async function removeOldCaches() {
    const cacheNames = await caches.keys();

    await Promise.all(
        cacheNames
            .filter((cacheName) => cacheName.startsWith('tarweaa-static-') && cacheName !== STATIC_CACHE)
            .map((cacheName) => caches.delete(cacheName)),
    );
}

function isSensitivePath(pathname) {
    return SENSITIVE_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

function isStaticAssetRequest(request, pathname) {
    if (pathname === '/manifest.json' || pathname === '/favicon.ico') {
        return true;
    }

    return STATIC_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        refreshInBackground(cache, request);

        return cachedResponse;
    }

    const networkResponse = await fetch(request);

    if (isCacheableResponse(networkResponse)) {
        cache.put(request, networkResponse.clone());
    }

    return networkResponse;
}

function refreshInBackground(cache, request) {
    fetch(request)
        .then((response) => {
            if (isCacheableResponse(response)) {
                return cache.put(request, response.clone());
            }
        })
        .catch(() => {});
}

function isCacheableResponse(response) {
    return response.type === 'basic' && response.status === 200;
}
