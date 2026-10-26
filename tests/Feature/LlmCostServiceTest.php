<?php

namespace Tests\Feature;

use App\Exceptions\LlmBudgetExceededException;
use App\Models\FootballFixture;
use App\Models\LlmBudget;
use App\Models\LlmUsageRecord;
use App\Services\Llm\LlmCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LlmCostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_normalized_usage_and_estimated_cost(): void
    {
        Config::set('services.llm_prices.openai', [
            'input_per_million' => 10,
            'output_per_million' => 20,
        ]);

        $fixture = FootballFixture::factory()->create();
        $record = app(LlmCostService::class)->recordSuccess('openai', 'gpt-test', $fixture, [
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500, 'total_tokens' => 1500],
        ]);

        $this->assertSame(1000, $record->input_tokens);
        $this->assertSame(500, $record->output_tokens);
        $this->assertSame(1500, $record->total_tokens);
        $this->assertEquals(0.02, (float) $record->estimated_cost);
    }

    public function test_blocks_call_when_daily_budget_would_be_exceeded(): void
    {
        LlmBudget::ensureFor('gemini')->update([
            'daily_limit' => 0.10,
            'max_per_request' => 0.05,
        ]);

        LlmUsageRecord::query()->create([
            'provider' => 'gemini',
            'estimated_cost' => 0.06,
            'status' => 'success',
        ]);

        $this->expectException(LlmBudgetExceededException::class);

        app(LlmCostService::class)->assertCanCall('gemini');
    }
}
