<?php

namespace App\Enums;

enum AutomationKey: string
{
    case SyncFixtures = 'sync_fixtures';
    case ResolveSportsDbBroadcasts = 'resolve_sportsdb_broadcasts';
    case ResolveGeminiBroadcasts = 'resolve_gemini_broadcasts';
    case ResolveOpenAIBroadcasts = 'resolve_openai_broadcasts';
    case RefreshBroadcasts = 'refresh_broadcasts';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return match ($this) {
            self::SyncFixtures => [
                'key' => $this->value,
                'name' => 'Sincronizar partidas',
                'description' => 'Busca partidas e competicoes no provedor esportivo.',
                'schedule' => 'dailyAt:06:00',
                'schedule_days' => [1, 2, 3, 4, 5, 6, 7],
                'schedule_time' => '06:00:00',
            ],
            self::ResolveSportsDbBroadcasts => [
                'key' => $this->value,
                'name' => 'Buscar transmissoes na TheSportsDB',
                'description' => 'Consulta transmissoes na fonte esportiva.',
                'schedule' => null,
            ],
            self::ResolveGeminiBroadcasts => [
                'key' => $this->value,
                'name' => 'Resolver transmissoes com Gemini',
                'description' => 'Pesquisa transmissoes ausentes usando Gemini.',
                'schedule' => null,
            ],
            self::ResolveOpenAIBroadcasts => [
                'key' => $this->value,
                'name' => 'Resolver transmissoes com OpenAI',
                'description' => 'Pesquisa transmissoes ausentes usando OpenAI.',
                'schedule' => null,
            ],
            self::RefreshBroadcasts => [
                'key' => $this->value,
                'name' => 'Atualizar transmissoes',
                'description' => 'Executa o fluxo configurado de atualizacao de transmissoes.',
                'schedule' => 'dailyAt:16:00',
                'schedule_days' => [1, 2, 3, 4, 5, 6, 7],
                'schedule_time' => '16:00:00',
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return array_map(
            static fn (self $key): array => $key->definition(),
            self::cases(),
        );
    }
}
