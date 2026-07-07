
const VERSION = '3.1';
const CACHE_NAME = 'parking-app-cache-v3';

// Dexie is needed here (not just in index.html) so background sync can read
// the same IndexedDB queue while no page is open. Vendored locally (not loaded
// from unpkg) because Chrome disallows importScripts() on a redirected URL,
// and unpkg's /dexie@3/... path always issues a redirect.
importScripts('/dexie.js');
const urlsToCache = [
  '/',
  '/index.html',
  '/manifest.json',
  '/click.mp3',
  '/bell.mp3',
  '/dexie.js'
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

// --- Background Sync ---
// Lets pending photos upload even if the app/tab is closed or backgrounded,
// as soon as the OS tells the browser connectivity is back. Registered from
// index.html via `registration.sync.register('upload-photos')`.
// Note: not supported on iOS Safari (no Background Sync API there) - those
// devices fall back to the foreground online/ping logic in index.html.

const SYNC_TAG = 'upload-photos';
const CLAIM_STALE_MS = 2 * 60 * 1000; // matches index.html; treat an abandoned claim as free

function getUploadsDb() {
  const db = new Dexie('ParkingAppDatabase');
  db.version(1).stores({ pending_uploads: '++id, phototime' });
  return db;
}

// Same claim/release scheme as index.html, so the page and the service
// worker never upload (and thus duplicate) the same queued photo at once.
async function claimUpload(db, id) {
  return db.transaction('rw', db.pending_uploads, async () => {
    const row = await db.pending_uploads.get(id);
    if (!row) return null;
    if (row.claimed && (Date.now() - row.claimed) < CLAIM_STALE_MS) return null;
    await db.pending_uploads.update(id, { claimed: Date.now() });
    return row;
  });
}

async function releaseClaim(db, id) {
  await db.pending_uploads.update(id, { claimed: null });
}

async function syncPendingUploads() {
  const db = getUploadsDb();
  const rows = await db.pending_uploads.toArray();
  let anyFailed = false;

  for (const row of rows) {
    const claimed = await claimUpload(db, row.id);
    if (!claimed) continue; // being handled elsewhere (e.g. an open tab), or already gone

    try {
      const formData = new FormData();
      formData.append('photo', claimed.photoBlob, 'photo.jpg');
      formData.append('phototime', claimed.phototime);
      formData.append('uuid', claimed.uuid || '');

      const response = await fetch(claimed.api, { method: 'POST', body: formData });
      if (!response.ok) {
        throw new Error(`Server error ${response.status}`);
      }
      await db.pending_uploads.delete(claimed.id);
    } catch (err) {
      console.error('Background sync upload failed for photo', row.id, err);
      await releaseClaim(db, row.id);
      anyFailed = true;
    }
  }

  // Tell any open pages to refresh their pending count / button state.
  const clients = await self.clients.matchAll();
  clients.forEach(client => client.postMessage({ action: 'UPLOADS_CHANGED' }));

  if (anyFailed) {
    // Throwing tells the browser this sync attempt failed so it reschedules
    // the tag with its own backoff, instead of us reimplementing retry here.
    throw new Error('One or more background uploads failed; will retry.');
  }
}

self.addEventListener('sync', event => {
  if (event.tag === SYNC_TAG) {
    event.waitUntil(syncPendingUploads());
  }
});