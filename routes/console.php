<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @phpstan-ignore variable.undefined */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Game Background Processing Scheduler
|--------------------------------------------------------------------------
|
| These scheduled tasks handle the game's background processing:
| - Colony cycles (resource production, population growth)
| - Market events (price fluctuations, supply/demand)
| - Fuel regeneration (passive ship fuel recovery)
|
| To run the scheduler, add to your cron:
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Economy Tick - Every 5 minutes
// Processes mining extraction, shock decay, and refreshes stats cache
Schedule::command('economy:tick')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Economy tick completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Economy tick failed');
    });

// Fuel Regeneration - Every 5 minutes
// Regenerates fuel for all player ships based on time elapsed
Schedule::command('fuel:regenerate')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Fuel regeneration completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Fuel regeneration failed');
    });

// Colony Cycles - Every hour
// Processes resource production, population growth, building construction, ship production
Schedule::command('colony:process-cycles')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Colony cycles processed successfully');
    })
    ->onFailure(function () {
        \Log::error('Colony cycle processing failed');
    });

// Market Events - Every 6 hours
// Deactivates expired events and randomly generates new market events
Schedule::command('market:process-events')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Market events processed successfully');
    })
    ->onFailure(function () {
        \Log::error('Market event processing failed');
    });

// Contract Expiry - Every hour
// Expires POSTED contracts past their expiration date
// Fails ACCEPTED contracts that exceeded their deadline
Schedule::command('contracts:expire')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Contract expiry processing completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Contract expiry processing failed');
    });
