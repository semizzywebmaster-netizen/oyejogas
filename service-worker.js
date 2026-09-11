/* Oyejo Gas - service worker (Phase 27, PW-04…PW-06, PW-09).
 * Keep VERSION in sync with OYEJO_VERSION (E2E asserts this).
 * Security: only same-origin GETs are ever cached; admin, customer,
 * driver, api, install and cron traffic always bypasses the cache. */
var VERSION = '0.30.0';
var STATIC_CACHE = 'oyejo-static-' + VERSION;
var PRECACHE = [
  './',
  './index.php',
  './offline.php',
  './assets/css/style.css?v=' + VERSION,
  './assets/js/app.js?v=' + VERSION,
  './assets/images/logo.svg?v=' + VERSION,
  './assets/icons/icon-192.png?v=' + VERSION,
  './assets/icons/icon-512.png?v=' + VERSION,
  './assets/icons/maskable-192.png?v=' + VERSION,
  './assets/icons/maskable-512.png?v=' + VERSION
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(function (cache) {
      return cache.addAll(PRECACHE);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) {
        if (k.indexOf('oyejo-static-') === 0 && k !== STATIC_CACHE) {
          return caches.delete(k);
        }
        return Promise.resolve();
      }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

function cacheable(req) {
  if (req.method !== 'GET') {
    return false;
  }
  var u;
  try {
    u = new URL(req.url);
  } catch (e) {
    return false;
  }
  if (u.origin !== self.location.origin) {
    return false;
  }
  var p = u.pathname;
  // Private or state-changing areas: never cache (SC-15).
  if (/^\/(admin|customer|driver|api|install|cron)(\/|$)/.test(p)) {
    return false;
  }
  if (/(checkout|cart)(\.php)?$/.test(p)) {
    return false;
  }
  return true;
}

self.addEventListener('fetch', function (event) {
  var req = event.request;
  if (!cacheable(req)) {
    return; // network only, no caching
  }
  var path = new URL(req.url).pathname;
  if (path.indexOf('/assets/') !== -1) {
    // Static assets: cache-first, refresh in background.
    event.respondWith(
      caches.match(req).then(function (hit) {
        var fresh = fetch(req).then(function (res) {
          if (res && res.ok) {
            var copy = res.clone();
            caches.open(STATIC_CACHE).then(function (c) { c.put(req, copy); });
          }
          return res;
        }).catch(function () { return hit; });
        return hit || fresh;
      })
    );
    return;
  }
  if (req.mode === 'navigate') {
    // Public pages: network-first, offline fallback.
    event.respondWith(
      fetch(req).catch(function () {
        return caches.match('./offline.php');
      })
    );
  }
  // Other cacheable GETs (e.g. manifest): network only.
});

self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { body: event.data ? event.data.text() : '' };
  }
  var title = data.title || 'Oyejo Gas';
  event.waitUntil(
    self.registration.showNotification(title, {
      body: data.body || '',
      icon: './assets/icons/icon-192.png',
      badge: './assets/icons/icon-192.png',
      data: { url: data.url || './customer/notifications.php' }
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = (event.notification.data && event.notification.data.url) || './';
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].url === target && 'focus' in list[i]) {
          return list[i].focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(target);
      }
      return null;
    })
  );
});
