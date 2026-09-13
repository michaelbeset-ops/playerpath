<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Herinneringen bij openstaande betalingen. Eén keer per dag en op een
// burgerlijk tijdstip: een betaalmail om drie uur 's nachts leest niemand.
Schedule::command('payments:remind')->dailyAt('09:00');

// De facturenloop. Dagelijks, want abonnementen beginnen op verschillende
// dagen van de maand; de loop is idempotent en maakt nooit twee keer dezelfde
// rekening. Vóór de herinneringen, zodat een verse rekening niet meteen als
// achterstallig wordt gezien.
Schedule::command('payments:generate')->dailyAt('08:00');

// De vooraankondiging: elke incasso wordt eerst aangekondigd, en pas
// veertien dagen later afgeschreven. Vóór de incassoronde.
Schedule::command('payments:prenotify')->dailyAt('08:15');

// De incassoronde, na de facturenloop zodat verse rekeningen meteen meegaan.
Schedule::command('payments:collect')->dailyAt('08:30');

// De maandelijkse samenvatting aan ouders en spelers. Op de eerste van de
// maand, ná de facturenloop: eerst de rekening, dan het goede nieuws.
Schedule::command('players:digest')->monthlyOn(1, '10:00');

// De verjaardagsfelicitatie. Vroeg genoeg om 's ochtends binnen te komen, en
// alleen bij scholen die hem zelf hebben aangezet.
Schedule::command('players:birthday')->dailyAt('08:00');

// De levensloop van inschrijvingen: activeren, verleng-uitnodigingen,
// beëindigen, en abonnementen waarvan de opzegtermijn om is.
Schedule::command('enrollments:lifecycle')->dailyAt('04:00');

// Leeftijdscategorieen vaststellen. Bijna altijd verandert er niets; rond de
// jaarwisseling gaat een deel omhoog en wordt hun oude kaart bewaard.
Schedule::command('players:categories')->dailyAt('03:00');

// Seizoenen sluiten de dag na hun einddatum: kaart bewaren, punten opnieuw.
Schedule::command('seasons:close')->dailyAt('02:30');
