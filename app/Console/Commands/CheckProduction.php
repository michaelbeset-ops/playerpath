<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Support\Payments\PaymentGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Nakijken of deze omgeving klaar is om echte scholen te bedienen.
 *
 * Bedoeld om te draaien ná een deploy en vóór de eerste school. Elke regel is
 * een fout die iemand ooit maakt en die je pas merkt als het te laat is: debug
 * aan laten staan op een publiek adres, de queue vergeten, of de opslaglink
 * die niet bestaat waardoor elk schoollogo een gebroken plaatje is.
 */
class CheckProduction extends Command
{
    protected $signature = 'playerpath:check';

    protected $description = 'Controleert of deze omgeving klaar is voor gebruik door echte scholen';

    private int $problemen = 0;

    private int $waarschuwingen = 0;

    public function handle(PaymentGateway $gateway): int
    {
        $productie = app()->environment('production');

        $this->line('');
        $this->line('  Omgeving: <options=bold>'.app()->environment().'</>');
        $this->line('');

        $this->eis(! (config('app.debug') && $productie), 'Debugmodus staat uit', 'APP_DEBUG=true op productie toont bezoekers je stacktraces en configuratie.');
        $this->eis(! empty(config('app.key')), 'De applicatiesleutel is gezet', 'Draai php artisan key:generate.');
        $this->eis(str_starts_with((string) config('app.url'), 'https://') || ! $productie, 'APP_URL gebruikt https', 'Zonder https-URL genereert de app links die de browser blokkeert.');
        $this->eis(config('app.locale') === 'nl', 'De taal staat op Nederlands', 'APP_LOCALE hoort nl te zijn.');
        $this->eis(config('app.timezone') === 'Europe/Amsterdam' || ! $productie, 'De tijdzone staat op Europe/Amsterdam', 'Anders staan trainingstijden er een uur naast.');

        $this->eis($this->databaseBereikbaar(), 'De database is bereikbaar', 'Controleer de DB_-instellingen.');
        // file_exists en niet is_dir/is_link: op Windows maakt storage:link een
        // junction, en daar geeft PHP op beide false op terwijl de map er wel is.
        $this->eis(file_exists(public_path('storage')), 'De opslaglink bestaat', 'Draai php artisan storage:link, anders is elk schoollogo een gebroken plaatje.');

        $this->waarschuwing(config('queue.default') !== 'sync', 'De queue draait apart van het verzoek', 'Met QUEUE_CONNECTION=sync wacht het opslaan van een rapport op de mailserver. Zet hem op redis of database en draai een worker.');
        $this->waarschuwing(config('mail.default') !== 'log', 'Er is een echte mailer ingesteld', 'Met MAIL_MAILER=log komt er geen enkele mail aan bij een ouder.');
        $this->waarschuwing($gateway->isConnected(), 'De betaalprovider is aangesloten', 'Zonder MOLLIE_KEY kan een ouder niet betalen; de administratie werkt wel.');
        $this->waarschuwing(! blank(config('app.domain')), 'Er is een basisdomein voor subdomeinen', 'Zonder APP_DOMAIN krijgt geen enkele school een eigen adres.');
        $this->waarschuwing(file_exists(public_path('icons/icon-512.png')), 'De app-iconen staan klaar', 'Draai php artisan playerpath:icons.');
        $this->waarschuwing($this->heeftScholen(), 'Er is minstens één school', 'Zet er een op met php artisan school:create.');

        $this->line('');

        if ($this->problemen > 0) {
            $this->error("  {$this->problemen} blokkerend(e) punt(en). Deze omgeving is nog niet klaar.");

            return self::FAILURE;
        }

        $this->info($this->waarschuwingen > 0
            ? "  Geen blokkerende punten, wel {$this->waarschuwingen} aandachtspunt(en)."
            : '  Alles staat goed.');

        return self::SUCCESS;
    }

    private function eis(bool $goed, string $wat, string $uitleg): void
    {
        if ($goed) {
            $this->line("  <fg=green>✓</> {$wat}");

            return;
        }

        $this->problemen++;
        $this->line("  <fg=red>✗</> {$wat}");
        $this->line("    <fg=gray>{$uitleg}</>");
    }

    private function waarschuwing(bool $goed, string $wat, string $uitleg): void
    {
        if ($goed) {
            $this->line("  <fg=green>✓</> {$wat}");

            return;
        }

        $this->waarschuwingen++;
        $this->line("  <fg=yellow>!</> {$wat}");
        $this->line("    <fg=gray>{$uitleg}</>");
    }

    private function databaseBereikbaar(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function heeftScholen(): bool
    {
        try {
            return School::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
