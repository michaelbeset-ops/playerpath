<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Een uitnodiging inwisselen voor een account.
 *
 * Dit is de enige route waar iemand zonder inlog een account krijgt, en dat
 * mag alleen omdat de school hem persoonlijk heeft uitgenodigd. Wat het veilig
 * houdt: het token van 64 tekens is niet te raden, het verloopt, het is
 * eenmalig, en het bepaalt zelf bij welke school en welke rol het hoort - daar
 * valt via het formulier niets aan te veranderen.
 *
 * Een ouder krijgt zijn kinderen meteen gekoppeld. Zonder dat zou de school na
 * elke activatie alsnog handmatig moeten koppelen, en dan is de uitnodiging het
 * halve werk.
 *
 * Na afloop is de gebruiker ingelogd en staat hij op zijn eigen dashboard. Hem
 * na het kiezen van een wachtwoord alsnog een inlogscherm voorschotelen is
 * precies waar mensen afhaken.
 */
class AcceptInvitationController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function show(string $token): Response
    {
        $uitnodiging = $this->vind($token);

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            // Ongeldig of verlopen: dan geen formulier, maar wel uitleg. Een
            // kale foutpagina laat iemand denken dat hij iets fout deed.
            'invitation' => $uitnodiging === null ? null : [
                'name' => $uitnodiging->name,
                'email' => $uitnodiging->email,
                'role' => $uitnodiging->role,
                'school' => $uitnodiging->school->name,
                'logo' => $uitnodiging->school->logo_path === null ? null : Storage::url($uitnodiging->school->logo_path),
                'children' => $uitnodiging->players()->pluck('first_name')->all(),
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->vind($token);

        if ($uitnodiging === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Deze uitnodiging is verlopen of al gebruikt. Vraag je school om een nieuwe.',
            ]);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['password' => 'Het wachtwoord']);

        $gebruiker = DB::transaction(function () use ($uitnodiging, $data) {
            // De school komt uit de uitnodiging, niet uit invoer: er valt dus
            // niets aan te knoeien. Zie CLAUDE.md 3.1.
            $this->tenancy->set($uitnodiging->school);

            // Een trainer uit een import bestaat al, zonder adres. Die krijgt
            // nu zijn inlog; een tweede account zou hem los zetten van zijn
            // trainingen en rapporten.
            $bestaand = $uitnodiging->user_id === null ? null : User::query()
                ->where('school_id', $uitnodiging->school_id)
                ->whereNull('email')
                ->find($uitnodiging->user_id);

            if ($bestaand !== null) {
                $bestaand->forceFill([
                    'email' => $uitnodiging->email,
                    'password' => $data['password'],
                ])->save();
                $gebruiker = $bestaand;
            } else {
                $gebruiker = User::create([
                    'school_id' => $uitnodiging->school_id,
                    'name' => $uitnodiging->name,
                    'email' => $uitnodiging->email,
                    'password' => $data['password'],
                ]);
            }

            // Uitgenodigd worden en de link uit je eigen mailbox openen is het
            // bewijs dat het adres van jou is; nog een bevestigingsmail sturen
            // is een extra drempel zonder extra zekerheid.
            $gebruiker->forceFill(['email_verified_at' => now()])->save();

            $gebruiker->assignRole($uitnodiging->role);

            if ($uitnodiging->role === Role::Ouder->value) {
                foreach ($uitnodiging->players() as $speler) {
                    $speler->guardians()->syncWithoutDetaching([
                        $gebruiker->id => ['relationship' => $uitnodiging->relationship],
                    ]);
                }
            }

            // Een speler krijgt zijn eigen profiel. Had hij alleen de kind-link,
            // dan logt hij voortaan met dit account in; de ouders blijven gekoppeld.
            if ($uitnodiging->role === Role::Speler->value) {
                foreach ($uitnodiging->players() as $speler) {
                    $speler->forceFill(['user_id' => $gebruiker->id])->save();
                }
            }

            $uitnodiging->forceFill([
                'accepted_at' => now(),
                'user_id' => $gebruiker->id,
            ])->save();

            return $gebruiker;
        });

        Auth::login($gebruiker);
        $request->session()->regenerate();

        // Een ouder of speler gaat eerst langs de foto: dat is het moment
        // waarop hij er tijd voor heeft, en zonder foto is de kaart de helft
        // minder waard. Het scherm stuurt zelf door als er niets te doen is.
        $rol = $uitnodiging->role;
        $naar = in_array($rol, [Role::Ouder->value, Role::Speler->value], true) ? 'onboarding.photo' : 'dashboard';

        return redirect()->route($naar)->with('status', 'Je account is klaar. Welkom!');
    }

    /**
     * De uitnodiging bij dit token, of null.
     *
     * Zonder ingelogde gebruiker is er geen actieve school, dus de global scope
     * staat fail-closed dicht; `withoutSchoolScope()` is hier nodig en veilig,
     * want het token wijst één rij aan. Daarna wordt de school expliciet gezet.
     */
    protected function vind(string $token): ?Invitation
    {
        $uitnodiging = Invitation::withoutSchoolScope()
            ->with('school')
            ->where('token', $token)
            ->first();

        if ($uitnodiging === null || ! $uitnodiging->isOpen() || ! $uitnodiging->school?->is_active) {
            return null;
        }

        // Voor een bestaand account: dat moet er nog zijn en nog geen inlog hebben.
        if ($uitnodiging->user_id !== null && ! User::query()
            ->where('school_id', $uitnodiging->school_id)
            ->whereNull('email')
            ->whereKey($uitnodiging->user_id)
            ->exists()) {
            return null;
        }

        // Iemand die intussen langs een andere weg een account kreeg.
        if (User::where('email', $uitnodiging->email)->exists()) {
            return null;
        }

        return $uitnodiging;
    }
}
