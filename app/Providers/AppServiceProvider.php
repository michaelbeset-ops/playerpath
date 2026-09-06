<?php

namespace App\Providers;

use App\Policies\PlatformPolicy;
use App\Support\Payments\MollieGateway;
use App\Support\Payments\NotConnectedGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mollie\Api\MollieApiClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eén instantie per request: de actieve school.
        $this->app->singleton(Tenancy::class);

        // De betaalprovider. Zonder sleutel in .env blijft alles op
        // NotConnectedGateway staan: de schermen zeggen dat eerlijk en er wordt
        // nooit een betaling gestart. Een sleutel toevoegen is genoeg om de
        // hele keten aan te zetten; aan de schermen verandert er niets.
        $this->app->singleton(MollieApiClient::class, function () {
            $client = new MollieApiClient;
            $client->setApiKey((string) config('services.mollie.key'));

            return $client;
        });

        $this->app->singleton(PaymentGateway::class, function ($app) {
            return blank(config('services.mollie.key'))
                ? new NotConnectedGateway
                : new MollieGateway($app->make(MollieApiClient::class));
        });
    }

    public function boot(): void
    {
        // Platformbeheer hangt niet aan een model, dus het gaat via gates in
        // plaats van een modelpolicy. Ze staan hier zodat de rolcontrole ook
        // buiten de middleware nog een keer plaatsvindt: twee sloten op
        // dezelfde deur, net als bij de andere policies.
        Gate::define('platform.access', [PlatformPolicy::class, 'access']);
        Gate::define('platform.manageSchools', [PlatformPolicy::class, 'manageSchools']);

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
