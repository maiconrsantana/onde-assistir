<?php

namespace App\Contracts;

use App\Data\Broadcast\BroadcastSearchResult;
use App\Models\FootballFixture;

interface BroadcastFinder
{
    public function findForFixture(FootballFixture $fixture): BroadcastSearchResult;
}
