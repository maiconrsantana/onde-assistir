<?php

namespace App\Console\Commands;

use App\Models\BroadcastSource;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneRawDataCommand extends Command
{
    protected $signature = 'football:prune-raw-data
        {--days=90 : Number of days to retain raw provider payloads}
        {--execute : Permanently remove payloads instead of only simulating}';

    protected $description = 'Simulate or remove expired raw provider payloads while preserving audit evidence.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = CarbonImmutable::now('UTC')->subDays($days);
        $counts = [
            'fixtures' => FootballFixture::query()
                ->whereNotNull('raw_payload')
                ->where('starts_at', '<', $cutoff)
                ->count(),
            'provider_mappings' => FixtureProviderMapping::query()
                ->whereNotNull('raw_payload')
                ->where('matched_at', '<', $cutoff)
                ->count(),
            'broadcast_sources' => BroadcastSource::query()
                ->whereNotNull('raw_response')
                ->where('queried_at', '<', $cutoff)
                ->count(),
        ];

        $this->table(['Tipo', 'Registros'], collect($counts)
            ->map(fn (int $count, string $type): array => [$type, $count])
            ->values()
            ->all());

        if (! $this->option('execute')) {
            $this->components->info("Simulacao concluida. Corte: {$cutoff->toIso8601String()}. Use --execute para remover os payloads.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($cutoff): void {
            FootballFixture::query()
                ->whereNotNull('raw_payload')
                ->where('starts_at', '<', $cutoff)
                ->update(['raw_payload' => null]);

            FixtureProviderMapping::query()
                ->whereNotNull('raw_payload')
                ->where('matched_at', '<', $cutoff)
                ->update(['raw_payload' => null]);

            BroadcastSource::query()
                ->whereNotNull('raw_response')
                ->where('queried_at', '<', $cutoff)
                ->update(['raw_response' => null]);
        });

        Log::notice('football.raw_data_pruned', [
            'days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'counts' => $counts,
        ]);
        $this->components->info('Limpeza de payloads concluida. Evidencias e dados normalizados foram preservados.');

        return self::SUCCESS;
    }
}
