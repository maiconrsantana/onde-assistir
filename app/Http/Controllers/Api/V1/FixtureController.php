<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\FixtureResource;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Services\PublicSchedule\PublishedFixtureSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class FixtureController
{
    public function index(Request $request, PublishedFixtureSchedule $schedule): AnonymousResourceCollection
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'competition_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $fixtures = $schedule->upcoming();

        $fixtures = $fixtures
            ->when($request->filled('date'), fn ($items) => $items->filter(
                fn (FootballFixture $fixture): bool => $fixture->starts_at
                    ->timezone('America/Sao_Paulo')
                    ->toDateString() === $request->string('date')->toString()
            ))
            ->when($request->filled('competition_id'), fn ($items) => $items->where(
                'competition_id',
                $request->integer('competition_id')
            ))
            ->when($request->filled('team_id'), fn ($items) => $items->filter(
                fn (FootballFixture $fixture): bool => in_array(
                    $request->integer('team_id'),
                    [$fixture->home_team_id, $fixture->away_team_id],
                    true
                )
            ))
            ->values();

        $perPage = $request->integer('per_page', 30);
        $page = $request->integer('page', 1);
        $paginator = new LengthAwarePaginator(
            $fixtures->forPage($page, $perPage)->values(),
            $fixtures->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return FixtureResource::collection($paginator);
    }

    public function show(FootballFixture $fixture): JsonResource
    {
        abort_unless(
            $fixture->review_status === FootballFixture::REVIEW_APPROVED
                && $fixture->publication_status === FootballFixture::PUBLICATION_PUBLISHED,
            404
        );

        $fixture->load([
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
        ]);

        return new FixtureResource($fixture);
    }
}
