<?php

namespace Tests\Feature;

use App\Models\Broadcaster;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Services\Operations\PublicScheduleCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'America/Sao_Paulo'));
        $this->app->make(PublicScheduleCache::class)->invalidate();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_public_schedule_lists_only_approved_and_published_fixtures(): void
    {
        $publishedFixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-20 19:30:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
            'published_at' => now()->utc(),
        ]);

        $draftFixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-21 21:00:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);

        $pendingReviewFixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-22 21:00:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
            'published_at' => now()->utc(),
        ]);

        $response = $this->get(route('public.schedule'));

        $response
            ->assertOk()
            ->assertSeeText($publishedFixture->homeTeam->name)
            ->assertSeeText($publishedFixture->awayTeam->name)
            ->assertDontSeeText($draftFixture->homeTeam->name)
            ->assertDontSeeText($draftFixture->awayTeam->name)
            ->assertDontSeeText($pendingReviewFixture->homeTeam->name)
            ->assertDontSeeText($pendingReviewFixture->awayTeam->name);
    }

    public function test_public_schedule_shows_broadcasts_that_are_ready_for_visitors(): void
    {
        $fixture = FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-20 16:00:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
            'published_at' => now()->utc(),
        ]);

        $publishedBroadcaster = Broadcaster::factory()->create([
            'name' => 'Globo',
            'slug' => 'globo',
            'type' => Broadcaster::TYPE_TV_OPEN,
        ]);

        $reviewBroadcaster = Broadcaster::factory()->create([
            'name' => 'Canal em Revisao',
            'slug' => 'canal-em-revisao',
        ]);

        $foreignBroadcaster = Broadcaster::factory()->create([
            'name' => 'Canal Argentina',
            'slug' => 'canal-argentina',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $publishedBroadcaster->id,
            'access_type' => FixtureBroadcast::ACCESS_FREE,
            'country_code' => 'BR',
            'needs_review' => false,
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $reviewBroadcaster->id,
            'country_code' => 'BR',
            'needs_review' => true,
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $foreignBroadcaster->id,
            'country_code' => 'AR',
            'needs_review' => false,
        ]);

        $response = $this->get(route('public.schedule'));

        $response
            ->assertOk()
            ->assertSeeText('Globo')
            ->assertSeeText('TV aberta')
            ->assertSeeText('Gratuito')
            ->assertDontSeeText('Canal em Revisao')
            ->assertDontSeeText('Canal Argentina');
    }

    public function test_public_schedule_shows_not_disclosed_when_no_publishable_broadcast_exists(): void
    {
        FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-20 11:00:00', 'America/Sao_Paulo')->utc(),
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
            'published_at' => now()->utc(),
        ]);

        $response = $this->get(route('public.schedule'));

        $response
            ->assertOk()
            ->assertSeeText('Transmissao ainda nao divulgada');
    }

    public function test_public_schedule_has_empty_state_without_published_fixtures(): void
    {
        FootballFixture::factory()->create([
            'starts_at' => Carbon::parse('2026-09-20 11:00:00', 'America/Sao_Paulo')->utc(),
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);

        $response = $this->get(route('public.schedule'));

        $response
            ->assertOk()
            ->assertSeeText('Nenhum jogo publicado no momento');
    }
}
