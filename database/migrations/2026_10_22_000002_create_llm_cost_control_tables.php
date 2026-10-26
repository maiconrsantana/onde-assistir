<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('enabled')->default(true);
            $table->decimal('daily_limit', 10, 4)->nullable();
            $table->decimal('monthly_limit', 10, 4)->nullable();
            $table->decimal('max_per_request', 10, 4)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('llm_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->index();
            $table->string('model')->nullable();
            $table->foreignId('football_fixture_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('success')->index();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage_records');
        Schema::dropIfExists('llm_budgets');
    }
};
