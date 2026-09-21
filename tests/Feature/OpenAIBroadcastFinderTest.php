<?php

namespace Tests\Feature;

use App\Exceptions\BroadcastFinderException;
use App\Integrations\OpenAI\OpenAIBroadcastFinder;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\Competition;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIBroadcastFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_finder_maps_structured_response_with_web_evidence(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response($this->openAiResponse([
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
                    'summary' => 'Confirma a transmissao no Premiere.',
                ]],
                'summary' => 'Transmissao confirmada no Premiere.',
                'confidence' => 0.88,
            ])),
        ]);

        $result = $this->finder()->findForFixture($this->fixture());

        $this->assertSame(BroadcastSource::RESULT_FOUND, $result->status);
        $this->assertSame(BroadcastSource::PROVIDER_OPENAI, $result->provider);
        $this->assertSame('gpt-test', $result->model);
        $this->assertSame(123, $result->tokensUsed);
        $this->assertSame(1, $result->webSearchCalls);
        $this->assertSame(0.88, $result->providerConfidence);
        $this->assertCount(1, $result->channels);
        $this->assertSame('Premiere', $result->channels[0]->name);
        $this->assertSame(Broadcaster::TYPE_TV_CLOSED, $result->channels[0]->type);
        $this->assertSame(FixtureBroadcast::ACCESS_PAY_PER_VIEW, $result->channels[0]->accessType);
        $this->assertNotEmpty($result->evidence);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'gpt-test'
            && $request['tools'][0]['type'] === 'web_search_preview'
            && $request['tools'][0]['search_context_size'] === 'low'
            && $request['text']['format']['type'] === 'json_schema');
    }

    public function test_found_without_evidence_becomes_uncertain(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response($this->openAiResponse([
                'status' => 'found',
                'channels' => [[
                    'name' => 'Canal sem fonte',
                    'type' => 'other',
                    'access_type' => 'unknown',
                ]],
                'evidence' => [],
                'summary' => 'Resposta sem evidencia rastreavel.',
                'confidence' => 0.6,
            ], includeCitation: false)),
        ]);

        $result = $this->finder()->findForFixture($this->fixture());

        $this->assertSame(BroadcastSource::RESULT_UNCERTAIN, $result->status);
        $this->assertSame([], $result->channels);
        $this->assertSame([], $result->evidence);
        $this->assertSame(0.0, $result->calculatedConfidence);
    }

    public function test_invalid_json_response_throws_controlled_exception(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp-invalid',
                'status' => 'completed',
                'output_text' => 'not json',
            ]),
        ]);

        $this->expectException(BroadcastFinderException::class);

        $this->finder()->findForFixture($this->fixture());
    }

    private function finder(): OpenAIBroadcastFinder
    {
        return new OpenAIBroadcastFinder(
            baseUrl: 'https://api.openai.test/v1',
            key: 'test-key',
            model: 'gpt-test',
        );
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    private function openAiResponse(array $structured, bool $includeCitation = true): array
    {
        return [
            'id' => 'resp-test',
            'status' => 'completed',
            'model' => 'gpt-test',
            'output_text' => json_encode($structured, JSON_THROW_ON_ERROR),
            'usage' => [
                'total_tokens' => 123,
            ],
            'output' => [[
                'type' => 'web_search_call',
                'action' => [
                    'type' => 'search',
                    'sources' => $includeCitation ? [[
                        'type' => 'url',
                        'url' => 'https://ge.globo.com/agenda/jogo-exemplo',
                    ]] : [],
                ],
            ]],
        ];
    }

    private function fixture(): FootballFixture
    {
        $competition = Competition::factory()->create([
            'name' => 'Serie A',
            'season_name' => '2026',
        ]);

        $homeTeam = Team::factory()->create([
            'name' => 'Corinthians',
        ]);

        $awayTeam = Team::factory()->create([
            'name' => 'Palmeiras',
        ]);

        return FootballFixture::factory()->create([
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'round' => 'Rodada 1',
            'starts_at' => '2026-09-18 15:00:00',
        ]);
    }
}
