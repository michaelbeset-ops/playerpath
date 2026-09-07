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
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="theme-color" content="{{ $branding['usedColor'] ?? '#0A0F1C' }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $branding['name'] ?? config('app.name') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])

        {{-- De merkkleur van de school, vóór het eerste beeld. Staat dit in
             JavaScript, dan ziet elke bezoeker eerst een flits PlayerPath-groen.

             Dit blok moet ná @vite staan. De tokens in app.css hangen ook aan
             `:root`, dus bij gelijke specificiteit wint wie het laatst komt —
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

        @inertiaHead
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
