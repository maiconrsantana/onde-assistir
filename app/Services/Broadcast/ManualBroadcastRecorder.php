<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Support\ExternalUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ManualBroadcastRecorder
{
    public function record(FixtureBroadcast $broadcast): void
    {
        $broadcast->loadMissing(['broadcaster', 'fixture']);
        $sourceUrl = ExternalUrl::normalize($broadcast->source_url);

        $source = BroadcastSource::create([
            'football_fixture_id' => $broadcast->football_fixture_id,
            'provider' => BroadcastSource::PROVIDER_MANUAL,
            'external_event_id' => null,
            'channels' => [[
                'name' => $broadcast->broadcaster?->name,
                'type' => $broadcast->broadcaster?->type,
                'access_type' => $broadcast->access_type,
                'country_code' => $broadcast->country_code,
                'source_url' => $sourceUrl,
            ]],
            'evidence' => $sourceUrl ? [[
                'url' => $sourceUrl,
                'publisher' => parse_url($sourceUrl, PHP_URL_HOST),
                'published_at' => null,
                'summary' => 'Fonte informada manualmente no painel administrativo.',
            ]] : [],
            'evidence_summary' => $broadcast->notes ?: 'Transmissao informada manualmente no painel administrativo.',
            'raw_response' => [
                'manual' => true,
                'fixture_broadcast_id' => $broadcast->id,
                'user_id' => Auth::id(),
            ],
            'calculated_confidence' => $broadcast->confidence,
            'result_status' => BroadcastSource::RESULT_FOUND,
            'selected' => true,
            'query_hash' => 'manual-'.Str::uuid()->toString(),
            'queried_at' => now()->utc(),
            'validated_at' => now()->utc(),
        ]);

        BroadcastSource::query()
            ->where('football_fixture_id', $broadcast->football_fixture_id)
            ->whereKeyNot($source->id)
            ->update(['selected' => false]);

        $broadcast->forceFill([
            'broadcast_source_id' => $source->id,
            'source_type' => FixtureBroadcast::SOURCE_MANUAL,
            'verified_at' => $broadcast->verified_at ?? now()->utc(),
        ])->saveQuietly();
    }
}
