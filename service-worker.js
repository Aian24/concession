// Concession System - Service Worker
// Enables PWA install + offline shell

const CACHE_NAME = 'concession-v1';
const OFFLINE_URL = 'offline.html';

// Assets to cache for the app shell (keep lightweight)
const PRECACHE_ASSETS = [
    './',
    'assets/images/concession.webp',
    'assets/images/concessiontab.webp',
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

// Fetch: optimized caching strategy
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    
    // Skip API calls and form submissions - always go to network
    if (url.pathname.includes('/api/') || url.search.includes('ajax=1')) return;

    // Cache-First strategy for static assets
    const isStaticAsset = url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff2|woff|ttf|webp)$/i) || 
                          url.hostname === 'cdn.tailwindcss.com' ||
                          url.hostname === 'cdnjs.cloudflare.com' ||
                          url.hostname === 'cdn.sheetjs.com' ||
                          url.hostname === 'cdn.jsdelivr.net';

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached; // Return from cache if available

                return fetch(event.request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                }).catch(() => {
                    // Ignore errors for optional assets
                });
            })
        );
        return;
    }

    // Network-First for HTML/Navigation
    event.respondWith(
        fetch(event.request).then((response) => {
            if (response.ok) {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
            }
            return response;
        }).catch(() => {
            return caches.match(event.request).then((cached) => {
                if (cached) return cached;
                if (event.request.mode === 'navigate') {
                    return caches.match(OFFLINE_URL);
                }
            });
        })
    );
});
