<?php

namespace Tests\Feature;

use App\Integrations\TheSportsDb\TheSportsDbBroadcastFinder;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\Competition;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TheSportsDbBroadcastFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_finder_maps_brazilian_tv_channels_for_matching_event(): void
    {
        Http::fake([
            'https://www.thesportsdb.com/api/v1/json/123/searchevents.php*' => Http::response([
                'event' => [[
                    'idEvent' => 'tsdb-100',
                    'strEvent' => 'Corinthians vs Palmeiras',
                    'strHomeTeam' => 'Corinthians',
                    'strAwayTeam' => 'Palmeiras',
                    'strLeague' => 'Serie A',
                    'dateEvent' => '2026-09-18',
                ]],
            ]),
            'https://www.thesportsdb.com/api/v1/json/123/lookuptv.php*' => Http::response([
                'tvevent' => [[
                    'strChannel' => 'SporTV',
                    'strCountry' => 'Brazil',
                    'strWebsite' => 'https://example.com/sportv',
                ], [
                    'strChannel' => 'ESPN',
                    'strCountry' => 'United States',
                ]],
            ]),
        ]);

        $result = $this->finder()->findForFixture($this->fixture());

        $this->assertSame(BroadcastSource::RESULT_FOUND, $result->status);
        $this->assertSame('tsdb-100', $result->externalEventId);
        $this->assertCount(1, $result->channels);
        $this->assertSame('SporTV', $result->channels[0]->name);
        $this->assertSame(Broadcaster::TYPE_TV_CLOSED, $result->channels[0]->type);
        $this->assertSame(FixtureBroadcast::ACCESS_SUBSCRIPTION, $result->channels[0]->accessType);
    }

    public function test_finder_returns_uncertain_when_event_match_is_weak(): void
    {
        Http::fake([
            'https://www.thesportsdb.com/api/v1/json/123/searchevents.php*' => Http::response([
                'event' => [[
                    'idEvent' => 'tsdb-200',
                    'strEvent' => 'Santos vs Flamengo',
                    'strHomeTeam' => 'Santos',
                    'strAwayTeam' => 'Flamengo',
                    'strLeague' => 'Serie A',
                    'dateEvent' => '2026-09-18',
                ]],
            ]),
        ]);

        $result = $this->finder()->findForFixture($this->fixture());

        $this->assertSame(BroadcastSource::RESULT_UNCERTAIN, $result->status);
        $this->assertEqualsWithDelta(0.3, $result->calculatedConfidence, 0.0001);
    }

    private function finder(): TheSportsDbBroadcastFinder
    {
        return new TheSportsDbBroadcastFinder(
            baseUrl: 'https://www.thesportsdb.com/api/v1/json',
            key: '123',
        );
    }

    private function fixture(): FootballFixture
    {
        $competition = Competition::factory()->create([
            'name' => 'Serie A',
            'season_name' => '2026',
        ]);

        $homeTeam = Team::factory()->create([
            'name' => 'Corinthians',
        ]);

        $awayTeam = Team::factory()->create([
            'name' => 'Palmeiras',
        ]);

        return FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'starts_at' => '2026-09-18 15:00:00',
        ]);
    }
}
