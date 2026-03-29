<?php

namespace App\Providers;

use App\Contracts\GoogleCredentialVerifier as GoogleCredentialVerifierContract;
use App\Services\GoogleCredentialVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleCredentialVerifierContract::class, GoogleCredentialVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
