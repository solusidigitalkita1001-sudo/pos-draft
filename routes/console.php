<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminder trial + transisi trial->suspended, active->past_due->suspended.
// Jam 01:00 dipilih supaya tidak bentrok jam sibuk toko.
Schedule::command('subscriptions:process-lifecycle')->dailyAt('01:00');
