/*
 * De service worker van PlayerPath.
 *
 * Bewust zuinig. Het bouwplan zegt: geen offline-caching van financiële data —
 * en die regel is hier strenger doorgetrokken. Er wordt HELEMAAL geen antwoord
 * met gegevens bewaard: geen rapporten, geen betalingen, geen spelerskaarten.
 *
 * Waarom zo streng: een gedeelde telefoon of een uitgelogde gebruiker mag nooit
 * een pagina uit de cache terugkrijgen die niet meer van hem is. Dat risico
 * weegt niet op tegen het gemak van een paar offline schermen.
 *
 * Wat er wél in de cache gaat:
 * - de gebouwde assets onder /build/ (die hebben een hash in de naam en
 *   veranderen dus nooit van inhoud);
 * - de app-iconen;
 * - één offline-pagina, zodat je bij geen bereik iets nettters ziet dan de
 *   dinosaurus van de browser.
 */

const CACHE = 'playerpath-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((namen) => Promise.all(namen.filter((naam) => naam !== CACHE).map((naam) => caches.delete(naam))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Alleen GET. Een POST opnieuw afspelen uit een cache zou betekenen dat er
    // een rapport of een betaling dubbel wordt verstuurd.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Onveranderlijke assets: uit de cache, en anders ophalen en bewaren.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (gevonden) =>
                    gevonden ||
                    fetch(request).then((antwoord) => {
                        if (antwoord.ok) {
                            const kopie = antwoord.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, kopie));
                        }

                        return antwoord;
                    }),
            ),
        );

        return;
    }

    // Paginabezoeken: altijd van de server. Lukt dat niet, dan de offline-pagina.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }

    // Al het overige (Inertia-verzoeken, uploads, downloads) laten we met rust:
    // dat is data, en data hoort van de server te komen.
});
