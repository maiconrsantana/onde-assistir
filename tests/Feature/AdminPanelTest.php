<?php

namespace Tests\Feature;

use App\Contracts\BroadcastFinder;
use App\Data\Broadcast\BroadcastChannelData;
use App\Data\Broadcast\BroadcastSearchResult;
use App\Filament\Resources\FixtureBroadcasts\Pages\CreateFixtureBroadcast;
use App\Filament\Resources\FixtureBroadcasts\Pages\ListFixtureBroadcasts;
use App\Filament\Resources\FootballFixtures\FootballFixtureResource;
use App\Filament\Resources\FootballFixtures\Pages\EditFootballFixture;
use App\Filament\Resources\FootballFixtures\Pages\ListFootballFixtures;
use App\Filament\Resources\FootballFixtures\RelationManagers\FixtureBroadcastsRelationManager;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Models\User;
use App\Services\Broadcast\BroadcastResolver;
use App\Services\Operations\PublicScheduleCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_panel(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_user_can_access_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_non_admin_user_cannot_access_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_creates_manual_broadcast_with_history(): void
    {
        $fixture = FootballFixture::factory()->create([
            'starts_at' => '2026-09-19 15:00:00',
        ]);
        $broadcaster = Broadcaster::factory()->create([
            'name' => 'Premiere',
            'slug' => 'premiere',
            'type' => Broadcaster::TYPE_TV_CLOSED,
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateFixtureBroadcast::class)
            ->fillForm([
                'football_fixture_id' => $fixture->id,
                'broadcaster_id' => $broadcaster->id,
                'access_type' => FixtureBroadcast::ACCESS_PAY_PER_VIEW,
                'source_type' => FixtureBroadcast::SOURCE_OPENAI,
                'country_code' => 'BR',
                'source_url' => 'https://example.com/agenda',
                'confidence' => 0.95,
                'needs_review' => false,
                'notes' => 'Revisado manualmente.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fixture_broadcasts', [
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'source_type' => FixtureBroadcast::SOURCE_MANUAL,
            'needs_review' => false,
            'source_url' => 'https://example.com/agenda',
        ]);

        $this->assertDatabaseHas('broadcast_sources', [
            'football_fixture_id' => $fixture->id,
            'provider' => BroadcastSource::PROVIDER_MANUAL,
            'result_status' => BroadcastSource::RESULT_FOUND,
            'selected' => true,
        ]);
    }

    public function test_manual_broadcast_form_validates_url_and_enums(): void
    {
        $fixture = FootballFixture::factory()->create();
        $broadcaster = Broadcaster::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateFixtureBroadcast::class)
            ->fillForm([
                'football_fixture_id' => $fixture->id,
                'broadcaster_id' => $broadcaster->id,
                'access_type' => 'invalid-access',
                'source_type' => 'invalid-source',
                'country_code' => 'BR',
                'source_url' => 'javascript:alert(1)',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'access_type',
                'source_type',
                'source_url',
            ]);
    }

    public function test_manual_broadcast_is_not_overwritten_by_automatic_resolution(): void
    {
        $fixture = FootballFixture::factory()->create();
        $broadcaster = Broadcaster::factory()->create([
            'name' => 'SporTV',
            'slug' => 'sportv',
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'source_type' => FixtureBroadcast::SOURCE_MANUAL,
            'source_url' => 'https://example.com/manual',
            'needs_review' => false,
        ]);

        $this->app->bind(BroadcastFinder::class, fn () => new class implements BroadcastFinder
        {
            public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
            {
                return new BroadcastSearchResult(
                    provider: BroadcastSource::PROVIDER_THESPORTSDB,
                    status: BroadcastSource::RESULT_FOUND,
                    queryHash: 'automatic-'.$fixture->id,
                    channels: [
                        new BroadcastChannelData(
                            name: 'SporTV',
                            type: Broadcaster::TYPE_TV_CLOSED,
                            accessType: FixtureBroadcast::ACCESS_SUBSCRIPTION,
                            sourceUrl: 'https://example.com/automatic',
                        ),
                    ],
                    evidence: [[
                        'url' => 'https://example.com/automatic',
                        'publisher' => 'TheSportsDB',
                        'summary' => 'Fonte automatica.',
                    ]],
                    externalEventId: 'event-1',
                    calculatedConfidence: 0.9,
                    matchScore: 0.9,
                );
            }
        });

        app(BroadcastResolver::class)->resolve([$fixture]);

        $this->assertDatabaseHas('fixture_broadcasts', [
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'source_type' => FixtureBroadcast::SOURCE_MANUAL,
            'source_url' => 'https://example.com/manual',
            'needs_review' => false,
        ]);
    }

    public function test_admin_approves_and_publishes_fixture_from_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $fixture = FootballFixture::factory()->create([
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'published_at' => null,
        ]);

        FixtureBroadcast::factory()->create([
            'football_fixture_id' => $fixture->id,
            'needs_review' => true,
        ]);

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->actingAs($admin);

        Livewire::test(ListFootballFixtures::class)
            ->callTableAction('approve_review', $fixture)
            ->assertHasNoTableActionErrors();

        $fixture->refresh();

        $this->assertSame(FootballFixture::REVIEW_APPROVED, $fixture->review_status);
        $this->assertSame(FootballFixture::PUBLICATION_PUBLISHED, $fixture->publication_status);
        $this->assertSame($admin->id, $fixture->approved_by);
        $this->assertNotNull($fixture->approved_at);
        $this->assertNotNull($fixture->published_at);
        $this->assertFalse($fixture->fixtureBroadcasts()->where('needs_review', true)->exists());
        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }

    public function test_admin_approves_fixture_broadcast_from_panel(): void
    {
        $broadcast = FixtureBroadcast::factory()->create([
            'needs_review' => true,
            'verified_at' => null,
            'review_status' => FixtureBroadcast::REVIEW_PENDING,
            'publication_status' => FixtureBroadcast::PUBLICATION_DRAFT,
        ]);

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListFixtureBroadcasts::class)
            ->callTableAction('approve', $broadcast)
            ->assertHasNoTableActionErrors();

        $broadcast->refresh();

        $this->assertFalse($broadcast->needs_review);
        $this->assertNotNull($broadcast->verified_at);
        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }

    public function test_admin_can_unpublish_fixture_from_panel(): void
    {
        $fixture = FootballFixture::factory()->create([
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
        ]);

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListFootballFixtures::class)
            ->callTableAction('unpublish', $fixture)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('football_fixtures', [
            'id' => $fixture->id,
            'publication_status' => FootballFixture::PUBLICATION_UNPUBLISHED,
        ]);
        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }

    public function test_admin_can_unpublish_fixture_broadcast_from_panel(): void
    {
        $broadcast = FixtureBroadcast::factory()->create([
            'needs_review' => false,
            'review_status' => FixtureBroadcast::REVIEW_APPROVED,
            'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
        ]);

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListFixtureBroadcasts::class)
            ->callTableAction('unpublish', $broadcast)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('fixture_broadcasts', [
            'id' => $broadcast->id,
            'publication_status' => FixtureBroadcast::PUBLICATION_UNPUBLISHED,
        ]);
        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }

    public function test_fixture_edit_screen_registers_linked_broadcasts_section(): void
    {
        $fixture = FootballFixture::factory()->create();
        FixtureBroadcast::factory()->create(['football_fixture_id' => $fixture->id]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditFootballFixture::class, ['record' => $fixture->getRouteKey()])
            ->assertOk();

        $this->assertContains(
            FixtureBroadcastsRelationManager::class,
            FootballFixtureResource::getRelations(),
        );
    }
}
