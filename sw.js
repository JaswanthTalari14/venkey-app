const CACHE_NAME = 'medicalak-pwa-v19';

const STATIC_ASSETS = [
    './',
    'index.php',
    'login.php',
    'register.php',
    'customer_support.php',
    'create_ticket.php',
    'my_tickets.php',
    'ticket_view.php',
    'admin_support.php',
    'admin_whatsapp_settings.php',
    'digital_receipt.php',
    'admin_approval_center.php',
    'admin_search.php',
    'admin_audit.php',
    'digital_medical_card.php',
    'download_medical_card.php',
    'admin_digital_cards.php',
    'offline.html',
    'css/style.css',
    'manifest.json',
    'images/icons/icon-192.png',
    'images/icons/icon-512.png',
    'images/icons/apple-touch-icon.png',
    'images/icons/maskable-icon-512.png',
    'images/medicines/default.png',
    'images/medicines/paracetamol.png',
    'images/medicines/amoxicillin.png',
    'images/medicines/cetirizine.png',
    'images/medicines/vitaminc.png'
];

// Install Event: Cache Static App Shell
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('PWA Asset Caching warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate Event: Clean Old Caches & Claim Clients
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Network-First for Dynamic/PHP/API Data, Cache-First for Static Assets
self.addEventListener('fetch', (e) => {
    const req = e.request;
    const url = new URL(req.url);

    // Always bypass cache for non-GET requests (POST, PUT, DELETE)
    if (req.method !== 'GET') {
        return;
    }

    // Bypass cache for sensitive, financial, payment, dynamic PHP endpoints & SSE streams
    const isDynamic = url.pathname.endsWith('.php') || 
                      url.pathname.includes('api_') || 
                      url.search.includes('action=stream') ||
                      url.pathname.includes('login') || 
                      url.pathname.includes('register') || 
                      url.pathname.includes('verify') || 
                      url.pathname.includes('pay') || 
                      url.pathname.includes('order') || 
                      url.pathname.includes('wallet') || 
                      url.pathname.includes('referral');

    if (isDynamic) {
        e.respondWith(
            fetch(req).catch(() => {
                // If offline and navigating to a HTML page, return offline.html fallback
                if (req.mode === 'navigate' || (req.headers.get('accept') && req.headers.get('accept').includes('text/html'))) {
                    return caches.match('offline.html');
                }
            })
        );
        return;
    }

    // Cache-First strategy for static assets (CSS, JS, Images, Fonts)
    e.respondWith(
        caches.match(req).then((cachedResponse) => {
            if (cachedResponse) {
                // Return cached version & update cache in background
                fetch(req).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(req, networkResponse));
                    }
                }).catch(() => {});
                return cachedResponse;
            }

            return fetch(req).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && req.url.startsWith(self.location.origin)) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(req, responseClone));
                }
                return networkResponse;
            }).catch(() => {
                if (req.mode === 'navigate' || (req.headers.get('accept') && req.headers.get('accept').includes('text/html'))) {
                    return caches.match('offline.html');
                }
            });
        })
    );
});

// Push Notification Event
self.addEventListener('push', (e) => {
    let data = { title: 'MedicalAk Update', message: 'You have a new update from MedicalAk.' };
    if (e.data) {
        try {
            data = e.data.json();
        } catch (err) {
            data.message = e.data.text();
        }
    }
    const options = {
        body: data.message || data.body,
        icon: 'images/icons/icon-192.png',
        badge: 'images/icons/icon-192.png',
        vibrate: [100, 50, 100],
        data: { url: data.url || 'patient_dashboard.php' }
    };
    e.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification Click Event
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const targetUrl = e.notification.data && e.notification.data.url ? e.notification.data.url : 'index.php';
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let client of windowClients) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
