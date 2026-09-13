<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\School;
use App\Models\User;
use App\Notifications\IncassoAankondiging;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * De vooraankondiging van een incasso.
 *
 * Vóór elke afschrijving hoort de ouder te weten wat er komt, minstens
 * veertien dagen vooraf. Deze ronde stuurt die aankondiging voor elke
 * openstaande incasso die hem nog niet had, en legt vast wanneer
 * (`prenotified_at`). `payments:collect` schrijft pas af als dat moment
 * veertien dagen achter ons ligt; zonder aankondiging wordt er dus nooit
 * geïncasseerd. Idempotent: één aankondiging per betaling.
 */
class PrenotifyDirectDebits extends Command
{
    /** Hoeveel dagen er minstens tussen aankondiging en afschrijving zitten. */
    public const DAGEN_VOORAF = 14;

    protected $signature = 'payments:prenotify {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Kondigt komende incasso\'s aan bij de ouders, minstens veertien dagen vooraf';

    public function handle(Tenancy $tenancy): int
    {
        $droog = (bool) $this->option('dry-run');
        $totaal = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            if (! Features::enabledFor($school, Feature::Betalingen)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($droog, &$totaal) {
                $betalingen = Payment::query()
                    ->outstanding()
                    ->whereNull('prenotified_at')
                    ->whereNull('external_reference')
                    // Bij een abonnement beslist het abonnement; bij een orderbetaling
                    // de betaling zelf.
                    ->where(fn ($q) => $q
                        ->where(fn ($o) => $o->whereNull('subscription_id')->where('method', PaymentMethod::DirectDebit->value))
                        ->orWhereHas('subscription', fn ($s) => $s->where('payment_method', PaymentMethod::DirectDebit->value)))
                    ->with(['player.guardians', 'order.user'])
                    ->get();

                foreach ($betalingen as $betaling) {
                    $ontvangers = $this->ontvangers($betaling);

                    if ($ontvangers === []) {
                        continue;
                    }

                    $afschrijving = $betaling->due_on->max(now()->addDays(self::DAGEN_VOORAF))->startOfDay();

                    $this->line("  {$betaling->description} - ".Money::format($betaling->amount_cents).' - rond '.$afschrijving->format('d-m-Y'));

                    if ($droog) {
                        continue;
                    }

                    Notification::send($ontvangers, new IncassoAankondiging($betaling, $afschrijving));
                    $betaling->forceFill(['prenotified_at' => now()])->save();
                    $totaal++;
                }
            });
        }

        $this->info($droog ? 'Proefdraai: er is niets verstuurd.' : "Klaar. {$totaal} aankondiging(en) verstuurd.");

        return self::SUCCESS;
    }

    /**
     * Wie betaalt: de ouder van de order, anders de ouders van het kind.
     *
     * @return list<User>
     */
    protected function ontvangers(Payment $betaling): array
    {
        if ($betaling->order?->user !== null) {
            return [$betaling->order->user];
        }

        return $betaling->player?->guardians->all() ?? [];
    }
}
