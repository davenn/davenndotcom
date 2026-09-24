const CACHE = 'davenn-v18';
const SHELL = [
  '/index.html',
  '/tracktimer.html',
  '/meetingtimer.html',
  '/toolshare.html',
  '/facebreaker.html',
  '/pomodoro.html',
  '/pomodoro-cast.html',
  '/reactiontest.html',
  '/flighttracker.html',
  '/dailytasks.html',
  '/signspotter.html',
  '/cribbage.html',
  '/glucose.html',
  '/bgcast.html',
  '/nflpool.html',
  '/notify.html',
  // Real file paths only. addAll() rejects as a whole if any single entry
  // fails, which would leave every app above uncached — so never list a
  // directory URL like '/docs/' here and rely on the server's index rule.
  '/docs/index.html',
  '/docs/architecture.html',
  '/docs/api.html',
  '/docs/glucose-api.html',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  if (e.request.url.includes('/api.php')) return;

  // Network-first for HTML — always get fresh code, fall back to cache if offline
  if (e.request.destination === 'document') {
    e.respondWith(
      fetch(e.request)
        .then(res => {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
          return res;
        })
        .catch(() => caches.match(e.request))
    );
    return;
  }

  // Cache-first for everything else (fonts, icons, etc.)
  e.respondWith(
    caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
      if (res && res.status === 200 && e.request.method === 'GET') {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone));
      }
      return res;
    }))
  );
});
