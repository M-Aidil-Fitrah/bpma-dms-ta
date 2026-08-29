<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:update-expired-status')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('documents:purge-trash')
    ->dailyAt('00:20')
    ->withoutOverlapping();

Schedule::command('activitylog:clean --force')
    ->dailyAt('00:40')
    ->withoutOverlapping();
