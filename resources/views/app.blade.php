<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'PlayerPath') }}</title>

        {{-- Het tabblad. Het merkteken is een donkere tegel met de twee P's;
             die staat er dus in beide thema's hetzelfde op. --}}
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
        <link rel="icon" type="image/png" sizes="256x256" href="/brand/mark-256.png">

        {{-- PWA: installeerbaar op het beginscherm. Het manifest is per school. --}}
        <link rel="manifest" href="{{ $manifest ?? '/manifest.webmanifest' }}">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        {{-- De statusbalk van de telefoon kleurt mee met de balk bovenin, die
             voor iedereen dezelfde donkere tint heeft (--topbar). Niet de
             merkkleur: die staat op knoppen, niet in de balk, en dan zit er
             een gekleurde streep boven een donkerblauwe balk. --}}
        <meta name="theme-color" content="#111A2E">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
        <meta name="apple-mobile-web-app-title" content="{{ $appTitle ?? $branding['name'] ?? config('app.name') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])

        {{-- De merkkleur van de school, vóór het eerste beeld. Staat dit in
             JavaScript, dan ziet elke bezoeker eerst een flits PlayerPath-groen.

             Dit blok moet ná @vite staan. De tokens in app.css hangen ook aan
             `:root`, dus bij gelijke specificiteit wint wie het laatst komt -
             stond dit erboven, dan overschreef app.css de merkkleur meteen weer
             en gebeurde er zichtbaar niets.

             Alleen de merkkleur wordt overschreven: statuskleuren en
             grafiektinten blijven van PlayerPath, zodat "waarschuwing" overal
             hetzelfde betekent en het contrast gecontroleerd blijft. --}}
        @if (! empty($branding['primary']))
            <style>
                :root {
                    --primary: {{ $branding['primary'] }};
                    --primary-foreground: {{ $branding['primaryForeground'] }};
                    --ring: {{ $branding['primary'] }};
                }
            </style>
        @endif

        {{-- Draait dit als geïnstalleerde app? Dan staat het menu onderin, waar
             je duim is, in plaats van bovenaan.

             Dit moet vóór het eerste beeld gebeuren en dus hier, niet in Vue:
             anders zie je bij het openen van de app een fractie van een seconde
             de verkeerde balk staan en springt de pagina daarna. De klasse is
             het enige wat dit blokje doet; useAppMode.ts leest hem terug. --}}
        <script>
            (function () {
                var q = function (m) { return window.matchMedia && window.matchMedia(m).matches; };
                if (q('(display-mode: standalone)') || q('(display-mode: minimal-ui)') || q('(display-mode: fullscreen)') || window.navigator.standalone === true) {
                    document.documentElement.classList.add('pp-app');
                }
            })();
        </script>

        @inertiaHead
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
