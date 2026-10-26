<?php

namespace App\Services\Automation;

class AutomationRunContext
{
    private ?int $runId = null;

    public function set(int $runId): void
    {
        $this->runId = $runId;
    }

    public function id(): ?int
    {
        return $this->runId;
    }

    public function clear(): void
    {
        $this->runId = null;
    }
}
