<?php

namespace App\Data\Broadcast;

final readonly class BroadcastChannelData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $accessType,
        public string $countryCode = 'BR',
        public ?string $sourceUrl = null,
    ) {}
}
