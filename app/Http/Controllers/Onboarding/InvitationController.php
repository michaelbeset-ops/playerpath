<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Uitnodiging;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Trainers en ouders uitnodigen — één tegelijk of een hele lijst.
 *
 * Bulk is hier geen luxe: een school die overstapt heeft honderd ouders, en die
 * één voor één toevoegen is het soort werk waarna iemand besluit het toch maar
 * niet te doen. Het formulier neemt daarom regels aan van de vorm
 * "Naam <mail@voorbeeld.nl>" of "Naam, mail@voorbeeld.nl", één per regel.
 *
 * Vier regels die dit eerlijk houden:
 *
 * 1. **Een account ontstaat pas bij activatie.** Tot die tijd is er alleen een
 *    uitnodiging. Zou het account meteen bestaan, dan staat er een half
 *    ledenbestand met mensen die nog nooit hebben ingelogd, en telt de school
 *    ze wel mee.
 * 2. **Een e-mailadres dat al een account heeft wordt overgeslagen**, met een
 *    melding erbij. Anders krijgt iemand een uitnodiging voor een account dat
 *    hij al heeft, en dat is precies hoe je iemand kwijtraakt.
 * 3. **Opnieuw versturen maakt een nieuw token en een nieuwe termijn.** De oude
 *    link werkt daarna niet meer; twee geldige links naar hetzelfde account is
 *    er één te veel.
 * 4. **De mail gaat na de transactie de deur uit.** Een mislukte opslag mag
 *    nooit alsnog honderd uitnodigingen opleveren; die krijg je niet terug.
 */
class InvitationController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'role' => ['required', Rule::in([Role::Trainer->value, Role::Ouder->value])],
            // Eén per regel: "Naam <mail@voorbeeld.nl>" of "Naam, mail@voorbeeld.nl".
            'recipients' => ['required', 'string', 'max:20000'],
            'player_ids' => ['nullable', 'array'],
            'player_ids.*' => ['integer', Rule::exists('players', 'id')->where('school_id', $this->tenancy->id())],
            'relationship' => ['nullable', 'string', 'max:255'],
        ], [
            'recipients.required' => 'Vul minstens één naam en e-mailadres in.',
        ], [
            'recipients' => 'De lijst',
        ]);

        $regels = $this->ontleed($data['recipients']);

        if ($regels === []) {
            return back()->withErrors(['recipients' => 'Geen geldig e-mailadres gevonden. Zet één persoon per regel, bijvoorbeeld: Jansen <jansen@voorbeeld.nl>']);
        }

        $school = $request->user()->school;
        $dagen = (int) ($school->invitation_valid_days ?: 14);

        $verstuurd = [];
        $overgeslagen = [];

        foreach ($regels as [$naam, $email]) {
            if (User::where('email', $email)->exists()) {
                $overgeslagen[] = $email;

                continue;
            }

            $uitnodiging = DB::transaction(function () use ($naam, $email, $data, $request, $dagen) {
                // Een openstaande uitnodiging voor hetzelfde adres wordt
                // vervangen, niet verdubbeld: twee mails met twee links is
                // verwarrend en de tweede werkt toch alleen.
                Invitation::where('email', $email)->pending()->delete();

                $rij = new Invitation;
                $rij->forceFill([
                    'name' => $naam,
                    'email' => $email,
                    'role' => $data['role'],
                    'token' => Invitation::nieuwToken(),
                    'player_ids' => $data['role'] === Role::Ouder->value ? ($data['player_ids'] ?? []) : null,
                    'relationship' => $data['relationship'] ?? null,
                    'invited_by' => $request->user()->id,
                    'expires_at' => now()->addDays($dagen),
                    'last_sent_at' => now(),
                    'sent_count' => 1,
                ])->save();

                return $rij;
            });

            $verstuurd[] = $uitnodiging;
        }

        foreach ($verstuurd as $uitnodiging) {
            Notification::route('mail', $uitnodiging->email)->notify(new Uitnodiging($uitnodiging));
        }

        return back()->with('status', $this->melding(count($verstuurd), $overgeslagen));
    }

    public function resend(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);
        abort_unless($invitation->school_id === $request->user()->school_id, 403);
        abort_if($invitation->isAccepted(), 422, 'Deze uitnodiging is al gebruikt.');

        $dagen = (int) ($request->user()->school->invitation_valid_days ?: 14);

        // Nieuw token: de oude link hoort daarna niets meer te doen.
        $invitation->forceFill([
            'token' => Invitation::nieuwToken(),
            'expires_at' => now()->addDays($dagen),
            'last_sent_at' => now(),
            'sent_count' => $invitation->sent_count + 1,
        ])->save();

        Notification::route('mail', $invitation->email)->notify(new Uitnodiging($invitation));

        return back()->with('status', "De uitnodiging is opnieuw verstuurd naar {$invitation->email}.");
    }

    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);
        abort_unless($invitation->school_id === $request->user()->school_id, 403);

        $email = $invitation->email;
        $invitation->delete();

        return back()->with('status', "De uitnodiging voor {$email} is ingetrokken.");
    }

    /**
     * Ruwe regels naar naam en e-mailadres.
     *
     * Neemt "Naam <mail@x.nl>", "Naam, mail@x.nl", "Naam;mail@x.nl" en een kaal
     * e-mailadres aan. Dat laatste levert een naam op uit het adres — beter een
     * naam die niet klopt dan een uitnodiging die niet verstuurd wordt omdat
     * iemand een lijst adressen plakte.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function ontleed(string $ruw): array
    {
        $uit = [];
        $gezien = [];

        foreach (preg_split('/\r\n|\r|\n/', $ruw) as $regel) {
            $regel = trim($regel);

            if ($regel === '') {
                continue;
            }

            if (! preg_match('/([^\s<>,;]+@[^\s<>,;]+\.[^\s<>,;]+)/', $regel, $treffer)) {
                continue;
            }

            $email = strtolower(trim($treffer[1], '<>,; '));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($gezien[$email])) {
                continue;
            }

            $naam = trim(str_replace($treffer[1], '', $regel), " \t<>,;");
            $naam = $naam !== '' ? $naam : ucfirst(strstr($email, '@', true) ?: $email);

            $gezien[$email] = true;
            $uit[] = [mb_substr($naam, 0, 255), $email];
        }

        return $uit;
    }

    /** @param  list<string>  $overgeslagen */
    protected function melding(int $aantal, array $overgeslagen): string
    {
        $tekst = match ($aantal) {
            0 => 'Er is niemand uitgenodigd.',
            1 => 'De uitnodiging is verstuurd.',
            default => "{$aantal} uitnodigingen zijn verstuurd.",
        };

        if ($overgeslagen !== []) {
            $tekst .= ' Overgeslagen omdat er al een account is: '.implode(', ', array_slice($overgeslagen, 0, 5)).
                (count($overgeslagen) > 5 ? ' en '.(count($overgeslagen) - 5).' meer.' : '.');
        }

        return $tekst;
    }
}
