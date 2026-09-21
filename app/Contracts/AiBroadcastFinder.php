<?php

namespace App\Contracts;

interface AiBroadcastFinder extends BroadcastFinder
{
    public function provider(): string;
}
