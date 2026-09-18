<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('football:sync --days=14')
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping(120);

Schedule::command('football:refresh-broadcasts --days=7')
    ->dailyAt('16:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping(120);
