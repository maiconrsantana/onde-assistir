<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usage_records', function (Blueprint $table): void {
            $table->decimal('estimated_cost', 14, 8)->default(0)->change();
        });

        Schema::table('llm_budgets', function (Blueprint $table): void {
            $table->decimal('daily_limit', 14, 8)->nullable()->change();
            $table->decimal('monthly_limit', 14, 8)->nullable()->change();
            $table->decimal('max_per_request', 14, 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('llm_usage_records', function (Blueprint $table): void {
            $table->decimal('estimated_cost', 10, 6)->default(0)->change();
        });

        Schema::table('llm_budgets', function (Blueprint $table): void {
            $table->decimal('daily_limit', 10, 4)->nullable()->change();
            $table->decimal('monthly_limit', 10, 4)->nullable()->change();
            $table->decimal('max_per_request', 10, 4)->nullable()->change();
        });
    }
};
