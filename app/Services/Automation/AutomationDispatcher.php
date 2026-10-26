<?php

namespace App\Services\Automation;

use App\Enums\AutomationKey;
use App\Enums\AutomationTriggerType;
use App\Exceptions\AutomationExecutionException;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class AutomationDispatcher
{
    /** @param array<string, mixed> $parameters */
    public function run(
        AutomationKey $key,
        array $parameters = [],
        ?User $user = null,
        AutomationTriggerType $triggerType = AutomationTriggerType::Manual,
    ): AutomationRun {
        return app(AutomationRunner::class)->run(
            $key,
            function () use ($key, $parameters): array {
                $command = $this->commandFor($key);
                $exitCode = Artisan::call($command, $parameters);

                if ($exitCode !== 0) {
                    $output = Artisan::output();

                    throw new AutomationExecutionException(
                        "A automacao {$key->value} terminou com codigo {$exitCode}.",
                        [
                            'command' => $command,
                            'exit_code' => $exitCode,
                            'output' => $output,
                        ],
                    );
                }

                return [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ];
            },
            $parameters,
            $user,
            $triggerType,
        );
    }

    private function commandFor(AutomationKey $key): string
    {
        return match ($key) {
            AutomationKey::SyncFixtures => 'football:sync',
            AutomationKey::ResolveSportsDbBroadcasts => 'football:resolve-broadcasts',
            AutomationKey::ResolveGeminiBroadcasts,
            AutomationKey::ResolveOpenAIBroadcasts => 'football:resolve-openai-broadcasts',
            AutomationKey::RefreshBroadcasts => 'football:refresh-broadcasts',
        };
    }
}
