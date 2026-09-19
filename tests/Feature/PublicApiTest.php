<?php

namespace Tests\Feature;

use App\Models\Broadcaster;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_api_lists_only_published_fixtures_and_broadcasts(): void
    {
        $fixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-20 19:30:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
        ]);
        $draftFixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-21 19:30:00', 'America/Sao_Paulo')->utc(),
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
        $broadcaster = Broadcaster::factory()->create([
            'name' => 'Canal API',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'access_type' => FixtureBroadcast::ACCESS_FREE,
            'source_url' => 'https://example.com/onde-assistir',
            'needs_review' => false,
            'review_status' => FixtureBroadcast::REVIEW_APPROVED,
            'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
        ]);
        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'source_url' => 'javascript:alert(1)',
            'access_type' => FixtureBroadcast::ACCESS_UNKNOWN,
            'needs_review' => false,
            'review_status' => FixtureBroadcast::REVIEW_APPROVED,
            'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
        ]);
        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $draftFixture->id,
            'needs_review' => false,
            'review_status' => FixtureBroadcast::REVIEW_APPROVED,
            'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
        ]);

        $response = $this->getJson('/api/v1/fixtures?date=2026-09-20&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $fixture->id)
            ->assertJsonPath('data.0.starts_at', '2026-09-20T22:30:00+00:00')
            ->assertJsonPath('data.0.starts_at_brasilia', '2026-09-20T19:30:00-03:00')
            ->assertJsonPath('data.0.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.0.broadcasts.0.broadcaster.name', 'Canal API')
            ->assertJsonPath('data.0.broadcasts.0.source_url', 'https://example.com/onde-assistir')
            ->assertJsonPath('data.0.broadcasts.1.source_url', null)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_api_does_not_expose_unpublished_fixture(): void
    {
        $fixture = FootballFixture::factory()->create([
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);

        $this->getJson('/api/v1/fixtures/'.$fixture->id)
            ->assertNotFound();
    }

    public function test_api_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/fixtures?date=20-09-2026&per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'per_page']);
    }
}
