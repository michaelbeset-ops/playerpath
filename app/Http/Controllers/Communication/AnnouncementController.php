<?php

namespace App\Http\Controllers\Communication;

use App\Actions\Communication\SendAnnouncement;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Group;
use App\Support\Communication\AnnouncementAudience;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mededelingen van de school aan ouders en spelers.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        protected SendAnnouncement $verstuur,
        protected AnnouncementAudience $audience,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Announcement::class);

        $groepen = Group::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('announcements/Index', [
            'announcements' => Announcement::with('author', 'group')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Announcement $bericht) => [
                    'id' => $bericht->id,
                    'title' => $bericht->title,
                    'body' => $bericht->body,
                    'author' => $bericht->author?->name,
                    'group' => $bericht->group?->name,
                    'recipients_count' => $bericht->recipients_count,
                    'sent_at' => $bericht->created_at->format('d-m-Y H:i'),
                    'from_cancellation' => $bericht->training_id !== null,
                ]),
            'groups' => $groepen->map(fn (Group $groep) => [
                'id' => $groep->id,
                'name' => $groep->name,
                // Vooraf laten zien hoeveel mensen je aanschrijft: dat scheelt
                // een bericht dat per ongeluk naar de hele school gaat.
                'recipients' => $this->audience->countForGroup($groep),
            ]),
            'schoolRecipients' => $this->audience->countForGroup(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            // exists kent de global scope niet, dus expliciet op school begrenzen.
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('school_id', app(Tenancy::class)->id())],
        ], [], [
            'title' => 'Het onderwerp',
            'body' => 'Het bericht',
            'group_id' => 'De groep',
        ]);

        // Ontbrekende sleutel is iets anders dan een lege waarde: bij
        // 'nullable' voegt de validator hem niet toe als hij niet meekwam.
        $groep = ($validated['group_id'] ?? null) ? Group::findOrFail($validated['group_id']) : null;

        $bericht = $this->verstuur->handle(
            $request->user(),
            $validated['title'],
            $validated['body'],
            $groep,
        );

        return back()->with('status', $bericht->recipients_count === 0
            ? 'Er waren geen ontvangers; het bericht is wel bewaard.'
            : "Het bericht is verstuurd naar {$bericht->recipients_count} ontvanger(s).");
    }
}
