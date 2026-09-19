<?php

namespace App\Services\Publication;

use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Models\User;
use App\Services\Operations\PublicScheduleCache;
use Illuminate\Support\Facades\DB;

class FixturePublicationApprover
{
    public function __construct(
        private readonly PublicScheduleCache $cache,
    ) {}

    public function approveAndPublish(FootballFixture $fixture, ?User $user = null): void
    {
        DB::transaction(function () use ($fixture, $user): void {
            $now = now()->utc();

            $fixture->fixtureBroadcasts()->update([
                'needs_review' => false,
                'review_status' => FixtureBroadcast::REVIEW_APPROVED,
                'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
            ]);

            $fixture->forceFill([
                'review_status' => FootballFixture::REVIEW_APPROVED,
                'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
                'approved_by' => $user?->id,
                'approved_at' => $fixture->approved_at ?? $now,
                'published_at' => $fixture->published_at ?? $now,
            ])->save();
        });

        $this->cache->invalidate();
    }
}
