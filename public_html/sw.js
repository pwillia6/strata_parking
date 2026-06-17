
const VERSION = '2.5';
const CACHE_NAME = 'parking-app-cache-v2';
const urlsToCache = [
  '/',
  '/index.html',
  '/manifest.json',
  '/click.mp3',
  '/bell.mp3',
  'https://unpkg.com/dexie@3/dist/dexie.js'
];

self.addEventListener('install', event => {
  // Perform install steps
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        // addAll() is atomic. If one file fails, the whole cache operation fails.
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting()) // Force the waiting service worker to become the active service worker.
  );
});

self.addEventListener('fetch', event => {
    // For navigation requests, use a network-first strategy.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
        return;
    }

    // For other requests (CSS, JS, images), use a cache-first strategy.
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request).then(fetchResponse => {
                // Optional: Cache new assets as they are fetched.
                return caches.open(CACHE_NAME).then(cache => {
                    // Be careful not to cache failed responses or API calls this way.
                    // Only cache GET requests with http/https schemes.
                    if (fetchResponse.ok && event.request.method === 'GET' && event.request.url.startsWith('http')) {
                       // Clone the response because it's a one-time-use stream.
                       cache.put(event.request, fetchResponse.clone());
                    }
                    return fetchResponse;
                });
            });
        }).catch(error => {
            // This catch is for cases where both cache and network fail.
            // You could return a generic fallback page here if you had one.
            console.error('Fetch failed:', error);
        })
    );
});

self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      )
    }).then(() => self.clients.claim()) // Take control of all clients as soon as the SW is activated.
  );
});

self.addEventListener('message', event => {
  if (event.data && event.data.action === 'GET_VERSION') {
    // The service worker responds to the client that sent the message
    // with its version number.
    event.source.postMessage({ version: VERSION });
  }
});