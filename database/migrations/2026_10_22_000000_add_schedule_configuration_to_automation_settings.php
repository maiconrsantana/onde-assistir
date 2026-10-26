<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_settings', function (Blueprint $table): void {
            $table->json('schedule_days')->nullable()->after('schedule');
            $table->time('schedule_time')->nullable()->after('schedule_days');
            $table->timestamp('last_dispatched_at')->nullable()->after('last_run_at');
        });

        DB::table('automation_settings')->where('key', 'sync_fixtures')->update([
            'schedule_days' => json_encode([1, 2, 3, 4, 5, 6, 7]),
            'schedule_time' => '06:00:00',
        ]);

        DB::table('automation_settings')->where('key', 'refresh_broadcasts')->update([
            'schedule_days' => json_encode([1, 2, 3, 4, 5, 6, 7]),
            'schedule_time' => '16:00:00',
        ]);
    }

    public function down(): void
    {
        Schema::table('automation_settings', function (Blueprint $table): void {
            $table->dropColumn(['schedule_days', 'schedule_time', 'last_dispatched_at']);
        });
    }
};
