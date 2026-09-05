<?php

namespace App\Providers;

use App\Support\Payments\NotConnectedGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eén instantie per request: de actieve school.
        $this->app->singleton(Tenancy::class);

        // De betaalprovider. Zolang er geen koppeling is, zegt de app dat
        // overal eerlijk en maakt hij zelf geen betalingen aan. Mollie
        // aansluiten is straks: een MollieGateway schrijven en hem hier binden.
        $this->app->singleton(PaymentGateway::class, fn () => new NotConnectedGateway);
    }

    public function boot(): void
    {
        // De school komt altijd uit het ingelogde account. Dit is de terugval
        // voor het moment waarop route model binding draait: dat gebeurt in de
        // web-middlewaregroep nog vóór SetCurrentSchool, en zonder deze regel
        // zou elke URL met een {player} of {group} een 404 geven.
        // SetCurrentSchool blijft de plek waar geweigerd wordt.
        $this->app->make(Tenancy::class)->resolveUsing(
            fn () => auth()->user()?->school
        );
    }
}
