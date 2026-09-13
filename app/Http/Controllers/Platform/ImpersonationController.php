<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Impersonation;
use App\Models\User;
use App\Support\Platform\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bekijken als een gebruiker van een school.
 *
 * Onmisbaar voor support: "het werkt niet bij mij" is pas op te lossen als je
 * ziet wat zij ziet. Maar het is ook het gevoeligste dat er in dit product zit
 * - je kijkt in de gegevens van andermans kinderen. Vandaar vier voorwaarden,
 * en geen ervan is optioneel:
 *
 * 1. **Alleen de platformbeheerder** kan het starten.
 * 2. **Nooit als een andere platformbeheerder.** Anders is het een manier om
 *    elkaars rechten over te nemen zonder dat het opvalt.
 * 3. **Alles wordt vastgelegd**: wie, bij wie, welke school, wanneer, vanaf
 *    welk adres, en wanneer het weer stopte.
 * 4. **Altijd zichtbaar terwijl het loopt**, met een knop om terug te gaan.
 *    Zie ImpersonationBanner in de app-schil.
 *
 * De originele gebruiker staat in de sessie. Terugkeren is dus geen tweede
 * inlog maar het herstellen van wie je was.
 */
class ImpersonationController extends Controller
{
    public const SESSIE = 'impersonating';

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $beheerder = $request->user();

        abort_if($user->is($beheerder), 403, 'Je bent al jezelf.');
        abort_if($user->isPlatformbeheerder(), 403, 'Je kunt niet als een andere platformbeheerder kijken.');
        abort_if($user->school_id === null, 403, 'Deze gebruiker hoort bij geen enkele school.');
        abort_if(! $user->isActief(), 403, 'Dit account is gedeactiveerd.');

        $log = Impersonation::create([
            'admin_id' => $beheerder->id,
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'admin_email' => $beheerder->email,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
            'started_at' => now(),
        ]);

        // Inloggen ververst het sessie-id (Auth::login migreert de sessie), dus
        // de markering gaat er daarná in. Andersom zou hij de migratie
        // overleven, maar deze volgorde laat geen ruimte voor twijfel.
        Auth::login($user);

        $request->session()->put(self::SESSIE, [
            'admin_id' => $beheerder->id,
            'log_id' => $log->id,
        ]);

        app(PlatformAudit::class)->log('impersonation.started', 'Bekeken als '.$user->name.' ('.$user->email.')', $user->school);

        return redirect()->route('dashboard')
            ->with('status', "Je bekijkt de app nu als {$user->name}.");
    }

    /**
     * Terugkeren naar je eigen account.
     *
     * Deze route zit bewust buiten de beheeromgeving: die weigert verzoeken
     * terwijl je aan het kijken bent, en dan zou de uitgang achter de deur
     * liggen die hij zelf op slot doet.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $sessie = $request->session()->get(self::SESSIE);

        abort_if($sessie === null, 403, 'Je bekijkt op dit moment niemand.');

        $beheerder = User::find($sessie['admin_id']);

        abort_if($beheerder === null || ! $beheerder->isPlatformbeheerder(), 403);

        Impersonation::where('id', $sessie['log_id'])->update(['ended_at' => now()]);

        Auth::login($beheerder);
        $request->session()->forget(self::SESSIE);

        return redirect()->route('platform.dashboard')
            ->with('status', 'Je bent terug in het platformbeheer.');
    }
}
