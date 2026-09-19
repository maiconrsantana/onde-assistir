<?php

namespace Tests\Feature;

use App\Models\BroadcastSource;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RawDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_data_command_is_dry_run_by_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'America/Sao_Paulo'));
        $fixture = FootballFixture::factory()->create([
            'starts_at' => now()->utc()->subDays(120),
        ]);
        BroadcastSource::factory()->create([
            'football_fixture_id' => $fixture->id,
            'queried_at' => now()->utc()->subDays(120),
        ]);

        $this->artisan('football:prune-raw-data', ['--days' => 90])
            ->expectsOutputToContain('Simulacao concluida')
            ->assertExitCode(0);

        $this->assertNotNull($fixture->fresh()->raw_payload);
        Carbon::setTestNow();
    }

    public function test_raw_data_command_removes_expired_payloads_only_when_requested(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'America/Sao_Paulo'));
        $oldFixture = FootballFixture::factory()->create([
            'starts_at' => now()->utc()->subDays(120),
        ]);
        $recentFixture = FootballFixture::factory()->create([
            'starts_at' => now()->utc()->subDays(30),
        ]);
        $oldMapping = FixtureProviderMapping::factory()->create([
            'football_fixture_id' => $oldFixture->id,
            'matched_at' => now()->utc()->subDays(120),
        ]);
        $oldSource = BroadcastSource::factory()->create([
            'football_fixture_id' => $oldFixture->id,
            'queried_at' => now()->utc()->subDays(120),
        ]);

        $this->artisan('football:prune-raw-data', [
            '--days' => 90,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertNull($oldFixture->fresh()->raw_payload);
        $this->assertNotNull($recentFixture->fresh()->raw_payload);
        $this->assertNull(DB::table('fixture_provider_mappings')->where('id', $oldMapping->id)->value('raw_payload'));
        $this->assertNull(DB::table('broadcast_sources')->where('id', $oldSource->id)->value('raw_response'));
        Carbon::setTestNow();
    }
}
