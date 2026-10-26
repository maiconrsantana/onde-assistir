<?php

namespace App\Models;

use App\Enums\AutomationKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'options' => 'array',
            'schedule_days' => 'array',
            'schedule_time' => 'string',
            'last_dispatched_at' => 'datetime',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AutomationRun>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'automation_key', 'key');
    }

    public static function ensureFor(AutomationKey $key): self
    {
        $definition = $key->definition();

        return self::query()->firstOrCreate(
            ['key' => $key->value],
            array_merge(['enabled' => true], $definition),
        );
    }
}
