<?php

namespace App\Console\Commands;

use App\Actions\Payments\SyncPayment;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\School;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * De incassoronde: openstaande rekeningen afschrijven op een bestaand mandaat.
 *
 * Een mandaat ontstaat bij de eerste betaling die de ouder zelf doet; daarna
 * hoeft er niemand meer iets te doen. Vier regels die dat veilig houden:
 *
 * 1. **Alleen incasso-abonnementen.** Wie op iDEAL of overboeking staat, wordt
 *    met rust gelaten; die betaalt zelf.
 * 2. **Alleen als er nu een geldig mandaat is.** Dat vragen we per ronde aan de
 *    provider en onthouden we niet: een bank of een ouder kan het intrekken, en
 *    afschrijven zonder mandaat levert een stornering plus boze ouder op.
 * 3. **Alleen wat vervallen is**, en nooit iets waar al een poging voor loopt.
 * 4. **Eén fout stopt de ronde niet.** Een mislukte incasso bij het ene gezin
 *    mag de rest niet tegenhouden; hij wordt gelogd en de ronde gaat door.
 */
class CollectDuePayments extends Command
{
    protected $signature = 'payments:collect {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Schrijft vervallen betalingen af op een bestaand incassomandaat';

    public function handle(Tenancy $tenancy, PaymentGateway $gateway, SyncPayment $sync): int
    {
        if (! $gateway->isConnected()) {
            $this->warn('Er is geen betaalprovider aangesloten; er wordt niets geïncasseerd.');

            return self::SUCCESS;
        }

        $droog = (bool) $this->option('dry-run');
        $totaal = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            $tenancy->forSchool($school, function () use ($gateway, $sync, $droog, &$totaal) {
                $betalingen = Payment::query()
                    ->outstanding()
                    ->whereDate('due_on', '<=', now())
                    // Nog geen poging gedaan: anders zou een lopende incasso
                    // een tweede keer de deur uitgaan.
                    ->whereNull('external_reference')
                    ->whereHas('subscription', fn ($q) => $q->where('payment_method', PaymentMethod::DirectDebit->value))
                    ->with('player')
                    ->get();

                foreach ($betalingen as $betaling) {
                    $speler = $betaling->player;

                    if ($speler?->payment_customer_reference === null) {
                        continue;
                    }

                    $klant = $speler->payment_customer_reference;

                    try {
                        if (! $gateway->hasValidMandate($klant)) {
                            $this->line("  overgeslagen (geen mandaat): {$speler->full_name}");

                            continue;
                        }

                        $this->line("  {$betaling->description} — {$speler->full_name} — ".Money::format($betaling->amount_cents));

                        if ($droog) {
                            continue;
                        }

                        $sync->handle($betaling, $gateway->charge($betaling, $klant, route('webhooks.mollie')));
                        $totaal++;
                    } catch (Throwable $e) {
                        Log::error('Incasso mislukt', ['payment' => $betaling->id, 'exception' => $e]);
                        $this->error("  mislukt voor {$speler->full_name}: {$e->getMessage()}");
                    }
                }
            });
        }

        $this->info($droog ? 'Proefdraai: er is niets geïncasseerd.' : "Klaar. {$totaal} incasso('s) aangeboden.");

        return self::SUCCESS;
    }
}
