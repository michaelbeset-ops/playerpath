<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\School;
use App\Notifications\BetalingHerinnering;
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
            $tenancy->forSchool($school, function () use ($grens, $droog, &$verstuurd) {
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
