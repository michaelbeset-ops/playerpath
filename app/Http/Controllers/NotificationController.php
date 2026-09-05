<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De meldingen van de ingelogde gebruiker.
 *
 * Meldingen hangen aan de gebruiker, en die hoort al bij één school. Er is dus
 * geen aparte school-filter nodig; je ziet per definitie alleen je eigen rijen.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $meldingen = $request->user()
            ->notifications()
            ->limit(50)
            ->get()
            ->map(fn ($melding) => [
                'id' => $melding->id,
                'title' => $melding->data['title'] ?? 'Melding',
                'url' => $melding->data['url'] ?? null,
                'player_name' => $melding->data['player_name'] ?? null,
                'overall_rating' => $melding->data['overall_rating'] ?? null,
                'groei' => $melding->data['groei'] ?? null,
                'read' => $melding->read_at !== null,
                'when' => $melding->created_at->diffForHumans(),
            ]);

        return Inertia::render('notifications/Index', [
            'notifications' => $meldingen,
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Alle meldingen zijn gelezen.');
    }
}
