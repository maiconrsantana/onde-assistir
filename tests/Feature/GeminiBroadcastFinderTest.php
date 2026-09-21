<?php

namespace Tests\Feature;

use App\Integrations\Gemini\GeminiBroadcastFinder;
use App\Models\BroadcastSource;
use App\Models\Competition;
use App\Models\FootballFixture;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiBroadcastFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_finder_normalizes_gemini_response_and_grounding_sources(): void
    {
        Http::fake(['https://gemini.test/*' => Http::response([
            'modelVersion' => 'gemini-test',
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'status' => 'found',
                    'channels' => [['name' => 'Premiere', 'type' => 'tv_closed', 'access_type' => 'subscription']],
                    'evidence' => [],
                    'summary' => 'Transmissao confirmada.',
                    'confidence' => 0.84,
                ], JSON_THROW_ON_ERROR)]]],
                'groundingMetadata' => ['groundingChunks' => [[
                    'web' => ['uri' => 'https://ge.globo.com/agenda/jogo', 'title' => 'ge'],
                ]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 80],
        ])]);

        $finder = new GeminiBroadcastFinder('https://gemini.test', 'test-key', 'gemini-test');
        $result = $finder->findForFixture($this->fixture());

        $this->assertSame(BroadcastSource::PROVIDER_GEMINI, $result->provider);
        $this->assertSame(BroadcastSource::RESULT_FOUND, $result->status);
        $this->assertSame('Premiere', $result->channels[0]->name);
        $this->assertSame(80, $result->tokensUsed);
        $this->assertCount(1, $result->evidence);
        $this->assertSame(1, $result->webSearchCalls);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://gemini.test/models/gemini-test:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && isset($request['tools'][0]['google_search'])
            && $request['generationConfig']['responseMimeType'] === 'application/json');
    }

    private function fixture(): FootballFixture
    {
        $competition = Competition::factory()->create(['name' => 'Serie A', 'season_name' => '2026']);
        $home = Team::factory()->create(['name' => 'Corinthians']);
        $away = Team::factory()->create(['name' => 'Palmeiras']);

        return FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'round' => 'Rodada 1',
            'starts_at' => '2026-09-18 15:00:00',
        ]);
    }
}
