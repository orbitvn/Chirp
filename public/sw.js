/*
 * Chirp Maps service worker — offline drive mode.
 *
 * Caches the app shell, self-hosted map libraries, icons and the RAMM offline
 * bundle so /maps loads and drive mode works with zero coverage. Cross-origin
 * requests (map tiles, HDC ArcGIS) are left to the network; the page's own
 * IndexedDB fallback covers the RAMM API when offline.
 *
 * Bump VERSION whenever the precache list or caching logic changes.
 */
const VERSION = 'v2';
const SHELL = 'chirp-shell-' + VERSION;
const DATA  = 'chirp-data-'  + VERSION;

const PRECACHE = [
  '/vendor/leaflet/leaflet.js',
  '/vendor/leaflet/leaflet.css',
  '/vendor/leaflet/images/marker-icon.png',
  '/vendor/leaflet/images/marker-icon-2x.png',
  '/vendor/leaflet/images/marker-shadow.png',
  '/vendor/leaflet/images/layers.png',
  '/vendor/leaflet/images/layers-2x.png',
  '/vendor/geoman/leaflet-geoman.min.js',
  '/vendor/geoman/leaflet-geoman.css',
  '/vendor/turf/turf.min.js',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL);
    // Don't let one missing asset abort the whole install.
    await Promise.allSettled(PRECACHE.map((u) => cache.add(u)));
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k !== SHELL && k !== DATA).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

// One bundle per council: /data/ramm-offline-<council>.json (+ .version.json).
const isBundle = (url) => /\/data\/ramm-offline(-[a-z0-9_-]+)?(\.version)?\.json$/.test(url.pathname);

const isStaticAsset = (url) =>
  /\/(vendor|icons)\//.test(url.pathname) ||
  /\.(?:js|css|png|jpg|jpeg|svg|webp|woff2?)$/.test(url.pathname) ||
  url.pathname.endsWith('/manifest.webmanifest');

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;  // tiles / ArcGIS -> network

  // The /maps page: network-first so it stays fresh, cached copy when offline.
  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const res = await fetch(req);
        const cache = await caches.open(SHELL);
        cache.put(req, res.clone());
        return res;
      } catch (e) {
        return (await caches.match(req)) || (await caches.match('/maps')) ||
               new Response('Offline and no cached page yet.', { status: 503 });
      }
    })());
    return;
  }

  // RAMM offline bundle: stale-while-revalidate.
  if (isBundle(url)) {
    event.respondWith((async () => {
      const cache = await caches.open(DATA);
      const cached = await cache.match(req);
      const network = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => null);
      return cached || (await network) || new Response('{}', { headers: { 'Content-Type': 'application/json' } });
    })());
    return;
  }

  // Static assets: cache-first.
  if (isStaticAsset(url)) {
    event.respondWith((async () => {
      const cached = await caches.match(req);
      if (cached) return cached;
      try {
        const res = await fetch(req);
        if (res.ok) { const cache = await caches.open(SHELL); cache.put(req, res.clone()); }
        return res;
      } catch (e) {
        return cached || Response.error();
      }
    })());
    return;
  }

  // Everything else (e.g. /ramm/* API) -> default network; app handles offline via IndexedDB.
});
