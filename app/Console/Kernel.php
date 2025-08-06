<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Traiter les emails avec le système optimisé (batch et rate limiting)
        $schedule->command('emails:process-optimized')
            ->everyMinute()
            ->withoutOverlapping(10)  // Timeout de 10 minutes pour éviter les chevauchements
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/email-processing-optimized.log'));
            
        // Traiter la queue des jobs ProcessEmailAddressJob
        // Pas besoin de withoutOverlapping car les jobs sont ShouldBeUnique
        // timeout=50 : Ne prend plus de nouveaux jobs après 50 secondes
        // Les jobs en cours peuvent continuer jusqu'à leur propre timeout (120 sec)
        $schedule->command('emails:process-addresses --timeout=50')
            ->everyMinute()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/email-queue-processing.log'));
            
        // Rafraîchir les tokens OAuth toutes les 30 minutes
        $schedule->command('oauth:refresh-tokens')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/oauth-refresh.log'));
            
        // Vérifier les connexions email toutes les 20 minutes
        // Laravel n'a pas de méthode everyTwentyMinutes(), on utilise cron
        $schedule->command('email:check-connections')
            ->cron('*/20 * * * *')  // Toutes les 20 minutes
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/email-connection-check.log'));
            
        // Réparer les comptes Microsoft défaillants toutes les heures
        $schedule->command('microsoft:repair-accounts')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/microsoft-repair.log'));
            
        // Nettoyer les métriques anciennes une fois par jour à 3h du matin
        $schedule->command('metrics:clean')
            ->dailyAt('03:00')
            ->timezone('Europe/Paris')
            ->appendOutputTo(storage_path('logs/metrics-cleanup.log'));
            
        // Archiver les métriques chaque jour à 23h55
        $schedule->command('metrics:archive')
            ->dailyAt('23:55')
            ->timezone('Europe/Paris')
            ->appendOutputTo(storage_path('logs/metrics-archive.log'));
            
        // Nettoyer les tests anciens une fois par jour à 2h du matin
        $schedule->command('tests:clean --force')
            ->dailyAt('02:00')
            ->timezone('Europe/Paris')
            ->appendOutputTo(storage_path('logs/tests-cleanup.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
