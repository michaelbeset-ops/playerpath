<?php

namespace App\Console\Commands;

use App\Actions\Enrollments\InviteFromWaitlist;
use App\Enums\EnrollmentStatus;
use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Subscription;
use App\Notifications\VerlengUitnodiging;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * De levensloop van inschrijvingen en abonnementen, één keer per dag.
 *
 * Vijf dingen, in deze volgorde:
 *
 * 0. **Verlopen wachtlijst-uitnodigingen**: de plek vervalt en de volgende
 *    in de rij wordt uitgenodigd (InviteFromWaitlist).
 * 1. **Bevestigd → actief** zodra het aanbod begonnen is.
 * 2. **Verleng-uitnodiging** voor een blok dat binnenkort afloopt en niet
 *    automatisch verlengt: de ouder krijgt vóór het einde bericht en meldt
 *    opnieuw aan. Eén keer per inschrijving (`renewal_invited_at`).
 * 3. **Actief → beëindigd** zodra het aanbod voorbij is.
 * 4. **Opzegging gepland → beëindigd** zodra de opzegtermijn om is; de
 *    inschrijving gaat mee.
 *
 * Idempotent: elke stap kijkt naar de status van nu, dus twee keer draaien
 * doet niets twee keer.
 */
class RunEnrollmentLifecycle extends Command
{
    /** Hoeveel dagen vóór het einde de verleng-uitnodiging gaat. */
    public const VERLENGEN_DAGEN_VOORAF = 14;

    protected $signature = 'enrollments:lifecycle {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Activeert, beëindigt en verlengt inschrijvingen en abonnementen';

    public function handle(Tenancy $tenancy, InviteFromWaitlist $wachtlijst): int
    {
        $droog = (bool) $this->option('dry-run');
        $vandaag = CarbonImmutable::today();

        foreach (School::where('is_active', true)->cursor() as $school) {
            if (! Features::enabledFor($school, Feature::Inschrijvingen)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($school, $droog, $vandaag, $wachtlijst) {
                $instellingen = EnrollmentSettings::for($school);

                // 0. Verlopen uitnodigingen vanaf de wachtlijst: de plek vervalt en
                //    de volgende in de rij krijgt hem.
                if (! $droog) {
                    $verlopen = $wachtlijst->expire();

                    if ($verlopen > 0) {
                        $this->line("  {$verlopen} verlopen uitnodiging(en) afgehandeld");
                    }
                }

                // 1. Bevestigd → actief
                foreach (Enrollment::where('status', EnrollmentStatus::Confirmed->value)->with('product')->get() as $e) {
                    $start = $e->product?->starts_on;

                    if ($start === null || $start->gt($vandaag)) {
                        continue;
                    }

                    $this->doe($droog, "actief: {$e->child_name}", fn () => $e->transitionTo(EnrollmentStatus::Active));
                }

                // 2. Verleng-uitnodiging
                if (! $instellingen->get('auto_renew_block')) {
                    $grens = $vandaag->addDays(self::VERLENGEN_DAGEN_VOORAF);

                    $kandidaten = Enrollment::whereIn('status', [EnrollmentStatus::Confirmed->value, EnrollmentStatus::Active->value])
                        ->whereNull('renewal_invited_at')
                        ->with(['product', 'guardian'])
                        ->get()
                        ->filter(fn (Enrollment $e) => $e->product?->type->hasPeriod()
                            && $e->product->ends_on !== null
                            && $e->product->ends_on->between($vandaag, $grens)
                            && $e->guardian !== null);

                    foreach ($kandidaten as $e) {
                        $this->doe($droog, "verleng-uitnodiging: {$e->child_name}", function () use ($e) {
                            $e->forceFill(['renewal_invited_at' => now()])->save();
                            $e->guardian->notify(new VerlengUitnodiging($e));
                        });
                    }
                }

                // 3. Voorbij → beëindigd
                foreach (Enrollment::whereIn('status', [EnrollmentStatus::Confirmed->value, EnrollmentStatus::Active->value])->with('product')->get() as $e) {
                    $einde = $e->product?->ends_on;

                    if ($einde === null || $einde->gte($vandaag)) {
                        continue;
                    }

                    // Een doorlopend abonnement zonder einddatum loopt door;
                    // een blok dat per maand betaald wordt en doorloopt ook.
                    if ($e->paymentOption?->type->isRecurring() && ! ($e->product->stops_at_end ?? true)) {
                        continue;
                    }

                    $this->doe($droog, "beëindigd: {$e->child_name}", fn () => $e->transitionTo(EnrollmentStatus::Ended));
                }

                // 4. Opzegtermijn om
                foreach (Subscription::where('status', SubscriptionStatus::CancellationPlanned->value)->with('enrollment')->get() as $s) {
                    if ($s->ends_on === null || $s->ends_on->gte($vandaag)) {
                        continue;
                    }

                    $this->doe($droog, "abonnement beëindigd: {$s->player?->full_name}", function () use ($s) {
                        $s->transitionTo(SubscriptionStatus::Ended);

                        if ($s->enrollment?->status->canTransitionTo(EnrollmentStatus::Ended)) {
                            $s->enrollment->transitionTo(EnrollmentStatus::Ended);
                        }
                    });
                }
            });
        }

        $this->info($droog ? 'Proefdraai: er is niets veranderd.' : 'Klaar.');

        return self::SUCCESS;
    }

    protected function doe(bool $droog, string $wat, callable $actie): void
    {
        $this->line('  '.($droog ? 'zou doen: ' : '').$wat);

        if (! $droog) {
            $actie();
        }
    }
}
