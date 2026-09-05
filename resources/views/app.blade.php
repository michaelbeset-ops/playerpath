<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'PlayerPath') }}</title>

        {{-- De merkkleur van de school, vóór het eerste beeld. Staat dit in
             JavaScript, dan ziet elke bezoeker eerst een flits PlayerPath-groen.
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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
