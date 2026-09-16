<?php

namespace Tests\Feature;

use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\Competition;
use App\Models\FixtureBroadcast;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use App\Models\PublicationSetting;
use App\Models\RoundPublication;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FootballDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_relationships_are_available(): void
    {
        $competition = Competition::factory()->create();
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $broadcaster = Broadcaster::factory()->create();

        $fixture = FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
        $providerMapping = FixtureProviderMapping::factory()->create([
            'football_fixture_id' => $fixture->id,
        ]);
        $broadcastSource = BroadcastSource::factory()->create([
            'football_fixture_id' => $fixture->id,
            'selected' => true,
        ]);

        $broadcast = FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'broadcast_source_id' => $broadcastSource->id,
        ]);

        $this->assertTrue($fixture->competition->is($competition));
        $this->assertTrue($fixture->homeTeam->is($homeTeam));
        $this->assertTrue($fixture->awayTeam->is($awayTeam));
        $this->assertTrue($fixture->fixtureBroadcasts->first()->is($broadcast));
        $this->assertTrue($fixture->providerMappings->first()->is($providerMapping));
        $this->assertTrue($fixture->broadcastSources->first()->is($broadcastSource));
        $this->assertTrue($fixture->broadcasters->first()->is($broadcaster));
        $this->assertTrue($broadcast->broadcastSource->is($broadcastSource));
        $this->assertTrue($broadcaster->fixtures->first()->is($fixture));
        $this->assertTrue($homeTeam->homeFixtures->first()->is($fixture));
        $this->assertTrue($awayTeam->awayFixtures->first()->is($fixture));
    }

    public function test_provider_external_id_is_unique_per_provider(): void
    {
        Competition::factory()->create([
            'provider' => 'api_football',
            'external_id' => 'competition-123',
            'slug' => 'brasileirao-serie-a',
        ]);

        Competition::factory()->create([
            'provider' => 'manual',
            'external_id' => 'competition-123',
            'slug' => 'brasileirao-serie-a-manual',
        ]);

        $this->expectException(QueryException::class);

        Competition::factory()->create([
            'provider' => 'api_football',
            'external_id' => 'competition-123',
            'slug' => 'brasileirao-serie-a-copy',
        ]);
    }

    public function test_fixture_broadcast_is_unique_by_fixture_broadcaster_and_country(): void
    {
        $fixture = FootballFixture::factory()->create();
        $broadcaster = Broadcaster::factory()->create();

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'country_code' => 'BR',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'country_code' => 'AR',
        ]);

        $this->expectException(QueryException::class);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'country_code' => 'BR',
        ]);
    }

    public function test_provider_mapping_external_event_is_unique_per_provider(): void
    {
        FixtureProviderMapping::factory()->create([
            'provider' => FixtureProviderMapping::PROVIDER_THESPORTSDB,
            'external_event_id' => 'event-123',
        ]);

        FixtureProviderMapping::factory()->create([
            'provider' => FixtureProviderMapping::PROVIDER_API_FOOTBALL,
            'external_event_id' => 'event-123',
        ]);

        $this->expectException(QueryException::class);

        FixtureProviderMapping::factory()->create([
            'provider' => FixtureProviderMapping::PROVIDER_THESPORTSDB,
            'external_event_id' => 'event-123',
        ]);
    }

    public function test_broadcast_source_query_hash_is_unique_per_provider(): void
    {
        BroadcastSource::factory()->create([
            'provider' => BroadcastSource::PROVIDER_OPENAI,
            'query_hash' => 'fixture-search-hash',
        ]);

        BroadcastSource::factory()->create([
            'provider' => BroadcastSource::PROVIDER_THESPORTSDB,
            'query_hash' => 'fixture-search-hash',
        ]);

        $this->expectException(QueryException::class);

        BroadcastSource::factory()->create([
            'provider' => BroadcastSource::PROVIDER_OPENAI,
            'query_hash' => 'fixture-search-hash',
        ]);
    }

    public function test_domain_casts_are_applied(): void
    {
        $competition = Competition::factory()->create([
            'active' => 1,
        ]);

        $fixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-05-10 22:00:00', 'UTC'),
            'raw_payload' => [
                'id' => 123,
                'status' => 'scheduled',
            ],
            'synced_at' => Carbon::parse('2026-05-01 12:00:00', 'UTC'),
            'resolved_at' => Carbon::parse('2026-05-01 12:30:00', 'UTC'),
            'published_at' => null,
        ]);

        $source = BroadcastSource::factory()->create([
            'football_fixture_id' => $fixture->id,
            'channels' => [
                ['name' => 'Globo', 'type' => 'tv_open'],
            ],
            'evidence' => [
                ['url' => 'https://example.com/fonte', 'publisher' => 'Fonte'],
            ],
            'provider_confidence' => 0.8000,
            'calculated_confidence' => 0.9000,
            'selected' => 1,
        ]);

        $broadcast = FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcast_source_id' => $source->id,
            'confidence' => 0.8750,
            'verified_at' => Carbon::parse('2026-05-01 13:00:00', 'UTC'),
            'needs_review' => 1,
        ]);

        $this->assertTrue($competition->refresh()->active);
        $this->assertInstanceOf(Carbon::class, $fixture->refresh()->starts_at);
        $this->assertSame('scheduled', $fixture->raw_payload['status']);
        $this->assertInstanceOf(Carbon::class, $fixture->synced_at);
        $this->assertInstanceOf(Carbon::class, $fixture->resolved_at);
        $this->assertNull($fixture->published_at);
        $this->assertSame('Globo', $source->refresh()->channels[0]['name']);
        $this->assertSame('Fonte', $source->evidence[0]['publisher']);
        $this->assertSame(0.8, $source->provider_confidence);
        $this->assertSame(0.9, $source->calculated_confidence);
        $this->assertTrue($source->selected);
        $this->assertSame(0.875, $broadcast->refresh()->confidence);
        $this->assertInstanceOf(Carbon::class, $broadcast->verified_at);
        $this->assertTrue($broadcast->needs_review);
    }

    public function test_resolved_fixture_stays_draft_in_manual_publication_mode(): void
    {
        PublicationSetting::factory()->create([
            'publication_mode' => PublicationSetting::MODE_MANUAL,
        ]);

        $fixture = FootballFixture::factory()->create([
            'resolution_status' => FootballFixture::RESOLUTION_RESOLVED,
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
            'resolved_at' => now()->utc(),
            'published_at' => null,
        ]);

        $this->assertSame(PublicationSetting::MODE_MANUAL, PublicationSetting::first()->publication_mode);
        $this->assertSame(FootballFixture::RESOLUTION_RESOLVED, $fixture->resolution_status);
        $this->assertSame(FootballFixture::REVIEW_PENDING, $fixture->review_status);
        $this->assertSame(FootballFixture::PUBLICATION_DRAFT, $fixture->publication_status);
        $this->assertNull($fixture->published_at);
    }

    public function test_round_publication_tracks_manual_approval_and_publication_status(): void
    {
        $publication = RoundPublication::factory()->create([
            'publication_status' => RoundPublication::STATUS_DRAFT,
            'publication_mode' => PublicationSetting::MODE_MANUAL,
            'audit_payload' => [
                'reason' => 'created for review',
            ],
        ]);

        $this->assertTrue($publication->competition()->exists());
        $this->assertSame('created for review', $publication->audit_payload['reason']);
        $this->assertSame(RoundPublication::STATUS_DRAFT, $publication->publication_status);
        $this->assertSame(PublicationSetting::MODE_MANUAL, $publication->publication_mode);
    }

    public function test_database_seeder_creates_visual_development_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('competitions', 1);
        $this->assertDatabaseCount('teams', 4);
        $this->assertDatabaseCount('football_fixtures', 2);
        $this->assertDatabaseCount('broadcasters', 2);
        $this->assertDatabaseCount('fixture_provider_mappings', 1);
        $this->assertDatabaseCount('broadcast_sources', 1);
        $this->assertDatabaseCount('fixture_broadcasts', 2);
        $this->assertDatabaseCount('publication_settings', 1);
        $this->assertDatabaseCount('round_publications', 1);

        $this->assertDatabaseHas('competitions', [
            'slug' => 'campeonato-brasileiro-serie-a',
            'active' => true,
        ]);

        $this->assertDatabaseHas('publication_settings', [
            'publication_mode' => PublicationSetting::MODE_MANUAL,
        ]);

        $this->assertDatabaseHas('football_fixtures', [
            'external_id' => 'fixture-1',
            'resolution_status' => FootballFixture::RESOLUTION_RESOLVED,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
    }
}
