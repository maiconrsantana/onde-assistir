<?php

namespace Database\Seeders;

use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\Competition;
use App\Models\FixtureBroadcast;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use App\Models\PublicationSetting;
use App\Models\RoundPublication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        PublicationSetting::factory()->create([
            'publication_mode' => PublicationSetting::MODE_MANUAL,
        ]);

        $competition = Competition::factory()->create([
            'provider' => 'api_football',
            'external_id' => 'api-football-brasileirao-serie-a-2026',
            'name' => 'Campeonato Brasileiro Serie A',
            'slug' => 'campeonato-brasileiro-serie-a',
            'country_code' => 'BR',
            'season_name' => '2026',
            'active' => true,
        ]);

        $teams = collect([
            ['name' => 'Corinthians', 'short_name' => 'COR'],
            ['name' => 'Palmeiras', 'short_name' => 'PAL'],
            ['name' => 'Flamengo', 'short_name' => 'FLA'],
            ['name' => 'Sao Paulo', 'short_name' => 'SAO'],
        ])->map(fn (array $team, int $index) => Team::factory()->create([
            'provider' => 'api_football',
            'external_id' => 'team-'.($index + 1),
            'name' => $team['name'],
            'short_name' => $team['short_name'],
            'slug' => Str::slug($team['name']),
        ]));

        $globo = Broadcaster::factory()->create([
            'name' => 'Globo',
            'slug' => 'globo',
            'type' => Broadcaster::TYPE_TV_OPEN,
        ]);

        $premiere = Broadcaster::factory()->create([
            'name' => 'Premiere',
            'slug' => 'premiere',
            'type' => Broadcaster::TYPE_STREAMING,
        ]);

        $firstFixture = FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'external_id' => 'fixture-1',
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'round' => 'Rodada 1',
            'starts_at' => now()->utc()->addDays(2)->setTime(19, 30),
            'venue' => 'Neo Quimica Arena',
            'city' => 'Sao Paulo',
            'resolution_status' => FootballFixture::RESOLUTION_RESOLVED,
        ]);

        FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'external_id' => 'fixture-2',
            'home_team_id' => $teams[2]->id,
            'away_team_id' => $teams[3]->id,
            'round' => 'Rodada 1',
            'starts_at' => now()->utc()->addDays(3)->setTime(21, 0),
            'venue' => 'Maracana',
            'city' => 'Rio de Janeiro',
        ]);

        FixtureProviderMapping::factory()->create([
            'football_fixture_id' => $firstFixture->id,
            'provider' => FixtureProviderMapping::PROVIDER_THESPORTSDB,
            'external_event_id' => 'sportsdb-event-1',
            'match_score' => 0.9500,
        ]);

        $broadcastSource = BroadcastSource::factory()->create([
            'football_fixture_id' => $firstFixture->id,
            'provider' => BroadcastSource::PROVIDER_THESPORTSDB,
            'external_event_id' => 'sportsdb-event-1',
            'selected' => true,
            'query_hash' => 'seeded-sportsdb-fixture-1',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $firstFixture->id,
            'broadcaster_id' => $globo->id,
            'broadcast_source_id' => $broadcastSource->id,
            'access_type' => FixtureBroadcast::ACCESS_FREE,
            'source_type' => FixtureBroadcast::SOURCE_THESPORTSDB,
            'source_url' => 'https://ge.globo.com/',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $firstFixture->id,
            'broadcaster_id' => $premiere->id,
            'broadcast_source_id' => $broadcastSource->id,
            'access_type' => FixtureBroadcast::ACCESS_SUBSCRIPTION,
            'source_type' => FixtureBroadcast::SOURCE_THESPORTSDB,
            'source_url' => 'https://premiere.globo.com/',
        ]);

        RoundPublication::factory()->create([
            'competition_id' => $competition->id,
            'season_name' => '2026',
            'round' => 'Rodada 1',
            'publication_status' => RoundPublication::STATUS_DRAFT,
            'publication_mode' => PublicationSetting::MODE_MANUAL,
        ]);
    }
}
