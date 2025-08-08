<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;
use App\Providers\GmailSocialiteProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production with better proxy detection
        if (config('app.env') === 'production') {
            // Check if request is already secure or comes through a proxy
            if (request()->header('X-Forwarded-Proto') === 'https' || request()->secure()) {
                \URL::forceScheme('https');
            } elseif (!request()->is('test') && !request()->is('test/*')) {
                // Only force HTTPS for non-POST routes to avoid redirect loops
                \URL::forceScheme('https');
            }
        }

        // Use Bootstrap 5 for pagination
        Paginator::useBootstrapFive();

        // Register custom Gmail Socialite driver
        $socialite = $this->app->make(Factory::class);
        
        $socialite->extend('gmail', function ($app) use ($socialite) {
            $config = $app['config']['services.gmail'];
            return $socialite->buildProvider(GmailSocialiteProvider::class, $config);
        });
    }
}
