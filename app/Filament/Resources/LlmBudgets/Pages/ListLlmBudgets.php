<?php

namespace App\Filament\Resources\LlmBudgets\Pages;

use App\Filament\Resources\LlmBudgets\LlmBudgetResource;
use App\Models\LlmBudget;
use Filament\Resources\Pages\ListRecords;

class ListLlmBudgets extends ListRecords
{
    protected static string $resource = LlmBudgetResource::class;

    public function mount(): void
    {
        parent::mount();

        foreach (['openai', 'gemini'] as $provider) {
            LlmBudget::ensureFor($provider);
        }
    }
}
