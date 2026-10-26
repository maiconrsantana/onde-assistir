<?php

namespace App\Jobs;

use App\Enums\AutomationKey;
use App\Enums\AutomationTriggerType;
use App\Models\User;
use App\Services\Automation\AutomationDispatcher;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param array<string, mixed> $parameters */
    public function __construct(
        public AutomationKey $key,
        public array $parameters = [],
        public ?int $userId = null,
        public AutomationTriggerType $triggerType = AutomationTriggerType::Manual,
    ) {
        $this->onQueue('automations');
    }

    public function handle(AutomationDispatcher $dispatcher): void
    {
        $user = $this->userId ? User::find($this->userId) : null;
        $users = $this->userId
            ? User::query()->whereKey($this->userId)->get()
            : User::query()->where('is_admin', true)->get();

        try {
            $run = $dispatcher->run($this->key, $this->parameters, $user, $this->triggerType);
            $output = str((string) data_get($run->result, 'output', 'Sem detalhes de saída.'))->squish()->limit(500);

            foreach ($users as $user) {
                Notification::make()
                    ->title('Automação concluída')
                    ->body("{$this->key->definition()['name']} terminou com status {$run->status->value}. {$output}")
                    ->success()
                    ->sendToDatabase($user, isEventDispatched: true);
            }
        } catch (Throwable $exception) {
            foreach ($users as $user) {
                Notification::make()
                    ->title('Falha na automação')
                    ->body("{$this->key->definition()['name']}: {$exception->getMessage()}")
                    ->danger()
                    ->sendToDatabase($user, isEventDispatched: true);
            }

            throw $exception;
        }
    }
}
