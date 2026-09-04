<?php

namespace App\Providers;

use App\Support\Tenancy\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eén instantie per request: de actieve school.
        $this->app->singleton(Tenancy::class);
    }

    public function boot(): void
    {
        //
    }
}
