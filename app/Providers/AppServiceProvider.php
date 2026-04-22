<?php

namespace App\Providers;

use App\Contracts\ReservationCloudPushGatewayContract;
use App\Support\AuthEmail;
use App\Support\HttpReservationCloudPushGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReservationCloudPushGatewayContract::class, HttpReservationCloudPushGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-login', function (Request $request) {
            $email = AuthEmail::normalize($request->input('email', ''));
            $ip = (string) $request->ip();

            return Limit::perMinute(5)->by($email.'|'.$ip);
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $email = AuthEmail::normalize($request->input('email', ''));
            $ip = (string) $request->ip();

            // 5 attempts per 10 minutes (stricter window).
            return Limit::perMinutes(10, 5)->by($email.'|'.$ip);
        });

        RateLimiter::for('otp-resend', function (Request $request) {
            $email = AuthEmail::normalize($request->input('email', ''));
            $ip = (string) $request->ip();

            return Limit::perMinutes(10, 3)->by($email.'|'.$ip);
        });
    }
}
