<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\School;
use App\Notifications\BetalingHerinnering;
use App\Notifications\BetalingMislukt;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Herinneringen bij betalingen die over de vervaldatum zijn.
 *
 * Dit is het enige stuk van de betaalketen dat ook zonder provider nuttig is:
 * ook een overboeking die uitblijft verdient een nette herinnering.
 *
 * Drie regels die voorkomen dat dit een spamkanon wordt:
 *
 * - pas na een respijtperiode (standaard 3 dagen), want een incasso die
 *   vandaag verwerkt wordt is morgen pas zichtbaar;
 * - hooguit één herinnering per twee weken per betaling (`reminded_at`);
 * - alleen naar de ouders van dat kind, nooit naar de hele school.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'payments:remind
                            {--days=3 : Aantal dagen na de vervaldatum voordat er iets uitgaat}
                            {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Stuurt herinneringen voor openstaande betalingen die over de vervaldatum zijn';

    public function handle(Tenancy $tenancy): int
    {
        $respijt = max(0, (int) $this->option('days'));
        $droog = (bool) $this->option('dry-run');

        $grens = now()->startOfDay()->subDays($respijt);
        $verstuurd = 0;

        // Per school, zodat de global scope en de tenant-context kloppen.
        foreach (School::where('is_active', true)->cursor() as $school) {
            // Staat de functie uit voor deze school, dan gebeurt er ook in de
            // achtergrond niets. Alleen het scherm verbergen zou betekenen dat
            // herinneringen gewoon doorloopt bij een school die er niet voor betaalt.
            if (! Features::enabledFor($school, Feature::Betalingen)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($school, $grens, $droog, &$verstuurd) {
                // Mislukt, verlopen of gestorneerd: herinneringen met een nieuwe
                // betaallink, volgens het herhaalschema van de school.
                $schema = array_values((array) (EnrollmentSettings::for($school)->get('dunning')['days'] ?? []));

                $mislukt = Payment::query()
                    ->whereIn('status', [PaymentStatus::Failed->value, PaymentStatus::Expired->value, PaymentStatus::ChargedBack->value])
                    ->where('reminder_count', '<', count($schema))
                    ->with(['player.guardians', 'order.user'])
                    ->get();

                foreach ($mislukt as $betaling) {
                    $poging = (int) $betaling->reminder_count;
                    $na = (int) ($schema[$poging] ?? PHP_INT_MAX);
                    $sinds = (int) $betaling->updated_at->startOfDay()->diffInDays(now()->startOfDay());

                    if ($sinds < $na) {
                        continue;
                    }

                    $ontvanger = $betaling->payer();

                    if ($ontvanger === null) {
                        continue;
                    }

                    $this->line("  mislukt: {$betaling->description} — herinnering ".($poging + 1).' van '.count($schema));

                    if ($droog) {
                        continue;
                    }

                    $ontvanger->notify(new BetalingMislukt($betaling, $poging + 1, count($schema)));
                    // Zonder updated_at aan te raken: het schema telt vanaf het
                    // moment van mislukken, niet vanaf de vorige herinnering.
                    $betaling->timestamps = false;
                    $betaling->forceFill(['reminder_count' => $poging + 1])->save();
                    $betaling->timestamps = true;
                    $verstuurd++;
                }
                $betalingen = Payment::query()
                    ->outstanding()
                    ->whereDate('due_on', '<=', $grens)
                    ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subWeeks(2)))
                    ->with('player.guardians')
                    ->get();

                foreach ($betalingen as $betaling) {
                    $ouders = $betaling->player?->guardians ?? collect();

                    if ($ouders->isEmpty()) {
                        continue;
                    }

                    $dagen = (int) $betaling->due_on->startOfDay()->diffInDays(now()->startOfDay());

                    $this->line("  {$betaling->description} — {$betaling->player?->full_name} ({$dagen} dagen te laat)");

                    if ($droog) {
                        continue;
                    }

                    Notification::send($ouders, new BetalingHerinnering($betaling, $dagen));

                    $betaling->forceFill(['reminded_at' => now()])->save();
                    $verstuurd++;
                }
            });
        }

        $this->info($droog
            ? 'Proefdraai: er is niets verstuurd.'
            : "Klaar. {$verstuurd} herinnering(en) verstuurd.");

        return self::SUCCESS;
    }
}
