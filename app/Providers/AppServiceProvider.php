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
        // Force HTTPS in production
        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
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
