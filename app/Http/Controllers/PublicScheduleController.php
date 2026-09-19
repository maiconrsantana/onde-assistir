<?php

namespace App\Http\Controllers;

use App\Services\PublicSchedule\PublishedFixtureSchedule;
use Illuminate\Contracts\View\View;

class PublicScheduleController extends Controller
{
    public function __invoke(PublishedFixtureSchedule $schedule): View
    {
        $fixtures = $schedule->upcoming();

        return view('public.schedule', [
            'fixtures' => $fixtures,
            'fixturesByDate' => $schedule->groupByLocalDate($fixtures),
            'schedule' => $schedule,
        ]);
    }
}
