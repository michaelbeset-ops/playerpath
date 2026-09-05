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
