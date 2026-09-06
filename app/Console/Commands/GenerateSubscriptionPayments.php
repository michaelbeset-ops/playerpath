<?php

namespace App\Console\Commands;

use App\Actions\Payments\GeneratePayments;
use App\Enums\Feature;
use App\Models\School;
use App\Models\Subscription;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

/**
 * De facturenloop: elk lopend abonnement krijgt de rekening voor de termijn
 * die nu loopt.
 *
 * Draait dagelijks. Dat lijkt vaak voor maandbedragen, maar abonnementen
 * beginnen op verschillende dagen van de maand, en de loop is idempotent:
 * bestaat de rekening al, dan gebeurt er niets.
 */
class GenerateSubscriptionPayments extends Command
{
    protected $signature = 'payments:generate {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Maakt de openstaande betalingen aan voor lopende abonnementen';

    public function handle(Tenancy $tenancy, GeneratePayments $actie): int
    {
        $droog = (bool) $this->option('dry-run');
        $totaal = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            // Staat de functie uit voor deze school, dan gebeurt er ook in de
            // achtergrond niets. Alleen het scherm verbergen zou betekenen dat
            // de facturenloop gewoon doorloopt bij een school die er niet voor betaalt.
            if (! Features::enabledFor($school, Feature::Betalingen)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($actie, $droog, &$totaal) {
                $abonnementen = Subscription::query()->active()->with('plan', 'player')->get();

                foreach ($abonnementen as $abonnement) {
                    if ($droog) {
                        $this->line("  zou nakijken: {$abonnement->player?->full_name}");

                        continue;
                    }

                    foreach ($actie->handle($abonnement) as $betaling) {
                        $this->line("  {$betaling->description} — {$abonnement->player?->full_name} — ".Money::format($betaling->amount_cents));
                        $totaal++;
                    }
                }
            });
        }

        $this->info($droog ? 'Proefdraai: er is niets aangemaakt.' : "Klaar. {$totaal} betaling(en) aangemaakt.");

        return self::SUCCESS;
    }
}
