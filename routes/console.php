<?php

use App\Models\AbandonedCart;
use App\Models\ActivityLog;
use App\Models\PriceAlert;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [ActivityLog::class, AbandonedCart::class, PriceAlert::class]])->daily();

Schedule::command('price-alerts:send')->hourly()->withoutOverlapping();

Schedule::command('payments:check')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('feed:generate')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->runInBackground();
