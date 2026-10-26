<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmBudget extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'daily_limit' => 'decimal:4',
            'monthly_limit' => 'decimal:4',
            'max_per_request' => 'decimal:4',
        ];
    }

    public static function ensureFor(string $provider): self
    {
        return self::query()->firstOrCreate([
            'provider' => $provider,
        ], [
            'enabled' => true,
            'currency' => 'USD',
        ]);
    }
}
