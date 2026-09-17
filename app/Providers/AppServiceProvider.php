<?php

namespace App\Providers;

use App\Contracts\BroadcastFinder;
use App\Contracts\FootballDataProvider;
use App\Integrations\ApiFootball\ApiFootballProvider;
use App\Integrations\OpenAI\OpenAIBroadcastFinder;
use App\Integrations\TheSportsDb\TheSportsDbBroadcastFinder;
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

        $this->app->bind(BroadcastFinder::class, fn () => new TheSportsDbBroadcastFinder(
            baseUrl: config('services.thesportsdb.base_url'),
            key: config('services.thesportsdb.key'),
            timeout: config('services.thesportsdb.timeout'),
            retryTimes: config('services.thesportsdb.retry_times'),
            retrySleep: config('services.thesportsdb.retry_sleep'),
            country: config('services.thesportsdb.country'),
        ));

        $this->app->bind(OpenAIBroadcastFinder::class, fn () => new OpenAIBroadcastFinder(
            baseUrl: config('services.openai.base_url'),
            key: config('services.openai.key'),
            model: config('services.openai.model'),
            enabled: config('services.openai.broadcast_search_enabled'),
            webSearchTool: config('services.openai.web_search_tool'),
            timeout: config('services.openai.timeout'),
            retryTimes: config('services.openai.retry_times'),
            retrySleep: config('services.openai.retry_sleep'),
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
