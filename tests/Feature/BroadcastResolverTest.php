<?php

namespace Tests\Feature;

use App\Contracts\BroadcastFinder;
use App\Data\Broadcast\BroadcastChannelData;
use App\Data\Broadcast\BroadcastSearchResult;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use App\Services\Broadcast\BroadcastResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BroadcastResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_found_broadcast_is_persisted_as_draft_waiting_manual_review(): void
    {
        $fixture = FootballFixture::factory()->create();

        $result = app(BroadcastResolver::class)->resolve([
            $fixture,
        ]);

        $this->assertSame(1, $result->resolved);
        $this->assertSame(1, $result->broadcastsCreated);

        $this->assertDatabaseHas('broadcast_sources', [
            'football_fixture_id' => $fixture->id,
            'provider' => BroadcastSource::PROVIDER_THESPORTSDB,
            'external_event_id' => 'tsdb-100',
            'result_status' => BroadcastSource::RESULT_FOUND,
            'selected' => true,
        ]);

        $this->assertDatabaseHas('broadcasters', [
            'name' => 'SporTV',
            'slug' => 'sportv',
            'type' => Broadcaster::TYPE_TV_CLOSED,
        ]);

        $this->assertDatabaseHas('fixture_broadcasts', [
            'football_fixture_id' => $fixture->id,
            'source_type' => FixtureBroadcast::SOURCE_THESPORTSDB,
            'access_type' => FixtureBroadcast::ACCESS_SUBSCRIPTION,
            'country_code' => 'BR',
            'needs_review' => true,
        ]);

        $this->assertDatabaseHas('fixture_provider_mappings', [
            'football_fixture_id' => $fixture->id,
            'provider' => FixtureProviderMapping::PROVIDER_THESPORTSDB,
            'external_event_id' => 'tsdb-100',
        ]);

        $this->assertDatabaseHas('football_fixtures', [
            'id' => $fixture->id,
            'resolution_status' => FootballFixture::RESOLUTION_RESOLVED,
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
    }

    public function test_second_resolution_updates_without_duplicate_broadcasts(): void
    {
        $fixture = FootballFixture::factory()->create();
        $resolver = app(BroadcastResolver::class);

        $resolver->resolve([$fixture]);
        $result = $resolver->resolve([$fixture->fresh()]);

        $this->assertSame(1, $result->broadcastsUpdated);
        $this->assertDatabaseCount('broadcast_sources', 1);
        $this->assertDatabaseCount('broadcasters', 1);
        $this->assertDatabaseCount('fixture_broadcasts', 1);
        $this->assertDatabaseCount('fixture_provider_mappings', 1);
    }

    public function test_not_found_marks_fixture_without_creating_broadcast(): void
    {
        $fixture = FootballFixture::factory()->create();

        $this->app->bind(BroadcastFinder::class, fn () => new class implements BroadcastFinder
        {
            public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
            {
                return new BroadcastSearchResult(
                    provider: BroadcastSource::PROVIDER_THESPORTSDB,
                    status: BroadcastSource::RESULT_NOT_FOUND,
                    queryHash: 'sportsdb-not-found-'.$fixture->id,
                    evidenceSummary: 'Sem transmissao.',
                );
            }
        });

        $result = app(BroadcastResolver::class)->resolve([$fixture]);

        $this->assertSame(1, $result->notFound);
        $this->assertDatabaseCount('fixture_broadcasts', 0);
        $this->assertDatabaseHas('football_fixtures', [
            'id' => $fixture->id,
            'resolution_status' => FootballFixture::RESOLUTION_NOT_FOUND,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
    }

    public function test_finder_failure_is_recorded_without_throwing(): void
    {
        $fixture = FootballFixture::factory()->create();

        $this->app->bind(BroadcastFinder::class, fn () => new class implements BroadcastFinder
        {
            public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
            {
                throw new RuntimeException('rate limited');
            }
        });

        $result = app(BroadcastResolver::class)->resolve([$fixture]);

        $this->assertSame(1, $result->errors);
        $this->assertDatabaseHas('broadcast_sources', [
            'football_fixture_id' => $fixture->id,
            'provider' => BroadcastSource::PROVIDER_THESPORTSDB,
            'result_status' => BroadcastSource::RESULT_ERROR,
            'selected' => false,
        ]);
        $this->assertDatabaseHas('football_fixtures', [
            'id' => $fixture->id,
            'resolution_status' => FootballFixture::RESOLUTION_ERROR,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
    }

    public function test_resolve_command_processes_pending_fixtures(): void
    {
        $fixture = FootballFixture::factory()->create([
            'starts_at' => '2026-09-18 15:00:00',
        ]);

        $this->artisan('football:resolve-broadcasts', [
            '--from' => '2026-09-18',
            '--to' => '2026-09-18',
        ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('fixture_broadcasts', [
            'football_fixture_id' => $fixture->id,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(BroadcastFinder::class, fn () => new class implements BroadcastFinder
        {
            public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
            {
                return new BroadcastSearchResult(
                    provider: BroadcastSource::PROVIDER_THESPORTSDB,
                    status: BroadcastSource::RESULT_FOUND,
                    queryHash: 'sportsdb-found-'.$fixture->id,
                    channels: [
                        new BroadcastChannelData(
                            name: 'SporTV',
                            type: Broadcaster::TYPE_TV_CLOSED,
                            accessType: FixtureBroadcast::ACCESS_SUBSCRIPTION,
                            sourceUrl: 'https://example.com/sportv',
                        ),
                    ],
                    evidence: [[
                        'url' => 'https://www.thesportsdb.com/event/tsdb-100',
                        'publisher' => 'TheSportsDB',
                        'summary' => 'Agenda de TV retornada.',
                    ]],
                    evidenceSummary: 'Transmissao encontrada.',
                    rawResponse: [
                        'idEvent' => 'tsdb-100',
                    ],
                    externalEventId: 'tsdb-100',
                    providerConfidence: 0.95,
                    calculatedConfidence: 0.95,
                    matchScore: 0.95,
                );
            }
        });
    }
}
