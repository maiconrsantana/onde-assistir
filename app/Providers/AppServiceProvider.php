<?php

namespace App\Providers;

use App\Contracts\FootballDataProvider;
use App\Integrations\ApiFootball\ApiFootballProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FootballDataProvider::class, fn () => new ApiFootballProvider(
            baseUrl: config('services.api_football.base_url'),
            key: config('services.api_football.key'),
            leagueId: config('services.api_football.brasileirao_league_id'),
            season: config('services.api_football.season'),
            timeout: config('services.api_football.timeout'),
            retryTimes: config('services.api_football.retry_times'),
            retrySleep: config('services.api_football.retry_sleep'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
