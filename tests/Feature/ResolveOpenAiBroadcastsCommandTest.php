<?php

namespace Tests\Feature;

use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResolveOpenAiBroadcastsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-18 12:00:00', 'America/Sao_Paulo'));
        Config::set('services.ai.broadcast_provider', 'openai');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_processes_only_fixtures_that_need_openai_fallback(): void
    {
        Config::set('services.openai.base_url', 'https://api.openai.test/v1');
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-test');

        $target = FootballFixture::factory()->create([
            'starts_at' => '2026-09-19 15:00:00',
            'resolution_status' => FootballFixture::RESOLUTION_NOT_FOUND,
        ]);

        FootballFixture::factory()->create([
            'starts_at' => '2026-09-19 18:00:00',
            'resolution_status' => FootballFixture::RESOLUTION_PENDING,
        ]);

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp-test',
                'status' => 'completed',
                'model' => 'gpt-test',
                'output_text' => json_encode([
                    'status' => 'found',
                    'channels' => [[
                        'name' => 'Premiere',
                        'type' => 'tv_closed',
                        'access_type' => 'pay_per_view',
                    ]],
                    'evidence' => [[
                        'url' => 'https://ge.globo.com/agenda/jogo-exemplo',
                        'publisher' => 'ge',
                        'published_at' => null,
                        'summary' => 'Confirma a transmissao.',
                    ]],
                    'summary' => 'Transmissao confirmada.',
                    'confidence' => 0.8,
                ], JSON_THROW_ON_ERROR),
                'usage' => [
                    'total_tokens' => 50,
                ],
                'output' => [],
            ]),
        ]);

        $this->artisan('football:resolve-openai-broadcasts', [
            '--from' => '2026-09-19',
            '--to' => '2026-09-19',
        ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('broadcast_sources', [
            'football_fixture_id' => $target->id,
            'provider' => BroadcastSource::PROVIDER_OPENAI,
            'result_status' => BroadcastSource::RESULT_FOUND,
            'selected' => true,
        ]);

        $this->assertDatabaseHas('football_fixtures', [
            'id' => $target->id,
            'resolution_status' => FootballFixture::RESOLUTION_RESOLVED,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);

        $this->assertDatabaseHas('fixture_broadcasts', [
            'football_fixture_id' => $target->id,
            'source_type' => FixtureBroadcast::SOURCE_OPENAI,
        ]);

        Http::assertSentCount(1);
    }

    public function test_command_respects_recent_openai_search_ttl(): void
    {
        Config::set('services.openai.base_url', 'https://api.openai.test/v1');
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-test');

        $fixture = FootballFixture::factory()->create([
            'starts_at' => '2026-09-19 15:00:00',
            'resolution_status' => FootballFixture::RESOLUTION_NOT_FOUND,
        ]);

        BroadcastSource::factory()->create([
            'football_fixture_id' => $fixture->id,
            'provider' => BroadcastSource::PROVIDER_OPENAI,
            'result_status' => BroadcastSource::RESULT_NOT_FOUND,
            'query_hash' => 'recent-openai-search',
            'queried_at' => now()->utc(),
        ]);

        Http::fake();

        $this->artisan('football:resolve-openai-broadcasts', [
            '--from' => '2026-09-19',
            '--to' => '2026-09-19',
        ])
            ->assertExitCode(0);

        Http::assertNothingSent();
    }
}
