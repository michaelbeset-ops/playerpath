<?php

namespace App\Http\Controllers\Trainings;

use App\Enums\Registration;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Notifications\AfmeldingOntvangen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * De speler of ouder meldt zich aan of af voor een training.
 *
 * Bewust los van AttendanceController: dit is wat er vooraf gezegd wordt, niet
 * wat de trainer achteraf afvinkt. Deze actie raakt het veld `status` dan ook
 * nooit aan.
 */
class RegistrationController extends Controller
{
    public function store(Request $request, Training $training, Player $player): RedirectResponse
    {
        $this->authorize('view', $training);

        $user = $request->user();

        // Je meldt alleen jezelf of je eigen kind aan. Een trainer die dit voor
        // een ander wil doen, vinkt straks gewoon de aanwezigheid af.
        abort_unless(in_array($player->id, $user->visiblePlayerIds(), strict: true), 403,
            'Je kunt alleen je eigen kind aan- of afmelden.');

        abort_unless($training->expectedPlayers()->contains('id', $player->id), 404);

        abort_if($training->hasPassed(), 422, 'Deze training is al geweest.');

        $validated = $request->validate([
            'registration' => ['required', Rule::enum(Registration::class)],
            'reason' => ['nullable', 'string', 'max:300'],
        ], [], ['registration' => 'De aanmelding', 'reason' => 'De reden']);

        $afgemeld = $validated['registration'] === Registration::Declined->value;

        $training->attendances()->updateOrCreate(
            ['player_id' => $player->id],
            [
                'registration' => $validated['registration'],
                'registered_by_id' => $user->id,
                // De reden hoort bij een afmelding; weer aanmelden wist hem.
                'registration_note' => $afgemeld ? ($validated['reason'] ?? null) : null,
            ],
        );

        // De trainer hoort het meteen. Zonder gekoppelde trainers is een
        // training "van iedereen" (zie CLAUDE.md), dus dan de hele staf.
        // De trainer(s) van de training én de eigenaar. Zonder gekoppelde
        // trainers is een training "van iedereen" (zie CLAUDE.md): dan alle trainers.
        if ($afgemeld) {
            $ontvangers = $training->trainers()->get();

            if ($ontvangers->isEmpty()) {
                $ontvangers = User::ofCurrentSchool()->role(Role::Trainer->value)->get();
            }

            $ontvangers = $ontvangers
                ->merge(User::ofCurrentSchool()->role(Role::Eigenaar->value)->get())
                ->unique('id');

            Notification::send($ontvangers, new AfmeldingOntvangen($training, $player, $validated['reason'] ?? null));
        }

        $melding = $validated['registration'] === Registration::Attending->value
            ? 'Je bent aangemeld voor deze training.'
            : 'Je bent afgemeld voor deze training.';

        return back()->with('status', $melding);
    }
}
