<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Houdt een pagina uit zoekmachines.
 *
 * Bewust als HTTP-header en niet alleen als meta-tag: de meta wordt door
 * Inertia pas na het laden door JavaScript toegevoegd, en daar kun je bij een
 * crawler niet op rekenen. Deze header staat er altijd, meteen.
 */
class PreventSearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
