<?php

use App\Models\AbandonedCart;
use App\Models\ActivityLog;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [ActivityLog::class, AbandonedCart::class, PriceAlert::class]])->daily();

Schedule::command('price-alerts:send')->hourly()->withoutOverlapping();

Schedule::command('payments:check')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('feed:generate')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->runInBackground();
