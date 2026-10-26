<?php

namespace App\Services\PublicSchedule;

use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class PublishedFixtureSchedule
{
    public function __construct(
        private readonly PublicScheduleCache $cache,
    ) {}

    /**
     * @return Collection<int, FootballFixture>
     */
    public function upcoming(): Collection
    {
        return $this->cache->remember(function (): Collection {
            $timezone = config('app.timezone', 'America/Sao_Paulo');

            return $this->queryBetween(
                CarbonImmutable::now($timezone)->startOfDay(),
                CarbonImmutable::now($timezone)->addDays(30)->endOfDay(),
            );
        });
    }

    /** @return Collection<int, FootballFixture> */
    public function publishedBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->queryBetween($from, $to);
    }

    /** @return Collection<int, FootballFixture> */
    private function queryBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return FootballFixture::query()
            ->with([
                'competition',
                'homeTeam',
                'awayTeam',
                'fixtureBroadcasts' => fn ($query) => $query
                    ->where('country_code', 'BR')
                    ->where('needs_review', false)
                    ->where('review_status', FixtureBroadcast::REVIEW_APPROVED)
                    ->where('publication_status', FixtureBroadcast::PUBLICATION_PUBLISHED)
                    ->with('broadcaster')
                    ->orderBy('access_type')
                    ->orderBy('id'),
            ])
            ->where('publication_status', FootballFixture::PUBLICATION_PUBLISHED)
            ->where('review_status', FootballFixture::REVIEW_APPROVED)
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->orderBy('starts_at')
            ->limit(120)
            ->get();

    }

    /**
     * @param  Collection<int, FootballFixture>  $fixtures
     * @return array<string, Collection<int, FootballFixture>>
     */
    public function groupByLocalDate(Collection $fixtures): array
    {
        return $fixtures
            ->groupBy(fn (FootballFixture $fixture): string => $fixture->starts_at
                ->copy()
                ->timezone(config('app.timezone', 'America/Sao_Paulo'))
                ->toDateString())
            ->all();
    }

    public function accessLabel(FixtureBroadcast $broadcast): string
    {
        return match ($broadcast->access_type) {
            FixtureBroadcast::ACCESS_FREE => 'Gratuito',
            FixtureBroadcast::ACCESS_SUBSCRIPTION => 'Assinatura',
            FixtureBroadcast::ACCESS_PAY_PER_VIEW => 'Pay-per-view',
            default => 'A confirmar',
        };
    }

    public function broadcasterTypeLabel(string $type): string
    {
        return match ($type) {
            'tv_open' => 'TV aberta',
            'tv_closed' => 'TV fechada',
            'streaming' => 'Streaming',
            'youtube' => 'YouTube',
            default => 'Outro',
        };
    }
}
