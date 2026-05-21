// Minimal service worker for PWA installability on Chrome.
// Chrome requires a registered SW with a `fetch` listener for the install
// prompt to surface. This worker intentionally does NO caching — it just
// passes requests through to the network so Inertia routing, CSRF, and
// freshness all behave exactly like a normal page load.
//
// If we later want offline support, swap the fetch listener for a
// cache-first strategy on hashed Vite assets only (NOT on HTML, since
// Inertia depends on fresh server responses).

self.addEventListener('install', () => {
  // Become the active worker immediately on first install.
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Take control of open clients so the page that registered us is governed
  // by this SW without a reload.
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Passthrough — let the browser do its normal thing. Required by Chrome's
  // installability checks even though we're not caching anything.
  event.respondWith(fetch(event.request));
});
