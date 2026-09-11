<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Nog geen account? Zoek je school."
 *
 * Een ouder die op de inlogpagina belandt zonder account weet vaak niet waar
 * hij heen moet: het adres van de inschrijfpagina staat op de site van de
 * school, niet in zijn hoofd. Hier typt hij de naam van de school en komt bij
 * haar openbare inschrijfpagina uit.
 *
 * Er staat bewust weinig: alleen actieve scholen, alleen naam en logo, hooguit
 * tien resultaten, en pas vanaf twee tekens. Dit is geen lijst van al onze
 * klanten, het is een wegwijzer voor wie de naam al kent.
 */
class FindSchoolController extends Controller
{
    public function index(Request $request): Response
    {
        $zoek = trim((string) $request->query('q', ''));

        $scholen = mb_strlen($zoek) < 2
            ? collect()
            : School::query()
                ->where('is_active', true)
                ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $zoek).'%')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(fn (School $school) => [
                    'name' => $school->name,
                    'logo' => $school->logo_path === null ? null : Storage::url($school->logo_path),
                    'href' => route('enroll.show', $school->slug, absolute: false),
                ])
                ->values();

        return Inertia::render('auth/FindSchool', [
            'query' => $zoek,
            'schools' => $scholen,
        ]);
    }
}
