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

// De incassoronde, na de facturenloop zodat verse rekeningen meteen meegaan.
Schedule::command('payments:collect')->dailyAt('08:30');
