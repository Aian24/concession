// Concession System - Service Worker
// Enables PWA install + offline shell

const CACHE_NAME = 'concession-v1';
const OFFLINE_URL = 'offline.html';

// Assets to cache for the app shell (keep lightweight)
const PRECACHE_ASSETS = [
    './',
    'images/concession.png',
    'images/concessiontab.png',
    'images/icon-192.png',
    'images/icon-512.png',
    'assets/css/app.css',
    'offline.html'
];

// Install: cache the app shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch: network-first strategy (always try network, fall back to cache)
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip API calls and form submissions - always go to network
    const url = new URL(event.request.url);
    if (url.pathname.includes('/api/') || url.search.includes('ajax=1')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Cache successful responses for static assets
                if (response.ok && (
                    url.pathname.endsWith('.css') || 
                    url.pathname.endsWith('.js') || 
                    url.pathname.endsWith('.png') || 
                    url.pathname.endsWith('.jpg')
                )) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => {
                // If network fails and we have a cached version, use it
                return caches.match(event.request).then((cached) => {
                    if (cached) return cached;
                    // For navigation requests, show offline page
                    if (event.request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                });
            })
    );
});
