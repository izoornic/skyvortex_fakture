<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Contracts produce their drafts in the morning, before anyone opens the
 * application. Running it again the same day does nothing: a contract records
 * the period it has already generated.
 */
Schedule::command('contracts:generate-drafts')
    ->dailyAt('06:00')
    ->withoutOverlapping();
