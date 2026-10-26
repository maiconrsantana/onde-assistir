<?php

use App\Services\Automation\AutomationScheduler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn (AutomationScheduler $scheduler): int => $scheduler->dispatchDue())
    ->name('automation.dispatch-due')
    ->everyMinute()
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping(1);
