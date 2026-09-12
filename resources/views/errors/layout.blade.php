<!DOCTYPE html>
{{-- De foutpagina's: donker, Nederlands, en met een weg terug. Laravel's
     eigen pagina's zijn Engels en wit; op een telefoon leest dat als "stuk". --}}
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#111A2E">
        <title>@yield('title') - {{ config('app.name', 'PlayerPath') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <style>
            html { background: #0d0f12; color: #f1f5f9; }
            body { margin: 0; min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; box-sizing: border-box; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; }
            .vak { width: 100%; max-width: 24rem; text-align: center; }
            .code { font-size: 0.75rem; letter-spacing: 0.2em; color: #99a3b3; text-transform: uppercase; }
            h1 { font-size: 1.5rem; margin: 0.5rem 0 0.5rem; font-weight: 600; }
            p { color: #99a3b3; line-height: 1.5; margin: 0; }
            a.knop { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem; margin-top: 1.5rem; padding: 0 1.25rem; border-radius: 0.75rem; background: #22e06b; color: #0d0f12; font-weight: 600; text-decoration: none; }
            a.los { display: inline-flex; align-items: center; min-height: 2.75rem; margin-top: 0.5rem; color: #99a3b3; text-decoration: underline; text-underline-offset: 4px; }
        </style>
    </head>
    <body>
        <div class="vak">
            <p class="code">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div>
                <a class="knop" href="{{ url('/dashboard') }}">Naar het dashboard</a>
            </div>
            <a class="los" href="javascript:history.back()">Terug naar de vorige pagina</a>
        </div>
    </body>
</html>
