<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Branding\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Het manifest van de PWA, per school.
 *
 * Dynamisch en geen statisch bestand: op het eigen adres van een school heet
 * de app op het beginscherm dan ook zo, met haar kleur eromheen. Een vast
 * "PlayerPath" zou het white-label-verhaal precies op de plek breken waar een
 * ouder er het meest naar kijkt.
 *
 * Het icoon blijft voorlopig van PlayerPath: een geüpload logo is zelden
 * vierkant en zou op een beginscherm afgesneden worden. Een eigen app-icoon
 * vraagt een aparte, vierkante upload.
 */
class ManifestController extends Controller
{
    public function __construct(protected Branding $branding) {}

    public function __invoke(Request $request): JsonResponse
    {
        $school = $this->branding->forRequest($request);
        $huisstijl = $this->branding->describe($school);

        return response()->json([
            'name' => $huisstijl['name'],
            'short_name' => str($huisstijl['name'])->limit(12, '')->trim()->toString(),
            'description' => 'Rapporten, trainingen en de spelerskaart van je voetbalschool.',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'nl',
            'dir' => 'ltr',
            // De donkere kant van het merk: dat is wat een speler of ouder ziet
            // bij het opstarten, en het staat goed op een beginscherm.
            'background_color' => '#0A0F1C',
            'theme_color' => $huisstijl['usedColor'] ?? '#0A0F1C',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->withHeaders([
            'Content-Type' => 'application/manifest+json',
            // Kort cachen: een school die haar kleur wijzigt wil dat terugzien
            // zonder dat iedereen zijn app opnieuw moet installeren.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
